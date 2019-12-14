<?php

add_filter( 'searchwp_debug', '__return_true' );

$search_wp_indexer = null;

/**
 * Indexes posts for SearchWP. If posts previous indexed, call "swp_purge_index()" before calling this.
 * 
 * @param array $post_objects  Array of WP_Post objects.
 */
function swp_index_test_posts( $post_objects ) {
    global $search_wp_indexer;
    if ( null === $search_wp_indexer ) {
        SWP()->purge_index();
        $search_wp_indexer = new SearchWPIndexer();

        // Update SWP's default engine settings.
        SWP()->settings['engines']['default']['post']['weights']['tax'] = array(
            'category'    => 31,
            'post_tag'    => 31,
            'post_format' => 0,
        );
        SWP()->settings['engines']['default']['page']['weights']['tax'] = array(
            'category'    => 31,
            'post_tag'    => 31,
            'post_format' => 0,
        );
    }

    $search_wp_indexer->unindexedPosts = $post_objects;
    $search_wp_indexer->index();
}

/**
 * Purges default engines index.
 */
function swp_purge_index() {
    SWP()->purge_index();
}