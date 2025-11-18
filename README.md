# Polylang Auto Translate All

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0+-orange.svg)

🚀 **Powerful WP-CLI command for automated bulk translation of WordPress posts and pages using Polylang Pro and DeepL API.**

Translate hundreds of posts with a single command while preserving all meta fields, taxonomies, featured images, and ACF data.

---

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Examples](#-examples)
- [Advanced Features](#-advanced-features)
- [Performance](#-performance)
- [Troubleshooting](#-troubleshooting)
- [Changelog](#-changelog)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### Core Functionality
- ✅ **Bulk Translation** - Translate unlimited posts with a single command
- ✅ **Batch Processing** - Efficient API usage by translating multiple texts per request
- ✅ **Smart Pagination** - Process large datasets without memory issues
- ✅ **DeepL Integration** - Professional translation quality via DeepL REST API
- ✅ **Polylang Pro Native** - Uses Polylang Pro settings and language structure

### Content Preservation
- ✅ **Complete Content** - Translates title, content, and excerpt
- ✅ **ACF Support** - Handles Advanced Custom Fields (including repeaters)
- ✅ **Taxonomies** - Preserves categories, tags, and custom taxonomies
- ✅ **Featured Images** - Copies post thumbnails to translations
- ✅ **Meta Fields** - Intelligently copies or translates custom fields

### Reliability & Safety
- ✅ **Error Handling** - Robust retry logic with exponential backoff
- ✅ **Rate Limiting** - Automatic handling of API limits
- ✅ **Dry Run Mode** - Test translations without creating posts
- ✅ **Progress Tracking** - Real-time progress bar and detailed logging
- ✅ **Skip Logic** - Avoids duplicate translations

### Smart Translation
- ✅ **Content Detection** - Skips URLs, emails, numbers, and shortcodes
- ✅ **Array Handling** - Recursive translation of nested data structures
- ✅ **JSON Support** - Properly handles JSON-encoded meta fields
- ✅ **Memory Efficient** - Processes posts in configurable batches

---

## 📦 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **WP-CLI**: 2.5 or higher
- **Polylang Pro**: 3.0 or higher (with active license)
- **DeepL API**: Free or Pro account with valid API key

---

## 🔧 Installation

### Method 1: Manual Installation

1. Download the plugin file:
```bash
wget https://raw.githubusercontent.com/yourusername/polylang-auto-translate-all/main/polylang-auto-translate-all.php
```

2. Upload to your WordPress plugins directory:
```bash
wp plugin install /path/to/polylang-auto-translate-all.php
```

3. Verify installation:
```bash
wp polylang auto-translate-all --help
```

### Method 2: Direct Upload

1. Download `polylang-auto-translate-all.php`
2. Upload to `/wp-content/plugins/` via FTP or hosting panel
3. The plugin doesn't need activation (WP-CLI only)

---

## ⚙️ Configuration

### 1. Configure Polylang Pro

Navigate to **Settings → Languages → Machine Translation**:

![Polylang Settings](docs/images/polylang-settings.png)

- ☑️ Enable **Machine Translation**
- Select **DeepL** as translation service
- Enter your **DeepL API Key**
- Choose **Formality** level (optional)
- Configure **Meta Fields** to copy (if any)

### 2. Get DeepL API Key

1. Sign up at [DeepL API](https://www.deepl.com/pro-api)
2. Choose Free (500,000 chars/month) or Pro plan
3. Copy your authentication key
4. Add to Polylang Pro settings

### 3. Configure Post Meta (Optional)

Define which meta fields should be **copied** vs **translated**:

**Copy without translation** (IDs, numbers, references):
- `_thumbnail_id`
- `_wp_page_template`
- `product_id`
- Custom numeric fields

**Translate** (text content):
- ACF text fields
- Custom text meta
- Product descriptions

---

## 🚀 Usage

### Basic Command Structure

```bash
wp polylang auto-translate-all [--post_type=<type>] [--lang=<code>] [options]
```

### Required Parameters

| Parameter | Description | Default | Example |
|-----------|-------------|---------|---------|
| `--post_type` | Post type to translate | `post` | `--post_type=page` |
| `--lang` | Target language code | `de` | `--lang=fr` |

### Optional Parameters

| Parameter | Description | Default | Example |
|-----------|-------------|---------|---------|
| `--dry-run` | Preview without creating posts | `false` | `--dry-run` |
| `--per-page` | Posts per batch | `50` | `--per-page=100` |
| `--limit` | Max posts to process | `unlimited` | `--limit=10` |

### Language Codes

DeepL supports these language codes:

| Code | Language | Code | Language |
|------|----------|------|----------|
| `en` | English | `de` | German |
| `fr` | French | `es` | Spanish |
| `it` | Italian | `pt` | Portuguese |
| `nl` | Dutch | `pl` | Polish |
| `ru` | Russian | `ja` | Japanese |
| `zh` | Chinese | `uk` | Ukrainian |

[Full list of supported languages](https://www.deepl.com/docs-api/translating-text/)

---

## 💡 Examples

### Example 1: Basic Translation (3 posts for testing)
```bash
wp polylang auto-translate-all --post_type=post --lang=de --limit=3
```

**Output:**
```
Limit set to 3 posts (total available: 250)
Found 3 posts to translate from en to de
Translating posts  3/3 [============================] 100%

Limit of 3 posts reached. Stopping translation.
Success: Translation complete! Processed: 3 | Translated: 3 | Skipped: 0 | Errors: 0
Log file: /wp-content/polylang-translations-2025-11-18-14-30-15.log
```

---

### Example 2: Dry Run (Preview Without Creating)
```bash
wp polylang auto-translate-all --post_type=page --lang=fr --limit=5 --dry-run
```

**Output:**
```
--- DRY RUN MODE (no posts will be created) ---
Found 5 posts to translate from en to fr
Translating posts  5/5 [============================] 100%

Success: Translation complete! Processed: 5 | Translated: 0 | Skipped: 5 | Errors: 0
```

---

### Example 3: Translate All Posts
```bash
wp polylang auto-translate-all --post_type=post --lang=es
```

**Output:**
```
Found 250 posts to translate from en to es
Translating posts  250/250 [========================] 100%

Success: Translation complete! Processed: 250 | Translated: 235 | Skipped: 15 | Errors: 0
Log file: /wp-content/polylang-translations-2025-11-18-14-45-30.log
```

---

### Example 4: Custom Post Type with Large Batches
```bash
wp polylang auto-translate-all --post_type=portfolio --lang=it --per-page=100
```

---

### Example 5: Multiple Languages (Sequential)
```bash
# Translate to German
wp polylang auto-translate-all --post_type=post --lang=de

# Translate to French
wp polylang auto-translate-all --post_type=post --lang=fr

# Translate to Spanish
wp polylang auto-translate-all --post_type=post --lang=es
```

---

## 🎯 Advanced Features

### 1. Smart Content Detection

The plugin automatically **skips translation** for:

- **URLs**: `https://example.com`
- **Emails**: `user@example.com`
- **Numbers**: `123`, `45.67`
- **Shortcodes**: `[gallery id="123"]`
- **Short strings**: Less than 3 characters

### 2. ACF Repeater Support

Handles complex ACF structures:

```php
// ACF Repeater Field
[
    [
        'title' => 'Title in English',
        'description' => 'Description in English'
    ],
    [
        'title' => 'Another Title',
        'description' => 'Another Description'
    ]
]

// After translation
[
    [
        'title' => 'Titel auf Deutsch',
        'description' => 'Beschreibung auf Deutsch'
    ],
    [
        'title' => 'Ein weiterer Titel',
        'description' => 'Eine weitere Beschreibung'
    ]
]
```

### 3. Batch Translation

**Old Method** (inefficient):
- 3 API calls per post (title, content, excerpt)
- 1000 posts = 3000 API calls

**New Method** (optimized):
- 1 API call per post (batch request)
- 1000 posts = 1000 API calls
- **66% fewer API calls!**

### 4. Rate Limit Handling

Automatic retry with exponential backoff:

```
Attempt 1: API error → Wait 2 seconds → Retry
Attempt 2: API error → Wait 4 seconds → Retry
Attempt 3: API error → Wait 6 seconds → Retry
```

Rate limit (429) handling:
```
Rate limit hit → Wait 60 seconds → Retry
```

### 5. Detailed Logging

Every translation is logged with timestamp:

```
[2025-11-18 14:30:15] Translation started: 250 posts | en -> de | Dry run: No | Limit: None
[2025-11-18 14:30:20] Success: #123 "My Post Title" -> #456 (de)
[2025-11-18 14:30:25] Skipped #124: already has de translation (#457)
[2025-11-18 14:30:30] Error translating #125: DeepL API returned status 500
[2025-11-18 14:45:00] Translation finished. Processed: 250 | Translated: 235 | Skipped: 15 | Errors: 0
```

Log location: `/wp-content/polylang-translations-YYYY-MM-DD-HH-MM-SS.log`

---

## ⚡ Performance

### Benchmarks

| Metric | Value |
|--------|-------|
| **Memory Usage** | ~50 MB (paginated processing) |
| **Posts per Hour** | ~1000-2000 (depends on content size) |
| **API Efficiency** | 66% fewer calls vs sequential |
| **Max Posts** | Unlimited (pagination) |

### Optimization Tips

1. **Increase per-page for faster processing** (more memory):
```bash
wp polylang auto-translate-all --per-page=100
```

2. **Run during low-traffic hours** to avoid server load

3. **Use DeepL Pro** for higher rate limits

4. **Test with --limit first**:
```bash
wp polylang auto-translate-all --limit=10 --dry-run
```

---

## 🐛 Troubleshooting

### Issue: "Polylang Pro is not active"

**Solution:**
```bash
# Check if Polylang Pro is installed
wp plugin list | grep polylang

# Activate if needed
wp plugin activate polylang-pro
```

---

### Issue: "Machine translation is not enabled"

**Solution:**
1. Go to **Settings → Languages → Machine Translation**
2. Check "Enable machine translation"
3. Select DeepL as service
4. Enter API key
5. Save settings

---

### Issue: "DeepL API returned status 403"

**Cause:** Invalid or expired API key

**Solution:**
1. Verify API key in Polylang settings
2. Check DeepL account status
3. Ensure key has access to target language
4. Free API keys have different endpoint (handled automatically)

---

### Issue: "DeepL API returned status 429"

**Cause:** Rate limit exceeded

**Solution:**
- Plugin automatically handles this with 60-second wait
- Consider upgrading to DeepL Pro
- Reduce `--per-page` value

---

### Issue: "Memory exhausted"

**Solution:**
```bash
# Reduce batch size
wp polylang auto-translate-all --per-page=25

# Or increase PHP memory in wp-config.php
define( 'WP_MEMORY_LIMIT', '256M' );
```

---

### Issue: Posts created but taxonomies missing

**Cause:** Target language terms don't exist

**Solution:**
1. Go to **Languages → Term Translations**
2. Ensure all terms have translations in target language
3. Re-run translation command

---

## 📊 What Gets Translated vs Copied

### ✅ Translated Content

- Post title
- Post content
- Post excerpt
- Custom meta fields (text)
- ACF text fields
- ACF textarea fields
- ACF WYSIWYG fields

### 📋 Copied Without Translation

- Featured image
- Post author
- Post status
- Post date
- Meta fields in `polylang_copy_post_metas` setting
- Numeric values
- URLs and emails
- Taxonomy relationships (uses existing translations)

---

## 🎓 Best Practices

### 1. Testing Workflow

```bash
# Step 1: Dry run with 1 post
wp polylang auto-translate-all --post_type=post --lang=de --limit=1 --dry-run

# Step 2: Translate 1 real post
wp polylang auto-translate-all --post_type=post --lang=de --limit=1

# Step 3: Verify translation manually in WordPress admin

# Step 4: Translate 10 posts
wp polylang auto-translate-all --post_type=post --lang=de --limit=10

# Step 5: Full translation
wp polylang auto-translate-all --post_type=post --lang=de
```

### 2. Production Deployment

```bash
# Create backup first
wp db export backup-before-translation.sql

# Run translation
wp polylang auto-translate-all --post_type=post --lang=de

# Verify in admin panel

# If issues, restore backup
wp db import backup-before-translation.sql
```

### 3. Multi-Language Sites

```bash
# Translate to all languages sequentially
languages=("de" "fr" "es" "it")

for lang in "${languages[@]}"; do
    echo "Translating to $lang..."
    wp polylang auto-translate-all --post_type=post --lang=$lang
done
```

---

## 🔐 Security Considerations

- ✅ API keys stored in Polylang Pro settings (encrypted by WordPress)
- ✅ No sensitive data in logs (only post IDs and titles)
- ✅ WP-CLI only (no public-facing interface)
- ✅ Uses WordPress native functions (`wp_remote_post`, `sanitize_text_field`)
- ✅ Validates and escapes all user input

---

## 📈 Monitoring & Analytics

### View Translation History

```bash
# Show recent log file
cat /wp-content/polylang-translations-*.log | tail -50

# Count successful translations
grep "Success:" /wp-content/polylang-translations-*.log | wc -l

# Find errors
grep "Error:" /wp-content/polylang-translations-*.log
```

### Check Translation Status

```bash
# Count posts by language
wp post list --post_type=post --lang=en --format=count
wp post list --post_type=post --lang=de --format=count
```

---

## 🔄 Changelog

### Version 2.0.0 (2025-11-18)
- ✨ Added `--limit` parameter for testing
- ✨ Added `--dry-run` mode
- ✨ Implemented batch translation (66% fewer API calls)
- ✨ Added progress bar
- ✨ Smart pagination to avoid memory issues
- ✨ Enhanced error handling with retry logic
- ✨ Rate limit handling
- ✨ Detailed logging system
- ✨ ACF repeater support
- ✨ Smart content detection (skip URLs, emails, etc.)
- 🐛 Fixed memory exhaustion on large sites
- 🐛 Fixed taxonomy relationships
- ⚡ Performance improvements

### Version 1.5.0 (2024-XX-XX)
- Initial release
- Basic translation functionality
- DeepL integration
- Meta field support

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Development Setup

```bash
# Clone repository
git clone https://github.com/yourusername/polylang-auto-translate-all.git

# Install in WordPress
cp polylang-auto-translate-all.php /path/to/wordpress/wp-content/plugins/

# Test
wp polylang auto-translate-all --help
```

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/polylang-auto-translate-all/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/polylang-auto-translate-all/discussions)
- **Email**: mr.tishakov@gmail.com

---

## 📄 License

This project is licensed under the GPL-2.0+ License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- [Polylang Pro](https://polylang.pro/) - Multilingual WordPress plugin
- [DeepL](https://www.deepl.com/) - Neural machine translation
- [WP-CLI](https://wp-cli.org/) - Command-line interface for WordPress

---

## ⭐ Show Your Support

If this plugin helped you, please:
- ⭐ Star this repository
- 🐛 Report issues
- 💡 Suggest features
- 🔄 Share with others

---

**Made with ❤️ by [Dmitry Tishakov](https://github.com/yourusername)**

---

## 📚 Additional Resources

- [Polylang Pro Documentation](https://polylang.pro/doc/)
- [DeepL API Documentation](https://www.deepl.com/docs-api)
- [WP-CLI Handbook](https://make.wordpress.org/cli/handbook/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
