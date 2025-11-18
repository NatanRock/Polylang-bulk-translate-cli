<?php
/**
 * Plugin Name: Polylang Auto Translate All
 * Description: WP-CLI command to auto-translate posts and pages via Polylang Pro and DeepL REST API, reading settings from Polylang option and including ACF fields and taxonomies based on Polylang configuration.
 * Version: 2.0.0
 * Author: Dmitry Tishakov
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'polylang auto-translate-all', 'Polylang_Auto_Translate_All_Command' );
}

class Polylang_Auto_Translate_All_Command {
    
    /**
     * DeepL API rate limit delay in microseconds (0.5 seconds)
     */
    private const API_DELAY = 500000;
    
    /**
     * Maximum retry attempts for API calls
     */
    private const MAX_RETRIES = 3;
    
    /**
     * Posts per page for pagination
     */
    private const POSTS_PER_PAGE = 50;
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * DeepL API configuration
     */
    private $api_config = [];
    
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
     * [--dry-run]
     * : Preview translations without creating posts.
     *
     * [--per-page=<number>]
     * : Number of posts to process per page. Default 50.
     *
     * [--limit=<number>]
     * : Maximum number of posts to translate (useful for testing). If not specified, translates all posts.
     *
     * ## EXAMPLES
     *
     *     # Translate all posts to German
     *     wp polylang auto-translate-all --post_type=post --lang=de
     *
     *     # Preview translation without creating posts
     *     wp polylang auto-translate-all --post_type=page --lang=fr --dry-run
     *
     *     # Process 100 posts at a time
     *     wp polylang auto-translate-all --post_type=post --lang=es --per-page=100
     *
     *     # Translate only 3 posts for testing
     *     wp polylang auto-translate-all --post_type=post --lang=de --limit=3
     *
     *     # Test with dry-run for 5 posts
     *     wp polylang auto-translate-all --post_type=post --lang=de --limit=5 --dry-run
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $post_type   = $assoc_args['post_type'] ?? 'post';
        $target_lang = strtolower( $assoc_args['lang'] ?? 'de' );
        $dry_run     = isset( $assoc_args['dry-run'] );
        $per_page    = absint( $assoc_args['per-page'] ?? self::POSTS_PER_PAGE );
        $limit       = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : null;
        
        // Initialize log file
        $this->log_file = WP_CONTENT_DIR . '/polylang-translations-' . date( 'Y-m-d-H-i-s' ) . '.log';
        
        // Validate Polylang Pro
        if ( ! function_exists( 'pll_default_language' ) ) {
            WP_CLI::error( 'Polylang Pro is not active.' );
        }
        
        $source_lang = pll_default_language();
        
        // Initialize API configuration
        if ( ! $this->init_api_config() ) {
            WP_CLI::error( 'Machine translation is not enabled in Polylang settings or DeepL API key is missing.' );
        }
        
        // Get total count
        $count_query = new WP_Query([
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'lang'           => $source_lang,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);
        
        $total = $count_query->found_posts;
        
        if ( $total === 0 ) {
            WP_CLI::warning( 'No posts found for translation.' );
            return;
        }
        
        // Apply limit if specified
        $posts_to_process = $total;
        if ( $limit !== null && $limit > 0 ) {
            $posts_to_process = min( $limit, $total );
            WP_CLI::log( sprintf( 
                'Limit set to %d posts (total available: %d)', 
                $posts_to_process,
                $total
            ) );
        }
        
        WP_CLI::log( sprintf( 
            'Found %d posts to translate from %s to %s', 
            $posts_to_process, 
            $source_lang, 
            $target_lang 
        ) );
        
        if ( $dry_run ) {
            WP_CLI::log( '--- DRY RUN MODE (no posts will be created) ---' );
        }
        
        $this->log( sprintf( 
            'Translation started: %d posts | %s -> %s | Dry run: %s | Limit: %s',
            $posts_to_process,
            $source_lang,
            $target_lang,
            $dry_run ? 'Yes' : 'No',
            $limit !== null ? $limit : 'None'
        ) );
        
        // Get Polylang configuration
        $copy_metas = (array) get_option( 'polylang_copy_post_metas', [] );
        $taxonomies = get_object_taxonomies( $post_type, 'names' );
        
        // Initialize progress bar
        $progress = \WP_CLI\Utils\make_progress_bar( 'Translating posts', $posts_to_process );
        
        $page = 1;
        $translated_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $processed_count = 0;
        
        // Process posts in batches
        do {
            $query = new WP_Query([
                'post_type'      => $post_type,
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'lang'           => $source_lang,
                'post_status'    => 'publish',
            ]);
            
            if ( ! $query->have_posts() ) {
                break;
            }
            
            foreach ( $query->posts as $post ) {
                // Check if limit reached
                if ( $limit !== null && $processed_count >= $limit ) {
                    WP_CLI::log( sprintf( 
                        "\nLimit of %d posts reached. Stopping translation.", 
                        $limit 
                    ) );
                    break 2; // Break both foreach and do-while
                }
                
                $result = $this->process_post( 
                    $post, 
                    $source_lang, 
                    $target_lang, 
                    $copy_metas, 
                    $taxonomies,
                    $dry_run
                );
                
                if ( $result['status'] === 'translated' ) {
                    $translated_count++;
                } elseif ( $result['status'] === 'skipped' ) {
                    $skipped_count++;
                } else {
                    $error_count++;
                }
                
                $processed_count++;
                $progress->tick();
            }
            
            wp_reset_postdata();
            $page++;
            
            // Memory cleanup
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }
            
        } while ( $query->have_posts() );
        
        $progress->finish();
        
        // Summary
        WP_CLI::success( sprintf(
            'Translation complete! Processed: %d | Translated: %d | Skipped: %d | Errors: %d',
            $processed_count,
            $translated_count,
            $skipped_count,
            $error_count
        ) );
        
        WP_CLI::log( sprintf( 'Log file: %s', $this->log_file ) );
        
        $this->log( sprintf(
            'Translation finished. Processed: %d | Translated: %d | Skipped: %d | Errors: %d',
            $processed_count,
            $translated_count,
            $skipped_count,
            $error_count
        ) );
    }
    
    /**
     * Process single post translation
     */
    private function process_post( $post, $source_lang, $target_lang, $copy_metas, $taxonomies, $dry_run ) {
        // Check if translation already exists
        $translations = pll_get_post_translations( $post->ID );
        if ( ! empty( $translations[ $target_lang ] ) ) {
            $this->log( sprintf( 
                'Skipped #%d: already has %s translation (#%d)',
                $post->ID,
                $target_lang,
                $translations[ $target_lang ]
            ) );
            return [ 'status' => 'skipped' ];
        }
        
        if ( $dry_run ) {
            $this->log( sprintf( 
                'DRY RUN: Would translate #%d "%s"',
                $post->ID,
                $post->post_title
            ) );
            return [ 'status' => 'skipped' ];
        }
        
        try {
            // Prepare texts for batch translation
            $texts_to_translate = [];
            $text_keys = [];
            
            if ( ! empty( trim( $post->post_title ) ) ) {
                $texts_to_translate[] = $post->post_title;
                $text_keys[] = 'title';
            }
            
            if ( ! empty( trim( $post->post_content ) ) ) {
                $texts_to_translate[] = $post->post_content;
                $text_keys[] = 'content';
            }
            
            if ( ! empty( trim( $post->post_excerpt ) ) ) {
                $texts_to_translate[] = $post->post_excerpt;
                $text_keys[] = 'excerpt';
            }
            
            // Batch translate main content
            $translated_texts = $this->translate_texts_batch( 
                $texts_to_translate, 
                $source_lang, 
                $target_lang 
            );
            
            // Map translated texts back
            $translated_data = [
                'title'   => '',
                'content' => '',
                'excerpt' => '',
            ];
            
            foreach ( $text_keys as $index => $key ) {
                $translated_data[ $key ] = $translated_texts[ $index ] ?? '';
            }
            
            // Prepare translated terms map
            $terms_map = [];
            foreach ( $taxonomies as $tax ) {
                $term_ids = wp_get_object_terms( $post->ID, $tax, [ 'fields' => 'ids' ] );
                if ( empty( $term_ids ) || is_wp_error( $term_ids ) ) {
                    continue;
                }
                
                $translated_ids = [];
                foreach ( $term_ids as $term_id ) {
                    $term_trans = pll_get_term_translations( $term_id );
                    if ( ! empty( $term_trans[ $target_lang ] ) ) {
                        $translated_ids[] = $term_trans[ $target_lang ];
                    }
                }
                
                if ( $translated_ids ) {
                    $terms_map[ $tax ] = $translated_ids;
                }
            }
            
            // Build new post data
            $new_post = [
                'post_type'    => $post->post_type,
                'post_status'  => $post->post_status,
                'post_author'  => $post->post_author,
                'post_title'   => $translated_data['title'] ?: $post->post_title,
                'post_content' => $translated_data['content'] ?: $post->post_content,
                'post_excerpt' => $translated_data['excerpt'] ?: $post->post_excerpt,
            ];
            
            $new_id = wp_insert_post( $new_post );
            
            if ( is_wp_error( $new_id ) ) {
                throw new Exception( $new_id->get_error_message() );
            }
            
            // Assign taxonomy terms
            foreach ( $terms_map as $tax => $ids ) {
                if ( 'category' === $tax ) {
                    wp_set_post_categories( $new_id, $ids, false );
                } else {
                    wp_set_object_terms( $new_id, $ids, $tax, false );
                }
            }
            
            // Copy featured image
            if ( $thumb_id = get_post_thumbnail_id( $post->ID ) ) {
                set_post_thumbnail( $new_id, $thumb_id );
            }
            
            // Copy or translate meta fields (including ACF)
            $this->process_post_meta( $post->ID, $new_id, $copy_metas, $source_lang, $target_lang );
            
            // Set language and save relationships
            pll_set_post_language( $new_id, $target_lang );
            $translations[ $source_lang ] = $post->ID;
            $translations[ $target_lang ] = $new_id;
            pll_save_post_translations( $translations );
            
            // Force WP to refresh term relationships
            wp_update_post( [ 'ID' => $new_id ] );
            
            $this->log( sprintf(
                'Success: #%d "%s" -> #%d (%s)',
                $post->ID,
                $post->post_title,
                $new_id,
                $target_lang
            ) );
            
            return [ 
                'status' => 'translated',
                'new_id' => $new_id 
            ];
            
        } catch ( Exception $e ) {
            $error_msg = sprintf(
                'Error translating #%d: %s',
                $post->ID,
                $e->getMessage()
            );
            
            WP_CLI::warning( $error_msg );
            $this->log( $error_msg );
            
            return [ 
                'status' => 'error',
                'error'  => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process post meta fields (including ACF)
     */
    private function process_post_meta( $source_id, $target_id, $copy_metas, $source_lang, $target_lang ) {
        $all_meta = get_post_meta( $source_id );
        
        foreach ( $all_meta as $meta_key => $values ) {
            // Skip system meta
            if ( in_array( $meta_key, [ '_edit_lock', '_edit_last', '_wp_old_slug' ], true ) ) {
                continue;
            }
            
            foreach ( $values as $value ) {
                $value = maybe_unserialize( $value );
                
                // Check if this meta should be copied without translation
                if ( in_array( $meta_key, $copy_metas, true ) ) {
                    update_post_meta( $target_id, $meta_key, $value );
                } else {
                    // Translate the value
                    $translated_value = $this->translate_meta_value( $value, $source_lang, $target_lang );
                    update_post_meta( $target_id, $meta_key, $translated_value );
                }
            }
        }
    }
    
    /**
     * Translate meta value (handles arrays, strings, etc.)
     */
    private function translate_meta_value( $value, $source_lang, $target_lang ) {
        // Handle arrays (ACF repeaters, groups, etc.)
        if ( is_array( $value ) ) {
            return $this->translate_array_recursive( $value, $source_lang, $target_lang );
        }
        
        // Handle strings
        if ( is_string( $value ) && ! empty( trim( $value ) ) ) {
            // Check if it's JSON
            if ( $this->is_json( $value ) ) {
                $decoded = json_decode( $value, true );
                if ( is_array( $decoded ) ) {
                    $translated = $this->translate_array_recursive( $decoded, $source_lang, $target_lang );
                    return wp_json_encode( $translated );
                }
            }
            
            // Don't translate if it looks like a URL, number, or short code
            if ( $this->should_skip_translation( $value ) ) {
                return $value;
            }
            
            return $this->translate_text( $value, $source_lang, $target_lang );
        }
        
        // Return as-is for other types (int, bool, null, objects)
        return $value;
    }
    
    /**
     * Recursively translate array values
     */
    private function translate_array_recursive( $array, $source_lang, $target_lang ) {
        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {
                $array[ $key ] = $this->translate_array_recursive( $value, $source_lang, $target_lang );
            } elseif ( is_string( $value ) && ! empty( trim( $value ) ) ) {
                if ( ! $this->should_skip_translation( $value ) ) {
                    $array[ $key ] = $this->translate_text( $value, $source_lang, $target_lang );
                }
            }
        }
        return $array;
    }
    
    /**
     * Check if value should skip translation
     */
    private function should_skip_translation( $value ) {
        // Skip URLs
        if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return true;
        }
        
        // Skip numbers
        if ( is_numeric( $value ) ) {
            return true;
        }
        
        // Skip short strings (likely IDs or codes)
        if ( strlen( $value ) < 3 ) {
            return true;
        }
        
        // Skip email addresses
        if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            return true;
        }
        
        // Skip shortcodes
        if ( preg_match( '/\[.*\]/', $value ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if string is valid JSON
     */
    private function is_json( $string ) {
        if ( ! is_string( $string ) ) {
            return false;
        }
        json_decode( $string );
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Initialize DeepL API configuration from Polylang settings
     */
    private function init_api_config() {
        $pll_opts = get_option( 'polylang', [] );
        
        if ( empty( $pll_opts['machine_translation_enabled'] ) ) {
            return false;
        }
        
        $services  = $pll_opts['machine_translation_services'] ?? [];
        $deepl     = $services['deepl'] ?? [];
        $api_key   = $deepl['api_key'] ?? '';
        
        if ( ! $api_key ) {
            return false;
        }
        
        $this->api_config = [
            'api_key'   => $api_key,
            'formality' => ( ! empty( $deepl['formality'] ) && 'default' !== $deepl['formality'] ) 
                ? $deepl['formality'] 
                : '',
        ];
        
        return true;
    }
    
    /**
     * Translate multiple texts in a single API call
     */
    private function translate_texts_batch( array $texts, $source, $target ) {
        if ( empty( $texts ) ) {
            return [];
        }
        
        // Filter out empty texts but keep track of indices
        $texts_to_send = [];
        $text_indices = [];
        
        foreach ( $texts as $index => $text ) {
            if ( ! empty( trim( $text ) ) ) {
                $texts_to_send[] = $text;
                $text_indices[] = $index;
            }
        }
        
        if ( empty( $texts_to_send ) ) {
            return $texts;
        }
        
        $body = [
            'auth_key'    => $this->api_config['api_key'],
            'text'        => $texts_to_send,
            'source_lang' => strtoupper( $source ),
            'target_lang' => strtoupper( $target ),
        ];
        
        if ( ! empty( $this->api_config['formality'] ) ) {
            $body['formality'] = $this->api_config['formality'];
        }
        
        $attempt = 0;
        
        while ( $attempt < self::MAX_RETRIES ) {
            try {
                $response = wp_remote_post( 'https://api.deepl.com/v2/translate', [
                    'body'    => $body,
                    'timeout' => 30,
                ]);
                
                if ( is_wp_error( $response ) ) {
                    throw new Exception( 'HTTP request failed: ' . $response->get_error_message() );
                }
                
                $status = wp_remote_retrieve_response_code( $response );
                
                // Handle rate limiting
                if ( $status === 429 ) {
                    $wait_time = 60;
                    WP_CLI::warning( sprintf( 
                        'DeepL rate limit hit (attempt %d/%d), waiting %d seconds...', 
                        $attempt + 1,
                        self::MAX_RETRIES,
                        $wait_time
                    ) );
                    sleep( $wait_time );
                    $attempt++;
                    continue;
                }
                
                // Handle other HTTP errors
                if ( $status !== 200 ) {
                    $body_content = wp_remote_retrieve_body( $response );
                    throw new Exception( sprintf(
                        'DeepL API returned status %d: %s',
                        $status,
                        $body_content
                    ) );
                }
                
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    throw new Exception( 'Invalid JSON response from DeepL: ' . json_last_error_msg() );
                }
                
                if ( empty( $data['translations'] ) ) {
                    throw new Exception( 'No translations returned from DeepL' );
                }
                
                // Map translated texts back to original indices
                $result = $texts; // Start with originals
                $translation_index = 0;
                
                foreach ( $text_indices as $original_index ) {
                    if ( isset( $data['translations'][ $translation_index ]['text'] ) ) {
                        $result[ $original_index ] = $data['translations'][ $translation_index ]['text'];
                    }
                    $translation_index++;
                }
                
                // Small delay to avoid rate limiting
                usleep( self::API_DELAY );
                
                return $result;
                
            } catch ( Exception $e ) {
                $attempt++;
                
                if ( $attempt >= self::MAX_RETRIES ) {
                    WP_CLI::warning( sprintf(
                        'DeepL translation failed after %d attempts: %s',
                        self::MAX_RETRIES,
                        $e->getMessage()
                    ) );
                    $this->log( 'DeepL API error: ' . $e->getMessage() );
                    return $texts; // Return originals on failure
                }
                
                WP_CLI::warning( sprintf(
                    'DeepL API error (attempt %d/%d): %s. Retrying...',
                    $attempt,
                    self::MAX_RETRIES,
                    $e->getMessage()
                ) );
                
                sleep( 2 * $attempt ); // Exponential backoff
            }
        }
        
        return $texts; // Fallback to originals
    }
    
    /**
     * Translate single text via DeepL API
     */
    private function translate_text( $text, $source, $target ) {
        if ( empty( trim( $text ) ) ) {
            return '';
        }
        
        $result = $this->translate_texts_batch( [ $text ], $source, $target );
        return $result[0] ?? $text;
    }
    
    /**
     * Write to log file
     */
    private function log( $message ) {
        $timestamp = current_time( 'mysql' );
        $log_line = sprintf( "[%s] %s\n", $timestamp, $message );
        file_put_contents( $this->log_file, $log_line, FILE_APPEND );
    }
}
