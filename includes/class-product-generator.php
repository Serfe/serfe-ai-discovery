<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates an allowlisted Markdown representation of a WooCommerce product. */
final class Product_Generator {
	/** @param int $product_id Product ID. @return bool */
	public function is_exposable( $product_id ) {
		$exposable = false;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$visible   = in_array( $product->get_catalog_visibility(), array( 'visible', 'catalog' ), true );
				$price     = $product->get_price();
				$exposable = $visible && $price !== '' && null !== $price;
			}
		}

		return (bool) apply_filters( 'serfe_ai_discovery_product_exposable', $exposable, $product_id );
	}

	/** @param \WP_Post $post Product post. @return string */
	public function generate( $post ) {
		$id       = $post->ID;
		$details  = apply_filters( 'wpml_post_language_details', null, $id );
		$lang     = is_array( $details ) && ! empty( $details['language_code'] ) ? $details['language_code'] : '';
		$currency = $this->default_currency( $lang );
		$wcml     = isset( $GLOBALS['woocommerce_wpml'] ) ? $GLOBALS['woocommerce_wpml'] : null;

		if ( $wcml && isset( $wcml->multi_currency ) && method_exists( $wcml->multi_currency, 'set_client_currency' ) ) {
			$wcml->multi_currency->set_client_currency( $currency );
		}

		$product  = wc_get_product( $id );
		$title    = get_the_title( $id );
		$url      = get_permalink( $id );
		$coverage = $this->coverage( $id );

		// Build the table first: price_from must match the rows it prints.
		$price_from   = null;
		$plans        = $this->plan_table( $product, $currency, $price_from );
		$review_count = $product ? (int) $product->get_review_count() : 0;

		$front  = "---\n";
		$front .= 'title: ' . Markdown::yaml_scalar( $title ) . "\n";
		$front .= 'url: ' . Markdown::yaml_scalar( $url ) . "\n";
		$front .= 'type: ' . Markdown::yaml_scalar( 'product' ) . "\n";
		if ( $lang !== '' ) {
			$front .= 'lang: ' . Markdown::yaml_scalar( $lang ) . "\n";
		}
		if ( $coverage !== '' ) {
			$front .= 'coverage: ' . Markdown::yaml_scalar( $coverage ) . "\n";
		}
		$front .= 'currency: ' . Markdown::yaml_scalar( $currency ) . "\n";
		// Generation time: prices are read from WooCommerce at this moment.
		$front .= 'updated: ' . Markdown::yaml_scalar( gmdate( 'c' ) ) . "\n";
		if ( null !== $price_from ) {
			$front .= 'price_from: ' . number_format( $price_from, 2, '.', '' ) . "\n";
		}
		$front .= 'availability: ' . Markdown::yaml_scalar( $product && $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ) . "\n";
		if ( $review_count > 0 ) {
			$front .= 'rating: ' . round( (float) $product->get_average_rating(), 2 ) . "\n";
			$front .= 'review_count: ' . $review_count . "\n";
		}
		$front .= "---\n\n";

		$body = '# ' . $title . "\n\n";
		if ( $coverage !== '' ) {
			$body .= '**' . __( 'Coverage', 'serfe-ai-discovery' ) . ':** ' . $coverage . "\n\n";
		}
		$body .= $plans;
		$body .= $this->faqs( $post, $lang );

		return $front . $body . "\n";
	}

	/** @param string $lang Language code. @return string */
	private function default_currency( $lang ) {
		$wcml = isset( $GLOBALS['woocommerce_wpml'] ) ? $GLOBALS['woocommerce_wpml'] : null;
		if ( $wcml && ! empty( $wcml->settings['default_currencies'][ $lang ] ) ) {
			return $wcml->settings['default_currencies'][ $lang ];
		}
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
	}

	/** @param int $product_id Product ID. @return string */
	private function coverage( $product_id ) {
		if ( ! function_exists( 'get_field' ) ) {
			return '';
		}

		$coverage = (string) get_field( 'destination', $product_id );
		if ( $coverage === '' ) {
			$coverage = (string) get_field( 'country_code', $product_id );
		}
		if ( $coverage !== '' ) {
			return $coverage;
		}

		$default_language = apply_filters( 'wpml_default_language', null );
		$original_id      = (int) apply_filters( 'wpml_object_id', $product_id, 'product', true, $default_language );
		if ( ! $original_id || $original_id === $product_id ) {
			return '';
		}

		$coverage = (string) get_field( 'destination', $original_id );
		return $coverage !== '' ? $coverage : (string) get_field( 'country_code', $original_id );
	}

	/** @param \WP_Post $post Product post. @param string $lang Language code. @return string */
	private function faqs( $post, $lang ) {
		if ( ! function_exists( 'holasim_faq_build_questions_from_query' ) ) {
			return '';
		}

		$content = (string) get_post_field( 'post_content', $post->ID );
		$match   = null;
		if ( ! preg_match( '/\[hs_faqs([^\]]*)\]/', $content, $match ) ) {
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $elementor_data ) ) {
				preg_match( '/\[hs_faqs([^\]]*)\]/', $elementor_data, $match );
			}
		}
		if ( ! $match ) {
			return '';
		}

		$attributes = shortcode_parse_atts( stripslashes( $match[1] ) );
		$attributes = is_array( $attributes ) ? $attributes : array();
		$post_type   = ! empty( $attributes['post_type'] ) ? sanitize_key( $attributes['post_type'] ) : 'faq';
		$taxonomy    = ! empty( $attributes['taxonomy'] ) ? sanitize_key( $attributes['taxonomy'] ) : 'faq_category';
		$per_page    = isset( $attributes['total'] ) && $attributes['total'] !== '' ? (int) $attributes['total'] : -1;
		$term_value  = ! empty( $attributes['category'] ) ? $attributes['category'] : ( ! empty( $attributes['term'] ) ? $attributes['term'] : '' );

		$args = array(
			'post_type'        => $post_type,
			'posts_per_page'   => $per_page,
			'post_status'      => 'publish',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);
		if ( $term_value !== '' ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => is_numeric( $term_value ) ? 'term_id' : 'slug',
					'terms'    => is_numeric( $term_value ) ? (int) $term_value : sanitize_title( $term_value ),
				),
			);
		}

		$faq_lang = $this->faq_language( $lang );
		if ( $faq_lang !== '' ) {
			$args['lang'] = $faq_lang;
		}
		$questions = holasim_faq_build_questions_from_query( $args, $faq_lang ?: null );
		if ( empty( $questions ) ) {
			return '';
		}

		$output = "\n## " . __( 'Frequently asked questions', 'serfe-ai-discovery' ) . "\n\n";
		foreach ( $questions as $question ) {
			$name = isset( $question['name'] ) ? $question['name'] : '';
			$text = isset( $question['acceptedAnswer']['text'] ) ? $question['acceptedAnswer']['text'] : '';
			if ( $name !== '' && $text !== '' ) {
				$output .= '### ' . $name . "\n\n" . $text . "\n\n";
			}
		}

		return rtrim( $output ) . "\n";
	}

	/** @param string $lang Product language. @return string */
	private function faq_language( $lang ) {
		$normalised = strtolower( str_replace( '_', '-', (string) $lang ) );
		if ( ! in_array( $normalised, array( 'ar', 'es-ar', 'es_ar' ), true ) ) {
			return $lang;
		}

		$default_language = apply_filters( 'wpml_default_language', null );
		return is_string( $default_language ) && strpos( strtolower( $default_language ), 'es' ) === 0 ? $default_language : 'es';
	}

	/** @param mixed $product Product. @param string $currency Currency. @param float|null $price_from Lowest printed price. @return string */
	private function plan_table( $product, $currency, &$price_from = null ) {
		$price_from = null;

		if ( ! $product || ! ( $product instanceof \WC_Product_Variable ) ) {
			$price = $product ? $product->get_price() : '';
			if ( $price === '' || null === $price ) {
				return '';
			}
			$price_from = (float) $price;
			return '**' . __( 'Price', 'serfe-ai-discovery' ) . ':** ' . Markdown::price( $price, $currency ) . "\n";
		}

		$rows = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_status() !== 'publish' ) {
				continue;
			}
			$price = $variation->get_price();
			$plan  = $this->variation_plan_label( $variation );
			if ( $price === '' || null === $price || $plan === '' ) {
				continue;
			}
			$rows[] = array(
				'plan'      => $plan,
				'price'     => (float) $price,
				'formatted' => Markdown::price( $price, $currency ),
				'group'     => $this->variation_plan_group( $variation ),
			);
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$rows = $this->group_plan_rows( $rows );
		usort( $rows, function ( $a, $b ) { return $a['price'] <=> $b['price']; } );
		$price_from = $rows[0]['price'];

		$output  = '## ' . __( 'Plans', 'serfe-ai-discovery' ) . "\n\n";
		$output .= '| ' . __( 'Plan', 'serfe-ai-discovery' ) . ' | ' . __( 'Price', 'serfe-ai-discovery' ) . " |\n";
		$output .= "| --- | --- |\n";
		foreach ( $rows as $row ) {
			$output .= '| ' . str_replace( '|', '\\|', $row['plan'] ) . ' | ' . $row['formatted'] . " |\n";
		}
		return $output;
	}

	/**
	 * Extract the comparable parts of a duration-based plan variation.
	 *
	 * @param mixed $variation Product variation.
	 * @return array|null
	 */
	private function variation_plan_group( $variation ) {
		$attributes = $variation->get_attributes();
		$plan_slug  = isset( $attributes['pa_plan-de-datos'] ) ? (string) $attributes['pa_plan-de-datos'] : '';

		if ( ! preg_match( '/^(\d+)-(?:dia|dias|day|days)-(.+)$/i', $plan_slug, $slug_match ) ) {
			return null;
		}

		$term       = get_term_by( 'slug', $plan_slug, 'pa_plan-de-datos' );
		$plan_label = $term ? $term->name : $plan_slug;
		if ( ! preg_match( '/^(\d+)\s+(?:d[ií]as?|days?)\s+(.+)$/iu', $plan_label, $label_match ) ) {
			return null;
		}

		$duration = (int) $slug_match[1];
		if ( $duration < 1 || $duration !== (int) $label_match[1] ) {
			return null;
		}

		unset( $attributes['pa_plan-de-datos'] );
		ksort( $attributes );

		return array(
			'duration'         => $duration,
			'package_key'      => strtolower( $slug_match[2] ),
			'package_label'    => trim( $label_match[2] ),
			'other_attributes' => $attributes,
			'purchasable'      => $variation->is_purchasable(),
			'stock_status'     => $variation->get_stock_status(),
		);
	}

	/**
	 * Combine consecutive durations when all other public plan data is equivalent.
	 *
	 * @param array  $rows Plan rows.
	 * @return array
	 */
	private function group_plan_rows( $rows ) {
		$grouped = array();
		$buckets = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['group'] ) ) {
				$grouped[] = $row;
				continue;
			}

			$group = $row['group'];
			$key   = wp_json_encode(
				array(
					$group['package_key'],
					$group['package_label'],
					$row['formatted'],
					$group['other_attributes'],
					$group['purchasable'],
					$group['stock_status'],
				)
			);

			$buckets[ $key ][] = $row;
		}

		foreach ( $buckets as $bucket ) {
			usort( $bucket, function ( $a, $b ) {
				return $a['group']['duration'] <=> $b['group']['duration'];
			} );

			$sequence = array();
			foreach ( $bucket as $row ) {
				$previous = end( $sequence );
				if ( $previous && $row['group']['duration'] !== $previous['group']['duration'] + 1 ) {
					$grouped[] = $this->plan_sequence_row( $sequence );
					$sequence  = array();
				}
				$sequence[] = $row;
			}

			if ( ! empty( $sequence ) ) {
				$grouped[] = $this->plan_sequence_row( $sequence );
			}
		}

		return $grouped;
	}

	/**
	 * Build one output row from a sequence of equivalent plan durations.
	 *
	 * @param array  $sequence Equivalent plan rows.
	 * @return array
	 */
	private function plan_sequence_row( $sequence ) {
		$row = reset( $sequence );
		if ( count( $sequence ) < 2 ) {
			return $row;
		}

		$last        = end( $sequence );
		$row['plan'] = $row['group']['duration'] . '–' . $last['group']['duration'] . ' '
			. __( 'Days', 'serfe-ai-discovery' ) . ' ' . $row['group']['package_label'];

		return $row;
	}

	/** @param mixed $variation Product variation. @return string */
	private function variation_plan_label( $variation ) {
		$labels = array();
		foreach ( $variation->get_attributes() as $taxonomy => $slug ) {
			if ( $slug === '' ) {
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
