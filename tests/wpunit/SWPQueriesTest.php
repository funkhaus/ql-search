<?php

class SWPQueriesTest extends \Codeception\TestCase\WPTestCase {
    private static $admin;
    private static $post_objects;
    private static $indexer;

    public function wpSetUpBeforeClass( $factory ) {
        self::$admin        = $factory->user->create( array( 'role' => 'admin' ) );
        self::$post_objects = self::create_post_objects( $factory );

        // Initialize SearchWP Indexer.
        self::$indexer = new SearchWPIndexer();
        self::$indexer->unindexedPosts = self::$post_objects;
        self::$indexer->index();

        codecept_debug( SWP()->settings['engines']['default'] );

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

        codecept_debug( SWP()->settings['engines']['default'] );
    }

    public function wpTearDownAfterClass() {
        SWP()->purge_index();
    }
    
    public function setUp() {
        // before
        parent::setUp();

        // your set up methods here
    }

    public function tearDown() {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    private static function create_post_objects( $factory ) {
        $posts = array();

        $a_day_ago = date( 'Y-m-d H:i:s', strtotime( '- 1 day' ) );
        $category_id   = $factory->term->create(
            array(
                'name'     => 'Test category',
                'slug'     => 'test_category',
                'taxonomy' => 'category',
            )
        );
        $tag_id   = $factory->term->create(
            array(
                'name'     => 'Test tag',
                'slug'     => 'test_tag',
                'taxonomy' => 'post_tag',
            )
        );

        $post_args = array(
            array(
                'post_title'   => 'Post One',
                'post_content' => 'some content',
            ),
            array(
                'post_title'   => 'Post Two',
                'post_content' => 'some more content',
                'post_date' => $a_day_ago
            ),
            array(
                'post_title'   => 'Page One',
                'post_content' => 'some more content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Page Two',
                'post_content' => 'some content',
                'post_type' => 'page'
            ),
        );

        foreach ( $post_args as $new_post ) {
            $args = array_merge(
                array(
                    'post_author'  => self::$admin,
                    'post_status'  => 'publish',
                    'post_type'    => 'post',
                ),
                $new_post
            );
            $post_id = $factory->post->create_object( $args );
            update_post_meta( $post_id, 'test_meta', "key-{$post_id}" );
            $posts[] = get_post( $post_id );
        }

        wp_set_object_terms( $posts[0]->ID, $category_id, 'category' );
        wp_set_object_terms( $posts[1]->ID, $category_id, 'category' );
        wp_set_object_terms( $posts[2]->ID, $tag_id, 'post_tag' );

        return $posts;
    }

    private function expected_post_object( $index ) {
        $post = ! empty( self::$post_objects[ $index ] ) ? self::$post_objects[ $index ] : null;
        if ( ! $post ) {
            return null;
        }

        return array( 'id' =>  \GraphQLRelay\Relay::toGlobalId( $post->post_type, $post->ID ) );
    }

    // tests
    public function testSWPQuery() {
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
         * 
         * Tests basic search with "input".
         */
        $variables = array(
            'where' => array(
                'input'    => 'One',
                'postType' => 'PAGE',
            )
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        $this->expected_post_object(2),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Two
         * 
         * Tests "postIn" parameter.
         */
        $variables = array(
            'where' => array(
                'input'     => 'One',
                'postIn'    => array( self::$post_objects[2]->ID ),
            ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        $this->expected_post_object(2),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Three
         * 
         * Tests "postNotIn" parameter.
         */
        $variables = array(
            'where' => array(
                'input'     => 'Post Page',
                'postNotIn' => array( self::$post_objects[0]->ID, self::$post_objects[3]->ID, ),
            ),
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );
        
        // use --debug flag to view
        codecept_debug( $actual );

        $expected = array(
            'data' => array(
                'searchWP' => array(
                    'nodes' => array(
                        $this->expected_post_object(2),
                        $this->expected_post_object(1),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Four
         * 
         * Tests "taxonomies" parameter.
         */
        $variables = array(
            'where' => array(
                'input'      => 'content',
                'taxonomies' => array(
                    'relation' => 'OR',
                    'taxArray' => array(
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
                    )
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
                        $this->expected_post_object(2),
                        $this->expected_post_object(0),
                        $this->expected_post_object(1),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Five
         * 
         * Tests "meta" parameter.
         */
        $variables = array(
            'where' => array(
                'input' => 'some content',
                'meta'  => array(
                    'relation'  => 'OR',
                    'metaArray' => array(
                        array(
                            'key'     => 'test_meta',
                            'value'   => 'key-' . self::$post_objects[0]->ID,
                            'compare' => 'EQUAL_TO',
                        ),
                        array(
                            'key'     => 'test_meta',
                            'value'   => 'key-' . self::$post_objects[2]->ID,
                            'compare' => 'EQUAL_TO',
                        ),
                    ),
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
                        $this->expected_post_object(2),
                        $this->expected_post_object(0),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );

        /**
         * Assertion Six
         * 
         * Tests "date" parameter.
         */
        $variables = array(
            'where' => array(
                'input' => 'some content',
                'date'  => array(
                    array(
                        'year'  => absint( date( 'Y', strtotime( '- 1 day' ) ) ),
                        'month' => absint( date( 'm', strtotime( '- 1 day' ) ) ),
                        'day'   => absint( date( 'd', strtotime( '- 1 day' ) ) ),
                    ),
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
                        $this->expected_post_object(1),
                    )
                )
            )
        );

        $this->assertEquals( $expected, $actual );
    }
}