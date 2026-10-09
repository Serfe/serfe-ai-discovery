<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Applies administrator denials after an item has otherwise qualified. */
final class Exclusions {
	/** Apply configured type and exact-path exclusions to an archive. */
	public static function blocks_archive( $type, $canonical_url ) {
		$choices = Settings::choices();
		$path    = Settings::normalize_blocked_path( $canonical_url );
		return in_array( $type, $choices['blocked_post_types'], true )
			|| '' === $path
			|| in_array( $path, $choices['blocked_paths'], true );
	}

	/** @param \WP_Post $post Queried item. @return bool */
	public static function blocks( $post ) {
		$choices = Settings::choices();
		if ( in_array( $post->post_type, $choices['blocked_post_types'], true ) ) {
			return true;
		}

		$path = Settings::normalize_blocked_path( get_permalink( $post ) );
		if ( '' !== $path && in_array( $path, $choices['blocked_paths'], true ) ) {
			return true;
		}

		foreach ( $choices['blocked_terms'] as $term_key ) {
			$parts = explode( ':', $term_key, 2 );
			if ( count( $parts ) === 2 && has_term( (int) $parts[1], $parts[0], $post ) ) {
				return true;
			}
		}

		return false;
	}
}
