<?php
/**
 * Registers "SearchWPMetaCompare" if it doesn't exist.
 *
 * @package \WPGraphQL\SearchWP\Type\WPEnum
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPEnum;

/**
 * Class Meta_Compare
 */
class Meta_Compare {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_enum( $type_registry ) {
		$type_registry->register_enum_type(
			'SearchWPMetaCompare',
			array(
				'values' => array(
					'EQUAL_TO'                 => array(
						'name'  => 'EQUAL_TO',
						'value' => '=',
					),
					'NOT_EQUAL_TO'             => array(
						'name'  => 'NOT_EQUAL_TO',
						'value' => '!=',
					),
					'GREATER_THAN'             => array(
						'name'  => 'GREATER_THAN',
						'value' => '>',
					),
					'GREATER_THAN_OR_EQUAL_TO' => array(
						'name'  => 'GREATER_THAN_OR_EQUAL_TO',
						'value' => '>=',
					),
					'LESS_THAN'                => array(
						'name'  => 'LESS_THAN',
						'value' => '<',
					),
					'LESS_THAN_OR_EQUAL_TO'    => array(
						'name'  => 'LESS_THAN_OR_EQUAL_TO',
						'value' => '<=',
					),
					'LIKE'                     => array(
						'name'  => 'LIKE',
						'value' => 'LIKE',
					),
					'NOT_LIKE'                 => array(
						'name'  => 'NOT_LIKE',
						'value' => 'NOT LIKE',
					),
					'IN'                       => array(
						'name'  => 'IN',
						'value' => 'IN',
					),
					'NOT_IN'                   => array(
						'name'  => 'NOT_IN',
						'value' => 'NOT IN',
					),
					'BETWEEN'                  => array(
						'name'  => 'BETWEEN',
						'value' => 'BETWEEN',
					),
					'NOT_BETWEEN'              => array(
						'name'  => 'NOT_BETWEEN',
						'value' => 'NOT BETWEEN',
					),
					'EXISTS'                   => array(
						'name'  => 'EXISTS',
						'value' => 'EXISTS',
					),
					'NOT_EXISTS'               => array(
						'name'  => 'NOT_EXISTS',
						'value' => 'NOT EXISTS',
					),
				),
			)
		);
	}
}
