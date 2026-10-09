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
	const CACHE_KEY     = 'serfe_ai_discovery_readiness_v1';
	const CACHE_TTL     = 300;
	const SKILLS_SCHEMA = 'https://schemas.agentskills.io/discovery/0.2.0/schema.json';

	/** Register the Tools screen and its refresh action. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_serfe_ai_discovery_readiness_refresh', array( $this, 'refresh' ) );
		add_action( 'admin_head-tools_page_serfe-agent-readiness', array( $this, 'render_styles' ) );
	}

	/** Render styles kept local to the Agent Readiness screen. */
	public function render_styles() {
		?>
		<style>
			.serfe-readiness-footer {
				max-width: 1000px;
				border-top: 1px solid #dcdcde;
				margin-top: 34px;
				padding: 20px 0;
				display: flex;
				align-items: center;
				gap: 14px;
			}

			.serfe-readiness-footer[data-serfe-surface="dark"] {
				background: #123b68;
				border-color: #123b68;
				border-radius: 4px;
				padding: 20px;
			}

			.serfe-readiness-footer__link {
				display: inline-flex;
				align-items: center;
			}

			.serfe-readiness-logo {
				display: block;
				width: 149px;
				height: auto;
				max-width: 100%;
			}

			.serfe-readiness-logo--dark {
				display: none;
			}

			.serfe-readiness-footer[data-serfe-surface="dark"] .serfe-readiness-logo--light {
				display: none;
			}

			.serfe-readiness-footer[data-serfe-surface="dark"] .serfe-readiness-logo--dark {
				display: block;
			}

			.serfe-readiness-footer[data-serfe-surface="dark"] .description {
				color: #dce8f5;
			}
		</style>
		<?php
	}

	/** Add Tools > Agent Readiness. */
	public function register_page() {
		add_management_page(
			__( 'Agent Readiness', 'serfe-ai-discovery' ),
			__( 'Agent Readiness', 'serfe-ai-discovery' ),
			'manage_options',
			'serfe-agent-readiness',
			array( $this, 'render_page' )
		);
	}

	/** Remove the local result cache, then return to the panel. */
	public function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run this check.', 'serfe-ai-discovery' ) );
		}

		check_admin_referer( 'serfe_ai_discovery_readiness_refresh' );
		delete_transient( self::CACHE_KEY );
		$this->inspect( true );
		$redirect_url = add_query_arg(
			array(
				'page'      => 'serfe-agent-readiness',
				'refreshed' => '1',
				'_wpnonce'  => wp_create_nonce( 'serfe_ai_discovery_readiness_refreshed' ),
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/** Render the diagnostic panel. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = get_transient( self::CACHE_KEY );
		if ( ! is_array( $report ) || ! isset( $report['checks'], $report['checked_at'] ) ) {
			$report = array(
				'checks'     => array(),
				'checked_at' => '',
			);
		}
		$footer_surface = apply_filters( 'serfe_ai_discovery_footer_surface', 'light' );
		$footer_surface = 'dark' === $footer_surface ? 'dark' : 'light';
		$logo_blue      = SERFE_AI_DISCOVERY_URL . 'assets/serfe-logo-blue.png';
		$logo_white     = SERFE_AI_DISCOVERY_URL . 'assets/serfe-logo-white.png';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Agent Readiness', 'serfe-ai-discovery' ); ?></h1>
			<div class="card" style="max-width: 1000px; padding: 20px 24px; margin: 16px 0;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'Build trust for the agentic web.', 'serfe-ai-discovery' ); ?></h2>
				<p><?php esc_html_e( 'Agent Readiness gives your team a clear, practical view of how easily browsers, crawlers, and AI agents can discover and understand your WordPress site.', 'serfe-ai-discovery' ); ?></p>
				<p><?php esc_html_e( 'Each validated capability strengthens a reliable path to your public content, catalog, and services. Completing the applicable items improves discoverability, reduces integration friction, and creates a stronger technical foundation for future agent experiences.', 'serfe-ai-discovery' ); ?></p>
				<p style="margin-bottom: 0;"><?php esc_html_e( 'Use this dashboard to prioritize improvements without compromising intentional security boundaries: private workflows remain private, and a capability is only marked ready when a real implementation is present.', 'serfe-ai-discovery' ); ?></p>
			</div>
			<p class="description"><?php esc_html_e( 'Checks run only when you select Run checks, then the result is cached for five minutes. A warning means the local loopback request could not be completed, not that the capability is absent.', 'serfe-ai-discovery' ); ?></p>

			<?php if ( isset( $_GET['refreshed'], $_GET['_wpnonce'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['refreshed'] ) ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'serfe_ai_discovery_readiness_refreshed' ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Checks refreshed.', 'serfe-ai-discovery' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $report['checks'] ) : ?>
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

			<p class="description" style="margin-top: 14px;">
				<?php esc_html_e( '✅ validated · ❌ not configured or not available · ⚠ could not be verified locally · — not applicable to the current site.', 'serfe-ai-discovery' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="serfe_ai_discovery_readiness_refresh">
				<?php wp_nonce_field( 'serfe_ai_discovery_readiness_refresh' ); ?>
				<?php submit_button( __( 'Run checks again', 'serfe-ai-discovery' ), 'secondary', 'submit', false ); ?>
			</form>
				<?php if ( '' !== $report['checked_at'] ) : ?>
					<?php /* translators: %s: date and time of the last capability check. */ ?>
					<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s', 'serfe-ai-discovery' ), $report['checked_at'] ) ); ?></p>
			<?php endif; ?>

			<div class="serfe-readiness-footer" data-serfe-surface="<?php echo esc_attr( $footer_surface ); ?>">
				<a class="serfe-readiness-footer__link" href="https://www.serfe.com" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Visit Serfe', 'serfe-ai-discovery' ); ?>">
					<img class="serfe-readiness-logo serfe-readiness-logo--light" src="<?php echo esc_url( $logo_blue ); ?>" alt="" aria-hidden="true">
					<img class="serfe-readiness-logo serfe-readiness-logo--dark" src="<?php echo esc_url( $logo_white ); ?>" alt="" aria-hidden="true">
				</a>
				<span class="description"><?php esc_html_e( 'Your Nearshore Software Development Partner in the Americas', 'serfe-ai-discovery' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Inspect the active site. This intentionally has no remote scanner dependency.
	 *
	 * @param bool $force Ignore the transient cache.
	 * @return array<string, mixed>
	 */
	public function inspect( $force = false ) {
		$cached = $force ? false : get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['checks'], $cached['checked_at'] ) ) {
			return $cached;
		}

		$report = array(
			'checked_at' => current_time( 'mysql' ),
			'checks'     => array(
				$this->check_robots(),
				$this->check_sitemap(),
				$this->check_api_catalog(),
				$this->check_markdown(),
				$this->check_mcp(),
				$this->check_oauth(),
				$this->check_skills(),
				$this->check_commerce(),
			),
		);

		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );
		return $report;
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

	/** @return array{ok: bool, code: int, body: string, content_type: string, detail: string} */
	private function get( $url, $headers = array() ) {
		if ( ! self::is_same_origin_url( $url ) ) {
			return array(
				'ok'           => false,
				'code'         => 0,
				'body'         => '',
				'content_type' => '',
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
				'detail'       => __( 'The site could not complete its local loopback request.', 'serfe-ai-discovery' ),
			);
		}

		return array(
			'ok'           => true,
			'code'         => (int) wp_remote_retrieve_response_code( $response ),
			'body'         => (string) wp_remote_retrieve_body( $response ),
			'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			'detail'       => '',
		);
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
	 * Validate the v0.2.0 Agent Skills index shape without fetching artifacts.
	 *
	 * @param mixed $index Decoded JSON index.
	 * @return array{valid: bool, count: int, detail: string}
	 */
	public static function validate_skills_index( $index ) {
		if ( ! is_array( $index ) || ( $index['$schema'] ?? '' ) !== self::SKILLS_SCHEMA || ! isset( $index['skills'] ) || ! is_array( $index['skills'] ) ) {
			return array(
				'valid'  => false,
				'count'  => 0,
				'detail' => __( 'No valid Agent Skills v0.2.0 index was found.', 'serfe-ai-discovery' ),
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
