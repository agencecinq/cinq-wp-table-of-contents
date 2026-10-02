<?php
/**
 * Plugin Name: CINQ Table of Contents
 * Plugin URI: https://github.com/agencecinq/cinq-wp-table-of-contents
 * Description: Adds unique ids to H2 headings and exposes a raw TOC items API. No markup, no settings screen.
 * Version: 1.0.0
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
 * Default the_content filter priority.
 */
const CINQ_TOC_CONTENT_PRIORITY = 12;

/**
 * Add unique id attributes to H2 headings that do not already have one.
 *
 * @param string $content HTML content.
 * @return string
 */
function cinq_add_heading_ids( string $content ): string {
	if ( '' === $content || false === stripos( $content, '<h2' ) ) {
		return $content;
	}

	$used = array();

	return (string) preg_replace_callback(
		'/<h2([^>]*)>(.*?)<\/h2>/is',
		function ( $matches ) use ( &$used ) {
			$attrs = $matches[1];
			$inner = $matches[2];

			if ( preg_match( '/\bid\s*=/i', $attrs ) ) {
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

			return '<h2' . $attrs . ' id="' . esc_attr( $slug ) . '">' . $inner . '</h2>';
		},
		$content
	);
}

/**
 * Extract H2 table-of-contents entries from HTML content.
 *
 * Expects heading ids from cinq_add_heading_ids() or the editor.
 *
 * @param string $content HTML content.
 * @return array<int, array{title: string, id: string}>
 */
function cinq_parse_toc_items( string $content ): array {
	if ( '' === $content || false === stripos( $content, '<h2' ) ) {
		return array();
	}

	$items = array();

	if ( ! preg_match_all( '/<h2([^>]*)>(.*?)<\/h2>/is', $content, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $matches as $match ) {
		$attrs = $match[1];
		$inner = $match[2];
		$title = trim( wp_strip_all_tags( $inner ) );

		if ( '' === $title ) {
			continue;
		}

		if ( ! preg_match( '/\bid=["\']([^"\']+)["\']/i', $attrs, $id_match ) ) {
			continue;
		}

		$items[] = array(
			'title' => $title,
			'id'    => $id_match[1],
		);
	}

	return $items;
}

/**
 * Heading ids + TOC entries for HTML content.
 *
 * @param string $content HTML content.
 * @return array{content: string, items: array<int, array{title: string, id: string}>}
 */
function cinq_toc( string $content ): array {
	$content = cinq_add_heading_ids( $content );

	return array(
		'content' => $content,
		'items'   => cinq_parse_toc_items( $content ),
	);
}

/**
 * TOC entries for HTML content (ids are injected when missing).
 *
 * @param string $content HTML content.
 * @return array<int, array{title: string, id: string}>
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

$cinq_toc_priority = (int) apply_filters( 'cinq_toc_content_priority', CINQ_TOC_CONTENT_PRIORITY );
add_filter( 'the_content', 'cinq_toc_filter_content', $cinq_toc_priority > 0 ? $cinq_toc_priority : CINQ_TOC_CONTENT_PRIORITY );
