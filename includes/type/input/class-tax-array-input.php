<?php
/**
 * Registers "SearchWPTaxArrayInput" type.
 *
 * @package \WPGraphQL\SearchWP\Type\WPInputObject
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPInputObject;

/**
 * Class Tax_Array_Input
 */
class Tax_Array_Input {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_input( $type_registry ) {
		$type_registry->register_input_type(
			'SearchWPTaxArrayInput',
			array(
				'fields' => array(
					'taxonomy'        => array(
						'type' => 'TaxonomyEnum',
					),
					'field'           => array(
						'type' => 'SearchWPTaxQueryField',
					),
					'terms'           => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => __( 'A list of term slugs', 'ql-search' ),
					),
					'includeChildren' => array(
						'type'        => 'Boolean',
						'description' => __( 'Whether or not to include children for hierarchical taxonomies. Defaults to false to improve performance (note that this is opposite of the default for WP_Query).', 'ql-search' ),
					),
					'operator'        => array(
						'type' => 'SearchWPTaxQueryOperator',
					),
				),
			)
		);
	}
}
