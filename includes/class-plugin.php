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
	/** @var Markdown_Cache */
	private $markdown_cache;

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
		$this->markdown_cache        = new Markdown_Cache();
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
		Discovery_Files::register_hooks();
		$this->markdown_cache->register_hooks();
		Cache_Integrations::register_hooks();
		Cache_Integrations::sync_rocket_config();
	}

	/** Render the resolved object when this is a Markdown request. */
	public function dispatch() {
		if ( ! $this->router->is_markdown_request() ) {
			return;
		}
		$negotiated          = $this->router->is_accept_negotiated();
		$allow_admin_preview = ! $negotiated && current_user_can( 'manage_options' );
		$archive             = $this->discovery->current_archive_context( $allow_admin_preview );
		if ( $archive ) {
			global $wp_query;
			if ( ! ( $wp_query instanceof \WP_Query ) ) {
				$this->response->not_found();
			}
			$catalog   = $this->catalog_generator;
			$generator = $this->archive_generator;
			$query     = $wp_query;
			$result    = Markdown_Cache::remember(
				'archive',
				$archive['type'] . '|' . (int) $query->max_num_pages . '|' . implode( ',', wp_list_pluck( (array) $query->posts, 'ID' ) ),
				$archive['url'],
				static function () use ( $archive, $catalog, $generator, $query ) {
					return in_array( $archive['type'], array( 'product', 'product_cat', 'product_tag' ), true )
						? $catalog->generate( $archive, (int) $query->max_num_pages, $query->posts )
						: $generator->generate(
							$archive['type'],
							$archive['url'],
							$archive['page'],
							(int) $query->max_num_pages,
							$query->posts,
							isset( $archive['term'] ) && $archive['term'] instanceof \WP_Term ? $archive['term']->name : '',
							isset( $archive['archive_type'] ) ? $archive['archive_type'] : ''
						);
				}
			);
			$this->response->markdown( $result['markdown'], $negotiated, $archive['url'], $result['hit'] );
		}

		if ( ! $this->discovery->current_content_has_markdown( $allow_admin_preview ) ) {
			if ( $negotiated ) {
				// Accept is a preference, not a route: ineligible content keeps its normal HTML response.
				return;
			}
			$this->response->not_found();
		}

		$post      = get_queried_object();
		$canonical = (string) get_permalink( $post );
		$generator = 'product' === $post->post_type ? $this->product_generator : $this->blog_generator;
		$result    = Markdown_Cache::remember(
			'post',
			(string) $post->ID,
			$canonical,
			static function () use ( $generator, $post ) {
				return $generator->generate( $post );
			}
		);
		$this->response->markdown( $result['markdown'], $negotiated, $canonical, $result['hit'] );
	}
}
