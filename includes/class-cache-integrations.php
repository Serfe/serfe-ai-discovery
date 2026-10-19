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
		add_filter( 'rocket_config_file', array( __CLASS__, 'filter_rocket_config_file' ), 10, 1 );
		add_filter( 'rocket_htaccess_mod_rewrite', array( __CLASS__, 'filter_htaccess_bypass_markdown' ), 10, 1 );
		add_filter( 'after_rocket_htaccess_rules', array( __CLASS__, 'filter_rocket_after_htaccess_rules' ), 10, 1 );
		add_action( 'after_rocket_clean_domain', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'after_rocket_clean_post', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'rocket_after_clean_domain', array( __CLASS__, 'on_cache_purged' ), 20, 0 );

		add_filter( 'litespeed_can_cache', array( __CLASS__, 'filter_can_cache_request' ), 10, 1 );
		add_filter( 'litespeed_optm_uri_exc', array( __CLASS__, 'filter_litespeed_exclude_uri' ), 10, 1 );
		add_action( 'litespeed_purged_all', array( __CLASS__, 'on_cache_purged' ), 20, 0 );

		add_filter( 'w3tc_can_cache', array( __CLASS__, 'filter_can_cache_request' ), 10, 1 );
		add_filter( 'w3tc_can_print_comment', array( __CLASS__, 'filter_can_cache_request' ), 10, 1 );
		add_filter( 'w3tc_pagecache_rules_apache', array( __CLASS__, 'filter_htaccess_bypass_markdown' ), 10, 1 );
		add_action( 'w3tc_flush_all', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'w3tc_flush_posts', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'w3tc_pgcache_flush', array( __CLASS__, 'on_cache_purged' ), 20, 0 );

		add_action( 'wp_cache_cleared', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
		add_action( 'cache_enabler_complete_cache_cleared', array( __CLASS__, 'on_cache_purged' ), 20, 0 );
	}

	/**
	 * Return human-readable names of active page-cache plugins or drop-ins.
	 *
	 * @return array<int, string>
	 */
	public static function active_cache_plugins() {
		$plugins = array();

		if ( defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_clean_domain' ) ) {
			$plugins[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Purge' ) || class_exists( '\LiteSpeed\Core' ) ) {
			$plugins[] = 'LiteSpeed Cache';
		}
		if ( defined( 'W3TC' ) || function_exists( 'w3tc_flush_all' ) || function_exists( 'w3tc_pgcache_flush' ) ) {
			$plugins[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) || defined( 'WPSC_VERSION' ) ) {
			$plugins[] = 'WP Super Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) || defined( '\SiteGround_Optimizer\VERSION' ) ) {
			$plugins[] = 'SiteGround Optimizer';
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) || class_exists( 'WpFastestCache' ) ) {
			$plugins[] = 'WP Fastest Cache';
		}
		if ( class_exists( '\Cache_Enabler' ) ) {
			$plugins[] = 'Cache Enabler';
		}
		if ( class_exists( '\autoptimizeCache' ) ) {
			$plugins[] = 'Autoptimize';
		}
		if ( empty( $plugins ) && defined( 'WP_CACHE' ) && WP_CACHE ) {
			$plugins[] = 'WP_CACHE (advanced-cache.php)';
		}

		return $plugins;
	}

	/**
	 * Whether the current HTTP request negotiates or targets a Markdown representation.
	 *
	 * @return bool
	 */
	public static function is_markdown_http_request() {
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return true;
		}
		if ( ! class_exists( __NAMESPACE__ . '\Request_Router' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path inspected only by router helpers.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( Request_Router::is_markdown_uri( $uri ) ) {
			return true;
		}
		if ( ! Request_Router::is_negotiable_uri( $uri ) ) {
			return false;
		}
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		if ( Request_Router::accepts_markdown( $accept ) ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only query parameters.
		$markdown = isset( $_GET['markdown'] ) ? sanitize_text_field( wp_unslash( $_GET['markdown'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only query parameters.
		$format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : '';

		return '1' === $markdown || 'markdown' === $format;
	}

	/**
	 * Prevent LiteSpeed Cache and W3 Total Cache from caching Markdown responses or appending HTML comments.
	 *
	 * @param mixed $can_cache Whether the current request can be cached.
	 * @return bool
	 */
	public static function filter_can_cache_request( $can_cache ) {
		if ( self::is_markdown_http_request() ) {
			return false;
		}
		return (bool) $can_cache;
	}

	/**
	 * Exclude .md URLs from LiteSpeed Cache URI optimization and page caching.
	 *
	 * @param mixed $uris Excluded URI patterns.
	 * @return array<int, string>
	 */
	public static function filter_litespeed_exclude_uri( $uris ) {
		$uris    = is_array( $uris ) ? $uris : array();
		$pattern = '/(.*)\.md$';
		if ( ! in_array( $pattern, $uris, true ) ) {
			$uris[] = $pattern;
		}
		return $uris;
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

	/**
	 * Inject an Apache RewriteCond so static page-cache rewrites (WP Rocket, W3 Total Cache)
	 * bypass cached HTML files when the client sends Accept: text/markdown.
	 *
	 * @param mixed $rules Mod-rewrite block generated by the cache plugin.
	 * @return string
	 */
	public static function filter_htaccess_bypass_markdown( $rules ) {
		if ( ! is_string( $rules ) || '' === trim( $rules ) || false !== stripos( $rules, 'text/markdown' ) ) {
			return is_string( $rules ) ? $rules : '';
		}

		$cond = 'RewriteCond %{HTTP:Accept} !text/markdown [NC]' . "\n";
		$pos  = strripos( $rules, 'RewriteRule ' );
		if ( false === $pos ) {
			return $rules;
		}

		return substr( $rules, 0, $pos ) . $cond . substr( $rules, $pos );
	}

	/**
	 * Add Apache mod_headers rules for static WP Rocket cache files so Vary and Link headers
	 * remain present even when Apache serves .html/.html_gzip directly without PHP.
	 *
	 * @param mixed $rules Custom rules appended after WP Rocket's .htaccess block.
	 * @return string
	 */
	public static function filter_rocket_after_htaccess_rules( $rules ) {
		$rules   = is_string( $rules ) ? $rules : '';
		$headers = self::cached_agent_link_header_strings( false );

		$block  = "\n<IfModule mod_headers.c>\n";
		$block .= "<FilesMatch \"index(-https)?(-webp)?\\.html(_gzip)?$\">\n";
		$block .= "Header append Vary \"Accept\"\n";
		foreach ( $headers as $header_line ) {
			$value  = substr( $header_line, strlen( 'Link: ' ) );
			$block .= 'Header add Link "' . addcslashes( $value, '"\\' ) . "\"\n";
		}
		$block .= "</FilesMatch>\n";
		$block .= "</IfModule>\n";

		return $rules . $block;
	}

	/**
	 * Inject Accept: text/markdown bypass and early Link header support into WP Rocket's domain config file.
	 *
	 * @param mixed $buffer PHP code written to wp-rocket-config/*.php.
	 * @return string
	 */
	public static function filter_rocket_config_file( $buffer ) {
		$buffer = is_string( $buffer ) ? $buffer : '';
		if ( false !== strpos( $buffer, 'serfe-ai-discovery-cache-sig:' ) ) {
			return $buffer;
		}

		$common_headers = self::cached_agent_link_header_strings( false );
		$home_headers   = self::cached_agent_link_header_strings( true );
		$home_path      = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path      = '' === $home_path ? '/' : '/' . trim( $home_path, '/' ) . '/';
		if ( '//' === $home_path ) {
			$home_path = '/';
		}
		$sig = self::rocket_config_signature();

		$snippet  = "\n// serfe-ai-discovery-cache-sig:" . $sig . "\n";
		$snippet .= "if ( ! empty( \$_SERVER['HTTP_ACCEPT'] ) && false !== stripos( \$_SERVER['HTTP_ACCEPT'], 'text/markdown' ) && ! preg_match( '/text\\/markdown\\s*;\\s*q\\s*=\\s*0(?:\\.0+)?\\b/i', \$_SERVER['HTTP_ACCEPT'] ) ) {\n";
		$snippet .= "\t\$rocket_cache_reject_uri = '.*';\n";
		$snippet .= "} elseif ( function_exists( 'header_register_callback' ) && empty( \$GLOBALS['serfe_ai_discovery_rocket_cb'] ) ) {\n";
		$snippet .= "\t\$GLOBALS['serfe_ai_discovery_rocket_cb'] = true;\n";
		$snippet .= "\theader_register_callback( function () {\n";
		$snippet .= "\t\tif ( ( function_exists( 'did_action' ) && did_action( 'muplugins_loaded' ) ) || headers_sent() ) {\n";
		$snippet .= "\t\t\treturn;\n";
		$snippet .= "\t\t}\n";
		$snippet .= "\t\theader( 'Vary: Accept', false );\n";
		foreach ( $common_headers as $line ) {
			$snippet .= "\t\theader( " . var_export( $line, true ) . ", false );\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Generates valid PHP string literal for WP Rocket config file.
		}
		if ( ! empty( $home_headers ) ) {
			$snippet .= "\t\t\$req_path = isset( \$_SERVER['REQUEST_URI'] ) ? (string) strtok( \$_SERVER['REQUEST_URI'], '?' ) : '';\n";
			$snippet .= "\t\t\$req_path = '' === \$req_path ? '/' : '/' . trim( \$req_path, '/' ) . '/';\n";
			$snippet .= "\t\tif ( '//' === \$req_path ) {\n";
			$snippet .= "\t\t\t\$req_path = '/';\n";
			$snippet .= "\t\t}\n";
			$snippet .= "\t\tif ( " . var_export( $home_path, true ) . " === \$req_path ) {\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Generates valid PHP string literal for WP Rocket config file.
			foreach ( $home_headers as $line ) {
				$snippet .= "\t\t\theader( " . var_export( $line, true ) . ", false );\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Generates valid PHP string literal for WP Rocket config file.
			}
			$snippet .= "\t\t}\n";
		}
		$snippet .= "\t} );\n";
		$snippet .= "}\n";

		return $buffer . $snippet;
	}

	/**
	 * Build the HTTP Link header strings to emit on cached HTML responses.
	 *
	 * @param bool $homepage_only True for homepage-only Link headers (api-catalog, service-desc).
	 * @return array<int, string>
	 */
	public static function cached_agent_link_header_strings( $homepage_only = false ) {
		$headers = array();
		if ( $homepage_only ) {
			if ( class_exists( __NAMESPACE__ . '\Settings' ) && class_exists( __NAMESPACE__ . '\Discovery' ) && Settings::api_catalog_enabled() ) {
				$catalog_url = home_url( Discovery::API_CATALOG_PATH );
				$rest_url    = function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );
				$headers[]   = sprintf( 'Link: <%s>; rel="api-catalog"; type="application/linkset+json"; profile="%s"', esc_url_raw( $catalog_url ), Discovery::API_CATALOG_PROFILE );
				$headers[]   = sprintf( 'Link: <%s>; rel="service-desc"; type="application/json"', esc_url_raw( $rest_url ) );
			}
			return $headers;
		}

		if ( ! class_exists( __NAMESPACE__ . '\Readiness' ) ) {
			return $headers;
		}

		$links = get_transient( Readiness::LINK_HEADERS_CACHE_KEY );
		if ( ! is_array( $links ) ) {
			return $headers;
		}

		foreach ( $links as $link ) {
			if ( ! is_array( $link ) || empty( $link['url'] ) || empty( $link['rel'] ) || empty( $link['type'] ) ) {
				continue;
			}
			$headers[] = sprintf(
				'Link: <%1$s>; rel="%2$s"; type="%3$s"',
				esc_url_raw( (string) $link['url'] ),
				sanitize_key( (string) $link['rel'] ),
				sanitize_text_field( (string) $link['type'] )
			);
		}

		return $headers;
	}

	/**
	 * Compute a deterministic hash representing the current WP Rocket integration config state.
	 *
	 * @return string
	 */
	public static function rocket_config_signature() {
		$payload = array(
			'version' => '2',
			'common'  => self::cached_agent_link_header_strings( false ),
			'home'    => self::cached_agent_link_header_strings( true ),
		);
		return md5( (string) wp_json_encode( $payload ) );
	}

	/** Invalidate the plugin Markdown cache when an external cache flush is detected. */
	public static function on_cache_purged() {
		Markdown_Cache::invalidate( 'cache_sync' );
	}

	/**
	 * Declare standard constants and fire cache-control hooks to prevent page-cache
	 * plugins from buffering, caching, or minifying Markdown responses.
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
		if ( function_exists( 'do_action' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official LiteSpeed Cache third-party API hook.
			do_action( 'litespeed_control_set_nocache', 'Serfe AI Discovery Markdown response' );
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
		} elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}

		if ( function_exists( 'do_action' ) && ( defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Purge' ) ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official LiteSpeed Cache third-party API hook.
			do_action( 'litespeed_purge_all' );
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}

		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		if ( class_exists( '\Cache_Enabler' ) && method_exists( '\Cache_Enabler', 'clear_total_cache' ) ) {
			\Cache_Enabler::clear_total_cache();
		}

		$purging = false;
	}

	/** Ensure WP Rocket's configuration file on disk reflects our rejection and Link header rules. */
	public static function sync_rocket_config() {
		if ( ! function_exists( 'rocket_generate_config_file' ) ) {
			return;
		}

		$config_files = array();
		if ( function_exists( 'get_rocket_config_file' ) ) {
			$rocket_config = get_rocket_config_file();
			if ( is_array( $rocket_config ) && isset( $rocket_config[0] ) && is_array( $rocket_config[0] ) ) {
				$config_files = $rocket_config[0];
			}
		} elseif ( function_exists( 'get_rocket_config_file_path' ) ) {
			$config_files[] = (string) get_rocket_config_file_path();
		} elseif ( defined( 'WP_ROCKET_CONFIG_PATH' ) ) {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			if ( '' !== $host ) {
				$config_files[] = WP_ROCKET_CONFIG_PATH . strtolower( $host ) . '.php';
			}
		}

		$expected_sig = 'serfe-ai-discovery-cache-sig:' . self::rocket_config_signature();
		$in_sync      = ! empty( $config_files );

		foreach ( $config_files as $config_file ) {
			if ( '' === $config_file || ! file_exists( $config_file ) ) {
				$in_sync = false;
				break;
			}
			$contents = (string) file_get_contents( $config_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local configuration file.
			if ( false === strpos( $contents, '\.md' ) || false === strpos( $contents, 'GPTBot' ) || false === strpos( $contents, $expected_sig ) ) {
				$in_sync = false;
				break;
			}
		}

		if ( $in_sync ) {
			return;
		}

		rocket_generate_config_file();
		if ( function_exists( 'flush_rocket_htaccess' ) && ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) ) {
			flush_rocket_htaccess();
		}
	}
}
