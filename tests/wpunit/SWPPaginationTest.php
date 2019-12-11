<?php

class SWPPaginationTest extends \Codeception\TestCase\WPTestCase {
    private static $admin;
    private static $post_objects;
    private static $indexer;

    public function wpSetUpBeforeClass( $factory ) {
        self::$admin        = $factory->user->create( array( 'role' => 'admin' ) );
        self::$post_objects = self::create_post_objects( $factory );

        // Initialize SearchWP Indexer.
        swp_index_test_posts( self::$post_objects );
    }

    public static function wpTearDownAfterClass() {
        swp_purge_index();
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
        $posts       = array();

        $post_args   = array(
            array(
                'post_title'   => 'Post One',
                'post_content' => 'some content',
            ),
            array(
                'post_title'   => 'Post Two',
                'post_content' => 'some more content',
            ),
            array(
                'post_title'   => 'Page One',
                'post_content' => 'some more content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Page Two',
                'post_content' => 'some content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Post Three',
                'post_content' => 'some content',
            ),
            array(
                'post_title'   => 'Post Four',
                'post_content' => 'some more content',
            ),
            array(
                'post_title'   => 'Page Three',
                'post_content' => 'some more content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Page Four',
                'post_content' => 'some content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Post Five',
                'post_content' => 'some content',
            ),
            array(
                'post_title'   => 'Post Six',
                'post_content' => 'some more content',
            ),
            array(
                'post_title'   => 'Page Five',
                'post_content' => 'some more content',
                'post_type'    => 'page'
            ),
            array(
                'post_title'   => 'Page Six',
                'post_content' => 'some content',
                'post_type'    => 'page'
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
            $posts[] = get_post( $post_id );
        }

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
    public function testSWPPaginationDESC() {
        $query = '
            query( $first: Int, $after: String, $where: RootQueryToSWPResultConnectionWhereArgs ) {
                searchWP(first: $first, after: $after, where: $where) {
                    nodes {
                        ... on Post {
                            id
                            title
                        }
                        ... on Page {
                            id
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        /**
         * Assertion One
         * 
         * Get first four results
         */
        $variables = array(
            'first' => 4,
            'where' => array(
                'input'    => 'Post',
            )
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );

        // use --debug flag to view
        codecept_debug( $actual );

        // Test search info
        $this->assertNotEmpty( $actual['data'] );
        $this->assertNotEmpty( $actual['data']['searchWP'] );
        $this->assertNotEmpty( $actual['data']['searchWP']['pageInfo'] );
        $this->assertTrue( $actual['data']['searchWP']['pageInfo']['hasNextPage'] );
        $end_cursor = $actual['data']['searchWP']['pageInfo']['endCursor'];

        // Test search results
        $expected = array(
            $this->expected_post_object(0),
            $this->expected_post_object(1),
            $this->expected_post_object(4),
            $this->expected_post_object(5),
        );
        $this->assertNotEmpty( $actual['data']['searchWP']['nodes'] );
        $this->assertEquals( $expected, $actual['data']['searchWP']['nodes'] );

        /**
         * Assertion Two
         * 
         * Get last two results after previous query end cursor
         */
        $variables = array(
            'first' => 4,
            'after' => $end_cursor,
            'where' => array(
                'input'    => 'Post',
            )
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );

        // use --debug flag to view
        codecept_debug( $actual );

        // Test search info
        $this->assertNotEmpty( $actual['data'] );
        $this->assertNotEmpty( $actual['data']['searchWP'] );
        $this->assertNotEmpty( $actual['data']['searchWP']['pageInfo'] );
        $this->assertFalse( $actual['data']['searchWP']['pageInfo']['hasNextPage'] );
        $end_cursor = $actual['data']['searchWP']['pageInfo']['endCursor'];

        // Test search results
        $expected = array(
            $this->expected_post_object(8),
            $this->expected_post_object(9),
        );
        $this->assertNotEmpty( $actual['data']['searchWP']['nodes'] );
        $this->assertEquals( $expected, $actual['data']['searchWP']['nodes'] );
    }

    // tests
    public function testSWPPaginationASC() {
        $query = '
            query( $last: Int, $before: String, $where: RootQueryToSWPResultConnectionWhereArgs ) {
                searchWP(last: $last, before: $before, where: $where) {
                    nodes {
                        ... on Post {
                            id
                            title
                        }
                        ... on Page {
                            id
                        }
                    }
                    pageInfo {
                        hasPreviousPage
                        startCursor
                    }
                }
            }
        ';

        /**
         * Assertion One
         * 
         * Get last four results
         */
        $variables = array(
            'last' => 4,
            'where' => array(
                'input'    => 'Page',
            )
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );

        // use --debug flag to view
        codecept_debug( $actual );

        // Test search info
        $this->assertNotEmpty( $actual['data'] );
        $this->assertNotEmpty( $actual['data']['searchWP'] );
        $this->assertNotEmpty( $actual['data']['searchWP']['pageInfo'] );
        $this->assertTrue( $actual['data']['searchWP']['pageInfo']['hasPreviousPage'] );
        $start_cursor = $actual['data']['searchWP']['pageInfo']['startCursor'];

        // Test search results
        $expected = array(
            $this->expected_post_object(11),
            $this->expected_post_object(10),
            $this->expected_post_object(7),
            $this->expected_post_object(6),
        );
        $this->assertNotEmpty( $actual['data']['searchWP']['nodes'] );
        $this->assertEquals( $expected, $actual['data']['searchWP']['nodes'] );

        /**
         * Assertion Two
         * 
         * Get first two results before previous query start cursor
         */
        $variables = array(
            'last' => 4,
            'after' => $start_cursor,
            'where' => array(
                'input'    => 'Page',
            )
        );
        $actual = graphql( array( 'query' => $query, 'variables' => $variables ) );

        // use --debug flag to view
        codecept_debug( $actual );

        // Test search info
        $this->assertNotEmpty( $actual['data'] );
        $this->assertNotEmpty( $actual['data']['searchWP'] );
        $this->assertNotEmpty( $actual['data']['searchWP']['pageInfo'] );
        $this->assertFalse( $actual['data']['searchWP']['pageInfo']['hasNextPage'] );
        $end_cursor = $actual['data']['searchWP']['pageInfo']['endCursor'];

        // Test search results
        $expected = array(
            $this->expected_post_object(3),
            $this->expected_post_object(2),
        );
        $this->assertNotEmpty( $actual['data']['searchWP']['nodes'] );
        $this->assertEquals( $expected, $actual['data']['searchWP']['nodes'] );
    }
}