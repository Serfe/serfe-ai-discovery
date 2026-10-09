<?php

namespace Serfe\AI_Discovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared Markdown formatting helpers. */
final class Markdown {
	/** @param mixed $value Value to encode. @return string */
	public static function yaml_scalar( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		return '"' . str_replace( '"', '\\"', $value ) . '"';
	}

	/** @param mixed $amount Amount. @param string $currency Currency code. @return string */
	public static function price( $amount, $currency ) {
		return $currency . ' ' . number_format( (float) $amount, 2, '.', ',' );
	}
}
