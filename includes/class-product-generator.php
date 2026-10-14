<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates Markdown from public WooCommerce product properties. */
final class Product_Generator {
	/** @param int $product_id Product ID. @return bool */
	public function is_exposable( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$visible = in_array( $product->get_catalog_visibility(), array( 'visible', 'catalog' ), true );
		$price   = $product->get_price();
		if ( ( '' === $price || null === $price ) && $product instanceof \WC_Product_Variable ) {
			if ( method_exists( $product, 'get_variation_price' ) ) {
				$price = $product->get_variation_price( 'min' );
			}
			if ( '' === $price || null === $price ) {
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child instanceof \WC_Product_Variation && $child->variation_is_visible() ) {
						$child_price = $child->get_price();
						if ( '' !== $child_price && null !== $child_price ) {
							$price = $child_price;
							break;
						}
					}
				}
			}
		}
		$public = $visible && '' !== $price && null !== $price;

		// Extensions may deny exposure, but may not override WooCommerce visibility.
		return $public && (bool) apply_filters( 'serfe_ai_discovery_product_exposable', true, $product_id );
	}

	/** @param \WP_Post $post Product post. @return string */
	public function generate( $post ) {
		$product  = wc_get_product( $post->ID );
		$title    = get_the_title( $post->ID );
		$url      = get_permalink( $post->ID );
		$lang     = get_bloginfo( 'language' );
		$currency = get_woocommerce_currency();

		$price_from = null;
		$price_to   = null;
		$prices     = $this->price_details( $product, $currency, $price_from, $price_to );
		$front      = "---\n";
		$front     .= 'title: ' . Markdown::yaml_scalar( $title ) . "\n";
		$front     .= 'url: ' . Markdown::yaml_scalar( $url ) . "\n";
		$front     .= 'type: ' . Markdown::yaml_scalar( 'product' ) . "\n";
		if ( '' !== $lang ) {
			$front .= 'lang: ' . Markdown::yaml_scalar( $lang ) . "\n";
		}
		$front .= 'currency: ' . Markdown::yaml_scalar( $currency ) . "\n";
		if ( $product && ! ( $product instanceof \WC_Product_Variable ) ) {
			$raw_price = $product->get_price();
			if ( '' !== $raw_price && null !== $raw_price ) {
				$front .= 'price: ' . number_format( (float) $raw_price, 2, '.', '' ) . "\n";
			}
			$reg_price = $product->get_regular_price();
			if ( '' !== $reg_price && null !== $reg_price ) {
				$front .= 'regular_price: ' . number_format( (float) $reg_price, 2, '.', '' ) . "\n";
			}
			$sale_price = $product->get_sale_price();
			if ( $product->is_on_sale() && '' !== $sale_price && null !== $sale_price ) {
				$front .= 'sale_price: ' . number_format( (float) $sale_price, 2, '.', '' ) . "\n";
				$front .= "on_sale: true\n";
			}
		} elseif ( $product && $product instanceof \WC_Product_Variable && $product->is_on_sale() ) {
			$front .= "on_sale: true\n";
		}
		if ( null !== $price_from ) {
			$front .= 'price_from: ' . number_format( $price_from, 2, '.', '' ) . "\n";
		}
		if ( null !== $price_to && $price_to !== $price_from ) {
			$front .= 'price_to: ' . number_format( $price_to, 2, '.', '' ) . "\n";
		}
		$front .= 'availability: ' . Markdown::yaml_scalar( $product && $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ) . "\n";
		$front .= "---\n\n";

		$body = '# ' . $title . "\n\n";
		if ( $product ) {
			$description = trim( (string) $product->get_short_description() );
			$full        = trim( (string) $product->get_description() );
			if ( '' !== $full ) {
				$description .= ( '' !== $description ? "\n\n" : '' ) . $full;
			}
			if ( '' !== $description ) {
				$body .= ( new Html_To_Markdown() )->convert( $description ) . "\n\n";
			}
		}

		return $front . $body . $prices . "\n";
	}

	/**
	 * @param mixed $product Product.
	 * @param string $currency Currency.
	 * @param float|null $price_from Lowest printed price.
	 * @param float|null $price_to Highest printed price.
	 * @return string
	 */
	private function price_details( $product, $currency, &$price_from, &$price_to = null ) {
		$price_from = null;
		$price_to   = null;
		if ( ! $product ) {
			return '';
		}

		if ( ! ( $product instanceof \WC_Product_Variable ) ) {
			$price = $product->get_price();
			if ( '' === $price || null === $price ) {
				return '';
			}
			$price_from    = (float) $price;
			$price_to      = (float) $price;
			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();
			$is_on_sale    = $product->is_on_sale() && '' !== $sale_price && null !== $sale_price;

			$lines   = array();
			$lines[] = '**' . __( 'Price', 'serfe-ai-discovery' ) . ':** ' . Markdown::price( $price, $currency );
			if ( $is_on_sale ) {
				if ( '' !== $regular_price && null !== $regular_price ) {
					$lines[] = '**' . __( 'Regular Price', 'serfe-ai-discovery' ) . ':** ' . Markdown::price( $regular_price, $currency );
				}
				$lines[] = '**' . __( 'Sale Price', 'serfe-ai-discovery' ) . ':** ' . Markdown::price( $sale_price, $currency );
			}
			return implode( "\n", $lines ) . "\n";
		}

		$rows     = array();
		$has_sale = false;
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! ( $variation instanceof \WC_Product_Variation ) || 'publish' !== $variation->get_status() || ! $variation->variation_is_visible() ) {
				continue;
			}
			$price = $variation->get_price();
			$label = $this->variation_label( $variation );
			if ( '' === $price || null === $price || '' === $label ) {
				continue;
			}
			$regular_price = $variation->get_regular_price();
			$sale_price    = $variation->get_sale_price();
			$is_on_sale    = $variation->is_on_sale() && '' !== $sale_price && null !== $sale_price;
			if ( $is_on_sale ) {
				$has_sale = true;
			}
			$rows[] = array(
				'label'         => $label,
				'price'         => (float) $price,
				'regular_price' => '' !== $regular_price && null !== $regular_price ? (float) $regular_price : null,
				'sale_price'    => $is_on_sale ? (float) $sale_price : null,
				'on_sale'       => $is_on_sale,
				'in_stock'      => $variation->is_in_stock(),
			);
		}

		if ( ! $rows ) {
			return '';
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return $a['price'] <=> $b['price'];
			}
		);
		$price_from = $rows[0]['price'];
		$price_to   = $rows[ count( $rows ) - 1 ]['price'];

		$output = '## ' . __( 'Variations', 'serfe-ai-discovery' ) . "\n\n";
		if ( $has_sale ) {
			$output .= '| ' . __( 'Option', 'serfe-ai-discovery' ) . ' | ' . __( 'Price', 'serfe-ai-discovery' ) . ' | ' . __( 'Regular Price', 'serfe-ai-discovery' ) . ' | ' . __( 'Sale Price', 'serfe-ai-discovery' ) . ' | ' . __( 'Availability', 'serfe-ai-discovery' ) . " |\n";
			$output .= "| --- | --- | --- | --- | --- |\n";
			foreach ( $rows as $row ) {
				$label   = str_replace( '|', '\\|', preg_replace( '/\s+/', ' ', $row['label'] ) );
				$stock   = $row['in_stock'] ? __( 'In stock', 'serfe-ai-discovery' ) : __( 'Out of stock', 'serfe-ai-discovery' );
				$regular = null !== $row['regular_price'] ? Markdown::price( $row['regular_price'], $currency ) : '-';
				$sale    = null !== $row['sale_price'] ? Markdown::price( $row['sale_price'], $currency ) : '-';
				$output .= '| ' . $label . ' | ' . Markdown::price( $row['price'], $currency ) . ' | ' . $regular . ' | ' . $sale . ' | ' . $stock . " |\n";
			}
		} else {
			$output .= '| ' . __( 'Option', 'serfe-ai-discovery' ) . ' | ' . __( 'Price', 'serfe-ai-discovery' ) . ' | ' . __( 'Availability', 'serfe-ai-discovery' ) . " |\n";
			$output .= "| --- | --- | --- |\n";
			foreach ( $rows as $row ) {
				$label   = str_replace( '|', '\\|', preg_replace( '/\s+/', ' ', $row['label'] ) );
				$stock   = $row['in_stock'] ? __( 'In stock', 'serfe-ai-discovery' ) : __( 'Out of stock', 'serfe-ai-discovery' );
				$output .= '| ' . $label . ' | ' . Markdown::price( $row['price'], $currency ) . ' | ' . $stock . " |\n";
			}
		}
		return $output;
	}

	/** @param mixed $variation Product variation. @return string */
	private function variation_label( $variation ) {
		$labels = array();
		foreach ( $variation->get_attributes() as $taxonomy => $slug ) {
			if ( '' === $slug ) {
				continue;
			}
			if ( taxonomy_exists( $taxonomy ) ) {
				$term     = get_term_by( 'slug', $slug, $taxonomy );
				$labels[] = $term ? $term->name : $slug;
			} else {
				$labels[] = $slug;
			}
		}
		return trim( implode( ' · ', $labels ) );
	}
}
