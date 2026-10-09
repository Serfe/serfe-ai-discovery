<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates an approved public WooCommerce catalog page. */
final class Catalog_Generator {
	/** @var Product_Generator */
	private $product_generator;

	public function __construct( Product_Generator $product_generator ) {
		$this->product_generator = $product_generator;
	}

	/** Build Markdown from the already resolved WooCommerce main query page. */
	public function generate( $context, $max_pages, $posts ) {
		$term     = isset( $context['term'] ) ? $context['term'] : null;
		$title    = $term instanceof \WP_Term ? wp_strip_all_tags( $term->name ) : __( 'Shop', 'serfe-ai-discovery' );
		$currency = get_woocommerce_currency();
		$front    = "---\n";
		$front   .= 'title: ' . Markdown::yaml_scalar( $title ) . "\n";
		$front   .= 'url: ' . Markdown::yaml_scalar( $context['url'] ) . "\n";
		$front   .= 'type: ' . Markdown::yaml_scalar( $context['type'] . '-archive' ) . "\n";
		$front   .= 'page: ' . (int) $context['page'] . "\n";
		$front   .= 'currency: ' . Markdown::yaml_scalar( $currency ) . "\n";
		$front   .= "---\n\n";
		$body     = '# ' . $title . "\n\n";
		if ( $term instanceof \WP_Term && is_string( $term->description ) && false === strpos( $term->description, '[' ) ) {
			$description = ( new Html_To_Markdown() )->convert( $term->description );
			if ( '' !== $description ) {
				$body .= $description . "\n\n";
			}
		}

		foreach ( $posts as $post ) {
			if ( ! ( $post instanceof \WP_Post ) || 'product' !== $post->post_type || 'publish' !== $post->post_status
				|| ! empty( $post->post_password ) || ! is_post_publicly_viewable( $post )
				|| Exclusions::blocks( $post )
				|| ! $this->product_generator->is_exposable( $post->ID ) ) {
				continue;
			}
			$product = wc_get_product( $post->ID );
			if ( ! ( $product instanceof \WC_Product ) ) {
				continue;
			}
			$title = trim( wp_strip_all_tags( $post->post_title ) );
			$url   = esc_url_raw( get_permalink( $post ) );
			$price = $product->get_price();
			if ( '' === $title || '' === $url || '' === $price || null === $price ) {
				continue;
			}
			$title = str_replace( array( '\\', '[', ']' ), array( '\\\\', '\\[', '\\]' ), $title );
			$stock = $product->is_in_stock() ? __( 'In stock', 'serfe-ai-discovery' ) : __( 'Out of stock', 'serfe-ai-discovery' );
			$body .= '- [' . $title . '](<' . str_replace( '>', '%3E', $url ) . '>) — ' . Markdown::price( (float) $price, $currency ) . ' — ' . $stock . "\n";
		}

		if ( 1 < $context['page'] ) {
			$body .= "\n" . self::page_link( __( 'Previous page', 'serfe-ai-discovery' ), $context['page'] - 1 );
		}
		if ( $context['page'] < $max_pages ) {
			$body .= "\n" . self::page_link( __( 'Next page', 'serfe-ai-discovery' ), $context['page'] + 1 );
		}
		return $front . rtrim( $body ) . "\n";
	}

	/** Format a public catalog pagination link. */
	private static function page_link( $label, $page ) {
		$url = esc_url_raw( get_pagenum_link( $page, false ) );
		return '[' . $label . '](<' . str_replace( '>', '%3E', $url ) . ">)\n";
	}
}
