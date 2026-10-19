<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrator editor for the two public-root discovery files. */
final class Discovery_Files_Admin {
	const ACTION                   = 'serfe_ai_discovery_file_action';
	const AJAX_TEMPLATE_ACTION     = 'serfe_ai_discovery_llms_template';
	const TEMPLATE_ITEMS_PER_GROUP = 80;
	const LLMS_CRON_HOOK           = 'serfe_ai_discovery_generate_llms';
	const LLMS_CRON_STATUS_KEY     = 'serfe_ai_discovery_llms_generation_status';

	/** Register one capability- and nonce-protected form handler. */
	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
		add_action( 'wp_ajax_' . self::AJAX_TEMPLATE_ACTION, array( $this, 'handle_llms_template_ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( self::LLMS_CRON_HOOK, array( $this, 'run_scheduled_llms_generation' ) );
	}

	/** Load the local controls for manually copying robots.txt content signals. */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_' . Settings::PAGE !== $hook ) {
			return;
		}
		$style_path  = SERFE_AI_DISCOVERY_PATH . 'assets/admin-files.css';
		$script_path = SERFE_AI_DISCOVERY_PATH . 'assets/admin-files.js';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- These fixed bundled assets need cache busting during an in-place plugin update.
		$style_version = SERFE_AI_DISCOVERY_VERSION . '.' . filemtime( $style_path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- These fixed bundled assets need cache busting during an in-place plugin update.
		$script_version = SERFE_AI_DISCOVERY_VERSION . '.' . filemtime( $script_path );
		wp_enqueue_style( 'serfe-ai-discovery-files', SERFE_AI_DISCOVERY_URL . 'assets/admin-files.css', array(), $style_version );
		wp_enqueue_script( 'serfe-ai-discovery-files', SERFE_AI_DISCOVERY_URL . 'assets/admin-files.js', array(), $script_version, true );
		wp_localize_script(
			'serfe-ai-discovery-files',
			'serfeAiDiscoveryFiles',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'templateNonce' => wp_create_nonce( self::AJAX_TEMPLATE_ACTION ),
				'generating'    => __( 'Generating template…', 'serfe-ai-discovery' ),
				'inserted'      => __( 'Template inserted. Review it before publishing.', 'serfe-ai-discovery' ),
				'failed'        => __( 'The template could not be generated. Try again or reload the page.', 'serfe-ai-discovery' ),
			)
		);
	}

	/** Return a generated llms.txt draft without submitting or publishing the editor form. */
	public function handle_llms_template_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to generate this template.', 'serfe-ai-discovery' ) ), 403 );
		}
		check_ajax_referer( self::AJAX_TEMPLATE_ACTION, 'nonce' );
		wp_send_json_success( array( 'content' => self::starter_template() ) );
	}

	/** Process a file action and return to the plugin's files tab. */
	public function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage discovery files.', 'serfe-ai-discovery' ) );
		}
		check_admin_referer( self::ACTION );
		$name = isset( $_POST['file'] ) && is_string( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) || ! in_array( $mode, array( 'publish', 'restore', 'template', 'validate', 'release', 'enable-signals', 'schedule', 'unschedule' ), true ) || ( 'template' === $mode && 'llms.txt' !== $name ) || ( in_array( $mode, array( 'schedule', 'unschedule' ), true ) && 'llms.txt' !== $name ) || ( 'enable-signals' === $mode && 'robots.txt' !== $name ) ) {
			wp_die( esc_html__( 'Invalid discovery file action.', 'serfe-ai-discovery' ) );
		}
		// Preserve approved plain text exactly; Discovery_Files::normalize_content() validates it before publication.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizing would change robots.txt or llms.txt policy content.
		$candidate = isset( $_POST['content'] ) && is_string( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$expected  = isset( $_POST['checksum'] ) && is_string( $_POST['checksum'] ) ? sanitize_text_field( wp_unslash( $_POST['checksum'] ) ) : '';
		$takeover  = isset( $_POST['takeover'] ) && is_string( $_POST['takeover'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['takeover'] ) );
		if ( 'restore' === $mode ) {
			$state = Discovery_Files::inspect( $name );
			if ( is_array( $state ) && is_string( $state['backup'] ) ) {
				$candidate = $state['backup'];
			}
		}
		if ( 'template' === $mode ) {
			$notice = array(
				'file'      => $name,
				'type'      => 'info',
				'message'   => __( 'Starter template inserted into the editor only. Review it and select Publish file when you are ready to save it.', 'serfe-ai-discovery' ),
				'candidate' => self::starter_template(),
			);
		} else {
			if ( 'schedule' === $mode ) {
				$result = $this->schedule_llms_generation();
			} elseif ( 'unschedule' === $mode ) {
				$result = $this->unschedule_llms_generation();
			} elseif ( 'enable-signals' === $mode ) {
				$result = Discovery_Files::enable_virtual_content_signals();
			} elseif ( 'release' === $mode ) {
				$result = Discovery_Files::release_control( $name, $expected );
			} elseif ( 'validate' === $mode ) {
				$result = Discovery_Files::validate_current( $name );
			} elseif ( 'restore' === $mode ) {
				$result = Discovery_Files::restore( $name, $takeover, $expected );
			} else {
				$result = Discovery_Files::publish( $name, $candidate, $takeover, $expected );
			}
			if ( is_wp_error( $result ) ) {
				$notice = array(
					'file'      => $name,
					'type'      => 'error',
					'message'   => $result->get_error_message(),
					'candidate' => strlen( $candidate ) <= Discovery_Files::MAX_LIMIT ? $candidate : '',
					'code'      => $result->get_error_code(),
				);
			} else {
				$notice = array(
					'file'     => $name,
					'type'     => $result['verified'] && empty( $result['warnings'] ) ? 'success' : 'warning',
					'message'  => 'validate' === $mode
						? __( 'The current public file was checked.', 'serfe-ai-discovery' )
					: ( 'schedule' === $mode
						? __( 'The full llms.txt generation was scheduled.', 'serfe-ai-discovery' )
						: ( 'unschedule' === $mode
							? __( 'The scheduled llms.txt generation was cancelled.', 'serfe-ai-discovery' )
						: ( 'enable-signals' === $mode
						? __( 'Content Signals will be added to the virtual robots.txt response.', 'serfe-ai-discovery' )
						: ( 'release' === $mode
							? __( 'Serfe no longer manages this file. Its current contents were not changed.', 'serfe-ai-discovery' )
							: __( 'The public file was written and checked.', 'serfe-ai-discovery' ) ) ) ) ),
					'warnings' => $result['warnings'],
				);
				if ( 'validate' === $mode ) {
					if ( strlen( $candidate ) <= Discovery_Files::MAX_LIMIT ) {
						$notice['candidate'] = $candidate;
					}
					$notice['virtual']  = ! empty( $result['virtual'] );
					$notice['checksum'] = isset( $result['checksum'] ) ? $result['checksum'] : '';
					if ( ! empty( $result['virtual'] ) ) {
						$notice['public_content'] = $result['content'];
					}
				}
			}
		}
		$target_tab = 'robots.txt' === $name ? 'robots' : 'llms';
		set_transient( self::notice_key(), $notice, 300 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Settings::PAGE,
					'tab'  => $target_tab,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Render the dedicated robots.txt tab. */
	public function render_robots_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( self::notice_key() );
		delete_transient( self::notice_key() );
		echo '<h2>' . esc_html__( 'Why robots.txt matters', 'serfe-ai-discovery' ) . '</h2>';
		echo '<p>' . esc_html__( 'robots.txt communicates crawler access rules and can point crawlers to the XML sitemap. Access rules must reflect your organization’s policy. Allowing or blocking a crawler does not guarantee crawling, training, indexing, or citation.', 'serfe-ai-discovery' ) . '</p>';
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About robots.txt', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Defines crawler access rules and can declare the XML sitemap location.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Clarifies which public areas are intended for crawling and where public URLs can be discovered.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: Rules must reflect your organization’s policy. Allowing or blocking a crawler does not guarantee crawling, training, indexing, or citation.', 'serfe-ai-discovery' ) . '</p></details>';
		$this->render_file( 'robots.txt', $notice );
	}

	/** Render the dedicated llms.txt tab. */
	public function render_llms_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( self::notice_key() );
		delete_transient( self::notice_key() );
		echo '<h2>' . esc_html__( 'Why llms.txt matters', 'serfe-ai-discovery' ) . '</h2>';
		echo '<p>' . esc_html__( 'llms.txt is an optional public guide containing curated context and links to relevant public content. Include only current, public, canonical URLs. It complements the website, sitemap, structured content, and crawler policy; it does not replace them.', 'serfe-ai-discovery' ) . '</p>';
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About llms.txt', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Provides an optional human-readable Markdown overview and curated links to relevant public content.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Makes approved information easier for AI systems and technical tools to identify and interpret.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: Include only current, public, canonical URLs. Never include private areas, customer pages, admin URLs, credentials, unsupported claims, or outdated offers.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'This file complements the website, sitemap, structured content, and crawler policy; it does not replace them.', 'serfe-ai-discovery' ) . ' ' . esc_html__( 'Read more about it in', 'serfe-ai-discovery' ) . ' <a href="https://llmstxt.org/" target="_blank" rel="noopener noreferrer">llmstxt.org</a>.</p></details>';
		$this->render_file( 'llms.txt', $notice );
	}

	/** Backward compatible renderer for file tabs. */
	public function render_tab( $file = 'robots' ) {
		if ( 'llms' === $file || 'llms.txt' === $file ) {
			$this->render_llms_tab();
		} else {
			$this->render_robots_tab();
		}
	}

	/** Build a scanner link without sending a local or private host. */
	public static function agent_ready_target( $home, $environment ) {
		$root  = 'https://isitagentready.com/';
		$parts = wp_parse_url( $home );
		$host  = is_array( $parts ) && isset( $parts['host'] ) ? rtrim( strtolower( trim( $parts['host'], '[]' ) ), '.' ) : '';
		$is_ip = false !== filter_var( $host, FILTER_VALIDATE_IP );
		if ( 'local' === $environment || '' === $host || 'localhost' === $host || 'playground.wordpress.net' === $host || substr( $host, -25 ) === '.playground.wordpress.net'
			|| preg_match( '/\.(?:localhost|local|test|invalid|internal|example|lan|home)$/', $host )
			|| ( ! $is_ip && preg_match( '/^[0-9.]+$/', $host ) )
			|| ( $is_ip && false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) )
			|| ( ! $is_ip && ( false === filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) || false === strpos( $host, '.' ) ) ) ) {
			return array(
				'url'    => $root,
				'public' => false,
			);
		}
		return array(
			'url'    => $root . rawurlencode( $host ),
			'public' => true,
		);
	}

	/** Show the current file, its last backup, and a protected publication form. */
	private function render_file( $name, $notice ) {
		$state = Discovery_Files::inspect( $name );
		$error = is_wp_error( $state );
		$title = 'robots.txt' === $name ? 'robots.txt' : 'llms.txt';
		echo '<section class="card" style="max-width: 1000px; padding: 16px 24px; margin: 20px 0;"><h2>' . esc_html( $title ) . '</h2>';
		if ( $error ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $state->get_error_message() ) . '</p></div>';
			$state = array(
				'exists'   => false,
				'content'  => '',
				'checksum' => '',
				'meta'     => array(),
				'backup'   => false,
			);
		}
		$known    = isset( $state['meta']['checksum'] ) ? $state['meta']['checksum'] : '';
		$virtual  = ! $state['exists'] && is_array( $notice ) && isset( $notice['file'] ) && $name === $notice['file'] && ! empty( $notice['virtual'] );
		$conflict = $known !== $state['checksum'] || $virtual;
		if ( ! $state['exists'] && empty( $state['virtual'] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No physical file exists at the public root. A virtual public response might still be served. Select Validate public file to inspect it before publishing a physical file.', 'serfe-ai-discovery' ) . '</p></div>';
		} elseif ( ! $state['exists'] ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This Multisite site stores and serves this discovery file virtually, without writing to the shared filesystem.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		if ( $conflict ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This public response is unmanaged or changed outside the plugin. Review its current content before taking control.', 'serfe-ai-discovery' ) . '</p></div>';
		}
		if ( is_array( $notice ) && isset( $notice['file'] ) && $name === $notice['file'] ) {
			$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'success', 'warning', 'error', 'info' ), true ) ? $notice['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $notice['message'] ) . '</p></div>';
			if ( isset( $notice['warnings'] ) && is_array( $notice['warnings'] ) ) {
				foreach ( $notice['warnings'] as $warning ) {
					echo '<div class="notice notice-warning inline"><p>' . esc_html( $warning ) . '</p></div>';
				}
			}
		}
		$editor        = is_array( $notice ) && isset( $notice['file'], $notice['candidate'] ) && $name === $notice['file'] ? $notice['candidate'] : $state['content'];
		$display_state = $state;

		if ( $virtual ) {
			$display_state['content']  = isset( $notice['public_content'] ) ? $notice['public_content'] : $display_state['content'];
			$display_state['checksum'] = isset( $notice['checksum'] ) ? $notice['checksum'] : $display_state['checksum'];
			if ( '' === $editor ) {
				$editor = $display_state['content'];
			}
		}
		if ( 'robots.txt' === $name ) {
			$this->render_virtual_content_signals_activation();
			$this->render_content_signals();
		}
		$managed = '' !== $known && $known === $state['checksum'];
		echo '<section class="serfe-file-editor-introduction"><h3>' . esc_html__( 'Advanced file editor', 'serfe-ai-discovery' ) . '</h3>';
		echo '<p>' . esc_html__( 'Open this editor when you want Serfe to manage and edit the file manually. Review the current public response before activating it.', 'serfe-ai-discovery' ) . '</p></section>';
		printf( '<details class="serfe-file-advanced"%s><summary>%s</summary>', $managed ? ' open' : '', esc_html__( 'Open advanced file editor', 'serfe-ai-discovery' ) );
		if ( 'llms.txt' === $name ) {
			echo '<p>' . esc_html__( 'Start with the site name and a concise description. Group current public canonical links under useful headings such as Products, Services, Resources, Support, Documentation, and Legal. Review this guide after important content or URL changes.', 'serfe-ai-discovery' ) . '</p>';
		}
		printf( '<p><strong>%s</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_html__( 'If your file is present, you could see content here:', 'serfe-ai-discovery' ), esc_url( home_url( '/' . $name ) ), esc_html( home_url( '/' . $name ) ) );
		if ( isset( $state['meta']['published_at'], $state['meta']['user_id'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( sprintf( /* translators: 1: date and time, 2: administrator ID. */ __( 'Last published: %1$s by administrator %2$d.', 'serfe-ai-discovery' ), $state['meta']['published_at'], $state['meta']['user_id'] ) ) );
		}
		$this->render_warnings( $name, $display_state['content'] );
		printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION );
		printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="file" value="%s"><input type="hidden" name="checksum" value="%s">', esc_attr( self::ACTION ), esc_attr( $name ), esc_attr( $display_state['checksum'] ) );

		printf( '<p><label for="serfe-file-%s"><strong>%s</strong></label></p><textarea id="serfe-file-%s" name="content" rows="15" class="large-text code">%s</textarea>', esc_attr( $name ), esc_html__( 'Editor content', 'serfe-ai-discovery' ), esc_attr( $name ), esc_textarea( $editor ) );
		printf( '<p><label><input type="checkbox" name="takeover" value="1" required %s> %s</label></p>', checked( ! $conflict, true, false ), esc_html__( 'I reviewed the current public file and confirm that this plugin may take control of it.', 'serfe-ai-discovery' ) );
		if ( 'llms.txt' === $name ) {
			echo '<div class="serfe-template-status" data-serfe-template-feedback aria-live="polite" hidden></div>';
		}
		echo '<p><button type="submit" name="mode" value="publish" class="button button-primary">' . esc_html__( 'Publish file', 'serfe-ai-discovery' ) . '</button> ';
		echo '<button type="submit" name="mode" value="validate" class="button" formnovalidate>' . esc_html__( 'Reset and load current', 'serfe-ai-discovery' ) . '</button> ';
		if ( 'llms.txt' === $name ) {
			echo '<button type="submit" name="mode" value="template" class="button serfe-insert-template" formnovalidate data-serfe-insert-template data-serfe-template-target="serfe-file-llms.txt"><span data-serfe-template-label>' . esc_html__( 'Insert starter template', 'serfe-ai-discovery' ) . '</span><span class="screen-reader-text" data-serfe-template-status aria-live="polite"></span></button> ';
		}
		if ( is_string( $state['backup'] ) ) {
			echo '<button type="submit" name="mode" value="restore" class="button" formnovalidate>' . esc_html__( 'Restore previous version', 'serfe-ai-discovery' ) . '</button>';
		}
		if ( $managed && empty( $state['virtual'] ) ) {
			echo ' <button type="submit" name="mode" value="release" class="button" formnovalidate data-serfe-release-control>' . esc_html__( 'Release control', 'serfe-ai-discovery' ) . '</button>';
			echo '<br><span class="description">' . esc_html__( 'This keeps the current physical file unchanged but removes Serfe management data and backups. A plugin that requires a virtual robots.txt response may still require you to remove the physical file through your host.', 'serfe-ai-discovery' ) . '</span>';
		}
		echo '</p></form>';
		if ( 'llms.txt' === $name ) {
			$this->render_scheduled_llms_generation( $display_state );
		}
		if ( $conflict || ( is_array( $notice ) && isset( $notice['file'], $notice['code'] ) && $name === $notice['file'] ) ) {
			$this->render_review( $name, $display_state, $editor );
		}
		echo '</details></section>';
	}

	/** Schedule a full llms.txt regeneration without blocking this admin request. */
	private function schedule_llms_generation() {
		$state = Discovery_Files::inspect( 'llms.txt' );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( '' !== trim( $state['content'] ) ) {
			return new \WP_Error( 'llms_not_empty', __( 'Scheduled generation only creates llms.txt when it is empty or missing. It will not overwrite existing content.', 'serfe-ai-discovery' ) );
		}
		$site_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$args    = array( $site_id );
		if ( wp_next_scheduled( self::LLMS_CRON_HOOK, $args ) ) {
			return array(
				'verified' => true,
				'warnings' => array( __( 'A full llms.txt generation is already scheduled.', 'serfe-ai-discovery' ) ),
			);
		}
		if ( ! wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::LLMS_CRON_HOOK, $args ) ) {
			return new \WP_Error( 'schedule_failed', __( 'The llms.txt generation could not be scheduled.', 'serfe-ai-discovery' ) );
		}
		return array(
			'verified' => true,
			'warnings' => array(),
		);
	}

	/** Cancel the one pending full llms.txt generation for this site. */
	private function unschedule_llms_generation() {
		$site_id      = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$args         = array( $site_id );
		$scheduled_at = wp_next_scheduled( self::LLMS_CRON_HOOK, $args );
		if ( ! $scheduled_at ) {
			return new \WP_Error( 'not_scheduled', __( 'No full llms.txt generation is currently scheduled for this site.', 'serfe-ai-discovery' ) );
		}
		if ( ! wp_unschedule_event( $scheduled_at, self::LLMS_CRON_HOOK, $args ) ) {
			return new \WP_Error( 'unschedule_failed', __( 'The scheduled llms.txt generation could not be cancelled.', 'serfe-ai-discovery' ) );
		}
		delete_option( self::LLMS_CRON_STATUS_KEY );
		return array(
			'verified' => true,
			'warnings' => array(),
		);
	}

	/** Generate every eligible item only if llms.txt remains empty when the action runs. */
	public function run_scheduled_llms_generation( $site_id = 0 ) {
		$switched = false;
		if ( function_exists( 'is_multisite' ) && is_multisite() && $site_id > 0 && function_exists( 'switch_to_blog' ) && get_current_blog_id() !== (int) $site_id ) {
			$switched = switch_to_blog( (int) $site_id );
		}
		$result = Discovery_Files::generate_llms_if_empty( self::starter_template( true ) );
		$status = is_wp_error( $result )
			? array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			)
			: ( ! empty( $result['skipped'] )
				? array(
					'type'    => 'info',
					'message' => __( 'Scheduled llms.txt generation was skipped because the file already contains content.', 'serfe-ai-discovery' ),
				)
			: array(
				'type'    => 'success',
				'message' => __( 'The scheduled llms.txt generation completed.', 'serfe-ai-discovery' ),
			) );
		update_option( self::LLMS_CRON_STATUS_KEY, $status, false );
		if ( $switched && function_exists( 'restore_current_blog' ) ) {
			restore_current_blog();
		}
	}

	/** Explain and offer the non-blocking full llms.txt generation after the editor. */
	private function render_scheduled_llms_generation( $state ) {
		$status = get_option( self::LLMS_CRON_STATUS_KEY, false );
		echo '<section class="serfe-scheduled-llms">';
		echo '<h3>' . esc_html__( 'Generate full llms.txt asynchronously', 'serfe-ai-discovery' ) . '</h3>';
		echo '<p>' . esc_html__( 'Recommended for blogs or sites with more than 100 public pages. This creates a full inventory of eligible public content in a deferred WordPress action.', 'serfe-ai-discovery' ) . '</p>';
		if ( is_array( $status ) && isset( $status['type'], $status['message'] ) ) {
			$type = in_array( $status['type'], array( 'success', 'error' ), true ) ? $status['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $status['message'] ) . '</p></div>';
		}
		$empty        = is_array( $state ) && isset( $state['content'] ) && '' === trim( $state['content'] );
		$site_id      = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		$args         = array( $site_id );
		$scheduled_at = wp_next_scheduled( self::LLMS_CRON_HOOK, $args );
		if ( $scheduled_at ) {
			echo '<p class="description">' . esc_html__( 'A full llms.txt generation is currently scheduled for this site.', 'serfe-ai-discovery' ) . '</p>';
			printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::ACTION );
			printf( '<input type="hidden" name="action" value="%1$s"><input type="hidden" name="file" value="llms.txt">', esc_attr( self::ACTION ) );
			echo '<button type="submit" name="mode" value="unschedule" class="button">' . esc_html__( 'Cancel scheduled generation', 'serfe-ai-discovery' ) . '</button></form>';
		} elseif ( $empty ) {
			printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::ACTION );
			printf( '<input type="hidden" name="action" value="%1$s"><input type="hidden" name="file" value="llms.txt">', esc_attr( self::ACTION ) );
			echo '<button type="submit" name="mode" value="schedule" class="button">' . esc_html__( 'Schedule full llms.txt generation', 'serfe-ai-discovery' ) . '</button></form>';
		} else {
			echo '<p class="description">' . esc_html__( 'Scheduled generation only creates llms.txt when it is empty or missing. It will not overwrite existing content. Clear the editor and publish an empty file first if you want to use this option.', 'serfe-ai-discovery' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'The action checks again when it runs. If llms.txt has content by then, it is skipped without changing the file.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'The action runs later when WP-Cron is correctly configured. WP-Cron depends on site visits unless your host triggers it on a schedule.', 'serfe-ai-discovery' ) . ' <a href="https://developer.wordpress.org/plugins/cron/#what-is-wp-cron" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn how WP-Cron works', 'serfe-ai-discovery' ) . '</a>.</p>';
		echo '</section>';
	}

	/** Offer automatic Content Signals only when no physical robots.txt is present. */
	private function render_virtual_content_signals_activation() {
		$physical = Discovery_Files::physical_file_exists( 'robots.txt' );
		echo '<section class="serfe-content-signals serfe-virtual-content-signals">';
		echo '<h3>' . esc_html__( 'Add AI Content Signals to your robots.txt', 'serfe-ai-discovery' ) . '</h3>';
		if ( is_wp_error( $physical ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $physical->get_error_message() ) . '</p></div>';
		} elseif ( $physical ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'A physical robots.txt file exists. Use Advanced file management to edit it, or edit it in the plugin that generates it. Content Signals cannot be added dynamically while that file is served.', 'serfe-ai-discovery' ) . '</p></div>';
		} elseif ( Discovery_Files::content_signals_enabled() ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Content Signals are enabled for the virtual robots.txt response.', 'serfe-ai-discovery' ) . '</p></div>';
		} else {
			echo '<p>' . esc_html__( 'Validate the current virtual robots.txt response, then append the standard Serfe Content Signals block through WordPress without creating a physical file.', 'serfe-ai-discovery' ) . '</p>';
			printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::ACTION );
			printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="file" value="robots.txt">', esc_attr( self::ACTION ) );
			echo '<button type="submit" name="mode" value="enable-signals" class="button button-primary">' . esc_html__( 'Add AI Content Signals to your robots.txt', 'serfe-ai-discovery' ) . '</button></form>';
		}
		echo '</section>';
	}

	/** Render Content Signals for manual use in physical or virtual robots.txt editors. */
	private function render_content_signals() {
		$base_block = Discovery_Files::content_signals_block();
		$path_hint  = self::suggested_section_path();
		$id         = 'serfe-robots-content-signals';
		$path_id    = 'serfe-robots-content-signals-path';

		echo '<section class="serfe-content-signals">';
		echo '<h3>' . esc_html__( 'Update signals manually in your current robots.txt', 'serfe-ai-discovery' ) . '</h3>';
		echo '<p>' . esc_html__( 'Copy this block into the robots.txt editor currently serving your site. This works with virtual files managed by Rank Math, Yoast SEO, and other SEO plugins without creating a physical file.', 'serfe-ai-discovery' ) . '</p>';
		printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br><input id="%1$s" type="text" class="regular-text code" value="%3$s" placeholder="/blog/" data-serfe-section-path></p>', esc_attr( $path_id ), esc_html__( 'Section path', 'serfe-ai-discovery' ), esc_attr( $path_hint ) );
		echo '<p class="description">' . esc_html__( 'Enter one site path, such as /blog/. The rule applies to that section and URLs below it. To protect more sections, copy the generated section block and repeat it manually with each path.', 'serfe-ai-discovery' ) . '</p>';
		printf( '<p><button type="button" class="button" data-serfe-protect-section data-serfe-content-signals="%s" data-serfe-section-input="%s"><span class="serfe-protect-icon" aria-hidden="true">+</span> <span data-serfe-protect-label>%s</span><span class="screen-reader-text" data-serfe-section-status aria-live="polite"></span></button></p>', esc_attr( $id ), esc_attr( $path_id ), esc_html__( 'Protect section from AI-Train', 'serfe-ai-discovery' ) );
		printf( '<textarea id="%1$s" class="large-text code" rows="13" readonly data-serfe-base="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $base_block ), esc_textarea( $base_block ) );
		echo '<p><button type="button" class="button button-primary" data-serfe-copy-file="' . esc_attr( $id ) . '"><span class="serfe-copy-icon" aria-hidden="true">⧉</span> <span data-serfe-copy-label>' . esc_html__( 'Copy to clipboard', 'serfe-ai-discovery' ) . '</span><span class="screen-reader-text" data-serfe-copy-status aria-live="polite"></span></button></p>';
		echo '<p><a href="https://contentsignals.org/?utm_source=serfe-ai-discovery" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about Content Signals options', 'serfe-ai-discovery' ) . '</a></p>';
		$this->render_seo_editor_guidance();
		echo '</section>';
	}

	/** Describe detected SEO editors that can serve robots.txt virtually. */
	private function render_seo_editor_guidance() {
		$seo_links = array();
		$seo_names = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$seo_links[] = sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( 'https://yoast.com/help/how-to-edit-robots-txt-through-yoast-seo/' ), esc_html__( 'Yoast SEO robots.txt guide', 'serfe-ai-discovery' ) );
			$seo_names[] = 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$seo_links[] = sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( 'https://rankmath.com/kb/how-to-edit-robots-txt-with-rank-math/' ), esc_html__( 'Rank Math robots.txt guide', 'serfe-ai-discovery' ) );
			$seo_names[] = 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$seo_links[] = sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( 'https://aioseo.com/docs/using-the-robots-txt-tool-in-all-in-one-seo/' ), esc_html__( 'AIOSEO robots.txt guide', 'serfe-ai-discovery' ) );
			$seo_names[] = 'All in One SEO';
		}
		if ( ! empty( $seo_names ) ) {
			/* translators: %1$s: Name of SEO plugin(s) (e.g. Yoast SEO, Rank Math). */
			echo '<p class="description">' . sprintf( esc_html__( 'We detected %1$s. Paste the generated block in its robots.txt editor to keep that plugin in control of the virtual response.', 'serfe-ai-discovery' ), esc_html( implode( ' and ', $seo_names ) ) ) . '<br>' . wp_kses_post( implode( ' &middot; ', $seo_links ) ) . '</p>';
		}
	}

	/** Show all versions needed to make an informed takeover or manual deployment. */
	private function render_review( $name, $state, $editor ) {
		echo '<details><summary>' . esc_html__( 'Review current file, editor text, and previous backup', 'serfe-ai-discovery' ) . '</summary>';
		$validated = Discovery_Files::normalize_content( $editor );
		foreach ( array(
			__( 'Current public response', 'serfe-ai-discovery' ) => $state['content'],
			__( 'Editor content', 'serfe-ai-discovery' )  => $editor,
			__( 'Previous backup', 'serfe-ai-discovery' ) => is_string( $state['backup'] ) ? $state['backup'] : __( 'No backup is available.', 'serfe-ai-discovery' ),
		) as $label => $content ) {
			echo '<h3>' . esc_html( $label ) . '</h3><textarea readonly rows="8" class="large-text code">' . esc_textarea( $content ) . '</textarea>';
		}
		if ( ! is_wp_error( $validated ) ) {
			$id = 'serfe-manual-' . str_replace( '.', '-', $name );
			echo '<h3>' . esc_html__( 'Validated content for manual deployment', 'serfe-ai-discovery' ) . '</h3>';
			printf( '<textarea id="%s" readonly rows="8" class="large-text code">%s</textarea><p><button type="button" class="button" data-serfe-copy-file="%s">%s</button></p>', esc_attr( $id ), esc_textarea( $validated ), esc_attr( $id ), esc_html__( 'Copy validated content', 'serfe-ai-discovery' ) );
		}
		echo '</details>';
	}

	/** Explain likely robots.txt mistakes without changing administrator policy. */
	private function render_warnings( $name, $content ) {
		if ( 'robots.txt' !== $name || '' === $content ) {
			return;
		}
		if ( ! preg_match( '/^\s*Sitemap\s*:/im', $content ) ) {
			echo '<p class="notice notice-warning inline">' . esc_html__( 'No sitemap is declared in the current robots.txt file.', 'serfe-ai-discovery' ) . '</p>';
		}
		if ( preg_match( '/^\s*Disallow\s*:\s*\/\s*$/im', $content ) ) {
			echo '<p class="notice notice-warning inline">' . esc_html__( 'A rule appears to block the entire public site. Review whether this matches your crawler policy.', 'serfe-ai-discovery' ) . '</p>';
		}
	}

	/** Build a reviewable llms.txt draft from current, eligible public content. */
	private static function starter_template( $all_items = false ) {
		$site_name        = self::template_text( get_bloginfo( 'name' ) );
		$site_description = self::template_text( get_bloginfo( 'description' ) );
		$items_per_group  = $all_items ? -1 : self::TEMPLATE_ITEMS_PER_GROUP;
		$lines            = array(
			'# ' . ( '' !== $site_name ? $site_name : __( 'Website', 'serfe-ai-discovery' ) ),
			'',
			'' !== $site_description ? $site_description : __( 'Public information and resources from this website.', 'serfe-ai-discovery' ),
		);

		self::append_post_type_links( $lines, __( 'Pages', 'serfe-ai-discovery' ), 'page', $items_per_group );
		self::append_post_type_links( $lines, __( 'Posts', 'serfe-ai-discovery' ), 'post', $items_per_group );

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			$name = is_object( $post_type ) && isset( $post_type->name ) ? $post_type->name : '';
			if ( '' === $name || in_array( $name, array( 'post', 'page', 'attachment', 'product' ), true ) ) {
				continue;
			}
			$label = is_object( $post_type ) && isset( $post_type->labels->name ) ? $post_type->labels->name : $name;
			self::append_post_type_links( $lines, self::template_text( $label ), $name, $items_per_group );
		}

		self::append_woocommerce_links( $lines, $items_per_group );
		self::append_sitemap_links( $lines );

		return implode( "\n", $lines ) . "\n";
	}

	/** Append links from one public post type. */
	private static function append_post_type_links( &$lines, $heading, $post_type, $items_per_group ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $items_per_group,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return;
		}
		$links = array();
		foreach ( $posts as $post ) {
			$url = get_permalink( $post );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$links[] = self::markdown_link( get_the_title( $post ), $url );
		}
		if ( empty( $links ) ) {
			return;
		}
		$lines[] = '';
		$lines[] = '## ' . $heading;
		$lines[] = '';
		foreach ( array_unique( $links ) as $link ) {
			$lines[] = $link;
		}
	}

	/** Append the WooCommerce shop and its top-level product categories when available. */
	private static function append_woocommerce_links( &$lines, $items_per_group ) {
		if ( ! defined( 'WC_VERSION' ) || ! function_exists( 'wc_get_page_id' ) || ! function_exists( 'get_terms' ) ) {
			return;
		}
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			$shop_url = get_permalink( $shop_id );
			if ( is_string( $shop_url ) && '' !== $shop_url ) {
				$lines[] = '';
				$lines[] = '## ' . __( 'Shop', 'serfe-ai-discovery' );
				$lines[] = '';
				$lines[] = self::markdown_link( get_the_title( $shop_id ), $shop_url );
			}
		}
		self::append_post_type_links( $lines, __( 'Products', 'serfe-ai-discovery' ), 'product', $items_per_group );
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => false,
				'number'     => $items_per_group < 0 ? 0 : $items_per_group,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $categories ) || ! is_array( $categories ) || empty( $categories ) ) {
			return;
		}
		$links = array();
		foreach ( $categories as $category ) {
			$url = get_term_link( $category );
			if ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$links[] = self::markdown_link( isset( $category->name ) ? $category->name : '', $url );
		}
		if ( ! empty( $links ) ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Product categories', 'serfe-ai-discovery' );
			$lines[] = '';
			foreach ( array_unique( $links ) as $link ) {
				$lines[] = $link;
			}
		}
	}

	/** Add XML sitemap indexes exposed by the active SEO plugin. */
	private static function append_sitemap_links( &$lines ) {
		$sitemaps = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$sitemaps[ home_url( '/sitemap_index.xml' ) ] = __( 'Yoast SEO XML Sitemap', 'serfe-ai-discovery' );
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$sitemaps[ home_url( '/sitemap_index.xml' ) ] = __( 'Rank Math XML Sitemap', 'serfe-ai-discovery' );
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$sitemaps[ home_url( '/sitemap.xml' ) ] = __( 'All in One SEO XML Sitemap', 'serfe-ai-discovery' );
		}
		if ( empty( $sitemaps ) ) {
			return;
		}
		$lines[] = '';
		$lines[] = '## ' . __( 'XML Sitemaps', 'serfe-ai-discovery' );
		$lines[] = '';
		foreach ( $sitemaps as $url => $label ) {
			$lines[] = self::markdown_link( $label, $url );
		}
	}

	/** Normalize a site-provided label for a Markdown heading or link label. */
	private static function template_text( $value ) {
		$value = is_scalar( $value ) ? wp_strip_all_tags( (string) $value ) : '';
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/** Render one Markdown link from a public WordPress URL. */
	private static function markdown_link( $title, $url ) {
		$title = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), self::template_text( $title ) );
		$url   = str_replace( array( "\r", "\n", ' ' ), '', (string) $url );
		return '- [' . ( '' !== $title ? $title : __( 'Untitled', 'serfe-ai-discovery' ) ) . '](' . $url . ')';
	}

	/** Isolate admin notices per administrator without putting content in URLs. */
	private static function notice_key() {
		return 'serfe_ai_discovery_file_notice_' . get_current_user_id();
	}
	/** Return the configured Posts-page path as a helpful section-path suggestion. */
	private static function suggested_section_path() {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$page_for_posts = get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$post = get_post( $page_for_posts );
				if ( ! $post ) {
					return false;
				}
				$blog_path = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
				$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
				$home_path = '/' === $home_path ? '/' : trailingslashit( $home_path );
				if ( '/' !== $home_path ) {
					if ( 0 !== strpos( $blog_path, $home_path ) ) {
						return false;
					}
					$blog_path = '/' . ltrim( substr( $blog_path, strlen( $home_path ) ), '/' );
				}
				$blog_path = trailingslashit( $blog_path );
				if ( '/' === $blog_path ) {
					return false;
				}
				return $blog_path;
			}
		}
		return false;
	}
}
