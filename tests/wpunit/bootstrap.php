<?php

add_filter( 'searchwp_debug', '__return_true' );

$search_wp_indexer = null;

/**
 * Indexes posts for SearchWP. If posts previous indexed, call "swp_purge_index()" before calling this.
 *
 * @param array $post_objects  Array of WP_Post objects.
 */
function swp_index_test_posts( $post_objects ) {
	$index = new \SearchWP\Index\Controller();

	// Create a Default Engine.
	$engine_model = json_decode( json_encode( new \SearchWP\Engine( 'default' ) ), true );
	\SearchWP\Settings::update_engines_config( [
		'default' => \SearchWP\Utils::normalize_engine_config( $engine_model ),
	] );

	foreach ( $post_objects as $post ) {
		$post_type = 'post.' . get_post_type( $post );
		$index->add( new \SearchWP\Entry( $post_type, $post->ID ) );
	}
}

/**
 * Purges default engines index.
 */
function swp_purge_index() {
	$index = \SearchWP::$index;
	$index->reset();

    \SearchWP\Settings::update_engines_config( [] );
}
