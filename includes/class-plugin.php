<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates routing, dispatch and output. */
final class Plugin {
	/** @var self|null */
	private static $instance;
	/** @var bool */
	private $booted = false;
	/** @var Request_Router */
	private $router;
	/** @var Response */
	private $response;
	/** @var Blog_Generator */
	private $blog_generator;
	/** @var Product_Generator */
	private $product_generator;
	/** @var Archive_Generator */
	private $archive_generator;
	/** @var Catalog_Generator */
	private $catalog_generator;
	/** @var Discovery */
	private $discovery;
	/** @var Readiness */
	private $readiness;
	/** @var Settings */
	private $settings;
	/** @var Discovery_Files_Admin */
	private $discovery_files_admin;

	/** @return self */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->router                = new Request_Router();
		$this->response              = new Response();
		$this->blog_generator        = new Blog_Generator( new Html_To_Markdown() );
		$this->product_generator     = new Product_Generator();
		$this->archive_generator     = new Archive_Generator();
		$this->catalog_generator     = new Catalog_Generator( $this->product_generator );
		$this->discovery             = new Discovery( $this->product_generator );
		$this->readiness             = new Readiness();
		$this->settings              = new Settings();
		$this->discovery_files_admin = new Discovery_Files_Admin();
	}

	/** Register the plugin once, early enough to alter REQUEST_URI. */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		$this->router->capture();
		add_action( 'template_redirect', array( $this, 'dispatch' ), 0 );
		$this->discovery->register_hooks();
		$this->readiness->register_hooks();
		$this->settings->register_hooks();
		$this->discovery_files_admin->register_hooks();
	}

	/** Render the resolved object when this is a Markdown request. */
	public function dispatch() {
		if ( ! $this->router->is_markdown_request() ) {
			return;
		}
		$archive = $this->discovery->current_archive_context();
		if ( $archive ) {
			global $wp_query;
			if ( ! ( $wp_query instanceof \WP_Query ) ) {
				$this->response->not_found();
			}
			$markdown = in_array( $archive['type'], array( 'product', 'product_cat', 'product_tag' ), true )
				? $this->catalog_generator->generate( $archive, (int) $wp_query->max_num_pages, $wp_query->posts )
				: $this->archive_generator->generate( $archive['type'], $archive['url'], $archive['page'], (int) $wp_query->max_num_pages, $wp_query->posts );
			$this->response->markdown( $markdown, $this->router->is_accept_negotiated(), $archive['url'] );
		}

		if ( ! $this->discovery->current_content_has_markdown() ) {
			$this->response->not_found();
		}

		$post = get_queried_object();
		switch ( $post->post_type ) {
			case 'product':
				$this->response->markdown( $this->product_generator->generate( $post ), $this->router->is_accept_negotiated(), get_permalink( $post ) );
				break;
			default:
				$this->response->markdown( $this->blog_generator->generate( $post ), $this->router->is_accept_negotiated(), get_permalink( $post ) );
				break;
		}

		$this->response->not_found();
	}
}
