<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Detects Markdown requests before WordPress parses the requested URL. */
final class Request_Router {
	/** @var bool */
	private $markdown_request = false;
	/** @var bool */
	private $accept_negotiated = false;

	/** Capture a .md request and expose the underlying permalink to WordPress. */
	public function capture() {
		if ( is_admin() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve encoded permalink bytes; this value is parsed as a path and is never rendered.
		if ( self::is_markdown_uri( $uri ) ) {
			$this->markdown_request = true;
			add_filter( 'woocommerce_set_cookie_enabled', '__return_false', 99 );
			$_SERVER['REQUEST_URI'] = self::underlying_uri( $uri );
			return;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( self::is_negotiable_uri( $uri ) && self::accepts_markdown( $accept ) ) {
			$this->markdown_request  = true;
			$this->accept_negotiated = true;
			add_filter( 'woocommerce_set_cookie_enabled', '__return_false', 99 );
		}
	}

	/** @return bool */
	public function is_markdown_request() {
		return $this->markdown_request;
	}

	/** @return bool Whether Markdown was selected through the Accept header. */
	public function is_accept_negotiated() {
		return $this->accept_negotiated;
	}

	/** @param string $uri Request URI. @return bool */
	public static function is_markdown_uri( $uri ) {
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		if ( '' === $path || ! preg_match( '#\.md/?$#', $path ) ) {
			return false;
		}

		return self::is_negotiable_uri( $uri );
	}

	/** @param string $uri Request URI. @return bool */
	public static function is_negotiable_uri( $uri ) {
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return false;
		}

		return ! preg_match( '~(?:^|/)(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)|(?:^|/)xmlrpc\.php(?:/|$)~', $path );
	}

	/** @param string $accept Accept header. @return bool */
	public static function accepts_markdown( $accept ) {
		foreach ( explode( ',', (string) $accept ) as $media_range ) {
			$parts = explode( ';', trim( $media_range ) );
			if ( strtolower( trim( $parts[0] ) ) !== 'text/markdown' ) {
				continue;
			}

			foreach ( array_slice( $parts, 1 ) as $parameter ) {
				if ( preg_match( '/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $matches ) && (float) $matches[1] <= 0 ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/** @param string $uri Markdown request URI. @return string */
	public static function underlying_uri( $uri ) {
		$path  = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		$clean = preg_replace( '#\.md/?$#', '/', $path );
		$query = (string) wp_parse_url( (string) $uri, PHP_URL_QUERY );

		return $clean . ( '' !== $query ? '?' . $query : '' );
	}
}
