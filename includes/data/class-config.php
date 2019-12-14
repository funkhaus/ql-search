<?php
/**
 * Defines hooks that alter SearchWP's data fetching functionality.
 *
 * @package WPGraphQL\SearchWP\Data
 * @since 0.0.1
 */

namespace WPGraphQL\SearchWP\Data;

use WPGraphQL\SearchWP\Data\Cursor\SWP_Query_Cursor;

/**
 * Class - Config
 */
class Config {
	/**
	 * Holds captured SWP Query args.
	 *
	 * @var array
	 */
	public $query_args;

	/**
	 * Config constructor
	 */
	public function __construct() {
		/**
		 * Hook SWP Query args for reuse in later in request.
		 */
		add_filter( 'searchwp_swp_query_args', array( $this, 'capture_swp_query_args' ), 10 );

		/**
		 * Filter the SWP_Query to support cursor based pagination where a post ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter( 'searchwp_where', array( $this, 'graphql_swp_query_cursor_pagination_support' ), 10, 3 );

		/**
		 * Filter the SWP_Query to stabilize pagination.
		 */
		add_filter( 'searchwp_query_orderby', array( $this, 'graphql_swp_query_cursor_pagination_stability' ), 10, 3 );
	}

	/**
	 * If "GraphQL" request, the query args are captured and pagination support filter hooked.
	 *
	 * @param array $args  SWP Query args.
	 *
	 * @return array
	 */
	public function capture_swp_query_args( $args ) {
		if ( true === is_graphql_request() ) {
			$this->query_args = $args;
		}

		return $args;
	}

	/**
	 * Modifies SWP Query's SQL where clauses to provided cursor-based pagination.
	 *
	 * @param string         $where   SQL WHERE statement of the SWP_Query.
	 * @param array          $engine  Engine being used in the search.
	 * @param SearchWPSearch $query   Query object.
	 *
	 * @return string.
	 */
	public function graphql_swp_query_cursor_pagination_support( $where, $engine, $query ) {
		if ( true === is_graphql_request() ) {
			$cursor = new SWP_Query_Cursor( $query, $this->query_args );

			return "{$where} {$cursor->get_where()}";
		}

		return $where;
	}

	/**
	 * Modifies SWP Query's SQL orderby clause.
	 *
	 * @param string         $orderby  SQL ORDERBY statement of the SWP_Query.
	 * @param array          $engine   Engine being used in the search.
	 * @param SearchWPSearch $query    Query object.
	 */
	public function graphql_swp_query_cursor_pagination_stability( $orderby, $engine, $query ) {
		if ( true === is_graphql_request() ) {
			$cursor = new SWP_Query_Cursor( $query, $this->query_args );

			return $cursor->get_orderby();
		}

		return $orderby;
	}
}
