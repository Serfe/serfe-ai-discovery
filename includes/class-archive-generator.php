<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates a public, item-approved Custom Post Type archive. */
final class Archive_Generator {
	/** Build a Markdown list from the already resolved main query page. */
	public function generate( $type, $canonical_url, $page, $max_pages, $posts, $title = '', $archive_type = '' ) {
		$object = get_post_type_object( $type );
		$title  = is_string( $title ) && '' !== $title ? wp_strip_all_tags( $title ) : ( $object && isset( $object->labels->name ) ? wp_strip_all_tags( $object->labels->name ) : $type );
		$kind   = is_string( $archive_type ) && '' !== $archive_type ? $archive_type : $type;
		$front  = "---\n";
		$front .= 'title: ' . Markdown::yaml_scalar( $title ) . "\n";
		$front .= 'url: ' . Markdown::yaml_scalar( $canonical_url ) . "\n";
		$front .= 'type: ' . Markdown::yaml_scalar( $kind . '-archive' ) . "\n";
		$front .= 'page: ' . (int) $page . "\n";
		$front .= "---\n\n";
		$body   = '# ' . $title . "\n\n";

		foreach ( $posts as $post ) {
			if ( ! ( $post instanceof \WP_Post )
				|| $type !== $post->post_type
				|| 'publish' !== $post->post_status
				|| ! empty( $post->post_password )
				|| ! is_post_publicly_viewable( $post )
				|| ! Settings::item_enabled( $post->ID )
				|| Exclusions::blocks( $post ) ) {
				continue;
			}
			$title = trim( wp_strip_all_tags( $post->post_title ) );
			$url   = esc_url_raw( get_permalink( $post ) );
			if ( '' === $title || '' === $url ) {
				continue;
			}
			$title = str_replace( array( '\\', '[', ']' ), array( '\\\\', '\\[', '\\]' ), $title );
			$body .= '- [' . $title . '](<' . str_replace( '>', '%3E', $url ) . ">)\n";
		}

		if ( 1 < $page ) {
			$body .= "\n" . self::page_link( __( 'Previous page', 'serfe-ai-discovery' ), $page - 1 );
		}
		if ( $page < $max_pages ) {
			$body .= "\n" . self::page_link( __( 'Next page', 'serfe-ai-discovery' ), $page + 1 );
		}
		return $front . rtrim( $body ) . "\n";
	}

	/** Format a public archive pagination link. */
	private static function page_link( $label, $page ) {
		$url = esc_url_raw( get_pagenum_link( $page, false ) );
		return '[' . $label . '](<' . str_replace( '>', '%3E', $url ) . ">)\n";
	}
}
