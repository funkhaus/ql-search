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
use SearchWP\Query;

/**
 * Class SWP_Connection_Resolver
 */
class SWP_Connection_Resolver extends AbstractConnectionResolver {
	/**
	 * The name of the post type, or array of post types the connection resolver is resolving for
	 *
	 * @var string|array
	 */
	protected $post_types;

	/**
	 * Mods to use in query
	 *
	 * @var \SearchWP\Mods[]
	 */
	protected $mods;

	/**
	 * Query search input.
	 *
	 * @var string
	 */
	protected $search_input;

	/**
	 * SWP_Connection_Resolver constructor.
	 *
	 * @param mixed       $source    The object passed down from the previous level in the Resolve tree.
	 * @param array       $args      The input arguments for the query.
	 * @param AppContext  $context   The context of the request.
	 * @param ResolveInfo $info      The resolve info passed down the Resolve tree.
	 */
	public function __construct( $source, $args, $context, $info ) {
		$this->post_types = ! empty( $args['where'] ) && ! empty( $args['where']['postType'] )
			? $args['where']['postType']
			: get_post_types(
				array(
					'exclude_from_search' => false,
					'show_in_graphql'     => true,
				)
			);

		$this->init_mods();

		/**
		 * Call the parent construct to setup class data
		 */
		parent::__construct( $source, $args, $context, $info );
	}

	private function init_mods() {
		$this->mods = array( new \SearchWP\Mod() );
		foreach( $this->post_types as $post_type ) {
			$source = \SearchWP\Utils::get_post_type_source_name( $post_type );
			$this->mods[ $post_type ] = new \SearchWP\Mod( $source );
		}
	}

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
			'fields'   => 'default',
			'per_page' => min( max( absint( $first ), absint( $last ), 10 ), $this->query_amount ) + 1,
		);

		/**
		 * Collect the input_fields and sanitize them to prepare them for sending to the WP_Query
		 */
		$input_fields = array();
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
		$cursor_offset = $this->get_offset();
		$compare       = ( ! empty( $last ) ) ? '>' : '<';

		/**
		 * If the starting offset is not 0 sticky posts will not be queried as the automatic checks in wp-query don't
		 * trigger due to the page parameter not being set in the query_vars, fixes #732
		 */
		if ( 0 !== $cursor_offset ) {
			$query_args['ignore_sticky_posts'] = true;
		}

		// Don't order search results by title (causes funky issues with cursors).
		$this->set_direction( isset( $last ) ? 'ASC' : 'DESC' );
		if ( $cursor_offset ) {
			$this->set_cursor( $cursor_offset, $compare );
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

		//wp_send_json( $query_args );

		return $query_args;
	}

	/**
	 * Return the name of the loader
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'post';
	}

	/**
	 * Executes query
	 *
	 * @return \SWP_Query
	 */
	public function get_query() {
		$args  = array_merge( $this->query_args, array( 'mods' => $this->get_mods() ) );
		//wp_send_json( $args );
		$query = new Query( $this->search_input, $args );

		return $query;
	}

	private function get_mods() {
		return array_values( $this->mods );
	}

	public function add_weight( $sql, $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$this->mods[0]->weight( $sql );
			return;
		}

		foreach( $post_types as $post_type ) {
			$this->mods[ $post_type ]->weight( $sql );
		}
	}

	public function set_direction( $direction ) {
		$this->mods[0]->order_by( 's.relevance', $direction, 1 );
		$this->mods[0]->order_by( 's.id', $direction, 2 );
	}

	public function set_cursor( $cursor, $compare ) {
		$mod = new \SearchWP\Mod();
		$mod->set_where(
			array(
				array(
					'column'  => 'id',
					'value'   => $cursor,
					'compare' => $compare,
					'type'    => 'NUMERIC',
				),
			),
		);

		$this->mods[] = $mod;
	}

	/**
	 * Return an array of items from the query
	 *
	 * @return array
	 */
	public function get_ids() {
		if ( empty( $this->results ) ) {
			$this->results = array_map(
				function( $result ) {
					return (array) $result;
				},
				$this->query->get_results()
			);
		}

		return array_column( $this->results, 'id' );
	}

	/**
	 * Get_edges
	 *
	 * This iterates over the nodes and returns edges
	 *
	 * @return array
	 */
	public function get_edges() {
		$edges = parent::get_edges();

		foreach( $edges as $i => $edge ) {
			$edge_id = $edge['node']->ID;
			$result  = array_filter(
				$this->results,
				function( $result ) use ( $edge_id ) {
					return absint( $result['id'] ) === absint( $edge_id );
				}
			);

			if ( empty( $result ) ) {
				continue;
			}

			$result      = array_pop( $result );
			$edges[ $i ] = array_merge(
				$edge,
				array(
					'site'      => $result['site'],
					'source'    => $result['source'],
					'relevance' => $result['relevance'],
				)
			);
		}

		return $edges;
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
		global $wpdb;

		$args = array();

		$args['engine'] = ! empty( $where_args['engine'] ) ? $where_args['engine'] : 'default';
		$args['engine'] = new \SearchWP\Engine( $args['engine'] );

		$this->search_input = ! empty( $where_args['input'] ) ? $where_args['input'] : '';

		$post_types = $this->post_types;
		if ( ! empty( $where_args['postType'] ) ) {
			$post_types = $where_args['postType'];

			foreach ( array_keys( $args['engine']->get_sources() ) as $engine_source ) {
				if (
					// Not a WP_Post Source.
					'post' . SEARCHWP_SEPARATOR !== substr( $engine_source, 0, strlen( 'post' . SEARCHWP_SEPARATOR ) )
					|| (
						// Not in the post_type arg.
						! empty( $post_types )
						&& ! in_array( substr( $engine_source, strlen( 'post' . SEARCHWP_SEPARATOR ) ), $post_types )
					)
				) {
					$args['engine']->remove_source( $engine_source );

					continue;
				}
			}
		}


		if ( ! empty( $where_args['postIn'] ) ) {
			$mod = new \SearchWP\Mod();
			$mod->set_where(
				array(
					array(
						'column'  => 'id',
						'value'   => $where_args['postIn'],
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					)
				)
			);

			$this->mods[] = $mod;
		}

		if ( ! empty( $where_args['postNotIn'] ) ) {
			$mod = new \SearchWP\Mod();
			$mod->set_where(
				array(
					array(
						'column'  => 'id',
						'value'   => $where_args['postNotIn'],
						'compare' => 'NOT IN',
						'type'    => 'NUMERIC',
					)
				)
			);

			$this->mods[] = $mod;
		}

		if ( ! empty( $where_args['taxonomies'] ) ) {
			$tax_query = $where_args['taxonomies']['taxArray']; // WPCS: slow query ok.
			if ( ! empty( $where_args['taxonomies']['relation'] ) && count( $where_args['taxonomies']['taxArray'] ) > 1 ) {
				$tax_query['relation'] = $where_args['taxonomies']['relation'];
			}

			// We need to do a bit of detective work depending on the tax_query.
			$alias     = 'swpquerytax';
			$tax_query = new \WP_Tax_Query( $tax_query );
			$tq_sql    = $tax_query->get_sql( $alias, 'ID' );
			$mod       = new \SearchWP\Mod();

			// If the JOIN is empty, WP_Tax_Query assumes we have a JOIN with wp_posts, so let's make that.
			if ( ! empty( $tq_sql['join'] ) ) {
				// Queue the assumed wp_posts JOIN using our alias.
				$mod->raw_join_sql( function( $runtime ) use ( $wpdb, $alias ) {
					return "LEFT JOIN {$wpdb->posts} {$alias} ON {$alias}.ID = {$runtime->get_foreign_alias()}.id";
				} );

				// Queue the WP_Tax_Query JOIN which already has our alias.
				$mod->raw_join_sql( $tq_sql['join'] );

				// Queue the WP_Tax_Query WHERE which already has our alias.
				$mod->raw_where_sql( '1=1 ' . $tq_sql['where'] );
			} else {
				// There's no JOIN here because WP_Tax_Query assumes a JOIN with wp_posts already
				// exists. We need to rebuild the tax_query SQL to use a functioning alias. The Mod
				// will ensure the JOIN, and we can use that Mod's alias to rebuild our tax_query.
				$mod->set_local_table( $wpdb->posts );
				$mod->on( 'ID', [ 'column' => 'id' ] );

				$mod->raw_where_sql( function( $runtime ) use ( $tax_query ) {
					$tq_sql = $tax_query->get_sql( $runtime->get_local_table_alias(), 'ID' );

					return '1=1 ' . $tq_sql['where'];
				} );
			}

			$this->mods[] = $mod;
		}

		if ( ! empty( $where_args['meta'] ) ) {
			$meta_query = $where_args['meta']['metaArray']; // WPCS: slow query ok.
			if ( ! empty( $where_args['meta']['relation'] ) && count( $where_args['meta']['metaArray'] ) > 1 ) {
				$meta_query['relation'] = $where_args['meta']['relation'];
			}

			$alias      = 'swpquerymeta';
			$meta_query = new \WP_Meta_Query( $meta_query );
			$mq_sql     = $meta_query->get_sql( 'post', $alias, 'ID', null );

			$mod = new \SearchWP\Mod();
			$mod->set_local_table( $wpdb->posts );
			$mod->on( 'ID', [ 'column' => 'id' ] );

			$mod->raw_join_sql( function( $runtime ) use ( $mq_sql, $alias ) {
				return str_replace( $alias, $runtime->get_local_table_alias(), $mq_sql['join'] );
			} );

			$mod->raw_where_sql( function( $runtime ) use ( $mq_sql, $alias ) {
				return '1=1 ' . str_replace( $alias, $runtime->get_local_table_alias(), $mq_sql['where'] );
			} );

			$this->mods[] = $mod;
		}

		if ( ! empty( $where_args['date'] ) ) {
			$date_query = $where_args['date'];

			$date_query = new \WP_Date_Query( (array) $date_query );
			$dq_sql     = $date_query->get_sql();

			$mod = new \SearchWP\Mod();
			$mod->set_local_table( $wpdb->posts );
			$mod->on( 'ID', [ 'column' => 'id' ] );

			$mod->raw_where_sql( function( $runtime ) use ( $dq_sql ) {
				global $wpdb;

				return '1=1 ' . str_replace( $wpdb->posts . '.', $runtime->get_local_table_alias() . '.', $dq_sql );
			} );

			$this->mods[] = $mod;
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
			$this->post_types
		);

		return $args;
	}

	/**
	 * Determine whether or not the the offset is valid, i.e the post corresponding to the offset exists.
	 * Offset is equivalent to post_id. So this function is equivalent
	 * to checking if the post with the given ID exists.
	 *
	 * @param integer $offset  Post ID.
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		global $wpdb;

		if ( ! empty( wp_cache_get( $offset, 'posts' ) ) ) {
			return true;
		}

		return $wpdb->get_var( $wpdb->prepare( "SELECT EXISTS (SELECT 1 FROM $wpdb->posts WHERE ID = %d)", $offset ) );
	}
}
