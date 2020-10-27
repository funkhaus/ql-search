<?php
/**
 * Registers WooGraphQL types to the schema.
 *
 * @package \WPGraphQL\SearchWP
 * @since   0.0.1
 */

namespace WPGraphQL\SearchWP;

/**
 * Class Type_Registry
 */
class Type_Registry {
	/**
	 * Registers QL Search types, connections, unions, and mutations to GraphQL schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  Instance of the WPGraphQL TypeRegistry.
	 */
	public function init( \WPGraphQL\Registry\TypeRegistry $type_registry ) {
		// Enumerations.
		\WPGraphQL\SearchWP\Type\WPEnum\Meta_Type::register_enum( $type_registry );
		\WPGraphQL\SearchWP\Type\WPEnum\Meta_Compare::register_enum( $type_registry );
		\WPGraphQL\SearchWP\Type\WPEnum\Tax_Query_Field::register_enum( $type_registry );
		\WPGraphQL\SearchWP\Type\WPEnum\Tax_Query_Operator::register_enum( $type_registry );

		// InputObjects.
		\WPGraphQL\SearchWP\Type\WPInputObject\Tax_Array_Input::register_input( $type_registry );
		\WPGraphQL\SearchWP\Type\WPInputObject\Meta_Array_Input::register_input( $type_registry );
		\WPGraphQL\SearchWP\Type\WPInputObject\Tax_Query_Input::register_input( $type_registry );
		\WPGraphQL\SearchWP\Type\WPInputObject\Meta_Query_Input::register_input( $type_registry );

		// Connections.
		\WPGraphQL\SearchWP\Connection\SWP::register_connections();
	}
}
