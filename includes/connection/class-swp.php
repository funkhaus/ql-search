<?php
/**
 * Registers connections used for SearchWP.
 *
 * @package WPGraphQL\SearchWP\Connection
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Connection;

use WPGraphQL\SearchWP\Data\Factory;

/**
 * Class - SWP
 */
class SWP {
	/**
	 * Registers the various connections from other Types to CartItem
	 */
	public static function register_connections() {
		// From RootQuery.
		register_graphql_connection( self::get_connection_config() );
	}

	/**
	 * Given an array of $args, this returns the connection config, merging the provided args
	 * with the defaults
	 *
	 * @access public
	 * @param array $args - Connection configuration.
	 *
	 * @return array
	 */
	public static function get_connection_config( $args = array() ) {
		$defaults = array(
			'fromType'       => 'RootQuery',
			'toType'         => 'ContentNode',
			'fromFieldName'  => 'searchWP',
			'connectionArgs' => self::get_connection_args(),
			'edgeFields'     => self::get_edge_fields(),
			'resolve'        => function ( $source, $args, $context, $info ) {
				return Factory::resolve_swp_connection( $source, $args, $context, $info );
			},
		);
		return array_merge( $defaults, $args );
	}

	/**
	 * Returns array of where args
	 *
	 * @return array
	 */
	public static function get_connection_args() {
		return array(
			'input'      => array(
				'type'        => array( 'non_null' => 'String' ),
				'description' => __( 'The search query', 'ql-search' ),
			),
			'engine'     => array(
				'type'        => 'String',
				'description' => __( 'The SearchWP engine to use (default: default)', 'ql-search' ),
			),
			'postType'   => array(
				'type'        => array( 'list_of' => 'ContentTypeEnum' ),
				'description' => __( 'Override the engine configuration with an array of post types', 'ql-search' ),
			),
			'nopaging'   => array(
				'type'        => 'Boolean',
				'description' => __( 'Disable pagination and return all posts', 'ql-search' ),
			),
			'postIn'     => array(
				'type'        => array( 'list_of' => 'Int' ),
				'description' => __( 'Array of post IDs to limit results to', 'ql-search' ),
			),
			'postNotIn'  => array(
				'type'        => array( 'list_of' => 'Int' ),
				'description' => __( 'Array of post IDs to exclude from results', 'ql-search' ),
			),
			'taxonomies' => array(
				'type'        => 'SearchWPTaxQueryInput',
				'description' => __( 'Filter results by taxonomy', 'ql-search' ),
			),
			'meta'       => array(
				'type'        => 'SearchWPMetaQueryInput',
				'description' => __( 'Filter results by meta', 'ql-search' ),
			),
			'date'       => array(
				'type'        => array( 'list_of' => 'DateInput' ),
				'description' => __( 'Filter results by date', 'ql-search' ),
			),
		);
	}

	public static function get_edge_fields() {
		$fields = array(
			'source'    => array(
				'type'        => 'String',
				'description' => __( 'Result source type', 'ql-search' ),
				'resolve'     => function( $source ) {
					return ! empty( $source['source'] ) ? $source['source'] : null;
				}
			),
			'relevance' => array(
				'type'        => 'Int',
				'description' => __( 'Result relevance.', 'ql-search' ),
				'resolve'     => function( $source ) {
					return absint( $source['relevance'] );
				}
			),
		);

		if ( is_multisite() ) {
			$fields = array_merge(
				$fields,
				array(
					'site'      => array(
						'type'        => 'String',
						'description' => __( 'Name of site result being to.', 'ql-search' ),
						'resolve'     => function( $source ) {
							$site = \get_blog_details( $source['site'] );
							return ! empty( $site->blogname ) ? $site->blogname : null;
						}
					),
				)
			);
		}

		return $fields;
	}
}
