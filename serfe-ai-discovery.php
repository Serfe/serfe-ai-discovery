<?php
/**
 * Plugin Name: Serfe AI Discovery
 * Description: Exposes Markdown representations of public pages, posts and Woo products at URLs ending in .md.
 * Author: Serfe <info@serfe.com>
 * Version: 1.0.10
 * Requires PHP: 7.4
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
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-product-generator.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-discovery.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-readiness.php';
require_once SERFE_AI_DISCOVERY_PATH . 'includes/class-plugin.php';

Serfe\AI_Discovery\Plugin::instance()->boot();
