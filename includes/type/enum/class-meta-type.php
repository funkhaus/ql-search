<?php
/**
 * Registers "SearchWPMetaType" if it doesn't exist.
 *
 * @package \WPGraphQL\SearchWP\Type\WPEnum
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPEnum;

/**
 * Class Meta_Type
 */
class Meta_Type {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_enum( $type_registry ) {
		$type_registry->register_enum_type(
			'SearchWPMetaType',
			array(
				'values' => array(
					'NUMERIC'  => array(
						'name'  => 'NUMERIC',
						'value' => 'NUMERIC',
					),
					'BINARY'   => array(
						'name'  => 'BINARY',
						'value' => 'BINARY',
					),
					'CHAR'     => array(
						'name'  => 'CHAR',
						'value' => 'CHAR',
					),
					'DATE'     => array(
						'name'  => 'DATE',
						'value' => 'DATE',
					),
					'DATETIME' => array(
						'name'  => 'DATETIME',
						'value' => 'DATETIME',
					),
					'DECIMAL'  => array(
						'name'  => 'DECIMAL',
						'value' => 'DECIMAL',
					),
					'SIGNED'   => array(
						'name'  => 'SIGNED',
						'value' => 'SIGNED',
					),
					'TIME'     => array(
						'name'  => 'TIME',
						'value' => 'TIME',
					),
					'UNSIGNED' => array(
						'name'  => 'UNSIGNED',
						'value' => 'UNSIGNED',
					),
				),
			)
		);
	}
}
