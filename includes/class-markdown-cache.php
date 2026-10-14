<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side cache for generated Markdown documents.
 *
 * Eligibility is always evaluated before a cached document is read, so a
 * visibility, password, opt-in or exclusion change takes effect immediately.
 * Cached bodies are keyed by a site-wide generation number that is advanced by
 * every relevant content, catalog, taxonomy or setting change; superseded
 * entries are never read again and expire on their own. HTTP responses keep
 * their private, no-store policy: this cache never reaches a proxy or CDN.
 */
final class Markdown_Cache {
	const STATE_OPTION = 'serfe_ai_discovery_markdown_cache';
	const PREFIX       = 'serfe_aid_md_';
	const TTL          = 86400;
	const MAX_BYTES    = 524288;

	/** @var array<int, string> Site options that change generated Markdown. */
	const SITE_OPTIONS = array(
		'blogname',
		'blogdescription',
		'home',
		'siteurl',
		'permalink_structure',
		'category_base',
		'tag_base',
		'WPLANG',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'posts_per_page',
		Settings::OPTION,
	);

	/** Register invalidation hooks for all Markdown sources. */
	public function register_hooks() {
		add_action( 'save_post', array( $this, 'post_changed' ), 20, 1 );
		add_action( 'deleted_post', array( $this, 'post_changed' ), 20, 1 );
		add_action( 'trashed_post', array( $this, 'post_changed' ), 20, 1 );
		add_action( 'untrashed_post', array( $this, 'post_changed' ), 20, 1 );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 20, 3 );
		add_action( 'set_object_terms', array( $this, 'terms_assigned' ), 20, 1 );
		add_action( 'created_term', array( $this, 'term_changed' ), 20, 0 );
		add_action( 'edited_term', array( $this, 'term_changed' ), 20, 0 );
		add_action( 'delete_term', array( $this, 'term_changed' ), 20, 0 );
		add_action( 'added_post_meta', array( $this, 'meta_changed' ), 20, 3 );
		add_action( 'updated_post_meta', array( $this, 'meta_changed' ), 20, 3 );
		add_action( 'deleted_post_meta', array( $this, 'meta_changed' ), 20, 3 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'catalog_changed' ), 20, 0 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'catalog_changed' ), 20, 0 );
		add_action( 'woocommerce_product_set_visibility', array( $this, 'catalog_changed' ), 20, 0 );
		add_action( 'updated_option', array( $this, 'option_changed' ), 20, 1 );
		add_action( 'added_option', array( $this, 'option_changed' ), 20, 1 );
	}

	/**
	 * Return a cached document or build, store and return a fresh one.
	 *
	 * @param string   $kind  Source kind, such as post or archive.
	 * @param string   $id    Source identifier.
	 * @param string   $url   Canonical HTML URL, including pagination.
	 * @param callable $build Generator returning Markdown.
	 * @return array{markdown: string, hit: bool}
	 */
	public static function remember( $kind, $id, $url, $build ) {
		$key    = self::key( $kind, $id, $url );
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) {
			return array(
				'markdown' => $cached,
				'hit'      => true,
			);
		}
		$markdown = (string) call_user_func( $build );
		if ( strlen( $markdown ) <= self::MAX_BYTES ) {
			set_transient( $key, $markdown, self::TTL );
		}
		return array(
			'markdown' => $markdown,
			'hit'      => false,
		);
	}

	/** @return string Transient key bound to the current generation and locale. */
	public static function key( $kind, $id, $url ) {
		$state = self::state();
		return self::PREFIX . md5( implode( '|', array( $state['generation'], (string) $kind, (string) $id, (string) $url, (string) get_locale() ) ) );
	}

	/** @return array{generation: int, invalidated_at: string, reason: string} */
	public static function state() {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return array(
			'generation'     => isset( $state['generation'] ) ? max( 1, (int) $state['generation'] ) : 1,
			'invalidated_at' => isset( $state['invalidated_at'] ) && is_string( $state['invalidated_at'] ) ? $state['invalidated_at'] : '',
			'reason'         => isset( $state['reason'] ) && is_string( $state['reason'] ) ? $state['reason'] : '',
		);
	}

	/** Advance the generation so every previously generated document is ignored. */
	public static function invalidate( $reason ) {
		static $depth = 0;
		if ( $depth > 0 ) {
			return;
		}
		++$depth;
		$state = self::state();
		update_option(
			self::STATE_OPTION,
			array(
				'generation'     => $state['generation'] + 1,
				'invalidated_at' => current_time( 'mysql' ),
				'reason'         => sanitize_key( $reason ),
			),
			true
		);
		if ( class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ) {
			Cache_Integrations::purge_page_caches();
		}
		--$depth;
	}

	/** @param int $post_id Changed post. */
	public function post_changed( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( in_array( $type, array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'wp_global_styles' ), true ) ) {
			return;
		}
		self::invalidate( 'content' );
	}

	/** @param string $new_status New status. @param string $old_status Previous status. @param \WP_Post $post Post. */
	public function status_changed( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && $post instanceof \WP_Post && 'revision' !== $post->post_type ) {
			self::invalidate( 'visibility' );
		}
	}

	/** @param int $object_id Object receiving terms. */
	public function terms_assigned( $object_id ) {
		if ( get_post( $object_id ) ) {
			self::invalidate( 'taxonomy' );
		}
	}

	/** Term names, descriptions and slugs appear in archives and permalinks. */
	public function term_changed() {
		self::invalidate( 'taxonomy' );
	}

	/** @param mixed $meta_id Meta IDs. @param int $object_id Post ID. @param string $meta_key Meta key. */
	public function meta_changed( $meta_id, $object_id, $meta_key ) {
		if ( is_string( $meta_key ) && 0 === strpos( $meta_key, '_edit_' ) ) {
			return;
		}
		if ( in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) {
			self::invalidate( 'catalog' );
		}
	}

	/** Product stock and catalog visibility changes. */
	public function catalog_changed() {
		self::invalidate( 'catalog' );
	}

	/** @param string $option Changed option. */
	public function option_changed( $option ) {
		if ( self::STATE_OPTION === $option || 0 === strpos( (string) $option, '_transient' ) || 0 === strpos( (string) $option, '_site_transient' ) ) {
			return;
		}
		if ( in_array( $option, self::SITE_OPTIONS, true )
			|| preg_match( '/^woocommerce_(?:currency|price_|prices_include_tax|tax_display_shop|calc_taxes|hide_out_of_stock_items|shop_page_id|permalinks|catalog_|weight_unit|dimension_unit)/', (string) $option ) ) {
			self::invalidate( Settings::OPTION === $option ? 'settings' : 'site' );
		}
	}
}
