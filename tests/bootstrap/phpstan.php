<?php

/** Declare runtime plugin constants for static analysis only. */
if ( ! defined( 'SERFE_AI_DISCOVERY_PATH' ) ) {
	define( 'SERFE_AI_DISCOVERY_PATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'SERFE_AI_DISCOVERY_URL' ) ) {
	define( 'SERFE_AI_DISCOVERY_URL', 'https://example.invalid/wp-content/plugins/serfe-ai-discovery/' );
}
