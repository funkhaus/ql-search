<?php
/**
 * Plugin Name: QL Search
 * Plugin URI: https://github.com/funkhaus/ql-search
 * Description: Adds SearchWP support to WPGraphQL schema.
 * Version: 0.0.1
 * Author: kidunot89
 * Author URI: https://axistaylor.com
 * Text Domain: ql-search
 * Domain Path: /languages
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package     WPGraphQL\SearchWP
 * @author      kidunot89
 * @license     GPL-3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * If the codeception remote coverage file exists, require it.
 *
 * This file should only exist locally or when CI bootstraps the environment for testing
 */
if ( file_exists( __DIR__ . '/c3.php' ) ) {
	// Get tests output directory.
	$test_dir = __DIR__ . '/tests/output';
	define( 'C3_CODECOVERAGE_ERROR_LOG_FILE', $test_dir . '/c3_error.log' );
	require_once __DIR__ . '/c3.php';
}

/**
 * Setups WPGraphQL WooCommerce constants
 */
function ql_search_constants() {
	// Plugin version.
	if ( ! defined( 'QL_SEARCH_VERSION' ) ) {
		define( 'QL_SEARCH_VERSION', '0.2.1' );
	}
	// Plugin Folder Path.
	if ( ! defined( 'QL_SEARCH_PLUGIN_DIR' ) ) {
		define( 'QL_SEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}
	// Plugin Folder URL.
	if ( ! defined( 'QL_SEARCH_PLUGIN_URL' ) ) {
		define( 'QL_SEARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}
	// Plugin Root File.
	if ( ! defined( 'QL_SEARCH_PLUGIN_FILE' ) ) {
		define( 'QL_SEARCH_PLUGIN_FILE', __FILE__ );
	}
	// Whether to autoload the files or not.
	if ( ! defined( 'QL_SEARCH_AUTOLOAD' ) ) {
		define( 'QL_SEARCH_AUTOLOAD', true );
	}
}

/**
 * Checks if WPGraphQL WooCommerce required plugins are installed and activated
 */
function ql_search_dependencies_not_ready() {
	$deps = [];
	if ( ! class_exists( '\WPGraphQL' ) ) {
		$deps[] = 'WPGraphQL';
	}
	if ( ! class_exists( '\SWP_Query' ) ) {
		$deps[] = 'SearchWP';
	}

	return $deps;
}

/**
 * Initializes QL Search
 */
function ql_search_init() {
	ql_search_constants();

	$not_ready = ql_search_dependencies_not_ready();
	if ( empty( $not_ready ) ) {
		require_once QL_SEARCH_PLUGIN_DIR . 'includes/class-ql-search.php';
		return QL_Search::instance();
	}

	foreach ( $not_ready as $dep ) {
		add_action(
			'admin_notices',
			function() use ( $dep ) {
				?>
				<div class="error notice">
					<p>
						<?php
							printf(
								/* translators: dependency not ready error message */
								esc_html__( '%1$s must be active for "QL Search" to work', 'ql-search' ),
								esc_html( $dep )
							);
						?>
					</p>
				</div>
				<?php
			}
		);
	}

	return false;
}
add_action( 'graphql_init', 'ql_search_init' );
