<?php
/**
 * Registers "SearchWPMetaArrayInput" type.
 *
 * @package \WPGraphQL\SearchWP\Type\WPInputObject
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPInputObject;

/**
 * Class Meta_Array_Input
 */
class Meta_Array_Input {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_input( $type_registry ) {
		$type_registry->register_input_type(
			'SearchWPMetaArrayInput',
			array(
				'fields' => array(
					'key'     => array(
						'type'        => 'String',
						'description' => __( 'Custom field key', 'ql-search' ),
					),
					'value'   => array(
						'type'        => 'String',
						'description' => __( 'Custom field value', 'ql-search' ),
					),
					'compare' => array(
						'type'        => 'SearchWPMetaCompare',
						'description' => __( 'Custom field value', 'ql-search' ),
					),
					'type'    => array(
						'type'        => 'SearchWPMetaType',
						'description' => __( 'Custom field value', 'ql-search' ),
					),
				),
			)
		);
	}
}
