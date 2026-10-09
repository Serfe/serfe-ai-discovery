<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Administrator editor for the two public-root discovery files. */
final class Discovery_Files_Admin {
	const ACTION = 'serfe_ai_discovery_file_action';

	/** Register one capability- and nonce-protected form handler. */
	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/** Add a local copy button for validated manual-deployment text. */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_' . Settings::PAGE !== $hook ) {
			return;
		}
		wp_register_script( 'serfe-ai-discovery-files', false, array(), '1.0.10', true );
		wp_enqueue_script( 'serfe-ai-discovery-files' );
		wp_add_inline_script( 'serfe-ai-discovery-files', 'document.addEventListener("click", function(event) { if (!event.target.matches("[data-serfe-copy-file]")) { return; } var area = document.getElementById(event.target.getAttribute("data-serfe-copy-file")); if (area && navigator.clipboard) { navigator.clipboard.writeText(area.value); } });' );
	}

	/** Process a file action and return to the plugin's files tab. */
	public function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage discovery files.', 'serfe-ai-discovery' ) );
		}
		check_admin_referer( self::ACTION );
		$name = isset( $_POST['file'] ) && is_string( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) || ! in_array( $mode, array( 'publish', 'restore', 'template', 'validate' ), true ) || ( 'template' === $mode && 'llms.txt' !== $name ) ) {
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
				'message'   => __( 'Starter template inserted. Review and edit it before publishing.', 'serfe-ai-discovery' ),
				'candidate' => self::starter_template(),
			);
		} else {
			if ( 'validate' === $mode ) {
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
						: __( 'The public file was written and checked.', 'serfe-ai-discovery' ),
					'warnings' => $result['warnings'],
				);
				if ( 'validate' === $mode && strlen( $candidate ) <= Discovery_Files::MAX_LIMIT ) {
					$notice['candidate'] = $candidate;
				}
			}
		}
		set_transient( self::notice_key(), $notice, 300 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Settings::PAGE,
					'tab'  => 'files',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Render independent forms and current file states. */
	public function render_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( self::notice_key() );
		delete_transient( self::notice_key() );
		echo '<h2>' . esc_html__( 'Why these files matter', 'serfe-ai-discovery' ) . '</h2>';
		echo '<p>' . esc_html__( 'robots.txt communicates crawler access rules and can point crawlers to the XML sitemap. llms.txt is an optional public guide containing curated context and links. A sitemap maps canonical public URLs. None of these signals guarantees crawling, indexing, ranking, traffic, or AI citations.', 'serfe-ai-discovery' ) . '</p>';
		$scanner = self::agent_ready_target( home_url( '/' ), wp_get_environment_type() );
		printf( '<p><a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( $scanner['url'] ), esc_html__( 'Check this site with Is It Agent-Ready', 'serfe-ai-discovery' ) );
		if ( ! $scanner['public'] ) {
			echo '<p class="description">' . esc_html__( 'External scanners cannot access local or private environments. Open the scanner and test a public URL after deployment.', 'serfe-ai-discovery' ) . '</p>';
		}
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About robots.txt', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Defines crawler access rules and can declare the XML sitemap location.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Clarifies which public areas are intended for crawling and where public URLs can be discovered.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: Rules must reflect your organization’s policy. Allowing or blocking a crawler does not guarantee crawling, training, indexing, or citation.', 'serfe-ai-discovery' ) . '</p></details>';
		echo '<details style="margin: 16px 0;"><summary>' . esc_html__( 'About llms.txt', 'serfe-ai-discovery' ) . '</summary>';
		echo '<p>' . esc_html__( 'What it does: Provides an optional human-readable Markdown overview and curated links to relevant public content.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Why it helps: Makes approved information easier for AI systems and technical tools to identify and interpret.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'Important: Include only current, public, canonical URLs. Never include private areas, customer pages, admin URLs, credentials, unsupported claims, or outdated offers.', 'serfe-ai-discovery' ) . '</p>';
		echo '<p>' . esc_html__( 'This file complements the website, sitemap, structured content, and crawler policy; it does not replace them.', 'serfe-ai-discovery' ) . '</p></details>';
		$this->render_file( 'robots.txt', $notice );
		$this->render_file( 'llms.txt', $notice );
	}

	/** Build a scanner link without sending a local or private host. */
	public static function agent_ready_target( $home, $environment ) {
		$root  = 'https://isitagentready.com/';
		$parts = wp_parse_url( $home );
		$host  = is_array( $parts ) && isset( $parts['host'] ) ? rtrim( strtolower( trim( $parts['host'], '[]' ) ), '.' ) : '';
		$is_ip = false !== filter_var( $host, FILTER_VALIDATE_IP );
		if ( 'local' === $environment || '' === $host || 'localhost' === $host
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
		$conflict = $known !== $state['checksum'];
		if ( $conflict ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This file is unmanaged, missing, or changed outside the plugin. Review its current state before taking control.', 'serfe-ai-discovery' ) . '</p></div>';
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
		$editor = is_array( $notice ) && isset( $notice['file'], $notice['candidate'] ) && $name === $notice['file'] ? $notice['candidate'] : $state['content'];
		if ( 'llms.txt' === $name ) {
			echo '<p>' . esc_html__( 'Start with the site name and a concise description. Group current public canonical links under useful headings such as Products, Services, Resources, Support, Documentation, and Legal. Review this guide after important content or URL changes.', 'serfe-ai-discovery' ) . '</p>';
		}
		printf( '<p><strong>%s</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_html__( 'Public URL:', 'serfe-ai-discovery' ), esc_url( home_url( '/' . $name ) ), esc_html( home_url( '/' . $name ) ) );
		if ( isset( $state['meta']['published_at'], $state['meta']['user_id'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( sprintf( /* translators: 1: date and time, 2: administrator ID. */ __( 'Last published: %1$s by administrator %2$d.', 'serfe-ai-discovery' ), $state['meta']['published_at'], $state['meta']['user_id'] ) ) );
		}
		$this->render_warnings( $name, $state['content'] );
		printf( '<form action="%s" method="post">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION );
		printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="file" value="%s"><input type="hidden" name="checksum" value="%s">', esc_attr( self::ACTION ), esc_attr( $name ), esc_attr( $state['checksum'] ) );
		printf( '<p><label for="serfe-file-%s"><strong>%s</strong></label></p><textarea id="serfe-file-%s" name="content" rows="15" class="large-text code">%s</textarea>', esc_attr( $name ), esc_html__( 'Editor content', 'serfe-ai-discovery' ), esc_attr( $name ), esc_textarea( $editor ) );
		if ( $conflict ) {
			echo '<p><label><input type="checkbox" name="takeover" value="1"> ' . esc_html__( 'I reviewed the current public file and confirm that this plugin may take control of it.', 'serfe-ai-discovery' ) . '</label></p>';
		}
		echo '<p><button type="submit" name="mode" value="publish" class="button button-primary">' . esc_html__( 'Publish file', 'serfe-ai-discovery' ) . '</button> ';
		echo '<button type="submit" name="mode" value="validate" class="button">' . esc_html__( 'Validate public file', 'serfe-ai-discovery' ) . '</button> ';
		if ( 'llms.txt' === $name ) {
			echo '<button type="submit" name="mode" value="template" class="button">' . esc_html__( 'Insert starter template', 'serfe-ai-discovery' ) . '</button> ';
		}
		if ( is_string( $state['backup'] ) ) {
			echo '<button type="submit" name="mode" value="restore" class="button">' . esc_html__( 'Restore previous version', 'serfe-ai-discovery' ) . '</button>';
		}
		echo '</p></form>';
		if ( $conflict || ( is_array( $notice ) && isset( $notice['file'], $notice['code'] ) && $name === $notice['file'] ) ) {
			$this->render_review( $name, $state, $editor );
		}
		echo '</section>';
	}

	/** Show all versions needed to make an informed takeover or manual deployment. */
	private function render_review( $name, $state, $editor ) {
		echo '<details><summary>' . esc_html__( 'Review current file, editor text, and previous backup', 'serfe-ai-discovery' ) . '</summary>';
		$validated = Discovery_Files::normalize_content( $editor );
		foreach ( array(
			__( 'Current public file', 'serfe-ai-discovery' ) => $state['content'],
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

	/** Insert only reviewable placeholders; no site URLs or claims are generated. */
	private static function starter_template() {
		return "# [Site or organization name]\n\n[Brief public description.]\n\n## Products and services\n\n- [Public page title]([canonical public URL])\n\n## Support and resources\n\n- [Public page title]([canonical public URL])\n";
	}

	/** Isolate admin notices per administrator without putting content in URLs. */
	private static function notice_key() {
		return 'serfe_ai_discovery_file_notice_' . get_current_user_id();
	}
}
