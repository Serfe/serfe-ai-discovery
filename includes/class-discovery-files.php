<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Safely publishes the two public-root discovery files. */
final class Discovery_Files {
	const MAX_BYTES           = 131072;
	const MAX_LIMIT           = 1048576;
	const MAX_SITEMAPS        = 5;
	const CONTENT_SIGNALS_KEY = 'serfe_ai_discovery_virtual_robots_content_signals';

	/** Multisite sites use per-blog virtual files; a physical root is shared. */
	public static function uses_virtual_storage() {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/** Register virtual public files before WordPress renders a 404 or core robots response. */
	public static function register_hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'serve_virtual_file' ), -100 );
		if ( self::content_signals_enabled() ) {
			add_filter( 'robots_txt', array( __CLASS__, 'add_content_signals' ), 9999 );
		}
	}

	/** Whether Content Signals are appended to WordPress's virtual robots.txt response. */
	public static function content_signals_enabled() {
		return (bool) get_option( self::CONTENT_SIGNALS_KEY, false );
	}

	/** Append this plugin's marked Content Signals block to a virtual robots.txt response. */
	public static function add_content_signals( $robots ) {
		if ( ! is_string( $robots ) || false !== strpos( $robots, '# START SERFE BLOCK' ) ) {
			return $robots;
		}
		return rtrim( $robots ) . "\n\n" . self::content_signals_block() . "\n";
	}

	/** Return the reviewed, site-wide Content Signals block. */
	public static function content_signals_block() {
		return "# START SERFE BLOCK\n# ---------------------------\n# Allow unfettered access to your / page\nUser-Agent: *\nContent-Signal: / ai-train=yes, search=yes, ai-input=yes\nAllow: /\n\n# ---------------------------\n# END SERFE BLOCK";
	}

	/** Enable Content Signals only after confirming that WordPress serves a virtual robots.txt. */
	public static function enable_virtual_content_signals() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to manage Content Signals.', 'serfe-ai-discovery' ) );
		}
		$physical = self::physical_file_exists( 'robots.txt' );
		if ( is_wp_error( $physical ) ) {
			return $physical;
		}
		if ( $physical ) {
			return new \WP_Error( 'physical_robots_exists', __( 'A physical robots.txt file exists. Edit it through Advanced file management or the plugin that manages it.', 'serfe-ai-discovery' ) );
		}
		if ( self::uses_virtual_storage() && is_string( get_option( self::content_key( 'robots.txt' ), false ) ) ) {
			return new \WP_Error( 'serfe_virtual_robots', __( 'This site already serves a virtual robots.txt managed by Serfe. Edit its content through Advanced file management.', 'serfe-ai-discovery' ) );
		}
		$public = self::inspect_public_response( 'robots.txt' );
		if ( is_wp_error( $public ) ) {
			return $public;
		}
		if ( ! $public['exists'] ) {
			return new \WP_Error( 'virtual_robots_missing', __( 'No virtual robots.txt response was found. Create or enable one before adding Content Signals automatically.', 'serfe-ai-discovery' ) );
		}
		update_option( self::CONTENT_SIGNALS_KEY, true, false );
		return array(
			'verified' => true,
			'warnings' => array(),
			'url'      => home_url( '/robots.txt' ),
		);
	}

	/** Serve a Multisite blog's saved discovery file at its own public URL. */
	public static function serve_virtual_file() {
		if ( ! self::uses_virtual_storage() || is_admin() ) {
			return;
		}
		$name = self::requested_virtual_file();
		if ( '' === $name ) {
			return;
		}
		$content = get_option( self::content_key( $name ), false );
		if ( ! is_string( $content ) ) {
			return;
		}
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: public, max-age=300' );
		}
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Administrator-authored plain text validated before storage.
		exit;
	}

	/** Resolve the public root only when the WordPress URL and directory layout agree. */
	public static function resolve_public_root( $wp_root, $home_url, $site_url ) {
		$home = wp_parse_url( $home_url );
		$site = wp_parse_url( $site_url );
		if ( ! is_array( $home ) || ! is_array( $site ) || empty( $home['host'] ) || empty( $site['host'] )
			|| strtolower( $home['host'] ) !== strtolower( $site['host'] )
			|| ( isset( $home['port'] ) ? (int) $home['port'] : null ) !== ( isset( $site['port'] ) ? (int) $site['port'] : null ) ) {
			return new \WP_Error( 'root_unresolved', __( 'The public root cannot be mapped safely from the site URLs.', 'serfe-ai-discovery' ) );
		}
		$home_path = '/' . trim( isset( $home['path'] ) ? $home['path'] : '/', '/' );
		$site_path = '/' . trim( isset( $site['path'] ) ? $site['path'] : '/', '/' );
		$home_path = '/' === $home_path ? '/' : trailingslashit( $home_path );
		$site_path = '/' === $site_path ? '/' : trailingslashit( $site_path );
		if ( 0 !== strpos( $site_path, $home_path ) ) {
			return new \WP_Error( 'root_unresolved', __( 'The WordPress URL is outside the public site URL.', 'serfe-ai-discovery' ) );
		}
		$relative = trim( substr( $site_path, strlen( $home_path ) ), '/' );
		$root     = rtrim( $wp_root, '/\\' );
		if ( '' === $root || ! is_dir( $root ) || is_link( $root ) ) {
			return new \WP_Error( 'root_unresolved', __( 'The WordPress directory cannot be verified.', 'serfe-ai-discovery' ) );
		}
		if ( '' !== $relative ) {
			$segments = explode( '/', $relative );
			foreach ( array_reverse( $segments ) as $segment ) {
				$segment = rawurldecode( $segment );
				if ( '' === $segment || '.' === $segment || '..' === $segment || false !== strpbrk( $segment, '/\\' )
					|| basename( $root ) !== $segment || is_link( $root ) ) {
					return new \WP_Error( 'root_unresolved', __( 'The WordPress directory does not match its public URL path.', 'serfe-ai-discovery' ) );
				}
				$root = dirname( $root );
			}
		}
		if ( ! is_dir( $root ) || is_link( $root ) || false === realpath( $root ) ) {
			return new \WP_Error( 'root_unresolved', __( 'The public root is not an accessible directory.', 'serfe-ai-discovery' ) );
		}
		return realpath( $root );
	}

	/** Validate and normalize a submitted UTF-8 text file without rewriting its policy. */
	public static function normalize_content( $content ) {
		$limit = (int) apply_filters( 'serfe_ai_discovery_max_file_bytes', self::MAX_BYTES );
		$limit = max( 1, min( self::MAX_LIMIT, $limit ) );
		if ( ! is_string( $content ) || 1 !== preg_match( '//u', $content ) ) {
			return new \WP_Error( 'invalid_encoding', __( 'The file must contain valid UTF-8 text.', 'serfe-ai-discovery' ) );
		}
		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content ) ) {
			return new \WP_Error( 'invalid_control', __( 'The file contains a non-permitted control character.', 'serfe-ai-discovery' ) );
		}
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		if ( strlen( $content ) > $limit ) {
			return new \WP_Error( 'file_too_large', __( 'The file exceeds the configured size limit.', 'serfe-ai-discovery' ) );
		}
		return $content;
	}

	/** Return the current root file and publication metadata without changing it. */
	public static function inspect( $name ) {
		if ( self::uses_virtual_storage() ) {
			if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) ) {
				return new \WP_Error( 'invalid_name', __( 'Only robots.txt and llms.txt can be published.', 'serfe-ai-discovery' ) );
			}
			$content = get_option( self::content_key( $name ), false );
			$content = is_string( $content ) ? $content : '';
			$meta    = get_option( self::meta_key( $name ), array() );
			return array(
				'exists'   => false,
				'content'  => $content,
				'checksum' => '' !== $content ? hash( 'sha256', $content ) : '',
				'meta'     => is_array( $meta ) ? $meta : array(),
				'backup'   => get_option( self::backup_key( $name ), false ),
				'virtual'  => true,
			);
		}
		$path = self::path( $name );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( is_link( $path ) || ( file_exists( $path ) && ! is_file( $path ) ) ) {
			return new \WP_Error( 'unsafe_file', __( 'The public path is not a regular file.', 'serfe-ai-discovery' ) );
		}
		$exists = file_exists( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This reads a fixed local public-root file, not a URL.
		$content = $exists ? file_get_contents( $path ) : '';
		if ( false === $content ) {
			return new \WP_Error( 'read_failed', __( 'The public file could not be read.', 'serfe-ai-discovery' ) );
		}
		if ( strlen( $content ) > self::MAX_LIMIT ) {
			return new \WP_Error( 'file_too_large', __( 'The existing public file is too large to manage safely.', 'serfe-ai-discovery' ) );
		}
		$meta = get_option( self::meta_key( $name ), array() );
		$meta = is_array( $meta ) ? $meta : array();
		return array(
			'exists'   => $exists,
			'content'  => $content,
			'checksum' => $exists ? hash( 'sha256', $content ) : '',
			'meta'     => $meta,
			'backup'   => get_option( self::backup_key( $name ), false ),
		);
	}

	/** Check only the fixed, public-root path without reading or changing it. */
	public static function physical_file_exists( $name ) {
		$path = self::path( $name );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( is_link( $path ) || ( file_exists( $path ) && ! is_file( $path ) ) ) {
			return new \WP_Error( 'unsafe_file', __( 'The public path is not a regular file.', 'serfe-ai-discovery' ) );
		}
		return file_exists( $path );
	}

	/** Inspect a public response only after an administrator explicitly requests validation or publication. */
	public static function inspect_public_response( $name ) {
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to inspect discovery files.', 'serfe-ai-discovery' ) );
		}
		$response = wp_safe_remote_get(
			home_url( '/' . $name ),
			array(
				'redirection'         => 0,
				'timeout'             => 5,
				'limit_response_size' => self::MAX_LIMIT + 1,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'public_unverified', __( 'The public response could not be checked. No root file was written.', 'serfe-ai-discovery' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) {
			return array(
				'exists'   => false,
				'content'  => '',
				'checksum' => '',
			);
		}
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$content      = wp_remote_retrieve_body( $response );
		if ( 200 !== $code || ! is_string( $content_type ) || 0 !== stripos( $content_type, 'text/plain' )
			|| ! is_string( $content ) || strlen( $content ) > self::MAX_LIMIT ) {
			return new \WP_Error( 'public_unverified', __( 'The public response is not a bounded plain-text file. No root file was written.', 'serfe-ai-discovery' ) );
		}
		if ( is_wp_error( self::normalize_content( $content ) ) ) {
			return new \WP_Error( 'public_unverified', __( 'The public response is not valid editable text. No root file was written.', 'serfe-ai-discovery' ) );
		}
		return array(
			'exists'   => true,
			'content'  => $content,
			'checksum' => hash( 'sha256', $content ),
		);
	}

	/** Publish an approved file, requiring an exact current checksum on takeover. */
	public static function publish( $name, $candidate, $takeover = false, $expected_checksum = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to publish discovery files.', 'serfe-ai-discovery' ) );
		}
		return self::publish_content( $name, $candidate, $takeover, $expected_checksum );
	}

	/** Regenerate only an unchanged llms.txt file that Serfe already manages. */
	public static function regenerate_managed_llms( $candidate, $expected_checksum ) {
		$content = self::normalize_content( $candidate );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$state = self::inspect( 'llms.txt' );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$known = isset( $state['meta']['checksum'] ) ? (string) $state['meta']['checksum'] : '';
		if ( '' === $known || ! hash_equals( $known, $state['checksum'] ) || ! hash_equals( $state['checksum'], (string) $expected_checksum ) ) {
			return new \WP_Error( 'stale_file', __( 'The llms.txt file changed since this regeneration was scheduled. Review it before scheduling another update.', 'serfe-ai-discovery' ) );
		}
		return self::publish_content( 'llms.txt', $content, true, $expected_checksum );
	}

	/** Publish content after the caller has performed its capability or ownership checks. */
	private static function publish_content( $name, $candidate, $takeover = false, $expected_checksum = '' ) {
		$content = self::normalize_content( $candidate );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		if ( self::uses_virtual_storage() ) {
			return self::publish_virtual( $name, $content, $takeover, $expected_checksum );
		}
		$current = self::inspect( $name );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$known    = isset( $current['meta']['checksum'] ) ? $current['meta']['checksum'] : '';
		$conflict = $known !== $current['checksum'];
		if ( $conflict && ! $takeover ) {
			return new \WP_Error( 'takeover_required', __( 'The public file is unmanaged or has changed outside this plugin. Review it and confirm takeover before publishing.', 'serfe-ai-discovery' ) );
		}
		$virtual = false;
		if ( ! $current['exists'] ) {
			$virtual = self::inspect_public_response( $name );
			if ( is_wp_error( $virtual ) ) {
				return $virtual;
			}
			if ( $virtual['exists'] && ! $takeover ) {
				return new \WP_Error( 'takeover_required', __( 'A virtual public response already exists. Inspect it and explicitly confirm takeover before creating a physical file.', 'serfe-ai-discovery' ) );
			}
		}
		$effective_checksum = is_array( $virtual ) && $virtual['exists'] ? $virtual['checksum'] : $current['checksum'];
		if ( ( $current['exists'] || '' !== $known || ( is_array( $virtual ) && $virtual['exists'] ) || '' !== (string) $expected_checksum ) && ! hash_equals( $effective_checksum, (string) $expected_checksum ) ) {
			return new \WP_Error( 'stale_file', __( 'The public file changed since this editor was opened. Review the current content before publishing.', 'serfe-ai-discovery' ) );
		}
		$path = self::path( $name );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$root = dirname( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Atomic writes require direct access to this verified public root.
		if ( ! is_writable( $root ) ) {
			return new \WP_Error( 'not_writable', __( 'The public root is not writable by WordPress.', 'serfe-ai-discovery' ) );
		}
		$temp = tempnam( $root, '.serfe-ai-' );
		if ( false === $temp || realpath( dirname( $temp ) ) !== $root ) {
			if ( false !== $temp ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only the temporary file created above.
				unlink( $temp );
			}
			return new \WP_Error( 'write_failed', __( 'A temporary file could not be created in the public root.', 'serfe-ai-discovery' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write a temporary file in the verified root before atomic rename.
		if ( strlen( $content ) !== file_put_contents( $temp, $content, LOCK_EX ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only the temporary file created above.
			unlink( $temp );
			return new \WP_Error( 'write_failed', __( 'The temporary file could not be written completely.', 'serfe-ai-discovery' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Preserve the prior public file mode on atomic replacement.
		chmod( $temp, $current['exists'] ? ( fileperms( $path ) & 0777 ) : 0644 );
		$latest = self::inspect( $name );
		if ( is_wp_error( $latest ) || $latest['checksum'] !== $current['checksum'] ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only the temporary file created above.
			unlink( $temp );
			return new \WP_Error( 'stale_file', __( 'The public file changed during publication. Review it before trying again.', 'serfe-ai-discovery' ) );
		}
		if ( is_array( $virtual ) ) {
			$latest_public = self::inspect_public_response( $name );
			if ( is_wp_error( $latest_public ) || $latest_public['checksum'] !== $virtual['checksum'] ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only the temporary file created above.
				unlink( $temp );
				return new \WP_Error( 'stale_file', __( 'The public response changed during publication. Review it before trying again.', 'serfe-ai-discovery' ) );
			}
		}
		if ( $current['exists'] || ( is_array( $virtual ) && $virtual['exists'] ) ) {
			update_option( self::backup_key( $name ), $current['exists'] ? $current['content'] : $virtual['content'], false );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Rename within the same directory provides atomic publication.
		if ( is_link( $path ) || ! rename( $temp, $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only the temporary file created above.
			unlink( $temp );
			return new \WP_Error( 'write_failed', __( 'The public file could not be replaced atomically.', 'serfe-ai-discovery' ) );
		}
		$checksum = hash( 'sha256', $content );
		update_option(
			self::meta_key( $name ),
			array(
				'checksum'     => $checksum,
				'published_at' => current_time( 'mysql' ),
				'user_id'      => get_current_user_id(),
			),
			false
		);
		if ( 'llms.txt' === $name && class_exists( __NAMESPACE__ . '\\Readiness' ) ) {
			delete_transient( Readiness::LINK_HEADERS_CACHE_KEY );
		}
		$check = self::validate_current( $name, true );
		$url   = home_url( '/' . $name );
		return array(
			'checksum' => $checksum,
			'verified' => ! is_wp_error( $check ) && $check['verified'],
			'warnings' => is_wp_error( $check ) ? array( $check->get_error_message() ) : $check['warnings'],
			'url'      => $url,
		);
	}

	/** Check the current public response and declared sitemap URLs only on an admin action. */
	public static function validate_current( $name, $trusted = false ) {
		if ( ! $trusted && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to validate discovery files.', 'serfe-ai-discovery' ) );
		}
		$state = self::inspect( $name );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( self::uses_virtual_storage() ) {
			return self::validate_virtual( $name, $state );
		}
		if ( ! $state['exists'] ) {
			$public = self::inspect_public_response( $name );
			if ( is_wp_error( $public ) ) {
				return $public;
			}
			$warnings = $public['exists']
				? array( __( 'A virtual public response exists without a physical root file. Publishing here would replace the response served by WordPress or another plugin.', 'serfe-ai-discovery' ) )
				: array( __( 'No physical root file or public response was found.', 'serfe-ai-discovery' ) );
			if ( 'robots.txt' === $name && $public['exists'] ) {
				$warnings = array_merge( $warnings, self::robots_warnings( $public['content'] ) );
			}
			return array(
				'verified' => false,
				'warnings' => $warnings,
				'virtual'  => $public['exists'],
				'content'  => $public['content'],
				'checksum' => $public['checksum'],
			);
		}
		$url      = home_url( '/' . $name );
		$response = wp_safe_remote_get(
			$url,
			array(
				'redirection'         => 0,
				'timeout'             => 5,
				'limit_response_size' => self::MAX_LIMIT + 1,
			)
		);
		$verified = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) && wp_remote_retrieve_body( $response ) === $state['content'];
		$warnings = array();
		if ( ! $verified ) {
			$warnings[] = __( 'The public response does not match the current root file, or it could not be reached.', 'serfe-ai-discovery' );
		}
		if ( 'robots.txt' === $name ) {
			$warnings = array_merge( $warnings, self::robots_warnings( $state['content'] ) );
		}
		$meta = $state['meta'];
		if ( isset( $meta['checksum'] ) && $meta['checksum'] === $state['checksum'] ) {
			$meta['validated_at'] = current_time( 'mysql' );
			$meta['verified']     = $verified;
			update_option( self::meta_key( $name ), $meta, false );
		}
		return array(
			'verified' => $verified,
			'warnings' => $warnings,
		);
	}

	/** Collect non-blocking policy and sitemap warnings for robots.txt. */
	public static function robots_warnings( $content ) {
		$warnings = array();
		if ( ! preg_match_all( '/^\s*Sitemap\s*:\s*(.*?)\s*$/im', $content, $matches ) ) {
			$warnings[] = __( 'No sitemap is declared in robots.txt.', 'serfe-ai-discovery' );
		}
		if ( preg_match( '/^\s*Disallow\s*:\s*\/\s*$/im', $content ) ) {
			$warnings[] = __( 'A rule appears to block the entire public site. Review whether this matches your crawler policy.', 'serfe-ai-discovery' );
		}
		foreach ( array_slice( isset( $matches[1] ) ? $matches[1] : array(), 0, self::MAX_SITEMAPS ) as $url ) {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
				|| isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$warnings[] = __( 'A Sitemap URL is malformed. Review its absolute public URL.', 'serfe-ai-discovery' );
				continue;
			}
			if ( ! self::is_same_origin_url( $url ) ) {
				$warnings[] = __( 'A Sitemap URL points outside this site and was not checked.', 'serfe-ai-discovery' );
				continue;
			}
			$response = wp_safe_remote_head(
				$url,
				array(
					'redirection' => 0,
					'timeout'     => 3,
				)
			);
			if ( ! is_wp_error( $response ) && 405 === wp_remote_retrieve_response_code( $response ) ) {
				$response = wp_safe_remote_get(
					$url,
					array(
						'redirection'         => 0,
						'timeout'             => 3,
						'limit_response_size' => 512,
					)
				);
			}
			if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 401, 403 ), true ) ) {
				$warnings[] = __( 'A Sitemap URL requires authentication or is not publicly accessible.', 'serfe-ai-discovery' );
				continue;
			}
			if ( is_wp_error( $response ) || 200 > wp_remote_retrieve_response_code( $response ) || 299 < wp_remote_retrieve_response_code( $response ) ) {
				/* translators: %s: Sitemap URL from robots.txt. */
				$warnings[] = sprintf( __( 'The Sitemap URL could not be reached: %s', 'serfe-ai-discovery' ), $url );
			}
		}
		return $warnings;
	}

	/** Whether a complete URL has the same scheme, host, and port as the public site. */
	private static function is_same_origin_url( $url ) {
		$target = wp_parse_url( $url );
		$site   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $site ) || empty( $target['scheme'] ) || empty( $target['host'] ) || empty( $site['scheme'] ) || empty( $site['host'] ) ) {
			return false;
		}
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : ( 'https' === strtolower( $target['scheme'] ) ? 443 : 80 );
		$site_port   = isset( $site['port'] ) ? (int) $site['port'] : ( 'https' === strtolower( $site['scheme'] ) ? 443 : 80 );
		return strtolower( $target['scheme'] ) === strtolower( $site['scheme'] )
			&& strtolower( $target['host'] ) === strtolower( $site['host'] )
			&& $target_port === $site_port;
	}

	/** Restore the one previous version using the same conflict checks as publication. */
	public static function restore( $name, $takeover = false, $expected_checksum = '' ) {
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) ) {
			return new \WP_Error( 'invalid_name', __( 'Only robots.txt and llms.txt can be published.', 'serfe-ai-discovery' ) );
		}
		$backup = get_option( self::backup_key( $name ), false );
		if ( ! is_string( $backup ) ) {
			return new \WP_Error( 'no_backup', __( 'No previous version is available.', 'serfe-ai-discovery' ) );
		}
		return self::publish( $name, $backup, $takeover, $expected_checksum );
	}

	/** Stop managing a physical file without changing its current contents. */
	public static function release_control( $name, $expected_checksum = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to release discovery files.', 'serfe-ai-discovery' ) );
		}
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) ) {
			return new \WP_Error( 'invalid_name', __( 'Only robots.txt and llms.txt can be released.', 'serfe-ai-discovery' ) );
		}
		if ( self::uses_virtual_storage() ) {
			return new \WP_Error( 'virtual_release_unsupported', __( 'This virtual file remains served by Serfe until its content is removed. Releasing it without changing the public response is not available.', 'serfe-ai-discovery' ) );
		}
		$state = self::inspect( $name );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$known = isset( $state['meta']['checksum'] ) ? (string) $state['meta']['checksum'] : '';
		if ( '' === $known || ! hash_equals( $known, $state['checksum'] ) || ! hash_equals( $state['checksum'], (string) $expected_checksum ) ) {
			return new \WP_Error( 'stale_file', __( 'The public file changed since Serfe last managed it. Review the current content before releasing control.', 'serfe-ai-discovery' ) );
		}
		delete_option( self::meta_key( $name ) );
		delete_option( self::backup_key( $name ) );
		return array(
			'checksum' => $state['checksum'],
			'verified' => true,
			'warnings' => array(),
			'url'      => home_url( '/' . $name ),
		);
	}

	/** Store a Multisite blog's discovery file without touching the shared filesystem. */
	private static function publish_virtual( $name, $content, $takeover, $expected_checksum ) {
		$current = self::inspect( $name );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$stored     = get_option( self::content_key( $name ), false );
		$has_stored = is_string( $stored );
		$known      = isset( $current['meta']['checksum'] ) ? $current['meta']['checksum'] : '';
		$conflict   = $has_stored && $known !== $current['checksum'];
		if ( $conflict && ! $takeover ) {
			return new \WP_Error( 'takeover_required', __( 'The virtual public file changed outside this plugin. Review it and confirm takeover before publishing.', 'serfe-ai-discovery' ) );
		}
		$public = false;
		if ( ! $has_stored ) {
			$public = self::inspect_public_response( $name );
			if ( is_wp_error( $public ) ) {
				return $public;
			}
			if ( $public['exists'] && ! $takeover ) {
				return new \WP_Error( 'takeover_required', __( 'A virtual public response already exists. Inspect it and explicitly confirm takeover before replacing it.', 'serfe-ai-discovery' ) );
			}
		}
		$effective_checksum = $has_stored ? $current['checksum'] : ( is_array( $public ) && $public['exists'] ? $public['checksum'] : '' );
		if ( ( $has_stored || '' !== $known || ( is_array( $public ) && $public['exists'] ) || '' !== (string) $expected_checksum ) && ! hash_equals( $effective_checksum, (string) $expected_checksum ) ) {
			return new \WP_Error( 'stale_file', __( 'The public file changed since this editor was opened. Review the current content before publishing.', 'serfe-ai-discovery' ) );
		}
		if ( $has_stored || ( is_array( $public ) && $public['exists'] ) ) {
			update_option( self::backup_key( $name ), $has_stored ? $stored : $public['content'], false );
		}
		update_option( self::content_key( $name ), $content, false );
		$checksum = hash( 'sha256', $content );
		update_option(
			self::meta_key( $name ),
			array(
				'checksum'     => $checksum,
				'published_at' => current_time( 'mysql' ),
				'user_id'      => get_current_user_id(),
			),
			false
		);
		if ( 'llms.txt' === $name && class_exists( __NAMESPACE__ . '\\Readiness' ) ) {
			delete_transient( Readiness::LINK_HEADERS_CACHE_KEY );
		}
		$check = self::validate_current( $name, true );
		return array(
			'checksum' => $checksum,
			'verified' => ! is_wp_error( $check ) && $check['verified'],
			'warnings' => is_wp_error( $check ) ? array( $check->get_error_message() ) : $check['warnings'],
			'url'      => home_url( '/' . $name ),
		);
	}

	/** Validate a Multisite blog's saved virtual response through its public URL. */
	private static function validate_virtual( $name, $state ) {
		$stored = get_option( self::content_key( $name ), false );
		if ( ! is_string( $stored ) ) {
			$public = self::inspect_public_response( $name );
			if ( is_wp_error( $public ) ) {
				return $public;
			}
			return array(
				'verified' => false,
				'warnings' => $public['exists'] ? array( __( 'A virtual public response exists but is not managed by this plugin.', 'serfe-ai-discovery' ) ) : array( __( 'No virtual public response was found.', 'serfe-ai-discovery' ) ),
				'virtual'  => $public['exists'],
				'content'  => $public['content'],
				'checksum' => $public['checksum'],
			);
		}
		$response = wp_safe_remote_get(
			home_url( '/' . $name ),
			array(
				'redirection'         => 0,
				'timeout'             => 5,
				'limit_response_size' => self::MAX_LIMIT + 1,
			)
		);
		$verified = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) && wp_remote_retrieve_body( $response ) === $stored;
		$warnings = $verified ? array() : array( __( 'The public response does not match the current virtual file, or it could not be reached.', 'serfe-ai-discovery' ) );
		if ( 'robots.txt' === $name ) {
			$warnings = array_merge( $warnings, self::robots_warnings( $stored ) );
		}
		$meta = $state['meta'];
		if ( isset( $meta['checksum'] ) && $meta['checksum'] === $state['checksum'] ) {
			$meta['validated_at'] = current_time( 'mysql' );
			$meta['verified']     = $verified;
			update_option( self::meta_key( $name ), $meta, false );
		}
		return array(
			'verified' => $verified,
			'warnings' => $warnings,
			'virtual'  => true,
		);
	}

	/** @return string robots.txt or llms.txt when the current request targets this site's virtual file. */
	private static function requested_virtual_file() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve encoded URI bytes for an exact path comparison; this value is never rendered.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		foreach ( array( 'robots.txt', 'llms.txt' ) as $name ) {
			$expected = (string) wp_parse_url( home_url( '/' . $name ), PHP_URL_PATH );
			if ( rtrim( $path, '/' ) === rtrim( $expected, '/' ) ) {
				return $name;
			}
		}
		return '';
	}

	/** Build only one of the two fixed public-root paths. */
	private static function path( $name ) {
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) ) {
			return new \WP_Error( 'invalid_name', __( 'Only robots.txt and llms.txt can be published.', 'serfe-ai-discovery' ) );
		}
		$root = self::resolve_public_root( ABSPATH, home_url( '/' ), site_url( '/' ) );
		return is_wp_error( $root ) ? $root : $root . DIRECTORY_SEPARATOR . $name;
	}

	/** Database keys are chosen exclusively from fixed file names. */
	private static function meta_key( $name ) {
		return 'serfe_ai_discovery_' . str_replace( '.', '_', $name ) . '_meta';
	}

	private static function backup_key( $name ) {
		return 'serfe_ai_discovery_' . str_replace( '.', '_', $name ) . '_backup';
	}

	private static function content_key( $name ) {
		return 'serfe_ai_discovery_' . str_replace( '.', '_', $name ) . '_virtual';
	}
}
