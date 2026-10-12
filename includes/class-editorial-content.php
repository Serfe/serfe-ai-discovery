<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Markdown output independent of dynamic, visitor-specific rendering. */
final class Editorial_Content {
	/**
	 * WordPress 7 registers these core blocks with render callbacks, but their
	 * saved markup is self-contained and is the only representation we convert.
	 *
	 * @var array<int, string>
	 */
	const SAVED_CORE_BLOCKS = array(
		'core/heading',
		'core/list',
		'core/list-item',
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
				$type = $registry->get_registered( $block['blockName'] );
				if ( ! $type || ( $type->is_dynamic() && ! in_array( $block['blockName'], self::SAVED_CORE_BLOCKS, true ) ) ) {
					return false;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && ! self::blocks_are_static( $block['innerBlocks'] ) ) {
				return false;
			}
		}

		return true;
	}
}
