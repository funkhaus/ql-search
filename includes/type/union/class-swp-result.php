<?php
/**
 * Registers "SWPResult" type.
 *
 * @package \WPGraphQL\SearchWP\Type\WPUnion
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPUnion;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\WooCommerce\Data\Factory;

/**
 * Class - SWP_Result
 */
class SWP_Result {
	/**
	 * Registers type.
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_union( $type_registry ) {
		$type_registry->register_union_type(
			'SWPResult',
			array(
				'name'        => 'SWPResult',
				'typeNames'   => self::get_possible_types(),
				'description' => __( 'SearchWP result.', 'ql-search' ),
				'resolveType' => function( $value ) use ( $type_registry ) {
					$type      = null;
					$type_name = DataSource::resolve_node_type( $value );
					if ( ! empty( $type_name ) ) {
						$type = $type_registry->get_type( $type_name );
					}

					return ! empty( $type ) ? $type : null;
				},
			)
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
	 * @access public
	 * @return array
	 */
	public static function get_possible_types() {
		$possible_types     = [];
		$allowed_post_types = get_post_types(
			array(
				'exclude_from_search' => false,
				'show_in_graphql'     => true,
			)
		);
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $allowed_post_type ) {
				if ( empty( $possible_types[ $allowed_post_type ] ) ) {
					$post_type_object = get_post_type_object( $allowed_post_type );
					if ( isset( $post_type_object->graphql_single_name ) ) {
						$possible_types[] = $post_type_object->graphql_single_name;
					}
				}
			}
		}
		return apply_filters( 'graphql_swp_result_possible_types', $possible_types );
	}
}
