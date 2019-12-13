<?php
/**
 * Creates a extra SQL WHERE statement for the purpose of executing
 * cursor-based pagination on the query results.
 *
 * @package WPGraphQL\SearchWP\Data\Cursor
 * @since 0.0.1
 */

namespace WPGraphQL\SearchWP\Data\Cursor;

use WPGraphQL\Data\Cursor\CursorBuilder;

/**
 * Class - SWP_Query_Cursor
 */
class SWP_Query_Cursor {
	/**
	 * The global wpdb instance
	 *
	 * @var $wpdb
	 */
	public $wpdb;

	/**
	 * The current post id which is our cursor offset
	 *
	 * @var $post_type
	 */
	public $cursor_offset;

	/**
	 * Copy of query args so we can modify them safely
	 *
	 * @var array
	 */
	public $query_args = null;

	/**
	 * Stores the instance of CursorBuilder
	 *
	 * @var CursorBuilder
	 */
	public $builder;

	/**
	 * Store the (potentially) filtered terms to save on redundant queries
	 *
	 * @var array
	 */
	public $terms_final = array();

	/**
	 * SWP_Query_Cursor constructor
	 *
	 * @param SearchWPSearch $query       SWPQuery object.
	 * @param array          $query_args  SWP_Query args.
	 */
	public function __construct( $query, $query_args ) {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->query      = $query;
		$this->query_args = $query_args;

		/**
		 * Get the cursor offset if any
		 */
		$offset              = $query_args['graphql_cursor_offset'];
		$this->cursor_offset = ! empty( $offset ) ? $offset : 0;

		/**
		 * Get the direction for the query builder
		 */
		$compare       = ! empty( $query_args['graphql_cursor_compare'] ) ? $query_args['graphql_cursor_compare'] : '>';
		$this->compare = in_array( $compare, array( '>', '<' ), true ) ? $compare : '>';

		$this->builder = new CursorBuilder( $compare );
	}

	/**
	 * Cache the final term(s) after filtering to prevent redundant queries
	 *
	 * @param string $term  Term being cached.
	 *
	 * @since 2.3
	 */
	private function cache_term_final( $term ) {
		// $term has been prepared already
		$this->terms_final = array_merge( $this->terms_final, $term );
		$this->terms_final = array_filter( $this->terms_final, 'strlen' );
		$this->terms_final = array_unique( $this->terms_final );
	}

	/**
	 * Generate the SQL used to open the per-post type sub-query.
	 *
	 * @param array  $args            Arguments for the post type.
	 * @param string $extra_sql_join  Extra SQL table joins statements.
	 *
	 * @return string
	 */
	public function query_post_type_open( $args, $extra_sql_join = '' ) {
		$defaults = array(
			'post_type'      => 'post',
			'post_column'    => 'ID',
			'title_weight'   => function_exists( 'searchwp_get_engine_weight' ) ? searchwp_get_engine_weight( 'title' ) : 20,
			'slug_weight'    => function_exists( 'searchwp_get_engine_weight' ) ? searchwp_get_engine_weight( 'slug' ) : 10,
			'content_weight' => function_exists( 'searchwp_get_engine_weight' ) ? searchwp_get_engine_weight( 'content' ) : 2,
			'comment_weight' => function_exists( 'searchwp_get_engine_weight' ) ? searchwp_get_engine_weight( 'comment' ) : 1,
			'excerpt_weight' => function_exists( 'searchwp_get_engine_weight' ) ? searchwp_get_engine_weight( 'excerpt' ) : 6,
			'custom_fields'  => 0,
			'taxonomies'     => 0,
			'attributed_to'  => false,
		);

		// Process our arguments.
		$args = wp_parse_args( $args, $defaults );

		if ( ! post_type_exists( $args['post_type'] ) ) {
			wp_die( 'Invalid request', 'searchwp' );
		}

		$post_type = $args['post_type'];

		$post_column = $args['post_column'];
		if ( ! in_array( $post_column, array( 'post_parent', 'ID' ), true ) ) {
			$post_column = 'ID';
		}

		$title_weight   = absint( $args['title_weight'] );
		$slug_weight    = absint( $args['slug_weight'] );
		$content_weight = absint( $args['content_weight'] );
		$comment_weight = absint( $args['comment_weight'] );
		$excerpt_weight = absint( $args['excerpt_weight'] );

		$wrap_core_weights  = apply_filters( 'searchwp_weight_mods_wrap_core_weights', false );
		$core_weight_prefix = $wrap_core_weights ? '(' : '';
		$core_weight_suffix = $wrap_core_weights ? ')' : '';

		$sql = "
            LEFT JOIN (
                SELECT {$this->wpdb->prefix}posts.{$post_column} AS post_id,
                    {$core_weight_prefix}( SUM( {$this->query->db_prefix}index.title ) * {$title_weight} ) +
                    ( SUM( {$this->query->db_prefix}index.slug ) * {$slug_weight} ) +
                    ( SUM( {$this->query->db_prefix}index.content ) * {$content_weight} ) +
                    ( SUM( {$this->query->db_prefix}index.comment ) * {$comment_weight} ) +
                    ( SUM( {$this->query->db_prefix}index.excerpt ) * {$excerpt_weight} ) +
                    {$args['custom_fields']} + {$args['taxonomies']}{$core_weight_suffix}";

		// Allow developers to inject their own weight modifications.
		$sql .= apply_filters( 'searchwp_weight_mods', '', array( 'engine' => $this->query->engine ) );

		// The identifier is different if we're attributing.
		$sql .= ! empty( $args['attributed_to'] ) ? " AS `{$post_type}attr` " : " AS `{$post_type}weight` ";

		$sql .= "
            FROM {$this->query->db_prefix}terms
            LEFT JOIN {$this->query->db_prefix}index ON {$this->query->db_prefix}terms.id = {$this->query->db_prefix}index.term
            LEFT JOIN {$this->wpdb->prefix}posts ON {$this->query->db_prefix}index.post_id = {$this->wpdb->prefix}posts.ID
            {$extra_sql_join}
		";

		return $sql;
	}

	/**
	 * Generate the SQL that extracts Custom Field weights
	 *
	 * @param string $post_type       The post type.
	 * @param array  $weights         Custom Field weights from SearchWP Settings.
	 * @param string $sql_term_where  SQL Where statement.
	 * @param string $sql_status      SQL posts status statement.
	 * @param string $extra_sql_join  Extra SQL table joins statements.
	 * @param string $sql_conditions  Extra SQL conditions.
	 * @return string
	 */
	public function query_post_type_custom_field_weights( $post_type, $weights, $sql_term_where, $sql_status, $extra_sql_join = '', $sql_conditions = '' ) {
		// First we'll try to merge any matching weight meta_keys so as to save as many JOINs as possible.
		$optimized_weights = array();
		$like_weights      = array();
		foreach ( $weights as $post_type_meta_guid => $post_type_custom_field ) {

			$custom_field_weight        = $post_type_custom_field['weight'];
			$post_type_custom_field_key = $post_type_custom_field['metakey'];

			if ( false !== strpos( $custom_field_weight, '.' ) ) {
				$custom_field_weight = (string) abs( floatval( $custom_field_weight ) );
			} else {
				$custom_field_weight = (string) absint( $custom_field_weight );
			}

			// Allow developers to implement LIKE matching on custom field keys.
			if ( false === strpos( $post_type_custom_field_key, '%' ) ) {
				$optimized_weights[ $custom_field_weight ][] = $post_type_custom_field_key;
			} else {
				$like_weights[] = array(
					'metakey' => $post_type_custom_field_key,
					'weight'  => $custom_field_weight,
				);
			}
		}

		$column = 'ID';

		/**
		 * Our custom fields are now keyed by their weight, allowing us to group Custom Fields with the
		 * same weight together in the same LEFT JOIN
		 */
		$sql = '';
		$i   = 0;
		foreach ( $optimized_weights as $weight_key => $meta_keys_for_weight ) {
			$post_meta_clause = '';
			if ( ! in_array( 'searchwpcfdefault', str_ireplace( ' ', '', $meta_keys_for_weight ), true ) ) {

				if ( is_array( $meta_keys_for_weight ) ) {
					foreach ( $meta_keys_for_weight as $key => $val ) {
						$meta_keys_for_weight[ $key ] = $wpdb->prepare( '%s', $val );
					}
				}

				$post_meta_clause = ' AND ' . $this->query->db_prefix . 'cf.metakey IN (' . implode( ',', $meta_keys_for_weight ) . ')';
			}
			$weight_key = floatval( $weight_key );
			$sql       .= "
                LEFT JOIN (
                    SELECT {$this->wpdb->prefix}posts.{$column} as post_id, ( SUM( COALESCE(`{$this->query->db_prefix}cf`.`count`, 0) ) * {$weight_key} ) AS cfweight{$i}
                    FROM {$this->query->db_prefix}terms
                    LEFT JOIN {$this->query->db_prefix}cf ON {$this->query->db_prefix}terms.id = {$this->query->db_prefix}cf.term
                    LEFT JOIN {$this->wpdb->prefix}posts ON {$this->query->db_prefix}cf.post_id = {$this->wpdb->prefix}posts.ID
                    {$extra_sql_join}
                    WHERE {$sql_term_where}
                    {$sql_status}
                    AND {$wpdb->prefix}posts.post_type = '{$post_type}'
                    {$this->query->sql_exclude}
                    {$this->query->sql_include}
                    {$post_meta_clause}
                    {$sql_conditions}
                    GROUP BY post_id
                ) AS cfweights{$i} ON cfweights{$i}.post_id = {$this->wpdb->prefix}posts.ID";
			$i++;
		}

		// There also may be LIKE weights, though, so we need to build out that SQL as well.
		if ( ! empty( $like_weights ) ) {
			foreach ( $like_weights as $like_weight ) {
				$like_weight['metakey'] = $wpdb->prepare( '%s', $like_weight['metakey'] );
				$like_weight['weight']  = floatval( $like_weight['weight'] );
				$post_meta_clause       = ' AND ' . $this->query->db_prefix . 'cf.metakey LIKE ' . $like_weight['metakey'];
				$sql                   .= "
                LEFT JOIN (
                    SELECT {$this->wpdb->prefix}posts.{$column} as post_id, ( SUM( COALESCE(`{$this->query->db_prefix}cf`.`count`, 0) ) * {$like_weight['weight']} ) AS cfweight{$i}
                    FROM {$this->query->db_prefix}terms
                    LEFT JOIN {$this->query->db_prefix}cf ON {$this->query->db_prefix}terms.id = {$this->query->db_prefix}cf.term
                    LEFT JOIN {$this->wpdb->prefix}posts ON {$this->query->db_prefix}cf.post_id = {$this->wpdb->prefix}posts.ID
                    {$extra_sql_join}
                    WHERE {$sql_term_where}
                    {$sql_status}
                    AND {$this->wpdb->prefix}posts.post_type = '{$post_type}'
                    {$this->query->sql_exclude}
                    {$this->query->sql_include}
                    {$post_meta_clause}
                    {$sql_conditions}
                    GROUP BY post_id
                ) AS cfweights{$i} ON cfweights{$i}.post_id = {$wpdb->prefix}posts.ID";
				$i++;
			}
		}

		return $sql;
	}

	/**
	 * Generate the SQL that extracts taxonomy weights
	 *
	 * @param string $post_type       The post type.
	 * @param array  $weights         Taxonomy weights from SearchWP Settings.
	 * @param string $sql_term_where  SQL Where statement.
	 * @param string $sql_status      SQL posts status statement.
	 * @param string $extra_sql_join  Extra SQL table joins statements.
	 * @param string $sql_conditions  Extra SQL conditions.
	 * @return string
	 */
	public function query_post_type_taxonomy_weights( $post_type, $weights, $sql_term_where, $sql_status, $extra_sql_join = '', $sql_conditions = '' ) {
		$i = 0;

		// First we'll try to merge any matching weight taxonomies so as to save as many JOINs as possible.
		$optimized_weights = array();
		foreach ( $weights as $taxonomy_name => $taxonomy_weight ) {
			$taxonomy_weight = absint( $taxonomy_weight );
			$optimized_weights[ $taxonomy_weight ][] = $taxonomy_name;
		}

		$sql = '';
		foreach ( $optimized_weights as $post_type_tax_weight => $post_type_taxonomies ) {
			$post_type_tax_weight = absint( $post_type_tax_weight );

			if ( is_array( $post_type_taxonomies ) ) {
				foreach ( $post_type_taxonomies as $key => $val ) {
					$post_type_taxonomies[ $key ] = $this->wpdb->prepare( '%s', $val );
				}
			}

			$sql .= "
                LEFT JOIN (
                    SELECT {$this->query->db_prefix}tax.post_id, ( SUM( {$this->query->db_prefix}tax.count ) * {$post_type_tax_weight} ) AS taxweight{$i}
                    FROM {$this->query->db_prefix}terms
                    LEFT JOIN {$this->query->db_prefix}tax ON {$this->query->db_prefix}terms.id = {$this->query->db_prefix}tax.term
                    LEFT JOIN {$this->wpdb->prefix}posts ON {$this->query->db_prefix}tax.post_id = {$this->wpdb->prefix}posts.ID
                    {$extra_sql_join}
                    WHERE {$sql_term_where}
                    {$sql_status}
                    AND {$this->wpdb->prefix}posts.post_type = '{$post_type}'
                    {$this->query->sql_exclude}
                    {$this->query->sql_include}
                    AND {$this->query->db_prefix}tax.taxonomy IN (" . implode( ',', $post_type_taxonomies ) . ")
                    {$sql_conditions}
                    GROUP BY {$this->query->db_prefix}tax.post_id
                ) AS taxweights{$i} ON taxweights{$i}.post_id = {$this->wpdb->prefix}posts.ID";
			$i++;
		}

		return $sql;
	}

	/**
	 * Generate the SQL that closes the per-post type sub-query
	 *
	 * @param string   $post_type       The post type.
	 * @param bool|int $attribute_to    The attribution target post ID (if applicable).
	 * @param string   $sql_term_where  SQL Where statement.
	 * @param string   $sql_status      SQL posts status statement.
	 * @param string   $sql_conditions  Extra SQL conditions.
	 * @return string
	 */
	public function query_post_type_close( $post_type, $attribute_to = false, $sql_term_where, $sql_status, $sql_conditions = '' ) {
		if ( ! post_type_exists( $post_type ) ) {
			wp_die( 'Invalid request', 'searchwp' );
		}

		$post_type_group_by = apply_filters( 'searchwp_post_type_group_by_clause', array( "{$this->wpdb->prefix}posts.ID" ) );
		$post_type_group_by = array_map( 'esc_sql', $post_type_group_by );
		$post_type_group_by = implode( ', ', $post_type_group_by );

		// Cap off each enabled post type subquery.
		$sql = "
            WHERE {$sql_term_where}
            {$sql_status}
            AND {$this->wpdb->prefix}posts.post_type = '{$post_type}'
            {$this->query->sql_exclude}
            {$this->query->sql_include}
            {$sql_conditions}
			GROUP BY {$post_type_group_by}";

		// @since 2.9.0
		$sql .= $this->query->only_full_group_by_fix_for_post_type();

		if ( isset( $attribute_to ) && ! empty( $attribute_to ) ) {
			// $attributedTo was defined in the initial conditional
			$attributed_to = absint( $attribute_to );
			$sql          .= ") `attributed{$post_type}` ON $attributed_to = {$this->wpdb->prefix}posts.ID";
		} else {
			$sql .= ") AS `{$post_type}weights` ON `{$post_type}weights`.post_id = {$this->wpdb->prefix}posts.ID";
		}

		return $sql;
	}

	/**
	 * Generate the SQL that limits search results to a specific minimum weight per post type
	 *
	 * @return string
	 */
	public function query_limit_post_type_to_weight() {
		$sql = '';
		foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
			if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] && empty( $post_type_weights['options']['attribute_to'] ) ) {
				$sql .= " COALESCE(`{$post_type}weight`,0) +";
			}
		}

		foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
			if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] && ! empty( $post_type_weights['options']['attribute_to'] ) ) {
				$attributed_to = absint( $post_type_weights['options']['attribute_to'] );
				// Make sure we're not excluding the attributed post id.
				if ( ! in_array( $attributed_to, $this->query->excluded, true ) ) {
					$sql .= " COALESCE(`{$post_type}attr`,0) +";
				}
			}
		}

		$sql  = substr( $sql, 0, strlen( $sql ) - 2 ); // trim off the extra +.
		$sql .= ' > ' . absint( apply_filters( 'searchwp_weight_threshold', 0 ) ) . ' ';

		return $sql;
	}

	/**
	 * Get post instance for the cursor.
	 *
	 * This is cached internally so it does not generate extra queries
	 *
	 * @return mixed WP_Post|null
	 */
	public function get_cursor_post() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		return \WP_Post::get_instance( $this->cursor_offset );
	}

	/**
	 * Returns the cursor post's finalweight
	 *
	 * @return integer
	 */
	public function get_cursor_post_weight() {
		$sql = 'SELECT SUM(';
		foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
			if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] ) {
				$term_counter = 1;
				if ( empty( $post_type_weights['options']['attribute_to'] ) ) {
					foreach ( $this->query->terms as $term ) {
						$sql .= "COALESCE(term{$term_counter}.`{$post_type}weight`,0) + ";
						$term_counter++;
					}
				} else {
					foreach ( $this->query->terms as $term ) {
						$sql .= "COALESCE(term{$term_counter}.`{$post_type}attr`,0) + ";
						$term_counter++;
					}
				}
			}
		}
		$sql  = substr( $sql, 0, strlen( $sql ) - 2 ); // trim off the extra +.
		$sql .= ") AS `cursorWeight` FROM {$this->wpdb->prefix}posts ";

		$term_counter = 1;
		foreach ( $this->query->terms as $term ) {
			$sql .= "LEFT JOIN ( SELECT {$this->wpdb->prefix}posts.ID AS post_id";

			$post_type_weight_sql       = '';
			$attribute_weight_sql       = '';
			$final_weight_sql           = '';
			$attribute_final_weight_sql = '';
			foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
				if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] ) {
					if ( ! empty( $post_type_weights['options']['attribute_to'] ) ) {
						$attributed_to = absint( $post_type_weights['options']['attribute_to'] );

						// make sure we're not excluding the attributed post id.
						if ( ! in_array( $attributed_to, $this->query->excluded, true ) ) {
							$attribute_weight_sql       .= ", COALESCE(`{$post_type}attr`,0) as `{$post_type}attr` ";
							$attribute_final_weight_sql .= " COALESCE(`{$post_type}attr`,0) +";
						}
					} else {
						$post_type_weight_sql .= ", COALESCE(`{$post_type}weight`,0) AS `{$post_type}weight` ";
						$final_weight_sql     .= " COALESCE(`{$post_type}weight`,0) +";
					}
				}
			}

			$sql .= $post_type_weight_sql . $attribute_weight_sql . ', ' . $final_weight_sql . $attribute_final_weight_sql;

			$sql  = substr( $sql, 0, strlen( $sql ) - 2 );
			$sql .= ' AS weight ';
			$sql .= " FROM {$this->wpdb->prefix}posts ";

			foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
				if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] ) {
					$prepped_term          = $this->query->prep_term( $term, $post_type_weights );
					$term                  = $prepped_term['term'];
					$term_or_stem          = $prepped_term['term_or_stem'];
					$original_prepped_term = $prepped_term['original_prepped_term'];
					$this->cache_term_final( $term );

					$collate_override = $this->query->get_collate_override();
					$sql_term_where   = " {$this->query->db_prefix}terms." . $term_or_stem . $collate_override . ' IN (' . implode( ',', $term ) . ')';

					// If it's an attachment we need to force 'inherit'.
					$post_statuses = ( 'attachment' === $post_type ) ? array( 'inherit' ) : $this->query->post_statuses;
					if ( is_array( $post_statuses ) ) {
						foreach ( $post_statuses as $key => $val ) {
							$post_statuses[ $key ] = $this->wpdb->prepare( '%s', $val );
						}
					}
					$sql_status = "AND {$this->wpdb->prefix}posts.post_status IN ( " . implode( ',', $post_statuses ) . ' ) ';

					// Determine whether we need to limit to a mime type.
					if ( isset( $post_type_weights['options']['mimes'] ) && '' !== $post_type_weights['options']['mimes'] ) {

						// Stored as an array of integers that correlate to mime type groups.
						$mimes = explode( ',', $post_type_weights['options']['mimes'] );
						$mimes = array_map( 'absint', $mimes );

						$targeted_mimes = \SWP()->get_mimes_from_settings_ids( $mimes );

						if ( empty( $targeted_mimes ) ) {
							return;
						}

						if ( is_array( $targeted_mimes ) ) {
							foreach ( $targeted_mimes as $key => $val ) {
								$targeted_mimes[ $key ] = $this->wpdb->prepare( '%s', $val );
							}
						}

						// We have an array of keys that match MIME types (not subtypes) that we can limit to by appending this condition.
						$sql_status .= " AND {$this->wpdb->prefix}posts.post_type = 'attachment' AND {$this->wpdb->prefix}posts.post_mime_type IN ( " . implode( ',', $targeted_mimes ) . ' ) ';
					}

					// Take into consideration the engine limiter rules FOR THIS POST TYPE.
					$limited_ids    = $this->query->get_included_ids_from_taxonomies_for_post_type( $post_type );
					$limiter_column = 'ID';

					// If parent attribution is in play we need to transfer the inclusion/exclusion rules.
					if (
						'attachment' === $post_type
						&& isset( $post_type_weights['options']['parent'] )
						&& ! empty( $post_type_weights['options']['parent'] )
					) {
						$limiter_column     = 'post_parent';
						$global_limited_ids = array();

						// This isn't ideal because the post_parent can be _any_ post type, so we need to limit to them all...
						foreach ( $this->query->engineSettings as $limiter_post_type => $limiter_post_type_weights ) {
							if ( ! isset( $limiter_post_type_weights['enabled'] ) || empty( $limiter_post_type_weights['enabled'] ) ) {
								continue;
							}

							$these_limited_ids = $this->query->get_included_ids_from_taxonomies_for_post_type( $limiter_post_type );

							if ( ! empty( $these_limited_ids ) ) {
								$global_limited_ids = array_merge( $global_limited_ids, $these_limited_ids );
							}
						}

						$limited_ids = array_unique( $global_limited_ids );
					}

					// Function returns false if not applicable.
					if ( is_array( $limited_ids ) && ! empty( $limited_ids ) ) {
						$limited_ids = array_map( 'absint', $limited_ids );
						$limited_ids = array_unique( $limited_ids );
						$sql_status .= " AND {$this->wpdb->prefix}posts.post_type = '{$post_type}' AND {$this->wpdb->prefix}posts." . $limiter_column . ' IN ( ' . implode( ',', $limited_ids ) . ' ) ';
					}

					// Reset back to our original term.
					$term = $original_prepped_term;

					// We need to use absint because if a weight was set to -1 for exclusion, it was already forcefully excluded.
					$title_weight   = isset( $post_type_weights['weights']['title'] ) ? absint( $post_type_weights['weights']['title'] ) : 0;
					$slug_weight    = isset( $post_type_weights['weights']['slug'] ) ? absint( $post_type_weights['weights']['slug'] ) : 0;
					$content_weight = isset( $post_type_weights['weights']['content'] ) ? absint( $post_type_weights['weights']['content'] ) : 0;
					$excerpt_weight = isset( $post_type_weights['weights']['excerpt'] ) ? absint( $post_type_weights['weights']['excerpt'] ) : 0;

					if ( apply_filters( 'searchwp_index_comments', true ) ) {
						$comment_weight = isset( $post_type_weights['weights']['comment'] ) ? absint( $post_type_weights['weights']['comment'] ) : 0;
					} else {
						$comment_weight = 0;
					}

					// Build the SQL to accommodate Custom Fields.
					$custom_field_weights   = isset( $post_type_weights['weights']['cf'] ) ? $post_type_weights['weights']['cf'] : 0;
					$coalesce_custom_fields = $this->query->query_coalesce_custom_fields( $custom_field_weights );

					// Build the SQL to accommodate Taxonomies.
					$taxonomy_weights    = isset( $post_type_weights['weights']['tax'] ) ? $post_type_weights['weights']['tax'] : 0;
					$coalesce_taxonomies = $this->query->query_coalesce_taxonomies( $taxonomy_weights );

					// Allow additional tables to be joined.
					$sql_join = apply_filters( 'searchwp_query_join', '', $post_type, $this->query->engine );
					if ( ! is_string( $sql_join ) ) {
						$sql_join = '';
					}

					// Allow additional conditions.
					$sql_conditions = apply_filters( 'searchwp_query_conditions', '', $post_type, $this->query->engine );
					if ( ! is_string( $sql_conditions ) ) {
						$sql_conditions = '';
					}

					// If we're dealing with attributed weight we need to make sure that the attribution target was not excluded.
					$excluded_by_attribution = false;
					$attributed_to           = false;
					if ( isset( $post_type_weights['options']['attribute_to'] ) && ! empty( $post_type_weights['options']['attribute_to'] ) ) {
						$post_column   = 'ID';
						$attributed_to = absint( $post_type_weights['options']['attribute_to'] );
						if ( in_array( $attributed_to, $this->query->excluded, true ) ) {
							$excluded_by_attribution = true;
						}
					} else {
						// If it's an attachment and we want to attribute to the parent, we need to set that here.
						$post_column = ! empty( $post_type_weights['options']['parent'] ) ? 'post_parent' : 'ID';
					}

					// Open up the post type subquery if not excluded by attribution.
					if ( ! $excluded_by_attribution ) {
						$post_type_params = array(
							'post_type'      => $post_type,
							'post_column'    => $post_column,
							'title_weight'   => $title_weight,
							'slug_weight'    => $slug_weight,
							'content_weight' => $content_weight,
							'comment_weight' => $comment_weight,
							'excerpt_weight' => $excerpt_weight,
							'custom_fields'  => isset( $coalesce_custom_fields ) ? $coalesce_custom_fields : '',
							'taxonomies'     => isset( $coalesce_taxonomies ) ? $coalesce_taxonomies : '',
							'attributed_to'  => $attributed_to,
						);
						$sql             .= $this->query_post_type_open( $post_type_params, $sql_join );

						// Handle custom field weights.
						if ( isset( $post_type_weights['weights']['cf'] )
							&& is_array( $post_type_weights['weights']['cf'] )
							&& ! empty( $post_type_weights['weights']['cf'] ) ) {
							$sql .= $this->query_post_type_custom_field_weights(
								$post_type,
								$post_type_weights['weights']['cf'],
								$sql_term_where,
								$sql_status,
								$sql_join,
								$sql_conditions
							);
						}

						// Handle taxonomy weights.
						if ( isset( $post_type_weights['weights']['tax'] )
							&& is_array( $post_type_weights['weights']['tax'] )
							&& ! empty( $post_type_weights['weights']['tax'] ) ) {
							$sql .= $this->query_post_type_taxonomy_weights(
								$post_type,
								$post_type_weights['weights']['tax'],
								$sql_term_where,
								$sql_status,
								$sql_join,
								$sql_conditions
							);
						}

						// Close out the per-post type sub-query.
						$attribute_to = isset( $post_type_weights['options']['attribute_to'] ) ? absint( $post_type_weights['options']['attribute_to'] ) : false;
						$sql         .= $this->query_post_type_close(
							$post_type,
							$attribute_to,
							$sql_term_where,
							$sql_status,
							$sql_conditions
						);
					}
				}
			}

			$sql .= " LEFT JOIN {$this->query->db_prefix}index ON {$this->query->db_prefix}index.post_id = {$this->wpdb->prefix}posts.ID ";
			$sql .= " LEFT JOIN {$this->query->db_prefix}terms ON {$this->query->db_prefix}terms.id = {$this->query->db_prefix}index.term ";
			$sql .= ' WHERE ';
			$sql .= $this->query_limit_post_type_to_weight();

			/**
			 * SearchWP hotfix.
			 */
			$old_query_sql = $this->query->sql;
			$sql          .= $this->query->query_limit_pool_by_stem();

			if ( $this->query->sql !== $old_query_sql ) {
				$sql             .= substr( $this->query->sql, strlen( $old_query_sql ) - 1 );
				$this->query->sql = $old_query_sql;
			}
			$sql .= $this->query->post_status_limiter_sql( $this->query->engineSettings );
			$sql .= ' GROUP BY post_id';
			$sql .= $this->query->only_full_group_by_fix_for_term();
			$sql .= " ) AS term{$term_counter} ON term{$term_counter}.post_id = {$this->wpdb->prefix}posts.ID ";

			$term_counter++;
		}

		$cursor_id = $this->get_cursor_post()->ID;

		$sql .= "WHERE {$this->wpdb->prefix}posts.ID = {$cursor_id}";
		return $sql;
	}

	/**
	 * Wrapper function query args
	 *
	 * @param string $name  Name of desired query arg.
	 *
	 * @return null|mixed
	 */
	public function get_query_arg( $name ) {
		return empty( $this->query_args[ $name ] ) ? null : $this->query_args[ $name ];
	}

	/**
	 * Return the additional AND operators for the where statement
	 *
	 * @return string|null
	 */
	public function get_where() {

		/**
		 * Ensure the cursor_offset is a positive integer
		 */
		if ( ! is_integer( $this->cursor_offset ) || 0 >= $this->cursor_offset ) {
			return '';
		}

		/**
		 * If we have bad cursor just skip...
		 */
		if ( ! $this->get_cursor_post() ) {
			return '';
		}

		$this->where_sql = 'AND ';
		// Sum our final weights per post type.
		foreach ( $this->query->engineSettings as $post_type => $post_type_weights ) {
			if ( isset( $post_type_weights['enabled'] ) && true === $post_type_weights['enabled'] ) {
				$term_counter = 1;
				if ( empty( $post_type_weights['options']['attribute_to'] ) ) {
					foreach ( $this->query->terms as $term ) {
						$this->where_sql .= "COALESCE(term{$term_counter}.`{$post_type}weight`,0) + ";
						$term_counter++;
					}
				} else {
					foreach ( $this->query->terms as $term ) {
						$this->where_sql .= "COALESCE(term{$term_counter}.`{$post_type}attr`,0) + ";
						$term_counter++;
					}
				}
			}
		}
		$this->where_sql    = substr( $this->where_sql, 0, strlen( $this->where_sql ) - 2 ); // trim off the extra +.
		$cursor_post_weight = $this->wpdb->get_results( $this->get_cursor_post_weight() )[0]->cursorWeight; // WPCS: unprepared SQL OK.
		$this->where_sql   .= "{$this->compare}= {$cursor_post_weight}";
		$this->where_sql   .= ' AND ';
		$this->where_sql   .= "(CAST({$this->wpdb->prefix}posts.post_date as DATETIME) {$this->compare} CAST('{$this->get_cursor_post()->post_date}' as DATETIME)";
		$this->where_sql   .= ' OR ';
		$this->where_sql   .= "({$this->wpdb->prefix}posts.ID > {$this->get_cursor_post()->ID}))";

		return $this->where_sql;
	}

	/**
	 * Returns the ORDER BY statement for the SWP Query.
	 *
	 * @return string
	 */
	public function get_orderby() {
		return "ORDER BY finalweight {$this->query->order}, post_date {$this->query->order}, post_id DESC ";
	}
}
