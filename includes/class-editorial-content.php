<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Markdown output independent of dynamic, visitor-specific rendering. */
final class Editorial_Content {
	/** @param \WP_Post $post Public editorial item. @return bool */
	public static function is_static( $post ) {
		$content = (string) $post->post_content;
		if ( preg_match( '/\[(?:\/)?[A-Za-z][^\]\r\n]*\]/', $content ) ) {
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
				if ( ! $type || $type->is_dynamic() ) {
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
