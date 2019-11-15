<?php
/**
 * Registers "SearchWPTaxQueryField" if it doesn't exist.
 *
 * @package \WPGraphQL\SearchWP\Type\WPEnum
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPEnum;

/**
 * Class Tax_Query_Field
 */
class Tax_Query_Field {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_enum( $type_registry ) {
		$type_registry->register_enum_type(
			'SearchWPTaxQueryField',
			array(
				'description' => __( 'Which field to select taxonomy term by. Default value is "term_id"', 'ql-search' ),
				'values'      => array(
					'ID'          => array(
						'name'  => 'ID',
						'value' => 'term_id',
					),
					'NAME'        => array(
						'name'  => 'NAME',
						'value' => 'name',
					),
					'SLUG'        => array(
						'name'  => 'SLUG',
						'value' => 'slug',
					),
					'TAXONOMY_ID' => array(
						'name'  => 'TAXONOMY_ID',
						'value' => 'term_taxonomy_id',
					),
				),
			)
		);
	}
}
