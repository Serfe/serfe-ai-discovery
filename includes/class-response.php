<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Sends terminal HTTP responses for Markdown requests. */
final class Response {
	/** @param string $markdown Markdown document. @param bool $negotiated Selected via Accept. */
	public function markdown( $markdown, $negotiated = false ) {
		if ( ! headers_sent() ) {
			header_remove( 'Set-Cookie' );
			status_header( 200 );
			header( 'Content-Type: text/markdown; charset=utf-8' );
			if ( $negotiated ) {
				header( 'Vary: Accept', false );
			}
			header( 'X-Markdown-Tokens: ' . self::estimate_tokens( $markdown ) );
			header( 'X-Robots-Tag: noindex, follow' );
			header( 'Cache-Control: public, max-age=300, s-maxage=3600' );
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
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo "404 Not Found\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant response.
		exit;
	}
}
