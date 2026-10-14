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
		wp_register_script( 'serfe-ai-discovery-files', false, array(), SERFE_AI_DISCOVERY_VERSION, true );
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
		if ( ! in_array( $name, array( 'robots.txt', 'llms.txt' ), true ) || ! in_array( $mode, array( 'publish', 'restore', 'template', 'validate', 'activate_content_signals', 'remove_content_signals', 'protect_blog_ai_train' ), true ) || ( 'template' === $mode && 'llms.txt' !== $name ) ) {
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
			if ( in_array( $mode, array( 'activate_content_signals', 'protect_blog_ai_train', 'remove_content_signals' ), true ) ) {
				if ( 'activate_content_signals' === $mode ) {
					$candidate = trim( preg_replace( '/# START SERFE BLOCK.*?# END SERFE BLOCK\n?/s', '', $candidate ) ) . "\n\n" . self::content_signals_block( false ) . "\n";
				} elseif ( 'protect_blog_ai_train' === $mode ) {
					$candidate = trim( preg_replace( '/# START SERFE BLOCK.*?# END SERFE BLOCK\n?/s', '', $candidate ) ) . "\n\n" . self::content_signals_block( true ) . "\n";
				} elseif ( 'remove_content_signals' === $mode ) {
					$candidate = trim( preg_replace( '/# START SERFE BLOCK.*?# END SERFE BLOCK\n?/s', '', $candidate ) ) . "\n";
				}
				$takeover = true;
				// Proceed to publish after modifying candidate
				$mode = 'publish';
			}

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
		echo '<p>' . esc_html__( 'This file complements the website, sitemap, structured content, and crawler policy; it does not replace them.', 'serfe-ai-discovery' ) . '</p></details>';
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
		$virtual  = ! $state['exists'] && is_array( $notice ) && isset( $notice['file'] ) && $name === $notice['file'] && ! empty( $notice['virtual'] );
		$conflict = $known !== $state['checksum'] || $virtual;
		if ( ! $state['exists'] ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No physical file exists at the public root. A virtual public response might still be served. Select Validate public file to inspect it before publishing a physical file.', 'serfe-ai-discovery' ) . '</p></div>';
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

		if ( ! $state['exists'] && '' === $editor ) {
			$live_result = Discovery_Files::validate_current( $name );
			if ( ! is_wp_error( $live_result ) && ! empty( $live_result['virtual'] ) ) {
				$editor                    = $live_result['content'];
				$display_state['content']  = $live_result['content'];
				$display_state['checksum'] = isset( $live_result['checksum'] ) ? $live_result['checksum'] : '';
				// Setting virtual to true so conflict checks work identically.
				$virtual  = true;
				$conflict = true;
			}
		}

		if ( $virtual ) {
			$display_state['content']  = isset( $notice['public_content'] ) ? $notice['public_content'] : $display_state['content'];
			$display_state['checksum'] = isset( $notice['checksum'] ) ? $notice['checksum'] : $display_state['checksum'];
			if ( '' === $editor ) {
				$editor = $display_state['content'];
			}
		}
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
		echo '<p><button type="submit" name="mode" value="publish" class="button button-primary">' . esc_html__( 'Publish file', 'serfe-ai-discovery' ) . '</button> ';
		if ( 'robots.txt' === $name ) {
			$has_signals = false !== strpos( $editor, 'Content-Signal:' ) || false !== strpos( $editor, '# START SERFE BLOCK' );
			if ( $has_signals ) {
				echo '<button type="submit" name="mode" value="remove_content_signals" class="button" style="background: #d63638; color: #fff; border-color: #d63638;">' . esc_html__( 'Remove Content Signals', 'serfe-ai-discovery' ) . '</button> ';
			} else {
				echo '<button type="submit" name="mode" value="activate_content_signals" class="button button-primary">' . esc_html__( 'Activate Content Signals', 'serfe-ai-discovery' ) . '</button> ';
			}
			$blog_slug = self::get_blog_slug();
			if ( $blog_slug ) {
				echo '<button type="submit" name="mode" value="protect_blog_ai_train" class="button button-primary">' . esc_html__( 'Protect Blog from AI Train', 'serfe-ai-discovery' ) . '</button> ';
			}
		}
		echo '<button type="submit" name="mode" value="validate" class="button" formnovalidate>' . esc_html__( 'Reset and load current', 'serfe-ai-discovery' ) . '</button> ';
		if ( 'llms.txt' === $name ) {
			echo '<button type="submit" name="mode" value="template" class="button" formnovalidate>' . esc_html__( 'Insert starter template', 'serfe-ai-discovery' ) . '</button> ';
		}
		if ( is_string( $state['backup'] ) ) {
			echo '<button type="submit" name="mode" value="restore" class="button" formnovalidate>' . esc_html__( 'Restore previous version', 'serfe-ai-discovery' ) . '</button>';
		}
		echo '</p></form>';
		if ( 'robots.txt' === $name ) {
			echo '<p><a href="https://contentsignals.org/?utm_source=serfe-ai-discovery" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about Content Signals options', 'serfe-ai-discovery' ) . '</a></p>';

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
			if ( ! empty( $seo_names ) ) {
				/* translators: %1$s: Name of SEO plugin(s) (e.g. Yoast SEO, Rank Math). */
				echo '<p class="description">' . sprintf( esc_html__( 'We detected you have %1$s installed, keep in mind that other plugins may edit your robots.txt even without your explicit action.', 'serfe-ai-discovery' ), esc_html( implode( ' and ', $seo_names ) ) ) . '<br>' . wp_kses_post( implode( ' &middot; ', $seo_links ) ) . '</p>';
			}
		}
		if ( $conflict || ( is_array( $notice ) && isset( $notice['file'], $notice['code'] ) && $name === $notice['file'] ) ) {
			$this->render_review( $name, $display_state, $editor );
		}
		echo '</section>';
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

	/** Insert only reviewable placeholders; no site URLs or claims are generated. */
	private static function starter_template() {
		return "# [Site or organization name]\n\n[Brief public description.]\n\n## Products and services\n\n- [Public page title]([canonical public URL])\n\n## Support and resources\n\n- [Public page title]([canonical public URL])\n";
	}

	/** Isolate admin notices per administrator without putting content in URLs. */
	private static function notice_key() {
		return 'serfe_ai_discovery_file_notice_' . get_current_user_id();
	}
	private static function content_signals_block( $with_blog = false ) {
		$block = "# START SERFE BLOCK\n# ---------------------------\n# Allow unfettered access to your / page\nUser-Agent: *\nContent-Signal: / ai-train=yes, search=yes, ai-input=yes\nAllow: /\n";
		if ( $with_blog ) {
			$blog_slug = self::get_blog_slug();
			if ( $blog_slug ) {
				$block .= "\n# Search-only access to your blog\nUser-Agent: *\nContent-Signal: " . $blog_slug . " ai-train=no, search=yes, ai-input=no\nAllow: " . $blog_slug . "\n";
			}
		}
		$block .= "\n# ---------------------------\n# END SERFE BLOCK";
		return $block;
	}

	private static function get_blog_slug() {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$page_for_posts = get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$post = get_post( $page_for_posts );
				if ( $post ) {
					return '/' . $post->post_name . '/';
				}
			}
		}
		return false;
	}
}
