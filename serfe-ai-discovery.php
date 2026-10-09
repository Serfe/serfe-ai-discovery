<?php
/**
 * Plugin Name: Serfe AI Discovery
 * Description: Publishes approved public WordPress content as alternative Markdown and discovery signals.
 * Author: Serfe <info@serfe.com>
 * Version: 1.0.10
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
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-readiness.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery-files.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-plugin.php';

Serfe\AI_Discovery\Plugin::instance()->boot();
