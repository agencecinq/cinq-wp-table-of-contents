<?php
/**
 * Plugin Name: CINQ Table of Contents
 * Plugin URI: https://github.com/agencecinq/cinq-wp-table-of-contents
 * Description: Adds unique ids to H2–H6 headings and exposes a nested TOC tree. No markup, no settings screen.
 * Version: 2.0.0
 * Author: CINQ
 * Author URI: https://agencecinq.com/
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: cinq-wp-table-of-contents
 *
 * @package CinqTableOfContents
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default post types that receive heading ids via the_content.
 */
const CINQ_TOC_POST_TYPES = array( 'post', 'page' );

/**
 * Add unique id attributes to H2–H6 headings that do not already have one.
 *
 * Ids are unique within this HTML string. Existing id attributes are left as-is
 * and reserved so generated ids do not collide with them.
 *
 * @param string $content HTML content.
 * @return string
 */
function cinq_add_heading_ids( string $content ): string {
	if ( '' === $content || ! preg_match( '/<h[2-6]\b/i', $content ) ) {
		return $content;
	}

	$used = array();

	return (string) preg_replace_callback(
		'/<h([2-6])([^>]*)>(.*?)<\/h\1>/is',
		function ( $matches ) use ( &$used ) {
			$level = $matches[1];
			$attrs = $matches[2];
			$inner = $matches[3];

			if ( preg_match( '/\bid\s*=/i', $attrs ) ) {
				if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $id_match ) ) {
					$used[] = $id_match[1];
				}

				return $matches[0];
			}

			$slug = sanitize_title( wp_strip_all_tags( $inner ) );

			if ( '' === $slug ) {
				return $matches[0];
			}

			$base = $slug;
			$i    = 2;

			while ( in_array( $slug, $used, true ) ) {
				$slug = $base . '-' . $i;
				++$i;
			}

			$used[] = $slug;

			return '<h' . $level . $attrs . ' id="' . esc_attr( $slug ) . '">' . $inner . '</h' . $level . '>';
		},
		$content
	);
}

/**
 * Extract a nested table-of-contents tree from HTML content.
 *
 * Expects heading ids from cinq_add_heading_ids() or the editor.
 * A heading is nested under the closest previous heading of a lower level.
 * A skipped level (H2 then H4) attaches to that closest parent. A heading with
 * no shallower predecessor is a root.
 *
 * @param string $content HTML content.
 * @return array<int, array{title: string, id: string, level: int, children: array}>
 */
function cinq_parse_toc_items( string $content ): array {
	if ( '' === $content || ! preg_match( '/<h[2-6]\b/i', $content ) ) {
		return array();
	}

	if ( ! preg_match_all( '/<h([2-6])([^>]*)>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	$flat = array();

	foreach ( $matches as $match ) {
		$title = trim( wp_strip_all_tags( $match[3] ) );

		if ( '' === $title ) {
			continue;
		}

		if ( ! preg_match( '/\bid=["\']([^"\']+)["\']/i', $match[2], $id_match ) ) {
			continue;
		}

		$flat[] = array(
			'title' => $title,
			'id'    => $id_match[1],
			'level' => (int) $match[1],
		);
	}

	return cinq_toc_nest_items( $flat );
}

/**
 * Nest flat heading entries under the closest shallower parent.
 *
 * @param array<int, array{title: string, id: string, level: int}> $items Document order.
 * @return array<int, array{title: string, id: string, level: int, children: array}>
 */
function cinq_toc_nest_items( array $items ): array {
	$tree  = array();
	$stack = array();

	foreach ( $items as $item ) {
		$node = array(
			'title'    => $item['title'],
			'id'       => $item['id'],
			'level'    => $item['level'],
			'children' => array(),
		);

		while ( array() !== $stack && $stack[ array_key_last( $stack ) ]['level'] >= $node['level'] ) {
			array_pop( $stack );
		}

		if ( array() === $stack ) {
			$tree[]  = $node;
			$stack[] = array(
				'level' => $node['level'],
				'path'  => array( array_key_last( $tree ) ),
			);
			continue;
		}

		$path   = $stack[ array_key_last( $stack ) ]['path'];
		$cursor = &$tree;

		foreach ( $path as $depth => $index ) {
			if ( 0 === $depth ) {
				$cursor = &$cursor[ $index ];
				continue;
			}

			$cursor = &$cursor['children'][ $index ];
		}

		$cursor['children'][] = $node;

		$stack[] = array(
			'level' => $node['level'],
			'path'  => array_merge( $path, array( array_key_last( $cursor['children'] ) ) ),
		);

		unset( $cursor );
	}

	return $tree;
}

/**
 * Heading ids + nested TOC tree for HTML content.
 *
 * @param string $content HTML content.
 * @return array{content: string, items: array<int, array{title: string, id: string, level: int, children: array}>}
 */
function cinq_toc( string $content ): array {
	$content = cinq_add_heading_ids( $content );

	return array(
		'content' => $content,
		'items'   => cinq_parse_toc_items( $content ),
	);
}

/**
 * Nested TOC tree for HTML content (ids are injected when missing).
 *
 * @param string $content HTML content.
 * @return array<int, array{title: string, id: string, level: int, children: array}>
 */
function cinq_toc_items( string $content ): array {
	return cinq_parse_toc_items( cinq_add_heading_ids( $content ) );
}

/**
 * Inject heading ids on singular content for configured post types.
 *
 * @param string $content Post content.
 * @return string
 */
function cinq_toc_filter_content( string $content ): string {
	if ( is_admin() || ! is_singular() ) {
		return $content;
	}

	$post_types = apply_filters( 'cinq_toc_post_types', CINQ_TOC_POST_TYPES );

	if ( ! is_array( $post_types ) || array() === $post_types ) {
		return $content;
	}

	if ( ! is_singular( $post_types ) ) {
		return $content;
	}

	return cinq_add_heading_ids( $content );
}

add_filter( 'the_content', 'cinq_toc_filter_content', 12 );
