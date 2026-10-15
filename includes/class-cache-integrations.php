<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Integrates with full-page and bytecode caching plugins. */
final class Cache_Integrations {
	/** @var array<int, string> Known AI agents and developer tools that should bypass HTML page cache. */
	const REJECTED_USER_AGENTS = array(
		'curl',
		'GPTBot',
		'ChatGPT-User',
		'ClaudeBot',
		'Claude-Web',
		'anthropic-ai',
		'PerplexityBot',
		'Google-Extended',
		'Bytespider',
		'CCBot',
		'cohere-ai',
		'OAI-SearchBot',
		'Applebot-Extended',
		'Diffbot',
		'FacebookBot',
	);

	/** Register hooks for supported caching plugins. */
	public static function register_hooks() {
		add_filter( 'rocket_cache_reject_uri', array( __CLASS__, 'filter_rocket_reject_uri' ) );
		add_filter( 'rocket_cache_reject_ua', array( __CLASS__, 'filter_rocket_reject_ua' ) );
		add_action( 'after_rocket_clean_domain', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'after_rocket_clean_post', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'rocket_after_clean_domain', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'wp_cache_cleared', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'w3tc_flush_all', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'litespeed_purged_all', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
	}

	/**
	 * Ensure WP Rocket does not cache Markdown document URLs as static HTML.
	 *
	 * @param mixed $uris URIs rejected by WP Rocket.
	 * @return array<int, string>
	 */
	public static function filter_rocket_reject_uri( $uris ) {
		$uris    = is_array( $uris ) ? $uris : array();
		$pattern = '/(.*)\.md(?:/.*)?$';
		if ( ! in_array( $pattern, $uris, true ) ) {
			$uris[] = $pattern;
		}
		return $uris;
	}

	/**
	 * Bypass WP Rocket HTML cache for AI agents and CLI developer tools.
	 *
	 * @param mixed $uas User agents rejected by WP Rocket.
	 * @return array<int, string>
	 */
	public static function filter_rocket_reject_ua( $uas ) {
		$uas = is_array( $uas ) ? $uas : array();
		foreach ( self::REJECTED_USER_AGENTS as $ua ) {
			if ( ! in_array( $ua, $uas, true ) ) {
				$uas[] = $ua;
			}
		}
		return $uas;
	}

	/** Invalidate the plugin Markdown cache when an external cache flush is detected. */
	public static function on_cache_purged() {
		Markdown_Cache::invalidate( 'cache_sync' );
	}

	/**
	 * Declare standard constants to prevent page-cache plugins from buffering or
	 * minifying Markdown responses.
	 */
	public static function disable_page_caching() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard page-cache interoperability constant.
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTROCKETOPTIMIZE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard WP Rocket interoperability constant.
			define( 'DONOTROCKETOPTIMIZE', true );
		}
		if ( ! defined( 'DONOTMINIFY' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard minification-plugin interoperability constant.
			define( 'DONOTMINIFY', true );
		}
	}

	/** Invalidate known page caches when Markdown cache is invalidated. */
	public static function purge_page_caches() {
		static $purging = false;
		if ( $purging ) {
			return;
		}
		$purging = true;

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}

		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		if ( class_exists( '\Cache_Enabler' ) && method_exists( '\Cache_Enabler', 'clear_total_cache' ) ) {
			\Cache_Enabler::clear_total_cache();
		}

		$purging = false;
	}

	/** Ensure WP Rocket's configuration file on disk reflects our rejection rules. */
	public static function sync_rocket_config() {
		if ( ! function_exists( 'rocket_generate_config_file' ) ) {
			return;
		}

		$config_file = '';
		if ( function_exists( 'get_rocket_config_file_path' ) ) {
			$config_file = (string) get_rocket_config_file_path();
		} elseif ( defined( 'WP_ROCKET_CONFIG_PATH' ) ) {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			if ( '' !== $host ) {
				$config_file = WP_ROCKET_CONFIG_PATH . strtolower( $host ) . '.php';
			}
		}

		if ( '' !== $config_file && file_exists( $config_file ) ) {
			$contents = (string) file_get_contents( $config_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local configuration file.
			if ( false !== strpos( $contents, '\.md' ) && false !== strpos( $contents, 'GPTBot' ) ) {
				return;
			}
		}

		rocket_generate_config_file();
	}
}
