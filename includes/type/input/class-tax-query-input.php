<?php
/**
 * Registers "SearchWPTaxQueryInput" type.
 *
 * @package \WPGraphQL\SearchWP\Type\WPInputObject
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPInputObject;

/**
 * Class Tax_Query_Input
 */
class Tax_Query_Input {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_input( $type_registry ) {
		$type_registry->register_input_type(
			'SearchWPTaxQueryInput',
			array(
				'description' => __( 'Query objects based on taxonomy parameters', 'ql-search' ),
				'fields'      => array(
					'relation' => array(
						'type' => 'RelationEnum',
					),
					'taxArray' => array(
						'type' => array(
							'list_of' => 'SearchWPTaxArrayInput',
						),
					),
				),
			)
		);
	}
}
