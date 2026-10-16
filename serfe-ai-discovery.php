<?php
/**
 * Plugin Name: Serfe AI Discovery
 * Description: Publishes approved public WordPress content as alternative Markdown and discovery signals.
 * Author: Serfe
 * Author URI: https://www.serfe.com/en/
 * Version: 1.0.13
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: serfe-ai-discovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SERFE_AI_DISCOVERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'SERFE_AI_DISCOVERY_URL', plugin_dir_url( __FILE__ ) );

$serfe_ai_discovery_headers = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
define( 'SERFE_AI_DISCOVERY_VERSION', $serfe_ai_discovery_headers['Version'] );
unset( $serfe_ai_discovery_headers );

require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-request-router.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-response.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-markdown.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-html-to-markdown.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-blog-generator.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-archive-generator.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-editorial-content.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-product-generator.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-catalog-generator.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-settings.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-exclusions.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-markdown-cache.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-cache-integrations.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-readiness.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery-files.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery-files-admin.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-plugin.php';

Serfe\AI_Discovery\Plugin::instance()->boot();
