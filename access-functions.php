<?php
/**
 * This file contains access functions for various class methods
 *
 * @package WPGraphQL\SearchWP
 * @since 0.0.1
 */

/**
 * Checks if source string starts with the target string
 *
 * @param string $haystack - Source string.
 * @param string $needle - Target string.
 *
 * @return bool
 */
function graphql_starts_with( $haystack, $needle ) {
	$length = strlen( $needle );
	return ( substr( $haystack, 0, $length ) === $needle );
}

/**
 * Checks if source string ends with the target string
 *
 * @param string $haystack - Source string.
 * @param string $needle - Target string.
 *
 * @return bool
 */
function graphql_ends_with( $haystack, $needle ) {
	$length = strlen( $needle );
	if ( 0 === $length ) {
		return true;
	}

	return ( substr( $haystack, -$length ) === $needle );
}
