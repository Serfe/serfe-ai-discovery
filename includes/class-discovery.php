<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Announces available Markdown representations from HTML responses. */
final class Discovery {
	const API_CATALOG_PATH    = '/.well-known/api-catalog';
	const API_CATALOG_PROFILE = 'https://www.rfc-editor.org/info/rfc9727';

	/** @var Product_Generator */
	private $product_generator;

	public function __construct( Product_Generator $product_generator ) {
		$this->product_generator = $product_generator;
	}

	/** Register discovery hooks. */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'send_api_catalog' ), -1 );
		add_action( 'template_redirect', array( $this, 'send_homepage_agent_link_headers' ), 0 );
		add_action( 'wp_head', array( $this, 'render_html_alternate' ) );
		add_action( 'template_redirect', array( $this, 'send_http_link_header' ), 11 );
	}

	/** Serve the RFC 9727 API catalog from its standard well-known URI. */
	public function send_api_catalog() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! Settings::api_catalog_enabled() || ! self::is_api_catalog_request( $uri ) ) {
			return;
		}

		$catalog_url = home_url( self::API_CATALOG_PATH );
		$rest_url    = rest_url();
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: application/linkset+json; profile="' . self::API_CATALOG_PROFILE . '"; charset=utf-8' );
			header( sprintf( 'Link: <%s>; rel="api-catalog"; type="application/linkset+json"; profile="%s"', esc_url_raw( $catalog_url ), self::API_CATALOG_PROFILE ), false );
			header( sprintf( 'Link: <%s>; rel="item"; type="application/json"', esc_url_raw( $rest_url ) ), false );
			header( 'Cache-Control: public, max-age=3600' );
		}

		echo wp_json_encode( self::api_catalog_document( $catalog_url, $rest_url ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON API response.
		exit;
	}

	/** Announce agent-facing machine resources from the site's homepage. */
	public function send_homepage_agent_link_headers() {
		if ( headers_sent() || ! is_front_page() || ! Settings::api_catalog_enabled() ) {
			return;
		}

		$catalog_url = home_url( self::API_CATALOG_PATH );
		$rest_url    = rest_url();
		header( sprintf( 'Link: <%s>; rel="api-catalog"; type="application/linkset+json"; profile="%s"', esc_url_raw( $catalog_url ), self::API_CATALOG_PROFILE ), false );
		header( sprintf( 'Link: <%s>; rel="service-desc"; type="application/json"', esc_url_raw( $rest_url ) ), false );
	}

	/** @param string $uri Request URI. @return bool */
	public static function is_api_catalog_request( $uri ) {
		$expected_path = (string) parse_url( home_url( self::API_CATALOG_PATH ), PHP_URL_PATH );
		return rtrim( (string) parse_url( (string) $uri, PHP_URL_PATH ), '/' ) === rtrim( $expected_path, '/' );
	}

	/** @return array<string, array<int, array<string, mixed>>> */
	public static function api_catalog_document( $catalog_url, $rest_url ) {
		return array(
			'linkset' => array(
				array(
					'anchor' => (string) $catalog_url,
					'item'   => array(
						array(
							'href'  => (string) $rest_url,
							'type'  => 'application/json',
							'title' => 'WordPress REST API',
						),
					),
				),
			),
		);
	}

	/**
	 * Whether the queried content has a Markdown representation.
	 *
	 * @return bool
	 */
	public function current_content_has_markdown() {
		if ( ! is_singular() || is_feed() || is_embed() || is_user_logged_in() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof \WP_Post )
			|| $post->post_status !== 'publish'
			|| ! empty( $post->post_password )
			|| ! is_post_publicly_viewable( $post )
			|| self::is_sensitive_woocommerce_page( $post )
			|| Exclusions::blocks( $post )
			|| ! Settings::markdown_enabled_for( $post->post_type )
			|| ! Settings::item_enabled( $post->ID ) ) {
			return false;
		}

		if ( 'product' !== $post->post_type ) {
			return Editorial_Content::is_static( $post );
		}

		return $post->post_type === 'product' && $this->product_generator->is_exposable( $post->ID );
	}

	/** @param \WP_Post $post Queried post. @return bool */
	private static function is_sensitive_woocommerce_page( $post ) {
		if ( 'page' !== $post->post_type ) {
			return false;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'pay', 'view_order' ) as $page ) {
				if ( (int) wc_get_page_id( $page ) === (int) $post->ID ) {
					return true;
				}
			}
		}

		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page', 'is_wc_endpoint_url' ) as $condition ) {
			if ( function_exists( $condition ) && call_user_func( $condition ) ) {
				return true;
			}
		}

		return false;
	}

	/** Print the Markdown alternate link for eligible HTML content. */
	public function render_html_alternate() {
		if ( ! $this->current_content_has_markdown() ) {
			return;
		}

		printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( $this->current_markdown_url() ) );
	}

	/**
	 * Send the alternate representation as an appended HTTP Link header.
	 *
	 * Priority 11 mirrors the core Link headers without replacing them. On a WP
	 * Rocket cache hit WordPress does not load, so this header is absent; the
	 * alternate element stored in the cached HTML remains the reliable signal.
	 * Markdown responses exit at dispatcher priority 0 and never reach this hook.
	 */
	public function send_http_link_header() {
		if ( headers_sent() || ! $this->current_content_has_markdown() ) {
			return;
		}

		header( sprintf( 'Link: <%s>; rel="alternate"; type="text/markdown"', sanitize_url( $this->current_markdown_url() ) ), false );
		header( 'Vary: Accept', false );
	}

	/** @return string */
	private function current_markdown_url() {
		return self::markdown_url( get_permalink( get_queried_object_id() ) );
	}

	/**
	 * Build the alternate URL using the same suffix expected by the router.
	 *
	 * @param string $permalink Canonical HTML permalink.
	 * @return string
	 */
	public static function markdown_url( $permalink ) {
		$permalink = (string) $permalink;
		$path      = (string) parse_url( $permalink, PHP_URL_PATH );
		if ( $path === '' || $path === '/' ) {
			return rtrim( $permalink, '/\\' ) . '/.md';
		}

		return rtrim( $permalink, '/\\' ) . '.md';
	}
}
