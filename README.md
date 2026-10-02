# CINQ Table of Contents

WordPress plugin that adds unique `id` attributes to H2 headings and exposes a raw TOC items API. No markup, no settings screen.

**Repository:** [`agencecinq/cinq-wp-table-of-contents`](https://github.com/agencecinq/cinq-wp-table-of-contents)

## Requirements

- WordPress 6.0+
- PHP 8.1+

## Install as a must-use plugin (recommended)

```bash
cp cinq-wp-table-of-contents.php wp-content/mu-plugins/
```

Or clone and symlink:

```bash
git clone git@github.com:agencecinq/cinq-wp-table-of-contents.git
ln -s "$(pwd)/cinq-wp-table-of-contents/cinq-wp-table-of-contents.php" wp-content/mu-plugins/cinq-wp-table-of-contents.php
```

## Install as a regular plugin

1. Copy the folder to `wp-content/plugins/cinq-wp-table-of-contents`
2. Activate **CINQ Table of Contents** in the WordPress admin

## API

```php
// Inject missing H2 ids.
$html = cinq_add_heading_ids( $html );

// Parse H2 entries that already have an id.
$items = cinq_parse_toc_items( $html );
// array{ title: string, id: string }[]

// Inject ids then parse.
$items = cinq_toc_items( $html );

// Both at once.
$data = cinq_toc( $html );
// array{ content: string, items: array }
```

### Optional filters

```php
// Singular post types that receive ids via the_content (default: post, page).
add_filter( 'cinq_toc_post_types', fn () => array( 'post', 'page', 'guide' ) );

// the_content priority (default: 12).
add_filter( 'cinq_toc_content_priority', fn () => 12 );
```

## Theme usage

```php
$items = function_exists( 'cinq_toc_items' )
	? cinq_toc_items( (string) $post->content )
	: array();
```

Timber / Twig: keep the TOC markup in the theme. This plugin only returns data and enriches `the_content`.

## Scope

- Headings: `H2` only
- No options, no admin UI, no shortcode, no front-end assets
