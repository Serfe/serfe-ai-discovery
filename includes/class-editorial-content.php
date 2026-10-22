<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Markdown output limited to editor-saved, public editorial content. */
final class Editorial_Content {
	/**
	 * Dynamic core blocks that can be represented exclusively from their saved
	 * markup. Render callbacks and content filters are never executed.
	 *
	 * @var array<int, string>
	 */
	const SAVED_CORE_BLOCKS = array(
		'core/gallery',
		'core/heading',
		'core/image',
		'core/list',
		'core/list-item',
		'core/video',
	);

	/**
	 * Dynamic core blocks that are navigation, queries, widgets, templates, or
	 * other generated site UI rather than saved editorial content.
	 *
	 * @var array<int, string>
	 */
	const GENERATED_CORE_BLOCKS = array(
		'core/archives',
		'core/calendar',
		'core/categories',
		'core/comments',
		'core/latest-comments',
		'core/latest-posts',
		'core/loginout',
		'core/navigation',
		'core/page-list',
		'core/query',
		'core/rss',
		'core/search',
		'core/tag-cloud',
		'core/template-part',
	);

	/** @param \WP_Post $post Public editorial item. @return bool */
	public static function is_static( $post ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		$content = (string) $post->post_content;
		if ( preg_match( '/\[[a-zA-Z0-9_-]+(?:\s+[^\]]*)?\]/', $content ) ) {
			return false;
		}

		return self::blocks_are_static( parse_blocks( $content ) );
	}

	/** @param array<int, array<string, mixed>> $blocks Parsed WordPress blocks. @return bool */
	private static function blocks_are_static( $blocks ) {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				$name              = $block['blockName'];
				$is_core_block     = 0 === strpos( $name, 'core/' );
				$has_saved_content = self::has_saved_editorial_content( $block );
				$type              = $registry->get_registered( $name );
				if ( self::has_block_bindings( $block )
					|| ( $is_core_block && in_array( $name, self::GENERATED_CORE_BLOCKS, true ) )
					|| ( ! $is_core_block && ! $has_saved_content )
					|| ( ! $type && ! $has_saved_content )
					|| ( $type && $type->is_dynamic() && $is_core_block && ( ! in_array( $name, self::SAVED_CORE_BLOCKS, true ) || ! $has_saved_content ) ) ) {
					return false;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && ! self::blocks_are_static( $block['innerBlocks'] ) ) {
				return false;
			}
		}

		return true;
	}

	/** Reject block bindings because their public value can differ from saved markup. */
	private static function has_block_bindings( $block ) {
		return ! empty( $block['attrs']['metadata']['bindings'] ) && is_array( $block['attrs']['metadata']['bindings'] );
	}

	/**
	 * Whether a block stores meaningful text or supported media in post content.
	 * This deliberately examines saved markup instead of invoking a plugin's
	 * render callback, shortcode, or content filter.
	 */
	private static function has_saved_editorial_content( $block ) {
		$markup = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
		$markup = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', '', $markup );
		$markup = is_string( $markup ) ? $markup : '';
		if ( '' !== trim( $markup ) ) {
			if ( preg_match( '/\[[a-zA-Z0-9_-]+(?:\s+[^\]]*)?\]/', $markup ) ) {
				return false;
			}
			$text = trim( wp_strip_all_tags( $markup, true ) );
			if ( '' !== $text || preg_match( '/<(?:img|video|source)\b[^>]*\bsrc\s*=/i', $markup ) ) {
				return true;
			}
		}

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return false;
		}
		foreach ( $block['innerBlocks'] as $inner_block ) {
			if ( self::has_saved_editorial_content( $inner_block ) ) {
				return true;
			}
		}

		return false;
	}
}
