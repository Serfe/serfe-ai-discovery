<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-request-router.php';
require_once dirname( __DIR__ ) . '/includes/class-response.php';
require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-html-to-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery.php';

use Serfe\AI_Discovery\Html_To_Markdown;
use Serfe\AI_Discovery\Markdown;
use Serfe\AI_Discovery\Request_Router;
use Serfe\AI_Discovery\Discovery;
use Serfe\AI_Discovery\Response;

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

$failures = array();
$tests    = 0;

$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . "\nExpected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true );
	}
};

$assert_same( true, Request_Router::is_markdown_uri( '/es/blog/post.md' ), 'Detects .md URL' );
$assert_same( true, Request_Router::is_markdown_uri( '/es/product.md/?currency=USD' ), 'Detects .md URL with query' );
$assert_same( false, Request_Router::is_markdown_uri( '/es/product/' ), 'Ignores HTML URL' );
$assert_same( false, Request_Router::is_markdown_uri( '/wp-json/example.md' ), 'Ignores internal URL' );
$assert_same( true, Request_Router::is_negotiable_uri( '/privacy-policy/' ), 'Allows public canonical URLs for negotiation' );
$assert_same( false, Request_Router::is_negotiable_uri( '/wp-json/wp/v2/posts' ), 'Does not negotiate internal REST URLs' );
$assert_same( false, Request_Router::is_negotiable_uri( '/blog/wp-json/wp/v2/posts' ), 'Does not negotiate REST URLs under a site prefix' );
$assert_same( false, Request_Router::is_markdown_uri( '/blog/wp-admin/post.md' ), 'Does not rewrite admin URLs under a site prefix' );
$assert_same( true, Request_Router::is_negotiable_uri( '/blog/wp-json-guide/' ), 'Allows similarly named public paths' );
$assert_same( true, Request_Router::accepts_markdown( 'text/html, text/markdown; q=0.9' ), 'Detects Markdown in Accept header' );
$assert_same( false, Request_Router::accepts_markdown( 'text/html, text/markdown; q=0' ), 'Honours an unacceptable Markdown quality value' );
$assert_same( false, Request_Router::accepts_markdown( 'text/html, application/json' ), 'Keeps HTML as default without Markdown Accept header' );
$assert_same( '/es/product/?currency=USD', Request_Router::underlying_uri( '/es/product.md?currency=USD' ), 'Preserves query string' );
$assert_same( '/discovery/product/', Request_Router::underlying_path( '/discovery/product.md' ), 'Normalizes PATH_INFO for a Multisite subdirectory Markdown request' );
$assert_same( 'https://serfe.test/es/product.md', Discovery::markdown_url( 'https://serfe.test/es/product/' ), 'Builds Markdown discovery URL' );
$assert_same( 'https://serfe.test/es/product.md', Discovery::markdown_url( 'https://serfe.test/es/product' ), 'Builds Markdown discovery URL without trailing slash' );
$assert_same( '"Hello \\"world\\""', Markdown::yaml_scalar( " Hello\n\"world\" " ), 'Escapes YAML scalar' );
$assert_same( 'USD 1,234.50', Markdown::price( 1234.5, 'USD' ), 'Formats price' );

$converter = new Html_To_Markdown();
$html      = '<h2>Title</h2><p>Hello <strong>world</strong>.</p><ul><li>One</li><li>Two</li></ul><script>secret()</script>';
$expected  = "## Title\n\nHello **world**.\n\n- One\n- Two";
$assert_same( $expected, $converter->convert( $html ), 'Converts HTML and removes scripts' );
$assert_same( 'Public', $converter->convert( '<p>Public</p><p hidden>Hidden</p><div aria-hidden="true">Private</div><p style="display:none">Gone</p><form><input value="secret"><button>Pay</button></form>' ), 'Skips hidden content and forms' );
$assert_same( '[Link](https://example.com) and `code`', $converter->convert( '<p><a href="https://example.com">Link</a> and <code>code</code></p>' ), 'Converts links and code' );
$table_html = '<table><thead><tr><th>Service</th><th>Option</th></tr></thead><tbody><tr><td>WordPress</td><td><strong>Managed</strong></td></tr></tbody></table>';
$table_md   = "| Service | Option |\n| --- | --- |\n| WordPress | **Managed** |";
$assert_same( $table_md, $converter->convert( $table_html ), 'Converts tables to Markdown' );
$assert_same( '| A\\|B |' . "\n| --- |\n| Value |", $converter->convert( '<table><tr><th>A|B</th></tr><tr><td>Value</td></tr></table>' ), 'Escapes table cell separators' );
$assert_same( "- Parent\n    - Child\n    - Another\n- Last", $converter->convert( '<ul><li>Parent<ul><li>Child</li><li>Another</li></ul></li><li>Last</li></ul>' ), 'Converts nested lists' );
$assert_same( "3. Third\n4. Fourth", $converter->convert( '<ol start="3"><li>Third</li><li>Fourth</li></ol>' ), 'Preserves ordered list start value' );
$assert_same( "- [x] Done\n- [ ] Pending", $converter->convert( '<ul><li><input type="checkbox" checked>Done</li><li><input type="checkbox">Pending</li></ul>' ), 'Converts task lists' );
$assert_same( '~~Retired~~ and `Ctrl`', $converter->convert( '<p><del>Retired</del> and <kbd>Ctrl</kbd></p>' ), 'Converts strikethrough and keyboard input' );
$assert_same( "```php\necho 'hello';\n```", $converter->convert( '<pre><code class="language-php">echo \'hello\';</code></pre>' ), 'Preserves fenced code language' );
$assert_same( '``tick`value``', $converter->convert( '<code>tick`value</code>' ), 'Uses a safe inline code delimiter' );
$assert_same( "| Name | Cost |\n| :--- | ---: |\n| Basic | 10 |", $converter->convert( '<table><tr><th class="has-text-align-left">Name</th><th style="text-align:right">Cost</th></tr><tr><td>Basic</td><td>10</td></tr></table>' ), 'Preserves table alignment' );
$assert_same( "Term\n: Explanation", $converter->convert( '<dl><dt>Term</dt><dd>Explanation</dd></dl>' ), 'Converts definition lists' );
$assert_same( '[Docs](https://example.com "Reference")', $converter->convert( '<a href="https://example.com" title="Reference">Docs</a>' ), 'Preserves link title' );
$assert_same( '[Video](https://example.com/video.mp4)', $converter->convert( '<video><source src="https://example.com/video.mp4"></video>' ), 'Converts video sources to a Markdown link' );
$assert_same( "![First](https://example.com/first.jpg)\n\n![Second](https://example.com/second.jpg)", $converter->convert( '<figure><img src="https://example.com/first.jpg" alt="First"></figure><figure><img src="https://example.com/second.jpg" alt="Second"></figure>' ), 'Separates saved Gutenberg gallery images into Markdown blocks' );
$assert_same( 2, Response::estimate_tokens( 'One two' ), 'Estimates Markdown tokens when no tokenizer is installed' );

// Verify WordPress 6.2 to 7.1 and PHP 7.4+ headers.
$main_php = file_get_contents( dirname( __DIR__ ) . '/serfe-ai-discovery.php' );
preg_match( '/\*\s*Requires at least:\s*(.+)$/m', $main_php, $m_wp );
preg_match( '/\*\s*Requires PHP:\s*(.+)$/m', $main_php, $m_php );
$assert_same( '6.2', isset( $m_wp[1] ) ? trim( $m_wp[1] ) : '', 'Plugin declares Requires at least: 6.2' );
$assert_same( '7.4', isset( $m_php[1] ) ? trim( $m_php[1] ) : '', 'Plugin declares Requires PHP: 7.4' );

$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
preg_match( '/^Requires at least:\s*(.+)$/m', $readme, $r_wp );
preg_match( '/^Tested up to:\s*(.+)$/m', $readme, $r_tested );
preg_match( '/^Requires PHP:\s*(.+)$/m', $readme, $r_php );
$assert_same( '6.2', isset( $r_wp[1] ) ? trim( $r_wp[1] ) : '', 'Readme declares Requires at least: 6.2' );
$assert_same( '7.1', isset( $r_tested[1] ) ? trim( $r_tested[1] ) : '', 'Readme declares Tested up to: 7.1' );
$assert_same( '7.4', isset( $r_php[1] ) ? trim( $r_php[1] ) : '', 'Readme declares Requires PHP: 7.4' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} tests passed.\n";
