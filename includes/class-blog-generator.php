<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Generates the Markdown representation of a standard post or page. */
final class Blog_Generator {
	/** @var Html_To_Markdown */
	private $converter;

	public function __construct( Html_To_Markdown $converter ) {
		$this->converter = $converter;
	}

	/** @param \WP_Post $post Post. @return string */
	public function generate( $post ) {
		$lang    = get_bloginfo( 'language' );
		$title   = get_the_title( $post );
		$url     = get_permalink( $post );
		$date    = get_the_date( 'c', $post );
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		$html    = apply_filters( 'the_content', $post->post_content );

		$front  = "---\n";
		$front .= 'title: ' . Markdown::yaml_scalar( $title ) . "\n";
		$front .= 'url: ' . Markdown::yaml_scalar( $url ) . "\n";
		if ( $lang !== '' ) {
			$front .= 'lang: ' . Markdown::yaml_scalar( $lang ) . "\n";
		}
		$front .= 'date: ' . Markdown::yaml_scalar( $date ) . "\n";
		if ( $excerpt !== '' ) {
			$front .= 'excerpt: ' . Markdown::yaml_scalar( $excerpt ) . "\n";
		}
		$front .= "---\n\n";

		return $front . '# ' . $title . "\n\n" . $this->converter->convert( $html ) . "\n";
	}
}
