<?php
/**
 * Plugin Name: Polylang Auto Translate All
 * Description: WP-CLI command to auto-translate posts and pages via Polylang Pro and DeepL REST API, reading settings from Polylang option and including ACF fields and taxonomies based on Polylang configuration.
 * Version: 1.4.0
 * Author: Dmitry Tishakov
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'polylang auto-translate-all', 'Polylang_Auto_Translate_All_Command' );
}

class Polylang_Auto_Translate_All_Command {
    /**
     * Auto translate all posts of a given type to a target language.
     *
     * ## OPTIONS
     *
     * [--post_type=<post_type>]
     * : Post type to process. Default 'post'.
     *
     * [--lang=<lang>]
     * : Language code to translate to. Default 'de'.
     *
     * ## EXAMPLES
     *
     *     wp polylang auto-translate-all --post_type=post --lang=de
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $post_type   = $assoc_args['post_type'] ?? 'post';
        $target_lang = strtolower( $assoc_args['lang'] ?? 'de' );

        if ( ! function_exists( 'pll_default_language' ) ) {
            WP_CLI::error( 'Polylang Pro is not active.' );
        }
        $source_lang = pll_default_language();

        $query = new WP_Query([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'lang'           => $source_lang,
            'post_status'    => 'publish',
        ]);
        if ( ! $query->have_posts() ) {
            WP_CLI::warning( 'No posts found for translation.' );
            return;
        }

        $copy_metas = (array) get_option( 'polylang_copy_post_metas', [] );
        $taxonomies = get_object_taxonomies( $post_type, 'names' );

        foreach ( $query->posts as $post ) {
            $translations = pll_get_post_translations( $post->ID );
            if ( ! empty( $translations[ $target_lang ] ) ) {
                WP_CLI::log( "Skipping #{$post->ID}: already has {$target_lang} translation." );
                continue;
            }

            // Build new post data
            $new = [
                'post_type'    => $post->post_type,
                'post_status'  => $post->post_status,
                'post_author'  => $post->post_author,
                'post_title'   => $this->translate_text( $post->post_title, $source_lang, $target_lang ),
                'post_content' => $this->translate_text( $post->post_content, $source_lang, $target_lang ),
                'post_excerpt' => $this->translate_text( $post->post_excerpt, $source_lang, $target_lang ),
            ];
            $new_id = wp_insert_post( $new );
            if ( is_wp_error( $new_id ) ) {
                WP_CLI::warning( "Error inserting translation for #{$post->ID}: {$new_id->get_error_message()}" );
                continue;
            }

            // Copy featured image
            $thumb = get_post_thumbnail_id( $post->ID );
            if ( $thumb ) {
                set_post_thumbnail( $new_id, $thumb );
            }

            // Copy or translate meta fields (including ACF)
            foreach ( get_post_meta( $post->ID ) as $meta_key => $values ) {
                if ( in_array( $meta_key, ['_edit_lock', '_edit_last'], true ) ) {
                    continue;
                }
                foreach ( $values as $value ) {
                    if ( in_array( $meta_key, $copy_metas, true ) ) {
                        update_post_meta( $new_id, $meta_key, maybe_unserialize( maybe_serialize( $value ) ) );
                    } else {
                        update_post_meta( $new_id, $meta_key, $this->translate_text( $value, $source_lang, $target_lang ) );
                    }
                }
            }

            // Copy taxonomy terms
            foreach ( $taxonomies as $tax ) {
                $term_ids = wp_get_object_terms( $post->ID, $tax, [ 'fields' => 'ids' ] );
                if ( ! empty( $term_ids ) ) {
                    $new_terms = [];
                    foreach ( $term_ids as $term_id ) {
                        $term_trans = pll_get_term_translations( $term_id );
                        if ( ! empty( $term_trans[ $target_lang ] ) ) {
                            $new_terms[] = $term_trans[ $target_lang ];
                        }
                    }
                    if ( ! empty( $new_terms ) ) {
                        wp_set_object_terms( $new_id, $new_terms, $tax, false );
                    }
                }
            }

            // Set language and save relationships
            pll_set_post_language( $new_id, $target_lang );
            $translations[ $source_lang ] = $post->ID;
            $translations[ $target_lang ] = $new_id;
            pll_save_post_translations( $translations );

            WP_CLI::success( "Translated #{$post->ID} → #{$new_id} ({$target_lang})." );
        }
    }

    /**
     * Translate text via DeepL API based on Polylang settings, fallback to original on error or disabled
     */
    private function translate_text( $text, $source, $target ) {
        if ( empty( trim( $text ) ) ) {
            return '';
        }

        $pll_opts = get_option( 'polylang', [] );
        if ( empty( $pll_opts['machine_translation_enabled'] ) ) {
            return $text;
        }
        $services  = $pll_opts['machine_translation_services'] ?? [];
        $deepl     = $services['deepl'] ?? [];
        $api_key   = $deepl['api_key'] ?? '';
        $formality = ! empty( $deepl['formality'] ) && 'default' !== $deepl['formality'] ? $deepl['formality'] : '';
        if ( ! $api_key ) {
            return $text;
        }

        $body = [
            'auth_key'    => $api_key,
            'text'        => $text,
            'source_lang' => strtoupper( $source ),
            'target_lang' => strtoupper( $target ),
        ];
        if ( $formality ) {
            $body['formality'] = $formality;
        }

        $response = wp_remote_post( 'https://api.deepl.com/v2/translate', [ 'body' => $body ] );
        if ( is_wp_error( $response ) ) {
            WP_CLI::warning( "DeepL request failed: " . $response->get_error_message() );
            return $text;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['translations'][0]['text'] ?? $text;
    }
}
