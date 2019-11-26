<?php
/**
 * Registers "SearchWPMetaQueryInput" type.
 *
 * @package \WPGraphQL\SearchWP\Type\WPInputObject
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPInputObject;

/**
 * Class Meta_Query_Input
 */
class Meta_Query_Input {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_input( $type_registry ) {
		$type_registry->register_input_type(
			'SearchWPMetaQueryInput',
			array(
				'fields' => array(
					'relation'  => array(
						'type' => 'RelationEnum',
					),
					'metaArray' => array(
						'type' => array( 'list_of' => 'SearchWPMetaArrayInput' ),
					),
				),
			)
		);
	}
}
