<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Markdown output independent of dynamic, visitor-specific rendering. */
final class Editorial_Content {
	/** @param \WP_Post $post Public editorial item. @return bool */
	public static function is_static( $post ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		$content = (string) $post->post_content;
		return self::blocks_are_static( parse_blocks( $content ) );
	}

	/** @param array<int, array<string, mixed>> $blocks Parsed WordPress blocks. @return bool */
	private static function blocks_are_static( $blocks ) {
		$prohibited_blocks = array(
			'core/loginout',
			'woocommerce/cart',
			'woocommerce/checkout',
			'woocommerce/my-account',
		);

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && in_array( $block['blockName'], $prohibited_blocks, true ) ) {
				return false;
			}
			if ( ! empty( $block['innerBlocks'] ) && ! self::blocks_are_static( $block['innerBlocks'] ) ) {
				return false;
			}
		}

		return true;
	}
}
