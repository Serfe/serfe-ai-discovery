<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrator choices for public discovery features. */
final class Settings {
	const OPTION         = 'serfe_ai_discovery_settings';
	const ITEM_META      = '_serfe_ai_discovery_markdown_enabled';
	const NONCE_ACTION   = 'serfe_ai_discovery_save_item';
	const NONCE_NAME     = 'serfe_ai_discovery_item_nonce';
	const GROUP          = 'serfe_ai_discovery';
	const PAGE           = 'serfe-ai-discovery';
	const SCHEMA_VERSION = 2;

	/** Register the settings screen and per-item opt-in. */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'migrate_legacy_option' ), 5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_item_boxes' ) );
		add_action( 'save_post', array( $this, 'save_item_choice' ), 10, 2 );
		add_action( 'admin_post_serfe_ai_discovery_markdown_cache', array( $this, 'regenerate_markdown_cache' ) );
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

	/** @return array<string, mixed> */
	public static function choices() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		if ( ! isset( $stored['schema_version'] ) || (int) $stored['schema_version'] < self::SCHEMA_VERSION ) {
			$stored = self::migrate_legacy( $stored );
		}
		$choices                      = wp_parse_args( $stored, self::defaults() );
		$choices['detail_post_types'] = is_array( $choices['detail_post_types'] ) ? $choices['detail_post_types'] : array();
		$choices['enabled_archives']  = is_array( $choices['enabled_archives'] ) ? $choices['enabled_archives'] : array();
		foreach ( array( 'blocked_post_types', 'blocked_paths', 'blocked_terms' ) as $key ) {
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
				'detail_post_types'   => isset( $stored['enabled_post_types'] ) ? $stored['enabled_post_types'] : array(),
				'api_catalog_enabled' => isset( $stored['api_catalog_enabled'] ) ? $stored['api_catalog_enabled'] : 0,
			)
		);
	}

	/** @return array<string, mixed> */
	private static function defaults() {
		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'markdown_enabled'    => 0,
			'detail_post_types'   => array(),
			'enabled_archives'    => array(),
			'blocked_post_types'  => array(),
			'blocked_paths'       => array(),
			'blocked_terms'       => array(),
			'api_catalog_enabled' => 0,
		);
	}

	/** @param string $post_type Post type. @return bool */
	public static function markdown_enabled_for( $post_type ) {
		$choices = self::choices();
		return 1 === (int) $choices['markdown_enabled']
			&& in_array( $post_type, self::supported_post_types(), true )
			&& in_array( $post_type, $choices['detail_post_types'], true );
	}

	/** @param string $archive Archive post type or supported product taxonomy. @return bool */
	public static function archive_enabled_for( $archive ) {
		$choices = self::choices();
		return 1 === (int) $choices['markdown_enabled']
			&& in_array( $archive, self::supported_archive_keys(), true )
			&& in_array( $archive, $choices['enabled_archives'], true );
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

	/** @param int $post_id Post ID. @return bool */
	public static function item_enabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::ITEM_META, true );
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
		$types         = isset( $input['detail_post_types'] ) && is_array( $input['detail_post_types'] )
			? $input['detail_post_types']
			: array();
		$archives      = isset( $input['enabled_archives'] ) && is_array( $input['enabled_archives'] )
			? $input['enabled_archives']
			: array();
		$blocked_types = isset( $input['blocked_post_types'] ) && is_array( $input['blocked_post_types'] ) ? $input['blocked_post_types'] : array();

		$allowed_types = array();
		$supported     = self::supported_post_types();
		foreach ( $types as $type ) {
			if ( is_string( $type ) && in_array( $type, $supported, true ) ) {
				$allowed_types[] = $type;
			}
		}
		$allowed_archives   = array();
		$supported_archives = self::supported_archive_keys();
		foreach ( $archives as $archive ) {
			if ( is_string( $archive ) && in_array( $archive, $supported_archives, true ) ) {
				$allowed_archives[] = $archive;
			}
		}
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
		$blocked_terms = array();
		$term_lines    = isset( $input['blocked_terms'] ) && is_string( $input['blocked_terms'] ) ? preg_split( '/\r\n|\r|\n/', $input['blocked_terms'] ) : array();
		foreach ( array_slice( $term_lines, 0, 100 ) as $line ) {
			$key = trim( $line );
			if ( ! preg_match( '/^([a-z][a-z0-9_]*)\:([1-9][0-9]*)$/', $key, $parts ) ) {
				continue;
			}
			$taxonomy = $parts[1];
			$term_id  = (int) $parts[2];
			$object   = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : null;
			if ( $term_id > 0 && $object && ! empty( $object->public ) && ! empty( $object->publicly_queryable ) && term_exists( $term_id, $taxonomy ) ) {
				$blocked_terms[] = $taxonomy . ':' . $term_id;
			}
		}

		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'markdown_enabled'    => isset( $input['markdown_enabled'] ) && in_array( $input['markdown_enabled'], array( '1', 1 ), true ) ? 1 : 0,
			'detail_post_types'   => array_values( array_unique( $allowed_types ) ),
			'enabled_archives'    => array_values( array_unique( $allowed_archives ) ),
			'blocked_post_types'  => array_values( array_unique( $allowed_blocked_types ) ),
			'blocked_paths'       => array_values( array_unique( $blocked_paths ) ),
			'blocked_terms'       => array_values( array_unique( $blocked_terms ) ),
			'api_catalog_enabled' => isset( $input['api_catalog_enabled'] ) && in_array( $input['api_catalog_enabled'], array( '1', 1 ), true ) ? 1 : 0,
		);
	}

	/** Register all submitted fields through the Settings API. */
	public function register_settings() {
		register_setting( self::GROUP, self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
		add_settings_section( 'serfe_ai_discovery_publication', __( 'Public discovery', 'serfe-ai-discovery' ), array( $this, 'render_section' ), self::PAGE );
		add_settings_field( 'markdown_enabled', __( 'Markdown', 'serfe-ai-discovery' ), array( $this, 'render_markdown_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'detail_post_types', __( 'Individual content', 'serfe-ai-discovery' ), array( $this, 'render_types_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'enabled_archives', __( 'Public archives', 'serfe-ai-discovery' ), array( $this, 'render_archives_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'blocked_post_types', __( 'Excluded content types', 'serfe-ai-discovery' ), array( $this, 'render_blocked_types_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'blocked_paths', __( 'Excluded URL paths', 'serfe-ai-discovery' ), array( $this, 'render_blocked_paths_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'blocked_terms', __( 'Excluded categories and terms', 'serfe-ai-discovery' ), array( $this, 'render_blocked_terms_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'api_catalog_enabled', __( 'API Catalog', 'serfe-ai-discovery' ), array( $this, 'render_catalog_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
	}

	/** Add Serfe AI Discovery as a top-level admin menu. */
	public function register_page() {
		// WordPress centers SVG data URIs as menu backgrounds instead of rendering an img element.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read-only bundled SVG asset.
		$svg = file_get_contents( SERFE_AI_DISCOVERY_PATH . 'assets/wp_serfe_logo_gray.svg' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress requires an SVG data URI for centered menu icons.
		$icon_url = false !== $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-admin-generic';
		add_menu_page( __( 'Serfe AI Discovery', 'serfe-ai-discovery' ), __( 'Serfe AI Discovery', 'serfe-ai-discovery' ), 'manage_options', self::PAGE, array( $this, 'render_page' ), $icon_url );
	}

	/** Explain the scope of the current switches. */
	public function render_section() {
		echo '<p>' . esc_html__( 'Markdown is available only for selected content types and items. All publication options are disabled until you enable them.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the Markdown master switch. */
	public function render_markdown_field() {
		$choices = self::choices();
		printf( '<label><input type="checkbox" name="%s[markdown_enabled]" value="1" %s> %s</label>', esc_attr( self::OPTION ), checked( 1, $choices['markdown_enabled'], false ), esc_html__( 'Enable Markdown for selected content', 'serfe-ai-discovery' ) );
	}

	/** Render currently supported singular post types. */
	public function render_types_field() {
		$choices = self::choices();
		foreach ( self::supported_post_types() as $type ) {
			$object = get_post_type_object( $type );
			$label  = $object && isset( $object->labels->name ) ? $object->labels->name : $type;
			printf( '<label><input type="checkbox" name="%s[detail_post_types][]" value="%s" %s> %s</label><br>', esc_attr( self::OPTION ), esc_attr( $type ), checked( in_array( $type, $choices['detail_post_types'], true ), true, false ), esc_html( $label ) );
		}
	}

	/** Render independent choices for public post-type and product-taxonomy archives. */
	public function render_archives_field() {
		$choices = self::choices();
		foreach ( self::supported_archive_keys() as $archive ) {
			if ( 'product_cat' === $archive ) {
				$label = __( 'Product categories', 'serfe-ai-discovery' );
			} elseif ( 'product_tag' === $archive ) {
				$label = __( 'Product tags', 'serfe-ai-discovery' );
			} else {
				$object = get_post_type_object( $archive );
				$label  = $object && isset( $object->labels->name ) ? $object->labels->name : $archive;
			}
			printf( '<label><input type="checkbox" name="%s[enabled_archives][]" value="%s" %s> %s</label><br>', esc_attr( self::OPTION ), esc_attr( $archive ), checked( in_array( $archive, $choices['enabled_archives'], true ), true, false ), esc_html( $label ) );
		}
		echo '<p class="description">' . esc_html__( 'Enabled public archives list only individually approved items. WooCommerce shop, product category, and product tag archives can be enabled separately.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the API Catalog switch. */
	public function render_catalog_field() {
		$choices = self::choices();
		printf( '<label><input type="checkbox" name="%s[api_catalog_enabled]" value="1" %s> %s</label>', esc_attr( self::OPTION ), checked( 1, $choices['api_catalog_enabled'], false ), esc_html__( 'Publish a catalog of the public WordPress REST API', 'serfe-ai-discovery' ) );
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

	/** Render public taxonomy terms to exclude, one taxonomy and ID per line. */
	public function render_blocked_terms_field() {
		$choices = self::choices();
		printf( '<textarea name="%s[blocked_terms]" rows="5" cols="55">%s</textarea>', esc_attr( self::OPTION ), esc_textarea( implode( "\n", $choices['blocked_terms'] ) ) );
		echo '<p class="description">' . esc_html__( 'One public taxonomy and term ID per line, such as category:123 or product_cat:456. Matching items will not have Markdown.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the settings form. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array(
			'overview'  => __( 'Overview', 'serfe-ai-discovery' ),
			'markdown'  => __( 'Markdown', 'serfe-ai-discovery' ),
			'robots'    => __( 'Robots.txt', 'serfe-ai-discovery' ),
			'llms'      => __( 'llms.txt', 'serfe-ai-discovery' ),
			'discovery' => __( 'Discovery', 'serfe-ai-discovery' ),
			'help'      => __( 'Help & Support', 'serfe-ai-discovery' ),
		);
		// The tab selects read-only output; no state changes occur from this query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$requested = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tab       = isset( $tabs[ $requested ] ) ? $requested : ( 'files' === $requested ? 'robots' : 'overview' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Serfe AI Discovery', 'serfe-ai-discovery' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Serfe AI Discovery sections', 'serfe-ai-discovery' ) . '">';
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
				submit_button();
				echo '</form>';
				$this->render_markdown_urls();
				$this->render_markdown_cache();
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
		echo '<p>' . esc_html__( 'What it does: Returns an alternative Markdown representation of approved public content when a compatible client requests it.', 'serfe-ai-discovery' ) . '</p>';
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
		$discovery = new Discovery( new Product_Generator() );
		$rows      = array();
		if ( $choices['detail_post_types'] ) {
			$posts = get_posts(
				array(
					'post_type'        => $choices['detail_post_types'],
					'post_status'      => 'publish',
					'posts_per_page'   => 50,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'has_password'     => false,
					'suppress_filters' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded admin-only list of opted-in items.
					'meta_query'       => array(
						array(
							'key'   => self::ITEM_META,
							'value' => '1',
						),
					),
				)
			);
			foreach ( $posts as $post ) {
				$url = get_permalink( $post );
				if ( is_string( $url ) && $discovery->post_is_eligible( $post ) ) {
					$rows[] = array( get_the_title( $post ), $url );
				}
			}
		}
		foreach ( $choices['enabled_archives'] as $archive ) {
			if ( in_array( $archive, array( 'product_cat', 'product_tag' ), true ) ) {
				continue;
			}
			$url    = get_post_type_archive_link( $archive );
			$object = get_post_type_object( $archive );
			if ( is_string( $url ) && $object && ! Exclusions::blocks_archive( $archive, $url ) ) {
				/* translators: %s: post type plural label. */
				$rows[] = array( sprintf( __( '%s archive', 'serfe-ai-discovery' ), $object->labels->name ), $url );
			}
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No item or archive currently passes the publication policy. Enable a content type and approve items in their editor.', 'serfe-ai-discovery' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width: 1000px;"><thead><tr><th>' . esc_html__( 'Content', 'serfe-ai-discovery' ) . '</th><th>HTML</th><th>Markdown</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$markdown = Discovery::markdown_url( $row[1] );
				printf( '<tr><td>%s</td><td><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></td><td><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></td></tr>', esc_html( $row[0] ), esc_url( $row[1] ), esc_html( $row[1] ), esc_url( $markdown ), esc_html( $markdown ) );
			}
			echo '</tbody></table>';
		}
		echo '<p class="description">' . esc_html__( 'Shows up to 50 recently modified approved items. Product category and tag archives follow the same rules for each public term.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Show the generated-content cache state and its explicit regeneration action. */
	private function render_markdown_cache() {
		$state = Markdown_Cache::state();
		echo '<h2>' . esc_html__( 'Generated Markdown cache', 'serfe-ai-discovery' ) . '</h2>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag after a nonce-protected redirect.
		if ( isset( $_GET['regenerated'] ) && '1' === $_GET['regenerated'] ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Generated Markdown will be rebuilt on the next request for each URL.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Generated Markdown is stored on this server for up to one day and discarded whenever content, visibility, prices, stock, catalog terms, approvals, or these settings change. Eligibility is checked on every request before a stored document is used. Markdown responses always send Cache-Control: private, no-store, so proxies and CDNs do not keep them.', 'serfe-ai-discovery' ) . '</p>';
		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Last invalidation:', 'serfe-ai-discovery' ),
			esc_html( '' !== $state['invalidated_at'] ? sprintf( /* translators: 1: date and time, 2: reason code. */ __( '%1$s (%2$s)', 'serfe-ai-discovery' ), $state['invalidated_at'], $state['reason'] ) : __( 'Not yet invalidated', 'serfe-ai-discovery' ) )
		);
		printf( '<form method="post" action="%s"><input type="hidden" name="action" value="serfe_ai_discovery_markdown_cache">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'serfe_ai_discovery_markdown_cache' );
		submit_button( __( 'Regenerate Markdown cache', 'serfe-ai-discovery' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/** Summarize only states that this plugin can currently establish locally. */
	private function render_overview_tab() {
		$choices = self::choices();
		$report  = get_transient( Readiness::CACHE_KEY );
		$date    = is_array( $report ) && ! empty( $report['checked_at'] ) ? $report['checked_at'] : __( 'Not validated', 'serfe-ai-discovery' );
		$checks  = is_array( $report ) && ! empty( $report['checks'] ) && is_array( $report['checks'] ) ? $report['checks'] : array();
		$rows    = array(
			array( __( 'Markdown Negotiation', 'serfe-ai-discovery' ), 1 === (int) $choices['markdown_enabled'] ? __( 'Enabled', 'serfe-ai-discovery' ) : __( 'Disabled', 'serfe-ai-discovery' ), __( 'Select content types and approve individual items in the Markdown tab.', 'serfe-ai-discovery' ), '' ),
			array( 'robots.txt', __( 'Not validated', 'serfe-ai-discovery' ), __( 'Run the local discovery checks.', 'serfe-ai-discovery' ), 'robots.txt' ),
			array( 'llms.txt', __( 'Not validated', 'serfe-ai-discovery' ), __( 'Review or publish the public file.', 'serfe-ai-discovery' ), '' ),
			array( __( 'XML Sitemap', 'serfe-ai-discovery' ), __( 'Not validated', 'serfe-ai-discovery' ), __( 'Run the local discovery checks.', 'serfe-ai-discovery' ), __( 'XML sitemap', 'serfe-ai-discovery' ) ),
			array( __( 'API Catalog', 'serfe-ai-discovery' ), 1 === (int) $choices['api_catalog_enabled'] ? __( 'Enabled', 'serfe-ai-discovery' ) : __( 'Disabled', 'serfe-ai-discovery' ), __( 'Review its public response in Discovery.', 'serfe-ai-discovery' ), 'API Catalog' ),
			array( __( 'MCP server', 'serfe-ai-discovery' ), __( 'Not validated', 'serfe-ai-discovery' ), __( 'Run the local discovery checks for an explicitly registered service.', 'serfe-ai-discovery' ), __( 'MCP server', 'serfe-ai-discovery' ) ),
			array( __( 'MCP Server Card', 'serfe-ai-discovery' ), __( 'Not available', 'serfe-ai-discovery' ), __( 'Server Card configuration and publication are not available in this version.', 'serfe-ai-discovery' ), '' ),
		);
		foreach ( $rows as &$row ) {
			if ( '' === $row[3] ) {
				continue;
			}
			foreach ( $checks as $check ) {
				if ( isset( $check['label'], $check['status'] ) && $row[3] === $check['label'] ) {
					$row[1] = 'pass' === $check['status'] ? __( 'Available', 'serfe-ai-discovery' ) : ( 'unknown' === $check['status'] ? __( 'Needs review', 'serfe-ai-discovery' ) : __( 'Not available', 'serfe-ai-discovery' ) );
					$row[2] = isset( $check['detail'] ) ? $check['detail'] : $row[2];
					$row[4] = true;
				}
			}
		}
		unset( $row );
		$rows[1] = $this->file_overview_row( 'robots.txt' );
		$rows[2] = $this->file_overview_row( 'llms.txt' );
		echo '<p>' . esc_html__( 'Public discovery features are opt-in. Validation runs only when an administrator requests it.', 'serfe-ai-discovery' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Capability', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'State', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'Last validation', 'serfe-ai-discovery' ) . '</th><th>' . esc_html__( 'Next action', 'serfe-ai-discovery' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$checked = ! empty( $row[4] ) ? ( is_string( $row[4] ) ? $row[4] : $date ) : __( 'Not validated', 'serfe-ai-discovery' );
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( $row[0] ), esc_html( $row[1] ), esc_html( $checked ), esc_html( $row[2] ) );
		}
		echo '</tbody></table>';
	}

	/** Summarize the file actually present at the resolved public root. */
	private function file_overview_row( $name ) {
		$state = Discovery_Files::inspect( $name );
		if ( is_wp_error( $state ) ) {
			return array( $name, __( 'Needs review', 'serfe-ai-discovery' ), $state->get_error_message(), '', false );
		}
		$meta = $state['meta'];
		$date = isset( $meta['validated_at'] ) ? $meta['validated_at'] : false;
		if ( ! $state['exists'] ) {
			return array( $name, __( 'No physical file', 'serfe-ai-discovery' ), __( 'Validate the public response; WordPress or an SEO plugin may serve a virtual file.', 'serfe-ai-discovery' ), '', $date );
		}
		if ( ! isset( $meta['checksum'] ) || $meta['checksum'] !== $state['checksum'] ) {
			return array( $name, __( 'External change', 'serfe-ai-discovery' ), __( 'Review the current file and confirm takeover before publishing.', 'serfe-ai-discovery' ), '', $date );
		}
		return ! empty( $meta['verified'] )
			? array( $name, __( 'Published and verified', 'serfe-ai-discovery' ), __( 'Review the content after material site changes.', 'serfe-ai-discovery' ), '', $date )
			: array( $name, __( 'Published; response needs review', 'serfe-ai-discovery' ), __( 'Check the public URL and any web server or cache rules.', 'serfe-ai-discovery' ), '', $date );
	}

	/** Render the public-root file editors. */
	private function render_files_tab() {
		( new Discovery_Files_Admin() )->render_tab();
	}

	/** Show the read-only diagnostic without triggering a request. */
	private function render_discovery_tab() {
		$choices = self::choices();
		echo '<h2>' . esc_html__( 'Discovery resources', 'serfe-ai-discovery' ) . '</h2>';
		printf( '<p>%s</p>', esc_html( 1 === (int) $choices['api_catalog_enabled'] ? __( 'API Catalog publication is enabled.', 'serfe-ai-discovery' ) : __( 'API Catalog publication is disabled. Its switch is currently on the Markdown tab.', 'serfe-ai-discovery' ) ) );
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

		$surface    = 'light' === get_user_option( 'admin_color' ) ? 'light' : 'dark';
		$surface    = apply_filters( 'serfe_ai_discovery_footer_surface', $surface );
		$surface    = 'dark' === $surface ? 'dark' : 'light';
		$logo       = 'dark' === $surface ? 'serfe-logo-white.png' : 'serfe-logo-blue.png';
		$style      = 'dark' === $surface ? 'background: #123b68; color: #fff;' : 'background: #fff; color: #1d2327;';
		$link_style = 'dark' === $surface ? 'color: #fff;' : 'color: #135e96;';
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

	/** Add a separate choice to each supported content editor. */
	public function register_item_boxes() {
		foreach ( self::supported_post_types() as $type ) {
			if ( post_type_exists( $type ) ) {
				add_meta_box( 'serfe_ai_discovery_markdown', __( 'Serfe AI Discovery', 'serfe-ai-discovery' ), array( $this, 'render_item_box' ), $type, 'side' );
			}
		}
	}

	/** @param \WP_Post $post Edited content. */
	public function render_item_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		printf( '<label><input type="checkbox" name="serfe_ai_discovery_item_enabled" value="1" %s> %s</label>', checked( self::item_enabled( $post->ID ), true, false ), esc_html__( 'Allow Markdown when this content type is enabled', 'serfe-ai-discovery' ) );
	}

	/** @param int $post_id Saved post ID. @param \WP_Post $post Saved post. */
	public function save_item_choice( $post_id, $post ) {
		if ( ! in_array( $post->post_type, self::supported_post_types(), true )
			|| ! isset( $_POST[ self::NONCE_NAME ] )
			|| ! is_string( $_POST[ self::NONCE_NAME ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION )
			|| ! current_user_can( 'edit_post', $post_id )
			|| wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['serfe_ai_discovery_item_enabled'] ) && '1' === $_POST['serfe_ai_discovery_item_enabled'] ) {
			update_post_meta( $post_id, self::ITEM_META, '1' );
		} else {
			delete_post_meta( $post_id, self::ITEM_META );
		}
	}
}
