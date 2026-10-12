<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrator choices for public discovery features. */
final class Settings {
	const OPTION         = 'serfe_ai_discovery_settings';
	const GROUP          = 'serfe_ai_discovery';
	const PAGE           = 'serfe-ai-discovery';
	const SCHEMA_VERSION = 3;

	/** Register the settings screen and its actions. */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'migrate_legacy_option' ), 5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_serfe_ai_discovery_markdown_cache', array( $this, 'regenerate_markdown_cache' ) );
		add_action( 'admin_post_serfe_ai_discovery_api_catalog', array( $this, 'update_api_catalog' ) );
	}

	/** Discard every generated Markdown document after an explicit administrator action. */
	public function regenerate_markdown_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the Markdown cache.', 'serfe-ai-discovery' ) );
		}
		check_admin_referer( 'serfe_ai_discovery_markdown_cache' );
		Markdown_Cache::invalidate( 'manual' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE,
					'tab'         => 'markdown',
					'regenerated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Enable or disable API Catalog publication from its dedicated settings tab. */
	public function update_api_catalog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the API Catalog.', 'serfe-ai-discovery' ) );
		}
		check_admin_referer( 'serfe_ai_discovery_api_catalog' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_admin_referer() above.
		$action                         = isset( $_POST['serfe_ai_discovery_api_catalog_action'] ) ? sanitize_key( wp_unslash( $_POST['serfe_ai_discovery_api_catalog_action'] ) ) : '';
		$choices                        = self::choices();
		$choices['api_catalog_enabled'] = 'enable' === $action ? 1 : 0;
		update_option( self::OPTION, $choices );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE,
					'tab'             => 'api-catalog',
					'catalog-updated' => $choices['api_catalog_enabled'] ? 'enabled' : 'disabled',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** @return array<string, mixed> */
	public static function choices() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		if ( ! isset( $stored['schema_version'] ) || (int) $stored['schema_version'] < self::SCHEMA_VERSION ) {
			$stored = self::migrate_legacy( $stored );
		}
		$choices = wp_parse_args( $stored, self::defaults() );
		foreach ( array( 'blocked_post_types', 'blocked_paths' ) as $key ) {
			$choices[ $key ] = is_array( $choices[ $key ] ) ? $choices[ $key ] : array();
		}
		return $choices;
	}

	/** Persist the safe migration when an administrator next loads WordPress. */
	public function migrate_legacy_option() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stored = get_option( self::OPTION, false );
		if ( is_array( $stored ) && ( ! isset( $stored['schema_version'] ) || (int) $stored['schema_version'] < self::SCHEMA_VERSION ) ) {
			update_option( self::OPTION, self::migrate_legacy( $stored ) );
		}
	}

	/** @param array<string, mixed> $stored Previous option. @return array<string, mixed> */
	private static function migrate_legacy( $stored ) {
		$settings = new self();
		return $settings->sanitize(
			array(
				'markdown_enabled'    => isset( $stored['markdown_enabled'] ) ? $stored['markdown_enabled'] : 0,
				'blocked_post_types'  => isset( $stored['blocked_post_types'] ) ? $stored['blocked_post_types'] : array(),
				'blocked_paths'       => isset( $stored['blocked_paths'] ) ? implode( "\n", (array) $stored['blocked_paths'] ) : '',
				'api_catalog_enabled' => isset( $stored['api_catalog_enabled'] ) ? $stored['api_catalog_enabled'] : 0,
			)
		);
	}

	/** @return array<string, mixed> */
	private static function defaults() {
		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'markdown_enabled'    => 0,
			'blocked_post_types'  => array(),
			'blocked_paths'       => array(),
			'api_catalog_enabled' => 0,
		);
	}

	/** @param string $post_type Post type. @return bool */
	public static function markdown_enabled_for( $post_type ) {
		$choices = self::choices();
		if ( 1 !== (int) $choices['markdown_enabled'] || ! in_array( $post_type, self::supported_post_types(), true ) ) {
			return false;
		}
		if ( in_array( $post_type, $choices['blocked_post_types'], true ) ) {
			return false;
		}
		return true;
	}

	/** @param string $archive Archive post type or supported product taxonomy. @return bool */
	public static function archive_enabled_for( $archive ) {
		if ( 'post' === $archive ) {
			return self::markdown_enabled_for( 'post' );
		}
		if ( ! in_array( $archive, self::supported_archive_keys(), true ) ) {
			return false;
		}
		$type = in_array( $archive, array( 'product_cat', 'product_tag' ), true ) ? 'product' : $archive;
		return self::markdown_enabled_for( $type );
	}

	/** @return array<int, string> Public types supported for individual Markdown pages. */
	public static function supported_post_types() {
		$types = array( 'post', 'page' );
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}
		foreach ( get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'names'
		) as $type ) {
			if ( ! in_array( $type, array( 'attachment', 'post', 'page', 'product' ), true ) ) {
				$types[] = $type;
			}
		}
		return array_values( array_unique( $types ) );
	}

	/** @return array<int, string> Public archive keys available for opt-in. */
	public static function supported_archive_keys() {
		$archives = array();
		foreach ( self::supported_post_types() as $type ) {
			$object = get_post_type_object( $type );
			if ( $object && ! empty( $object->public ) && ! empty( $object->publicly_queryable ) && ! empty( $object->has_archive ) ) {
				$archives[] = $type;
			}
		}
		if ( post_type_exists( 'product' ) ) {
			foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
				$object = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
				if ( $object && ! empty( $object->public ) && ! empty( $object->publicly_queryable ) ) {
					$archives[] = $taxonomy;
				}
			}
		}
		return $archives;
	}

	/** @return bool */
	public static function api_catalog_enabled() {
		$choices = self::choices();
		return 1 === (int) $choices['api_catalog_enabled'];
	}

	/** Normalize a site-relative path or same-origin URL for exact matching. */
	public static function normalize_blocked_path( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value || preg_match( '/[\x00-\x1F\x7F]/', $value ) || 0 === strpos( $value, '//' ) ) {
			return '';
		}
		if ( 0 === strpos( $value, '/' ) ) {
			$path = $value;
		} else {
			$url  = wp_parse_url( $value );
			$home = wp_parse_url( home_url( '/' ) );
			if ( ! is_array( $url ) || ! is_array( $home ) || empty( $url['scheme'] ) || ! in_array( strtolower( $url['scheme'] ), array( 'http', 'https' ), true )
				|| empty( $url['host'] ) || empty( $home['host'] ) || strtolower( $url['host'] ) !== strtolower( $home['host'] )
				|| ( isset( $url['port'] ) ? (int) $url['port'] : null ) !== ( isset( $home['port'] ) ? (int) $home['port'] : null )
				|| isset( $url['user'] ) || isset( $url['pass'] ) || isset( $url['query'] ) || isset( $url['fragment'] ) ) {
				return '';
			}
			$path = isset( $url['path'] ) ? $url['path'] : '/';
		}
		if ( '' === $path || '/' !== $path[0] || false !== strpbrk( $path, '?#\\' ) || preg_match( '/[\x00-\x1F\x7F]/', rawurldecode( $path ) ) ) {
			return '';
		}
		$decoded = rawurldecode( $path );
		if ( false !== strpos( $decoded, '\\' ) || false !== strpos( $decoded, '?' ) || false !== strpos( $decoded, '#' )
			|| preg_match( '~(?:^|/)\.{1,2}(?:/|$)~', $decoded ) ) {
			return '';
		}
		return '/' === $path ? '/' : rtrim( $path, '/' );
	}

	/** @param mixed $input Submitted setting. @return array<string, mixed> */
	public function sanitize( $input ) {
		$input         = is_array( $input ) ? $input : array();
		$current       = get_option( self::OPTION, array() );
		$current       = is_array( $current ) ? $current : array();
		$blocked_types = isset( $input['blocked_post_types'] ) && is_array( $input['blocked_post_types'] ) ? $input['blocked_post_types'] : array();

		$supported             = self::supported_post_types();
		$allowed_blocked_types = array();
		foreach ( $blocked_types as $type ) {
			if ( is_string( $type ) && in_array( $type, $supported, true ) ) {
				$allowed_blocked_types[] = $type;
			}
		}
		$blocked_paths = array();
		$path_lines    = isset( $input['blocked_paths'] ) && is_string( $input['blocked_paths'] ) ? preg_split( '/\r\n|\r|\n/', $input['blocked_paths'] ) : array();
		foreach ( array_slice( $path_lines, 0, 100 ) as $line ) {
			$path = self::normalize_blocked_path( $line );
			if ( '' !== $path ) {
				$blocked_paths[] = $path;
			}
		}
		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'markdown_enabled'    => isset( $input['markdown_enabled'] ) && in_array( $input['markdown_enabled'], array( '1', 1 ), true ) ? 1 : 0,
			'blocked_post_types'  => array_values( array_unique( $allowed_blocked_types ) ),
			'blocked_paths'       => array_values( array_unique( $blocked_paths ) ),
			'api_catalog_enabled' => isset( $input['api_catalog_enabled'] )
				? ( in_array( $input['api_catalog_enabled'], array( '1', 1 ), true ) ? 1 : 0 )
				: ( isset( $current['api_catalog_enabled'] ) ? (int) $current['api_catalog_enabled'] : 0 ),
		);
	}

	/** Register all submitted fields through the Settings API. */
	public function register_settings() {
		register_setting( self::GROUP, self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
		add_settings_section( 'serfe_ai_discovery_publication', __( 'General publication settings', 'serfe-ai-discovery' ), array( $this, 'render_section' ), self::PAGE );
		add_settings_field( 'markdown_enabled', __( 'Markdown negotiation', 'serfe-ai-discovery' ), array( $this, 'render_markdown_field' ), self::PAGE, 'serfe_ai_discovery_publication' );

		add_settings_section( 'serfe_ai_discovery_advanced', __( 'Advanced Settings', 'serfe-ai-discovery' ), array( $this, 'render_advanced_section' ), self::PAGE );
		add_settings_field( 'blocked_post_types', __( 'Excluded content types', 'serfe-ai-discovery' ), array( $this, 'render_blocked_types_field' ), self::PAGE, 'serfe_ai_discovery_advanced' );
		add_settings_field( 'blocked_paths', __( 'Excluded URL paths', 'serfe-ai-discovery' ), array( $this, 'render_blocked_paths_field' ), self::PAGE, 'serfe_ai_discovery_advanced' );
	}

	/** Add Serfe WordPress AI Discovery as a top-level admin menu. */
	public function register_page() {
		// WordPress centers SVG data URIs as menu backgrounds instead of rendering an img element.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read-only bundled SVG asset.
		$svg = file_get_contents( SERFE_AI_DISCOVERY_PATH . 'assets/wp_serfe_logo_gray.svg' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress requires an SVG data URI for centered menu icons.
		$icon_url = false !== $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-admin-generic';
		add_menu_page( __( 'Serfe WordPress AI Discovery', 'serfe-ai-discovery' ), __( 'WP AI Discovery', 'serfe-ai-discovery' ), 'manage_options', self::PAGE, array( $this, 'render_page' ), $icon_url );
	}

	/** Explain the scope of the current switches. */
	public function render_section() {
		echo '<p>' . esc_html__( 'Configure public discovery features and Markdown representation options.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Explain advanced filtering options within a collapsible details wrapper. */
	public function render_advanced_section() {
		$choices    = self::choices();
		$has_custom = ! empty( $choices['blocked_post_types'] ) || ! empty( $choices['blocked_paths'] );
		$open_attr  = $has_custom ? 'open' : '';
		printf(
			'<details %s style="margin-top: 24px; padding: 12px 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;"><summary style="font-size: 1.1em; font-weight: 600; cursor: pointer; color: #1d2327;">%s</summary>',
			esc_attr( $open_attr ),
			esc_html__( 'Advanced Settings', 'serfe-ai-discovery' )
		);
		echo '<p style="margin-top: 8px; color: #646970;">' . esc_html__( 'Exclude public content types or exact URL paths. Markdown otherwise applies to all supported public content.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the Markdown master switch. */
	public function render_markdown_field() {
		$choices = self::choices();
		printf(
			'<label><input type="checkbox" name="%s[markdown_enabled]" value="1" %s> <strong>%s</strong></label><p class="description">%s</p>',
			esc_attr( self::OPTION ),
			checked( 1, $choices['markdown_enabled'], false ),
			esc_html__( 'Enable Markdown content negotiation (Full support)', 'serfe-ai-discovery' ),
			esc_html__( 'Enables Markdown negotiation for all public published content (Posts, Pages, Products, Custom Post Types) and public archives.', 'serfe-ai-discovery' )
		);
	}

	/** Render content types that must never have Markdown. */
	public function render_blocked_types_field() {
		$choices = self::choices();
		foreach ( self::supported_post_types() as $type ) {
			$object = get_post_type_object( $type );
			$label  = $object && isset( $object->labels->name ) ? $object->labels->name : $type;
			printf( '<label><input type="checkbox" name="%s[blocked_post_types][]" value="%s" %s> %s</label><br>', esc_attr( self::OPTION ), esc_attr( $type ), checked( in_array( $type, $choices['blocked_post_types'], true ), true, false ), esc_html( $label ) );
		}
	}

	/** Render exact paths to exclude, one per line. */
	public function render_blocked_paths_field() {
		$choices = self::choices();
		printf( '<textarea name="%s[blocked_paths]" rows="5" cols="55">%s</textarea>', esc_attr( self::OPTION ), esc_textarea( implode( "\n", $choices['blocked_paths'] ) ) );
		echo '<p class="description">' . esc_html__( 'One exact site path per line, such as /private-page/. Same-site full URLs are also accepted.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the settings form. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array(
			'overview'    => __( 'Overview', 'serfe-ai-discovery' ),
			'markdown'    => __( 'Markdown', 'serfe-ai-discovery' ),
			'api-catalog' => __( 'API Catalog', 'serfe-ai-discovery' ),
			'robots'      => __( 'Robots.txt', 'serfe-ai-discovery' ),
			'llms'        => __( 'AI Files (llms.txt)', 'serfe-ai-discovery' ),
			'discovery'   => __( 'Discovery Checks', 'serfe-ai-discovery' ),
			'help'        => __( 'Help & Support', 'serfe-ai-discovery' ),
		);
		// The tab selects read-only output; no state changes occur from this query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$requested = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tab       = isset( $tabs[ $requested ] ) ? $requested : ( 'files' === $requested ? 'robots' : 'overview' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Serfe WordPress AI Discovery', 'serfe-ai-discovery' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Serfe WordPress AI Discovery sections', 'serfe-ai-discovery' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg(
				array(
					'page' => self::PAGE,
					'tab'  => $key,
				),
				admin_url( 'admin.php' )
			);
			printf( '<a class="nav-tab %s" href="%s" %s>%s</a>', esc_attr( $key === $tab ? 'nav-tab-active' : '' ), esc_url( $url ), $key === $tab ? 'aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
		switch ( $tab ) {
			case 'markdown':
				$this->render_markdown_guidance();
				echo '<form action="options.php" method="post">';
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				echo '</details>';
				submit_button();
				echo '</form>';
				$this->render_markdown_urls();
				break;
			case 'api-catalog':
				$this->render_api_catalog_tab();
				break;
			case 'robots':
				( new Discovery_Files_Admin() )->render_robots_tab();
				break;
			case 'llms':
				( new Discovery_Files_Admin() )->render_llms_tab();
				break;
			case 'files':
				( new Discovery_Files_Admin() )->render_robots_tab();
				break;
			case 'discovery':
				$this->render_discovery_tab();
				break;
			case 'help':
				$this->render_help_tab();
				break;
			default:
				$this->render_overview_tab();
		}
		echo '</div>';
	}

	/** Explain Markdown publication before an administrator changes its switches. */
	private function render_markdown_guidance() {
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About Markdown negotiation', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Returns an alternative Markdown representation of public content when a compatible client requests it.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Removes unnecessary presentation markup while browsers continue receiving HTML.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: HTML remains the canonical customer-facing version. Markdown availability does not guarantee crawler usage or AI citations.', 'serfe-ai-discovery' ) . '</p></details>';
	}

	/** List the individual items and archives that currently pass the publication policy. */
	private function render_markdown_urls() {
		$choices = self::choices();
		echo '<h2>' . esc_html__( 'Enabled HTML and Markdown URLs', 'serfe-ai-discovery' ) . '</h2>';
		if ( 1 !== (int) $choices['markdown_enabled'] ) {
			echo '<p>' . esc_html__( 'Markdown is disabled, so no URL is published.', 'serfe-ai-discovery' ) . '</p>';
			return;
		}
		$discovery    = new Discovery( new Product_Generator() );
		$rows         = array();
		$target_types = self::supported_post_types();
		if ( ! empty( $target_types ) ) {
			$posts = get_posts(
				array(
					'post_type'        => $target_types,
					'post_status'      => 'publish',
					'posts_per_page'   => 10,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'has_password'     => false,
					'suppress_filters' => false,
				)
			);
			foreach ( $posts as $post ) {
				$url = get_permalink( $post );
				if ( is_string( $url ) && $discovery->post_is_eligible( $post ) ) {
					$rows[] = array( get_the_title( $post ), $url );
				}
			}
		}
		foreach ( self::supported_archive_keys() as $archive ) {
			if ( in_array( $archive, array( 'product_cat', 'product_tag' ), true ) ) {
				continue;
			}
			$url    = get_post_type_archive_link( $archive );
			$object = get_post_type_object( $archive );
			if ( is_string( $url ) && $object && self::archive_enabled_for( $archive ) && ! Exclusions::blocks_archive( $archive, $url ) ) {
				/* translators: %s: post type plural label. */
				$rows[] = array( sprintf( __( '%s archive', 'serfe-ai-discovery' ), $object->labels->name ), $url );
			}
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No item or archive currently passes the publication policy.', 'serfe-ai-discovery' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width: 1000px;"><thead><tr><th>' . esc_html__( 'Content', 'serfe-ai-discovery' ) . '</th><th>HTML</th><th>Markdown</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$markdown = Discovery::markdown_url( $row[1] );
				printf( '<tr><td>%s</td><td><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></td><td><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></td></tr>', esc_html( $row[0] ), esc_url( $row[1] ), esc_html( $row[1] ), esc_url( $markdown ), esc_html( $markdown ) );
			}
			echo '</tbody></table>';
		}
		echo '<p class="description">' . esc_html__( 'Shows the last 10 edited approved items.', 'serfe-ai-discovery' ) . '</p>';

		$this->render_markdown_cache();
	}

	/** Render API Catalog status and a single state-changing action. */
	private function render_api_catalog_tab() {
		$enabled = self::api_catalog_enabled();
		echo '<h2>' . esc_html__( 'API Catalog', 'serfe-ai-discovery' ) . '</h2>';
		echo '<p>' . esc_html__( 'Publish a catalog of the public WordPress REST API at the standard discovery URL.', 'serfe-ai-discovery' ) . '</p>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag after a nonce-protected redirect.
		if ( isset( $_GET['catalog-updated'] ) && in_array( $_GET['catalog-updated'], array( 'enabled', 'disabled' ), true ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'API Catalog publication updated.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		if ( $enabled ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'API Catalog publication is enabled.', 'serfe-ai-discovery' ) . '</p></div>';
			printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( home_url( '/.well-known/api-catalog' ) ), esc_html( home_url( '/.well-known/api-catalog' ) ) );
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'API Catalog publication is disabled.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		echo '<input type="hidden" name="action" value="serfe_ai_discovery_api_catalog">';
		wp_nonce_field( 'serfe_ai_discovery_api_catalog' );
		printf( '<input type="hidden" name="serfe_ai_discovery_api_catalog_action" value="%s">', esc_attr( $enabled ? 'disable' : 'enable' ) );
		submit_button( $enabled ? __( 'Disable API Catalog', 'serfe-ai-discovery' ) : __( 'Enable API Catalog', 'serfe-ai-discovery' ), $enabled ? 'secondary' : 'primary', 'submit', false, $enabled ? array( 'style' => 'color: #b32d2e; border-color: #b32d2e;' ) : array() );
		echo '</form>';
	}

	/** Show the generated-content cache state and its explicit regeneration action. */
	private function render_markdown_cache() {
		$state = Markdown_Cache::state();
		echo '<div style="margin-top: 24px; padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; max-width: 1000px;">';
		echo '<h3 style="margin-top: 0;">' . esc_html__( 'Markdown Cache Management', 'serfe-ai-discovery' ) . '</h3>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag after a nonce-protected redirect.
		if ( isset( $_GET['regenerated'] ) && '1' === $_GET['regenerated'] ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Generated Markdown cache has been regenerated.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Generated Markdown is stored on this server and invalidated automatically whenever content is saved or edited.', 'serfe-ai-discovery' ) . '</p>';
		if ( '' !== $state['invalidated_at'] ) {
			printf(
				'<p class="description">%s</p>',
				/* translators: 1: date and time, 2: reason code. */
				esc_html( sprintf( __( 'Last cache invalidation: %1$s (reason: %2$s, generation: %3$d)', 'serfe-ai-discovery' ), $state['invalidated_at'], $state['reason'], $state['generation'] ) )
			);
		}
		printf( '<form method="post" action="%s" style="margin-top: 12px;"><input type="hidden" name="action" value="serfe_ai_discovery_markdown_cache">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'serfe_ai_discovery_markdown_cache' );
		submit_button( __( 'Regenerate Markdown Cache', 'serfe-ai-discovery' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Emoji for a state level. The emoji complements, and never replaces, the translated text.
	 *
	 * @param string $level One of ok, warn, fail or na.
	 * @return string
	 */
	private static function state_icon( $level ) {
		$icons = array(
			'ok'   => '✅',
			'warn' => '⚠️',
			'fail' => '❌',
			'na'   => '➖',
		);
		return isset( $icons[ $level ] ) ? $icons[ $level ] : $icons['na'];
	}

	/**
	 * Build one Overview row.
	 *
	 * @param string $label     Capability name.
	 * @param string $level     ok, warn, fail or na.
	 * @param string $state     Translated state text.
	 * @param string $next      Translated next action.
	 * @param string $validated Last validation date, or an empty string.
	 * @return array<string, string>
	 */
	private static function overview_row( $label, $level, $state, $next, $validated = '' ) {
		return array(
			'label'     => $label,
			'level'     => $level,
			'state'     => $state,
			'next'      => $next,
			'validated' => $validated,
		);
	}

	/**
	 * Translate a Readiness check into an Overview state.
	 *
	 * @param array<string, string>|null $check   Check result from the last "Run checks".
	 * @param string                     $checked Date of that run.
	 * @return array{0: string, 1: string, 2: string, 3: string}|null Level, state, detail, date.
	 */
	private static function check_state( $check, $checked ) {
		if ( ! is_array( $check ) || ! isset( $check['status'] ) ) {
			return null;
		}
		$detail = isset( $check['detail'] ) ? (string) $check['detail'] : '';
		switch ( $check['status'] ) {
			case 'pass':
				return array( 'ok', __( 'Available', 'serfe-ai-discovery' ), $detail, $checked );
			case 'fail':
				return array( 'fail', __( 'Not available', 'serfe-ai-discovery' ), $detail, $checked );
			case 'na':
				return array( 'na', __( 'Not applicable', 'serfe-ai-discovery' ), $detail, $checked );
			default:
				return array( 'warn', __( 'Needs review', 'serfe-ai-discovery' ), $detail, $checked );
		}
	}

	/**
	 * Index the cached Readiness checks by their stable id.
	 *
	 * @param mixed $report Cached report.
	 * @return array<string, array<string, string>>
	 */
	private static function checks_by_id( $report ) {
		$indexed = array();
		if ( is_array( $report ) && ! empty( $report['checks'] ) && is_array( $report['checks'] ) ) {
			foreach ( $report['checks'] as $check ) {
				if ( is_array( $check ) && isset( $check['id'] ) ) {
					$indexed[ $check['id'] ] = $check;
				}
			}
		}
		return $indexed;
	}

	/**
	 * Compute every Overview row from current settings and the cached discovery checks.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function overview_rows() {
		$choices = self::choices();
		$report  = get_transient( Readiness::CACHE_KEY );
		$checked = is_array( $report ) && ! empty( $report['checked_at'] ) ? (string) $report['checked_at'] : '';
		$checks  = self::checks_by_id( $report );
		$rows    = array();

		if ( 1 !== (int) $choices['markdown_enabled'] ) {
			$rows[] = self::overview_row( __( 'Markdown Negotiation', 'serfe-ai-discovery' ), 'na', __( 'Disabled', 'serfe-ai-discovery' ), __( 'Enable Markdown negotiation in the Markdown tab.', 'serfe-ai-discovery' ) );
		} else {
			$found  = self::check_state( isset( $checks['markdown'] ) ? $checks['markdown'] : null, $checked );
			$rows[] = $found
				? self::overview_row( __( 'Markdown Negotiation', 'serfe-ai-discovery' ), $found[0], $found[1], $found[2], $found[3] )
				: self::overview_row( __( 'Markdown Negotiation', 'serfe-ai-discovery' ), 'ok', __( 'Enabled', 'serfe-ai-discovery' ), __( 'Run the discovery checks to verify a public Markdown response.', 'serfe-ai-discovery' ) );
		}

		$rows[] = $this->file_overview_row( 'robots.txt', isset( $checks['robots'] ) ? $checks['robots'] : null, $checked );
		$rows[] = $this->file_overview_row( 'llms.txt', null, $checked );

		$found  = self::check_state( isset( $checks['sitemap'] ) ? $checks['sitemap'] : null, $checked );
		$rows[] = $found
			? self::overview_row( __( 'XML Sitemap', 'serfe-ai-discovery' ), $found[0], $found[1], $found[2], $found[3] )
			: self::overview_row( __( 'XML Sitemap', 'serfe-ai-discovery' ), 'na', __( 'Not validated', 'serfe-ai-discovery' ), __( 'Run the discovery checks.', 'serfe-ai-discovery' ) );

		$found  = self::check_state( isset( $checks['link_headers'] ) ? $checks['link_headers'] : null, $checked );
		$rows[] = $found
			? self::overview_row( __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), $found[0], $found[1], $found[2], $found[3] )
			: self::overview_row( __( 'Agent discovery Link headers', 'serfe-ai-discovery' ), 'na', __( 'Not validated', 'serfe-ai-discovery' ), __( 'Create llms.txt and an XML sitemap, then run the discovery checks.', 'serfe-ai-discovery' ) );

		if ( 1 !== (int) $choices['api_catalog_enabled'] ) {
			$rows[] = self::overview_row( __( 'API Catalog', 'serfe-ai-discovery' ), 'na', __( 'Disabled', 'serfe-ai-discovery' ), __( 'Enable the API Catalog in the API Catalog tab.', 'serfe-ai-discovery' ) );
		} else {
			$found  = self::check_state( isset( $checks['api_catalog'] ) ? $checks['api_catalog'] : null, $checked );
			$rows[] = $found
				? self::overview_row( __( 'API Catalog', 'serfe-ai-discovery' ), $found[0], $found[1], $found[2], $found[3] )
				: self::overview_row( __( 'API Catalog', 'serfe-ai-discovery' ), 'ok', __( 'Enabled', 'serfe-ai-discovery' ), __( 'Run the discovery checks to verify its public response.', 'serfe-ai-discovery' ) );
		}

		$found  = self::check_state( isset( $checks['mcp'] ) ? $checks['mcp'] : null, $checked );
		$rows[] = $found
			? self::overview_row( __( 'MCP server', 'serfe-ai-discovery' ), $found[0], $found[1], $found[2], $found[3] )
			: self::overview_row( __( 'MCP server', 'serfe-ai-discovery' ), 'na', __( 'Not validated', 'serfe-ai-discovery' ), __( 'Run the local discovery checks for an explicitly registered service.', 'serfe-ai-discovery' ) );

		$rows[] = self::overview_row( __( 'MCP Server Card', 'serfe-ai-discovery' ), 'na', __( 'Not available', 'serfe-ai-discovery' ), __( 'Server Card configuration and publication are not available in this version.', 'serfe-ai-discovery' ) );

		return $rows;
	}

	/** Summarize only states that this plugin can currently establish locally. */
	private function render_overview_tab() {
		echo '<h2>' . esc_html__( 'WordPress AI discovery overview', 'serfe-ai-discovery' ) . '</h2>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag after a nonce-protected redirect.
		if ( isset( $_GET['refreshed'] ) && '1' === $_GET['refreshed'] ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Checks refreshed.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Public discovery features are opt-in. Validation runs only when an administrator requests it.', 'serfe-ai-discovery' ) . '</p></div>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Capability', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'State', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'Last validation', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'Next action', 'serfe-ai-discovery' ) . '</th></tr></thead><tbody>';
		foreach ( $this->overview_rows() as $row ) {
			$validated = '' !== $row['validated'] ? $row['validated'] : __( 'Not validated', 'serfe-ai-discovery' );
			printf(
				'<tr><td>%s</td><td><span aria-hidden="true">%s</span> %s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $row['label'] ),
				esc_html( self::state_icon( $row['level'] ) ),
				esc_html( $row['state'] ),
				esc_html( $validated ),
				esc_html( $row['next'] )
			);
		}
		echo '</tbody></table>';
		echo '<p class="description" style="margin-top: 14px;">' . esc_html__( '✅ ready · ⚠️ needs attention · ❌ not available · ➖ disabled, not validated or not applicable.', 'serfe-ai-discovery' ) . '</p>';
		printf( '<form method="post" action="%s" style="margin-top: 12px;"><input type="hidden" name="action" value="serfe_ai_discovery_readiness_refresh"><input type="hidden" name="return_tab" value="overview">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'serfe_ai_discovery_readiness_refresh' );
		submit_button( __( 'Run checks again', 'serfe-ai-discovery' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Summarize the file actually present at the resolved public root.
	 *
	 * @param string                     $name    robots.txt or llms.txt.
	 * @param array<string, string>|null $check   Matching public-response check, if one ran.
	 * @param string                     $checked Date of the last discovery check run.
	 * @return array<string, string>
	 */
	private function file_overview_row( $name, $check, $checked ) {
		$state = Discovery_Files::inspect( $name );
		if ( is_wp_error( $state ) ) {
			return self::overview_row( $name, 'warn', __( 'Needs review', 'serfe-ai-discovery' ), $state->get_error_message() );
		}
		$meta = $state['meta'];
		$date = isset( $meta['validated_at'] ) && is_string( $meta['validated_at'] ) ? $meta['validated_at'] : '';
		if ( ! $state['exists'] ) {
			$next = __( 'Validate the public response; WordPress or an SEO plugin may serve a virtual file.', 'serfe-ai-discovery' );
			if ( is_array( $check ) && ! empty( $check['detail'] ) ) {
				$next = (string) $check['detail'];
				$date = '' !== $checked ? $checked : $date;
			}
			return self::overview_row( $name, 'warn', __( 'No physical file', 'serfe-ai-discovery' ), $next, $date );
		}
		if ( ! isset( $meta['checksum'] ) || $meta['checksum'] !== $state['checksum'] ) {
			return self::overview_row( $name, 'warn', __( 'External change', 'serfe-ai-discovery' ), __( 'Review the current file and confirm takeover before publishing.', 'serfe-ai-discovery' ), $date );
		}
		return ! empty( $meta['verified'] )
			? self::overview_row( $name, 'ok', __( 'Published and verified', 'serfe-ai-discovery' ), __( 'Review the content after material site changes.', 'serfe-ai-discovery' ), $date )
			: self::overview_row( $name, 'warn', __( 'Published; response needs review', 'serfe-ai-discovery' ), __( 'Check the public URL and any web server or cache rules.', 'serfe-ai-discovery' ), $date );
	}

	/** Render the public-root file editors. */
	private function render_files_tab() {
		( new Discovery_Files_Admin() )->render_tab();
	}

	/** Show the read-only diagnostic without triggering a request. */
	private function render_discovery_tab() {
		$choices = self::choices();
		echo '<h2>' . esc_html__( 'Discovery resources', 'serfe-ai-discovery' ) . '</h2>';
		printf( '<p>%s</p>', esc_html( 1 === (int) $choices['api_catalog_enabled'] ? __( 'API Catalog publication is enabled.', 'serfe-ai-discovery' ) : __( 'API Catalog publication is disabled. Configure it in the API Catalog tab.', 'serfe-ai-discovery' ) ) );
		echo '<p>' . esc_html__( 'When valid public llms.txt and XML sitemap responses are detected, the plugin automatically publishes RFC 8288 Link headers. Create either missing resource, then run the checks again.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'The local checks inspect public responses only when you run them. A Server Card requires a validated MCP endpoint and is not available yet.', 'serfe-ai-discovery' ) . '</p>';
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About XML Sitemaps', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Lists canonical public URLs in a machine-readable format.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Gives crawlers and technical tools a consistent public URL map.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: Include live canonical public URLs only. Exclude redirects, noindex content, duplicates, private pages, search pages, cart, checkout, and account pages.', 'serfe-ai-discovery' ) . '</p></details>';
		( new Readiness() )->render_page();
	}

	/** Provide maintained local documentation and clear feature limits. */
	private function render_help_tab() {
		echo '<h2>' . esc_html__( 'Help & Support', 'serfe-ai-discovery' ) . '</h2>';
		echo '<p>' . esc_html__( 'This starter plugin serves approved public content as Markdown, can publish robots.txt and llms.txt, and publishes an optional API Catalog. Generated Markdown uses a server-side cache with invalidation; HTTP responses remain private and no-store. MCP Server Cards are not available yet.', 'serfe-ai-discovery' ) . '</p>';
		printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( 'https://github.com/Serfe-com' ), esc_html__( 'Serfe open-source repositories', 'serfe-ai-discovery' ) );
		echo '<p>' . esc_html__( 'For support and security reporting, use the channels documented in the plugin repository.', 'serfe-ai-discovery' ) . '</p>';

		$logo       = 'serfe-logo-white.webp';
		$style      = 'background: #123b68; color: #fff;';
		$link_style = 'color: #fff;';
		printf(
			'<div class="serfe-help-serfe-card" style="max-width: 1000px; margin-top: 32px; padding: 20px 24px; border-radius: 4px; %1$s"><a href="%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s"><img src="%4$s" alt="%5$s" style="display: block; width: 149px; height: auto; max-width: 100%%;"></a><h3 style="color: inherit;">%6$s</h3><p>%7$s</p><p style="margin-bottom: 0;"><a href="%8$s" target="_blank" rel="noopener noreferrer" style="%9$s">%10$s</a></p></div>',
			esc_attr( $style ),
			esc_url( 'https://www.serfe.com?utm_source=serfe-ai-discovery' ),
			esc_attr__( 'Visit Serfe', 'serfe-ai-discovery' ),
			esc_url( SERFE_AI_DISCOVERY_URL . 'assets/' . $logo ),
			esc_attr__( 'Serfe', 'serfe-ai-discovery' ),
			esc_html__( 'Need a broader AI discovery or GEO assessment?', 'serfe-ai-discovery' ),
			esc_html__( 'This plugin provides basic public-content and discovery signals only. Serfe can help review technical foundations, content, SEO, risks, and a broader implementation roadmap.', 'serfe-ai-discovery' ),
			esc_url( 'https://www.serfe.com/en/contact-us?utm_source=serfe-ai-discovery' ),
			esc_attr( $link_style ),
			esc_html__( 'Contact Serfe', 'serfe-ai-discovery' )
		);
	}
}
