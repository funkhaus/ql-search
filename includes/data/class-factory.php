<?php
/**
 * Factory
 *
 * This class serves as a factory for all the resolvers of queries and mutations.
 *
 * @package WPGraphQL\SearchWP\Data
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Data;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\SearchWP\Data\Connection\SWP_Connection_Resolver;

/**
 * Class Factory
 */
class Factory {
	/**
	 * Returns the GraphQL object for the post ID
	 *
	 * @param int         $id       post ID of the crud object being retrieved.
	 * @param AppContext  $context  AppContext object.
	 * @param ResolveInfo $info     ResolveInfo object.
	 *
	 * @throws UserError Invalid ID.
	 * @return Deferred object
	 * @access public
	 */
	public static function resolve_swp_result( $id, $context, $info ) {
		$post_type = get_post_type( $id );
		if ( ! $post_type ) {
			throw new UserError(
				/* translators: %s: post_id */
				sprintf( __( '%s is not an invalid CPT ID', 'ql-search' ), $id )
			);
		}

		return DataSource::resolve_node(
			Relay::toGlobalId( $post_type, $id ),
			$context,
			$info
		);
	}

	/**
	 * Resolves Search connections
	 *
	 * @param mixed       $source     - Data resolver for connection source.
	 * @param array       $args       - Connection arguments.
	 * @param AppContext  $context    - AppContext object.
	 * @param ResolveInfo $info       - ResolveInfo object.
	 *
	 * @return array
	 * @access public
	 */
	public static function resolve_swp_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new SWP_Connection_Resolver( $source, $args, $context, $info );
		return $resolver->get_connection();
	}
}
