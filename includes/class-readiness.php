<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local, read-only checks for agent-facing capabilities.
 *
 * Integrations can declare a real MCP, protected resource or commerce service
 * through the filters documented in README.md. Nothing is inferred from a
 * plugin name alone, and the checker never calls third-party scanners.
 */
final class Readiness {
	const REPORT_OPTION          = 'serfe_ai_discovery_readiness_report';
	const CACHE_KEY              = 'serfe_ai_discovery_readiness_v1';
	const CACHE_TTL              = 300;
	const LINK_HEADERS_CACHE_KEY = 'serfe_ai_discovery_agent_links_v1';
	const LINK_HEADERS_CACHE_TTL = 300;

	/** Register the diagnostic refresh action on the plugin settings screen. */
	public function register_hooks() {
		add_action( 'admin_post_serfe_ai_discovery_readiness_refresh', array( $this, 'refresh' ) );
	}

	/** Remove the local result cache, then return to the panel. */
	public function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run this check.', 'serfe-ai-discovery' ) );
		}

		check_admin_referer( 'serfe_ai_discovery_readiness_refresh' );
		delete_transient( self::CACHE_KEY );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_admin_referer() above.
		$purge_cache = isset( $_POST['purge_cache'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['purge_cache'] ) );
		if ( $purge_cache && class_exists( __NAMESPACE__ . '\Markdown_Cache' ) ) {
			Markdown_Cache::invalidate( 'manual_refresh' );
		}
		if ( class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ) {
			Cache_Integrations::sync_rocket_config();
		}
		$this->inspect( true );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_admin_referer() above.
		$return_tab = isset( $_POST['return_tab'] ) && is_string( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'discovery';
		$return_tab = in_array( $return_tab, array( 'overview', 'discovery' ), true ) ? $return_tab : 'discovery';
		$query_args = array(
			'page'      => Settings::PAGE,
			'tab'       => $return_tab,
			'refreshed' => '1',
			'_wpnonce'  => wp_create_nonce( 'serfe_ai_discovery_readiness_refreshed' ),
		);
		if ( $purge_cache ) {
			$query_args['cache_purged'] = '1';
		}
		$redirect_url = add_query_arg( $query_args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/** Render the diagnostic panel. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = self::saved_report();
		if ( ! is_array( $report ) || ! isset( $report['checks'], $report['checked_at'] ) ) {
			$report = array(
				'checks'     => array(),
				'checked_at' => '',
			);
		}
		$has_checks    = ! empty( $report['checks'] );
		$cache_plugins = class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ? Cache_Integrations::active_cache_plugins() : array();
		?>
		<div class="serfe-readiness-panel">
			<h2><?php esc_html_e( 'Discovery Checks', 'serfe-ai-discovery' ); ?></h2>
			<div class="card" style="max-width: 1000px; padding: 20px 24px; margin: 16px 0;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Your site discovery status', 'serfe-ai-discovery' ); ?></h3>
				<?php if ( $has_checks ) : ?>
					<p><?php esc_html_e( 'The latest local results are shown below. Review any item marked unavailable or needing verification.', 'serfe-ai-discovery' ); ?></p>
					<?php /* translators: %s: date and time of the last capability check. */ ?>
					<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s', 'serfe-ai-discovery' ), self::format_checked_at( $report['checked_at'] ) ) ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'No checks have run yet. Select Run checks to inspect the public discovery resources for this site.', 'serfe-ai-discovery' ); ?></p>
				<?php endif; ?>
				<p><?php esc_html_e( 'These checks make local requests to public URLs for robots.txt, llms.txt, XML sitemaps, Link headers, Markdown, and optional agent services. They do not publish or change site content.', 'serfe-ai-discovery' ); ?></p>
				<p class="description"><?php esc_html_e( 'Each URL must be publicly reachable without authentication. A private, password-protected, or login-restricted site cannot be validated by these checks.', 'serfe-ai-discovery' ); ?></p>
				<p><?php echo esc_html( Settings::api_catalog_enabled() ? __( 'API Catalog publication is enabled.', 'serfe-ai-discovery' ) : __( 'API Catalog publication is disabled. Configure it in the API Catalog tab.', 'serfe-ai-discovery' ) ); ?></p>
				<p><?php esc_html_e( 'When valid public llms.txt and XML sitemap responses are detected, the plugin automatically publishes RFC 8288 Link headers.', 'serfe-ai-discovery' ); ?></p>
				<?php if ( ! empty( $cache_plugins ) ) : ?>
					<?php /* translators: %s: comma-separated list of active page-cache plugins. */ ?>
					<p class="description"><?php echo esc_html( sprintf( __( 'Active page cache detected: %s. If cached HTML pages were generated before updating discovery settings, purge the site cache before re-running checks.', 'serfe-ai-discovery' ), implode( ', ', $cache_plugins ) ) ); ?></p>
				<?php endif; ?>
				<details style="margin: 16px 0;"><summary><?php esc_html_e( 'About XML Sitemaps', 'serfe-ai-discovery' ); ?></summary>
					<p><?php esc_html_e( 'An XML sitemap lists canonical public URLs in a machine-readable format, helping crawlers and technical tools find the right pages.', 'serfe-ai-discovery' ); ?></p>
					<p><?php esc_html_e( 'Include live public URLs only. Exclude redirects, noindex content, duplicates, private pages, search pages, cart, checkout, and account pages.', 'serfe-ai-discovery' ); ?></p>
				</details>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="serfe_ai_discovery_readiness_refresh">
					<?php wp_nonce_field( 'serfe_ai_discovery_readiness_refresh' ); ?>
					<?php submit_button( __( 'Run checks', 'serfe-ai-discovery' ), 'primary', 'submit', false ); ?>
					<?php if ( ! empty( $cache_plugins ) ) : ?>
						<button type="submit" name="purge_cache" value="1" class="button button-secondary" style="margin-left: 8px;"><?php esc_html_e( 'Purge cache & run checks', 'serfe-ai-discovery' ); ?></button>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( isset( $_GET['refreshed'], $_GET['_wpnonce'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['refreshed'] ) ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'serfe_ai_discovery_readiness_refreshed' ) ) : ?>
				<?php $purged_notice = isset( $_GET['cache_purged'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cache_purged'] ) ); ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $purged_notice ? __( 'Site cache purged and checks refreshed.', 'serfe-ai-discovery' ) : __( 'Checks refreshed.', 'serfe-ai-discovery' ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $has_checks ) : ?>
			<table class="widefat striped" style="max-width: 1000px; margin-top: 16px;">
				<thead><tr>
					<th scope="col" style="width: 52px;"><?php esc_html_e( 'Status', 'serfe-ai-discovery' ); ?></th>
					<th scope="col" style="width: 230px;"><?php esc_html_e( 'Capability', 'serfe-ai-discovery' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current installation', 'serfe-ai-discovery' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $report['checks'] as $check ) : ?>
						<tr>
							<td aria-label="<?php echo esc_attr( $this->status_label( $check['status'] ) ); ?>"><?php echo esc_html( $this->status_icon( $check['status'] ) ); ?></td>
							<td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
							<td>
								<?php echo esc_html( $check['detail'] ); ?>
								<?php if ( ! empty( $check['url'] ) ) : ?>
									<br><a href="<?php echo esc_url( $check['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $check['url'] ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>

			<?php if ( $has_checks ) : ?>
				<p class="description" style="margin-top: 14px;">
					<?php esc_html_e( '✅ validated · ❌ not configured or not available · ⚠ could not be verified locally · — not applicable to the current site.', 'serfe-ai-discovery' ); ?>
				</p>
			<?php endif; ?>
			<?php $scanner = Discovery_Files_Admin::agent_ready_target( home_url( '/' ), wp_get_environment_type() ); ?>
			<div class="card" style="max-width: 1000px; padding: 16px 24px; margin: 16px 0;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Optional external validation', 'serfe-ai-discovery' ); ?></h3>
				<p><?php esc_html_e( 'Open Is It Agent-Ready in a new tab to check the publicly deployed site. No request is sent until you select this button.', 'serfe-ai-discovery' ); ?></p>
				<p><a class="button button-secondary" href="<?php echo esc_url( $scanner['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Check this site with Is It Agent-Ready', 'serfe-ai-discovery' ); ?></a></p>
				<?php if ( ! $scanner['public'] ) : ?>
					<p class="description"><?php esc_html_e( 'External scanners cannot access local or private environments. Open the scanner and test a public URL after deployment.', 'serfe-ai-discovery' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Inspect the active site. This intentionally has no remote scanner dependency.
	 *
	 * @param bool $force Ignore the short-lived request cache.
	 * @return array<string, mixed>
	 */
	public function inspect( $force = false ) {
		$cached = $force ? false : get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['checks'], $cached['checked_at'] ) ) {
			return $cached;
		}

		if ( class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ) {
			Cache_Integrations::sync_rocket_config();
		}

		$llms    = $this->check_llms();
		$sitemap = $this->check_sitemap();
		set_transient( self::LINK_HEADERS_CACHE_KEY, self::agent_discovery_links_from_checks( $llms, $sitemap ), self::LINK_HEADERS_CACHE_TTL );
		if ( class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ) {
			Cache_Integrations::sync_rocket_config();
		}

		$results = array(
			'robots'       => $this->check_robots(),
			'llms'         => $llms,
			'sitemap'      => $sitemap,
			'link_headers' => $this->check_agent_link_headers( $llms, $sitemap ),
			'api_catalog'  => $this->check_api_catalog(),
			'markdown'     => $this->check_markdown(),
			'mcp'          => $this->check_mcp(),
			'oauth'        => $this->check_oauth(),
			'skills'       => $this->check_skills(),
			'commerce'     => $this->check_commerce(),
		);
		// A stable id lets other screens match a check without depending on its translated label.
		foreach ( $results as $id => $result ) {
			$results[ $id ]['id'] = $id;
		}
		$report = array(
			'checked_at' => current_time( 'mysql' ),
			'checks'     => array_values( $results ),
		);

		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );
		update_option( self::REPORT_OPTION, $report, false );
		return $report;
	}

	/** Return the persisted report from the most recent administrator-run checks. */
	public static function saved_report() {
		$report = get_option( self::REPORT_OPTION, array() );
		return is_array( $report ) && isset( $report['checks'], $report['checked_at'] ) ? $report : array();
	}

	/** Format a stored check timestamp using the site's configured date and time formats. */
	public static function format_checked_at( $checked_at ) {
		if ( ! is_string( $checked_at ) || '' === $checked_at ) {
			return '';
		}
		$timezone = wp_timezone();
		$date     = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $checked_at, $timezone );
		if ( false === $date ) {
			return $checked_at;
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date->getTimestamp(), $timezone );
	}

	/** Return cached or newly verified Link header resources for public requests. */
	public function agent_discovery_links() {
		$cached = get_transient( self::LINK_HEADERS_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$links = self::agent_discovery_links_from_checks( $this->check_llms(), $this->check_sitemap() );
		set_transient( self::LINK_HEADERS_CACHE_KEY, $links, self::LINK_HEADERS_CACHE_TTL );
		if ( class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ) {
			Cache_Integrations::sync_rocket_config();
		}
		return $links;
	}

	/**
	 * Build RFC 8288 Link header values from verified resource checks.
	 *
	 * @param array<string, string> $llms    llms.txt check.
	 * @param array<string, string> $sitemap XML sitemap check.
	 * @return array<int, array<string, string>>
	 */
	public static function agent_discovery_links_from_checks( $llms, $sitemap ) {
		$links = array();
		if ( is_array( $llms ) && 'pass' === ( isset( $llms['status'] ) ? $llms['status'] : '' ) && ! empty( $llms['url'] ) ) {
			$links[] = array(
				'url'  => (string) $llms['url'],
				'rel'  => 'describedby',
				'type' => 'text/plain',
			);
		}
		if ( is_array( $sitemap ) && 'pass' === ( isset( $sitemap['status'] ) ? $sitemap['status'] : '' ) && ! empty( $sitemap['url'] ) ) {
			$links[] = array(
				'url'  => (string) $sitemap['url'],
				'rel'  => 'related',
				'type' => 'application/xml',
			);
		}
		return $links;
	}

	/** @return array<string, string> */
	private function check_robots() {
		$url      = home_url( '/robots.txt' );
		$response = $this->get( $url );
		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', __( 'robots.txt', 'serfe-ai-discovery' ), $response['detail'], $url );
		}

		if ( 200 === $response['code'] && preg_match( '/^\s*user-agent\s*:/im', $response['body'] ) ) {
			return $this->check( 'pass', __( 'robots.txt', 'serfe-ai-discovery' ), __( 'A robots.txt response is available.', 'serfe-ai-discovery' ), $url );
		}

		return $this->check( 'fail', __( 'robots.txt', 'serfe-ai-discovery' ), __( 'No valid robots.txt response was found.', 'serfe-ai-discovery' ), $url );
	}

	/** @return array<string, string> */
	private function check_sitemap() {
		$urls          = array( home_url( '/wp-sitemap.xml' ), home_url( '/sitemap_index.xml' ), home_url( '/sitemap.xml' ) );
		$loopback_down = true;

		foreach ( array_unique( $urls ) as $url ) {
			$response = $this->get( $url );
			if ( ! $response['ok'] ) {
				continue;
			}
			$loopback_down = false;
			if ( 200 === $response['code'] && preg_match( '/<(?:\w+:)?(?:urlset|sitemapindex)\b/i', $response['body'] ) ) {
				return $this->check( 'pass', __( 'XML sitemap', 'serfe-ai-discovery' ), __( 'A valid XML sitemap was found.', 'serfe-ai-discovery' ), $url );
			}
		}

		if ( $loopback_down ) {
			return $this->check( 'unknown', __( 'XML sitemap', 'serfe-ai-discovery' ), __( 'The local sitemap URLs could not be requested.', 'serfe-ai-discovery' ) );
		}

		return $this->check( 'fail', __( 'XML sitemap', 'serfe-ai-discovery' ), __( 'No XML sitemap was found at the WordPress or common SEO-plugin URLs.', 'serfe-ai-discovery' ) );
	}

	/** @return array<string, string> */
	private function check_llms() {
		$url      = home_url( '/llms.txt' );
		$response = $this->get( $url );
		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', __( 'llms.txt', 'serfe-ai-discovery' ), $response['detail'], $url );
		}
		if ( 200 === $response['code'] && 0 === stripos( $response['content_type'], 'text/plain' ) && '' !== trim( $response['body'] ) ) {
			return $this->check( 'pass', __( 'llms.txt', 'serfe-ai-discovery' ), __( 'A non-empty plain-text llms.txt response is available.', 'serfe-ai-discovery' ), $url );
		}
		return $this->check( 'fail', __( 'llms.txt', 'serfe-ai-discovery' ), __( 'No valid llms.txt response was found.', 'serfe-ai-discovery' ), $url );
	}

	/** @param array<string, string> $llms @param array<string, string> $sitemap @return array<string, string> */
	private function check_agent_link_headers( $llms, $sitemap ) {
		$links = self::agent_discovery_links_from_checks( $llms, $sitemap );
		if ( ! empty( $links ) ) {
			$home_url      = home_url( '/' );
			$home_response = $this->get( $home_url );
			if ( $home_response['ok'] && 200 === $home_response['code'] ) {
				foreach ( $links as $link ) {
					if ( false === stripos( $home_response['link_header'], 'rel="' . $link['rel'] . '"' ) ) {
						return $this->check(
							'unknown',
							__( 'Agent discovery Link headers', 'serfe-ai-discovery' ),
							self::cache_clear_notice( __( 'The discovery files are valid, but the homepage response did not include the expected HTTP Link headers.', 'serfe-ai-discovery' ) ),
							$home_url
						);
					}
				}
			}
		}

		if ( 2 === count( $links ) ) {
			return $this->check( 'pass', __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), __( 'Link headers advertise llms.txt and the XML sitemap.', 'serfe-ai-discovery' ) );
		}
		if ( $links ) {
			return $this->check( 'unknown', __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), __( 'Link headers advertise the detected resource. Create the missing llms.txt or XML sitemap to advertise both.', 'serfe-ai-discovery' ) );
		}
		if ( 'unknown' === $llms['status'] || 'unknown' === $sitemap['status'] ) {
			return $this->check( 'unknown', __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), __( 'The local resources could not be verified. Create llms.txt and an XML sitemap, then run the checks again.', 'serfe-ai-discovery' ) );
		}
		return $this->check( 'fail', __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), __( 'Create a public llms.txt file and XML sitemap to publish these Link headers.', 'serfe-ai-discovery' ) );
	}

	/** @return array<string, string> */
	private function check_api_catalog() {
		$url      = home_url( Discovery::API_CATALOG_PATH );
		$response = $this->get( $url );
		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', __( 'API catalog', 'serfe-ai-discovery' ), $response['detail'], $url );
		}

		$catalog = json_decode( $response['body'], true );
		if ( 200 === $response['code']
			&& stripos( $response['content_type'], 'application/linkset+json' ) === 0
			&& self::valid_api_catalog( $catalog ) ) {
			return $this->check( 'pass', __( 'API catalog', 'serfe-ai-discovery' ), __( 'A valid RFC 9727 API catalog is available.', 'serfe-ai-discovery' ), $url );
		}

		return $this->check( 'fail', __( 'API catalog', 'serfe-ai-discovery' ), __( 'No valid RFC 9727 API catalog was found.', 'serfe-ai-discovery' ), $url );
	}

	/** @return array<string, string> */
	private function check_markdown() {
		if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) || ! class_exists( __NAMESPACE__ . '\\Request_Router' ) ) {
			return $this->check( 'fail', __( 'Markdown negotiation', 'serfe-ai-discovery' ), __( 'Serfe AI Discovery is not active.', 'serfe-ai-discovery' ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( empty( $posts ) ) {
			return $this->check( 'na', __( 'Markdown negotiation', 'serfe-ai-discovery' ), __( 'The plugin is active, but there is no published page or post to probe.', 'serfe-ai-discovery' ) );
		}

		$posts_page_id = (int) get_option( 'page_for_posts' );
		$excluded_ids  = array( $posts_page_id );
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'shop' ) as $page_name ) {
				$excluded_ids[] = (int) wc_get_page_id( $page_name );
			}
		}
		$first_url     = '';
		$loopback_down = true;
		foreach ( $posts as $post ) {
			if ( in_array( (int) $post->ID, $excluded_ids, true ) ) {
				continue;
			}

			$url = get_permalink( $post );
			if ( '' === $first_url ) {
				$first_url = $url;
			}
			$response = $this->get( $url, array( 'Accept' => 'text/markdown' ) );
			if ( ! $response['ok'] ) {
				continue;
			}

			$loopback_down = false;
			if ( 200 === $response['code'] && stripos( $response['content_type'], 'text/markdown' ) === 0 ) {
				return $this->check( 'pass', __( 'Markdown negotiation', 'serfe-ai-discovery' ), __( 'A public page returned text/markdown for Accept: text/markdown.', 'serfe-ai-discovery' ), $url );
			}
		}

		if ( $loopback_down ) {
			return $this->check( 'unknown', __( 'Markdown negotiation', 'serfe-ai-discovery' ), __( 'The site could not complete its local Markdown probe.', 'serfe-ai-discovery' ), $first_url );
		}

		if ( '' !== $first_url && class_exists( __NAMESPACE__ . '\Discovery' ) ) {
			$md_response = $this->get( Discovery::markdown_url( $first_url ) );
			if ( $md_response['ok'] && 200 === $md_response['code'] && 0 === stripos( $md_response['content_type'], 'text/markdown' ) ) {
				return $this->check(
					'fail',
					__( 'Markdown negotiation', 'serfe-ai-discovery' ),
					self::cache_clear_notice( __( 'Direct .md URLs work, but Accept: text/markdown returned cached HTML instead of text/markdown.', 'serfe-ai-discovery' ) ),
					$first_url
				);
			}
		}

		$cache_plugins = class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ? Cache_Integrations::active_cache_plugins() : array();
		if ( ! empty( $cache_plugins ) ) {
			return $this->check(
				'fail',
				__( 'Markdown negotiation', 'serfe-ai-discovery' ),
				self::cache_clear_notice( __( 'The public probe did not return Content-Type: text/markdown.', 'serfe-ai-discovery' ) ),
				$first_url
			);
		}

		return $this->check( 'fail', __( 'Markdown negotiation', 'serfe-ai-discovery' ), __( 'The public probe did not return Content-Type: text/markdown.', 'serfe-ai-discovery' ), $first_url );
	}

	/** @return array<string, string> */
	private function check_mcp() {
		$provider = apply_filters( 'serfe_agent_readiness_mcp', null );
		return $this->check_provider( $provider, __( 'MCP server', 'serfe-ai-discovery' ), __( 'No MCP server is registered with this site.', 'serfe-ai-discovery' ) );
	}

	/** @return array<string, string> */
	private function check_oauth() {
		$provider = apply_filters( 'serfe_agent_readiness_oauth_protected_resource', null );
		$url      = is_array( $provider ) && ! empty( $provider['metadata_url'] ) ? $provider['metadata_url'] : home_url( '/.well-known/oauth-protected-resource' );
		$response = $this->get( $url );

		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', __( 'OAuth protected resource', 'serfe-ai-discovery' ), $response['detail'], $url );
		}

		if ( 404 === $response['code'] && ! is_array( $provider ) ) {
			return $this->check( 'na', __( 'OAuth protected resource', 'serfe-ai-discovery' ), __( 'No protected agent resource is declared. Public content does not need OAuth.', 'serfe-ai-discovery' ) );
		}

		$metadata = json_decode( $response['body'], true );
		if ( 200 === $response['code'] && self::valid_oauth_metadata( $metadata ) ) {
			return $this->check( 'pass', __( 'OAuth protected resource', 'serfe-ai-discovery' ), __( 'Protected Resource Metadata has a resource and authorization server.', 'serfe-ai-discovery' ), $url );
		}

		return $this->check( 'fail', __( 'OAuth protected resource', 'serfe-ai-discovery' ), __( 'Protected Resource Metadata is missing or invalid.', 'serfe-ai-discovery' ), $url );
	}

	/** @return array<string, string> */
	private function check_skills() {
		$url      = home_url( '/.well-known/agent-skills/index.json' );
		$response = $this->get( $url );
		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', __( 'Agent Skills', 'serfe-ai-discovery' ), $response['detail'], $url );
		}

		$validation = self::validate_skills_index( json_decode( $response['body'], true ) );
		if ( 200 === $response['code'] && $validation['valid'] ) {
			/* translators: %d: number of skills listed in the validated index. */
			return $this->check( 'pass', __( 'Agent Skills', 'serfe-ai-discovery' ), sprintf( __( 'A valid Agent Skills index lists %d skill(s).', 'serfe-ai-discovery' ), $validation['count'] ), $url );
		}

		return $this->check( 'fail', __( 'Agent Skills', 'serfe-ai-discovery' ), $validation['detail'], $url );
	}

	/** @return array<string, string> */
	private function check_commerce() {
		$provider = apply_filters( 'serfe_agent_readiness_agentic_commerce', null );
		if ( ! is_array( $provider ) && ! class_exists( 'WooCommerce' ) && ! defined( 'WC_VERSION' ) ) {
			return $this->check( 'na', __( 'Agentic Commerce', 'serfe-ai-discovery' ), __( 'WooCommerce is not active and no commerce integration is registered.', 'serfe-ai-discovery' ) );
		}

		return $this->check_provider( $provider, __( 'Agentic Commerce', 'serfe-ai-discovery' ), __( 'No agentic-commerce protocol integration is registered.', 'serfe-ai-discovery' ) );
	}

	/**
	 * Verify an explicitly registered service without making protocol calls.
	 * Providers may optionally expose a read-only health URL for this panel.
	 *
	 * @param mixed  $provider Provider configuration.
	 * @param string $label Check label.
	 * @param string $missing Detail when no provider is present.
	 * @return array<string, string>
	 */
	private function check_provider( $provider, $label, $missing ) {
		if ( ! is_array( $provider ) || empty( $provider['endpoint'] ) ) {
			return $this->check( 'fail', $label, $missing );
		}

		$endpoint = esc_url_raw( $provider['endpoint'] );
		if ( empty( $provider['health_url'] ) ) {
			return $this->check( 'unknown', $label, __( 'A service is registered, but it has no read-only health URL to validate.', 'serfe-ai-discovery' ), $endpoint );
		}

		$response = $this->get( $provider['health_url'] );
		if ( ! $response['ok'] ) {
			return $this->check( 'unknown', $label, $response['detail'], $endpoint );
		}

		if ( $response['code'] >= 200 && $response['code'] < 300 ) {
			return $this->check( 'pass', $label, __( 'A registered service responded successfully to its health check.', 'serfe-ai-discovery' ), $endpoint );
		}

		return $this->check( 'fail', $label, __( 'The registered service did not pass its health check.', 'serfe-ai-discovery' ), $endpoint );
	}

	/** @return array{ok: bool, code: int, body: string, content_type: string, link_header: string, detail: string} */
	private function get( $url, $headers = array() ) {
		if ( ! self::is_same_origin_url( $url ) ) {
			return array(
				'ok'           => false,
				'code'         => 0,
				'body'         => '',
				'content_type' => '',
				'link_header'  => '',
				'detail'       => __( 'Only this site can be checked.', 'serfe-ai-discovery' ),
			);
		}
		$request_url = esc_url_raw( $url );
		$args        = array(
			'timeout'             => 5,
			'redirection'         => 0,
			'limit_response_size' => 524288,
			'headers'             => $headers,
		);
		$response    = wp_safe_remote_get( $request_url, $args );

		/*
		 * Docker development hosts such as WordPress.localhost resolve on the
		 * browser host but not necessarily in the WordPress container. Retry only
		 * these local hostnames through Apache's loopback address, preserving Host.
		 */
		if ( is_wp_error( $response ) && self::is_local_hostname( $request_url ) ) {
			$parts                    = wp_parse_url( $request_url );
			$host                     = $parts['host'];
			$host_header              = isset( $parts['port'] ) ? $host . ':' . $parts['port'] : $host;
			$path                     = isset( $parts['path'] ) ? $parts['path'] : '/';
			$path                    .= isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$loopback_headers         = $headers;
			$loopback_headers['Host'] = $host_header;
			$args['headers']          = $loopback_headers;
			$response                 = wp_remote_get( 'http://127.0.0.1' . $path, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'           => false,
				'code'         => 0,
				'body'         => '',
				'content_type' => '',
				'link_header'  => '',
				'detail'       => __( 'The site could not complete its local loopback request.', 'serfe-ai-discovery' ),
			);
		}
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $response_code, array( 401, 403 ), true ) ) {
			return array(
				'ok'           => false,
				'code'         => $response_code,
				'body'         => '',
				'content_type' => '',
				'link_header'  => '',
				'detail'       => __( 'The URL requires authentication or is not publicly accessible.', 'serfe-ai-discovery' ),
			);
		}

		$raw_link    = wp_remote_retrieve_header( $response, 'link' );
		$link_header = is_array( $raw_link ) ? implode( ', ', array_map( 'strval', $raw_link ) ) : (string) $raw_link;

		return array(
			'ok'           => true,
			'code'         => $response_code,
			'body'         => (string) wp_remote_retrieve_body( $response ),
			'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			'link_header'  => $link_header,
			'detail'       => '',
		);
	}

	/**
	 * Append an actionable cache-clearing instruction naming active cache plugins when detected.
	 *
	 * @param string $prefix Primary diagnostic explanation.
	 * @return string
	 */
	public static function cache_clear_notice( $prefix ) {
		$plugins = class_exists( __NAMESPACE__ . '\Cache_Integrations' ) ? Cache_Integrations::active_cache_plugins() : array();
		if ( ! empty( $plugins ) ) {
			/* translators: 1: diagnostic reason, 2: comma-separated list of active cache plugins. */
			return sprintf( __( '%1$s Clear your site cache (%2$s) and run the checks again.', 'serfe-ai-discovery' ), $prefix, implode( ', ', $plugins ) );
		}

		/* translators: %s: diagnostic reason. */
		return sprintf( __( '%s Clear your site or server page cache and run the checks again.', 'serfe-ai-discovery' ), $prefix );
	}

	/** @param string $url Candidate URL. @return bool */
	public static function is_same_origin_url( $url ) {
		$home  = wp_parse_url( home_url( '/' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $parts )
			|| ! isset( $home['scheme'], $home['host'], $parts['scheme'], $parts['host'] )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		$home_scheme = strtolower( $home['scheme'] );
		$scheme      = strtolower( $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true )
			|| $scheme !== $home_scheme
			|| strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) {
			return false;
		}
		$default_port = 'https' === $scheme ? 443 : 80;
		return (int) ( isset( $parts['port'] ) ? $parts['port'] : $default_port )
			=== (int) ( isset( $home['port'] ) ? $home['port'] : $default_port );
	}

	/** @return bool */
	private static function is_local_hostname( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return 'localhost' === $host || substr( $host, -10 ) === '.localhost' || substr( $host, -5 ) === '.test';
	}

	/** @return array<string, string> */
	private function check( $status, $label, $detail, $url = '' ) {
		return array(
			'status' => $status,
			'label'  => $label,
			'detail' => $detail,
			'url'    => $url,
		);
	}

	/** @return string */
	private function status_icon( $status ) {
		$icons = array(
			'pass'    => '✅',
			'fail'    => '❌',
			'unknown' => '⚠',
			'na'      => '—',
		);
		return isset( $icons[ $status ] ) ? $icons[ $status ] : '⚠';
	}

	/** @return string */
	private function status_label( $status ) {
		$labels = array(
			'pass'    => __( 'Validated', 'serfe-ai-discovery' ),
			'fail'    => __( 'Not available', 'serfe-ai-discovery' ),
			'unknown' => __( 'Could not verify', 'serfe-ai-discovery' ),
			'na'      => __( 'Not applicable', 'serfe-ai-discovery' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['unknown'];
	}

	/**
	 * Validate the subset of RFC 9727 used by this plugin's API catalog.
	 *
	 * @param mixed $catalog Decoded JSON catalog.
	 * @return bool
	 */
	public static function valid_api_catalog( $catalog ) {
		if ( ! is_array( $catalog ) || empty( $catalog['linkset'] ) || ! is_array( $catalog['linkset'] ) ) {
			return false;
		}

		foreach ( $catalog['linkset'] as $linkset ) {
			if ( ! is_array( $linkset ) || empty( $linkset['anchor'] ) || empty( $linkset['item'] ) || ! is_array( $linkset['item'] ) ) {
				continue;
			}

			foreach ( $linkset['item'] as $item ) {
				if ( is_array( $item ) && ! empty( $item['href'] ) && ! empty( $item['type'] ) ) {
					return true;
				}
			}
		}

		return false;
	}


	/** @return bool */
	public static function valid_oauth_metadata( $metadata ) {
		if ( ! is_array( $metadata ) || empty( $metadata['resource'] ) || empty( $metadata['authorization_servers'] ) || ! is_array( $metadata['authorization_servers'] ) ) {
			return false;
		}

		foreach ( $metadata['authorization_servers'] as $server ) {
			if ( ! is_string( $server ) || strpos( $server, 'https://' ) !== 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate the Agent Skills index shape without fetching schemas or artifacts.
	 *
	 * @param mixed $index Decoded JSON index.
	 * @return array{valid: bool, count: int, detail: string}
	 */
	public static function validate_skills_index( $index ) {
		if ( ! is_array( $index ) || ! isset( $index['skills'] ) || ! is_array( $index['skills'] ) ) {
			return array(
				'valid'  => false,
				'count'  => 0,
				'detail' => __( 'No valid Agent Skills index was found.', 'serfe-ai-discovery' ),
			);
		}

		foreach ( $index['skills'] as $skill ) {
			if ( ! is_array( $skill )
				|| empty( $skill['name'] )
				|| ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $skill['name'] )
				|| empty( $skill['description'] )
				|| empty( $skill['url'] )
				|| ! isset( $skill['type'] )
				|| ! in_array( $skill['type'], array( 'skill-md', 'archive' ), true )
				|| empty( $skill['digest'] )
				|| ! preg_match( '/^sha256:[a-f0-9]{64}$/', $skill['digest'] ) ) {
				return array(
					'valid'  => false,
					'count'  => 0,
					'detail' => __( 'The Agent Skills index has an invalid skill entry.', 'serfe-ai-discovery' ),
				);
			}
		}

		return array(
			'valid'  => true,
			'count'  => count( $index['skills'] ),
			'detail' => '',
		);
	}
}
