# CINQ Table of Contents

WordPress plugin that adds unique `id` attributes to H2–H6 headings and exposes a nested TOC tree. No markup, no settings screen.

**Repository:** [`agencecinq/cinq-wp-table-of-contents`](https://github.com/agencecinq/cinq-wp-table-of-contents)

## Requirements

- WordPress 6.0+
- PHP 8.1+

## Lint (WordPress Coding Standards)

```bash
composer install
composer lint          # phpcs
composer lint:fix     # phpcbf (auto-fix)
```

Use `./vendor/bin/phpcs`, not the global `phpcs` binary — the global install does not register the WordPress standards.

## Install

Copy the plugin file into `mu-plugins`. WordPress loads it on every request. It is listed under **Plugins → Must-Use**, not with the regular plugins.

```bash
cp cinq-wp-table-of-contents.php /path/to/wp-content/mu-plugins/
```

Replace that file when the plugin changes.

To install it as a regular plugin, copy the folder to `wp-content/plugins/cinq-wp-table-of-contents` and activate **CINQ Table of Contents** in the WordPress admin. It then appears in the plugin list and stays off until activated.

## API

`2.0.0` replaces the flat H2 list with a tree. Each node is:

```php
array{
    title: string,
    id: string,
    level: int,      // 2–6
    children: array  // same shape, possibly empty
}
```

A heading is nested under the closest previous heading of a lower level. `H2` then `H4` (no `H3`) attaches the `H4` to that `H2`. A heading with no shallower predecessor is a root.

```php
// Inject missing H2–H6 ids. Existing ids are kept.
$html = cinq_add_heading_ids( $html );

// Parse headings that already have an id.
$items = cinq_parse_toc_items( $html );

// Inject ids, then parse.
$items = cinq_toc_items( $html );

// Both at once.
$data = cinq_toc( $html );
// array{ content: string, items: array }
```

Ids are unique within the HTML string passed in, not across the whole page.

### Optional filters

```php
// Singular post types that receive ids via the_content (default: post, page).
add_filter( 'cinq_toc_post_types', fn () => array( 'post', 'page', 'guide' ) );
```

## Theme usage

In the template, inside the Loop, pass `get_the_content()`. Ids match those injected on `the_content`. Markup stays in the theme.

Read only the roots for a flat list. Walk `children` when the template needs depth.

`functions.php`:

```php
function theme_toc_list( array $items ): void {
	if ( array() === $items ) {
		return;
	}

	echo '<ol>';

	foreach ( $items as $item ) {
		echo '<li>';
		echo '<a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['title'] ) . '</a>';
		theme_toc_list( $item['children'] );
		echo '</li>';
	}

	echo '</ol>';
}
```

`single.php`:

```php
$items = function_exists( 'cinq_toc_items' )
	? cinq_toc_items( get_the_content() )
	: array();

if ( array() !== $items ) {
	echo '<nav aria-label="' . esc_attr__( 'Table of contents', 'cinq-wp-table-of-contents' ) . '">';
	theme_toc_list( $items );
	echo '</nav>';
}

the_content();
```

## Scope

- Headings: `H2`–`H6` (`H1` is the document title, outside the content)
- No options, no admin UI, no shortcode, no front-end assets
