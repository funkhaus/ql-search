<?php

class SWPQueriesTest extends \Codeception\TestCase\WPTestCase {
    private $admin;
    private $a_day_ago;

    public function setUp() {
        // before
        parent::setUp();

        // your set up methods here
        $this->admin     = $this->factory()->user->create( array( 'role' => 'administrator' ) );
        $this->a_day_ago = date( 'Y-m-d H:i:s', strtotime( '- 1 day' ) );
        $this->category_id   = $this->factory->term->create(
            array(
                'name'     => 'Test category',
                'slug'     => 'test_category',
                'taxonomy' => 'category',
            )
        );
        $this->tag_id   = $this->factory->term->create(
            array(
                'name'     => 'Test tag',
                'slug'     => 'test_slug',
                'taxonomy' => 'tag',
            )
        );
    }

    public function tearDown() {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    private function relay_id( $id, $node_type ) {
        \GraphQLRelay\Relay::toGlobalId( $id, $node_type );
    }

    private function setup_posts() {
        $post_ids   = array();

        $posts = array(
            array(),
            array( 'post_date' => $this->a_day_ago ),
            array(
                'post_content' => 'Hello Universe',
                'post_excerpt' => 'hello universe',
                'post_title'   => 'Hello Universe',
                'post_type'    => 'page'
            ),
            array( 'post_type' => 'page' ),
        );

        foreach ( $posts as $post ) {
            $defaults = array(
                'post_author'  => $this->admin,
                'post_content' => 'Hello World',
                'post_excerpt' => 'hello world',
                'post_status'  => 'publish',
                'post_title'   => 'Hello World',
                'post_type'    => 'post',
            );
            $post_id    = $this->factory()->post->create( array_merge( $defaults, $post ) );
            update_post_meta( $post_id, 'test_meta', "key-{$post_id}" );
            $post_ids[] = $post_id;
        }

        wp_set_object_terms( $post_ids[0], $this->category_id, 'category' );
        wp_set_object_terms( $post_ids[1], $this->category_id, 'category' );
        wp_set_object_terms( $post_ids[2], $this->tag_id, 'tag' );

        return $post_ids;
    }

    // tests
    public function testSWPQuery() {
        $post_ids = $this->setup_posts();
        $indexer = new SearchWPIndexer();
		$indexer->unindexedPosts = get_posts( array( 'include' => $post_ids ) );
		$indexer->index();

        $query = '
            query( $first: Int, $after: String, $where: RootQueryToSWPResultConnectionWhereArgs ) {
                searchWP(first: $first, after: $after, where: $where) {
                    nodes {
                        ... on Post {
                            id
                        }
                        ... on Page {
                            id
                        }
                    }
                }
            }
        ';

        /**
         * Assertion One
         */
        $variables = array(
            'input'    => 'Hello World',
            'postType' => array( 'Post' ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        array( 'id' => $this->relay_id( $post_ids[0], 'post' ) ),
                        array( 'id' => $this->relay_id( $post_ids[1], 'post' ) ),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Two
         * 
         * Tests "postIn" and "postNotIn" parameters.
         */
        $variables = array(
            'postIn'    => array( $post_ids[2], $post_ids[3] ),
            'postNotIn' => array( $post_ids[0], $post_ids[1] ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        array( 'id' => $this->relay_id( $post_ids[2], 'page' ) ),
                        array( 'id' => $this->relay_id( $post_ids[3], 'page' ) ),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Three
         * 
         * Tests "taxonomies" parameter.
         */
        $variables = array(
            'taxonomies' => array(
                array(
                    'taxonomy' => 'TAG',
                    'field'    => 'SLUG',
                    'terms'    => 'test_tag'
                ),
                array(
                    'taxonomy' => 'CATEGORY',
                    'field'    => 'SLUG',
                    'terms'    => 'test_category'
                )
            ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        array( 'id' => $this->relay_id( $post_ids[0], 'post' ) ),
                        array( 'id' => $this->relay_id( $post_ids[1], 'post' ) ),
                        array( 'id' => $this->relay_id( $post_ids[2], 'page' ) ),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Four
         * 
         * Tests "meta" parameter.
         */
        $variables = array(
            'meta' => array(
                array(
                    'key'     => 'test_meta',
                    'value'   => array( 'key-' . $post_ids[2], 'key-' . $post_ids[3] ),
                    'compare' => 'IN',
                ),
            ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        array( 'id' => $this->relay_id( $post_ids[2], 'page' ) ),
                        array( 'id' => $this->relay_id( $post_ids[3], 'page' ) ),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Five
         * 
         * Tests "date" parameter.
         */
        $variables = array(
            'date' => array(
                array(
                    'year'  => absint( date( 'Y', strtotime( '- 1 day' ) ) ),
                    'month' => absint( date( 'm', strtotime( '- 1 day' ) ) ),
                    'day'   => absint( date( 'd', strtotime( '- 1 day' ) ) ),
                ),
            ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        array( 'id' => $this->relay_id( $post_ids[1], 'post' ) ),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );
    }
}