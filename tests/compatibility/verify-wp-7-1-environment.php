<?php
/**
 * Read-only WP71 compatibility environment preflight.
 * Reports observed versions; fails on floors and structural contracts.
 */

require_once __DIR__ . '/bootstrap.php';

tutorpress_wp71_compat_run(
	function () {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$wp_version    = get_bloginfo( 'version' );
		$php_version   = PHP_VERSION;
		$tutor_version = defined( 'TUTOR_VERSION' ) ? TUTOR_VERSION : 'not detected';
		$pro_active    = is_plugin_active( 'tutor-pro/tutor-pro.php' );
		$pro_report    = $pro_active
			? ( defined( 'TUTOR_PRO_VERSION' ) ? 'active ' . TUTOR_PRO_VERSION : 'active (unversioned)' )
			: 'inactive';

		tutorpress_wp71_compat_assert( class_exists( 'TutorPress_Dependency_Checker' ), 'TutorPress_Dependency_Checker is not loaded' );
		$readme_wp_floor   = '5.8';
		$internal_wp_floor = TutorPress_Dependency_Checker::MINIMUM_WP_VERSION;
		$php_floor         = TutorPress_Dependency_Checker::MINIMUM_PHP_VERSION;

		echo 'WordPress: ' . $wp_version . PHP_EOL;
		echo 'PHP: ' . $php_version . PHP_EOL;
		echo 'Tutor LMS: ' . $tutor_version . PHP_EOL;
		echo 'Tutor Pro: ' . $pro_report . PHP_EOL;
		echo 'WordPress floor (readme Requires at least): ' . $readme_wp_floor . PHP_EOL;
		echo 'WordPress floor (MINIMUM_WP_VERSION): ' . $internal_wp_floor . PHP_EOL;
		echo 'PHP floor (readme / MINIMUM_PHP_VERSION): ' . $php_floor . PHP_EOL;

		tutorpress_wp71_compat_assert( version_compare( $wp_version, $readme_wp_floor, '>=' ), 'WordPress below readme floor ' . $readme_wp_floor );
		tutorpress_wp71_compat_assert( version_compare( $wp_version, $internal_wp_floor, '>=' ), 'WordPress below MINIMUM_WP_VERSION ' . $internal_wp_floor );
		tutorpress_wp71_compat_assert( version_compare( $php_version, $php_floor, '>=' ), 'PHP below floor ' . $php_floor );

		if ( version_compare( $wp_version, '7.1', '<' ) ) {
			fwrite( STDERR, 'WARN: WordPress ' . $wp_version . ' is below the current 7.1 campaign target' . PHP_EOL );
		}

		tutorpress_wp71_compat_assert( is_plugin_active( 'tutorpress/tutorpress.php' ), 'TutorPress is not active' );

		$plugin_file = WP_PLUGIN_DIR . '/tutorpress/tutorpress.php';
		$plugin_dir  = realpath( dirname( $plugin_file ) );
		$fixture     = realpath( __FILE__ );
		tutorpress_wp71_compat_assert(
			is_file( $plugin_file ) && $plugin_dir && $fixture && 0 === strpos( $fixture, $plugin_dir . DIRECTORY_SEPARATOR ),
			'fixture is not under the active TutorPress plugin directory'
		);

		$routes = rest_get_server()->get_routes();
		foreach ( array( 'courses', 'lesson', 'tutor_assignments', 'course-bundle' ) as $post_type ) {
			$object    = get_post_type_object( $post_type );
			$rest_base = ( $object && $object->rest_base ) ? $object->rest_base : $post_type;
			tutorpress_wp71_compat_assert(
				$object instanceof WP_Post_Type && ! empty( $object->show_in_rest ) && isset( $routes[ '/wp/v2/' . $rest_base ] ),
				$post_type . ' is not registered and REST-visible'
			);
		}

		$asset_path = ( defined( 'TUTORPRESS_PATH' ) ? TUTORPRESS_PATH : $plugin_dir . '/' ) . 'assets/js/build/index.asset.php';
		tutorpress_wp71_compat_assert( is_readable( $asset_path ), 'index.asset.php is not readable' );
		$asset = include $asset_path;
		tutorpress_wp71_compat_assert( is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ), 'asset metadata missing dependencies' );
		foreach ( array( 'react', 'react-dom', 'wp-api-fetch', 'wp-components', 'wp-core-data', 'wp-data', 'wp-data-controls', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-notices', 'wp-plugins', 'wp-primitives' ) as $dep ) {
			tutorpress_wp71_compat_assert( in_array( $dep, $asset['dependencies'], true ), 'asset metadata missing ' . $dep );
		}

		echo 'TutorPress plugin dir: ' . $plugin_dir . PHP_EOL;
		echo 'Fixture: ' . $fixture . PHP_EOL;
		tutorpress_wp71_compat_pass( 'environment preflight' );
	}
);
