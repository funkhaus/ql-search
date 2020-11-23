<?php
/**
 * Initializes a singleton instance of QL_Search
 *
 * @package WPGraphQL\SearchWP
 * @since 0.0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QL_Search' ) ) :
	/**
	 * Class QL_Search
	 */
	final class QL_Search {

		/**
		 * Stores the instance of the QL_Search class
		 *
		 * @var QL_Search The one true QL_Search
		 * @access private
		 */
		private static $instance;


		/**
		 * Returns QL_Search instance.
		 *
		 * @return QL_Search
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( is_a( self::$instance, __CLASS__ ) ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * QL_Search singleton constructor
		 */
		private function __construct() {
			$this->includes();
			$this->setup();

			/**
			 * Fire off init action
			 *
			 * @param QL_Search $instance The instance of the QL_Search class
			 */
			do_action( 'ql_search_init', $this );
		}

		/**
		 * Throw error on object clone.
		 * The whole idea of the singleton design pattern is that there is a single object
		 * therefore, we don't want the object to be cloned.
		 *
		 * @since  0.0.1
		 * @access public
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'QL_Search class should not be cloned.', 'ql-search' ), '0.0.1' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since  0.0.1
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {
			// De-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the QL_Search class is not allowed', 'ql-search' ), '0.0.1' );
		}

		/**
		 * Include required files.
		 * Uses composer's autoload
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function includes() {
			/**
			 * Autoload Required Classes
			 */
			if ( defined( 'QL_SEARCH_AUTOLOAD' ) && false !== QL_SEARCH_AUTOLOAD ) {
				require_once QL_SEARCH_PLUGIN_DIR . 'vendor/autoload.php';
			}
		}

		/**
		 * Sets up WooGraphQL schema.
		 */
		private function setup() {
			// Register WPGraphQL core filters.
			\WPGraphQL\SearchWP\Core_Schema_Filters::add_filters();

			$registry = new \WPGraphQL\SearchWP\Type_Registry();
			add_action( 'graphql_register_types', array( $registry, 'init' ), 10, 1 );
		}
	}
endif;
