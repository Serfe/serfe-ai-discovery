<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Sends terminal HTTP responses for Markdown requests. */
final class Response {
	/** @param string $markdown Markdown document. @param bool $negotiated Selected via Accept. @param string $canonical_url HTML permalink. @param bool|null $cache_hit Server-side cache result. */
	public function markdown( $markdown, $negotiated = false, $canonical_url = '', $cache_hit = null ) {
		if ( ! headers_sent() ) {
			header_remove( 'Set-Cookie' );
			status_header( 200 );
			header( 'Content-Type: text/markdown; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			if ( '' !== $canonical_url ) {
				header( sprintf( 'Link: <%s>; rel="canonical"', esc_url_raw( $canonical_url ) ), false );
			}
			if ( $negotiated ) {
				header( 'Vary: Accept', false );
			}
			header( 'X-Markdown-Tokens: ' . self::estimate_tokens( $markdown ) );
			header( 'X-Robots-Tag: noindex, follow' );
			header( 'Cache-Control: private, no-store' );
			if ( null !== $cache_hit ) {
				header( 'X-Markdown-Cache: ' . ( $cache_hit ? 'hit' : 'miss' ) );
			}
		}

		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain Markdown response.
		exit;
	}

	/** Return a lightweight, deterministic estimate when no tokenizer is available. */
	public static function estimate_tokens( $markdown ) {
		if ( trim( $markdown ) === '' ) {
			return 0;
		}

		$matches = array();
		preg_match_all( '/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u', $markdown, $matches );

		return count( $matches[0] );
	}

	/** Send a controlled not-found response. */
	public function not_found() {
		if ( ! headers_sent() ) {
			header_remove( 'Set-Cookie' );
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Robots-Tag: noindex' );
			header( 'Cache-Control: private, no-store' );
		}
		echo "404 Not Found\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant response.
		exit;
	}
}
