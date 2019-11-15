<?php
/**
 * Resolves connections to SearchWP results.
 *
 * @package WPGraphQL\SearchWP\Data\Connection
 * @since 0.0.1
 */

namespace WPGraphQL\SearchWP\Data\Connection;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class SWP_Connection_Resolver
 */
class SWP_Connection_Resolver extends AbstractConnectionResolver {
	/**
	 * Confirms the uses has the privileges to query Products
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

	/**
	 * Creates query arguments array
	 */
	public function get_query_args() {
		global $wpdb;

		// Prepare for later use.
		$last  = ! empty( $this->args['last'] ) ? $this->args['last'] : null;
		$first = ! empty( $this->args['first'] ) ? $this->args['first'] : null;

		// Set the $query_args based on various defaults and primary input $args.
		$query_args = array(
			'fields' => 'ids',
		);

		/**
		 * Collect the input_fields and sanitize them to prepare them for sending to the WP_Query
		 */
		$input_fields = [];
		if ( ! empty( $this->args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $this->args['where'] );
		}

		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		/**
		 * Set the graphql_cursor_offset which is used by Config::graphql_wp_query_cursor_pagination_support
		 * to filter the WP_Query to support cursor pagination
		 */
		$cursor_offset                        = $this->get_offset();
		$query_args['graphql_cursor_offset']  = $cursor_offset;
		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';

		/**
		 * If the starting offset is not 0 sticky posts will not be queried as the automatic checks in wp-query don't
		 * trigger due to the page parameter not being set in the query_vars, fixes #732
		 */
		if ( 0 !== $cursor_offset ) {
			$query_args['ignore_sticky_posts'] = true;
		}

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $this->args;

		/**
		 * Filter the $query_args to allow folks to customize queries programmatically.
		 *
		 * @param array       $query_args The args that will be passed to the WP_Query.
		 * @param mixed       $source     The source that's passed down the GraphQL queries.
		 * @param array       $args       The inputArgs on the field.
		 * @param AppContext  $context    The AppContext passed down the GraphQL tree.
		 * @param ResolveInfo $info       The ResolveInfo passed down the GraphQL tree.
		 */
		$query_args = apply_filters( 'graphql_swp_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );

		return $query_args;
	}

	/**
	 * Executes query
	 *
	 * @return \SWP_Query
	 */
	public function get_query() {
		return new \SWP_Query( $this->get_query_args() );
	}

	/**
	 * Return an array of items from the query
	 *
	 * @return array
	 */
	public function get_items() {
		return ! empty( $this->query->posts ) ? $this->query->posts : array();
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_Query
	 * friendly keys. There's probably a cleaner/more dynamic way to approach this, but
	 * this was quick. I'd be down to explore more dynamic ways to map this, but for
	 * now this gets the job done.
	 *
	 * @param array $where_args - arguments being used to filter query.
	 *
	 * @return array
	 */
	public function sanitize_input_fields( array $where_args ) {
		$args = array();

		$args['s']      = ! empty( $where_args['input'] ) ? $where_args['input'] : '';
		$args['engine'] = ! empty( $where_args['engine'] ) ? $where_args['engine'] : 'default';
		if ( ! empty( $where_args['postIn'] ) ) {
			$args['post__in'] = $where_args['postIn'];
		}

		if ( ! empty( $where_args['postNotIn'] ) ) {
			$args['post__not_in'] = $where_args['postNotIn'];
		}

		if ( ! empty( $where_args['postType'] ) ) {
			$args['post_type'] = $where_args['postType'];
		}

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the WP_Query
		 *
		 * @param array       $args       The mapped query arguments
		 * @param array       $where_args Query "where" args
		 * @param mixed       $source     The query results for a query calling this
		 * @param array       $all_args   All of the arguments for the query (not just the "where" args)
		 * @param AppContext  $context    The AppContext object
		 * @param ResolveInfo $info       The ResolveInfo object
		 * @param mixed|string|array      $post_type  The post type for the query
		 */
		$args = apply_filters(
			'graphql_map_input_fields_to_swp_query',
			$args,
			$where_args,
			$this->source,
			$this->args,
			$this->context,
			$this->info,
			$this->post_type
		);

		return $args;
	}
}
