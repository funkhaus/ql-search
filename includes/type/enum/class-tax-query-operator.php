<?php
/**
 * Registers "SearchWPTaxQueryOperator" if it doesn't exist.
 *
 * @package \WPGraphQL\SearchWP\Type\WPEnum
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP\Type\WPEnum;

/**
 * Class Tax_Query_Operator
 */
class Tax_Query_Operator {
	/**
	 * Registers type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public static function register_enum( $type_registry ) {
		$type_registry->register_enum_type(
			'SearchWPTaxQueryOperator',
			array(
				'values' => array(
					'IN'         => array(
						'name'  => 'IN',
						'value' => 'IN',
					),
					'NOT_IN'     => array(
						'name'  => 'NOT_IN',
						'value' => 'NOT IN',
					),
					'AND'        => array(
						'name'  => 'AND',
						'value' => 'AND',
					),
					'EXISTS'     => array(
						'name'  => 'EXISTS',
						'value' => 'EXISTS',
					),
					'NOT_EXISTS' => array(
						'name'  => 'NOT_EXISTS',
						'value' => 'NOT EXISTS',
					),
				),
			)
		);
	}
}
