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
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve encoded URI bytes for an exact path comparison; no raw value is rendered.
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
		$expected_path = (string) wp_parse_url( home_url( self::API_CATALOG_PATH ), PHP_URL_PATH );
		return rtrim( (string) wp_parse_url( (string) $uri, PHP_URL_PATH ), '/' ) === rtrim( $expected_path, '/' );
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
		return $post instanceof \WP_Post && ! self::is_woocommerce_request_endpoint() && $this->post_is_eligible( $post );
	}

	/**
	 * Request-independent publication policy for one item.
	 *
	 * @param \WP_Post $post Candidate item.
	 * @return bool
	 */
	public function post_is_eligible( $post ) {
		if ( ! ( $post instanceof \WP_Post )
			|| 'publish' !== $post->post_status
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

		return $this->product_generator->is_exposable( $post->ID );
	}

	/** Return an enabled public archive and its canonical page URL. */
	public function current_archive_context() {
		if ( is_404() || is_feed() || is_embed() || is_search() || is_user_logged_in() ) {
			return null;
		}
		$page = max( 1, (int) get_query_var( 'paged' ) );
		if ( is_home() ) {
			$post_type = get_post_type_object( 'post' );
			$posts_id  = (int) get_option( 'page_for_posts' );
			$url       = $posts_id > 0 ? get_permalink( $posts_id ) : home_url( '/' );
			$url       = 1 === $page ? $url : get_pagenum_link( $page, false );
			if ( ! $post_type || ! $post_type->public || ! $post_type->publicly_queryable || ! Settings::archive_enabled_for( 'post' )
				|| ! is_string( $url ) || '' === $url || Exclusions::blocks_archive( 'post', $url ) ) {
				return null;
			}
			return array(
				'type' => 'post',
				'page' => $page,
				'url'  => $url,
			);
		}
		if ( ( function_exists( 'is_category' ) && is_category() ) || ( function_exists( 'is_tag' ) && is_tag() ) || ( function_exists( 'is_tax' ) && is_tax() ) ) {
			$term     = get_queried_object();
			$taxonomy = $term instanceof \WP_Term ? get_taxonomy( $term->taxonomy ) : null;
			if ( ! ( $term instanceof \WP_Term ) || ! $taxonomy || empty( $taxonomy->public ) || empty( $taxonomy->publicly_queryable )
				|| ! in_array( 'post', (array) $taxonomy->object_type, true ) || ! Settings::archive_enabled_for( 'post' )
				|| Exclusions::blocks_term( $term->taxonomy, $term->term_id ) ) {
				return null;
			}
			$url = 1 === $page ? get_term_link( $term ) : get_pagenum_link( $page, false );
			if ( ! is_string( $url ) || '' === $url || Exclusions::blocks_archive( 'post', $url ) || Exclusions::blocks_archive( $term->taxonomy, $url ) ) {
				return null;
			}
			return array(
				'type'         => 'post',
				'page'         => $page,
				'url'          => $url,
				'term'         => $term,
				'archive_type' => $term->taxonomy,
			);
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$type = get_post_type_object( 'product' );
			$url  = 1 === $page ? get_post_type_archive_link( 'product' ) : get_pagenum_link( $page, false );
			if ( ! $type || ! $type->public || ! $type->publicly_queryable || ! Settings::archive_enabled_for( 'product' )
				|| ! is_string( $url ) || Exclusions::blocks_archive( 'product', $url ) ) {
				return null;
			}
			return array(
				'type' => 'product',
				'page' => $page,
				'url'  => $url,
			);
		}
		foreach ( array(
			'product_cat' => 'is_product_category',
			'product_tag' => 'is_product_tag',
		) as $taxonomy => $condition ) {
			if ( ! function_exists( $condition ) || ! call_user_func( $condition ) ) {
				continue;
			}
			$term   = get_queried_object();
			$object = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
			if ( ! ( $term instanceof \WP_Term ) || $taxonomy !== $term->taxonomy || ! $object || ! $object->public || ! $object->publicly_queryable
				|| ! Settings::archive_enabled_for( $taxonomy ) || Exclusions::blocks_term( $taxonomy, $term->term_id ) ) {
				return null;
			}
			$url = 1 === $page ? get_term_link( $term ) : get_pagenum_link( $page, false );
			if ( ! is_string( $url ) || Exclusions::blocks_archive( 'product', $url ) || Exclusions::blocks_archive( $taxonomy, $url ) ) {
				return null;
			}
			return array(
				'type' => $taxonomy,
				'page' => $page,
				'url'  => $url,
				'term' => $term,
			);
		}
		if ( ! is_post_type_archive() ) {
			return null;
		}
		$object = get_queried_object();
		if ( ! ( $object instanceof \WP_Post_Type ) || 'product' === $object->name || ! Settings::archive_enabled_for( $object->name ) ) {
			return null;
		}
		$type = get_post_type_object( $object->name );
		if ( ! $type || ! $type->public || ! $type->publicly_queryable || ! $type->has_archive ) {
			return null;
		}
		$url = 1 === $page ? get_post_type_archive_link( $type->name ) : get_pagenum_link( $page, false );
		if ( ! is_string( $url ) || '' === $url || Exclusions::blocks_archive( $type->name, $url ) ) {
			return null;
		}
		return array(
			'type' => $type->name,
			'page' => $page,
			'url'  => $url,
		);
	}

	/** @param \WP_Post $post Candidate post. @return bool */
	private static function is_sensitive_woocommerce_page( $post ) {
		if ( 'page' !== $post->post_type || ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		foreach ( array( 'cart', 'checkout', 'myaccount', 'pay', 'view_order', 'shop' ) as $page ) {
			if ( (int) wc_get_page_id( $page ) === (int) $post->ID ) {
				return true;
			}
		}

		return false;
	}

	/** @return bool Whether the current front-end request is a WooCommerce cart, checkout, account or endpoint view. */
	private static function is_woocommerce_request_endpoint() {
		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page', 'is_wc_endpoint_url' ) as $condition ) {
			if ( function_exists( $condition ) && call_user_func( $condition ) ) {
				return true;
			}
		}

		return false;
	}

	/** Print the Markdown alternate link for eligible HTML content. */
	public function render_html_alternate() {
		$url = $this->current_markdown_url();
		if ( '' === $url ) {
			return;
		}

		printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( $url ) );
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
		$url = $this->current_markdown_url();
		if ( headers_sent() || '' === $url ) {
			return;
		}

		header( sprintf( 'Link: <%s>; rel="alternate"; type="text/markdown"', sanitize_url( $url ) ), false );
		header( 'Vary: Accept', false );
	}

	/** @return string */
	private function current_markdown_url() {
		if ( $this->current_content_has_markdown() ) {
			return self::markdown_url( get_permalink( get_queried_object_id() ) );
		}
		$archive = $this->current_archive_context();
		return $archive ? self::markdown_url( $archive['url'] ) : '';
	}

	/**
	 * Build the alternate URL using the same suffix expected by the router.
	 *
	 * @param string $permalink Canonical HTML permalink.
	 * @return string
	 */
	public static function markdown_url( $permalink ) {
		$permalink = (string) $permalink;
		$path      = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		if ( '' === $path || '/' === $path ) {
			return rtrim( $permalink, '/\\' ) . '/.md';
		}

		return rtrim( $permalink, '/\\' ) . '.md';
	}
}
