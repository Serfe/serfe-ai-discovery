<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrator choices for public discovery features. */
final class Settings {
	const OPTION = 'serfe_ai_discovery_settings';
	const ITEM_META = '_serfe_ai_discovery_markdown_enabled';
	const NONCE_ACTION = 'serfe_ai_discovery_save_item';
	const NONCE_NAME = 'serfe_ai_discovery_item_nonce';
	const GROUP = 'serfe_ai_discovery';
	const PAGE = 'serfe-ai-discovery';

	/** Register the settings screen and per-item opt-in. */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_item_boxes' ) );
		add_action( 'save_post', array( $this, 'save_item_choice' ), 10, 2 );
	}

	/** @return array<string, mixed> */
	public static function choices() {
		$stored = get_option( self::OPTION, array() );
		$choices = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		if ( ! is_array( $choices['enabled_post_types'] ) ) {
			$choices['enabled_post_types'] = array();
		}
		return $choices;
	}

	/** @return array<string, mixed> */
	private static function defaults() {
		return array(
			'schema_version'      => 1,
			'markdown_enabled'   => 0,
			'enabled_post_types' => array(),
			'api_catalog_enabled' => 0,
		);
	}

	/** @param string $post_type Post type. @return bool */
	public static function markdown_enabled_for( $post_type ) {
		$choices = self::choices();
		return 1 === (int) $choices['markdown_enabled']
			&& is_array( $choices['enabled_post_types'] )
			&& in_array( $post_type, $choices['enabled_post_types'], true );
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

	/** @param mixed $input Submitted setting. @return array<string, mixed> */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$types = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? $input['enabled_post_types']
			: array();

		$allowed_types = array();
		foreach ( $types as $type ) {
			if ( is_string( $type ) && in_array( $type, array( 'post', 'page', 'product' ), true ) ) {
				$allowed_types[] = $type;
			}
		}

		return array(
			'schema_version'      => 1,
			'markdown_enabled'   => isset( $input['markdown_enabled'] ) && '1' === $input['markdown_enabled'] ? 1 : 0,
			'enabled_post_types' => array_values( array_unique( $allowed_types ) ),
			'api_catalog_enabled' => isset( $input['api_catalog_enabled'] ) && '1' === $input['api_catalog_enabled'] ? 1 : 0,
		);
	}

	/** Register all submitted fields through the Settings API. */
	public function register_settings() {
		register_setting( self::GROUP, self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
		add_settings_section( 'serfe_ai_discovery_publication', __( 'Public discovery', 'serfe-ai-discovery' ), array( $this, 'render_section' ), self::PAGE );
		add_settings_field( 'markdown_enabled', __( 'Markdown', 'serfe-ai-discovery' ), array( $this, 'render_markdown_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'enabled_post_types', __( 'Content types', 'serfe-ai-discovery' ), array( $this, 'render_types_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
		add_settings_field( 'api_catalog_enabled', __( 'API Catalog', 'serfe-ai-discovery' ), array( $this, 'render_catalog_field' ), self::PAGE, 'serfe_ai_discovery_publication' );
	}

	/** Add Settings > Serfe AI Discovery. */
	public function register_page() {
		add_options_page( __( 'Serfe AI Discovery', 'serfe-ai-discovery' ), __( 'Serfe AI Discovery', 'serfe-ai-discovery' ), 'manage_options', self::PAGE, array( $this, 'render_page' ) );
	}

	/** Explain the scope of the current switches. */
	public function render_section() {
		echo '<p>' . esc_html__( 'Markdown is available only for selected content types and items. All publication options are disabled until you enable them.', 'serfe-ai-discovery' ) . '</p>';
	}

	/** Render the Markdown master switch. */
	public function render_markdown_field() {
		$choices = self::choices();
		printf( '<label><input type="checkbox" name="%s[markdown_enabled]" value="1" %s> %s</label>', esc_attr( self::OPTION ), checked( 1, $choices['markdown_enabled'], false ), esc_html__( 'Enable Markdown for selected items', 'serfe-ai-discovery' ) );
	}

	/** Render currently supported singular post types. */
	public function render_types_field() {
		$choices = self::choices();
		$types = array(
			'post' => __( 'Posts', 'serfe-ai-discovery' ),
			'page' => __( 'Pages', 'serfe-ai-discovery' ),
			'product' => __( 'WooCommerce products', 'serfe-ai-discovery' ),
		);
		foreach ( $types as $type => $label ) {
			if ( 'product' === $type && ! post_type_exists( 'product' ) ) {
				continue;
			}
			printf( '<label><input type="checkbox" name="%s[enabled_post_types][]" value="%s" %s> %s</label><br>', esc_attr( self::OPTION ), esc_attr( $type ), checked( in_array( $type, $choices['enabled_post_types'], true ), true, false ), esc_html( $label ) );
		}
	}

	/** Render the API Catalog switch. */
	public function render_catalog_field() {
		$choices = self::choices();
		printf( '<label><input type="checkbox" name="%s[api_catalog_enabled]" value="1" %s> %s</label>', esc_attr( self::OPTION ), checked( 1, $choices['api_catalog_enabled'], false ), esc_html__( 'Publish a catalog of the public WordPress REST API', 'serfe-ai-discovery' ) );
	}

	/** Render the settings form. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Serfe AI Discovery', 'serfe-ai-discovery' ) . '</h1><form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}

	/** Add a separate choice to each supported content editor. */
	public function register_item_boxes() {
		foreach ( array( 'post', 'page', 'product' ) as $type ) {
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
		if ( ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true )
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
