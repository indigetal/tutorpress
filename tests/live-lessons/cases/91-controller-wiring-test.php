<?php

register_rest_route(
	'tutorpress/v1',
	'/live-lessons',
	array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'TutorPress_REST_Live_Lessons_Controller::get_items',
		'permission_callback' => 'TutorPress_REST_Live_Lessons_Controller::check_permission',
		'args'                => array(
			'course_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
				'description'       => 'fake-credential-value',
			),
		),
	),
	false
);

$route    = TutorPress_Live_Lessons_Rest_Fake::$routes[0];
$endpoint = $route['endpoints'][0];
$schema   = $endpoint['schema'][0];
tutorpress_live_lessons_assert( 'tutorpress/v1' === $route['namespace'] && '/live-lessons' === $route['route'] && false === $route['override'] && 1 === count( $route['endpoints'] ), 'one live-lessons route is registered' );
tutorpress_live_lessons_assert( 'GET' === $endpoint['method'] && 'TutorPress_REST_Live_Lessons_Controller::get_items' === $endpoint['callback'] && 'TutorPress_REST_Live_Lessons_Controller::check_permission' === $endpoint['permission_callback'], 'route keeps method and callback names' );
tutorpress_live_lessons_assert( 'course_id' === $schema['name'] && 'integer' === $schema['type'] && true === $schema['required'] && null === $schema['enum'] && null === $schema['default'] && 'absint' === $schema['sanitize_callback'] && false === function_exists( 'absint' ), 'schema keeps the integer course field and does not call its sanitize callback' );

$request = new WP_REST_Request();
$request->set_param( 'course_id', 15 );
$request->set_param( 'api_secret', 'fake-credential-value' );
tutorpress_live_lessons_assert( 15 === $request->get_param( 'course_id' ) && null === $request->get_param( 'api_secret' ) && array( 'course_id' => 15 ) === $request->get_params(), 'request keeps the course id and drops the credential' );
tutorpress_live_lessons_assert( array( array( 'name' => 'course_id', 'id' => 15 ) ) === TutorPress_Live_Lessons_Rest_Fake::$params, 'one course param read is ledgered' );

$denied = new WP_Error( 'rest_forbidden', 'fake-credential-value', array( 'status' => 403 ) );
TutorPress_Live_Lessons_Rest_Fake::note_permission( 'TutorPress_REST_Live_Lessons_Controller::check_permission', $request, $denied );
tutorpress_live_lessons_assert(
	array(
		array(
			'callback' => 'TutorPress_REST_Live_Lessons_Controller::check_permission',
			'ids'      => array( 'course_id' => 15 ),
			'result'   => array( 'error' => 'rest_forbidden', 'status' => 403 ),
		),
	) === TutorPress_Live_Lessons_Rest_Fake::$permissions,
	'permission ledger keeps the object id and bare error status'
);

$bare = TutorPress_Live_Lessons_Rest_Fake::note_result( $denied );
tutorpress_live_lessons_assert( $bare instanceof WP_Error && ! ( $bare instanceof WP_REST_Response ) && array( array( 'kind' => 'wp_error', 'code' => 'rest_forbidden' ) ) === TutorPress_Live_Lessons_Rest_Fake::$results, 'bare error stays a WP_Error' );

TutorPress_Live_Lessons_Rest_Fake::note_gate();
tutorpress_live_lessons_assert( array( true ) === TutorPress_Live_Lessons_Rest_Fake::$gates && 17 === count( tutorpress_live_lessons_production_files() ), 'gate ledger reads the true constant and the loader stays eighteen files' );

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Discard a hook registration without invoking its callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback. Not invoked.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Discard a filter registration without invoking its callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback. Not invoked.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

require_once dirname( __DIR__, 3 ) . '/includes/class-tutorpress-assets.php';

$sync_method = new ReflectionMethod( 'TutorPress_Assets', 'enqueue_admin_assets' );
$sync_lines  = file( $sync_method->getFileName() );
$sync_body   = is_array( $sync_lines ) ? implode( '', array_slice( $sync_lines, $sync_method->getStartLine() - 1, $sync_method->getEndLine() - $sync_method->getStartLine() + 1 ) ) : '';
$sync_at     = strpos( $sync_body, 'tutorPressCurriculum.liveLessonSyncEnabled' );
$sync_window = false === $sync_at ? '' : substr( $sync_body, max( 0, $sync_at - 120 ), 280 );
tutorpress_live_lessons_assert(
	true === TutorPress_Assets::live_lesson_sync_enabled()
	&& false !== $sync_at
	&& 1 === substr_count( $sync_body, 'liveLessonSyncEnabled' )
	&& false !== strpos( $sync_window, 'wp_add_inline_script' )
	&& false !== strpos( $sync_window, 'live_lesson_sync_enabled()' )
	&& false !== strpos( $sync_window, "'before'" )
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Topics_Controller', false )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'the sync presentation flag is true while the gate is true and enqueue assigns it without running'
);

$timezone_list     = timezone_identifiers_list( DateTimeZone::ALL_WITH_BC );
$timezone_expected = array();
$timezone_seen     = array();
if ( is_array( $timezone_list ) ) {
	foreach ( $timezone_list as $timezone_identifier ) {
		if ( ! is_string( $timezone_identifier ) || isset( $timezone_seen[ $timezone_identifier ] ) || ! TutorPress_Live_Lesson_Datetime::is_approved_timezone( $timezone_identifier ) ) {
			continue;
		}
		$timezone_seen[ $timezone_identifier ] = true;
		$timezone_expected[]                   = array(
			'value' => $timezone_identifier,
			'label' => $timezone_identifier,
		);
	}
}
if ( ! isset( $timezone_seen['UTC'] ) && TutorPress_Live_Lesson_Datetime::is_approved_timezone( 'UTC' ) ) {
	array_unshift(
		$timezone_expected,
		array(
			'value' => 'UTC',
			'label' => 'UTC',
		)
	);
}
$timezone_options = TutorPress_Assets::live_lesson_timezone_options();
$timezone_encoded = json_encode( $timezone_options );
$zone_at          = strpos( $sync_body, 'tutorPressCurriculum.liveLessonTimezoneOptions' );
$zone_window      = false === $zone_at ? '' : substr( $sync_body, max( 0, $zone_at - 120 ), 360 );
tutorpress_live_lessons_assert(
	$timezone_expected === $timezone_options
	&& is_string( $timezone_encoded )
	&& false === strpos( $timezone_encoded, 'fake-credential-value' )
	&& false !== $zone_at
	&& 1 === substr_count( $sync_body, 'liveLessonTimezoneOptions' )
	&& false !== strpos( $zone_window, 'wp_add_inline_script' )
	&& false !== strpos( $zone_window, 'live_lesson_timezone_options()' )
	&& false !== strpos( $zone_window, "'before'" )
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Topics_Controller', false )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'timezone options are the full approved identifier set and enqueue assigns them without running'
);

$encoded = json_encode(
	array(
		TutorPress_Live_Lessons_Rest_Fake::$routes,
		TutorPress_Live_Lessons_Rest_Fake::$params,
		TutorPress_Live_Lessons_Rest_Fake::$permissions,
		TutorPress_Live_Lessons_Rest_Fake::$results,
		TutorPress_Live_Lessons_Rest_Fake::$gates,
	)
);
tutorpress_live_lessons_assert( is_string( $encoded ) && false === strpos( $encoded, 'fake-credential-value' ) && false === strpos( $encoded, 'edit_posts' ), 'ledgers omit the credential sentinel and broad capability' );

tutorpress_live_lessons_pass( 'route schema permission and gate ledgers' );

$provider_before   = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$replacement_runs  = 0;
TutorPress_Live_Lessons_Service_Fake::register(
	'replacement',
	function () use ( &$replacement_runs ) {
		$replacement_runs++;
		return 'replacement';
	}
);
TutorPress_Live_Lessons_Service_Fake::note_fallback( '/live-lessons' );
tutorpress_live_lessons_assert( 0 === $replacement_runs && array() === TutorPress_Live_Lessons_Service_Fake::$resolved && array() === TutorPress_Live_Lessons_Service_Fake::$fallbacks, 'gate-true note_fallback records no legacy path' );

$ordering_runs = 0;
TutorPress_Live_Lessons_Service_Fake::register(
	'ordering',
	function () use ( &$ordering_runs ) {
		$ordering_runs++;
		return 'ordering';
	}
);
tutorpress_live_lessons_assert( 'ordering' === TutorPress_Live_Lessons_Service_Fake::get( 'ordering' ) && 1 === $ordering_runs && array( 'ordering' ) === TutorPress_Live_Lessons_Service_Fake::$resolved, 'one direct get runs its factory once' );

$rows         = TutorPress_Live_Lessons_Service_Fake::topic_rows( 4, 8, array( array( 'id' => 1 ) ) );
$denied_topic = TutorPress_Live_Lessons_Service_Fake::topic_rows( 0, 8, array( array( 'id' => 2 ) ) );
$denied_course = TutorPress_Live_Lessons_Service_Fake::topic_rows( 4, 0, array( array( 'id' => 3 ) ) );
tutorpress_live_lessons_assert( array( array( 'id' => 1 ) ) === $rows && null === $denied_topic && null === $denied_course, 'topic rows require positive topic and course ids' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Service_Fake::child_handler( 'lesson', 3 ) && $provider_before === TutorPress_Live_Lessons_Boundary_Fake::$ledger && array( array( 'kind' => 'lesson', 'child_id' => 3 ) ) === TutorPress_Live_Lessons_Service_Fake::$children, 'child handler records the child id and adds no provider call' );

wp_localize_script(
	'tutorpress-curriculum-metabox',
	'tutorPressCurriculum',
	array(
		'course_id' => 8,
		'api_secret' => 'fake-credential-value',
		'settings'  => array(
			'token'    => 'fake-credential-value',
			'timezone' => 'UTC',
		),
	)
);
$service_encoded = json_encode(
	array(
		TutorPress_Live_Lessons_Service_Fake::$resolved,
		TutorPress_Live_Lessons_Service_Fake::$fallbacks,
		TutorPress_Live_Lessons_Service_Fake::$topics,
		TutorPress_Live_Lessons_Service_Fake::$children,
		TutorPress_Live_Lessons_Service_Fake::$localized,
	)
);
tutorpress_live_lessons_assert(
	array(
		array(
			'handle' => 'tutorpress-curriculum-metabox',
			'name'   => 'tutorPressCurriculum',
			'data'   => array(
				'course_id' => 8,
				'settings'  => array( 'timezone' => 'UTC' ),
			),
		),
	) === TutorPress_Live_Lessons_Service_Fake::$localized
	&& is_string( $service_encoded )
	&& false === strpos( $service_encoded, 'fake-credential-value' ),
	'localize capture drops credential keys and the gate is true'
);

tutorpress_live_lessons_pass( 'gate-false services stay unresolved' );

require_once dirname( __DIR__, 3 ) . '/includes/class-tutorpress-service-container.php';

$foundation = array(
	'live_lesson.repository'        => 'TutorPress_Live_Lesson_Sync_Repository',
	'live_lesson.envelope'           => 'TutorPress_Live_Lesson_Sync_Envelope',
	'live_lesson.authorization'      => 'TutorPress_Live_Lesson_Authorization',
	'live_lesson.response_projector' => 'TutorPress_Live_Lesson_Response_Projector',
);
$container = TutorPress_Service_Container::instance();
$classmap  = file_get_contents( dirname( __DIR__, 3 ) . '/vendor/composer/autoload_classmap.php' );
$resolved  = array();
foreach ( $foundation as $service_id => $class_name ) {
	$first            = $container->get( $service_id );
	$second           = $container->get( $service_id );
	$resolved[ $service_id ] = $first;
	tutorpress_live_lessons_assert(
		true === $container->has( $service_id ) && $class_name === $first && $class_name === $second && is_string( $classmap ) && false !== strpos( $classmap, "'" . $class_name . "'" ),
		$service_id . ' resolves its mapped class string'
	);
}
$foundation_encoded = json_encode( $resolved );
tutorpress_live_lessons_assert(
	is_string( $foundation_encoded ) && false === strpos( $foundation_encoded, 'fake-credential-value' ) && 17 === count( tutorpress_live_lessons_production_files() ),
	'foundation resolution stays gate-false and omits the credential sentinel'
);

tutorpress_live_lessons_pass( 'foundation services resolve mapped class strings' );

$reads = array(
	'live_lesson.ordering'         => array( 'TutorPress_Live_Lesson_Ordering', array() ),
	'live_lesson.collection_query' => array( 'TutorPress_Live_Lesson_Collection_Query', array( 'live_lesson.authorization' ) ),
	'live_lesson.topic_projector'  => array( 'TutorPress_Live_Lesson_Topic_Projector', array( 'live_lesson.authorization', 'live_lesson.response_projector' ) ),
);
$read_resolved = array();
foreach ( $reads as $service_id => $expected ) {
	$value                       = $container->get( $service_id );
	$read_resolved[ $service_id ] = array(
		'service'      => $value,
		'dependencies' => $container->dependencies( $service_id ),
	);
	tutorpress_live_lessons_assert( $expected[0] === $value && $expected[1] === $container->dependencies( $service_id ), $service_id . ' resolves with its declared dependencies' );
}
tutorpress_live_lessons_assert(
	false === $container->resolved( 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ) && false === $container->resolved( 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ) && 17 === count( tutorpress_live_lessons_production_files() ),
	'read services construct no adapter while the gate stays false'
);
$read_encoded = json_encode( $read_resolved );
tutorpress_live_lessons_assert( is_string( $read_encoded ) && false === strpos( $read_encoded, 'fake-credential-value' ), 'read resolution omits the credential sentinel' );

tutorpress_live_lessons_pass( 'read services resolve projector and authorization dependencies' );

$owner_resolver = $container->get( 'live_lesson.owner_resolver' );
$google         = $container->get( 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' );
$google_calls   = ( new ReflectionProperty( 'TutorPress_Google_Meet_Provider_Adapter', 'call' ) )->getValue( $google );
$google_source  = file_get_contents( dirname( __DIR__, 3 ) . '/includes/class-tutorpress-service-container.php' );
$ready_at       = strpos( $google_source, 'function google_ready' );
$calendar_at    = strpos( $google_source, 'function google_calendar' );
$ready_source   = is_int( $ready_at ) && is_int( $calendar_at ) && $calendar_at > $ready_at ? substr( $google_source, $ready_at, $calendar_at - $ready_at ) : '';
$google_closures = true;
foreach ( array( 'ready', 'current', 'switch', 'restore', 'factory', 'settings' ) as $call_name ) {
	$google_closures = $google_closures && isset( $google_calls[ $call_name ] ) && $google_calls[ $call_name ] instanceof Closure;
}
tutorpress_live_lessons_assert(
	'TutorPress_Live_Lesson_Owner_Resolver' === $owner_resolver && $google instanceof TutorPress_Google_Meet_Provider_Adapter && $google instanceof TutorPress_Live_Lesson_Provider_Adapter && true === $google_closures && is_string( $ready_source ) && false === strpos( $ready_source, 'GoogleEvent' ),
	'google adapter stores six lazy owner-context callbacks'
);
tutorpress_live_lessons_assert(
	is_string( $google_source ) && false !== strpos( $google_source, 'get_userdata' ) && false !== strpos( $google_source, 'tutor-json/' ) && false !== strpos( $google_source, '-credential.json' ) && false !== strpos( $google_source, 'new \\TutorPro\\GoogleMeet\\GoogleEvent\\GoogleEvent' ) && false !== strpos( $google_source, 'is_app_permitted' ) && false !== strpos( $google_source, 'Settings::META_KEY' ) && false === $container->resolved( 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ),
	'google callbacks name the owner credential file and the calendar client'
);
$google_encoded = json_encode( array( 'owner' => $owner_resolver, 'callbacks' => array_keys( $google_calls ) ) );
tutorpress_live_lessons_assert(
	is_string( $google_encoded ) && false === strpos( $google_encoded, 'fake-credential-value' ) && 17 === count( tutorpress_live_lessons_production_files() ),
	'google registration stays gate-false and does not invoke owner-context callbacks'
);

tutorpress_live_lessons_pass( 'google adapter stores lazy owner-context callbacks' );

$zoom       = $container->get( 'TutorPress_Live_Lesson_Provider_Adapter:zoom' );
$zoom_calls = ( new ReflectionProperty( 'TutorPress_Zoom_Provider_Adapter', 'call' ) )->getValue( $zoom );
$zoom_closures = true;
foreach ( array( 'current', 'switch', 'restore', 'credentials', 'token', 'meetings', 'users' ) as $call_name ) {
	$zoom_closures = $zoom_closures && isset( $zoom_calls[ $call_name ] ) && $zoom_calls[ $call_name ] instanceof Closure;
}
$zoom_source = file_get_contents( dirname( __DIR__, 3 ) . '/includes/class-tutorpress-service-container.php' );
tutorpress_live_lessons_assert(
	$zoom instanceof TutorPress_Zoom_Provider_Adapter && $zoom instanceof TutorPress_Live_Lesson_Provider_Adapter && true === $zoom_closures,
	'zoom adapter stores seven lazy owner-context callbacks'
);
tutorpress_live_lessons_assert(
	is_string( $zoom_source ) && false !== strpos( $zoom_source, 'new \\TUTOR_ZOOM\\Zoom' ) && false !== strpos( $zoom_source, 'get_api' ) && false !== strpos( $zoom_source, '\\Zoom\\Endpoint\\Meetings' ) && false !== strpos( $zoom_source, '\\Zoom\\Endpoint\\Users' ) && false === strpos( $zoom_source, 'TutorPress_Live_Lessons_' ),
	'zoom callbacks name the Tutor Pro client boundary'
);
$zoom_encoded = json_encode( array( 'callbacks' => array_keys( $zoom_calls ) ) );
tutorpress_live_lessons_assert(
	is_string( $zoom_encoded ) && false === strpos( $zoom_encoded, 'fake-credential-value' ) && 17 === count( tutorpress_live_lessons_production_files() ),
	'zoom registration stays gate-false and does not invoke owner-context callbacks'
);

tutorpress_live_lessons_pass( 'zoom adapter stores lazy owner-context callbacks' );

$orchestrator_dependencies = array(
	'live_lesson.repository',
	'live_lesson.envelope',
	'live_lesson.authorization',
	'live_lesson.owner_resolver',
	'live_lesson.ordering',
	'live_lesson.response_projector',
	'TutorPress_Live_Lesson_Provider_Adapter:google_meet',
	'TutorPress_Live_Lesson_Provider_Adapter:zoom',
);
$shared_dependencies = array( 'live_lesson.authorization', 'live_lesson.ordering', 'live_lesson.repository', 'live_lesson.response_projector' );
$orchestrator        = $container->get( 'live_lesson.orchestrator' );
$direct_duplicate    = $container->get( 'live_lesson.direct_duplicate' );
$topic_duplication   = $container->get( 'live_lesson.topic_duplication' );
$cascade_delete      = $container->get( 'live_lesson.cascade_delete' );
$mutation_source     = file_get_contents( dirname( __DIR__, 3 ) . '/includes/class-tutorpress-service-container.php' );
$mutation_at         = strpos( $mutation_source, 'function register_mutation_services' );
$clone_at            = strpos( $mutation_source, 'function __clone' );
$mutation_body       = is_int( $mutation_at ) && is_int( $clone_at ) && $clone_at > $mutation_at ? substr( $mutation_source, $mutation_at, $clone_at - $mutation_at ) : '';
tutorpress_live_lessons_assert(
	'TutorPress_Live_Lesson_Sync_Orchestrator' === $orchestrator && $orchestrator_dependencies === $container->dependencies( 'live_lesson.orchestrator' ) && $container->get( 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ) instanceof TutorPress_Live_Lesson_Provider_Adapter && $container->get( 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ) instanceof TutorPress_Live_Lesson_Provider_Adapter,
	'orchestrator resolves interface-keyed adapters'
);
tutorpress_live_lessons_assert(
	'TutorPress_Live_Lesson_Duplicate_Service' === $direct_duplicate && $shared_dependencies === $container->dependencies( 'live_lesson.direct_duplicate' ) && 'TutorPress_Topic_Duplication_Service' === $topic_duplication && $shared_dependencies === $container->dependencies( 'live_lesson.topic_duplication' ) && 'TutorPress_Live_Lesson_Cascade_Delete' === $cascade_delete && array( 'live_lesson.authorization' ) === $container->dependencies( 'live_lesson.cascade_delete' ),
	'duplication and cascade factories stay separate from the orchestrator'
);
tutorpress_live_lessons_assert(
	is_string( $mutation_body ) && false === strpos( $mutation_body, 'new TutorPress_Google_Meet_Provider_Adapter' ) && false === strpos( $mutation_body, 'new TutorPress_Zoom_Provider_Adapter' ),
	'mutation registration leaves adapter construction in the provider factories'
);
$mutation_encoded = json_encode(
	array(
		$container->dependencies( 'live_lesson.orchestrator' ),
		$container->dependencies( 'live_lesson.direct_duplicate' ),
		$container->dependencies( 'live_lesson.topic_duplication' ),
		$container->dependencies( 'live_lesson.cascade_delete' ),
	)
);
$direct_dependencies = $container->dependencies( 'live_lesson.direct_duplicate' );
$topic_dependencies  = $container->dependencies( 'live_lesson.topic_duplication' );
$cascade_dependencies = $container->dependencies( 'live_lesson.cascade_delete' );
tutorpress_live_lessons_assert(
	is_string( $mutation_encoded ) && false === strpos( $mutation_encoded, 'fake-credential-value' ) && false === in_array( 'live_lesson.orchestrator', $direct_dependencies, true ) && false === in_array( 'TutorPress_Live_Lesson_Provider_Adapter:google_meet', $direct_dependencies, true ) && false === in_array( 'TutorPress_Live_Lesson_Provider_Adapter:zoom', $topic_dependencies, true ) && false === in_array( 'live_lesson.orchestrator', $cascade_dependencies, true ) && 17 === count( tutorpress_live_lessons_production_files() ),
	'mutation resolution stays gate-false and does not invoke owner-context callbacks'
);

tutorpress_live_lessons_pass( 'mutation services keep adapters on the interface keys' );

$dispatch_stages = array();
$dispatch_runs   = 0;
$dispatch_seen   = null;
register_rest_route(
	'tutorpress/v1',
	'/dispatch-probe',
	array(
		'methods'  => WP_REST_Server::CREATABLE,
		'callback' => static function () use ( &$dispatch_runs ) {
			$dispatch_runs++;
			return 'handled';
		},
		'args'     => array(
			'title'  => array(
				'validate_callback' => static function ( $value ) use ( &$dispatch_stages ) {
					$dispatch_stages[] = 'validate-title';
					if ( 'bad' === $value ) {
						return false;
					}
					if ( 'err' === $value ) {
						return new WP_Error( 'bad_title', 'rejected' );
					}
					return 'ignored-replacement';
				},
				'sanitize_callback' => static function ( $value ) use ( &$dispatch_stages, &$dispatch_seen ) {
					$dispatch_stages[] = 'sanitize-title';
					$dispatch_seen     = $value;
					if ( 'dirty' === $value ) {
						return new WP_Error( 'dirty_title', 'dirty' );
					}
					return 'clean-' . $value;
				},
			),
			'count'  => array(
				'validate_callback' => static function () use ( &$dispatch_stages ) {
					$dispatch_stages[] = 'validate-count';
					return true;
				},
				'sanitize_callback' => static function ( $value ) use ( &$dispatch_stages ) {
					$dispatch_stages[] = 'sanitize-count';
					return (int) $value;
				},
			),
			'status' => array(
				'default'           => 'scheduled',
				'sanitize_callback' => static function () use ( &$dispatch_stages ) {
					$dispatch_stages[] = 'sanitize-status';
					return 'replaced-default';
				},
			),
		),
	)
);

$dispatch_request = static function ( $title, $count = null ) {
	$request         = new WP_REST_Request();
	$request->method = 'POST';
	$request->route  = '/dispatch-probe';
	$request->set_param( 'title', $title );
	$request->set_param( 'note', 'leave' );
	if ( null !== $count ) {
		$request->set_param( 'count', $count );
	}
	return $request;
};

$dispatch_stages = array();
$false_request  = $dispatch_request( 'bad', 2 );
$false_result   = TutorPress_Live_Lessons_Rest_Fake::dispatch( $false_request );
tutorpress_live_lessons_assert(
	$false_result instanceof WP_Error && 'rest_invalid_param' === $false_result->get_error_code() && 0 === $dispatch_runs && array( 'validate-title', 'validate-count' ) === $dispatch_stages && 'bad' === $false_request->params['title'] && 2 === $false_request->params['count'] && 'leave' === $false_request->params['note'],
	'false validation rejects before sanitization and the handler'
);

$dispatch_stages = array();
$error_request   = $dispatch_request( 'err', 2 );
$error_result    = TutorPress_Live_Lessons_Rest_Fake::dispatch( $error_request );
tutorpress_live_lessons_assert(
	$error_result instanceof WP_Error && 'rest_invalid_param' === $error_result->get_error_code() && 0 === $dispatch_runs && array( 'validate-title', 'validate-count' ) === $dispatch_stages && 'err' === $error_request->params['title'],
	'WP_Error validation rejects before sanitization and the handler'
);

$dispatch_stages = array();
$dirty_request   = $dispatch_request( 'dirty' );
$dirty_result    = TutorPress_Live_Lessons_Rest_Fake::dispatch( $dirty_request );
tutorpress_live_lessons_assert(
	$dirty_result instanceof WP_Error && 'rest_invalid_param' === $dirty_result->get_error_code() && 0 === $dispatch_runs && array( 'validate-title', 'sanitize-title' ) === $dispatch_stages && 'dirty' === $dirty_request->params['title'],
	'sanitize WP_Error rejects before the handler and does not replace the value'
);

$dispatch_stages = array();
$clean_request   = $dispatch_request( 'Meet', '4' );
$clean_result    = TutorPress_Live_Lessons_Rest_Fake::dispatch( $clean_request );
tutorpress_live_lessons_assert(
	'handled' === $clean_result && 1 === $dispatch_runs && array( 'validate-title', 'validate-count', 'sanitize-title', 'sanitize-count' ) === $dispatch_stages && 'Meet' === $dispatch_seen && 'clean-Meet' === $clean_request->params['title'] && 4 === $clean_request->params['count'] && 'leave' === $clean_request->params['note'] && ! array_key_exists( 'status', $clean_request->params ),
	'sanitization is the only value replacement and the handler runs after it'
);

$production_files = tutorpress_live_lessons_production_files();
$root             = dirname( __DIR__, 3 );
$probe            = '<?php' . "\n"
	. '$root = ' . var_export( $root, true ) . ';' . "\n"
	. 'require_once $root . "/tests/live-lessons/fakes.php";' . "\n"
	. 'require_once $root . "/tests/live-lessons/production-loader.php";' . "\n"
	. 'require_once $root . "/includes/rest/class-rest-controller.php";' . "\n"
	. 'require_once $root . "/includes/rest/class-live-lessons-controller.php";' . "\n"
	. '$controller = new class() extends TutorPress_REST_Live_Lessons_Controller {' . "\n"
	. '	public function get_mock_live_lessons() { return array(); }' . "\n"
	. '	protected function replacement_enabled() { return false; }' . "\n"
	. '};' . "\n"
	. '$files = tutorpress_live_lessons_production_files();' . "\n"
	. 'echo json_encode( array(' . "\n"
	. '	"tutor" => function_exists( "tutor" ),' . "\n"
	. '	"enabled" => true,' . "\n"
	. '	"files" => count( $files ),' . "\n"
	. '	"base_listed" => in_array( "includes/rest/class-rest-controller.php", $files, true ),' . "\n"
	. '	"controller_listed" => in_array( "includes/rest/class-live-lessons-controller.php", $files, true ),' . "\n"
	. ') );' . "\n";
$pipes = array();
$probe_process = proc_open(
	array( PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL' ),
	array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
	$pipes
);
tutorpress_live_lessons_assert( is_resource( $probe_process ), 'legacy controller probe starts' );
fwrite( $pipes[0], $probe );
fclose( $pipes[0] );
$probe_out    = stream_get_contents( $pipes[1] );
$probe_err    = stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
$probe_status = proc_close( $probe_process );
$legacy       = json_decode( (string) $probe_out, true );
tutorpress_live_lessons_assert(
	0 === $probe_status && '' === $probe_err && is_array( $legacy ),
	'gate-false read probe stays isolated and does not call the handlers'
);
tutorpress_live_lessons_assert(
	is_array( $legacy ) && false === $legacy['tutor'] && true === $legacy['enabled'] && 17 === $legacy['files'] && false === $legacy['base_listed'] && false === $legacy['controller_listed'] && 17 === count( $production_files ) && $production_files === tutorpress_live_lessons_production_files() && false === in_array( 'includes/rest/class-rest-controller.php', $production_files, true ) && false === in_array( 'includes/rest/class-live-lessons-controller.php', $production_files, true ) && false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false ) && false === class_exists( 'TutorPress_REST_Controller', false ),
	'gate-false read probe keeps the production file list'
);

tutorpress_live_lessons_pass( 'gate-false legacy controller reads' );

$run_controller_probe = static function ( $body ) use ( $root ) {
	$script  = "<?php\n"
		. '$root = ' . var_export( $root, true ) . ";\n"
		. 'require_once $root . "/tests/live-lessons/fakes.php";' . "\n"
		. 'require_once $root . "/tests/live-lessons/production-loader.php";' . "\n"
		. 'require_once $root . "/includes/rest/class-rest-controller.php";' . "\n"
		. 'require_once $root . "/includes/rest/class-live-lessons-controller.php";' . "\n"
		. $body;
	$pipes   = array();
	$process = proc_open(
		array( PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL' ),
		array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		return array( 'status' => 1, 'err' => 'probe failed to start', 'out' => '', 'data' => null );
	}
	fwrite( $pipes[0], $script );
	fclose( $pipes[0] );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return array(
		'status' => proc_close( $process ),
		'err'    => $err,
		'out'    => $out,
		'data'   => json_decode( (string) $out, true ),
	);
};

$schedule_reader = <<<'PHP'
$property = new ReflectionProperty( 'TutorPress_Live_Lessons_Rest_Fake', 'handlers' );
$fields   = array();
$aliases  = false;
foreach ( $property->getValue() as $handler ) {
	foreach ( $handler['endpoints'] as $endpoint ) {
		$methods = isset( $endpoint['methods'] ) ? (string) $endpoint['methods'] : '';
		$args    = isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array();
		if ( isset( $args['startDateTime'] ) || isset( $args['endDateTime'] ) ) {
			$aliases = true;
		}
		if ( ( 'POST' !== $methods && 'PATCH' !== $methods ) || ! isset( $args['start_date_time'] ) ) {
			continue;
		}
		$callback = isset( $endpoint['callback'] ) && is_array( $endpoint['callback'] ) ? (string) $endpoint['callback'][1] : '';
		foreach ( array( 'start_date_time', 'end_date_time' ) as $name ) {
			$arg       = isset( $args[ $name ] ) && is_array( $args[ $name ] ) ? $args[ $name ] : array();
			$sanitize  = isset( $arg['sanitize_callback'] ) ? $arg['sanitize_callback'] : '';
			$validate  = isset( $arg['validate_callback'] ) ? $arg['validate_callback'] : '';
			$fields[]  = array(
				'method'   => $methods,
				'callback' => $callback,
				'name'     => $name,
				'format'   => isset( $arg['format'] ) ? (string) $arg['format'] : '',
				'required' => isset( $arg['required'] ) && true === $arg['required'],
				'sanitize' => is_string( $sanitize ) ? $sanitize : ( is_array( $sanitize ) ? (string) $sanitize[1] : '' ),
				'validate' => is_array( $validate ) ? (string) $validate[0] . '::' . (string) $validate[1] : '',
			);
		}
	}
}
$ledger = array();
foreach ( TutorPress_Live_Lessons_Rest_Fake::$routes as $route ) {
	foreach ( $route['endpoints'] as $endpoint ) {
		if ( 'POST' !== $endpoint['method'] && 'PATCH' !== $endpoint['method'] ) {
			continue;
		}
		foreach ( $endpoint['schema'] as $field ) {
			if ( 'start_date_time' !== $field['name'] && 'end_date_time' !== $field['name'] ) {
				continue;
			}
			$ledger[] = array(
				'method'   => $endpoint['method'],
				'callback' => $endpoint['callback'],
				'name'     => $field['name'],
				'sanitize' => $field['sanitize_callback'],
			);
		}
	}
}
PHP;

$false_schedule = $run_controller_probe(
	"\$controller = new TutorPress_REST_Live_Lessons_Controller();\n"
	. "\$controller->register_routes( false );\n"
	. $schedule_reader
	. <<<'PHP'
$plain = array();
foreach ( $property->getValue() as $handler ) {
	foreach ( $handler['endpoints'] as $endpoint ) {
		$methods = isset( $endpoint['methods'] ) ? (string) $endpoint['methods'] : '';
		if ( 'POST' !== $methods && 'PATCH' !== $methods ) {
			continue;
		}
		$args    = isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array();
		$plain[] = array(
			'duration' => isset( $args['duration'] ),
			'timezone' => isset( $args['timezone'] ),
		);
	}
}
PHP
	. 'echo json_encode( array( "enabled" => true, "files" => count( tutorpress_live_lessons_production_files() ), "aliases" => $aliases, "fields" => $fields, "ledger" => $ledger, "plain" => $plain ) );' . "\n"
);
tutorpress_live_lessons_assert(
	0 === $false_schedule['status'] && '' === $false_schedule['err'] && is_array( $false_schedule['data'] ),
	'false-gate schedule probe stays isolated ' . $false_schedule['status'] . ' ' . $false_schedule['err'] . ' ' . substr( (string) $false_schedule['out'], 0, 400 )
);
tutorpress_live_lessons_assert(
	true === $false_schedule['data']['enabled']
	&& 17 === $false_schedule['data']['files']
	&& false === $false_schedule['data']['aliases']
	&& array(
		array(
			'method'   => 'POST',
			'callback' => 'mutate_schedule',
			'name'     => 'start_date_time',
			'format'   => '',
			'required' => true,
			'sanitize' => 'sanitize_wall_clock',
			'validate' => 'TutorPress_Live_Lesson_Datetime::is_wall_clock',
		),
		array(
			'method'   => 'POST',
			'callback' => 'mutate_schedule',
			'name'     => 'end_date_time',
			'format'   => '',
			'required' => true,
			'sanitize' => 'sanitize_wall_clock',
			'validate' => 'TutorPress_Live_Lesson_Datetime::is_wall_clock',
		),
		array(
			'method'   => 'PATCH',
			'callback' => 'mutate_schedule',
			'name'     => 'start_date_time',
			'format'   => '',
			'required' => false,
			'sanitize' => 'sanitize_wall_clock',
			'validate' => 'TutorPress_Live_Lesson_Datetime::is_wall_clock',
		),
		array(
			'method'   => 'PATCH',
			'callback' => 'mutate_schedule',
			'name'     => 'end_date_time',
			'format'   => '',
			'required' => false,
			'sanitize' => 'sanitize_wall_clock',
			'validate' => 'TutorPress_Live_Lesson_Datetime::is_wall_clock',
		),
	) === $false_schedule['data']['fields']
	&& array(
		array(
			'method'   => 'POST',
			'callback' => 'TutorPress_REST_Live_Lessons_Controller::mutate_schedule',
			'name'     => 'start_date_time',
			'sanitize' => 'TutorPress_REST_Live_Lessons_Controller::sanitize_wall_clock',
		),
		array(
			'method'   => 'POST',
			'callback' => 'TutorPress_REST_Live_Lessons_Controller::mutate_schedule',
			'name'     => 'end_date_time',
			'sanitize' => 'TutorPress_REST_Live_Lessons_Controller::sanitize_wall_clock',
		),
		array(
			'method'   => 'PATCH',
			'callback' => 'TutorPress_REST_Live_Lessons_Controller::mutate_schedule',
			'name'     => 'start_date_time',
			'sanitize' => 'TutorPress_REST_Live_Lessons_Controller::sanitize_wall_clock',
		),
		array(
			'method'   => 'PATCH',
			'callback' => 'TutorPress_REST_Live_Lessons_Controller::mutate_schedule',
			'name'     => 'end_date_time',
			'sanitize' => 'TutorPress_REST_Live_Lessons_Controller::sanitize_wall_clock',
		),
	) === $false_schedule['data']['ledger'],
	'false-gate POST and PATCH use the wall-clock schema'
);
$plain_rows  = is_array( $false_schedule['data']['plain'] ) ? $false_schedule['data']['plain'] : array();
$plain_clear = 6 === count( $plain_rows )
	&& true === $plain_rows[0]['duration']
	&& true === $plain_rows[0]['timezone']
	&& true === $plain_rows[1]['duration']
	&& true === $plain_rows[1]['timezone']
	&& false === $plain_rows[2]['duration']
	&& false === $plain_rows[2]['timezone']
	&& false === $plain_rows[3]['duration']
	&& false === $plain_rows[3]['timezone']
	&& false === $plain_rows[4]['duration']
	&& false === $plain_rows[4]['timezone']
	&& false === $plain_rows[5]['duration']
	&& false === $plain_rows[5]['timezone'];
tutorpress_live_lessons_assert( $plain_clear, 'false-gate POST and PATCH carry duration and timezone and the three added POST routes omit both' );

$true_schedule = $run_controller_probe(
	<<<'PHP'
$controller = new class() extends TutorPress_REST_Live_Lessons_Controller {
	public $runs = 0;
	public $downstream = 0;
	public function mutate_schedule( $request ) {
		$this->runs++;
		return parent::mutate_schedule( $request );
	}
	protected function schedule_result( $normalized ) {
		$this->downstream++;
		return parent::schedule_result( $normalized );
	}
};
$controller->register_routes( true );
PHP
	. $schedule_reader
	. <<<'PHP'
$clock = '2026-10-06 15:00:00';
$bad   = '2026-10-06T15:00:00';
$make  = static function ( $method, $route, $params ) {
	$request         = new WP_REST_Request();
	$request->method = $method;
	$request->route  = $route;
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$code = static function ( $result ) {
	return $result instanceof WP_Error ? $result->get_error_code() : '';
};
$bare = static function ( $result ) {
	return $result instanceof WP_Error && ! ( $result instanceof WP_REST_Response );
};
$post  = '/live-lessons';
$patch = '/live-lessons/(?P<id>[\d]+)';
$snake = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'POST', $post, array( 'start_date_time' => $bad ) ) );
$snake_runs = $controller->runs;
$snake_down = $controller->downstream;
$direct = $controller->normalize_mutation( $make( 'POST', $post, array( 'startDateTime' => $bad ) ) );
$direct_runs = $controller->runs;
$direct_down = $controller->downstream;
$camel = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'PATCH', $patch, array( 'startDateTime' => $bad ) ) );
$camel_runs = $controller->runs;
$camel_down = $controller->downstream;
$conflict = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'POST', $post, array( 'start_date_time' => $clock, 'startDateTime' => '2026-10-06 16:00:00' ) ) );
$conflict_down = $controller->downstream;
$valid = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'POST', $post, array( 'startDateTime' => $clock ) ) );
$reflection = new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', 'sanitize_wall_clock' );
$source     = file( $reflection->getFileName() );
$body       = is_array( $source ) ? implode( '', array_slice( $source, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) ) : '';
echo json_encode( array(
	'enabled'       => true,
	'files'         => count( tutorpress_live_lessons_production_files() ),
	'aliases'       => $aliases,
	'fields'        => $fields,
	'ledger'        => $ledger,
	'text_field'    => false !== strpos( $body, 'sanitize_text_field' ),
	'clean'         => $controller->sanitize_wall_clock( $clock ),
	'dirty_error'   => $controller->sanitize_wall_clock( $bad ) instanceof WP_Error,
	'snake_code'    => $code( $snake ),
	'snake_bare'    => $bare( $snake ),
	'snake_runs'    => $snake_runs,
	'snake_down'    => $snake_down,
	'direct_code'   => $code( $direct ),
	'direct_bare'   => $bare( $direct ),
	'direct_runs'   => $direct_runs,
	'direct_down'   => $direct_down,
	'camel_code'    => $code( $camel ),
	'camel_bare'    => $bare( $camel ),
	'camel_array'   => is_array( $camel ),
	'camel_runs'    => $camel_runs,
	'camel_down'    => $camel_down,
	'conflict_code' => $code( $conflict ),
	'conflict_bare' => $bare( $conflict ),
	'conflict_down' => $conflict_down,
	'valid'         => $valid,
	'valid_down'    => $controller->downstream,
	'valid_runs'    => $controller->runs,
	'valid_wrapped' => $valid instanceof WP_REST_Response,
) );
PHP
);
tutorpress_live_lessons_assert(
	0 === $true_schedule['status'] && '' === $true_schedule['err'] && is_array( $true_schedule['data'] ),
	'true-gate schedule probe stays isolated ' . $true_schedule['status'] . ' ' . $true_schedule['err'] . ' ' . substr( (string) $true_schedule['out'], 0, 400 )
);
$wall_field = static function ( $method, $name, $required ) {
	return array(
		'method'   => $method,
		'callback' => 'mutate_schedule',
		'name'     => $name,
		'format'   => '',
		'required' => $required,
		'sanitize' => 'sanitize_wall_clock',
		'validate' => 'TutorPress_Live_Lesson_Datetime::is_wall_clock',
	);
};
$wall_ledger = true;
foreach ( $true_schedule['data']['ledger'] as $row ) {
	$wall_ledger = $wall_ledger && false === strpos( $row['sanitize'], 'sanitize_text_field' ) && false !== strpos( $row['sanitize'], 'sanitize_wall_clock' ) && false !== strpos( $row['callback'], 'mutate_schedule' );
}
tutorpress_live_lessons_assert(
	true === $true_schedule['data']['enabled']
	&& 17 === $true_schedule['data']['files']
	&& false === $true_schedule['data']['aliases']
	&& false === $true_schedule['data']['text_field']
	&& '2026-10-06 15:00:00' === $true_schedule['data']['clean']
	&& true === $true_schedule['data']['dirty_error']
	&& array(
		$wall_field( 'POST', 'start_date_time', true ),
		$wall_field( 'POST', 'end_date_time', true ),
		$wall_field( 'PATCH', 'start_date_time', false ),
		$wall_field( 'PATCH', 'end_date_time', false ),
	) === $true_schedule['data']['fields']
	&& 4 === count( $true_schedule['data']['ledger'] )
	&& true === $wall_ledger,
	'true-gate schedule schemas accept only minute wall clocks'
);
tutorpress_live_lessons_assert(
	true === $true_schedule['data']['snake_bare']
	&& 'rest_invalid_param' === $true_schedule['data']['snake_code']
	&& 0 === $true_schedule['data']['snake_runs']
	&& 0 === $true_schedule['data']['snake_down'],
	'invalid snake-case wall clock is rejected before the handler'
);
tutorpress_live_lessons_assert(
	true === $true_schedule['data']['direct_bare']
	&& 'invalid_wall_clock' === $true_schedule['data']['direct_code']
	&& 0 === $true_schedule['data']['direct_runs']
	&& 0 === $true_schedule['data']['direct_down']
	&& true === $true_schedule['data']['camel_bare']
	&& 'invalid_wall_clock' === $true_schedule['data']['camel_code']
	&& false === $true_schedule['data']['camel_array']
	&& 1 === $true_schedule['data']['camel_runs']
	&& 0 === $true_schedule['data']['camel_down']
	&& true === $true_schedule['data']['conflict_bare']
	&& 'live_lesson_schedule_conflict' === $true_schedule['data']['conflict_code']
	&& 0 === $true_schedule['data']['conflict_down'],
	'invalid camelCase-only wall clock is rejected before downstream schedule work'
);
tutorpress_live_lessons_assert(
	false === $true_schedule['data']['valid_wrapped']
	&& array( 'start_date_time' => '2026-10-06 15:00:00' ) === $true_schedule['data']['valid']
	&& 1 === $true_schedule['data']['valid_down']
	&& 3 === $true_schedule['data']['valid_runs'],
	'valid camelCase-only start reaches the handler as start_date_time'
);
$schedule_encoded = json_encode( array( $false_schedule['data'], $true_schedule['data'] ) );
tutorpress_live_lessons_assert(
	is_string( $schedule_encoded )
	&& false === strpos( $schedule_encoded, 'fake-credential-value' )
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& $production_files === tutorpress_live_lessons_production_files()
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Controller', false ),
	'wall-clock probes keep the false gate, eighteen-file loader, and no controller leak'
);

tutorpress_live_lessons_pass( 'wall-clock schedule accepts normalized aliases' );

$duration_probe = $run_controller_probe(
	<<<'PHP'
$controller = new class() extends TutorPress_REST_Live_Lessons_Controller {
	protected function replacement_enabled() {
		return false;
	}
	public $runs = 0;
	public $downstream = 0;
	public $stages = array();
	public $validate_result = null;
	public $raw_at_validate = null;
	public $seen = null;
	public function validate_duration( $value, $request = null, $param = null ) {
		unset( $param );
		$this->stages[] = 'validate-duration';
		$this->raw_at_validate = $request instanceof WP_REST_Request ? $request->get_param( 'duration' ) : $value;
		$this->validate_result = parent::validate_duration( $value );
		return $this->validate_result;
	}
	public function sanitize_duration( $value ) {
		$this->stages[] = 'sanitize-duration';
		return parent::sanitize_duration( $value );
	}
	public function validate_timezone( $value ) {
		$this->stages[] = 'validate-timezone';
		return parent::validate_timezone( $value );
	}
	public function mutate_schedule( $request ) {
		$this->runs++;
		$this->stages[] = 'handler';
		$this->seen = $request->get_param( 'duration' );
		return parent::mutate_schedule( $request );
	}
	protected function schedule_result( $normalized ) {
		$this->downstream++;
		return parent::schedule_result( $normalized );
	}
};
$controller->register_routes( true );
$property = new ReflectionProperty( 'TutorPress_Live_Lessons_Rest_Fake', 'handlers' );
$schema   = array();
foreach ( $property->getValue() as $handler ) {
	foreach ( $handler['endpoints'] as $endpoint ) {
		$methods = isset( $endpoint['methods'] ) ? (string) $endpoint['methods'] : '';
		if ( 'POST' !== $methods && 'PATCH' !== $methods ) {
			continue;
		}
		$args     = isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array();
		$callback = isset( $endpoint['callback'] ) && is_array( $endpoint['callback'] ) ? (string) $endpoint['callback'][1] : '';
		foreach ( array( 'duration', 'timezone', 'start_date_time' ) as $name ) {
			if ( ! isset( $args[ $name ] ) || ! is_array( $args[ $name ] ) ) {
				continue;
			}
			$arg      = $args[ $name ];
			$sanitize = isset( $arg['sanitize_callback'] ) ? $arg['sanitize_callback'] : '';
			$validate = isset( $arg['validate_callback'] ) ? $arg['validate_callback'] : '';
			$schema[] = array(
				'method'   => $methods,
				'callback' => $callback,
				'name'     => $name,
				'type'     => isset( $arg['type'] ) ? (string) $arg['type'] : '',
				'format'   => isset( $arg['format'] ) ? (string) $arg['format'] : '',
				'sanitize' => is_string( $sanitize ) ? $sanitize : ( is_array( $sanitize ) ? (string) $sanitize[1] : '' ),
				'validate' => is_array( $validate ) ? (string) $validate[1] : '',
			);
		}
	}
}
$typed = array( 'value' => 2, 'unit' => 'hours' );
$make  = static function ( $method, $route, $params ) {
	$request         = new WP_REST_Request();
	$request->method = $method;
	$request->route  = $route;
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$code = static function ( $result ) {
	return $result instanceof WP_Error ? $result->get_error_code() : '';
};
$bare = static function ( $result ) {
	return $result instanceof WP_Error && ! ( $result instanceof WP_REST_Response );
};
$param_code = static function ( $result, $name ) {
	if ( ! $result instanceof WP_Error ) {
		return '';
	}
	$data = $result->get_error_data();
	return is_array( $data ) && isset( $data['params'][ $name ] ) ? (string) $data['params'][ $name ] : '';
};
$post  = '/live-lessons';
$patch = '/live-lessons/(?P<id>[\d]+)';
$clock = '2026-10-06 15:00:00';
$plain = new TutorPress_REST_Live_Lessons_Controller();
$valid_check = $plain->validate_duration( $typed );
$valid_typed = $plain->sanitize_duration( $typed );
$invalid_check = $plain->validate_duration( array( 'value' => 9, 'unit' => 'hours' ) );
$invalid_typed = $plain->sanitize_duration( array( 'value' => 9, 'unit' => 'hours' ) );
$zone_check = $plain->validate_timezone( 'UTC' );
$zone_bad = $plain->validate_timezone( 'Mars/Phobos' );
$controller->stages = array();
$valid = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'POST', $post, array( 'duration' => $typed, 'timezone' => 'UTC' ) ) );
$valid_stages = $controller->stages;
$valid_raw = $controller->raw_at_validate;
$valid_flag = $controller->validate_result;
$valid_seen = $controller->seen;
$valid_runs = $controller->runs;
$valid_down = $controller->downstream;
$controller->stages = array();
$bad_duration = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'POST', $post, array( 'duration' => array( 'value' => 9, 'unit' => 'hours' ) ) ) );
$bad_duration_stages = $controller->stages;
$bad_duration_runs = $controller->runs;
$bad_duration_down = $controller->downstream;
$controller->stages = array();
$bad_zone = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'PATCH', $patch, array( 'timezone' => 'Mars/Phobos' ) ) );
$bad_zone_stages = $controller->stages;
$bad_zone_runs = $controller->runs;
$bad_zone_down = $controller->downstream;
$identity_codes = array();
$identity_runs = $controller->runs;
$identity_down = $controller->downstream;
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity ) {
	$rejected = TutorPress_Live_Lessons_Rest_Fake::dispatch(
		$make( 'PATCH', $patch, array( 'id' => 15, 'start_date_time' => $clock, $identity => 'supplied' ) )
	);
	$identity_codes[] = array(
		'key'   => $identity,
		'code'  => $code( $rejected ),
		'bare'  => $bare( $rejected ),
		'array' => is_array( $rejected ),
	);
}
$identity_runs = $controller->runs - $identity_runs;
$identity_down = $controller->downstream - $identity_down;
$kept = TutorPress_Live_Lessons_Rest_Fake::dispatch( $make( 'PATCH', $patch, array( 'id' => 15, 'start_date_time' => $clock ) ) );
$text_field = false;
foreach ( array( 'with_schedule_contract', 'validate_duration', 'sanitize_duration', 'duration_value', 'validate_timezone', 'mutate_schedule', 'normalize_mutation' ) as $method_name ) {
	$reflection = new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', $method_name );
	$source     = file( $reflection->getFileName() );
	$body       = is_array( $source ) ? implode( '', array_slice( $source, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) ) : '';
	$text_field = $text_field || false !== strpos( $body, 'sanitize_text_field' );
}
echo json_encode( array(
	'enabled'              => true,
	'files'                => count( tutorpress_live_lessons_production_files() ),
	'schema'               => $schema,
	'validate_true'        => true === $valid_check && true === $valid_flag,
	'raw'                  => $valid_raw,
	'sanitized'            => $valid_typed,
	'seen'                 => $valid_seen,
	'valid'                => $valid,
	'valid_wrapped'        => $valid instanceof WP_REST_Response,
	'stages'               => $valid_stages,
	'valid_runs'           => $valid_runs,
	'valid_down'           => $valid_down,
	'invalid_check'        => $invalid_check instanceof WP_Error ? $invalid_check->get_error_code() : '',
	'invalid_sanitize'     => $invalid_typed instanceof WP_Error ? $invalid_typed->get_error_code() : '',
	'bad_duration_code'    => $code( $bad_duration ),
	'bad_duration_param'   => $param_code( $bad_duration, 'duration' ),
	'bad_duration_bare'    => $bare( $bad_duration ),
	'bad_duration_stages'  => $bad_duration_stages,
	'bad_duration_runs'    => $bad_duration_runs,
	'bad_duration_down'    => $bad_duration_down,
	'zone_true'            => true === $zone_check,
	'zone_bad'             => $zone_bad instanceof WP_Error ? $zone_bad->get_error_code() : '',
	'bad_zone_code'        => $code( $bad_zone ),
	'bad_zone_param'       => $param_code( $bad_zone, 'timezone' ),
	'bad_zone_bare'        => $bare( $bad_zone ),
	'bad_zone_stages'      => $bad_zone_stages,
	'bad_zone_runs'        => $bad_zone_runs,
	'bad_zone_down'        => $bad_zone_down,
	'identities'           => $identity_codes,
	'identity_runs'        => $identity_runs,
	'identity_down'        => $identity_down,
	'kept'                 => $kept,
	'kept_down'            => $controller->downstream,
	'text_field'           => $text_field,
	'provider'             => TutorPress_Live_Lessons_Boundary_Fake::$ledger,
	'permissions'          => TutorPress_Live_Lessons_Rest_Fake::$permissions,
) );
PHP
);
tutorpress_live_lessons_assert(
	0 === $duration_probe['status'] && '' === $duration_probe['err'] && is_array( $duration_probe['data'] ),
	'duration probe stays isolated ' . $duration_probe['status'] . ' ' . $duration_probe['err'] . ' ' . substr( (string) $duration_probe['out'], 0, 400 )
);
$duration_field = static function ( $method, $name, $type, $sanitize, $validate ) {
	return array(
		'method'   => $method,
		'callback' => 'mutate_schedule',
		'name'     => $name,
		'type'     => $type,
		'format'   => '',
		'sanitize' => $sanitize,
		'validate' => $validate,
	);
};
$typed_duration = array( 'value' => 2, 'unit' => 'hours' );
$identity_rows  = array();
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity ) {
	$identity_rows[] = array(
		'key'   => $identity,
		'code'  => 'live_lesson_supplied_identity',
		'bare'  => true,
		'array' => false,
	);
}
tutorpress_live_lessons_assert(
	true === $duration_probe['data']['enabled']
	&& 17 === $duration_probe['data']['files']
	&& false === $duration_probe['data']['text_field']
	&& array(
		$duration_field( 'POST', 'duration', 'object', 'sanitize_duration', 'validate_duration' ),
		$duration_field( 'POST', 'timezone', 'string', '', 'validate_timezone' ),
		$duration_field( 'PATCH', 'duration', 'object', 'sanitize_duration', 'validate_duration' ),
		$duration_field( 'PATCH', 'timezone', 'string', '', 'validate_timezone' ),
	) === array_values(
		array_filter(
			$duration_probe['data']['schema'],
			static function ( $row ) {
				return 'duration' === $row['name'] || 'timezone' === $row['name'];
			}
		)
	),
	'true-gate POST and PATCH schemas add duration and timezone'
);
tutorpress_live_lessons_assert(
	true === $duration_probe['data']['validate_true']
	&& $typed_duration === $duration_probe['data']['raw']
	&& $typed_duration === $duration_probe['data']['sanitized']
	&& $typed_duration === $duration_probe['data']['seen']
	&& false === $duration_probe['data']['valid_wrapped']
	&& array( 'duration' => $typed_duration ) === $duration_probe['data']['valid']
	&& array( 'validate-duration', 'validate-timezone', 'sanitize-duration', 'handler' ) === $duration_probe['data']['stages']
	&& 1 === $duration_probe['data']['valid_runs']
	&& 1 === $duration_probe['data']['valid_down'],
	'valid hours duration reaches the handler as the typed array after sanitize'
);
tutorpress_live_lessons_assert(
	'invalid_duration' === $duration_probe['data']['invalid_check']
	&& 'invalid_duration' === $duration_probe['data']['invalid_sanitize']
	&& true === $duration_probe['data']['bad_duration_bare']
	&& 'rest_invalid_param' === $duration_probe['data']['bad_duration_code']
	&& 'invalid_duration' === $duration_probe['data']['bad_duration_param']
	&& array( 'validate-duration' ) === $duration_probe['data']['bad_duration_stages']
	&& 1 === $duration_probe['data']['bad_duration_runs']
	&& 1 === $duration_probe['data']['bad_duration_down'],
	'invalid duration is rejected before the handler'
);
tutorpress_live_lessons_assert(
	true === $duration_probe['data']['zone_true']
	&& 'invalid_timezone' === $duration_probe['data']['zone_bad']
	&& true === $duration_probe['data']['bad_zone_bare']
	&& 'rest_invalid_param' === $duration_probe['data']['bad_zone_code']
	&& 'invalid_timezone' === $duration_probe['data']['bad_zone_param']
	&& array( 'validate-timezone' ) === $duration_probe['data']['bad_zone_stages']
	&& 1 === $duration_probe['data']['bad_zone_runs']
	&& 1 === $duration_probe['data']['bad_zone_down'],
	'invalid timezone is rejected before the handler'
);
tutorpress_live_lessons_assert(
	$identity_rows === $duration_probe['data']['identities']
	&& 5 === $duration_probe['data']['identity_runs']
	&& 0 === $duration_probe['data']['identity_down']
	&& array( 'start_date_time' => '2026-10-06 15:00:00' ) === $duration_probe['data']['kept']
	&& 2 === $duration_probe['data']['kept_down']
	&& array() === $duration_probe['data']['provider']
	&& array() === $duration_probe['data']['permissions'],
	'forbidden identity keys are rejected at the handler before downstream work'
);
$duration_encoded = json_encode( $duration_probe['data'] );
tutorpress_live_lessons_assert(
	is_string( $duration_encoded )
	&& false === strpos( $duration_encoded, 'fake-credential-value' )
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& $production_files === tutorpress_live_lessons_production_files()
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Controller', false ),
	'duration probes keep the false gate, eighteen-file loader, and no controller leak'
);

tutorpress_live_lessons_pass( 'typed duration and approved timezone reject supplied identity' );

$mutation_probe = $run_controller_probe(
	<<<'PHP'
$controller = new class() extends TutorPress_REST_Live_Lessons_Controller {
	public $maps = 0;
	public function map_mutation( $request, array $normalized, array $course, $acting_user_id ) {
		$this->maps++;
		return parent::map_mutation( $request, $normalized, $course, $acting_user_id );
	}
};
$make = static function ( $params ) {
	$request = new WP_REST_Request();
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$clock    = '2026-10-06 15:00:00';
$end      = '2026-10-06 16:00:00';
$duration = array( 'value' => 2, 'unit' => 'hours' );
$settings = array(
	'waiting_room'     => true,
	'join_before_host' => false,
	'credentials'      => array( 'api_secret' => 'fake-credential-value' ),
	'provider_id'      => 'evt-forged',
);
$params   = array(
	'type'             => 'zoom',
	'title'            => 'Weekly lab',
	'description'      => 'Bring notes',
	'startDateTime'    => $clock,
	'endDateTime'      => $end,
	'timezone'         => 'UTC',
	'duration'         => $duration,
	'settings'         => $settings,
	'provider_config'  => array(
		'host'     => 'zoom-host-9',
		'password' => 'fake-credential-value',
	),
	'credentials'      => array( 'api_secret' => 'fake-credential-value' ),
	'host_id'          => 'forged-host',
	'acting_user_id'   => 999,
	'course_author_id' => 999,
	'course_id'        => 8,
	'topic_id'         => 4,
);
$request    = $make( $params );
$normalized = $controller->normalize_mutation( $request );
$mapped     = is_array( $normalized ) ? $controller->map_mutation( $request, $normalized, array( 'ID' => 8, 'post_author' => 17 ), 44 ) : null;
$forbidden  = array( 'credentials', 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' );
$present    = array();
$walk       = static function ( $value ) use ( &$walk, $forbidden, &$present ) {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( in_array( $key, $forbidden, true ) ) {
			$present[] = $key;
		}
		$walk( $child );
	}
};
if ( is_array( $mapped ) ) {
	$walk( $mapped );
}
$forged = array();
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity ) {
	$forged_request = $make( $params );
	$forged_request->set_param( $identity, 'supplied' );
	$rejected       = $controller->normalize_mutation( $forged_request );
	$forged[]       = array(
		'key'  => $identity,
		'code' => $rejected instanceof WP_Error ? $rejected->get_error_code() : '',
		'bare' => $rejected instanceof WP_Error && ! ( $rejected instanceof WP_REST_Response ),
	);
}
$reflection = new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', 'map_mutation' );
$source     = file( $reflection->getFileName() );
$body       = is_array( $source ) ? implode( '', array_slice( $source, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) ) : '';
$lookups    = false !== strpos( $body, 'get_post(' ) || false !== strpos( $body, 'get_current_user_id' ) || false !== strpos( $body, 'current_user_can' );
echo json_encode(
	array(
		'enabled'    => true,
		'files'      => count( tutorpress_live_lessons_production_files() ),
		'normalized' => $normalized,
		'mapped'     => $mapped,
		'present'    => $present,
		'maps'       => $controller->maps,
		'forged'     => $forged,
		'lookups'    => $lookups,
		'attach'     => method_exists( 'TutorPress_REST_Live_Lessons_Controller', 'map_attach' ),
		'provider'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger,
		'permissions'=> TutorPress_Live_Lessons_Rest_Fake::$permissions,
	)
);
PHP
);
tutorpress_live_lessons_assert(
	0 === $mutation_probe['status'] && '' === $mutation_probe['err'] && is_array( $mutation_probe['data'] ),
	'mutation map probe stays isolated ' . $mutation_probe['status'] . ' ' . $mutation_probe['err'] . ' ' . substr( (string) $mutation_probe['out'], 0, 400 )
);
$forged_rows = array();
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity ) {
	$forged_rows[] = array(
		'key'  => $identity,
		'code' => 'live_lesson_supplied_identity',
		'bare' => true,
	);
}
tutorpress_live_lessons_assert(
	true === $mutation_probe['data']['enabled']
	&& 17 === $mutation_probe['data']['files']
	&& false === $mutation_probe['data']['lookups']
	&& false === $mutation_probe['data']['attach']
	&& array(
		'start_date_time' => '2026-10-06 15:00:00',
		'end_date_time'   => '2026-10-06 16:00:00',
	) === $mutation_probe['data']['normalized']
	&& array(
		'provider'             => 'zoom',
		'title'                => 'Weekly lab',
		'content'              => 'Bring notes',
		'start'                => '2026-10-06 15:00:00',
		'end'                  => '2026-10-06 16:00:00',
		'timezone'             => 'UTC',
		'duration'             => array( 'value' => 2, 'unit' => 'hours' ),
		'host_id'              => 'zoom-host-9',
		'settings'             => array(
			'waiting_room'     => true,
			'join_before_host' => false,
		),
		'acting_user_id'       => 44,
		'course_author_id'     => 17,
		'acting_instructor_id' => 44,
	) === $mutation_probe['data']['mapped']
	&& array() === $mutation_probe['data']['present']
	&& 1 === $mutation_probe['data']['maps'],
	'normalized mutation maps description, type, host, course author, and both acting-user fields'
);
tutorpress_live_lessons_assert(
	$forged_rows === $mutation_probe['data']['forged']
	&& 1 === $mutation_probe['data']['maps']
	&& array() === $mutation_probe['data']['provider']
	&& array() === $mutation_probe['data']['permissions'],
	'forged provider identity is rejected by the normalizer before mapping'
);
$mutation_encoded = json_encode( $mutation_probe['data'] );
tutorpress_live_lessons_assert(
	is_string( $mutation_encoded )
	&& false === strpos( $mutation_encoded, 'fake-credential-value' )
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& $production_files === tutorpress_live_lessons_production_files()
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Controller', false ),
	'mutation map keeps the false gate, eighteen-file loader, and no controller leak'
);

tutorpress_live_lessons_pass( 'mutation map whitelists domain input' );

$binding_probe = $run_controller_probe(
	<<<'PHP'
class TutorPress_Live_Lessons_Binding_Adapter implements TutorPress_Live_Lesson_Provider_Adapter {
	public $hosts           = null;
	public $host_calls      = array();
	public $preflight_calls = array();
	public $settings_calls  = array();
	public $settings_script = null;
	public function capabilities() { return array(); }
	public function preflight( $owner_id ): bool {
		$this->preflight_calls[] = $owner_id;
		return false;
	}
	public function settings( $owner_id ) {
		$this->settings_calls[] = $owner_id;
		return $this->settings_script;
	}
	public function list_hosts( $owner_id ) {
		$this->host_calls[] = $owner_id;
		return $this->hosts;
	}
	public function validate_host( $owner_id, $host_id ): bool { return false; }
	public $lifecycle = 0;
	public function create( $owner_id, array $target ) { $this->lifecycle++; return null; }
	public function fetch( $owner_id, $provider_id ) { $this->lifecycle++; return null; }
	public function update( $owner_id, $provider_id, array $target ) { $this->lifecycle++; return null; }
	public function delete( $owner_id, $provider_id ) { $this->lifecycle++; return null; }
	public function compare_target( array $result, array $expected ) { return null; }
	public function metadata_map( array $result, array $target ) { $this->lifecycle++; return null; }
	public function hydrate_legacy( array $local ) { return null; }
	public function hydrate_item( array $local ) { return null; }
}
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_parent;
	public $post_author;
	public $post_title;
	public $post_content;
	public $menu_order;
	public $post_excerpt;
	public function __construct( array $fields ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}
$make_post = static function ( array $fields ) {
	$fields['post_excerpt'] = 'not-canonical';
	return new WP_Post( $fields );
};
$controller = new class() extends TutorPress_REST_Live_Lessons_Controller {
	public $gate        = false;
	public $actor       = 44;
	public $can_results = null;
	public $broad_only  = false;
	public $calls       = array();
	public $posts     = array();
	public $children  = array();
	public $topics    = array();
	public $sync_rows = null;
	public $meta      = array();
	public function get_mock_live_lessons() {
		return array();
	}
	protected function replacement_enabled() {
		return $this->gate;
	}
	protected function load_post( $post_id ) {
		$this->calls[] = array( 'post', $post_id );
		return isset( $this->posts[ $post_id ] ) ? $this->posts[ $post_id ] : null;
	}
	protected function load_posts( array $query ) {
		$this->calls[] = array( 'posts', $query );
		$type          = isset( $query['post_type'] ) ? $query['post_type'] : array();
		$rows          = array( 'topics' ) === $type ? $this->topics : $this->children;
		if ( ! isset( $query['post_parent'] ) ) {
			return $rows;
		}
		$parent = (int) $query['post_parent'];
		$kept   = array();
		foreach ( $rows as $row ) {
			if ( ! ( $row instanceof WP_Post ) || (int) $row->post_parent === $parent ) {
				$kept[] = $row;
			}
		}
		return $kept;
	}
	protected function load_current_user_can( $capability, $course_id ) {
		$this->calls[] = array( 'can', $capability, $course_id );
		if ( true === $this->broad_only ) {
			return 'edit_posts' === $capability;
		}
		if ( is_array( $this->can_results ) ) {
			return true === array_shift( $this->can_results );
		}
		return 'edit_post' === $capability && 15 === (int) $course_id;
	}
	protected function load_clock() {
		$this->calls[] = 'clock';
		return parent::load_clock();
	}
	protected function load_token() {
		$this->calls[] = 'token';
		return parent::load_token();
	}
	protected function load_user_id() {
		$this->calls[] = 'user';
		return $this->actor;
	}
	public $services = array();
	protected function load_option( $name ) {
		$this->calls[] = array( 'option', $name );
		return 'tutorpress_live_lesson_lock_8' === $name ? 'lock-bytes' : null;
	}
	protected function load_option_update( $name, $bytes, $prior ) {
		$this->calls[] = array( 'takeover', $name, $bytes, $prior );
		return 'tutorpress_live_lesson_lock_8' === $name && 'next-bytes' === $bytes && 'prior-bytes' === $prior;
	}
	protected function load_meta_rows( $post_id, $key ) {
		$this->calls[] = array( 'read', $post_id, $key );
		if ( is_array( $this->sync_rows ) && array_key_exists( (int) $post_id, $this->sync_rows ) ) {
			return $this->sync_rows[ (int) $post_id ];
		}
		return array( array( 'meta_id' => 31, 'meta_value' => 'prior-meta', 'post_id' => (int) $post_id ) );
	}
	protected function load_meta_update( $post_id, $key, $meta_id, $bytes, $prior ) {
		$this->calls[] = array( 'update', $post_id, $key, $meta_id, $bytes, $prior );
		return 8 === (int) $post_id && '_tutorpress_live_lesson_sync' === $key && 31 === (int) $meta_id && 'next-meta' === $bytes && 'prior-meta' === $prior;
	}
	protected function load_affected() {
		$this->calls[] = array( 'affected' );
		return 1;
	}
	protected function load_post_meta( $post_id, $key ) {
		$this->calls[] = array( 'meta', (int) $post_id, $key );
		$bucket        = isset( $this->meta[ (int) $post_id ] ) ? $this->meta[ (int) $post_id ] : array();
		return array_key_exists( $key, $bucket ) ? $bucket[ $key ] : null;
	}
	protected function load_service( $id ) {
		$this->calls[] = array( 'service', $id );
		return array_key_exists( $id, $this->services ) ? $this->services[ $id ] : null;
	}
	public $binding_runs    = 0;
	public $adapter_runs    = 0;
	public $stage_calls        = array();
	public $dispatch_calls     = array();
	public $unlinked_stages    = array();
	public $synced_stages      = array();
	public $synced_dispatches  = array();
	public $delete_stages      = array();
	public $duplicate_calls    = array();
	public $duplicate_script   = null;
	public $reconcile_calls    = array();
	public $reconcile_script   = null;
	public $attach_calls       = array();
	public $attach_script      = null;
	public $stage_script       = null;
	public $dispatch_script    = null;
	public $last_callbacks     = array();
	public function request_bindings() {
		$this->binding_runs++;
		return parent::request_bindings();
	}
	public function provider_adapter( $provider ) {
		$this->adapter_runs++;
		return parent::provider_adapter( $provider );
	}
	public function provider_linked( $post_id, $post_type ) { return $this->load_provider_linked( $post_id, $post_type ); }
	protected function stage_live_create( array $topic, array $course, $can, array $input, array $children, $adapter, DateTimeImmutable $now, $token ) {
		$this->stage_calls[] = array(
			'topic'    => isset( $topic['ID'] ) ? (int) $topic['ID'] : 0,
			'course'   => isset( $course['ID'] ) ? (int) $course['ID'] : 0,
			'author'   => isset( $course['post_author'] ) ? (int) $course['post_author'] : 0,
			'input'    => $input,
			'children' => $children,
			'adapter'  => $this->adapter_label( $adapter ),
			'clock'    => $now instanceof DateTimeImmutable,
			'token'    => is_string( $token ) ? $token : '',
		);
		unset( $can );
		return $this->stage_script;
	}
	protected function dispatch_live_create( array $staged, $token, $adapter, $read, $update, $affected, $write, array $topic, array $course, $can ) {
		$this->last_callbacks    = array( $read, $update, $affected, $write );
		$this->dispatch_calls[]  = array(
			'staged'   => $staged,
			'token'    => is_string( $token ) ? $token : '',
			'adapter'  => $this->adapter_label( $adapter ),
			'read'     => is_callable( $read ),
			'update'   => is_callable( $update ),
			'affected' => is_callable( $affected ),
			'write'    => is_callable( $write ),
			'topic'    => isset( $topic['ID'] ) ? (int) $topic['ID'] : 0,
			'course'   => isset( $course['ID'] ) ? (int) $course['ID'] : 0,
		);
		unset( $can );
		return $this->dispatch_script;
	}
	protected function stage_live_unlinked( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		$this->unlinked_stages[] = array(
			'item'     => isset( $item['ID'] ) ? (int) $item['ID'] : 0,
			'type'     => isset( $item['post_type'] ) ? $item['post_type'] : '',
			'topic'    => isset( $topic['ID'] ) ? (int) $topic['ID'] : 0,
			'course'   => isset( $course['ID'] ) ? (int) $course['ID'] : 0,
			'input'    => $input,
			'adapter'  => $this->adapter_label( $adapter ),
			'clock'    => $now instanceof DateTimeImmutable,
			'token'    => is_string( $token ) ? $token : '',
			'read'     => is_callable( $read ),
			'update'   => is_callable( $update ),
			'affected' => is_callable( $affected ),
		);
		unset( $can );
		return $this->stage_script;
	}
	protected function stage_live_synced( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		$this->synced_stages[] = array(
			'item'     => isset( $item['ID'] ) ? (int) $item['ID'] : 0,
			'type'     => isset( $item['post_type'] ) ? $item['post_type'] : '',
			'topic'    => isset( $topic['ID'] ) ? (int) $topic['ID'] : 0,
			'course'   => isset( $course['ID'] ) ? (int) $course['ID'] : 0,
			'input'    => $input,
			'adapter'  => $this->adapter_label( $adapter ),
			'clock'    => $now instanceof DateTimeImmutable,
			'token'    => is_string( $token ) ? $token : '',
			'read'     => is_callable( $read ),
			'update'   => is_callable( $update ),
			'affected' => is_callable( $affected ),
		);
		unset( $can );
		return $this->stage_script;
	}
	protected function stage_live_delete( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		$this->delete_stages[] = array(
			'item'        => isset( $item['ID'] ) ? (int) $item['ID'] : 0,
			'item_type'   => isset( $item['post_type'] ) ? $item['post_type'] : '',
			'item_status' => isset( $item['post_status'] ) ? $item['post_status'] : '',
			'topic'       => isset( $topic['ID'] ) ? (int) $topic['ID'] : 0,
			'topic_type'  => isset( $topic['post_type'] ) ? $topic['post_type'] : '',
			'course'      => isset( $course['ID'] ) ? (int) $course['ID'] : 0,
			'course_type' => isset( $course['post_type'] ) ? $course['post_type'] : '',
			'author'      => isset( $course['post_author'] ) ? (int) $course['post_author'] : 0,
			'input'       => $input,
			'adapter'     => $this->adapter_label( $adapter ),
			'clock'       => $now instanceof DateTimeImmutable,
			'token'       => is_string( $token ) ? $token : '',
			'read'        => is_callable( $read ),
			'update'      => is_callable( $update ),
			'affected'    => is_callable( $affected ),
			'edit_posts'  => is_callable( $can ) && true === $can( 'edit_posts', isset( $course['ID'] ) ? (int) $course['ID'] : 0 ),
		);
		return $this->stage_script;
	}
	protected function duplicate_live( array $source, array $topic, array $course, array $target, array $target_course, array $children, array $meta, $can ) {
		$this->duplicate_calls[] = array(
			'source'        => $source,
			'topic'         => $topic,
			'course'        => $course,
			'target'        => $target,
			'target_course' => $target_course,
			'children'      => $children,
			'meta'          => $meta,
			'edit_posts'    => is_callable( $can ) && true === $can( 'edit_posts', isset( $course['ID'] ) ? (int) $course['ID'] : 0 ),
		);
		return $this->duplicate_script;
	}
	protected function dispatch_live_synced( array $staged, $token, $adapter, $read, $update, $affected, $write ) {
		$this->synced_dispatches[] = array(
			'staged'   => $staged,
			'token'    => is_string( $token ) ? $token : '',
			'adapter'  => $this->adapter_label( $adapter ),
			'read'     => is_callable( $read ),
			'update'   => is_callable( $update ),
			'affected' => is_callable( $affected ),
			'write'    => is_callable( $write ),
		);
		return $this->dispatch_script;
	}
	private function adapter_label( $adapter ) {
		$google = isset( $this->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'] ) ? $this->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'] : null;
		$zoom   = isset( $this->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] ) ? $this->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] : null;
		if ( null !== $google && $adapter === $google ) {
			return 'google_meet';
		}
		return null !== $zoom && $adapter === $zoom ? 'zoom' : ( null === $adapter ? 'null' : 'other' );
	}
	protected function run_reconcile( $action, array $item, array $topic, array $course, $can, array $input, $adapter, array $bindings ) {
		$option_name = 'tutorpress_live_lesson_lock_8';
		$this->reconcile_calls[] = array(
			'action'   => $action,
			'item'     => $item,
			'topic'    => $topic,
			'course'   => $course,
			'input'    => $input,
			'adapter'  => $this->adapter_label( $adapter ),
			'zone'     => $bindings['clock'] instanceof DateTimeImmutable ? $bindings['clock']->getTimezone()->getName() : '',
			'token'    => is_string( $bindings['token'] ) ? $bindings['token'] : '',
			'read'     => is_callable( $bindings['read'] ),
			'update'   => is_callable( $bindings['update'] ),
			'affected' => is_callable( $bindings['affected'] ),
			'write'    => array_key_exists( 'write', $bindings ) && is_callable( $bindings['write'] ),
			'option'   => is_callable( $bindings['option'] ) ? $bindings['option']( $option_name ) : null,
			'takeover' => is_callable( $bindings['takeover'] ) ? $bindings['takeover']( $option_name, 'next-bytes', 'prior-bytes' ) : null,
		);
		unset( $can );
		return $this->reconcile_script;
	}
	protected function run_attach( $kind, array $item, array $topic, array $course, $can, array $input, $adapter, array $bindings ) {
		$option_name = 'tutorpress_live_lesson_lock_8';
		$this->attach_calls[] = array(
			'kind'     => $kind,
			'item'     => $item,
			'topic'    => $topic,
			'course'   => $course,
			'input'    => $input,
			'adapter'  => $this->adapter_label( $adapter ),
			'zone'     => $bindings['clock'] instanceof DateTimeImmutable ? $bindings['clock']->getTimezone()->getName() : '',
			'token'    => is_string( $bindings['token'] ) ? $bindings['token'] : '',
			'read'     => is_callable( $bindings['read'] ),
			'write'    => array_key_exists( 'write', $bindings ) && is_callable( $bindings['write'] ),
			'option'   => is_callable( $bindings['option'] ) ? $bindings['option']( $option_name ) : null,
			'takeover' => is_callable( $bindings['takeover'] ) ? $bindings['takeover']( $option_name, 'next-bytes', 'prior-bytes' ) : null,
		);
		unset( $can );
		return $this->attach_script;
	}
};
$false_bindings = $controller->request_bindings();
$false_calls    = $controller->calls;
$controller->calls = array();
$controller->gate  = true;
$controller->posts = array(
	8 => $make_post(
		array(
			'ID'           => '8',
			'post_type'    => 'courses',
			'post_status'  => 'publish',
			'post_parent'  => '0',
			'post_author'  => '17',
			'post_title'   => 'Lab',
			'post_content' => 'Notes',
			'menu_order'   => '3',
		)
	),
);
$controller->children = array(
	$make_post(
		array(
			'ID'           => '21',
			'post_type'    => 'lesson',
			'post_status'  => 'draft',
			'post_parent'  => '4',
			'post_author'  => '17',
			'post_title'   => 'Lesson',
			'post_content' => 'Body',
			'menu_order'   => '1',
		)
	),
	$make_post(
		array(
			'ID'           => '22',
			'post_type'    => 'tutor_zoom_meeting',
			'post_status'  => 'publish',
			'post_parent'  => '4',
			'post_author'  => '17',
			'post_title'   => 'Zoom',
			'post_content' => 'Meet',
			'menu_order'   => '2',
		)
	),
	new stdClass(),
);
$bindings = $controller->request_bindings();
$built    = $controller->calls;
$binding_resolutions = $controller->adapter_resolutions;
$controller->calls = array();
$found    = is_array( $bindings ) ? $bindings['lookup']( 8 ) : null;
$missing  = is_array( $bindings ) ? $bindings['lookup']( 99 ) : 'skip';
$children = is_array( $bindings ) ? $bindings['children']( 4 ) : null;
$allowed  = is_array( $bindings ) ? $bindings['can']( 15 ) : null;
$denied   = is_array( $bindings ) ? $bindings['can']( 16 ) : null;
$queries  = array();
$caps     = array();
$posts    = array();
foreach ( $controller->calls as $call ) {
	if ( ! is_array( $call ) ) {
		continue;
	}
	if ( 'posts' === $call[0] ) {
		$queries[] = $call[1];
	} elseif ( 'can' === $call[0] ) {
		$caps[] = array( $call[1], $call[2] );
	} elseif ( 'post' === $call[0] ) {
		$posts[] = $call[1];
	}
}
$controller->calls = array();
$option_value = is_array( $bindings ) ? $bindings['option']( 'tutorpress_live_lesson_lock_8' ) : null;
$option_miss  = is_array( $bindings ) ? $bindings['option']( 'tutorpress_live_lesson_lock_9' ) : 'skip';
$takeover     = is_array( $bindings ) ? $bindings['takeover']( 'tutorpress_live_lesson_lock_8', 'next-bytes', 'prior-bytes' ) : null;
$read_rows    = is_array( $bindings ) ? $bindings['read']( 8, '_tutorpress_live_lesson_sync' ) : null;
$updated      = is_array( $bindings ) ? $bindings['update']( 8, '_tutorpress_live_lesson_sync', 31, 'next-meta', 'prior-meta' ) : null;
$affected      = is_array( $bindings ) ? $bindings['affected']() : null;
$write_rows    = array();
$provider_blob = '{"id":"z","url":"https://zoom.example/j"}';
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		global $write_rows, $controller;
		$controller->calls[] = array( 'write', (int) $post_id, $key, $value );
		foreach ( $write_rows as $index => $row ) {
			if ( (int) $row['post_id'] !== (int) $post_id || $row['meta_key'] !== $key ) {
				continue;
			}
			if ( isset( $row['meta_value'] ) && (string) $row['meta_value'] === (string) $value ) {
				return false;
			}
			$write_rows[ $index ]['meta_value'] = $value;
			return true;
		}
		$write_rows[] = array(
			'post_id'    => (int) $post_id,
			'meta_key'   => $key,
			'meta_value' => $value,
		);
		return 64;
	}
}
$written     = null;
$rewritten   = null;
if ( is_array( $bindings ) ) {
	$written   = $bindings['write']( 8, '_tutor_zm_data', $provider_blob );
	$rewritten = $bindings['write']( 8, '_tutor_zm_data', $provider_blob );
}
$stored_rows   = $write_rows;
$storage_calls = $controller->calls;
$storage_resolutions = $controller->adapter_resolutions;
$controller->calls = array();
$controller->topics = array(
	$make_post(
		array(
			'ID'           => '11',
			'post_type'    => 'topics',
			'post_status'  => 'draft',
			'post_parent'  => '7',
			'post_author'  => '17',
			'post_title'   => 'Topic',
			'post_content' => 'Outline',
			'menu_order'   => '4',
		)
	),
	new stdClass(),
);
$topic_rows  = is_array( $bindings ) ? $bindings['topics']( 7 ) : null;
$topic_calls = $controller->calls;
$topic_ledger = $controller->adapter_resolutions;
$sync_bytes = static function ( $state, $extra = array() ) {
	return json_encode(
		array_merge(
			array(
				'error'                   => null,
				'operation'               => null,
				'owner_id'                => 42,
				'provenance'              => null,
				'provider'                => 'zoom',
				'provider_id'             => null,
				'reconciliation_required' => false,
				'schema_version'          => 1,
				'state'                   => $state,
				'version'                 => 1,
				'warning'                 => null,
			),
			$extra
		)
	);
};
$sync_row = static function ( $post_id, $bytes ) {
	return array( array( 'meta_id' => 31, 'meta_value' => $bytes, 'post_id' => (int) $post_id ) );
};
$controller->sync_rows = array(
	80 => array(),
	81 => array(
		array( 'meta_id' => 1, 'meta_value' => 'one', 'post_id' => 81 ),
		array( 'meta_id' => 2, 'meta_value' => 'two', 'post_id' => 81 ),
	),
	82 => $sync_row( 82, 'not-json' ),
	83 => $sync_row( 83, '' ),
	84 => $sync_row( 84, $sync_bytes( 'pending', array( 'api_secret' => 'hidden' ) ) ),
	90 => $sync_row( 90, $sync_bytes( 'pending' ) ),
	91 => $sync_row( 91, $sync_bytes( 'error' ) ),
	92 => $sync_row( 92, $sync_bytes( 'unlinked' ) ),
	93 => $sync_row( 93, $sync_bytes( 'synced' ) ),
);
$controller->calls = array();
$envelope_ids = array( 80, 81, 82, 83, 84, 90, 91, 92, 93 );
$envelopes    = array();
foreach ( $envelope_ids as $envelope_id ) {
	$envelopes[ $envelope_id ] = is_array( $bindings ) ? $bindings['envelope']( $envelope_id ) : 'skip';
}
$envelope_calls  = $controller->calls;
$envelope_ledger = $controller->adapter_resolutions;
$controller->calls = array();
$meta_before = $controller->adapter_resolutions;
$meta_boundary_before = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$meta_key_list = static function ( $calls ) {
	$keys = array();
	foreach ( $calls as $call ) {
		$keys[] = $call[2];
	}
	return $keys;
};
$meta_batches = array();
$unknown = is_array( $bindings ) ? $bindings['metadata']( 70, 'lesson' ) : 'skip';
$unknown_calls = $controller->calls;
$meta_batches[] = $unknown_calls;
$controller->meta[71] = array(
	'tutor-google-meet-start-datetime' => '',
	'tutor-google-meet-end-datetime'   => false,
	'tutor-google-meet-event-details'  => null,
	'tutor-google-meet-link'           => '',
);
$controller->calls = array();
$empty_meta = is_array( $bindings ) ? $bindings['metadata']( 71, 'tutor-google-meet' ) : 'skip';
$empty_keys = $meta_key_list( $controller->calls );
$meta_batches[] = $controller->calls;
$controller->meta[72] = array(
	'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
	'tutor-google-meet-end-datetime'   => '',
	'tutor-google-meet-event-details'  => '{',
	'tutor-google-meet-link'           => 'https://meet.example/abc',
);
$controller->calls = array();
$malformed = is_array( $bindings ) ? $bindings['metadata']( 72, 'tutor-google-meet' ) : 'skip';
$meta_batches[] = $controller->calls;
$controller->meta[75] = array(
	'tutor-google-meet-event-details' => '"omitted-json-scalar"',
	'tutor-google-meet-link'          => 'https://meet.example/scalar',
);
$controller->calls = array();
$scalar_meta = is_array( $bindings ) ? $bindings['metadata']( 75, 'tutor-google-meet' ) : 'skip';
$meta_batches[] = $controller->calls;
$controller->meta[73] = array(
	'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
	'tutor-google-meet-end-datetime'   => false,
	'tutor-google-meet-event-details'  => array(
		'id'           => 'evt-1',
		'api_secret'   => 'fake-credential-value',
		'safe_link'    => 'https://meet.example/join',
		'nested'       => array(
			'password'      => 'hidden-pw',
			'token'         => 'hidden-tok',
			'credential'    => 'fake-credential-value',
			'access_token'  => 'hidden-access',
			'title'         => 'Safe title',
		),
	),
	'tutor-google-meet-link'           => 'https://meet.example/abc',
);
$controller->calls = array();
$google_meta = is_array( $bindings ) ? $bindings['metadata']( 73, 'tutor-google-meet' ) : 'skip';
$google_keys = $meta_key_list( $controller->calls );
$meta_batches[] = $controller->calls;
$controller->meta[74] = array(
	'_tutor_zm_start_date'     => '2026-10-06',
	'_tutor_zm_start_datetime' => '2026-10-06 18:00:00',
	'_tutor_zm_duration'       => 90,
	'_tutor_zm_duration_unit'  => 'min',
	'_tutor_zm_for_course'     => 7,
	'_tutor_zm_for_topic'      => 4,
	'_tutor_zm_data'           => json_encode(
		array(
			'id'         => 'zm-1',
			'timezone'   => 'UTC',
			'api_secret' => 'fake-credential-value',
			'meeting'    => array(
				'password'   => 'hidden-pw',
				'token'      => 'hidden-tok',
				'credential' => 'fake-credential-value',
				'host_id'    => 'host-9',
			),
		)
	),
);
$controller->calls = array();
$zoom_meta = is_array( $bindings ) ? $bindings['metadata']( 74, 'tutor_zoom_meeting' ) : 'skip';
$zoom_keys = $meta_key_list( $controller->calls );
$meta_batches[] = $controller->calls;
$controller->meta[76] = array(
	'_tutor_zm_start_date' => '2026-10-06',
	'_tutor_zm_data'       => 'not-json',
);
$controller->calls = array();
$zoom_bad = is_array( $bindings ) ? $bindings['metadata']( 76, 'tutor_zoom_meeting' ) : 'skip';
$zoom_bad_keys = $meta_key_list( $controller->calls );
$meta_batches[] = $controller->calls;
$meta_after = $controller->adapter_resolutions;
$meta_boundary_after = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$meta_service = array();
foreach ( $meta_batches as $batch ) {
	foreach ( $batch as $call ) {
		if ( is_array( $call ) && 'service' === $call[0] ) {
			$meta_service[] = $call;
		}
	}
}
$controller->calls = array();
$google = new TutorPress_Live_Lessons_Binding_Adapter();
$zoom   = new TutorPress_Live_Lessons_Binding_Adapter();
$controller->services = array(
	'TutorPress_Live_Lesson_Provider_Adapter:google_meet' => $google,
	'TutorPress_Live_Lesson_Provider_Adapter:zoom'        => $zoom,
);
$controller->calls = array();
$google_adapter = $controller->provider_adapter( 'google_meet' );
$zoom_adapter   = $controller->provider_adapter( 'zoom' );
$valid_calls    = $controller->calls;
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] = new stdClass();
$controller->calls = array();
$foreign = $controller->provider_adapter( 'zoom' );
$foreign_calls = $controller->calls;
$before_invalid = $controller->adapter_resolutions;
$controller->calls = array();
$invalid = array();
foreach ( array( 'google', 'ZOOM', '', 'google_meet ', 'TutorPress_Live_Lesson_Provider_Adapter:zoom', null, array( 'zoom' ) ) as $bad ) {
	$invalid[] = $controller->provider_adapter( $bad );
}
$invalid_calls = $controller->calls;
$ledger = $controller->adapter_resolutions;
$method_body = static function ( $method ) {
	$ref = new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', $method );
	$src = file( $ref->getFileName() );
	return is_array( $src ) ? implode( '', array_slice( $src, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) ) : '';
};
$option_body   = $method_body( 'load_option' );
$takeover_body = $method_body( 'load_option_update' );
$read_body     = $method_body( 'load_meta_rows' );
$update_body   = $method_body( 'load_meta_update' );
$affected_body = $method_body( 'load_affected' );
$write_body    = $method_body( 'request_bindings' );
$service_body  = $method_body( 'load_service' );
$adapter_body  = $method_body( 'provider_adapter' );
$metadata_body = $method_body( 'metadata' );
$provider_meta_body = $method_body( 'load_provider_meta' );
$post_meta_body = $method_body( 'load_post_meta' );
$strip_body = $method_body( 'strip_sensitive_meta' );
$clock = is_array( $bindings ) ? $bindings['clock'] : null;
$token = is_array( $bindings ) ? $bindings['token'] : '';
$handlers = array( 'get_items', 'get_item', 'delete_item', 'duplicate_item', 'get_zoom_users', 'get_google_meet_settings', 'mutate_schedule', 'normalize_mutation', 'map_mutation' );
$handler_calls = array();
foreach ( $handlers as $name ) {
	$method = new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', $name );
	$source = file( $method->getFileName() );
	$body   = is_array( $source ) ? implode( '', array_slice( $source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) ) : '';
	foreach ( array( 'request_bindings', 'provider_adapter', 'load_option', 'load_option_update', 'load_meta_rows', 'load_meta_update', 'load_affected', 'load_sync_meta', 'load_service', 'metadata', 'load_post_meta', 'load_provider_meta', 'rest_outcome' ) as $needle ) {
		if ( false !== strpos( $body, $needle ) ) {
			$handler_calls[] = $name;
		}
	}
}
$start = ( new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', 'request_bindings' ) )->getStartLine();
$end   = ( new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', 'canonical_post' ) )->getEndLine();
$file  = file( ( new ReflectionMethod( 'TutorPress_REST_Live_Lessons_Controller', 'request_bindings' ) )->getFileName() );
$seam  = is_array( $file ) ? implode( '', array_slice( $file, $start - 1, $end - $start + 1 ) ) : '';
$blocked = array( 'TutorPress_Live_Lesson_Provider_Adapter', 'TutorPress_Service_Container', 'get_option', 'TutorPress_Live_Lesson_Authorization', 'TutorPress_Live_Lesson_Sync_Orchestrator', 'TutorPress_Live_Lesson_Sync_Repository' );
$touched = array();
foreach ( $blocked as $name ) {
	if ( false !== strpos( $seam, $name ) ) {
		$touched[] = $name;
	}
}
if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code() {
		return 401;
	}
}
$pack_result = static function ( $result ) {
	if ( $result instanceof WP_Error ) {
		$data = $result->get_error_data();
		return array(
			'kind'    => 'error',
			'code'    => $result->get_error_code(),
			'message' => $result->get_error_message(),
			'status'  => is_array( $data ) && isset( $data['status'] ) ? $data['status'] : null,
		);
	}
	return array(
		'kind' => $result instanceof WP_REST_Response ? 'response' : 'other',
		'data' => $result instanceof WP_REST_Response ? $result->get_data() : null,
	);
};
$named_calls = static function ( $calls, $name ) {
	$found = array();
	foreach ( $calls as $call ) {
		if ( is_array( $call ) && $name === $call[0] ) {
			$found[] = $call;
		}
	}
	return $found;
};
$read_collection = static function ( $params ) use ( $controller, $pack_result, $named_calls ) {
	$controller->calls                = array();
	$controller->adapter_resolutions  = array( 'zoom' );
	$request                          = new WP_REST_Request();
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$result = $controller->get_items( $request );
	return array(
		'packed'   => $pack_result( $result ),
		'calls'    => $controller->calls,
		'ledger'   => $controller->adapter_resolutions,
		'services' => $named_calls( $controller->calls, 'service' ),
		'metas'    => $named_calls( $controller->calls, 'meta' ),
	);
};
$controller->gate = true;
$invalid_values   = array(
	array(),
	array( 'course_id' => 0 ),
	array( 'course_id' => -4 ),
	array( 'course_id' => '0' ),
	array( 'course_id' => '00' ),
	array( 'course_id' => '-3' ),
	array( 'course_id' => '15x' ),
	array( 'course_id' => '15 ' ),
	array( 'course_id' => 15.5 ),
	array( 'course_id' => true ),
	array( 'course_id' => array( 15 ) ),
	array( 'course_id' => null ),
);
$invalid_reads = array();
foreach ( $invalid_values as $params ) {
	$invalid_reads[] = $read_collection( $params );
}
$controller->posts[16] = $make_post(
	array(
		'ID'           => 16,
		'post_type'    => 'lesson',
		'post_status'  => 'publish',
		'post_parent'  => 0,
		'post_author'  => 17,
		'post_title'   => 'Not a course',
		'post_content' => '',
		'menu_order'   => 0,
	)
);
$missing_course = $read_collection( array( 'course_id' => 99 ) );
$wrong_type     = $read_collection( array( 'course_id' => 16 ) );
$denied_course  = $read_collection( array( 'course_id' => 8 ) );
$controller->posts[15] = $make_post(
	array(
		'ID'           => '15',
		'post_type'    => 'courses',
		'post_status'  => 'publish',
		'post_parent'  => '0',
		'post_author'  => '17',
		'post_title'   => 'Course',
		'post_content' => 'Body',
		'menu_order'   => '1',
	)
);
$lesson = static function ( $id, $type, $status, $parent ) use ( $make_post ) {
	return $make_post(
		array(
			'ID'           => $id,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_parent'  => $parent,
			'post_author'  => 17,
			'post_title'   => 'Item ' . $id,
			'post_content' => '',
			'menu_order'   => $id,
		)
	);
};
$controller->topics   = array(
	$lesson( 40, 'topics', 'publish', 15 ),
	$lesson( 41, 'topics', 'publish', 15 ),
	new stdClass(),
);
$controller->children = array(
	$lesson( 201, 'tutor-google-meet', 'publish', 40 ),
	$lesson( 202, 'tutor_zoom_meeting', 'publish', 40 ),
	$lesson( 203, 'tutor-google-meet', 'publish', 40 ),
	$lesson( 204, 'tutor_zoom_meeting', 'publish', 40 ),
	$lesson( 205, 'tutor-google-meet', 'publish', 40 ),
	$lesson( 206, 'tutor_zoom_meeting', 'publish', 40 ),
	$lesson( 207, 'tutor-google-meet', 'draft', 40 ),
	$lesson( 208, 'lesson', 'publish', 40 ),
	$lesson( 209, 'tutor_quiz', 'publish', 40 ),
	$lesson( 210, 'tutor_assignments', 'publish', 40 ),
	$lesson( 211, 'tutor_zoom_meeting', 'private', 40 ),
	$lesson( 212, 'tutor_zoom_meeting', 'publish', 41 ),
);
$controller->sync_rows = array(
	201 => $sync_row( 201, $sync_bytes( 'pending' ) ),
	202 => $sync_row( 202, $sync_bytes( 'error' ) ),
	203 => $sync_row( 203, $sync_bytes( 'unlinked' ) ),
	204 => $sync_row( 204, $sync_bytes( 'synced' ) ),
	205 => array(),
	206 => $sync_row( 206, 'not-json' ),
	207 => $sync_row( 207, $sync_bytes( 'pending' ) ),
	212 => $sync_row( 212, $sync_bytes( 'synced' ) ),
);
$authorized = $read_collection( array( 'course_id' => 15 ) );
$filtered   = $read_collection(
	array(
		'course_id' => '15',
		'topic_id'  => 40,
		'type'      => 'zoom',
		'per_page'  => 1,
		'page'      => 2,
		'status'    => 'live',
	)
);
$controller->can_results = array( true, false );
$null_query              = $read_collection( array( 'course_id' => 15 ) );
$controller->can_results = null;
$collection_read_body = $method_body( 'read_items' );
$read_proof           = false === strpos( $collection_read_body, 'provider_adapter' )
	&& false === strpos( $collection_read_body, 'load_service' )
	&& false === strpos( $collection_read_body, 'metadata' )
	&& false === strpos( $collection_read_body, 'format_response' )
	&& false === strpos( $collection_read_body, 'ensure_tutor_lms' )
	&& false === strpos( $collection_read_body, 'edit_posts' )
	&& false === strpos( $collection_read_body, "get_param('status')" )
	&& false === strpos( $collection_read_body, 'get_param("status")' )
	&& false !== strpos( $collection_read_body, "['topic_id', 'type', 'per_page', 'page']" )
	&& false !== strpos( $collection_read_body, 'authorize_course' )
	&& false !== strpos( $collection_read_body, 'TutorPress_Live_Lesson_Collection_Query::query' )
	&& false !== strpos( $collection_read_body, 'rest_ensure_response' )
	&& false !== strpos( $collection_read_body, "'edit_post' === \$capability" );
$get_item_body    = $method_body( 'get_item' );
$item_read_body   = $method_body( 'read_item' );
$item_guard_proof = false === strpos( $get_item_body, 'replacement_enabled' )
	&& false !== strpos( $get_item_body, '$this->read_item(' )
	&& false === strpos( $get_item_body, 'ensure_tutor_lms' )
	&& false === strpos( $get_item_body, 'get_post' )
	&& false === strpos( $get_item_body, 'format_response' )
	&& false === strpos( $get_item_body, 'request_bindings' )
	&& false === strpos( $get_item_body, 'provider_adapter' )
	&& false === strpos( $get_item_body, 'load_service' );
$item_read_proof = false === strpos( $item_read_body, 'provider_adapter' )
	&& false === strpos( $item_read_body, 'load_service' )
	&& false === strpos( $item_read_body, 'ensure_tutor_lms' )
	&& false === strpos( $item_read_body, 'edit_posts' )
	&& false !== strpos( $item_read_body, "'edit_post' === " )
	&& false !== strpos( $item_read_body, 'tutor-google-meet' )
	&& false !== strpos( $item_read_body, 'tutor_zoom_meeting' )
	&& false !== strpos( $item_read_body, 'TutorPress_Live_Lesson_Authorization::authorize(' )
	&& false !== strpos( $item_read_body, 'TutorPress_Live_Lesson_Response_Projector::project_item' );
$false_item = array( 'entered' => false );
$read_one         = static function ( $params, $can_results = null ) use ( $controller, $pack_result, $named_calls ) {
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->can_results         = $can_results;
	$request                         = new WP_REST_Request();
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$result = $controller->get_item( $request );
	return array(
		'packed'   => $pack_result( $result ),
		'calls'    => $controller->calls,
		'ledger'   => $controller->adapter_resolutions,
		'services' => $named_calls( $controller->calls, 'service' ),
	);
};
$invalid_ids = array(
	array(),
	array( 'id' => 0 ),
	array( 'id' => -3 ),
	array( 'id' => '0' ),
	array( 'id' => '00' ),
	array( 'id' => '-2' ),
	array( 'id' => '8x' ),
	array( 'id' => '8 ' ),
	array( 'id' => 8.5 ),
	array( 'id' => true ),
	array( 'id' => false ),
	array( 'id' => array( 8 ) ),
	array( 'id' => null ),
	array( 'id' => '' ),
	array( 'id' => new stdClass() ),
);
$invalid_items = array();
foreach ( $invalid_ids as $params ) {
	$invalid_items[] = $read_one( $params );
}
$put = static function ( $id, $type, $status, $parent ) use ( $controller, $make_post ) {
	$controller->posts[ (int) $id ] = $make_post(
		array(
			'ID'           => $id,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_parent'  => $parent,
			'post_author'  => 17,
			'post_title'   => 'Stored title',
			'post_content' => 'Stored notes',
			'menu_order'   => 2,
		)
	);
};
$put( 15, 'courses', 'publish', 0 );
$put( 40, 'topics', 'publish', 15 );
$put( 18, 'courses', 'publish', 0 );
$put( 517, 'topics', 'publish', 18 );
$put( 502, 'lesson', 'publish', 40 );
$put( 503, 'tutor_zoom_meeting', 'publish', 590 );
$put( 505, 'lesson', 'publish', 15 );
$put( 504, 'tutor_zoom_meeting', 'publish', 505 );
$put( 507, 'topics', 'publish', 591 );
$put( 506, 'tutor_zoom_meeting', 'publish', 507 );
$put( 510, 'lesson', 'publish', 0 );
$put( 509, 'topics', 'publish', 510 );
$put( 508, 'tutor_zoom_meeting', 'publish', 509 );
$controller->posts[512] = $make_post(
	array(
		'ID'           => 912,
		'post_type'    => 'topics',
		'post_status'  => 'publish',
		'post_parent'  => 15,
		'post_author'  => 17,
		'post_title'   => 'Shifted topic',
		'post_content' => 'Outline',
		'menu_order'   => 2,
	)
);
$put( 511, 'tutor_zoom_meeting', 'publish', 512 );
$put( 514, 'topics', 'publish', 515 );
$put( 513, 'tutor_zoom_meeting', 'publish', 514 );
$controller->posts[515] = $make_post(
	array(
		'ID'           => 915,
		'post_type'    => 'courses',
		'post_status'  => 'publish',
		'post_parent'  => 0,
		'post_author'  => 17,
		'post_title'   => 'Shifted course',
		'post_content' => 'Body',
		'menu_order'   => 2,
	)
);
$put( 516, 'tutor_zoom_meeting', 'publish', 517 );
foreach ( array( 518 => 'draft', 519 => 'private' ) as $item_id => $item_status ) {
	$put( $item_id, 'tutor_zoom_meeting', $item_status, 40 );
}
foreach ( array( 520, 521, 522, 523, 524, 525, 526, 527, 528, 529, 531 ) as $item_id ) {
	$put( $item_id, 'tutor_zoom_meeting', 'publish', 40 );
}
$put( 530, 'tutor-google-meet', 'publish', 40 );
$item_operation = array(
	'operation_id'    => 'op-1',
	'kind'            => 'create',
	'started_at'      => '2026-09-21 15:04:00',
	'base_version'    => 1,
	'provider'        => 'zoom',
	'owner_id'        => 42,
	'provider_id'     => null,
	'dispatch_state'  => 'not_dispatched',
	'course_id'       => 15,
	'topic_id'        => 40,
	'post_author'     => 17,
	'menu_order'      => 2,
	'title'           => 'Operation title',
	'content'         => 'Operation notes',
	'start_local'     => '2026-09-21 15:04:00',
	'end_local'       => '2026-09-21 16:04:00',
	'timezone'        => 'UTC',
	'duration'        => array( 'value' => 60, 'unit' => 'minutes' ),
	'provider_target' => array(
		'auto_recording'    => 'none',
		'enforce_login'     => false,
		'host_id'           => 'host-1',
		'host_video'        => false,
		'join_before_host'  => true,
		'mute_upon_entry'   => false,
		'participant_video' => false,
		'waiting_room'      => true,
	),
	'delete_hook'     => null,
);
$safe_meta = array(
	'_tutor_zm_start_date' => '2026-10-06',
	'_tutor_zm_data'       => array(
		'id'         => 'zm-9',
		'password'   => 'hidden-pw',
		'api_secret' => 'fake-credential-value',
		'host_id'    => 'host-9',
	),
);
foreach ( array( 524, 525, 527, 529 ) as $meta_id ) {
	$controller->meta[ $meta_id ] = $safe_meta;
}
$controller->sync_rows[520] = $sync_row( 520, 'not-json' );
$controller->sync_rows[521] = array();
$controller->sync_rows[522] = $sync_row( 522, $sync_bytes( 'pending', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[523] = $sync_row( 523, $sync_bytes( 'error', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[524] = $sync_row( 524, $sync_bytes( 'pending', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[525] = $sync_row( 525, $sync_bytes( 'error', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[526] = $sync_row( 526, $sync_bytes( 'unlinked', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[527] = $sync_row( 527, $sync_bytes( 'unlinked', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[528] = $sync_row( 528, $sync_bytes( 'synced', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[529] = $sync_row( 529, $sync_bytes( 'synced', array( 'operation' => $item_operation ) ) );
$controller->sync_rows[530] = array();
$controller->sync_rows[531] = $sync_row( 531, $sync_bytes( 'pending', array( 'operation' => $item_operation ) ) );
$missing_item         = $read_one( array( 'id' => 501 ) );
$wrong_item_type      = $read_one( array( 'id' => 502 ) );
$missing_topic        = $read_one( array( 'id' => 503 ) );
$wrong_topic          = $read_one( array( 'id' => 504 ) );
$missing_course_item  = $read_one( array( 'id' => 506 ) );
$wrong_course_item    = $read_one( array( 'id' => 508 ) );
$broken_item_parent   = $read_one( array( 'id' => 511 ) );
$broken_course_parent = $read_one( array( 'id' => 513 ) );
$denied_item          = $read_one( array( 'id' => 516 ) );
$draft_item           = $read_one( array( 'id' => 518 ) );
$private_item         = $read_one( array( 'id' => 519 ) );
$rejected_item        = $read_one( array( 'id' => 520 ) );
$legacy_item          = $read_one( array( 'id' => 521 ) );
$pending_item         = $read_one( array( 'id' => '522' ) );
$error_item           = $read_one( array( 'id' => 523 ) );
$pending_meta_item    = $read_one( array( 'id' => 524 ) );
$error_meta_item      = $read_one( array( 'id' => 525 ) );
$unlinked_item        = $read_one( array( 'id' => 526 ) );
$unlinked_meta_item   = $read_one( array( 'id' => 527 ) );
$synced_item          = $read_one( array( 'id' => 528 ) );
$synced_meta_item     = $read_one( array( 'id' => 529 ) );
$google_legacy_item   = $read_one( array( 'id' => 530 ) );
$unprojected_item     = $read_one( array( 'id' => 531 ), array( true, false ) );
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] = $zoom;
$zoom->hosts = array(
	'outcome' => 'hosts',
	'hosts'   => array(
		array(
			'id'         => 'host-1',
			'name'       => 'Ada Lovelace',
			'email'      => 'ada@example.com',
			'first_name' => 'kept-extra',
		),
	),
);
$plant_course = static function ( $id, $author ) use ( $controller, $make_post ) {
	$controller->posts[ (int) $id ] = $make_post(
		array(
			'ID'           => $id,
			'post_type'    => 'courses',
			'post_status'  => 'publish',
			'post_parent'  => 0,
			'post_author'  => $author,
			'post_title'   => 'Course ' . $id,
			'post_content' => '',
			'menu_order'   => 1,
		)
	);
};
$plant_course( 63, 0 );
$plant_course( 64, 0 );
$plant_course( 65, 'not-an-id' );
$pack_zoom = static function ( $request ) use ( $controller, $zoom, $pack_result, $named_calls ) {
	$result = $controller->get_zoom_users( $request );
	return array(
		'packed'    => $pack_result( $result ),
		'calls'     => $controller->calls,
		'ledger'    => $controller->adapter_resolutions,
		'services'  => $named_calls( $controller->calls, 'service' ),
		'metas'     => $named_calls( $controller->calls, 'meta' ),
		'reads'     => $named_calls( $controller->calls, 'read' ),
		'hosts'     => $zoom->host_calls,
		'preflight' => $zoom->preflight_calls,
	);
};
$begin_zoom = static function ( $params, $gate, $actor = 44, $can_results = null ) use ( $controller, $zoom ) {
	$controller->gate                = $gate;
	$controller->actor               = $actor;
	$controller->can_results         = $can_results;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$zoom->host_calls                = array();
	$zoom->preflight_calls           = array();
	$request                         = new WP_REST_Request();
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$zoom_boundary_before = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$false_zoom_missing   = array( 'entered' => false );
class TutorPress_Zoom_Users_Legacy {
	public static $meta     = 0;
	public static $switches = 0;
}
function get_current_user_id() {
	return 44;
}
function get_user_meta( $user_id, $key, $single = false ) {
	TutorPress_Zoom_Users_Legacy::$meta++;
	if ( 'tutor_zoom_api' === $key ) {
		return json_encode(
			array(
				'api_key'    => 'configured-key',
				'api_secret' => 'configured-secret',
			)
		);
	}
	return '';
}
function wp_set_current_user( $user_id ) {
	TutorPress_Zoom_Users_Legacy::$switches++;
	return $user_id;
}
function get_userdata( $user_id ) {
	$user               = new stdClass();
	$user->display_name = 'Legacy Instructor';
	return $user;
}
eval( 'namespace TUTOR_ZOOM { class Zoom { public static $built = 0; public function __construct( $boot ) { self::$built++; } public function tutor_zoom_get_users() { return array( array( "id" => "legacy-host", "first_name" => "Ada", "last_name" => "Lovelace", "email" => "ada@example.com" ) ); } } }' );
$false_zoom_legacy = array( 'entered' => false );
$legacy_built      = \TUTOR_ZOOM\Zoom::$built;
$legacy_meta       = TutorPress_Zoom_Users_Legacy::$meta;
$legacy_switches   = TutorPress_Zoom_Users_Legacy::$switches;
$read_zoom         = static function ( $params, $actor = 44, $can_results = null ) use ( $begin_zoom, $pack_zoom ) {
	return $pack_zoom( $begin_zoom( $params, true, $actor, $can_results ) );
};
$invalid_zoom_values = array(
	array(),
	array( 'course_id' => 0 ),
	array( 'course_id' => -4 ),
	array( 'course_id' => '0' ),
	array( 'course_id' => '00' ),
	array( 'course_id' => '-3' ),
	array( 'course_id' => '15x' ),
	array( 'course_id' => '15 ' ),
	array( 'course_id' => 15.5 ),
	array( 'course_id' => true ),
	array( 'course_id' => array( 15 ) ),
	array( 'course_id' => null ),
);
$invalid_zoom = array();
foreach ( $invalid_zoom_values as $params ) {
	$invalid_zoom[] = $read_zoom( $params );
}
$missing_zoom        = $read_zoom( array( 'course_id' => 99 ) );
$wrong_zoom          = $read_zoom( array( 'course_id' => 16 ) );
$denied_zoom         = $read_zoom( array( 'course_id' => 8 ) );
$author_zoom         = $read_zoom( array( 'course_id' => 15 ), 55 );
$author_string_zoom  = $read_zoom( array( 'course_id' => '15' ), 55 );
$fallback_missing    = $read_zoom( array( 'course_id' => 63 ), 55, array( true ) );
$fallback_invalid    = $read_zoom( array( 'course_id' => 65 ), 55, array( true ) );
$unresolved_zoom     = $read_zoom( array( 'course_id' => 64 ), 0, array( true ) );
$zoom_users_body     = $method_body( 'read_zoom_users' );
$zoom_handler_body   = $method_body( 'get_zoom_users' );
$zoom_marks          = array( 'authorize_course', 'TutorPress_Live_Lesson_Owner_Resolver::zoom', "provider_adapter('zoom')", 'list_hosts($owner_id)' );
$zoom_order          = true;
$zoom_cursor         = -1;
foreach ( $zoom_marks as $zoom_mark ) {
	$zoom_at     = strpos( $zoom_users_body, $zoom_mark );
	$zoom_order  = $zoom_order && false !== $zoom_at && $zoom_at > $zoom_cursor;
	$zoom_cursor = false === $zoom_at ? $zoom_cursor : $zoom_at;
}
$zoom_guard_proof = false === strpos( $zoom_handler_body, 'replacement_enabled' )
	&& false !== strpos( $zoom_handler_body, '$this->read_zoom_users(' )
	&& false === strpos( $zoom_handler_body, 'TUTOR_ZOOM\\Zoom' )
	&& false === strpos( $zoom_handler_body, 'request_bindings' )
	&& false === strpos( $zoom_handler_body, 'provider_adapter' )
	&& false === strpos( $zoom_handler_body, 'load_service' )
	&& false === strpos( $zoom_handler_body, 'list_hosts' )
	&& false === strpos( $zoom_handler_body, 'Owner_Resolver' )
	&& false === strpos( $zoom_handler_body, 'preflight' );
$zoom_read_proof = $zoom_order
	&& false === strpos( $zoom_users_body, 'edit_posts' )
	&& false !== strpos( $zoom_users_body, "'edit_post' === \$capability" )
	&& false === strpos( $zoom_users_body, 'metadata(' )
	&& false === strpos( $zoom_users_body, 'load_envelope' )
	&& false === strpos( $zoom_users_body, 'preflight(' )
	&& false === strpos( $zoom_users_body, 'get_user_meta' )
	&& false === strpos( $zoom_users_body, 'wp_set_current_user' )
	&& false === strpos( $zoom_users_body, 'TUTOR_ZOOM' )
	&& false === strpos( $zoom_users_body, 'google_meet' )
	&& false !== strpos( $zoom_users_body, 'format_response' )
	&& false !== strpos( $zoom_users_body, 'null === $owner_id' )
	&& strpos( $zoom_users_body, 'null === $owner_id' ) < strpos( $zoom_users_body, "provider_adapter('zoom')" );
$zoom_boundary_after = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$google_host_calls   = $google->host_calls;
$zoom_users          = array(
	'false_missing'    => $false_zoom_missing,
	'false_legacy'     => $false_zoom_legacy,
	'invalid'          => $invalid_zoom,
	'missing'          => $missing_zoom,
	'wrong_type'       => $wrong_zoom,
	'denied'           => $denied_zoom,
	'author'           => $author_zoom,
	'author_string'    => $author_string_zoom,
	'fallback_missing' => $fallback_missing,
	'fallback_invalid' => $fallback_invalid,
	'unresolved'       => $unresolved_zoom,
	'guard'            => $zoom_guard_proof,
	'order'            => $zoom_read_proof,
	'built'            => \TUTOR_ZOOM\Zoom::$built,
	'legacy_built'     => $legacy_built,
	'meta'             => TutorPress_Zoom_Users_Legacy::$meta,
	'legacy_meta'      => $legacy_meta,
	'switches'         => TutorPress_Zoom_Users_Legacy::$switches,
	'legacy_switches'  => $legacy_switches,
	'boundary'         => $zoom_boundary_after === $zoom_boundary_before && array() === $zoom_boundary_after,
	'google_hosts'     => $google_host_calls,
);
$outcome_body = $method_body( 'rest_outcome' ) . $method_body( 'outcome_failure' ) . $method_body( 'outcome_comparison' ) . $method_body( 'duplicate_projection' );
$outcome_forbidden = array( 'provider_adapter', 'load_service', 'metadata_map', 'hydrate_item', 'hydrate_legacy', 'Orchestrator', 'add_post_meta', 'update_post_meta', 'get_post_meta', 'load_sync_meta', 'load_meta_update', 'load_post_meta', 'GoogleEvent', 'TUTOR_ZOOM', 'list_hosts', 'preflight(', '->create(', '->delete(', '->fetch(', '->update(', "['metadata']", "['write']", "['update']" );
$outcome_proof = false === strpos( $method_body( 'rest_outcome' ), 'request_bindings' ) && false !== strpos( $method_body( 'duplicate_projection' ), 'request_bindings' ) && false !== strpos( $method_body( 'duplicate_projection' ), 'project_item' ) && false !== strpos( $method_body( 'duplicate_projection' ), "'edit_post' === " ) && false === strpos( $outcome_body, 'edit_posts' );
foreach ( $outcome_forbidden as $outcome_needle ) {
	$outcome_proof = $outcome_proof && false === strpos( $outcome_body, $outcome_needle );
}
$run_outcome = static function ( $action, $result, $gate = true ) use ( $controller, $pack_result, $named_calls ) {
	$controller->gate                = $gate;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$boundary                        = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$returned                        = $controller->rest_outcome( $action, $result );
	return array(
		'packed'   => $pack_result( $returned ),
		'same'     => $returned === $result,
		'calls'    => $controller->calls,
		'posts'    => $named_calls( $controller->calls, 'post' ),
		'services' => $named_calls( $controller->calls, 'service' ),
		'metas'    => $named_calls( $controller->calls, 'meta' ),
		'writes'   => $named_calls( $controller->calls, 'write' ),
		'updates'  => $named_calls( $controller->calls, 'update' ),
		'ledger'   => $controller->adapter_resolutions,
		'boundary' => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
	);
};
$accepted_envelope = json_decode( $sync_bytes( 'synced' ), true );
$rejected_envelope = $accepted_envelope;
$rejected_envelope['api_secret'] = 'fake-credential-value';
$confirmed_delete = array(
	'outcome'     => 'confirmed',
	'provider_id' => 'meet-1',
	'item'        => null,
	'diagnostics' => array(),
	'provenance'  => 'delete',
);
$inspect_comparison = array(
	'match'  => true,
	'fields' => array(
		'provider_id'      => 'meet-1',
		'start_at'         => '2026-10-07T16:00:00Z',
		'timezone'         => 'UTC',
		'duration_minutes' => 60,
	),
);
$unsafe_comparison = $inspect_comparison;
$unsafe_comparison['fields']['access_token'] = 'fake-credential-value';
$bare_error = new WP_Error( 'live_lesson_creation_failed', 'Failed to create live lesson.', array( 'status' => 500 ) );
$put( 540, 'tutor-google-meet', 'publish', 40 );
$put( 541, 'tutor-google-meet', 'publish', 517 );
$put( 542, 'tutor-google-meet', 'draft', 40 );
$put( 543, 'tutor-google-meet', 'publish', 40 );
$put( 545, 'tutor-google-meet', 'publish', 507 );
$controller->sync_rows[540] = array();
$controller->sync_rows[542] = array();
$controller->sync_rows[543] = $sync_row( 543, 'not-json' );
$duplicate_ids = array( 0, -2, '7', 7.5, false );
$duplicate_invalid = array();
foreach ( $duplicate_ids as $duplicate_id ) {
	$duplicate_invalid[] = $run_outcome( 'duplicate', $duplicate_id );
}
$outcomes = array(
	'proof'            => $outcome_proof,
	'error'            => $run_outcome( 'create', $bare_error ),
	'null_create'      => $run_outcome( 'create', null ),
	'null_update'      => $run_outcome( 'update', null ),
	'null_delete'      => $run_outcome( 'delete', null ),
	'null_duplicate'   => $run_outcome( 'duplicate', null ),
	'null_inspect'     => $run_outcome( 'inspect', null ),
	'envelope'         => $run_outcome( 'create', $accepted_envelope ),
	'envelope_update'  => $run_outcome( 'update', $accepted_envelope ),
	'rejected'         => $run_outcome( 'create', $rejected_envelope ),
	'delete'           => $run_outcome( 'delete', $confirmed_delete ),
	'inspect'          => $run_outcome( 'inspect', $inspect_comparison ),
	'unsafe_compare'   => $run_outcome( 'inspect', $unsafe_comparison ),
	'duplicate_closed' => $run_outcome( 'duplicate', 540, false ),
	'duplicate'        => $run_outcome( 'duplicate', 540 ),
	'invalid'          => $duplicate_invalid,
	'missing'          => $run_outcome( 'duplicate', 900 ),
	'malformed'        => $run_outcome( 'duplicate', 545 ),
	'unauthorized'     => $run_outcome( 'duplicate', 541 ),
	'rejected_post'    => $run_outcome( 'duplicate', 543 ),
	'unprojectable'    => $run_outcome( 'duplicate', 542 ),
);
$plant_row = static function ( $id, $type, $parent, $author = 17 ) use ( $make_post ) {
	return $make_post(
		array(
			'ID'           => $id,
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_parent'  => $parent,
			'post_author'  => $author,
			'post_title'   => 'Row ' . $id,
			'post_content' => 'Body',
			'menu_order'   => 1,
		)
	);
};
$controller->posts[15] = $plant_row( 15, 'courses', 0 );
$controller->posts[71] = $plant_row( 71, 'topics', 15 );
$controller->posts[81] = $plant_row( 81, 'topics', 8 );
$controller->posts[82] = $plant_row( 82, 'topics', 16 );
$controller->posts[83] = $plant_row( 83, 'topics', 8 );
$controller->posts[84] = $plant_row( 84, 'lesson', 15 );
$controller->children  = array( $plant_row( 91, 'lesson', 71 ) );
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'] = $google;
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom']        = $zoom;
$controller->actor = 44;
$create_envelope = json_decode( $sync_bytes( 'synced' ), true );
$stage_bytes     = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $create_envelope );
$dispatch_envelope = $create_envelope;
$dispatch_envelope['version'] = 3;
$dispatch_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $dispatch_envelope );
$staged_ok = array(
	'post_id'         => 90,
	'lock'            => 'lock-token',
	'owner_id'        => 42,
	'dispatch_state'  => 'not_dispatched',
	'envelope'        => $stage_bytes,
);
$dispatched_ok = array(
	'post_id'  => 90,
	'lock'     => 'lock-token',
	'owner_id' => 42,
	'envelope' => $dispatch_bytes,
);
$create_body = array(
	'course_id'       => 15,
	'topic_id'        => 71,
	'title'           => 'Weekly lab',
	'description'     => 'Bring notes',
	'type'            => 'google_meet',
	'start_date_time' => '2026-10-06 15:00:00',
	'end_date_time'   => '2026-10-06 16:00:00',
	'timezone'        => 'UTC',
	'duration'        => array( 'value' => 2, 'unit' => 'hours' ),
	'settings'        => array(
		'waiting_room' => true,
		'credentials'  => array( 'api_secret' => 'hidden-sentinel' ),
		'provider_id'  => 'evt-forged',
	),
	'provider_config'  => array(
		'host'     => 'zoom-host-9',
		'password' => 'hidden-sentinel',
	),
	'acting_user_id'   => 999,
	'course_author_id' => 999,
	'provider'         => 'zoom',
);
$run_create = static function ( $params, $method = 'POST', $gate = true, $stage = null, $dispatch = null ) use ( $controller, $pack_result, $named_calls ) {
	$controller->gate                = $gate;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->stage_script        = $stage;
	$controller->dispatch_script     = $dispatch;
	$controller->last_callbacks      = array();
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$request                         = new WP_REST_Request();
	$request->method                 = $method;
	$request->route                  = 'PATCH' === $method ? '/live-lessons/(?P<id>[\d]+)' : '/live-lessons';
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$result   = $controller->mutate_schedule( $request );
	return array(
		'packed'     => $pack_result( $result ),
		'same_stage' => $result === $stage,
		'same_dispatch' => $result === $dispatch,
		'calls'      => $controller->calls,
		'ledger'     => $controller->adapter_resolutions,
		'services'   => $named_calls( $controller->calls, 'service' ),
		'metas'      => $named_calls( $controller->calls, 'meta' ),
		'writes'     => $named_calls( $controller->calls, 'write' ),
		'updates'    => $named_calls( $controller->calls, 'update' ),
		'posts'      => $named_calls( $controller->calls, 'post' ),
		'queries'    => $named_calls( $controller->calls, 'posts' ),
		'caps'       => $named_calls( $controller->calls, 'can' ),
		'reads'      => $named_calls( $controller->calls, 'read' ),
		'stages'     => $controller->stage_calls,
		'dispatches' => $controller->dispatch_calls,
		'bindings'   => $controller->binding_runs,
		'adapters'   => $controller->adapter_runs,
		'boundary'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
		'array'      => is_array( $result ) ? $result : null,
	);
};
$false_gate = array( 'entered' => false );
$forged_body = $create_body;
$forged_body['provider_id'] = 'evt-forged';
$conflict_body = $create_body;
$conflict_body['startDateTime'] = '2026-10-06 16:00:00';
$bad_end_body = $create_body;
unset( $bad_end_body['end_date_time'] );
$bad_end_body['endDateTime'] = '2026-10-06T16:00:00';
$zoom_body = $create_body;
$zoom_body['type'] = 'zoom';
$string_body = $create_body;
$string_body['course_id'] = '15';
$string_body['topic_id'] = '71';
$google_row = $run_create( $create_body, 'POST', true, $staged_ok, $dispatched_ok );
$controller->calls = array();
$callback_names = array();
if ( 4 === count( $controller->last_callbacks ) ) {
	$controller->last_callbacks[0]( 8, '_tutorpress_live_lesson_sync' );
	$controller->last_callbacks[1]( 8, '_tutorpress_live_lesson_sync', 31, 'next-meta', 'prior-meta' );
	$controller->last_callbacks[2]();
	$controller->last_callbacks[3]( 8, '_tutor_zm_for_course', 60 );
	foreach ( $controller->calls as $callback_call ) {
		$callback_names[] = is_array( $callback_call ) ? $callback_call[0] : $callback_call;
	}
}
$stage_error = new WP_Error( 'stage_rejected', 'Stage rejected.', array( 'status' => 422 ) );
$dispatch_error = new WP_Error( 'dispatch_rejected', 'Dispatch rejected.', array( 'status' => 409 ) );
$delegate_body = $method_body( 'delegate_create' );
$mutate_body   = $method_body( 'mutate_schedule' );
$stage_body    = $method_body( 'stage_live_create' );
$dispatch_body = $method_body( 'dispatch_live_create' );
$create_path   = $delegate_body . $stage_body . $dispatch_body . $method_body( 'create_shaped' ) . $method_body( 'accepted_bytes' ) . $method_body( 'staged_create' ) . $method_body( 'scalar_id' );
$create_forbidden = array( 'wp_insert_post', 'wp_delete_post', 'wp_update_post', 'update_post_meta', 'add_post_meta', 'delete_post_meta', 'get_post_meta', 'metadata_map', 'preflight(', '->create(', '->update(', '->delete(', '->fetch(', 'list_hosts', 'GoogleEvent', 'TUTOR_ZOOM', 'hydrate_', 'load_service', 'edit_posts' );
$create_clean = false !== strpos( $create_path, "'edit_post' === \$capability" ) && false !== strpos( $delegate_body, "\$bindings['user_id']" );
foreach ( $create_forbidden as $create_needle ) {
	$create_clean = $create_clean && false === strpos( $create_path, $create_needle );
}
$delegate_marks = array( 'authorize_create', 'map_mutation', 'provider_adapter(', 'stage_live_create(', 'dispatch_live_create(', "rest_outcome('create'" );
$delegate_order = true;
$delegate_cursor = -1;
foreach ( $delegate_marks as $delegate_mark ) {
	$delegate_at     = strpos( $delegate_body, $delegate_mark );
	$delegate_order  = $delegate_order && false !== $delegate_at && $delegate_at > $delegate_cursor;
	$delegate_cursor = false === $delegate_at ? $delegate_cursor : $delegate_at;
}
$creates = array(
	'false' => $false_gate,
	'incomplete' => $run_create( array( 'startDateTime' => '2026-10-06 15:00:00' ) ),
	'patch'      => $run_create( $create_body, 'PATCH' ),
	'forged'     => $run_create( $forged_body ),
	'conflict'   => $run_create( $conflict_body ),
	'bad_end'    => $run_create( $bad_end_body ),
	'missing'    => $run_create( array_merge( $create_body, array( 'course_id' => 99 ) ) ),
	'wrong_course' => $run_create( array_merge( $create_body, array( 'course_id' => 16, 'topic_id' => 82 ) ) ),
	'wrong_topic' => $run_create( array_merge( $create_body, array( 'topic_id' => 84 ) ) ),
	'broken'     => $run_create( array_merge( $create_body, array( 'topic_id' => 83 ) ) ),
	'denied'     => $run_create( array_merge( $create_body, array( 'course_id' => 8, 'topic_id' => 81 ) ) ),
	'zero'       => $run_create( array_merge( $create_body, array( 'course_id' => 0 ) ) ),
	'google'     => $google_row,
	'string_ids' => $run_create( $string_body, 'POST', true, $staged_ok, $dispatched_ok ),
	'zoom'       => $run_create( $zoom_body, 'POST', true, $staged_ok, $dispatched_ok ),
	'null_stage' => $run_create( $create_body, 'POST', true, null, $dispatched_ok ),
	'error_stage' => $run_create( $create_body, 'POST', true, $stage_error, $dispatched_ok ),
	'malformed'  => $run_create( $create_body, 'POST', true, array( 'note' => 'plain' ), $dispatched_ok ),
	'bad_bytes'  => $run_create( $create_body, 'POST', true, array( 'post_id' => 90, 'lock' => 'lock-token', 'owner_id' => 42, 'envelope' => 'not-json' ), $dispatched_ok ),
	'dispatch_null' => $run_create( $create_body, 'POST', true, $staged_ok, null ),
	'dispatch_error' => $run_create( $create_body, 'POST', true, $staged_ok, $dispatch_error ),
	'dispatch_missing' => $run_create( $create_body, 'POST', true, $staged_ok, array( 'post_id' => 90 ) ),
	'dispatch_bad' => $run_create( $create_body, 'POST', true, $staged_ok, array( 'envelope' => 'not-json', 'post_id' => 90 ) ),
	'callbacks'  => $callback_names,
	'lifecycle'  => array( $google->lifecycle, $zoom->lifecycle ),
	'proof'      => $create_clean && $delegate_order && 1 === substr_count( $stage_body, 'stage_pending(' ) && 1 === substr_count( $dispatch_body, 'dispatch_create(' ) && false === strpos( $stage_body, 'dispatch_create(' ) && false === strpos( $dispatch_body, 'stage_pending(' ),
	'mutate'     => false === strpos( $mutate_body, 'request_bindings' ) && false === strpos( $mutate_body, 'provider_adapter' ) && false === strpos( $mutate_body, 'rest_outcome' ) && false === strpos( $mutate_body, 'stage_pending' ) && false === strpos( $mutate_body, 'dispatch_create' ) && false !== strpos( $mutate_body, 'delegate_create(' ) && false !== strpos( $mutate_body, 'schedule_result(' ),
	'legacy'     => false === method_exists( 'TutorPress_REST_Live_Lessons_Controller', 'create_item' ),
);
$put( 610, 'tutor-google-meet', 'publish', 40 );
$put( 611, 'tutor-google-meet', 'publish', 40 );
$controller->sync_rows[610] = $sync_row( 610, $sync_bytes( 'unlinked', array( 'provider' => 'google_meet' ) ) );
$controller->sync_rows[611] = $sync_row( 611, $sync_bytes( 'synced', array( 'provider' => 'google_meet' ) ) );
$patch = array(
	'title'           => 'Weekly lab',
	'description'     => 'Bring notes',
	'type'            => 'zoom',
	'start_date_time' => '2026-10-06 15:00:00',
	'end_date_time'   => '2026-10-06 16:00:00',
	'timezone'        => 'UTC',
	'duration'        => array( 'value' => 2, 'unit' => 'hours' ),
	'settings'        => array(
		'waiting_room' => true,
		'credentials'  => array( 'api_secret' => 'hidden-sentinel' ),
		'provider_id'  => 'evt-forged',
	),
	'provider_config' => array(
		'host'     => 'zoom-host-9',
		'password' => 'hidden-sentinel',
	),
	'provider'        => 'google_meet',
	'acting_user_id'  => 999,
);
$with_id = static function ( $id, $type ) use ( $patch ) {
	$body         = $patch;
	$body['id']   = $id;
	$body['type'] = $type;
	return $body;
};
$run_update = static function ( $params, $gate = true, $stage = null, $dispatch = null ) use ( $controller, $pack_result, $named_calls ) {
	$controller->gate                = $gate;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->stage_script        = $stage;
	$controller->dispatch_script     = $dispatch;
	$controller->last_callbacks      = array();
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$request                         = new WP_REST_Request();
	$request->method                 = 'PATCH';
	$request->route                  = '/live-lessons/(?P<id>[\d]+)';
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$result   = $controller->mutate_schedule( $request );
	return array(
		'packed'          => $pack_result( $result ),
		'same_stage'      => $result === $stage,
		'same_dispatch'   => $result === $dispatch,
		'array'           => is_array( $result ) ? $result : null,
		'ledger'          => $controller->adapter_resolutions,
		'services'        => $named_calls( $controller->calls, 'service' ),
		'metas'           => $named_calls( $controller->calls, 'meta' ),
		'writes'          => $named_calls( $controller->calls, 'write' ),
		'updates'         => $named_calls( $controller->calls, 'update' ),
		'posts'           => $named_calls( $controller->calls, 'post' ),
		'queries'         => $named_calls( $controller->calls, 'posts' ),
		'caps'            => $named_calls( $controller->calls, 'can' ),
		'reads'           => $named_calls( $controller->calls, 'read' ),
		'stages'          => $controller->stage_calls,
		'dispatches'      => $controller->dispatch_calls,
		'unlinked'        => $controller->unlinked_stages,
		'synced'          => $controller->synced_stages,
		'synced_dispatch' => $controller->synced_dispatches,
		'bindings'        => $controller->binding_runs,
		'adapters'        => $controller->adapter_runs,
		'boundary'        => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
	);
};
$identity_rows = array();
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity_key ) {
	$forged_patch                 = $with_id( 526, 'google_meet' );
	$forged_patch[ $identity_key ] = 'supplied';
	$identity_rows[ $identity_key ] = $run_update( $forged_patch );
}
$conflict_patch                   = $with_id( 526, 'google_meet' );
$conflict_patch['startDateTime']  = '2026-10-06 16:00:00';
$bad_end_patch                    = $with_id( 526, 'google_meet' );
unset( $bad_end_patch['end_date_time'] );
$bad_end_patch['endDateTime'] = '2026-10-06T16:00:00';
$zoom_unlinked = $run_update( $with_id( '526', 'google_meet' ), true, $staged_ok, $dispatched_ok );
$controller->calls = array();
$update_callbacks  = array();
if ( 4 === count( $controller->last_callbacks ) ) {
	$controller->last_callbacks[0]( 8, '_tutorpress_live_lesson_sync' );
	$controller->last_callbacks[1]( 8, '_tutorpress_live_lesson_sync', 31, 'next-meta', 'prior-meta' );
	$controller->last_callbacks[2]();
	$controller->last_callbacks[3]( 8, '_tutor_zm_for_course', 60 );
	foreach ( $controller->calls as $callback_call ) {
		$update_callbacks[] = is_array( $callback_call ) ? $callback_call[0] : $callback_call;
	}
}
$update_delegate = $method_body( 'delegate_update' );
$update_shape    = $method_body( 'update_shaped' );
$update_unlinked = $method_body( 'stage_live_unlinked' );
$update_synced   = $method_body( 'stage_live_synced' );
$update_sync_go  = $method_body( 'dispatch_live_synced' );
$update_open_go  = $method_body( 'dispatch_live_create' );
$update_mutate   = $method_body( 'mutate_schedule' );
$update_path     = $update_delegate . $update_shape . $update_unlinked . $update_synced . $update_sync_go . $update_open_go;
$update_forbidden = array( 'wp_insert_post', 'wp_delete_post', 'wp_update_post', 'update_post_meta', 'add_post_meta', 'delete_post_meta', 'get_post_meta', 'metadata_map', 'preflight(', '->create(', '->update(', '->delete(', '->fetch(', 'list_hosts', 'GoogleEvent', 'TUTOR_ZOOM', 'hydrate_', 'load_service', 'edit_posts', "get_param('type')", 'get_param("type")', 'provider_id', 'event_id', 'meeting_id' );
$update_clean = false !== strpos( $update_delegate, "'edit_post' === \$capability" ) && false !== strpos( $update_delegate, "\$input['provider'] = \$provider" ) && false !== strpos( $update_delegate, 'tutor_zoom_meeting' ) && false !== strpos( $update_delegate, 'google_meet' );
foreach ( $update_forbidden as $update_needle ) {
	$update_clean = $update_clean && false === strpos( $update_path, $update_needle );
}
$update_marks = array( "['lookup']", 'invalid_post_type', 'invalid_topic', "'edit_post' === \$capability", 'authorize(', "'publish'", "['envelope']", 'map_mutation(', "['provider'] = \$provider", 'provider_adapter(', 'stage_live_unlinked(', 'stage_live_synced(', 'dispatch_live_create(', 'dispatch_live_synced(', "rest_outcome('update'" );
$update_order = true;
$update_cursor = -1;
foreach ( $update_marks as $update_mark ) {
	$update_at     = strpos( $update_delegate, $update_mark );
	$update_order  = $update_order && false !== $update_at && $update_at > $update_cursor;
	$update_cursor = false === $update_at ? $update_cursor : $update_at;
}
$update_pairs = 1 === substr_count( $update_unlinked, 'stage_unlinked(' ) && false === strpos( $update_unlinked, 'stage_update(' ) && false === strpos( $update_unlinked, 'dispatch_' ) && 1 === substr_count( $update_synced, 'stage_update(' ) && false === strpos( $update_synced, 'stage_unlinked(' ) && false === strpos( $update_synced, 'dispatch_' ) && 1 === substr_count( $update_open_go, 'dispatch_create(' ) && false === strpos( $update_open_go, 'dispatch_update(' ) && 1 === substr_count( $update_sync_go, 'dispatch_update(' ) && false === strpos( $update_sync_go, 'dispatch_create(' ) && 1 === substr_count( $update_delegate, 'stage_live_unlinked(' ) && 1 === substr_count( $update_delegate, 'stage_live_synced(' ) && 1 === substr_count( $update_delegate, 'dispatch_live_create(' ) && 1 === substr_count( $update_delegate, 'dispatch_live_synced(' );
$legacy_update = false === method_exists( 'TutorPress_REST_Live_Lessons_Controller', 'update_item' );
$mutate_update = false === strpos( $update_mutate, 'request_bindings' ) && false === strpos( $update_mutate, 'provider_adapter' ) && false === strpos( $update_mutate, 'rest_outcome' ) && false === strpos( $update_mutate, 'stage_unlinked' ) && false === strpos( $update_mutate, 'stage_update' ) && false === strpos( $update_mutate, 'dispatch_create' ) && false === strpos( $update_mutate, 'dispatch_update' ) && false !== strpos( $update_mutate, 'delegate_update(' ) && false !== strpos( $update_mutate, 'schedule_result(' );
$updates = array(
	'false' => array( 'entered' => false ),
	'false_mutate' => $run_update( $with_id( 526, 'google_meet' ), false ),
	'plain'        => $run_update( array( 'start_date_time' => '2026-10-06 15:00:00', 'end_date_time' => '2026-10-06 16:00:00', 'duration' => array( 'value' => 2, 'unit' => 'hours' ) ) ),
	'zero'         => $run_update( array( 'id' => 0, 'start_date_time' => '2026-10-06 15:00:00' ) ),
	'identity'     => $identity_rows,
	'conflict'     => $run_update( $conflict_patch ),
	'bad_end'      => $run_update( $bad_end_patch ),
	'missing'      => $run_update( $with_id( 501, 'zoom' ) ),
	'wrong_type'   => $run_update( $with_id( 502, 'google_meet' ) ),
	'missing_topic' => $run_update( $with_id( 503, 'zoom' ) ),
	'wrong_topic'  => $run_update( $with_id( 504, 'zoom' ) ),
	'missing_course' => $run_update( $with_id( 506, 'zoom' ) ),
	'wrong_course' => $run_update( $with_id( 508, 'zoom' ) ),
	'broken_item'  => $run_update( $with_id( 511, 'zoom' ) ),
	'broken_course' => $run_update( $with_id( 513, 'zoom' ) ),
	'denied'       => $run_update( $with_id( 516, 'zoom' ) ),
	'draft'        => $run_update( $with_id( 518, 'zoom' ) ),
	'private'      => $run_update( $with_id( 519, 'zoom' ) ),
	'rejected'     => $run_update( $with_id( 520, 'zoom' ) ),
	'absent'       => $run_update( $with_id( 521, 'zoom' ) ),
	'pending'      => $run_update( $with_id( 522, 'google_meet' ) ),
	'error_state'  => $run_update( $with_id( 523, 'google_meet' ) ),
	'zoom_unlinked' => $zoom_unlinked,
	'google_unlinked' => $run_update( $with_id( 610, 'zoom' ), true, $staged_ok, $dispatched_ok ),
	'zoom_synced'  => $run_update( $with_id( 528, 'google_meet' ), true, $staged_ok, $dispatched_ok ),
	'google_synced' => $run_update( $with_id( '611', 'zoom' ), true, $staged_ok, $dispatched_ok ),
	'null_stage'   => $run_update( $with_id( 526, 'google_meet' ), true, null, $dispatched_ok ),
	'error_stage'  => $run_update( $with_id( 526, 'google_meet' ), true, $stage_error, $dispatched_ok ),
	'malformed'    => $run_update( $with_id( 526, 'google_meet' ), true, array( 'note' => 'plain' ), $dispatched_ok ),
	'bad_bytes'    => $run_update( $with_id( 526, 'google_meet' ), true, array( 'post_id' => 90, 'lock' => 'lock-token', 'owner_id' => 42, 'envelope' => 'not-json' ), $dispatched_ok ),
	'dispatch_null' => $run_update( $with_id( 526, 'google_meet' ), true, $staged_ok, null ),
	'dispatch_error' => $run_update( $with_id( 526, 'google_meet' ), true, $staged_ok, $dispatch_error ),
	'dispatch_missing' => $run_update( $with_id( 526, 'google_meet' ), true, $staged_ok, array( 'post_id' => 90 ) ),
	'dispatch_bad' => $run_update( $with_id( 526, 'google_meet' ), true, $staged_ok, array( 'envelope' => 'not-json', 'post_id' => 90 ) ),
	'synced_null'  => $run_update( $with_id( 528, 'google_meet' ), true, null, $dispatched_ok ),
	'callbacks'    => $update_callbacks,
	'lifecycle'    => array( $google->lifecycle, $zoom->lifecycle ),
	'proof'        => $update_clean && $update_order && $update_pairs && $legacy_update && $mutate_update,
);
$delete_input = array(
	'acting_user_id'       => 44,
	'course_author_id'     => 17,
	'acting_instructor_id' => 44,
);
$delete_bytes = static function ( $state, $provider, $provider_id ) use ( $sync_bytes ) {
	$decoded = json_decode( $sync_bytes( $state, array( 'provider' => $provider, 'provider_id' => $provider_id, 'error' => 'error' === $state ? 'provider_delete_failed' : null ) ), true );
	$bytes   = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $decoded );
	return array( $bytes, json_decode( $bytes, true ) );
};
list( $confirmed_bytes, $confirmed_envelope ) = $delete_bytes( 'pending', 'google_meet', 'meet-1' );
list( $unconfirmed_bytes, $unconfirmed_envelope ) = $delete_bytes( 'error', 'zoom', 'meeting-9' );
$confirmed_stage = array( 'post_id' => 530, 'lock' => 'lock-token', 'dispatch_state' => 'confirmed', 'envelope' => $confirmed_bytes, 'provider' => 'google_meet', 'owner_id' => 42, 'course_id' => 15 );
$unconfirmed_stage = array( 'post_id' => 528, 'lock' => 'lock-token', 'dispatch_state' => 'dispatch_possible', 'envelope' => $unconfirmed_bytes, 'provider' => 'zoom', 'owner_id' => 42, 'course_id' => 15 );
$run_delete = static function ( $params, $gate = true, $stage = null ) use ( $controller, $pack_result, $named_calls ) {
	$controller->gate                = $gate;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->delete_stages       = array();
	$controller->stage_script        = $stage;
	$controller->dispatch_script     = null;
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$request                         = new WP_REST_Request();
	$request->method                 = 'DELETE';
	$request->route                  = '/live-lessons/(?P<id>[\d]+)';
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$result   = $controller->delete_item( $request );
	return array(
		'packed'          => $pack_result( $result ),
		'same'            => $result === $stage,
		'ledger'          => $controller->adapter_resolutions,
		'services'        => $named_calls( $controller->calls, 'service' ),
		'metas'           => $named_calls( $controller->calls, 'meta' ),
		'writes'          => $named_calls( $controller->calls, 'write' ),
		'updates'         => $named_calls( $controller->calls, 'update' ),
		'posts'           => $named_calls( $controller->calls, 'post' ),
		'queries'         => $named_calls( $controller->calls, 'posts' ),
		'caps'            => $named_calls( $controller->calls, 'can' ),
		'reads'           => $named_calls( $controller->calls, 'read' ),
		'stages'          => $controller->stage_calls,
		'dispatches'      => $controller->dispatch_calls,
		'unlinked'        => $controller->unlinked_stages,
		'synced'          => $controller->synced_stages,
		'synced_dispatch' => $controller->synced_dispatches,
		'deletes'         => $controller->delete_stages,
		'bindings'        => $controller->binding_runs,
		'adapters'        => $controller->adapter_runs,
		'boundary'        => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
	);
};
$delete_body = static function ( $id, $type ) {
	return array(
		'id'              => $id,
		'type'            => $type,
		'title'           => 'Forged title',
		'provider'        => 'zoom' === $type ? 'google_meet' : 'zoom',
		'provider_id'     => 'forged-provider',
		'event_id'        => 'forged-event',
		'meeting_id'      => 'forged-meeting',
		'credentials'     => array( 'api_secret' => 'hidden-sentinel' ),
		'acting_user_id'  => 999,
		'course_author_id' => 999,
	);
};
$delete_names = static function ( $method ) {
	$names = array();
	foreach ( ( new ReflectionMethod( $method[0], $method[1] ) )->getParameters() as $param ) {
		$names[] = $param->getName();
	}
	return $names;
};
$stage_names = $delete_names( array( 'TutorPress_REST_Live_Lessons_Controller', 'stage_live_delete' ) );
$auth_names  = $delete_names( array( 'TutorPress_Live_Lesson_Authorization', 'authorize' ) );
$orch_names  = $delete_names( array( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'stage_delete' ) );
$cascade_src  = file( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Cascade_Delete', 'preflight' ) )->getFileName() );
$cascade_body = '';
foreach ( array( 'preflight', 'delete_provider_children' ) as $cascade_method ) {
	$cascade_ref   = new ReflectionMethod( 'TutorPress_Live_Lesson_Cascade_Delete', $cascade_method );
	$cascade_body .= is_array( $cascade_src ) ? implode( '', array_slice( $cascade_src, $cascade_ref->getStartLine() - 1, $cascade_ref->getEndLine() - $cascade_ref->getStartLine() + 1 ) ) : '';
}
$delete_delegate = $method_body( 'delegate_delete' );
$delete_stage    = $method_body( 'stage_live_delete' );
$delete_route    = $method_body( 'delete_item' );
$delete_legacy_at = strpos( $delete_route, 'delegate_delete(' );
$delete_legacy = false === $delete_legacy_at ? $delete_route : substr( $delete_route, $delete_legacy_at + strlen( 'delegate_delete($request);' ) );
$delete_path = $delete_delegate . $delete_stage;
$delete_forbidden = array( 'wp_insert_post', 'wp_delete_post', 'wp_update_post', 'update_post_meta', 'add_post_meta', 'delete_post_meta', 'get_post_meta', 'metadata_map', 'preflight(', '->create(', '->update(', '->delete(', '->fetch(', 'list_hosts', 'GoogleEvent', 'TUTOR_ZOOM', 'hydrate_', 'load_service', 'edit_posts', 'map_mutation', 'dispatch_create', 'dispatch_update', 'stage_pending', 'stage_unlinked', 'stage_update(', 'do_action', "\$bindings['envelope']", "\$bindings['metadata']", "\$bindings['write']", "get_param('type')", 'get_param("type")', "get_param('provider_id')", "get_param('event_id')", "get_param('meeting_id')", "get_param('credentials')" );
$delete_clean = false !== strpos( $delete_delegate, "'edit_post' === \$capability" ) && false !== strpos( $delete_delegate, 'tutor_zoom_meeting' ) && false !== strpos( $delete_delegate, 'google_meet' ) && false === strpos( $delete_delegate, 'stage_delete(' ) && 1 === substr_count( $delete_stage, 'stage_delete(' );
foreach ( $delete_forbidden as $delete_needle ) {
	$delete_clean = $delete_clean && false === strpos( $delete_path, $delete_needle );
}
$delete_marks = array( "['lookup']", 'invalid_post_type', 'invalid_topic', "'edit_post' === \$capability", 'authorize(', "'publish'", 'acting_user_id', 'course_author_id', 'acting_instructor_id', 'provider_adapter(', 'stage_live_delete(', "rest_outcome('delete'" );
$delete_order = true;
$delete_cursor = -1;
foreach ( $delete_marks as $delete_mark ) {
	$delete_at     = strpos( $delete_delegate, $delete_mark );
	$delete_order  = $delete_order && false !== $delete_at && $delete_at > $delete_cursor;
	$delete_cursor = false === $delete_at ? $delete_cursor : $delete_at;
}
$legacy_delete = false === strpos( $delete_route, 'replacement_enabled' ) && 1 === substr_count( $delete_route, 'delegate_delete(' ) && false === strpos( $delete_legacy, 'request_bindings' ) && false === strpos( $delete_legacy, 'provider_adapter' ) && false === strpos( $delete_legacy, 'rest_outcome' ) && false === strpos( $delete_legacy, 'delegate_delete' ) && false === strpos( $delete_legacy, 'stage_live_delete' ) && false === strpos( $delete_legacy, 'stage_delete' );
$deletes = array(
	'false' => array( 'entered' => false ),
	'missing'        => $run_delete( $delete_body( 501, 'zoom' ) ),
	'wrong_type'     => $run_delete( $delete_body( 502, 'google_meet' ) ),
	'missing_topic'  => $run_delete( $delete_body( 503, 'zoom' ) ),
	'wrong_topic'    => $run_delete( $delete_body( 504, 'zoom' ) ),
	'missing_course' => $run_delete( $delete_body( 506, 'zoom' ) ),
	'wrong_course'   => $run_delete( $delete_body( 508, 'zoom' ) ),
	'broken_item'    => $run_delete( $delete_body( 511, 'zoom' ) ),
	'broken_course'  => $run_delete( $delete_body( 513, 'zoom' ) ),
	'denied'         => $run_delete( $delete_body( 516, 'google_meet' ) ),
	'draft'          => $run_delete( $delete_body( 518, 'zoom' ) ),
	'private'        => $run_delete( $delete_body( 519, 'zoom' ) ),
	'google'         => $run_delete( $delete_body( '530', 'zoom' ), true, $confirmed_stage ),
	'zoom'           => $run_delete( $delete_body( 528, 'google_meet' ), true, $unconfirmed_stage ),
	'null_stage'     => $run_delete( $delete_body( 530, 'zoom' ), true, null ),
	'error_stage'    => $run_delete( $delete_body( 530, 'zoom' ), true, $stage_error ),
	'malformed'      => $run_delete( $delete_body( 530, 'zoom' ), true, array( 'note' => 'plain' ) ),
	'bad_bytes'      => $run_delete( $delete_body( 530, 'zoom' ), true, array( 'dispatch_state' => 'confirmed', 'envelope' => 'not-json' ) ),
	'unconfirmed'    => $run_delete( $delete_body( 528, 'google_meet' ), true, $unconfirmed_stage ),
	'confirmed'      => $run_delete( $delete_body( 530, 'zoom' ), true, $confirmed_stage ),
	'confirmed_envelope' => $confirmed_envelope,
	'unconfirmed_envelope' => $unconfirmed_envelope,
	'params'         => array( 'stage' => $stage_names, 'authorize' => array_slice( $auth_names, 0, 4 ), 'orchestrator' => $orch_names ),
	'cascade'        => false !== strpos( $cascade_body, 'authorize( $child, $topic, $course, $can )' ) && false !== strpos( $cascade_body, '$delete( $child )' ),
	'lifecycle'      => array( $google->lifecycle, $zoom->lifecycle ),
	'proof'          => $delete_clean && $delete_order && $legacy_delete && $stage_names === $orch_names && array( 'item', 'topic', 'course', 'can' ) === array_slice( $auth_names, 0, 4 ),
);
$put( 640, 'topics', 'publish', 641 );
$controller->posts[641] = $make_post(
	array(
		'ID'           => 941,
		'post_type'    => 'courses',
		'post_status'  => 'publish',
		'post_parent'  => 0,
		'post_author'  => 17,
		'post_title'   => 'Shifted course',
		'post_content' => 'Body',
		'menu_order'   => 2,
	)
);
$controller->children = array(
	$plant_row( 91, 'lesson', 71 ),
	$plant_row( 92, 'tutor-google-meet', 40 ),
);
$controller->meta[530] = array(
	'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
	'tutor-google-meet-end-datetime'   => '2026-10-06 19:00:00',
	'tutor-google-meet-event-details'  => array(
		'id'         => 'evt-1',
		'api_secret' => 'fake-credential-value',
		'title'      => 'Lab',
	),
	'tutor-google-meet-link'           => 'https://meet.example/abc',
);
$duplicate_error = new WP_Error( 'duplicate_blocked', 'Duplicate blocked.', array( 'status' => 409 ) );
$run_duplicate   = static function ( $params, $gate = true, $script = 540 ) use ( $controller, $pack_result, $named_calls, $google, $zoom ) {
	$controller->gate                = $gate;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->delete_stages       = array();
	$controller->duplicate_calls     = array();
	$controller->duplicate_script    = $script;
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$request                         = new WP_REST_Request();
	$request->method                 = 'POST';
	$request->route                  = '/live-lessons/(?P<id>[\d]+)/duplicate';
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary     = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$before_hosts = array( $google->host_calls, $zoom->host_calls );
	$result       = $controller->duplicate_item( $request );
	return array(
		'packed'     => $pack_result( $result ),
		'same'       => $result === $script,
		'ledger'     => $controller->adapter_resolutions,
		'services'   => $named_calls( $controller->calls, 'service' ),
		'metas'      => $named_calls( $controller->calls, 'meta' ),
		'writes'     => $named_calls( $controller->calls, 'write' ),
		'updates'    => $named_calls( $controller->calls, 'update' ),
		'posts'      => $named_calls( $controller->calls, 'post' ),
		'queries'    => $named_calls( $controller->calls, 'posts' ),
		'caps'       => $named_calls( $controller->calls, 'can' ),
		'reads'      => $named_calls( $controller->calls, 'read' ),
		'duplicates' => $controller->duplicate_calls,
		'stages'     => $controller->stage_calls,
		'dispatches' => $controller->dispatch_calls,
		'deletes'    => $controller->delete_stages,
		'bindings'   => $controller->binding_runs,
		'adapters'   => $controller->adapter_runs,
		'boundary'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
		'lifecycle'  => array( $google->lifecycle, $zoom->lifecycle ),
		'hosts'      => array( $google->host_calls, $zoom->host_calls ) === $before_hosts,
	);
};
$duplicate_body = static function ( $id, $topic_id = null ) {
	$body = array(
		'id'          => $id,
		'provider_id' => 'forged-provider',
		'event_id'    => 'forged-event',
		'credentials' => array( 'api_secret' => 'hidden-sentinel' ),
	);
	if ( null !== $topic_id ) {
		$body['topic_id'] = $topic_id;
	}
	return $body;
};
$duplicate_names       = static function ( $class, $method ) {
	$names = array();
	foreach ( ( new ReflectionMethod( $class, $method ) )->getParameters() as $param ) {
		$names[] = $param->getName();
	}
	return $names;
};
$duplicate_delegate  = $method_body( 'delegate_duplicate' );
$duplicate_live      = $method_body( 'duplicate_live' );
$duplicate_route     = $method_body( 'duplicate_item' );
$duplicate_legacy_at = strpos( $duplicate_route, 'delegate_duplicate(' );
$duplicate_legacy    = false === $duplicate_legacy_at ? $duplicate_route : substr( $duplicate_route, $duplicate_legacy_at + strlen( 'delegate_duplicate($request);' ) );
$duplicate_path      = $duplicate_delegate . $duplicate_live;
$duplicate_forbidden = array( 'provider_adapter', 'load_service', 'wp_insert_post', 'wp_delete_post', 'wp_update_post', 'update_post_meta', 'add_post_meta', 'delete_post_meta', 'get_post_meta', 'metadata_map', 'hydrate_', 'Orchestrator', 'stage_pending', 'stage_delete', 'stage_update', 'dispatch_', 'do_action', "['envelope']", "['write']", "['update']", 'edit_posts', 'list_hosts', 'preflight(', 'GoogleEvent', 'TUTOR_ZOOM' );
$duplicate_clean     = 1 === substr_count( $duplicate_live, 'TutorPress_Live_Lesson_Duplicate_Service::duplicate(' ) && 1 === substr_count( $duplicate_delegate, 'duplicate_live(' ) && 1 === substr_count( $duplicate_delegate, "rest_outcome('duplicate'" ) && false === strpos( $duplicate_delegate, 'Duplicate_Service' ) && false !== strpos( $duplicate_delegate, "'edit_post' === \$capability" ) && false !== strpos( $duplicate_live, 'duplicate($source, $topic, $course, $target, $target_course, $children, $meta, $can)' );
foreach ( $duplicate_forbidden as $duplicate_needle ) {
	$duplicate_clean = $duplicate_clean && false === strpos( $duplicate_path, $duplicate_needle );
}
$duplicate_marks  = array( "['lookup']", 'invalid_post_type', 'invalid_topic', "'edit_post' === \$capability", '::authorize(', "'publish'", 'duplication_not_supported', "get_param('topic_id')", 'authorize_create(', "['children']", "['metadata']", 'duplicate_live(', "rest_outcome('duplicate'" );
$duplicate_order  = true;
$duplicate_cursor = -1;
foreach ( $duplicate_marks as $duplicate_mark ) {
	$duplicate_at     = strpos( $duplicate_delegate, $duplicate_mark );
	$duplicate_order  = $duplicate_order && false !== $duplicate_at && $duplicate_at > $duplicate_cursor;
	$duplicate_cursor = false === $duplicate_at ? $duplicate_cursor : $duplicate_at;
}
$legacy_duplicate = false === strpos( $duplicate_route, 'replacement_enabled' ) && 1 === substr_count( $duplicate_route, 'delegate_duplicate(' ) && false === strpos( $duplicate_legacy, 'request_bindings' ) && false === strpos( $duplicate_legacy, 'provider_adapter' ) && false === strpos( $duplicate_legacy, 'rest_outcome' ) && false === strpos( $duplicate_legacy, 'delegate_duplicate' ) && false === strpos( $duplicate_legacy, 'duplicate_live(' ) && false === strpos( $duplicate_legacy, 'Duplicate_Service' );
$live_names       = $duplicate_names( 'TutorPress_REST_Live_Lessons_Controller', 'duplicate_live' );
$service_names    = $duplicate_names( 'TutorPress_Live_Lesson_Duplicate_Service', 'duplicate' );
$duplicates       = array(
	'false'          => array( 'entered' => false ),
	'missing'        => $run_duplicate( $duplicate_body( 501 ) ),
	'wrong'          => $run_duplicate( $duplicate_body( 502 ) ),
	'draft'          => $run_duplicate( $duplicate_body( 542 ) ),
	'private'        => $run_duplicate( $duplicate_body( 519 ) ),
	'missing_topic'  => $run_duplicate( $duplicate_body( 503 ) ),
	'wrong_topic'    => $run_duplicate( $duplicate_body( 504 ) ),
	'missing_course' => $run_duplicate( $duplicate_body( 506 ) ),
	'wrong_course'   => $run_duplicate( $duplicate_body( 508 ) ),
	'broken_item'    => $run_duplicate( $duplicate_body( 511 ) ),
	'broken_course'  => $run_duplicate( $duplicate_body( 513 ) ),
	'denied'         => $run_duplicate( $duplicate_body( 541 ) ),
	'zoom'           => $run_duplicate( $duplicate_body( 528 ) ),
	'dest_missing'   => $run_duplicate( $duplicate_body( 530, 699 ) ),
	'dest_zero'      => $run_duplicate( $duplicate_body( 530, 0 ) ),
	'dest_wrong'     => $run_duplicate( $duplicate_body( 530, 502 ) ),
	'dest_forged'    => $run_duplicate( $duplicate_body( 530, 640 ) ),
	'dest_denied'    => $run_duplicate( $duplicate_body( 530, 81 ) ),
	'default'        => $run_duplicate( $duplicate_body( 530 ), true, 540 ),
	'explicit'       => $run_duplicate( $duplicate_body( 530, 71 ), true, 540 ),
	'null_result'    => $run_duplicate( $duplicate_body( 530 ), true, null ),
	'error_result'   => $run_duplicate( $duplicate_body( 530 ), true, $duplicate_error ),
	'params'         => array( 'live' => $live_names, 'service' => $service_names ),
	'proof'          => $duplicate_clean && $duplicate_order && $legacy_duplicate && $live_names === $service_names,
);
$reconcile_error = new WP_Error( 'live_lesson_retry_blocked', 'Retry blocked.', array( 'status' => 409 ) );
$meet_comparison = array(
	'match'  => false,
	'fields' => array(
		'end_at'      => '2026-10-07T17:00:00Z',
		'provider_id' => 'evt-9',
		'start_at'    => '2026-10-07T16:00:00Z',
		'timezone'    => 'UTC',
	),
);
$reconcile_envelope = json_decode( $sync_bytes( 'unlinked', array( 'version' => 2, 'warning' => 'orphan_may_remain' ) ), true );
$reconcile_bytes    = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $reconcile_envelope );
$reconcile_wrapped  = array( 'post_id' => 530, 'provider' => 'zoom', 'envelope' => $reconcile_bytes );
$owner_shape        = array( 'owner_id' => 44, 'candidates' => array( array( 'id' => 44, 'label' => 'Row 15' ) ) );
$owner_extra        = array( 'owner_id' => 44, 'candidates' => array( array( 'id' => 44, 'label' => 'Row 15' ) ), 'code' => 'legacy_identity_resolution_required' );
$owner_zero         = array( 'owner_id' => 0, 'candidates' => array( array( 'id' => 44, 'label' => 'Row 15' ) ) );
$owner_absent       = array( 'owner_id' => 17, 'candidates' => array( array( 'id' => 44, 'label' => 'Row 15' ) ) );
$run_reconcile_case = static function ( $params, $script = null, $sync_id = null, $sync = null ) use ( $controller, $pack_result, $named_calls, $google, $zoom ) {
	$controller->gate                = true;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->delete_stages       = array();
	$controller->duplicate_calls     = array();
	$controller->reconcile_calls     = array();
	$controller->reconcile_script    = $script;
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$had                             = null !== $sync_id && array_key_exists( (int) $sync_id, $controller->sync_rows );
	$prior                           = $had ? $controller->sync_rows[ (int) $sync_id ] : null;
	if ( null !== $sync_id ) {
		$controller->sync_rows[ (int) $sync_id ] = $sync;
	}
	$request         = new WP_REST_Request();
	$request->method = 'POST';
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary     = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$before_hosts = array( $google->host_calls, $zoom->host_calls );
	$result       = $controller->reconcile_item( $request );
	if ( null !== $sync_id ) {
		if ( $had ) {
			$controller->sync_rows[ (int) $sync_id ] = $prior;
		} else {
			unset( $controller->sync_rows[ (int) $sync_id ] );
		}
	}
	return array(
		'packed'     => $pack_result( $result ),
		'same'       => $result === $script,
		'ledger'     => $controller->adapter_resolutions,
		'services'   => $named_calls( $controller->calls, 'service' ),
		'metas'      => $named_calls( $controller->calls, 'meta' ),
		'writes'     => $named_calls( $controller->calls, 'write' ),
		'updates'    => $named_calls( $controller->calls, 'update' ),
		'posts'      => $named_calls( $controller->calls, 'post' ),
		'caps'       => $named_calls( $controller->calls, 'can' ),
		'reads'      => $named_calls( $controller->calls, 'read' ),
		'options'    => $named_calls( $controller->calls, 'option' ),
		'takeovers'  => $named_calls( $controller->calls, 'takeover' ),
		'reconciles' => $controller->reconcile_calls,
		'stages'     => $controller->stage_calls,
		'dispatches' => $controller->dispatch_calls,
		'deletes'    => $controller->delete_stages,
		'duplicates' => $controller->duplicate_calls,
		'bindings'   => $controller->binding_runs,
		'adapters'   => $controller->adapter_runs,
		'boundary'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
		'lifecycle'  => array( $google->lifecycle, $zoom->lifecycle ),
		'hosts'      => array( $google->host_calls, $zoom->host_calls ) === $before_hosts,
	);
};
$reconcile_body = static function ( $id, $action, $version, $operation = null ) {
	$body = array(
		'id'                => $id,
		'action'            => $action,
		'version'           => $version,
		'type'              => 'zoom' === $action ? 'google_meet' : 'zoom',
		'credentials'       => array( 'api_secret' => 'hidden-sentinel' ),
		'selected_owner_id' => 999,
	);
	if ( null !== $operation ) {
		$body['operation_id'] = $operation;
	}
	return $body;
};
$identity_cases = array();
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity_key ) {
	$identity_body                   = $reconcile_body( 530, 'inspect', 1, 'op-1' );
	$identity_body[ $identity_key ]  = 'forged-provider';
	$identity_cases[ $identity_key ] = $run_reconcile_case( $identity_body, $meet_comparison );
}
$reconcile_paths = static function ( $added ) {
	$paths = array();
	foreach ( $added as $route ) {
		$paths[] = $route['route'];
	}
	return $paths;
};
$before_false_routes = count( TutorPress_Live_Lessons_Rest_Fake::$routes );
$controller->register_routes( false );
$false_route_rows = array_slice( TutorPress_Live_Lessons_Rest_Fake::$routes, $before_false_routes );
$before_true_routes = count( TutorPress_Live_Lessons_Rest_Fake::$routes );
$controller->register_routes( true );
$true_route_rows = array_slice( TutorPress_Live_Lessons_Rest_Fake::$routes, $before_true_routes );
$reconcile_schema = null;
$reconcile_callback = '';
$reconcile_permission = '';
$attach_registered = array();
foreach ( $true_route_rows as $route_row ) {
	$endpoint   = isset( $route_row['endpoints'][0] ) ? $route_row['endpoints'][0] : array();
	$callback   = str_replace( "\0", '', isset( $endpoint['callback'] ) ? $endpoint['callback'] : '' );
	$permission = str_replace( "\0", '', isset( $endpoint['permission_callback'] ) ? $endpoint['permission_callback'] : '' );
	$callback   = '' === $callback ? '' : substr( $callback, (int) strrpos( $callback, '::' ) + 2 );
	$permission = '' === $permission ? '' : substr( $permission, (int) strrpos( $permission, '::' ) + 2 );
	if ( false !== strpos( $route_row['route'], '/reconcile' ) && null === $reconcile_schema ) {
		$reconcile_schema     = isset( $endpoint['schema'] ) ? $endpoint['schema'] : null;
		$reconcile_callback   = $callback;
		$reconcile_permission = $permission;
	}
	if ( false !== strpos( $route_row['route'], '/attach/' ) ) {
		$attach_registered[] = array(
			'route'      => $route_row['route'],
			'schema'     => isset( $endpoint['schema'] ) ? $endpoint['schema'] : array(),
			'callback'   => $callback,
			'permission' => $permission,
			'method'     => isset( $endpoint['method'] ) ? $endpoint['method'] : '',
		);
	}
}
$reconcile_item_body = $method_body( 'reconcile_item' );
$reconcile_run_body  = $method_body( 'run_reconcile' );
$reconcile_route_body = $method_body( 'register_routes' );
$reconcile_surface   = $method_body( 'validate_reconcile_operation' ) . $reconcile_item_body . $method_body( 'owner_evidence' ) . $method_body( 'reconcile_result' ) . $reconcile_run_body;
$reconcile_forbidden = array( 'get_option', 'update_option', 'add_option', '$wpdb', 'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'add_post_meta', 'update_post_meta', 'delete_post_meta', 'get_post_meta', 'metadata_map', 'hydrate_', '->create(', '->fetch(', '->update(', '->delete(', 'list_hosts', 'preflight(', 'edit_posts', "get_param('provider_id')", "get_param('type')", "get_param('event_id')", "get_param('meeting_id')", "get_param('credentials')", 'selected_owner_id' );
$reconcile_clean     = false !== strpos( $reconcile_item_body, "'edit_post' === \$capability" ) && 1 === substr_count( $reconcile_run_body, '::inspect(' ) && 1 === substr_count( $reconcile_run_body, '::retry(' ) && 1 === substr_count( $reconcile_run_body, '::abandon_unknown(' ) && 1 === substr_count( $reconcile_run_body, '::resolve_owner(' ) && 1 === substr_count( $reconcile_run_body, "['write']" );
foreach ( $reconcile_forbidden as $reconcile_needle ) {
	$reconcile_clean = $reconcile_clean && false === strpos( $reconcile_surface, $reconcile_needle );
}
$reconcile_marks  = array( 'provider_id', 'validate_reconcile_operation(', "['lookup']", 'invalid_post_type', 'invalid_topic', "'edit_post' === \$capability", '::authorize(', "'publish'", 'abandon_unknown', "['envelope']", 'provider_adapter(' );
$reconcile_order  = true;
$reconcile_cursor = -1;
foreach ( $reconcile_marks as $reconcile_mark ) {
	$reconcile_at     = strpos( $reconcile_item_body, $reconcile_mark );
	$reconcile_order  = $reconcile_order && false !== $reconcile_at && $reconcile_at > $reconcile_cursor;
	$reconcile_cursor = false === $reconcile_at ? $reconcile_cursor : $reconcile_at;
}
$retry_line    = '';
$inspect_line  = '';
$abandon_line  = '';
$resolve_line  = '';
foreach ( explode( "\n", $reconcile_run_body ) as $reconcile_line ) {
	if ( false !== strpos( $reconcile_line, '::retry(' ) ) { $retry_line = $reconcile_line; }
	if ( false !== strpos( $reconcile_line, '::inspect(' ) ) { $inspect_line = $reconcile_line; }
	if ( false !== strpos( $reconcile_line, '::abandon_unknown(' ) ) { $abandon_line = $reconcile_line; }
	if ( false !== strpos( $reconcile_line, '::resolve_owner(' ) ) { $resolve_line = $reconcile_line; }
}
$schema_at = strpos( $reconcile_route_body, '/reconcile' );
$schema_slice = false === $schema_at ? '' : substr( $reconcile_route_body, $schema_at, 700 );
$schema_identity_free = false !== $schema_at && false === strpos( $schema_slice, 'provider_id' ) && false === strpos( $schema_slice, 'event_id' ) && false === strpos( $schema_slice, 'meeting_id' ) && false === strpos( $schema_slice, 'eventId' ) && false === strpos( $schema_slice, 'meetingId' );
$gate_at = strrpos( substr( $reconcile_route_body, 0, (int) $schema_at ), 'if ($enabled)' );
$reconcile_cases = array(
	'identity'            => $identity_cases,
	'missing_operation'   => $run_reconcile_case( $reconcile_body( 530, 'inspect', 1 ) ),
	'zero_inspect'        => $run_reconcile_case( $reconcile_body( 530, 'inspect', 0, 'op-1' ) ),
	'owner_operation'     => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0, 'op-1' ) ),
	'owner_version'       => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 1 ) ),
	'attach'              => $run_reconcile_case( $reconcile_body( 530, 'attach', 1, 'op-1' ) ),
	'missing'             => $run_reconcile_case( $reconcile_body( 501, 'inspect', 1, 'op-1' ) ),
	'wrong'               => $run_reconcile_case( $reconcile_body( 502, 'inspect', 1, 'op-1' ) ),
	'draft'               => $run_reconcile_case( $reconcile_body( 518, 'inspect', 1, 'op-1' ) ),
	'private'             => $run_reconcile_case( $reconcile_body( 519, 'retry', 1, 'op-1' ) ),
	'missing_topic'       => $run_reconcile_case( $reconcile_body( 503, 'inspect', 1, 'op-1' ) ),
	'wrong_topic'         => $run_reconcile_case( $reconcile_body( 504, 'inspect', 1, 'op-1' ) ),
	'missing_course'      => $run_reconcile_case( $reconcile_body( 506, 'inspect', 1, 'op-1' ) ),
	'wrong_course'        => $run_reconcile_case( $reconcile_body( 508, 'inspect', 1, 'op-1' ) ),
	'broken_item'         => $run_reconcile_case( $reconcile_body( 511, 'inspect', 1, 'op-1' ) ),
	'broken_course'       => $run_reconcile_case( $reconcile_body( 513, 'resolve_owner', 0 ) ),
	'denied'              => $run_reconcile_case( $reconcile_body( 516, 'abandon_unknown', 1, 'op-1' ) ),
	'rejected'            => $run_reconcile_case( $reconcile_body( 520, 'inspect', 1, 'op-1' ), $meet_comparison ),
	'absent_google'       => $run_reconcile_case( $reconcile_body( 530, 'inspect', 1, 'op-1' ), $meet_comparison ),
	'absent_zoom'         => $run_reconcile_case( $reconcile_body( 521, 'retry', 1, 'op-1' ), $reconcile_wrapped ),
	'envelope_zoom'       => $run_reconcile_case( $reconcile_body( 530, 'inspect', 1, 'op-1' ), $meet_comparison, 530, $sync_row( 530, $sync_bytes( 'synced' ) ) ),
	'envelope_google'     => $run_reconcile_case( $reconcile_body( 521, 'resolve_owner', 0 ), $meet_comparison, 521, $sync_row( 521, $sync_bytes( 'synced', array( 'provider' => 'google_meet' ) ) ) ),
	'retry'               => $run_reconcile_case( $reconcile_body( 530, 'retry', 1, 'op-1' ), $reconcile_wrapped, 530, $sync_row( 530, $sync_bytes( 'synced' ) ) ),
	'resolve'             => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), $meet_comparison ),
	'resolve_shape'       => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), $owner_shape ),
	'resolve_extra'       => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), $owner_extra ),
	'resolve_zero'        => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), $owner_zero ),
	'resolve_absent'      => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), $owner_absent ),
	'null_retry'          => $run_reconcile_case( $reconcile_body( 530, 'retry', 1, 'op-1' ), null ),
	'null_resolve'        => $run_reconcile_case( $reconcile_body( 530, 'resolve_owner', 0 ), null ),
	'null_abandon'        => $run_reconcile_case( $reconcile_body( 520, 'abandon_unknown', 1, 'op-1' ), null ),
	'abandon'             => $run_reconcile_case( $reconcile_body( 520, 'abandon_unknown', 1, 'op-1' ), $reconcile_wrapped ),
	'error'               => $run_reconcile_case( $reconcile_body( 530, 'retry', 1, 'op-1' ), $reconcile_error ),
	'null_inspect'        => $run_reconcile_case( $reconcile_body( 530, 'inspect', 1, 'op-1' ), null ),
	'routes'              => array( 'false' => $reconcile_paths( $false_route_rows ), 'true' => $reconcile_paths( $true_route_rows ) ),
	'schema'              => $reconcile_schema,
	'callback'            => $reconcile_callback,
	'permission'          => $reconcile_permission,
	'proof'               => $reconcile_clean && $reconcile_order && $schema_identity_free && false === $gate_at && false === strpos( $inspect_line, "['write']" ) && false === strpos( $inspect_line, "['update']" ) && false !== strpos( $retry_line, "['write']" ) && false === strpos( $abandon_line, '$adapter' ) && false === strpos( $abandon_line, "['write']" ) && false !== strpos( $abandon_line, "['update']" ) && false === strpos( $resolve_line, "['write']" ) && false === strpos( $resolve_line, "['update']" ) && false !== strpos( $resolve_line, '$adapter' ),
);
$uncertain_route = '/tutorpress/v1/live-lessons/(?P<id>[\d]+)/attach/uncertain-create';
$legacy_route    = '/tutorpress/v1/live-lessons/(?P<id>[\d]+)/attach/legacy';
$attach_google_local = array(
	'tutor-google-meet-start-datetime' => '2026-10-08 15:00:00',
	'tutor-google-meet-link'           => 'https://meet.example/row',
);
$run_attach_case = static function ( $route, $params, $script = null, $sync_id = null, $sync = null, $meta_id = null, $meta = null ) use ( $controller, $pack_result, $named_calls, $google, $zoom ) {
	$controller->gate                = true;
	$controller->actor               = 44;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->delete_stages       = array();
	$controller->duplicate_calls     = array();
	$controller->reconcile_calls     = array();
	$controller->reconcile_script    = null;
	$controller->attach_calls        = array();
	$controller->attach_script       = $script;
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$had                             = null !== $sync_id && array_key_exists( (int) $sync_id, $controller->sync_rows );
	$prior                           = $had ? $controller->sync_rows[ (int) $sync_id ] : null;
	if ( null !== $sync_id ) {
		$controller->sync_rows[ (int) $sync_id ] = $sync;
	}
	$meta_had   = null !== $meta_id && array_key_exists( (int) $meta_id, $controller->meta );
	$meta_prior = $meta_had ? $controller->meta[ (int) $meta_id ] : null;
	if ( null !== $meta_id ) {
		$controller->meta[ (int) $meta_id ] = $meta;
	}
	$request         = new WP_REST_Request();
	$request->method = 'POST';
	$request->route  = $route;
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$boundary     = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$before_hosts = array( $google->host_calls, $zoom->host_calls );
	$result       = $controller->attach_item( $request );
	if ( null !== $sync_id ) {
		if ( $had ) {
			$controller->sync_rows[ (int) $sync_id ] = $prior;
		} else {
			unset( $controller->sync_rows[ (int) $sync_id ] );
		}
	}
	if ( null !== $meta_id ) {
		if ( $meta_had ) {
			$controller->meta[ (int) $meta_id ] = $meta_prior;
		} else {
			unset( $controller->meta[ (int) $meta_id ] );
		}
	}
	return array(
		'packed'     => $pack_result( $result ),
		'ledger'     => $controller->adapter_resolutions,
		'services'   => $named_calls( $controller->calls, 'service' ),
		'metas'      => $named_calls( $controller->calls, 'meta' ),
		'writes'     => $named_calls( $controller->calls, 'write' ),
		'updates'    => $named_calls( $controller->calls, 'update' ),
		'posts'      => $named_calls( $controller->calls, 'post' ),
		'caps'       => $named_calls( $controller->calls, 'can' ),
		'reads'      => $named_calls( $controller->calls, 'read' ),
		'options'    => $named_calls( $controller->calls, 'option' ),
		'takeovers'  => $named_calls( $controller->calls, 'takeover' ),
		'reconciles' => $controller->reconcile_calls,
		'attaches'   => $controller->attach_calls,
		'stages'     => $controller->stage_calls,
		'dispatches' => $controller->dispatch_calls,
		'deletes'    => $controller->delete_stages,
		'duplicates' => $controller->duplicate_calls,
		'bindings'   => $controller->binding_runs,
		'adapters'   => $controller->adapter_runs,
		'boundary'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
		'lifecycle'  => array( $google->lifecycle, $zoom->lifecycle ),
		'hosts'      => array( $google->host_calls, $zoom->host_calls ) === $before_hosts,
	);
};
$attach_body = static function ( $id, $version, $operation = null, $owner = null, $extra = array() ) {
	$body = array(
		'id'          => $id,
		'version'     => $version,
		'provider_id' => 'evt-supplied',
		'type'        => 'zoom',
		'evidence'    => array( array( 'source' => 'client', 'id' => 9, 'label' => 'hidden-sentinel' ) ),
	);
	if ( null !== $operation ) {
		$body['operation_id'] = $operation;
	}
	if ( null !== $owner ) {
		$body['selected_owner_id'] = $owner;
	}
	foreach ( $extra as $key => $value ) {
		$body[ $key ] = $value;
	}
	return $body;
};
$attach_blocked = array();
foreach ( array( 'credentials' => array( 'api_secret' => 'hidden-sentinel' ), 'event_id' => 'evt-9', 'meeting_id' => 'zm-9', 'eventId' => 'evt-9', 'meetingId' => 'zm-9' ) as $blocked_key => $blocked_value ) {
	$attach_blocked[ 'uncertain_' . $blocked_key ] = $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1', null, array( $blocked_key => $blocked_value ) ), $reconcile_wrapped );
	$attach_blocked[ 'legacy_' . $blocked_key ]    = $run_attach_case( $legacy_route, $attach_body( 530, 0, null, null, array( $blocked_key => $blocked_value ) ), $reconcile_wrapped );
}
$attach_blocked['uncertain_selected_owner_id'] = $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1', 27 ), $reconcile_wrapped );
$attach_blocked['uncertain_kind']              = $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1', null, array( 'kind' => 'legacy_identity' ) ), $reconcile_wrapped );
$attach_blocked['legacy_kind']                 = $run_attach_case( $legacy_route, $attach_body( 530, 0, null, null, array( 'kind' => 'uncertain_create' ) ), $reconcile_wrapped );
$missing_provider = $attach_body( 530, 1, 'op-1' );
unset( $missing_provider['provider_id'] );
$attach_shape = array(
	'uncertain_version'    => $run_attach_case( $uncertain_route, $attach_body( 530, 0, 'op-1' ) ),
	'uncertain_operation'  => $run_attach_case( $uncertain_route, $attach_body( 530, 1 ) ),
	'uncertain_empty_op'   => $run_attach_case( $uncertain_route, $attach_body( 530, 1, '' ) ),
	'uncertain_empty_id'   => $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1', null, array( 'provider_id' => '' ) ) ),
	'uncertain_missing_id' => $run_attach_case( $uncertain_route, $missing_provider ),
	'legacy_version'       => $run_attach_case( $legacy_route, $attach_body( 530, 1 ) ),
	'legacy_operation'     => $run_attach_case( $legacy_route, $attach_body( 530, 0, 'op-1' ) ),
	'legacy_empty_op'      => $run_attach_case( $legacy_route, $attach_body( 530, 0, '' ) ),
	'legacy_owner_zero'    => $run_attach_case( $legacy_route, $attach_body( 530, 0, null, 0 ) ),
	'legacy_owner_text'    => $run_attach_case( $legacy_route, $attach_body( 530, 0, null, '17' ) ),
	'legacy_empty_id'      => $run_attach_case( $legacy_route, $attach_body( 530, 0, null, null, array( 'provider_id' => '' ) ) ),
	'unknown_route'        => $run_attach_case( '/live-lessons/530/attach', $attach_body( 530, 1, 'op-1' ) ),
);
$attach_chain = array(
	'missing'         => $run_attach_case( $uncertain_route, $attach_body( 501, 1, 'op-1' ) ),
	'wrong'           => $run_attach_case( $uncertain_route, $attach_body( 502, 1, 'op-1' ) ),
	'draft'           => $run_attach_case( $uncertain_route, $attach_body( 518, 1, 'op-1' ) ),
	'private'         => $run_attach_case( $uncertain_route, $attach_body( 519, 1, 'op-1' ) ),
	'missing_topic'   => $run_attach_case( $uncertain_route, $attach_body( 503, 1, 'op-1' ) ),
	'wrong_topic'     => $run_attach_case( $uncertain_route, $attach_body( 504, 1, 'op-1' ) ),
	'missing_course'  => $run_attach_case( $uncertain_route, $attach_body( 506, 1, 'op-1' ) ),
	'wrong_course'    => $run_attach_case( $uncertain_route, $attach_body( 508, 1, 'op-1' ) ),
	'broken_item'     => $run_attach_case( $uncertain_route, $attach_body( 511, 1, 'op-1' ) ),
	'broken_course'   => $run_attach_case( $uncertain_route, $attach_body( 513, 1, 'op-1' ) ),
	'denied'          => $run_attach_case( $uncertain_route, $attach_body( 516, 1, 'op-1' ) ),
	'rejected'        => $run_attach_case( $uncertain_route, $attach_body( 520, 1, 'op-1' ) ),
	'legacy_draft'    => $run_attach_case( $legacy_route, $attach_body( 518, 0 ) ),
	'legacy_denied'   => $run_attach_case( $legacy_route, $attach_body( 516, 0 ) ),
	'legacy_rejected' => $run_attach_case( $legacy_route, $attach_body( 520, 0 ) ),
);
$zoom_synced   = $sync_row( 530, $sync_bytes( 'synced' ) );
$google_synced = $sync_row( 521, $sync_bytes( 'synced', array( 'provider' => 'google_meet' ) ) );
$attach_live = array(
	'uncertain_google'        => $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1' ), $reconcile_wrapped ),
	'uncertain_zoom_envelope' => $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1' ), $reconcile_wrapped, 530, $zoom_synced ),
	'uncertain_zoom'          => $run_attach_case( $uncertain_route, $attach_body( 521, 1, 'op-1' ), $reconcile_wrapped ),
	'legacy_google'           => $run_attach_case( $legacy_route, $attach_body( 530, 0 ), $reconcile_wrapped, null, null, 530, $attach_google_local ),
	'legacy_zoom_empty'       => $run_attach_case( $legacy_route, $attach_body( 521, 0 ), $reconcile_wrapped ),
	'legacy_selected'         => $run_attach_case( $legacy_route, $attach_body( 530, 0, null, 27 ), $reconcile_wrapped, null, null, 530, array() ),
	'legacy_google_envelope'  => $run_attach_case( $legacy_route, $attach_body( 521, 0 ), $reconcile_wrapped, 521, $google_synced ),
	'null_uncertain'          => $run_attach_case( $uncertain_route, $attach_body( 530, 1, 'op-1' ), null ),
	'null_legacy'             => $run_attach_case( $legacy_route, $attach_body( 530, 0 ), null ),
);
$attach_item_body = $method_body( 'attach_item' );
$attach_run_body  = $method_body( 'run_attach' );
$uncertain_call   = '';
$legacy_call      = '';
foreach ( explode( "\n", $attach_run_body ) as $attach_line ) {
	if ( false !== strpos( $attach_line, '::attach_provider_identity(' ) ) { $uncertain_call = $attach_line; }
	if ( false !== strpos( $attach_line, '::attach_legacy_identity(' ) ) { $legacy_call = $attach_line; }
}
$attach_surface = $attach_item_body . $attach_run_body;
$attach_clean   = 1 === substr_count( $attach_run_body, '::attach_provider_identity(' ) && 1 === substr_count( $attach_run_body, '::attach_legacy_identity(' ) && 1 === substr_count( $attach_item_body, "['metadata'](" );
foreach ( array( 'metadata_map', 'hydrate_item', 'hydrate_legacy', '$wpdb', 'get_option', 'update_option', 'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'add_post_meta', 'update_post_meta', 'get_post_meta', 'edit_posts', 'list_hosts', 'preflight(' ) as $attach_needle ) {
	$attach_clean = $attach_clean && false === strpos( $attach_surface, $attach_needle );
}
$attach_marks  = array( 'live_lesson_supplied_identity', 'request_bindings(', "'edit_post' === \$capability", "['envelope']", 'if ($legacy)', "['metadata'](", 'run_attach(', 'provider_adapter(' );
$attach_order  = true;
$attach_cursor = -1;
foreach ( $attach_marks as $attach_mark ) {
	$attach_at     = strpos( $attach_item_body, $attach_mark );
	$attach_order  = $attach_order && false !== $attach_at && $attach_at > $attach_cursor;
	$attach_cursor = false === $attach_at ? $attach_cursor : $attach_at;
}
$uncertain_args = false !== strpos( $uncertain_call, "\$read, \$option, \$takeover, \$bindings['write'], \$bindings['update'], \$bindings['affected']" );
$legacy_args    = false !== strpos( $legacy_call, "\$read, \$bindings['write'], \$option, \$takeover" ) && false === strpos( $legacy_call, "['update']" ) && false === strpos( $legacy_call, "['affected']" );
$attach_cases = array(
	'registered' => $attach_registered,
	'blocked'    => $attach_blocked,
	'shape'      => $attach_shape,
	'chain'      => $attach_chain,
	'live'       => $attach_live,
	'proof'      => $attach_clean && $attach_order && $uncertain_args && $legacy_args,
);
$matrix_request = static function ( $method, $route, $params ) {
	$request         = new WP_REST_Request();
	$request->method = $method;
	$request->route  = $route;
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$matrix_reset = static function () use ( $controller, $google, $zoom ) {
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->stage_calls         = array();
	$controller->dispatch_calls      = array();
	$controller->unlinked_stages     = array();
	$controller->synced_stages       = array();
	$controller->synced_dispatches   = array();
	$controller->delete_stages       = array();
	$controller->duplicate_calls     = array();
	$controller->reconcile_calls     = array();
	$controller->attach_calls        = array();
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$controller->can_results         = null;
	$controller->broad_only          = false;
	$controller->stage_script        = null;
	$controller->dispatch_script     = null;
	$controller->duplicate_script    = null;
	$controller->attach_script       = null;
	$controller->reconcile_script    = null;
	$google->lifecycle               = 0;
	$zoom->lifecycle                 = 0;
	$google->host_calls              = array();
	$zoom->host_calls                = array();
	$google->preflight_calls         = array();
	$zoom->preflight_calls           = array();
};
$matrix_call = static function ( $gate, $broad, $callback ) use ( $controller, $google, $zoom, $matrix_reset, $pack_result, $named_calls ) {
	$matrix_reset();
	$controller->gate       = $gate;
	$controller->broad_only = $broad;
	$boundary               = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
	$result                 = $callback();
	$controller->broad_only = false;
	return array(
		'packed'     => $pack_result( $result ),
		'caps'       => $named_calls( $controller->calls, 'can' ),
		'metas'      => $named_calls( $controller->calls, 'meta' ),
		'reads'      => $named_calls( $controller->calls, 'read' ),
		'services'   => $named_calls( $controller->calls, 'service' ),
		'bindings'   => $controller->binding_runs,
		'adapters'   => $controller->adapter_runs,
		'ledger'     => $controller->adapter_resolutions,
		'stages'     => $controller->stage_calls,
		'dispatches' => $controller->dispatch_calls,
		'deletes'    => $controller->delete_stages,
		'duplicates' => $controller->duplicate_calls,
		'reconciles' => $controller->reconcile_calls,
		'attaches'   => $controller->attach_calls,
		'boundary'   => TutorPress_Live_Lessons_Boundary_Fake::$ledger === $boundary,
		'lifecycle'  => array( $google->lifecycle, $zoom->lifecycle ),
		'hosts'      => array( $google->host_calls, $zoom->host_calls ),
	);
};
$matrix_schedule = array(
	'course_id'       => 15,
	'topic_id'        => 71,
	'title'           => 'Weekly lab',
	'type'            => 'google_meet',
	'start_date_time' => '2026-10-06 15:00:00',
	'end_date_time'   => '2026-10-06 16:00:00',
);
$matrix_patch = static function ( $id ) {
	return array(
		'id'              => $id,
		'start_date_time' => '2026-10-06 15:00:00',
		'end_date_time'   => '2026-10-06 16:00:00',
	);
};
$matrix_item = '/live-lessons/(?P<id>[\d]+)';
$matrix_uncertain = '/tutorpress/v1/live-lessons/(?P<id>[\d]+)/attach/uncertain-create';
$matrix_legacy    = '/tutorpress/v1/live-lessons/(?P<id>[\d]+)/attach/legacy';
$matrix_denied = array(
	'collection' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->get_items( $matrix_request( 'GET', '/live-lessons', array( 'course_id' => 15 ) ) );
	} ),
	'zoom' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->get_zoom_users( $matrix_request( 'GET', '/live-lessons/zoom/users', array( 'course_id' => 15 ) ) );
	} ),
	'item' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_item ) {
		return $controller->get_item( $matrix_request( 'GET', $matrix_item, array( 'id' => 530 ) ) );
	} ),
	'create' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_schedule ) {
		return $controller->mutate_schedule( $matrix_request( 'POST', '/live-lessons', $matrix_schedule ) );
	} ),
	'synced_patch' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_patch, $matrix_item ) {
		return $controller->mutate_schedule( $matrix_request( 'PATCH', $matrix_item, $matrix_patch( 611 ) ) );
	} ),
	'unlinked_patch' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_patch, $matrix_item ) {
		return $controller->mutate_schedule( $matrix_request( 'PATCH', $matrix_item, $matrix_patch( 610 ) ) );
	} ),
	'delete' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_item ) {
		return $controller->delete_item( $matrix_request( 'DELETE', $matrix_item, array( 'id' => 530 ) ) );
	} ),
	'duplicate' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->duplicate_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/duplicate', array( 'id' => 530 ) ) );
	} ),
	'inspect' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->reconcile_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/reconcile', array( 'id' => 530, 'action' => 'inspect', 'version' => 1, 'operation_id' => 'op-1' ) ) );
	} ),
	'retry' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->reconcile_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/reconcile', array( 'id' => 530, 'action' => 'retry', 'version' => 1, 'operation_id' => 'op-1' ) ) );
	} ),
	'resolve_owner' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->reconcile_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/reconcile', array( 'id' => 530, 'action' => 'resolve_owner', 'version' => 0 ) ) );
	} ),
	'abandon_unknown' => $matrix_call( true, true, static function () use ( $controller, $matrix_request ) {
		return $controller->reconcile_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/reconcile', array( 'id' => 530, 'action' => 'abandon_unknown', 'version' => 1, 'operation_id' => 'op-1' ) ) );
	} ),
	'uncertain' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_uncertain ) {
		return $controller->attach_item( $matrix_request( 'POST', $matrix_uncertain, array( 'id' => 530, 'provider_id' => 'evt-forged-attach', 'version' => 1, 'operation_id' => 'op-1' ) ) );
	} ),
	'legacy_attach' => $matrix_call( true, true, static function () use ( $controller, $matrix_request, $matrix_legacy ) {
		return $controller->attach_item( $matrix_request( 'POST', $matrix_legacy, array( 'id' => 530, 'provider_id' => 'evt-forged-attach', 'version' => 0 ) ) );
	} ),
);
$matrix_false_paths = array();
foreach ( $false_route_rows as $matrix_route_row ) {
	$matrix_false_paths[] = $matrix_route_row['route'];
}
$matrix_absent = static function ( $route ) use ( $matrix_false_paths ) {
	return array(
		'entered'    => false,
		'registered' => in_array( $route, $matrix_false_paths, true ),
		'bindings'   => 0,
		'ledger'     => array( 'zoom' ),
	);
};
$matrix_false = array(
	'collection' => array( 'entered' => false ),
	'zoom' => array( 'entered' => false ),
	'item' => array( 'entered' => false ),
	'create' => array( 'entered' => false ),
	'synced_patch' => array( 'entered' => false ),
	'unlinked_patch' => array( 'entered' => false ),
	'delete' => array( 'entered' => false ),
	'duplicate' => array( 'entered' => false ),
	'inspect' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/reconcile' ),
	'retry' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/reconcile' ),
	'resolve_owner' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/reconcile' ),
	'abandon_unknown' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/reconcile' ),
	'uncertain' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/attach/uncertain-create' ),
	'legacy_attach' => $matrix_absent( '/live-lessons/(?P<id>[\d]+)/attach/legacy' ),
);
$matrix_names = static function ( $route, $method ) use ( $true_route_rows ) {
	foreach ( $true_route_rows as $route_row ) {
		if ( $route !== $route_row['route'] ) {
			continue;
		}
		foreach ( $route_row['endpoints'] as $endpoint ) {
			if ( $method !== $endpoint['method'] ) {
				continue;
			}
			$names = array();
			foreach ( $endpoint['schema'] as $field ) {
				$names[] = $field['name'];
			}
			return $names;
		}
	}
	return null;
};
$matrix_identity = array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' );
$matrix_attach_schema = array();
foreach ( $attach_registered as $matrix_attach_row ) {
	$matrix_attach_schema[ $matrix_attach_row['route'] ] = $matrix_attach_row['schema'];
}
$matrix_conflict = $matrix_schedule;
$matrix_conflict['startDateTime'] = '2026-10-06 16:00:00';
$matrix_patch_conflict = $matrix_patch( 611 );
$matrix_patch_conflict['startDateTime'] = '2026-10-06 16:00:00';
$matrix_create_identity = $matrix_schedule;
$matrix_create_identity['provider_id'] = 'evt-forged-reject';
$matrix_patch_identity = $matrix_patch( 611 );
$matrix_patch_identity['provider_id'] = 'evt-forged-reject';
$matrix_delete_google = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_item ) {
	$controller->stage_script = array( 'kept' => true );
	return $controller->delete_item( $matrix_request( 'DELETE', $matrix_item, array( 'id' => 530, 'provider_id' => 'evt-forged-delete' ) ) );
} );
$matrix_delete_zoom = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_item ) {
	$controller->stage_script = array( 'kept' => true );
	return $controller->delete_item( $matrix_request( 'DELETE', $matrix_item, array( 'id' => 528, 'provider_id' => 'evt-forged-delete' ) ) );
} );
$matrix_duplicate = $matrix_call( true, false, static function () use ( $controller, $matrix_request ) {
	return $controller->duplicate_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/duplicate', array( 'id' => 530, 'topic_id' => 40, 'provider_id' => 'evt-forged-duplicate' ) ) );
} );
$matrix_attach_google = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_uncertain ) {
	return $controller->attach_item( $matrix_request( 'POST', $matrix_uncertain, array( 'id' => 530, 'provider_id' => 'evt-forged-attach', 'version' => 1, 'operation_id' => 'op-1' ) ) );
} );
$matrix_prior_sync = $controller->sync_rows[530];
$matrix_attach_envelope = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_uncertain, $sync_row, $sync_bytes ) {
	$controller->sync_rows[530] = $sync_row( 530, $sync_bytes( 'synced', array( 'provider' => 'zoom' ) ) );
	$result = $controller->attach_item( $matrix_request( 'POST', $matrix_uncertain, array( 'id' => 530, 'provider_id' => 'evt-forged-attach', 'version' => 1, 'operation_id' => 'op-1' ) ) );
	return $result;
} );
$controller->sync_rows[530] = $matrix_prior_sync;
$matrix_attach_zoom = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_uncertain ) {
	return $controller->attach_item( $matrix_request( 'POST', $matrix_uncertain, array( 'id' => 521, 'provider_id' => 'evt-forged-attach', 'version' => 1, 'operation_id' => 'op-1' ) ) );
} );
$matrix_attach_legacy = $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_legacy ) {
	return $controller->attach_item( $matrix_request( 'POST', $matrix_legacy, array( 'id' => 530, 'provider_id' => 'evt-forged-attach', 'version' => 0 ) ) );
} );
$matrix = array(
	'denied'   => $matrix_denied,
	'false'    => $matrix_false,
	'schemas'  => array(
		'create'    => $matrix_names( '/live-lessons', 'POST' ),
		'patch'     => $matrix_names( '/live-lessons/(?P<id>[\d]+)', 'PATCH' ),
		'delete'    => $matrix_names( '/live-lessons/(?P<id>[\d]+)', 'DELETE' ),
		'duplicate' => $matrix_names( '/live-lessons/(?P<id>[\d]+)/duplicate', 'POST' ),
		'reconcile' => $matrix_names( '/live-lessons/(?P<id>[\d]+)/reconcile', 'POST' ),
		'attach'    => $matrix_attach_schema,
	),
	'identity' => array( 'names' => $matrix_identity ),
	'conflict' => array(
		'create' => $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_conflict ) {
			return $controller->mutate_schedule( $matrix_request( 'POST', '/live-lessons', $matrix_conflict ) );
		} ),
		'patch'  => $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_patch_conflict, $matrix_item ) {
			return $controller->mutate_schedule( $matrix_request( 'PATCH', $matrix_item, $matrix_patch_conflict ) );
		} ),
	),
	'supplied' => array(
		'create'    => $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_create_identity ) {
			return $controller->mutate_schedule( $matrix_request( 'POST', '/live-lessons', $matrix_create_identity ) );
		} ),
		'patch'     => $matrix_call( true, false, static function () use ( $controller, $matrix_request, $matrix_patch_identity, $matrix_item ) {
			return $controller->mutate_schedule( $matrix_request( 'PATCH', $matrix_item, $matrix_patch_identity ) );
		} ),
		'reconcile' => $matrix_call( true, false, static function () use ( $controller, $matrix_request ) {
			return $controller->reconcile_item( $matrix_request( 'POST', '/live-lessons/(?P<id>[\d]+)/reconcile', array( 'id' => 530, 'action' => 'inspect', 'version' => 1, 'operation_id' => 'op-1', 'provider_id' => 'evt-forged-reject' ) ) );
		} ),
	),
	'ignored'  => array(
		'delete_google' => $matrix_delete_google,
		'delete_zoom'   => $matrix_delete_zoom,
		'duplicate'     => $matrix_duplicate,
	),
	'attach'   => array(
		'google'   => $matrix_attach_google,
		'envelope' => $matrix_attach_envelope,
		'zoom'     => $matrix_attach_zoom,
		'legacy'   => $matrix_attach_legacy,
	),
);
$controller->gate       = true;
$controller->broad_only = false;
$google_route = static function ( $rows ) {
	foreach ( $rows as $route_row ) {
		if ( '/live-lessons/google-meet/settings' !== $route_row['route'] ) {
			continue;
		}
		$endpoint = isset( $route_row['endpoints'][0] ) ? $route_row['endpoints'][0] : array();
		$callback = str_replace( "\0", '', isset( $endpoint['callback'] ) ? $endpoint['callback'] : '' );
		return array(
			'method'   => isset( $endpoint['method'] ) ? $endpoint['method'] : '',
			'callback' => '' === $callback ? '' : substr( $callback, (int) strrpos( $callback, '::' ) + 2 ),
			'schema'   => isset( $endpoint['schema'] ) ? $endpoint['schema'] : array(),
		);
	}
	return null;
};
$google_script = array(
	'is_authorized' => true,
	'ready'         => true,
	'preferences'   => array( 'zone' => 'UTC' ),
);
$google->settings_script = $google_script;
$pack_google = static function ( $request ) use ( $controller, $google, $zoom, $pack_result, $named_calls ) {
	$result = $controller->get_google_meet_settings( $request );
	return array(
		'packed'        => $pack_result( $result ),
		'calls'         => $controller->calls,
		'ledger'        => $controller->adapter_resolutions,
		'services'      => $named_calls( $controller->calls, 'service' ),
		'bindings'      => $controller->binding_runs,
		'adapters'      => $controller->adapter_runs,
		'settings'      => $google->settings_calls,
		'zoom_settings' => $zoom->settings_calls,
		'preflight'     => $google->preflight_calls,
		'hosts'         => $google->host_calls,
	);
};
$begin_google = static function ( $params, $gate, $actor = 77, $broad = false ) use ( $controller, $google, $zoom ) {
	$controller->gate                = $gate;
	$controller->actor               = $actor;
	$controller->broad_only          = $broad;
	$controller->can_results         = null;
	$controller->calls               = array();
	$controller->adapter_resolutions = array( 'zoom' );
	$controller->binding_runs        = 0;
	$controller->adapter_runs        = 0;
	$google->settings_calls          = array();
	$zoom->settings_calls            = array();
	$google->preflight_calls         = array();
	$zoom->preflight_calls           = array();
	$google->host_calls              = array();
	$zoom->host_calls                = array();
	$request                         = new WP_REST_Request();
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return $request;
};
$google_secrets = array(
	'course_id'  => 15,
	'token'      => 'supplied-token-value',
	'secret'     => 'supplied-secret-value',
	'credential' => 'supplied-credential-value',
	'password'   => 'supplied-password-value',
	'api_key'    => 'supplied-api-key-value',
	'note'       => 'hidden-sentinel',
);
$false_google = array( 'entered' => false );
$invalid_google_values = array(
	array(),
	array( 'course_id' => 0 ),
	array( 'course_id' => -4 ),
	array( 'course_id' => '0' ),
	array( 'course_id' => '00' ),
	array( 'course_id' => '-3' ),
	array( 'course_id' => '15x' ),
	array( 'course_id' => '15 ' ),
	array( 'course_id' => 15.5 ),
	array( 'course_id' => true ),
	array( 'course_id' => array( 15 ) ),
	array( 'course_id' => null ),
);
$invalid_google = array();
foreach ( $invalid_google_values as $params ) {
	$invalid_google[] = $pack_google( $begin_google( $params, true ) );
}
$missing_google      = $pack_google( $begin_google( array( 'course_id' => 99 ), true ) );
$wrong_google        = $pack_google( $begin_google( array( 'course_id' => 16 ), true ) );
$broad_google        = $pack_google( $begin_google( array( 'course_id' => 15 ), true, 77, true ) );
$authorized_google   = $pack_google( $begin_google( $google_secrets, true, 77, false ) );
$google->settings_script = null;
$null_settings_google = $pack_google( $begin_google( array( 'course_id' => 15 ), true, 77, false ) );
$google->settings_script = $google_script;
$null_owner_google = $pack_google( $begin_google( array( 'course_id' => 15 ), true, 0, false ) );
$google_settings_body = $method_body( 'read_google_settings' );
$google_handler_body  = $method_body( 'get_google_meet_settings' );
$google_marks         = array( 'authorize_course', 'TutorPress_Live_Lesson_Owner_Resolver::google', 'null === $owner_id', "provider_adapter('google_meet')", 'settings($owner_id)', 'null === $settings' );
$google_order         = true;
$google_cursor        = -1;
foreach ( $google_marks as $google_mark ) {
	$google_at      = strpos( $google_settings_body, $google_mark );
	$google_order   = $google_order && false !== $google_at && $google_at > $google_cursor;
	$google_cursor  = false === $google_at ? $google_cursor : $google_at;
}
$google_read_proof = $google_order && false !== strpos( $google_settings_body, "'edit_post' === \$capability" ) && false !== strpos( $google_settings_body, 'format_response' );
foreach ( array( 'GoogleEvent', 'get_user_meta', 'get_current_user_id', 'maybe_unserialize', 'admin_url', 'error_log', 'preflight(', 'edit_posts' ) as $google_needle ) {
	$google_read_proof = $google_read_proof && false === strpos( $google_settings_body, $google_needle );
}
$google_guard_proof = false === strpos( $google_handler_body, 'replacement_enabled' )
	&& false !== strpos( $google_handler_body, '$this->read_google_settings(' )
	&& false === strpos( $google_handler_body, 'request_bindings' )
	&& false === strpos( $google_handler_body, 'provider_adapter' );
foreach ( array( 'GoogleEvent', 'get_user_meta', 'get_current_user_id', 'maybe_unserialize', 'admin_url', 'error_log', 'default_settings', 'google_meet_settings_error' ) as $google_handler_needle ) {
	$google_guard_proof = $google_guard_proof && false === strpos( $google_handler_body, $google_handler_needle );
}
$controller->gate       = true;
$controller->broad_only = false;
$controller->actor      = 44;
$google_false_permission = '';
foreach ( $false_route_rows as $google_false_row ) {
	if ( '/live-lessons/google-meet/settings' !== $google_false_row['route'] ) {
		continue;
	}
	$google_false_endpoint   = isset( $google_false_row['endpoints'][0] ) ? $google_false_row['endpoints'][0] : array();
	$google_false_recorded   = isset( $google_false_endpoint['permission_callback'] ) ? (string) $google_false_endpoint['permission_callback'] : '';
	$google_false_mark       = strrpos( $google_false_recorded, '::' );
	$google_false_permission = false === $google_false_mark ? '' : substr( $google_false_recorded, $google_false_mark + 2 );
}
$google_settings = array(
	'false_schema'  => $google_route( $false_route_rows ),
	'false_permission' => $google_false_permission,
	'true_schema'   => $google_route( $true_route_rows ),
	'false_gate'    => $false_google,
	'invalid'       => $invalid_google,
	'missing'       => $missing_google,
	'wrong_type'    => $wrong_google,
	'broad'         => $broad_google,
	'authorized'    => $authorized_google,
	'null_settings' => $null_settings_google,
	'null_owner'    => $null_owner_google,
	'script'        => $google_script,
	'guard'         => $google_guard_proof,
	'order'         => $google_read_proof,
);
$marker_saved = $controller->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'];
$marker_idle  = static function () { return null; };
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'] = new TutorPress_Google_Meet_Provider_Adapter( $marker_idle, $marker_idle, $marker_idle, $marker_idle, $marker_idle );
$zoom_saved = $controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'];
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] = new TutorPress_Zoom_Provider_Adapter( $marker_idle, $marker_idle, $marker_idle, $marker_idle, $marker_idle, $marker_idle, $marker_idle );
$controller->meta[0]  = array( 'tutor-google-meet-event-details' => '{"id":"evt-copy"}' );
$controller->meta[91] = $controller->meta[0];
$controller->meta[92] = array( 'tutor-google-meet-event-details' => '{"id":"evt-empty"}' );
$controller->meta[93] = array( 'tutor-google-meet-event-details' => '{}' );
$controller->meta[94] = array( 'tutor-google-meet-event-details' => '{"id":"evt-one"}' );
$controller->meta[95] = $controller->meta[94];
$controller->meta[96] = array( 'tutor-google-meet-event-details' => '{"id":"evt-like"}' );
$controller->meta[98] = array( 'tutor-google-meet-event-details' => '{"id":"evt-many"}' );
$controller->meta[99] = $controller->meta[98];
$controller->meta[100] = array( '_tutor_zm_data' => '{"id":"88001"}' );
$controller->meta[101] = $controller->meta[100];
$controller->sync_rows[0]  = array();
$controller->sync_rows[91] = array();
$controller->sync_rows[92] = array( array( 'meta_id' => 1, 'meta_value' => '', 'post_id' => 92 ) );
$controller->sync_rows[93] = array();
$controller->sync_rows[94] = array( array( 'meta_id' => 1, 'meta_value' => 'https://meet.example/one', 'post_id' => 94 ) );
$controller->sync_rows[95] = array();
$controller->sync_rows[96] = array( array( 'meta_id' => 1, 'meta_value' => 'https://meet.example/like', 'post_id' => 96 ) );
$controller->sync_rows[97] = array( array( 'meta_id' => 1, 'meta_value' => 'https://meet.example/other', 'post_id' => 97 ) );
$controller->sync_rows[98] = array( array( 'meta_id' => 1, 'meta_value' => 'https://meet.example/many', 'post_id' => 98 ) );
$controller->sync_rows[99] = array( array( 'meta_id' => 1, 'meta_value' => 'https://meet.example/many-b', 'post_id' => 99 ) );
$controller->sync_rows[100] = array();
$controller->sync_rows[101] = array();
$GLOBALS['wpdb'] = new class() {
	public function __call( $name, $args ) { unset( $name, $args ); throw new RuntimeException( 'queried' ); }
};
$marker = array(
	'copy'    => $controller->provider_linked( 91, 'tutor-google-meet' ),
	'empty'   => null,
	'missing' => $controller->provider_linked( 93, 'tutor-google-meet' ),
	'zero'    => $controller->provider_linked( 0, 'tutor-google-meet' ),
	'zoom'    => $controller->provider_linked( 91, 'tutor_zoom_meeting' ),
);
$GLOBALS['wpdb'] = new class() {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $calls    = array();
	public $rows     = array();
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	public function prepare( $query, ...$args ) { unset( $query ); $this->calls[] = $args; return 'prepared'; }
	public function get_results( $query = '' ) { unset( $query ); return $this->rows; }
};
$marker['empty'] = $controller->provider_linked( 92, 'tutor-google-meet' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 101, 'meta_value' => '{"id":"88001"}' ) );
$marker['zoom_shared'] = $controller->provider_linked( 100, 'tutor_zoom_meeting' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 100, 'meta_value' => '{"id":"88001"}' ) );
$marker['zoom_other'] = $controller->provider_linked( 101, 'tutor_zoom_meeting' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 97, 'meta_value' => '{"id":"evt-like-x"}' ) );
$marker['like'] = $controller->provider_linked( 96, 'tutor-google-meet' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 95, 'meta_value' => '{"id":"evt-one"}' ) );
$marker['source'] = $controller->provider_linked( 94, 'tutor-google-meet' );
$marker['copy_shared'] = $controller->provider_linked( 95, 'tutor-google-meet' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 99, 'meta_value' => '{"id":"evt-many"}' ) );
$marker['many'] = $controller->provider_linked( 98, 'tutor-google-meet' );
$GLOBALS['wpdb']->rows = array( (object) array( 'post_id' => 98, 'meta_value' => '{"id":"evt-many"}' ) );
$marker['many_other'] = $controller->provider_linked( 99, 'tutor-google-meet' );
$marker_args = $GLOBALS['wpdb']->calls;
unset( $GLOBALS['wpdb'] );
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:google_meet'] = $marker_saved;
$controller->services['TutorPress_Live_Lesson_Provider_Adapter:zoom'] = $zoom_saved;
echo json_encode(
	array(
		'enabled'        => true,
		'files'          => count( tutorpress_live_lessons_production_files() ),
		'false_null'     => null === $false_bindings,
		'false_calls'    => $false_calls,
		'keys'           => is_array( $bindings ) ? array_keys( $bindings ) : array(),
		'built'          => $built,
		'found'          => $found,
		'found_keys'     => is_array( $found ) ? array_keys( $found ) : array(),
		'missing'        => $missing,
		'posts'          => $posts,
		'children'       => $children,
		'queries'        => $queries,
		'allowed'        => $allowed,
		'denied'         => $denied,
		'caps'           => $caps,
		'clock_class'    => is_object( $clock ) ? get_class( $clock ) : '',
		'clock_zone'     => $clock instanceof DateTimeImmutable ? $clock->getTimezone()->getName() : '',
		'token'          => $token,
		'user_id'        => is_array( $bindings ) ? $bindings['user_id'] : null,
		'user_type'      => is_array( $bindings ) ? gettype( $bindings['user_id'] ) : '',
		'binding_resolutions' => $binding_resolutions,
		'storage_calls'  => $storage_calls,
		'storage_resolutions' => $storage_resolutions,
		'topic_query'    => isset( $topic_calls[0][1] ) ? $topic_calls[0][1] : null,
		'topic_call_count' => count( $topic_calls ),
		'topic_rows'     => $topic_rows,
		'topic_ledger'   => $topic_ledger,
		'envelopes'      => $envelopes,
		'envelope_calls' => $envelope_calls,
		'envelope_ledger' => $envelope_ledger,
		'meta_unknown' => $unknown,
		'meta_unknown_calls' => $unknown_calls,
		'meta_empty' => $empty_meta,
		'meta_empty_keys' => $empty_keys,
		'meta_malformed' => $malformed,
		'meta_scalar' => $scalar_meta,
		'meta_google' => $google_meta,
		'meta_google_keys' => $google_keys,
		'meta_zoom' => $zoom_meta,
		'meta_zoom_keys' => $zoom_keys,
		'meta_zoom_bad' => $zoom_bad,
		'meta_zoom_bad_keys' => $zoom_bad_keys,
		'meta_before' => $meta_before,
		'meta_after' => $meta_after,
		'meta_service' => $meta_service,
		'meta_boundary_before' => $meta_boundary_before,
		'meta_boundary_after' => $meta_boundary_after,
		'meta_proof' => array(
			'delegate' => false !== strpos( $metadata_body, 'load_provider_meta' ) && false === strpos( $metadata_body, 'load_post_meta' ) && false === strpos( $metadata_body, 'get_post_meta' ) && false === strpos( $metadata_body, 'provider_adapter' ) && false === strpos( $metadata_body, 'load_service' ) && false === strpos( $metadata_body, 'metadata_map' ) && false === strpos( $metadata_body, 'hydrate_item' ) && false === strpos( $metadata_body, 'hydrate_legacy' ),
			'provider' => false === strpos( $provider_meta_body, 'get_post_meta' ) && false === strpos( $provider_meta_body, 'provider_adapter' ) && false === strpos( $provider_meta_body, 'load_service' ) && false === strpos( $provider_meta_body, 'metadata_map' ) && false === strpos( $provider_meta_body, 'hydrate_item' ) && false === strpos( $provider_meta_body, 'hydrate_legacy' ),
			'post'     => false !== strpos( $post_meta_body, 'get_post_meta' ) && 1 === substr_count( $post_meta_body, 'get_post_meta' ),
			'strip'    => false !== strpos( $strip_body, '/token|secret|credential|password|api_key/i' ),
		),
		'option_value'   => $option_value,
		'option_miss'    => $option_miss,
		'takeover'       => $takeover,
		'read_rows'      => $read_rows,
		'updated'        => $updated,
		'affected'       => $affected,
		'affected_type'  => gettype( $affected ),
		'written'        => $written,
		'written_type'   => gettype( $written ),
		'rewritten'      => $rewritten,
		'write_rows'     => $stored_rows,
		'google_same'    => $google_adapter === $google,
		'zoom_same'      => $zoom_adapter === $zoom,
		'valid_calls'    => $valid_calls,
		'foreign_null'   => null === $foreign,
		'foreign_calls'  => $foreign_calls,
		'invalid'        => $invalid,
		'invalid_calls'  => $invalid_calls,
		'before_invalid' => $before_invalid,
		'ledger'         => $ledger,
		'proof'          => array(
			'option'   => false !== strpos( $option_body, 'get_option' ) && false === strpos( $option_body, 'update_option' ) && false === strpos( $option_body, 'wpdb' ),
			'takeover' => false !== strpos( $takeover_body, '::compare_option_update' ) && false === strpos( $takeover_body, '::compare_meta_update' ) && false === strpos( $takeover_body, 'wpdb' ),
			'read'     => false !== strpos( $read_body, '::meta_rows' ) && false === strpos( $read_body, 'wpdb' ),
			'update'   => false !== strpos( $update_body, '::compare_meta_update' ) && false === strpos( $update_body, '::compare_option_update' ) && false === strpos( $update_body, 'wpdb' ),
			'affected' => false !== strpos( $affected_body, '::last_affected' ) && false === strpos( $affected_body, 'wpdb' ),
			'write'    => false !== strpos( $write_body, 'update_post_meta' ) && false !== strpos( $write_body, 'wp_slash' ) && false === strpos( $write_body, 'load_sync_meta' ) && false === strpos( $write_body, '_tutorpress_live_lesson_sync' ) && false === strpos( $write_body, 'add_post_meta' ) && false === strpos( $write_body, 'wpdb' ),
			'service'  => false !== strpos( $service_body, 'TutorPress_Service_Container::instance()->get' ),
			'adapter'  => false !== strpos( $adapter_body, 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ) && false !== strpos( $adapter_body, 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ) && false !== strpos( $adapter_body, 'instanceof TutorPress_Live_Lesson_Provider_Adapter' ),
			'seam'     => false === strpos( $seam, 'provider_adapter' ) && false === strpos( $seam, 'load_service' ) && false === strpos( $seam, 'add_post_meta' ),
		),
		'definitions'    => substr_count( implode( '', is_array( $file ) ? $file : array() ), 'function request_bindings(' ),
		'handler_calls'  => $handler_calls,
		'touched'        => $touched,
		'provider'       => TutorPress_Live_Lessons_Boundary_Fake::$ledger,
		'resolved'       => TutorPress_Live_Lessons_Service_Fake::$resolved,
		'permissions'    => TutorPress_Live_Lessons_Rest_Fake::$permissions,
		'invalid_reads'  => $invalid_reads,
		'missing_course' => $missing_course,
		'wrong_type'     => $wrong_type,
		'denied_course'  => $denied_course,
		'authorized'     => $authorized,
		'filtered'       => $filtered,
		'null_query'     => $null_query,
		'read_proof'     => $read_proof,
		'false_item'     => $false_item,
		'invalid_items'  => $invalid_items,
		'missing_item'   => $missing_item,
		'wrong_item_type' => $wrong_item_type,
		'missing_topic'  => $missing_topic,
		'wrong_topic'    => $wrong_topic,
		'missing_course_item' => $missing_course_item,
		'wrong_course_item' => $wrong_course_item,
		'broken_item_parent' => $broken_item_parent,
		'broken_course_parent' => $broken_course_parent,
		'denied_item'    => $denied_item,
		'draft_item'     => $draft_item,
		'private_item'   => $private_item,
		'rejected_item'  => $rejected_item,
		'legacy_item'    => $legacy_item,
		'pending_item'   => $pending_item,
		'error_item'     => $error_item,
		'pending_meta_item' => $pending_meta_item,
		'error_meta_item' => $error_meta_item,
		'unlinked_item'  => $unlinked_item,
		'unlinked_meta_item' => $unlinked_meta_item,
		'synced_item'     => $synced_item,
		'synced_meta_item' => $synced_meta_item,
		'google_legacy_item' => $google_legacy_item,
		'unprojected_item' => $unprojected_item,
		'item_guard_proof' => $item_guard_proof,
		'item_read_proof' => $item_read_proof,
		'zoom_users' => $zoom_users,
		'outcomes' => $outcomes,
		'creates'  => $creates,
		'updates'  => $updates,
		'deletes'  => $deletes,
		'duplicates' => $duplicates,
		'reconciles' => $reconcile_cases,
		'attaches' => $attach_cases,
		'matrix'   => $matrix,
		'google_settings' => $google_settings,
		'marker' => $marker,
		'marker_args' => $marker_args,
	)
);
PHP
);
tutorpress_live_lessons_assert(
	0 === $binding_probe['status'] && '' === $binding_probe['err'] && is_array( $binding_probe['data'] ),
	'request binding probe stays isolated ' . $binding_probe['status'] . ' ' . $binding_probe['err'] . ' ' . substr( (string) $binding_probe['out'], 0, 400 )
);
tutorpress_live_lessons_assert(
	isset( $binding_probe['data']['marker'] )
	&& array( 'copy' => false, 'empty' => true, 'missing' => true, 'zero' => true, 'zoom' => true, 'zoom_shared' => false, 'zoom_other' => false, 'like' => true, 'source' => true, 'copy_shared' => false, 'many' => false, 'many_other' => false ) === $binding_probe['data']['marker'],
	'google copy marker unlinks only an event id with no link row'
);
tutorpress_live_lessons_assert(
	isset( $binding_probe['data']['marker_args'] )
	&& array( array( 'tutor-google-meet', 'tutor-google-meet-event-details', 92, '%evt-empty%' ), array( 'tutor_zoom_meeting', '_tutor_zm_data', 100, '%88001%' ), array( 'tutor_zoom_meeting', '_tutor_zm_data', 101, '%88001%' ), array( 'tutor-google-meet', 'tutor-google-meet-event-details', 96, '%evt-like%' ), array( 'tutor-google-meet', 'tutor-google-meet-event-details', 94, '%evt-one%' ), array( 'tutor-google-meet', 'tutor-google-meet-event-details', 98, '%evt-many%' ), array( 'tutor-google-meet', 'tutor-google-meet-event-details', 99, '%evt-many%' ) ) === $binding_probe['data']['marker_args'],
	'google shared-id query binds the event id for the lesson that has a link row'
);
$canonical_keys = array( 'ID', 'post_type', 'post_status', 'post_parent', 'post_author', 'post_title', 'post_content', 'menu_order' );
$child_types    = array( 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor-google-meet', 'tutor_zoom_meeting' );
tutorpress_live_lessons_assert(
	true === $binding_probe['data']['enabled']
	&& 17 === $binding_probe['data']['files']
	&& true === $binding_probe['data']['false_null']
	&& array() === $binding_probe['data']['false_calls']
	&& 1 === $binding_probe['data']['definitions']
	&& array() === $binding_probe['data']['handler_calls'],
	'false-gate binding is null before loaders and handlers do not call it'
);
tutorpress_live_lessons_assert(
	array( 'lookup', 'children', 'topics', 'can', 'clock', 'token', 'user_id', 'option', 'takeover', 'read', 'envelope', 'metadata', 'update', 'affected', 'write' ) === $binding_probe['data']['keys']
	&& array( 'clock', 'token', 'user' ) === $binding_probe['data']['built']
	&& $canonical_keys === $binding_probe['data']['found_keys']
	&& array(
		'ID'           => 8,
		'post_type'    => 'courses',
		'post_status'  => 'publish',
		'post_parent'  => 0,
		'post_author'  => 17,
		'post_title'   => 'Lab',
		'post_content' => 'Notes',
		'menu_order'   => 3,
	) === $binding_probe['data']['found']
	&& null === $binding_probe['data']['missing']
	&& array( 8, 99 ) === $binding_probe['data']['posts'],
	'enabled lookup returns only canonical post arrays and null for a missing post'
);
tutorpress_live_lessons_assert(
	array(
		array(
			'post_parent'    => 4,
			'post_type'      => $child_types,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		),
	) === $binding_probe['data']['queries']
	&& $child_types === $binding_probe['data']['queries'][0]['post_type']
	&& array(
		array(
			'ID'           => 21,
			'post_type'    => 'lesson',
			'post_status'  => 'draft',
			'post_parent'  => 4,
			'post_author'  => 17,
			'post_title'   => 'Lesson',
			'post_content' => 'Body',
			'menu_order'   => 1,
		),
		array(
			'ID'           => 22,
			'post_type'    => 'tutor_zoom_meeting',
			'post_status'  => 'publish',
			'post_parent'  => 4,
			'post_author'  => 17,
			'post_title'   => 'Zoom',
			'post_content' => 'Meet',
			'menu_order'   => 2,
		),
		null,
	) === $binding_probe['data']['children'],
	'child lookup queries only the five topic post types and canonicalizes every child'
);
tutorpress_live_lessons_assert(
	true === $binding_probe['data']['allowed']
	&& false === $binding_probe['data']['denied']
	&& array( array( 'edit_post', 15 ), array( 'edit_post', 16 ) ) === $binding_probe['data']['caps']
	&& 'DateTimeImmutable' === $binding_probe['data']['clock_class']
	&& 'UTC' === $binding_probe['data']['clock_zone']
	&& 1 === preg_match( '/^[0-9a-f]{32}$/', (string) $binding_probe['data']['token'] )
	&& 44 === $binding_probe['data']['user_id']
	&& 'integer' === $binding_probe['data']['user_type'],
	'capability, clock, token, and user id use the injected runtime fixtures'
);
tutorpress_live_lessons_assert(
	array() === $binding_probe['data']['binding_resolutions']
	&& array() === $binding_probe['data']['storage_resolutions']
	&& array(
		array( 'option', 'tutorpress_live_lesson_lock_8' ),
		array( 'option', 'tutorpress_live_lesson_lock_9' ),
		array( 'takeover', 'tutorpress_live_lesson_lock_8', 'next-bytes', 'prior-bytes' ),
		array( 'read', 8, '_tutorpress_live_lesson_sync' ),
		array( 'update', 8, '_tutorpress_live_lesson_sync', 31, 'next-meta', 'prior-meta' ),
		array( 'affected' ),
		array( 'write', 8, '_tutor_zm_data', wp_slash( '{"id":"z","url":"https://zoom.example/j"}' ) ),
		array( 'write', 8, '_tutor_zm_data', wp_slash( '{"id":"z","url":"https://zoom.example/j"}' ) ),
	) === $binding_probe['data']['storage_calls']
	&& 'lock-bytes' === $binding_probe['data']['option_value']
	&& null === $binding_probe['data']['option_miss']
	&& true === $binding_probe['data']['takeover']
	&& array( array( 'meta_id' => 31, 'meta_value' => 'prior-meta', 'post_id' => 8 ) ) === $binding_probe['data']['read_rows']
	&& true === $binding_probe['data']['updated']
	&& 1 === $binding_probe['data']['affected']
	&& 'integer' === $binding_probe['data']['affected_type']
	&& 64 === $binding_probe['data']['written']
	&& 'integer' === $binding_probe['data']['written_type']
	&& false === $binding_probe['data']['rewritten']
	&& array(
		array(
			'post_id'    => 8,
			'meta_key'   => '_tutor_zm_data',
			'meta_value' => wp_slash( '{"id":"z","url":"https://zoom.example/j"}' ),
		),
	) === $binding_probe['data']['write_rows']
	&& true === $binding_probe['data']['proof']['option']
	&& true === $binding_probe['data']['proof']['takeover']
	&& true === $binding_probe['data']['proof']['read']
	&& true === $binding_probe['data']['proof']['update']
	&& true === $binding_probe['data']['proof']['affected']
	&& true === $binding_probe['data']['proof']['write']
	&& true === $binding_probe['data']['proof']['seam'],
	'storage closures hit only their overridden loaders'
);
tutorpress_live_lessons_assert(
	true === $binding_probe['data']['google_same']
	&& true === $binding_probe['data']['zoom_same']
	&& array(
		array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ),
		array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ),
	) === $binding_probe['data']['valid_calls']
	&& true === $binding_probe['data']['foreign_null']
	&& array(
		array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ),
	) === $binding_probe['data']['foreign_calls']
	&& array( 'google_meet', 'zoom', 'zoom' ) === $binding_probe['data']['before_invalid']
	&& array( null, null, null, null, null, null, null ) === $binding_probe['data']['invalid']
	&& array() === $binding_probe['data']['invalid_calls']
	&& array( 'google_meet', 'zoom', 'zoom' ) === $binding_probe['data']['ledger']
	&& true === $binding_probe['data']['proof']['service']
	&& true === $binding_probe['data']['proof']['adapter'],
	'provider adapter resolves only canonical interface services'
);
$binding_encoded = json_encode( $binding_probe['data'] );
tutorpress_live_lessons_assert(
	is_string( $binding_encoded )
	&& false === strpos( $binding_encoded, 'fake-credential-value' )
	&& false === strpos( $binding_encoded, 'zoom_token' )
	&& false === strpos( $binding_encoded, 'zoom_credentials' )
	&& false === strpos( $binding_encoded, 'google_calendar' )
	&& false === strpos( $binding_encoded, 'access_token' )
	&& false === strpos( $binding_encoded, 'refresh_token' )
	&& array() === $binding_probe['data']['touched']
	&& array() === $binding_probe['data']['provider']
	&& array() === $binding_probe['data']['resolved']
	&& array() === $binding_probe['data']['permissions']
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& $production_files === tutorpress_live_lessons_production_files()
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Controller', false ),
	'request bindings keep the false gate, eighteen-file loader, and no controller or provider boundary'
);
$topic_status = array( 'publish', 'draft', 'private', 'pending', 'future' );
$envelope_calls = array();
foreach ( array( 80, 81, 82, 83, 84, 90, 91, 92, 93 ) as $envelope_id ) {
	$envelope_calls[] = array( 'read', $envelope_id, '_tutorpress_live_lesson_sync' );
}
$accepted = array( '90' => 'pending', '91' => 'error', '92' => 'unlinked', '93' => 'synced' );
$accepted_ok = true;
foreach ( $accepted as $envelope_id => $state ) {
	$row = isset( $binding_probe['data']['envelopes'][ $envelope_id ] ) ? $binding_probe['data']['envelopes'][ $envelope_id ] : null;
	$accepted_ok = $accepted_ok && is_array( $row ) && $state === $row['state'] && $row === TutorPress_Live_Lesson_Sync_Envelope::envelope( $row );
}
tutorpress_live_lessons_assert(
	1 === $binding_probe['data']['topic_call_count']
	&& array(
		'post_parent'    => 7,
		'post_type'      => array( 'topics' ),
		'post_status'    => $topic_status,
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	) === $binding_probe['data']['topic_query']
	&& array(
		array(
			'ID'           => 11,
			'post_type'    => 'topics',
			'post_status'  => 'draft',
			'post_parent'  => 7,
			'post_author'  => 17,
			'post_title'   => 'Topic',
			'post_content' => 'Outline',
			'menu_order'   => 4,
		),
		null,
	) === $binding_probe['data']['topic_rows']
	&& array() === $binding_probe['data']['topic_ledger']
	&& null === $binding_probe['data']['envelopes']['80']
	&& false === $binding_probe['data']['envelopes']['81']
	&& false === $binding_probe['data']['envelopes']['82']
	&& false === $binding_probe['data']['envelopes']['83']
	&& false === $binding_probe['data']['envelopes']['84']
	&& $accepted_ok
	&& $envelope_calls === $binding_probe['data']['envelope_calls']
	&& array() === $binding_probe['data']['envelope_ledger'],
	'course topics and canonical envelopes stay behind overridable loaders'
);
$google_meta_keys = array(
	'tutor-google-meet-start-datetime',
	'tutor-google-meet-end-datetime',
	'tutor-google-meet-event-details',
	'tutor-google-meet-link',
);
$zoom_meta_keys = array(
	'_tutor_zm_start_date',
	'_tutor_zm_start_datetime',
	'_tutor_zm_duration',
	'_tutor_zm_duration_unit',
	'_tutor_zm_for_course',
	'_tutor_zm_for_topic',
	'_tutor_zm_data',
);
$sensitive_present = static function ( $value ) use ( &$sensitive_present ) {
	if ( ! is_array( $value ) ) {
		return false;
	}
	foreach ( $value as $key => $item ) {
		if ( is_string( $key ) && 1 === preg_match( '/token|secret|credential|password|api_key/i', $key ) ) {
			return true;
		}
		if ( $sensitive_present( $item ) ) {
			return true;
		}
	}
	return false;
};
tutorpress_live_lessons_assert(
	null === $binding_probe['data']['meta_unknown']
	&& array() === $binding_probe['data']['meta_unknown_calls']
	&& null === $binding_probe['data']['meta_empty']
	&& $google_meta_keys === $binding_probe['data']['meta_empty_keys']
	&& $google_meta_keys === $binding_probe['data']['meta_google_keys']
	&& $zoom_meta_keys === $binding_probe['data']['meta_zoom_keys']
	&& $zoom_meta_keys === $binding_probe['data']['meta_zoom_bad_keys']
	&& array(
		'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
		'tutor-google-meet-link'           => 'https://meet.example/abc',
	) === $binding_probe['data']['meta_malformed']
	&& array(
		'tutor-google-meet-link' => 'https://meet.example/scalar',
	) === $binding_probe['data']['meta_scalar']
	&& array(
		'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
		'tutor-google-meet-event-details'  => array(
			'id'        => 'evt-1',
			'safe_link' => 'https://meet.example/join',
			'nested'    => array(
				'title' => 'Safe title',
			),
		),
		'tutor-google-meet-link'           => 'https://meet.example/abc',
	) === $binding_probe['data']['meta_google']
	&& array(
		'_tutor_zm_start_date'     => '2026-10-06',
		'_tutor_zm_start_datetime' => '2026-10-06 18:00:00',
		'_tutor_zm_duration'       => 90,
		'_tutor_zm_duration_unit'  => 'min',
		'_tutor_zm_for_course'     => 7,
		'_tutor_zm_for_topic'      => 4,
		'_tutor_zm_data'           => array(
			'id'       => 'zm-1',
			'timezone' => 'UTC',
			'meeting'  => array(
				'host_id' => 'host-9',
			),
		),
	) === $binding_probe['data']['meta_zoom']
	&& array( '_tutor_zm_start_date' => '2026-10-06' ) === $binding_probe['data']['meta_zoom_bad']
	&& false === $sensitive_present( $binding_probe['data']['meta_google'] )
	&& false === $sensitive_present( $binding_probe['data']['meta_zoom'] )
	&& $binding_probe['data']['meta_before'] === $binding_probe['data']['meta_after']
	&& array() === $binding_probe['data']['meta_service']
	&& $binding_probe['data']['meta_boundary_before'] === $binding_probe['data']['meta_boundary_after']
	&& array() === $binding_probe['data']['meta_boundary_after']
	&& true === $binding_probe['data']['meta_proof']['delegate']
	&& true === $binding_probe['data']['meta_proof']['provider']
	&& true === $binding_probe['data']['meta_proof']['post']
	&& true === $binding_probe['data']['meta_proof']['strip'],
	'local provider metadata stays behind overridable loaders'
);

tutorpress_live_lessons_pass( 'request bindings expose canonical runtime seams' );

tutorpress_live_lessons_pass( 'storage bindings and provider adapter stay behind overridable loaders' );

tutorpress_live_lessons_pass( 'course topics and canonical envelopes stay behind overridable loaders' );

tutorpress_live_lessons_pass( 'local provider metadata stays behind overridable loaders' );

$collection_query = static function ( $parent, $types ) use ( $topic_status ) {
	return array(
		'post_parent'    => $parent,
		'post_type'      => $types,
		'post_status'    => $topic_status,
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	);
};
$authorized_calls   = array(
	'clock',
	'token',
	'user',
	array( 'post', 15 ),
	array( 'can', 'edit_post', 15 ),
	array( 'posts', $collection_query( 15, array( 'topics' ) ) ),
	array( 'posts', $collection_query( 40, $child_types ) ),
);
foreach ( array( 201, 202, 203, 204, 205, 206 ) as $envelope_id ) {
	$authorized_calls[] = array( 'read', $envelope_id, '_tutorpress_live_lesson_sync' );
}
$authorized_calls[] = array( 'posts', $collection_query( 41, $child_types ) );
$authorized_calls[] = array( 'read', 212, '_tutorpress_live_lesson_sync' );
$authorized_calls[] = array( 'can', 'edit_post', 15 );
$collection_rows    = array(
	array( 'id' => 201, 'courseId' => 15, 'topicId' => 40, 'type' => 'google_meet', 'status' => 'scheduled', 'sync_state' => 'pending' ),
	array( 'id' => 202, 'courseId' => 15, 'topicId' => 40, 'type' => 'zoom', 'status' => 'scheduled', 'sync_state' => 'error' ),
	array( 'id' => 203, 'courseId' => 15, 'topicId' => 40, 'type' => 'google_meet', 'status' => 'scheduled', 'sync_state' => 'unlinked' ),
	array( 'id' => 204, 'courseId' => 15, 'topicId' => 40, 'type' => 'zoom', 'status' => 'scheduled', 'sync_state' => 'synced' ),
	array( 'id' => 205, 'courseId' => 15, 'topicId' => 40, 'type' => 'google_meet', 'status' => 'scheduled', 'sync_state' => 'error' ),
	array( 'id' => 206, 'courseId' => 15, 'topicId' => 40, 'type' => 'zoom', 'status' => 'scheduled', 'sync_state' => 'error' ),
	array( 'id' => 212, 'courseId' => 15, 'topicId' => 41, 'type' => 'zoom', 'status' => 'scheduled', 'sync_state' => 'synced' ),
);
$collection_page = array(
	'success' => true,
	'message' => 'Live lessons retrieved successfully.',
	'data'    => array(
		'data'       => $collection_rows,
		'pagination' => array( 'total' => 7, 'page' => 1, 'per_page' => 10, 'total_pages' => 1 ),
	),
);
$filtered_page = array(
	'success' => true,
	'message' => 'Live lessons retrieved successfully.',
	'data'    => array(
		'data'       => array(
			array( 'id' => 204, 'courseId' => 15, 'topicId' => 40, 'type' => 'zoom', 'status' => 'scheduled', 'sync_state' => 'synced' ),
		),
		'pagination' => array( 'total' => 3, 'page' => 2, 'per_page' => 1, 'total_pages' => 3 ),
	),
);
$invalid_ok = 12 === count( $binding_probe['data']['invalid_reads'] );
foreach ( $binding_probe['data']['invalid_reads'] as $invalid_read ) {
	$invalid_ok = $invalid_ok
		&& 'error' === $invalid_read['packed']['kind']
		&& 'invalid_course_id' === $invalid_read['packed']['code']
		&& 'Invalid course ID.' === $invalid_read['packed']['message']
		&& 404 === $invalid_read['packed']['status']
		&& array( 'clock', 'token', 'user' ) === $invalid_read['calls']
		&& array( 'zoom' ) === $invalid_read['ledger']
		&& array() === $invalid_read['services']
		&& array() === $invalid_read['metas'];
}
tutorpress_live_lessons_assert(
	$invalid_ok,
	'invalid course ids fail before lookup'
);
$lookup_error = static function ( $read, $calls ) {
	return 'error' === $read['packed']['kind']
		&& 'invalid_course_id' === $read['packed']['code']
		&& 'Invalid course ID.' === $read['packed']['message']
		&& 404 === $read['packed']['status']
		&& $calls === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services']
		&& array() === $read['metas'];
};
tutorpress_live_lessons_assert(
	$lookup_error( $binding_probe['data']['missing_course'], array( 'clock', 'token', 'user', array( 'post', 99 ) ) )
	&& $lookup_error( $binding_probe['data']['wrong_type'], array( 'clock', 'token', 'user', array( 'post', 16 ) ) ),
	'missing and non-course lookups use invalid_course_id before capability checks'
);
tutorpress_live_lessons_assert(
	'error' === $binding_probe['data']['denied_course']['packed']['kind']
	&& 'rest_cannot_read' === $binding_probe['data']['denied_course']['packed']['code']
	&& 'Sorry, you are not allowed to view live lessons for this course.' === $binding_probe['data']['denied_course']['packed']['message']
	&& 401 === $binding_probe['data']['denied_course']['packed']['status']
	&& array( 'clock', 'token', 'user', array( 'post', 8 ), array( 'can', 'edit_post', 8 ) ) === $binding_probe['data']['denied_course']['calls']
	&& array( 'zoom' ) === $binding_probe['data']['denied_course']['ledger']
	&& array() === $binding_probe['data']['denied_course']['services']
	&& array() === $binding_probe['data']['denied_course']['metas'],
	'denied course authorization stops before topics, children, envelopes, and metadata'
);
tutorpress_live_lessons_assert(
	$authorized_calls === $binding_probe['data']['authorized']['calls']
	&& $collection_page === $binding_probe['data']['authorized']['packed']['data']
	&& 'response' === $binding_probe['data']['authorized']['packed']['kind']
	&& array( 'zoom' ) === $binding_probe['data']['authorized']['ledger']
	&& array() === $binding_probe['data']['authorized']['services']
	&& array() === $binding_probe['data']['authorized']['metas']
	&& $authorized_calls === $binding_probe['data']['filtered']['calls']
	&& $filtered_page === $binding_probe['data']['filtered']['packed']['data']
	&& array( 'zoom' ) === $binding_probe['data']['filtered']['ledger']
	&& array() === $binding_probe['data']['filtered']['services']
	&& array() === $binding_probe['data']['filtered']['metas'],
	'authorized collection keeps every publish sync state and passes only collection arguments'
);
tutorpress_live_lessons_assert(
	'error' === $binding_probe['data']['null_query']['packed']['kind']
	&& 'rest_cannot_read' === $binding_probe['data']['null_query']['packed']['code']
	&& 'Sorry, you are not allowed to view live lessons for this course.' === $binding_probe['data']['null_query']['packed']['message']
	&& 401 === $binding_probe['data']['null_query']['packed']['status']
	&& $authorized_calls === $binding_probe['data']['null_query']['calls']
	&& array( 'zoom' ) === $binding_probe['data']['null_query']['ledger']
	&& array() === $binding_probe['data']['null_query']['services']
	&& array() === $binding_probe['data']['null_query']['metas']
	&& true === $binding_probe['data']['read_proof'],
	'a null collection query stays unprojected and read_items does not resolve an adapter'
);
tutorpress_live_lessons_pass( 'authorized collection reads delegate without an adapter' );

$item_boot   = array( 'clock', 'token', 'user' );
$item_posts  = static function ( $ids ) use ( $item_boot ) {
	$calls = $item_boot;
	foreach ( $ids as $id ) {
		$calls[] = array( 'post', $id );
	}
	return $calls;
};
$item_chain  = static function ( $item, $topic, $course ) use ( $item_posts ) {
	$calls   = $item_posts( array( $item, $topic, $course ) );
	$calls[] = array( 'can', 'edit_post', $course );
	return $calls;
};
$item_reads  = static function ( $id, $keys ) use ( $item_chain ) {
	$calls   = $item_chain( $id, 40, 15 );
	$calls[] = array( 'read', $id, '_tutorpress_live_lesson_sync' );
	foreach ( $keys as $key ) {
		$calls[] = array( 'meta', $id, $key );
	}
	$calls[] = array( 'can', 'edit_post', 15 );
	return $calls;
};
$item_error  = static function ( $read, $code, $message, $status, $calls ) {
	return 'error' === $read['packed']['kind']
		&& $code === $read['packed']['code']
		&& $message === $read['packed']['message']
		&& $status === $read['packed']['status']
		&& $calls === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services'];
};
$item_row    = static function ( $read ) {
	$data = isset( $read['packed']['data'] ) ? $read['packed']['data'] : null;
	return is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : null;
};
$item_kept   = static function ( $read, $calls ) use ( $item_row ) {
	$data = isset( $read['packed']['data'] ) ? $read['packed']['data'] : null;
	return 'response' === $read['packed']['kind']
		&& is_array( $data )
		&& true === $data['success']
		&& 'Live lesson retrieved successfully.' === $data['message']
		&& is_array( $item_row( $read ) )
		&& $calls === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services'];
};
$operation_target = array(
	'title'           => 'Operation title',
	'content'         => 'Operation notes',
	'start_local'     => '2026-09-21 15:04:00',
	'end_local'       => '2026-09-21 16:04:00',
	'timezone'        => 'UTC',
	'duration'        => array( 'value' => 60, 'unit' => 'minutes' ),
	'provider_target' => array(
		'auto_recording'    => 'none',
		'enforce_login'     => false,
		'host_id'           => 'host-1',
		'host_video'        => false,
		'join_before_host'  => true,
		'mute_upon_entry'   => false,
		'participant_video' => false,
		'waiting_room'      => true,
	),
);
$operation_summary = array(
	'operation_id'   => 'op-1',
	'kind'           => 'create',
	'dispatch_state' => 'not_dispatched',
);
$sanitized_meta = array(
	'_tutor_zm_start_date' => '2026-10-06',
	'_tutor_zm_data'       => array(
		'id'      => 'zm-9',
		'host_id' => 'host-9',
	),
);
$invalid_item_ok = 15 === count( $binding_probe['data']['invalid_items'] );
foreach ( $binding_probe['data']['invalid_items'] as $invalid_item ) {
	$invalid_item_ok = $invalid_item_ok && $item_error( $invalid_item, 'live_lesson_not_found', 'Live lesson not found.', 404, $item_boot );
}
$legacy_shape = static function ( $row, $id, $type ) {
	return is_array( $row )
		&& $id === $row['id']
		&& 'Stored title' === $row['title']
		&& 'Stored notes' === $row['description']
		&& $type === $row['type']
		&& 40 === $row['topicId']
		&& 15 === $row['courseId']
		&& 'scheduled' === $row['status']
		&& 'error' === $row['sync_state']
		&& 0 === $row['sync_version']
		&& false === $row['persisted_envelope']
		&& array( 'legacy_resolution_required' ) === $row['resolution']
		&& null === $row['record'];
};
$stored_shape = static function ( $row, $id, $state, $record ) use ( $operation_summary ) {
	return is_array( $row )
		&& $id === $row['id']
		&& 'Stored title' === $row['title']
		&& 'zoom' === $row['type']
		&& $state === $row['sync_state']
		&& 1 === $row['sync_version']
		&& true === $row['persisted_envelope']
		&& null === $row['resolution']
		&& $operation_summary === $row['operation']
		&& $record === $row['record'];
};
$zoom_calls   = static function ( $id ) use ( $item_reads, $zoom_meta_keys ) {
	return $item_reads( $id, $zoom_meta_keys );
};
$google_calls = $item_reads( 530, $google_meta_keys );
$rejected_calls = $item_chain( 520, 40, 15 );
$rejected_calls[] = array( 'read', 520, '_tutorpress_live_lesson_sync' );
tutorpress_live_lessons_assert(
	false === $binding_probe['data']['false_item']['entered']
	&& $invalid_item_ok
	&& true === $binding_probe['data']['item_guard_proof']
	&& true === $binding_probe['data']['item_read_proof'],
	'false-gate item call stays absent and invalid ids fail before lookup'
);
tutorpress_live_lessons_assert(
	$item_error( $binding_probe['data']['missing_item'], 'live_lesson_not_found', 'Live lesson not found.', 404, $item_posts( array( 501 ) ) )
	&& $item_error( $binding_probe['data']['wrong_item_type'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400, $item_posts( array( 502 ) ) ),
	'missing and wrong-type items stop before the topic lookup'
);
tutorpress_live_lessons_assert(
	$item_error( $binding_probe['data']['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 503, 590 ) ) )
	&& $item_error( $binding_probe['data']['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 504, 505 ) ) ),
	'missing and wrong topics stop before the course lookup and capability'
);
tutorpress_live_lessons_assert(
	$item_error( $binding_probe['data']['missing_course_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 506, 507, 591 ) ) )
	&& $item_error( $binding_probe['data']['wrong_course_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 508, 509, 510 ) ) )
	&& $item_error( $binding_probe['data']['broken_item_parent'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 511, 512, 15 ) ) )
	&& $item_error( $binding_probe['data']['broken_course_parent'], 'invalid_topic', 'Invalid topic for this live lesson.', 404, $item_posts( array( 513, 514, 515 ) ) ),
	'missing courses and broken parent relations stop before capability, envelope, and metadata'
);
tutorpress_live_lessons_assert(
	$item_error( $binding_probe['data']['denied_item'], 'rest_cannot_read', 'Sorry, you are not allowed to view this live lesson.', 401, $item_chain( 516, 517, 18 ) )
	&& $item_error( $binding_probe['data']['draft_item'], 'live_lesson_not_found', 'Live lesson not found.', 404, $item_chain( 518, 40, 15 ) )
	&& $item_error( $binding_probe['data']['private_item'], 'live_lesson_not_found', 'Live lesson not found.', 404, $item_chain( 519, 40, 15 ) )
	&& $item_error( $binding_probe['data']['rejected_item'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409, $rejected_calls ),
	'authorization, non-publish, and rejected envelopes stop before metadata'
);
$pending_row = $item_row( $binding_probe['data']['pending_item'] );
$error_row   = $item_row( $binding_probe['data']['error_item'] );
$pending_meta_row = $item_row( $binding_probe['data']['pending_meta_item'] );
$error_meta_row   = $item_row( $binding_probe['data']['error_meta_item'] );
$unlinked_row = $item_row( $binding_probe['data']['unlinked_item'] );
$unlinked_meta_row = $item_row( $binding_probe['data']['unlinked_meta_item'] );
$synced_row = $item_row( $binding_probe['data']['synced_item'] );
$synced_meta_row = $item_row( $binding_probe['data']['synced_meta_item'] );
tutorpress_live_lessons_assert(
	$item_kept( $binding_probe['data']['legacy_item'], $zoom_calls( 521 ) )
	&& $legacy_shape( $item_row( $binding_probe['data']['legacy_item'] ), 521, 'zoom' )
	&& $item_kept( $binding_probe['data']['google_legacy_item'], $google_calls )
	&& $legacy_shape( $item_row( $binding_probe['data']['google_legacy_item'] ), 530, 'google_meet' )
	&& $item_kept( $binding_probe['data']['pending_item'], $zoom_calls( 522 ) )
	&& $stored_shape( $pending_row, 522, 'pending', $operation_target )
	&& $item_kept( $binding_probe['data']['error_item'], $zoom_calls( 523 ) )
	&& $stored_shape( $error_row, 523, 'error', $operation_target )
	&& $item_kept( $binding_probe['data']['pending_meta_item'], $zoom_calls( 524 ) )
	&& $stored_shape( $pending_meta_row, 524, 'pending', $sanitized_meta )
	&& $operation_target !== $pending_meta_row['record']
	&& $item_kept( $binding_probe['data']['error_meta_item'], $zoom_calls( 525 ) )
	&& $stored_shape( $error_meta_row, 525, 'error', $sanitized_meta )
	&& $item_kept( $binding_probe['data']['unlinked_item'], $zoom_calls( 526 ) )
	&& $stored_shape( $unlinked_row, 526, 'unlinked', null )
	&& $item_kept( $binding_probe['data']['unlinked_meta_item'], $zoom_calls( 527 ) )
	&& $stored_shape( $unlinked_meta_row, 527, 'unlinked', $sanitized_meta )
	&& $item_kept( $binding_probe['data']['synced_item'], $zoom_calls( 528 ) )
	&& $stored_shape( $synced_row, 528, 'synced', null )
	&& $item_kept( $binding_probe['data']['synced_meta_item'], $zoom_calls( 529 ) )
	&& $stored_shape( $synced_meta_row, 529, 'synced', $sanitized_meta )
	&& false === $sensitive_present( $pending_meta_row['record'] )
	&& false === $sensitive_present( $error_meta_row['record'] )
	&& false === $sensitive_present( $unlinked_meta_row['record'] )
	&& false === $sensitive_present( $synced_meta_row['record'] )
	&& $item_error( $binding_probe['data']['unprojected_item'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409, $zoom_calls( 531 ) ),
	'authorized publish items project legacy absence and accepted sync states without an adapter'
);
tutorpress_live_lessons_pass( 'authorized item reads project without an adapter' );

$zoom_boot    = array( 'clock', 'token', 'user' );
$zoom_service = array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:zoom' );
$zoom_hosts   = array(
	'outcome' => 'hosts',
	'hosts'   => array(
		array(
			'id'         => 'host-1',
			'name'       => 'Ada Lovelace',
			'email'      => 'ada@example.com',
			'first_name' => 'kept-extra',
		),
	),
);
$zoom_page = array(
	'success' => true,
	'message' => 'Zoom users retrieved successfully.',
	'data'    => $zoom_hosts,
);
$zoom_calls = static function ( $course ) use ( $zoom_boot, $zoom_service ) {
	return array(
		$zoom_boot[0],
		$zoom_boot[1],
		$zoom_boot[2],
		array( 'post', $course ),
		array( 'can', 'edit_post', $course ),
		$zoom_service,
	);
};
$zoom_stopped = static function ( $read, $calls ) {
	return 'error' === $read['packed']['kind']
		&& $calls === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services']
		&& array() === $read['metas']
		&& array() === $read['reads']
		&& array() === $read['hosts']
		&& array() === $read['preflight'];
};
$zoom_case = $binding_probe['data']['zoom_users'];
$invalid_zoom_ok = 12 === count( $zoom_case['invalid'] );
foreach ( $zoom_case['invalid'] as $invalid_zoom_read ) {
	$invalid_zoom_ok = $invalid_zoom_ok
		&& $zoom_stopped( $invalid_zoom_read, $zoom_boot )
		&& 'invalid_course_id' === $invalid_zoom_read['packed']['code']
		&& 'Invalid course ID.' === $invalid_zoom_read['packed']['message']
		&& 404 === $invalid_zoom_read['packed']['status'];
}
$zoom_kept = static function ( $read, $course, $owner ) use ( $zoom_calls, $zoom_page ) {
	return 'response' === $read['packed']['kind']
		&& $zoom_page === $read['packed']['data']
		&& $zoom_calls( $course ) === $read['calls']
		&& array( 'zoom', 'zoom' ) === $read['ledger']
		&& array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:zoom' ) ) === $read['services']
		&& array() === $read['metas']
		&& array() === $read['reads']
		&& array( $owner ) === $read['hosts']
		&& array() === $read['preflight'];
};
$zoom_encoded = json_encode( $zoom_case );
$zoom_idle    = static function ( $read ) {
	return 'other' === $read['packed']['kind']
		&& null === $read['packed']['data']
		&& false === isset( $read['packed']['code'] )
		&& array() === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services']
		&& array() === $read['metas']
		&& array() === $read['reads']
		&& array() === $read['hosts']
		&& array() === $read['preflight'];
};
tutorpress_live_lessons_assert(
	false === $zoom_case['false_missing']['entered']
	&& false === $zoom_case['false_legacy']['entered']
	&& $invalid_zoom_ok,
	'false-gate Zoom Users calls stay absent and invalid course ids stop before capability'
);
tutorpress_live_lessons_assert(
	$zoom_stopped( $zoom_case['missing'], array( 'clock', 'token', 'user', array( 'post', 99 ) ) )
	&& 'invalid_course_id' === $zoom_case['missing']['packed']['code']
	&& 'Invalid course ID.' === $zoom_case['missing']['packed']['message']
	&& 404 === $zoom_case['missing']['packed']['status']
	&& $zoom_stopped( $zoom_case['wrong_type'], array( 'clock', 'token', 'user', array( 'post', 16 ) ) )
	&& 'invalid_course_id' === $zoom_case['wrong_type']['packed']['code']
	&& $zoom_stopped( $zoom_case['denied'], array( 'clock', 'token', 'user', array( 'post', 8 ), array( 'can', 'edit_post', 8 ) ) )
	&& 'rest_cannot_read' === $zoom_case['denied']['packed']['code']
	&& 'Sorry, you are not allowed to view live lessons for this course.' === $zoom_case['denied']['packed']['message']
	&& 401 === $zoom_case['denied']['packed']['status'],
	'missing, non-course, and unauthorized Zoom Users requests stop before owner resolution'
);
tutorpress_live_lessons_assert(
	$zoom_kept( $zoom_case['author'], 15, 17 )
	&& $zoom_kept( $zoom_case['author_string'], 15, 17 )
	&& $zoom_kept( $zoom_case['fallback_missing'], 63, 55 )
	&& $zoom_kept( $zoom_case['fallback_invalid'], 65, 55 )
	&& $zoom_stopped( $zoom_case['unresolved'], array( 'clock', 'token', 'user', array( 'post', 64 ), array( 'can', 'edit_post', 64 ) ) )
	&& 'zoom_owner_unavailable' === $zoom_case['unresolved']['packed']['code']
	&& 'A Zoom owner could not be resolved for this course.' === $zoom_case['unresolved']['packed']['message']
	&& 400 === $zoom_case['unresolved']['packed']['status']
	&& true === $zoom_case['guard']
	&& true === $zoom_case['order']
	&& 0 === $zoom_case['built']
	&& $zoom_case['built'] === $zoom_case['legacy_built']
	&& $zoom_case['meta'] === $zoom_case['legacy_meta']
	&& $zoom_case['switches'] === $zoom_case['legacy_switches']
	&& true === $zoom_case['boundary']
	&& array() === $zoom_case['google_hosts']
	&& is_string( $zoom_encoded )
	&& false === strpos( $zoom_encoded, 'api_key' )
	&& false === strpos( $zoom_encoded, 'api_secret' )
	&& false === strpos( $zoom_encoded, 'configured-key' )
	&& false === strpos( $zoom_encoded, 'configured-secret' )
	&& false === strpos( $zoom_encoded, 'tutor_zoom_api' )
	&& false === strpos( $zoom_encoded, 'google_meet' ),
	'authorized Zoom Users resolve one owner and preserve the normalized host list'
);
tutorpress_live_lessons_pass( 'authorized zoom users delegate to the owner-scoped adapter' );

$outcome = $binding_probe['data']['outcomes'];
$outcome_quiet = static function ( $row ) {
	return array() === $row['calls']
		&& array() === $row['posts']
		&& array() === $row['services']
		&& array() === $row['metas']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array( 'zoom' ) === $row['ledger']
		&& true === $row['boundary'];
};
$outcome_held = static function ( $row ) {
	return array() === $row['services']
		&& array() === $row['metas']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array( 'zoom' ) === $row['ledger']
		&& true === $row['boundary'];
};
$outcome_error = static function ( $row, $code, $message, $status ) {
	return 'error' === $row['packed']['kind']
		&& $code === $row['packed']['code']
		&& $message === $row['packed']['message']
		&& $status === $row['packed']['status'];
};
$invalid_outcome_ok = 5 === count( $outcome['invalid'] );
foreach ( $outcome['invalid'] as $invalid_outcome ) {
	$invalid_outcome_ok = $invalid_outcome_ok && $outcome_quiet( $invalid_outcome ) && $outcome_error( $invalid_outcome, 'invalid_live_lesson', 'Invalid live lesson ID.', 404 );
}
$duplicate_chain = static function ( $id, $project = true ) {
	$calls = array( 'clock', 'token', 'user', array( 'post', $id ), array( 'post', 40 ), array( 'post', 15 ), array( 'can', 'edit_post', 15 ), array( 'read', $id, '_tutorpress_live_lesson_sync' ) );
	if ( $project ) {
		$calls[] = array( 'can', 'edit_post', 15 );
	}
	return $calls;
};
$outcome_encoded = json_encode( $outcome );
tutorpress_live_lessons_assert(
	true === $outcome['proof']
	&& true === $outcome['error']['same']
	&& $outcome_quiet( $outcome['error'] )
	&& $outcome_error( $outcome['error'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& $outcome_quiet( $outcome['null_create'] )
	&& $outcome_error( $outcome['null_create'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& $outcome_quiet( $outcome['null_update'] )
	&& $outcome_error( $outcome['null_update'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& $outcome_quiet( $outcome['null_delete'] )
	&& $outcome_error( $outcome['null_delete'], 'live_lesson_delete_failed', 'Failed to delete live lesson.', 500 )
	&& $outcome_quiet( $outcome['null_duplicate'] )
	&& $outcome_error( $outcome['null_duplicate'], 'duplicate_creation_failed', 'Failed to create duplicate live lesson.', 500 )
	&& $outcome_quiet( $outcome['null_inspect'] )
	&& $outcome_error( $outcome['null_inspect'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $outcome_quiet( $outcome['envelope'] )
	&& 'response' === $outcome['envelope']['packed']['kind']
	&& true === $outcome['envelope']['packed']['data']['success']
	&& 'Live lesson created successfully.' === $outcome['envelope']['packed']['data']['message']
	&& 'synced' === $outcome['envelope']['packed']['data']['data']['state']
	&& 1 === $outcome['envelope']['packed']['data']['data']['version']
	&& 'zoom' === $outcome['envelope']['packed']['data']['data']['provider']
	&& null === $outcome['envelope']['packed']['data']['data']['provider_id']
	&& 11 === count( $outcome['envelope']['packed']['data']['data'] )
	&& $outcome_quiet( $outcome['envelope_update'] )
	&& 'Live lesson retrieved successfully.' === $outcome['envelope_update']['packed']['data']['message']
	&& $outcome_quiet( $outcome['rejected'] )
	&& $outcome_error( $outcome['rejected'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $outcome_quiet( $outcome['delete'] )
	&& 'response' === $outcome['delete']['packed']['kind']
	&& 'Live lesson deleted successfully.' === $outcome['delete']['packed']['data']['message']
	&& array(
		'outcome'     => 'confirmed',
		'provider_id' => 'meet-1',
		'item'        => null,
		'diagnostics' => array(),
		'provenance'  => 'delete',
	) === $outcome['delete']['packed']['data']['data']
	&& $outcome_quiet( $outcome['inspect'] )
	&& 'Request successful.' === $outcome['inspect']['packed']['data']['message']
	&& array(
		'match'  => true,
		'fields' => array(
			'provider_id'      => 'meet-1',
			'start_at'         => '2026-10-07T16:00:00Z',
			'timezone'         => 'UTC',
			'duration_minutes' => 60,
		),
	) === $outcome['inspect']['packed']['data']['data']
	&& $outcome_quiet( $outcome['unsafe_compare'] )
	&& $outcome_error( $outcome['unsafe_compare'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $invalid_outcome_ok
	&& $outcome_quiet( $outcome['duplicate_closed'] )
	&& $outcome_error( $outcome['duplicate_closed'], 'duplicate_data_error', 'Duplicate created but failed to retrieve data.', 500 )
	&& $outcome_held( $outcome['duplicate'] )
	&& $duplicate_chain( 540 ) === $outcome['duplicate']['calls']
	&& 'response' === $outcome['duplicate']['packed']['kind']
	&& true === $outcome['duplicate']['packed']['data']['success']
	&& 'Google Meet lesson duplicated successfully.' === $outcome['duplicate']['packed']['data']['message']
	&& array(
		'id'                 => 540,
		'title'              => 'Stored title',
		'description'        => 'Stored notes',
		'type'               => 'google_meet',
		'topicId'            => 40,
		'courseId'           => 15,
		'status'             => 'scheduled',
		'sync_state'         => 'error',
		'sync_version'       => 0,
		'persisted_envelope' => false,
		'operation'          => null,
		'warning'            => null,
		'error'              => null,
		'resolution'         => array( 'legacy_resolution_required' ),
		'record'             => null,
	) === $outcome['duplicate']['packed']['data']['data']
	&& $outcome_held( $outcome['missing'] )
	&& array( 'clock', 'token', 'user', array( 'post', 900 ) ) === $outcome['missing']['calls']
	&& $outcome_error( $outcome['missing'], 'duplicate_data_error', 'Duplicate created but failed to retrieve data.', 500 )
	&& $outcome_held( $outcome['malformed'] )
	&& array( 'clock', 'token', 'user', array( 'post', 545 ), array( 'post', 507 ), array( 'post', 591 ) ) === $outcome['malformed']['calls']
	&& $outcome_error( $outcome['malformed'], 'duplicate_data_error', 'Duplicate created but failed to retrieve data.', 500 )
	&& $outcome_held( $outcome['unauthorized'] )
	&& array( 'clock', 'token', 'user', array( 'post', 541 ), array( 'post', 517 ), array( 'post', 18 ), array( 'can', 'edit_post', 18 ) ) === $outcome['unauthorized']['calls']
	&& $outcome_error( $outcome['unauthorized'], 'cannot_duplicate_live_lesson', 'You do not have permission to duplicate live lessons.', 403 )
	&& $outcome_held( $outcome['rejected_post'] )
	&& $duplicate_chain( 543, false ) === $outcome['rejected_post']['calls']
	&& $outcome_error( $outcome['rejected_post'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $outcome_held( $outcome['unprojectable'] )
	&& $duplicate_chain( 542 ) === $outcome['unprojectable']['calls']
	&& $outcome_error( $outcome['unprojectable'], 'duplicate_data_error', 'Duplicate created but failed to retrieve data.', 500 )
	&& is_string( $outcome_encoded )
	&& false === strpos( $outcome_encoded, 'fake-credential-value' )
	&& false === strpos( $outcome_encoded, 'access_token' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'REST outcome keeps bare errors and skips provider lifecycle ' . substr( (string) $outcome_encoded, 0, 500 )
);
tutorpress_live_lessons_pass( 'rest outcome preserves bare errors and skips provider lifecycle' );

$creates = isset( $binding_probe['data']['creates'] ) && is_array( $binding_probe['data']['creates'] ) ? $binding_probe['data']['creates'] : array();
$create_input = static function ( $provider ) {
	return array(
		'provider'             => $provider,
		'title'                => 'Weekly lab',
		'content'              => 'Bring notes',
		'start'                => '2026-10-06 15:00:00',
		'end'                  => '2026-10-06 16:00:00',
		'timezone'             => 'UTC',
		'duration'             => array( 'value' => 2, 'unit' => 'hours' ),
		'host_id'              => 'zoom-host-9',
		'settings'             => array( 'waiting_room' => true ),
		'acting_user_id'       => 44,
		'course_author_id'     => 17,
		'acting_instructor_id' => 44,
	);
};
$create_child = array(
	array(
		'ID'           => 91,
		'post_type'    => 'lesson',
		'post_status'  => 'publish',
		'post_parent'  => 71,
		'post_author'  => 17,
		'post_title'   => 'Row 91',
		'post_content' => 'Body',
		'menu_order'   => 1,
	),
);
$create_error = static function ( $row, $code, $message, $status ) {
	return is_array( $row ) && 'error' === $row['packed']['kind'] && $code === $row['packed']['code'] && $message === $row['packed']['message'] && $status === $row['packed']['status'];
};
$create_stopped = static function ( $row ) {
	return is_array( $row ) && array() === $row['stages'] && array() === $row['dispatches'] && 0 === $row['adapters'] && array( 'zoom' ) === $row['ledger'] && array() === $row['services'] && array() === $row['writes'] && array() === $row['updates'] && array() === $row['metas'] && true === $row['boundary'];
};
$create_success = static function ( $row, $provider ) use ( $create_input, $create_child ) {
	$stage    = isset( $row['stages'][0] ) && is_array( $row['stages'][0] ) ? $row['stages'][0] : array();
	$dispatch = isset( $row['dispatches'][0] ) && is_array( $row['dispatches'][0] ) ? $row['dispatches'][0] : array();
	return is_array( $row )
		&& 'response' === $row['packed']['kind']
		&& true === $row['packed']['data']['success']
		&& 'Live lesson created successfully.' === $row['packed']['data']['message']
		&& 3 === $row['packed']['data']['data']['version']
		&& 'synced' === $row['packed']['data']['data']['state']
		&& 1 === count( $row['stages'] )
		&& 1 === count( $row['dispatches'] )
		&& $create_input( $provider ) === $stage['input']
		&& $create_child === $stage['children']
		&& $provider === $stage['adapter']
		&& $provider === $dispatch['adapter']
		&& true === $stage['clock']
		&& is_string( $stage['token'] )
		&& 32 === strlen( $stage['token'] )
		&& $stage['token'] === $dispatch['token']
		&& 71 === $stage['topic']
		&& 15 === $stage['course']
		&& 17 === $stage['author']
		&& array( 'zoom', $provider ) === $row['ledger']
		&& 1 === $row['bindings']
		&& 1 === $row['adapters']
		&& array( array( 'post', 15 ), array( 'post', 71 ) ) === $row['posts']
		&& array( array( 'can', 'edit_post', 15 ) ) === $row['caps']
		&& 1 === count( $row['queries'] )
		&& array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:' . $provider ) ) === $row['services']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array() === $row['metas']
		&& array() === $row['reads']
		&& true === $row['boundary']
		&& is_string( $dispatch['staged']['envelope'] )
		&& 1 === json_decode( $dispatch['staged']['envelope'], true )['version'];
};
$create_encoded = json_encode( $creates );
tutorpress_live_lessons_assert(
	is_array( $creates ) && isset( $creates['false'] )
	&& false === $creates['false']['entered']
	&& true === $creates['legacy']
	&& true === $creates['mutate'],
	'false-gate create stays unentered and mutate_schedule keeps its scan'
);
tutorpress_live_lessons_assert(
	array( 'start_date_time' => '2026-10-06 15:00:00' ) === $creates['incomplete']['array']
	&& 0 === $creates['incomplete']['bindings']
	&& $create_stopped( $creates['incomplete'] )
	&& array(
		'start_date_time' => '2026-10-06 15:00:00',
		'end_date_time'   => '2026-10-06 16:00:00',
		'duration'        => array( 'value' => 2, 'unit' => 'hours' ),
	) === $creates['patch']['array']
	&& 0 === $creates['patch']['bindings']
	&& $create_stopped( $creates['patch'] ),
	'incomplete POST and gate-true PATCH stay on the schedule seam'
);
tutorpress_live_lessons_assert(
	$create_error( $creates['forged'], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
	&& 0 === $creates['forged']['bindings']
	&& array() === $creates['forged']['posts']
	&& $create_stopped( $creates['forged'] )
	&& $create_error( $creates['conflict'], 'live_lesson_schedule_conflict', 'Schedule fields conflict.', 400 )
	&& 0 === $creates['conflict']['bindings']
	&& $create_stopped( $creates['conflict'] )
	&& $create_error( $creates['bad_end'], 'invalid_wall_clock', 'Invalid wall clock.', 400 )
	&& 0 === $creates['bad_end']['bindings']
	&& $create_stopped( $creates['bad_end'] ),
	'invalid schedule and forged identity stop before lookup, adapter resolution, staging, and dispatch'
);
tutorpress_live_lessons_assert(
	$create_error( $creates['missing'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& 1 === $creates['missing']['bindings']
	&& array( array( 'post', 99 ), array( 'post', 71 ) ) === $creates['missing']['posts']
	&& array() === $creates['missing']['caps']
	&& $create_stopped( $creates['missing'] )
	&& $create_error( $creates['wrong_course'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $create_stopped( $creates['wrong_course'] )
	&& $create_error( $creates['wrong_topic'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $create_stopped( $creates['wrong_topic'] )
	&& $create_error( $creates['broken'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& array() === $creates['broken']['caps']
	&& $create_stopped( $creates['broken'] )
	&& $create_error( $creates['zero'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& array( array( 'post', 71 ) ) === $creates['zero']['posts']
	&& $create_stopped( $creates['zero'] )
	&& $create_error( $creates['denied'], 'rest_cannot_create', 'Sorry, you are not allowed to create live lessons in this course.', 401 )
	&& array( array( 'can', 'edit_post', 8 ) ) === $creates['denied']['caps']
	&& array() === $creates['denied']['queries']
	&& $create_stopped( $creates['denied'] ),
	'missing relations and denied edit_post stop before adapter resolution'
);
tutorpress_live_lessons_assert(
	$create_success( $creates['google'], 'google_meet' )
	&& $create_success( $creates['string_ids'], 'google_meet' )
	&& $create_success( $creates['zoom'], 'zoom' )
	&& array( 'read', 'update', 'affected', 'write' ) === $creates['callbacks'],
	'valid Google and Zoom creates stage once, dispatch once, and preserve the create success envelope'
);
tutorpress_live_lessons_assert(
	$create_error( $creates['null_stage'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& 1 === count( $creates['null_stage']['stages'] )
	&& array() === $creates['null_stage']['dispatches']
	&& true === $creates['error_stage']['same_stage']
	&& $create_error( $creates['error_stage'], 'stage_rejected', 'Stage rejected.', 422 )
	&& array() === $creates['error_stage']['dispatches']
	&& $create_error( $creates['malformed'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $creates['malformed']['dispatches']
	&& $create_error( $creates['bad_bytes'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $creates['bad_bytes']['dispatches']
	&& $create_error( $creates['dispatch_null'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& 1 === count( $creates['dispatch_null']['dispatches'] )
	&& true === $creates['dispatch_error']['same_dispatch']
	&& $create_error( $creates['dispatch_error'], 'dispatch_rejected', 'Dispatch rejected.', 409 )
	&& 1 === count( $creates['dispatch_error']['dispatches'] )
	&& $create_error( $creates['dispatch_missing'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& 1 === count( $creates['dispatch_missing']['dispatches'] )
	&& $create_error( $creates['dispatch_bad'], 'live_lesson_creation_failed', 'Failed to create live lesson.', 500 )
	&& 1 === count( $creates['dispatch_bad']['dispatches'] ),
	'null, error, and malformed staging never dispatch and bare outcomes stay unchanged'
);
tutorpress_live_lessons_assert(
	true === $creates['proof']
	&& array( 0, 0 ) === $creates['lifecycle']
	&& is_string( $create_encoded )
	&& false === strpos( $create_encoded, 'hidden-sentinel' )
	&& false === strpos( $create_encoded, 'fake-credential-value' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'create delegation performs no provider, metadata, or direct post lifecycle'
);
tutorpress_live_lessons_pass( 'gate-true create delegates to the orchestrator' );

$updates = isset( $binding_probe['data']['updates'] ) && is_array( $binding_probe['data']['updates'] ) ? $binding_probe['data']['updates'] : array();
$update_input = static function ( $provider ) {
	return array(
		'provider'             => $provider,
		'title'                => 'Weekly lab',
		'content'              => 'Bring notes',
		'start'                => '2026-10-06 15:00:00',
		'end'                  => '2026-10-06 16:00:00',
		'timezone'             => 'UTC',
		'duration'             => array( 'value' => 2, 'unit' => 'hours' ),
		'host_id'              => 'zoom-host-9',
		'settings'             => array( 'waiting_room' => true ),
		'acting_user_id'       => 44,
		'course_author_id'     => 17,
		'acting_instructor_id' => 44,
	);
};
$update_error = static function ( $row, $code, $message, $status ) {
	return is_array( $row ) && 'error' === $row['packed']['kind'] && $code === $row['packed']['code'] && $message === $row['packed']['message'] && $status === $row['packed']['status'];
};
$update_stopped = static function ( $row ) {
	return is_array( $row ) && array() === $row['unlinked'] && array() === $row['synced'] && array() === $row['synced_dispatch'] && array() === $row['stages'] && array() === $row['dispatches'] && 0 === $row['adapters'] && array( 'zoom' ) === $row['ledger'] && array() === $row['services'] && array() === $row['writes'] && array() === $row['updates'] && array() === $row['metas'] && array() === $row['reads'] && true === $row['boundary'];
};
$update_quiet = static function ( $row, $posts, $caps = array() ) use ( $update_stopped ) {
	return $update_stopped( $row ) && $posts === $row['posts'] && $caps === $row['caps'] && 1 === $row['bindings'] && array() === $row['queries'];
};
$update_envelope = static function ( $row, $id ) use ( $update_error ) {
	return $update_error( $row, 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
		&& array( array( 'post', $id ), array( 'post', 40 ), array( 'post', 15 ) ) === $row['posts']
		&& array( array( 'can', 'edit_post', 15 ) ) === $row['caps']
		&& array( array( 'read', $id, '_tutorpress_live_lesson_sync' ) ) === $row['reads']
		&& 1 === $row['bindings']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& array() === $row['services']
		&& array() === $row['unlinked']
		&& array() === $row['synced']
		&& array() === $row['dispatches']
		&& array() === $row['synced_dispatch'];
};
$update_success = static function ( $row, $provider, $item, $post_type, $kind ) use ( $update_input ) {
	$stage    = 'unlinked' === $kind ? ( isset( $row['unlinked'][0] ) ? $row['unlinked'][0] : array() ) : ( isset( $row['synced'][0] ) ? $row['synced'][0] : array() );
	$dispatch = 'unlinked' === $kind ? ( isset( $row['dispatches'][0] ) ? $row['dispatches'][0] : array() ) : ( isset( $row['synced_dispatch'][0] ) ? $row['synced_dispatch'][0] : array() );
	$opposite = 'unlinked' === $kind
		? array() === $row['synced'] && array() === $row['synced_dispatch'] && array() === $row['stages'] && 1 === count( $row['unlinked'] ) && 1 === count( $row['dispatches'] )
		: array() === $row['unlinked'] && array() === $row['dispatches'] && array() === $row['stages'] && 1 === count( $row['synced'] ) && 1 === count( $row['synced_dispatch'] );
	return is_array( $row )
		&& 'response' === $row['packed']['kind']
		&& true === $row['packed']['data']['success']
		&& 'Live lesson retrieved successfully.' === $row['packed']['data']['message']
		&& 3 === $row['packed']['data']['data']['version']
		&& 'synced' === $row['packed']['data']['data']['state']
		&& $update_input( $provider ) === $stage['input']
		&& $provider === $stage['adapter']
		&& $provider === $dispatch['adapter']
		&& $item === $stage['item']
		&& $post_type === $stage['type']
		&& 40 === $stage['topic']
		&& 15 === $stage['course']
		&& true === $stage['clock']
		&& true === $stage['read']
		&& true === $stage['update']
		&& true === $stage['affected']
		&& is_string( $stage['token'] )
		&& 32 === strlen( $stage['token'] )
		&& $stage['token'] === $dispatch['token']
		&& array( 'zoom', $provider ) === $row['ledger']
		&& 1 === $row['bindings']
		&& 1 === $row['adapters']
		&& array( array( 'post', $item ), array( 'post', 40 ), array( 'post', 15 ) ) === $row['posts']
		&& array( array( 'can', 'edit_post', 15 ) ) === $row['caps']
		&& array( array( 'read', $item, '_tutorpress_live_lesson_sync' ) ) === $row['reads']
		&& array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:' . $provider ) ) === $row['services']
		&& array() === $row['queries']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array() === $row['metas']
		&& true === $row['boundary']
		&& true === $opposite
		&& is_string( $dispatch['staged']['envelope'] )
		&& 1 === json_decode( $dispatch['staged']['envelope'], true )['version'];
};
$identity_ok = 5 === count( $updates['identity'] );
foreach ( $updates['identity'] as $identity_row ) {
	$identity_ok = $identity_ok && $update_error( $identity_row, 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 ) && 0 === $identity_row['bindings'] && array() === $identity_row['posts'] && $update_stopped( $identity_row );
}
$update_encoded = json_encode( $updates );
tutorpress_live_lessons_assert(
	is_array( $updates ) && isset( $updates['false'] )
	&& false === $updates['false']['entered']
	&& true === $updates['proof'],
	'false-gate update stays unentered and mutate_schedule keeps its scan'
);
tutorpress_live_lessons_assert(
	array(
		'start_date_time' => '2026-10-06 15:00:00',
		'end_date_time'   => '2026-10-06 16:00:00',
		'duration'        => array( 'value' => 2, 'unit' => 'hours' ),
	) === $updates['false_mutate']['array']
	&& 0 === $updates['false_mutate']['bindings']
	&& $update_stopped( $updates['false_mutate'] )
	&& array(
		'start_date_time' => '2026-10-06 15:00:00',
		'end_date_time'   => '2026-10-06 16:00:00',
		'duration'        => array( 'value' => 2, 'unit' => 'hours' ),
	) === $updates['plain']['array']
	&& 0 === $updates['plain']['bindings']
	&& $update_stopped( $updates['plain'] )
	&& array( 'start_date_time' => '2026-10-06 15:00:00' ) === $updates['zero']['array']
	&& 0 === $updates['zero']['bindings']
	&& $update_stopped( $updates['zero'] ),
	'false-gate and incomplete PATCH stay on the schedule seam'
);
tutorpress_live_lessons_assert(
	$identity_ok
	&& $update_error( $updates['conflict'], 'live_lesson_schedule_conflict', 'Schedule fields conflict.', 400 )
	&& 0 === $updates['conflict']['bindings']
	&& $update_stopped( $updates['conflict'] )
	&& $update_error( $updates['bad_end'], 'invalid_wall_clock', 'Invalid wall clock.', 400 )
	&& 0 === $updates['bad_end']['bindings']
	&& $update_stopped( $updates['bad_end'] ),
	'invalid schedule and forged identity stop before lookup, envelope, adapter, staging, and dispatch'
);
tutorpress_live_lessons_assert(
	$update_error( $updates['missing'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $update_quiet( $updates['missing'], array( array( 'post', 501 ) ) )
	&& $update_error( $updates['wrong_type'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400 )
	&& $update_quiet( $updates['wrong_type'], array( array( 'post', 502 ) ) )
	&& $update_error( $updates['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['missing_topic'], array( array( 'post', 503 ), array( 'post', 590 ) ) )
	&& $update_error( $updates['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['wrong_topic'], array( array( 'post', 504 ), array( 'post', 505 ) ) )
	&& $update_error( $updates['missing_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['missing_course'], array( array( 'post', 506 ), array( 'post', 507 ), array( 'post', 591 ) ) )
	&& $update_error( $updates['wrong_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['wrong_course'], array( array( 'post', 508 ), array( 'post', 509 ), array( 'post', 510 ) ) )
	&& $update_error( $updates['broken_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['broken_item'], array( array( 'post', 511 ), array( 'post', 512 ), array( 'post', 15 ) ) )
	&& $update_error( $updates['broken_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $update_quiet( $updates['broken_course'], array( array( 'post', 513 ), array( 'post', 514 ), array( 'post', 515 ) ) )
	&& $update_error( $updates['denied'], 'rest_cannot_edit', 'Sorry, you are not allowed to edit this live lesson.', 401 )
	&& $update_quiet( $updates['denied'], array( array( 'post', 516 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) )
	&& $update_error( $updates['draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $update_quiet( $updates['draft'], array( array( 'post', 518 ), array( 'post', 40 ), array( 'post', 15 ) ), array( array( 'can', 'edit_post', 15 ) ) )
	&& $update_error( $updates['private'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $update_quiet( $updates['private'], array( array( 'post', 519 ), array( 'post', 40 ), array( 'post', 15 ) ), array( array( 'can', 'edit_post', 15 ) ) ),
	'missing relations, non-publish items, and denied edit_post stop before envelope and adapter resolution'
);
tutorpress_live_lessons_assert(
	$update_envelope( $updates['rejected'], 520 )
	&& $update_envelope( $updates['absent'], 521 )
	&& $update_envelope( $updates['pending'], 522 )
	&& $update_envelope( $updates['error_state'], 523 ),
	'rejected, absent, pending, and error envelopes do not resolve an adapter'
);
tutorpress_live_lessons_assert(
	$update_success( $updates['zoom_unlinked'], 'zoom', 526, 'tutor_zoom_meeting', 'unlinked' )
	&& $update_success( $updates['google_unlinked'], 'google_meet', 610, 'tutor-google-meet', 'unlinked' )
	&& $update_success( $updates['zoom_synced'], 'zoom', 528, 'tutor_zoom_meeting', 'synced' )
	&& $update_success( $updates['google_synced'], 'google_meet', 611, 'tutor-google-meet', 'synced' )
	&& array( 'read', 'update', 'affected', 'write' ) === $updates['callbacks'],
	'canonical unlinked and synced items use only their matching stage and dispatch pair'
);
tutorpress_live_lessons_assert(
	$update_error( $updates['null_stage'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& 1 === count( $updates['null_stage']['unlinked'] )
	&& array() === $updates['null_stage']['dispatches']
	&& array() === $updates['null_stage']['synced']
	&& true === $updates['error_stage']['same_stage']
	&& $update_error( $updates['error_stage'], 'stage_rejected', 'Stage rejected.', 422 )
	&& array() === $updates['error_stage']['dispatches']
	&& $update_error( $updates['malformed'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $updates['malformed']['dispatches']
	&& $update_error( $updates['bad_bytes'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $updates['bad_bytes']['dispatches']
	&& $update_error( $updates['dispatch_null'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& 1 === count( $updates['dispatch_null']['dispatches'] )
	&& array() === $updates['dispatch_null']['synced_dispatch']
	&& true === $updates['dispatch_error']['same_dispatch']
	&& $update_error( $updates['dispatch_error'], 'dispatch_rejected', 'Dispatch rejected.', 409 )
	&& 1 === count( $updates['dispatch_error']['dispatches'] )
	&& $update_error( $updates['dispatch_missing'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& 1 === count( $updates['dispatch_missing']['dispatches'] )
	&& $update_error( $updates['dispatch_bad'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& 1 === count( $updates['dispatch_bad']['dispatches'] )
	&& $update_error( $updates['synced_null'], 'live_lesson_update_failed', 'Failed to update live lesson.', 500 )
	&& 1 === count( $updates['synced_null']['synced'] )
	&& array() === $updates['synced_null']['synced_dispatch']
	&& array() === $updates['synced_null']['unlinked']
	&& array() === $updates['synced_null']['dispatches'],
	'null, error, and noncanonical staging never dispatch and bare update outcomes stay unchanged'
);
tutorpress_live_lessons_assert(
	true === $updates['proof']
	&& array( 0, 0 ) === $updates['lifecycle']
	&& is_string( $update_encoded )
	&& false === strpos( $update_encoded, 'hidden-sentinel' )
	&& false === strpos( $update_encoded, 'fake-credential-value' )
	&& false === strpos( $update_encoded, 'edit_posts' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'update delegation performs no provider, metadata, or direct post lifecycle'
);
tutorpress_live_lessons_pass( 'gate-true patch delegates by the canonical sync state' );

$deletes = isset( $binding_probe['data']['deletes'] ) && is_array( $binding_probe['data']['deletes'] ) ? $binding_probe['data']['deletes'] : array();
$delete_error = static function ( $row, $code, $message, $status ) {
	return is_array( $row ) && 'error' === $row['packed']['kind'] && $code === $row['packed']['code'] && $message === $row['packed']['message'] && $status === $row['packed']['status'];
};
$delete_stopped = static function ( $row ) {
	return is_array( $row ) && array() === $row['deletes'] && array() === $row['stages'] && array() === $row['dispatches'] && array() === $row['unlinked'] && array() === $row['synced'] && array() === $row['synced_dispatch'] && 0 === $row['adapters'] && array( 'zoom' ) === $row['ledger'] && array() === $row['services'] && array() === $row['writes'] && array() === $row['updates'] && array() === $row['metas'] && array() === $row['reads'] && true === $row['boundary'];
};
$delete_quiet = static function ( $row, $posts, $caps = array() ) use ( $delete_stopped ) {
	return $delete_stopped( $row ) && $posts === $row['posts'] && $caps === $row['caps'] && 1 === $row['bindings'] && array() === $row['queries'];
};
$delete_context = array(
	'acting_user_id'       => 44,
	'course_author_id'     => 17,
	'acting_instructor_id' => 44,
);
$delete_once = static function ( $row, $provider, $item, $post_type ) use ( $delete_context ) {
	$stage = isset( $row['deletes'][0] ) && is_array( $row['deletes'][0] ) ? $row['deletes'][0] : array();
	return is_array( $row )
		&& 1 === count( $row['deletes'] )
		&& array() === $row['stages']
		&& array() === $row['dispatches']
		&& array() === $row['unlinked']
		&& array() === $row['synced']
		&& array() === $row['synced_dispatch']
		&& $delete_context === $stage['input']
		&& $provider === $stage['adapter']
		&& $item === $stage['item']
		&& $post_type === $stage['item_type']
		&& 'publish' === $stage['item_status']
		&& 40 === $stage['topic']
		&& 'topics' === $stage['topic_type']
		&& 15 === $stage['course']
		&& 'courses' === $stage['course_type']
		&& 17 === $stage['author']
		&& false === $stage['edit_posts']
		&& true === $stage['clock']
		&& true === $stage['read']
		&& true === $stage['update']
		&& true === $stage['affected']
		&& is_string( $stage['token'] )
		&& 32 === strlen( $stage['token'] )
		&& array( 'zoom', $provider ) === $row['ledger']
		&& 1 === $row['bindings']
		&& 1 === $row['adapters']
		&& array( array( 'post', $item ), array( 'post', 40 ), array( 'post', 15 ) ) === $row['posts']
		&& array( array( 'can', 'edit_post', 15 ) ) === $row['caps']
		&& array() === $row['reads']
		&& array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:' . $provider ) ) === $row['services']
		&& array() === $row['queries']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array() === $row['metas']
		&& true === $row['boundary']
		&& array( 'acting_user_id', 'course_author_id', 'acting_instructor_id' ) === array_keys( $stage['input'] );
};
$delete_encoded = json_encode( $deletes );
tutorpress_live_lessons_assert(
	is_array( $deletes ) && isset( $deletes['false'] )
	&& false === $deletes['false']['entered']
	&& true === $deletes['proof'],
	'false-gate delete stays unentered and delegate_delete keeps its scan'
);
tutorpress_live_lessons_assert(
	$delete_error( $deletes['missing'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $delete_quiet( $deletes['missing'], array( array( 'post', 501 ) ) )
	&& $delete_error( $deletes['wrong_type'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400 )
	&& $delete_quiet( $deletes['wrong_type'], array( array( 'post', 502 ) ) )
	&& $delete_error( $deletes['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['missing_topic'], array( array( 'post', 503 ), array( 'post', 590 ) ) )
	&& $delete_error( $deletes['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['wrong_topic'], array( array( 'post', 504 ), array( 'post', 505 ) ) )
	&& $delete_error( $deletes['missing_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['missing_course'], array( array( 'post', 506 ), array( 'post', 507 ), array( 'post', 591 ) ) )
	&& $delete_error( $deletes['wrong_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['wrong_course'], array( array( 'post', 508 ), array( 'post', 509 ), array( 'post', 510 ) ) )
	&& $delete_error( $deletes['broken_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['broken_item'], array( array( 'post', 511 ), array( 'post', 512 ), array( 'post', 15 ) ) )
	&& $delete_error( $deletes['broken_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $delete_quiet( $deletes['broken_course'], array( array( 'post', 513 ), array( 'post', 514 ), array( 'post', 515 ) ) )
	&& $delete_error( $deletes['denied'], 'rest_cannot_delete', 'Sorry, you are not allowed to delete this live lesson.', 401 )
	&& $delete_quiet( $deletes['denied'], array( array( 'post', 516 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) )
	&& $delete_error( $deletes['draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $delete_quiet( $deletes['draft'], array( array( 'post', 518 ), array( 'post', 40 ), array( 'post', 15 ) ), array( array( 'can', 'edit_post', 15 ) ) )
	&& $delete_error( $deletes['private'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $delete_quiet( $deletes['private'], array( array( 'post', 519 ), array( 'post', 40 ), array( 'post', 15 ) ), array( array( 'can', 'edit_post', 15 ) ) ),
	'missing relations, non-publish items, and denied edit_post stop before adapter resolution and staging'
);
tutorpress_live_lessons_assert(
	$delete_once( $deletes['google'], 'google_meet', 530, 'tutor-google-meet' )
	&& 'response' === $deletes['google']['packed']['kind']
	&& 'Live lesson deleted successfully.' === $deletes['google']['packed']['data']['message']
	&& $delete_once( $deletes['zoom'], 'zoom', 528, 'tutor_zoom_meeting' )
	&& 'response' === $deletes['zoom']['packed']['kind']
	&& 'Request successful.' === $deletes['zoom']['packed']['data']['message']
	&& $deletes['unconfirmed_envelope'] === $deletes['zoom']['packed']['data']['data'],
	'canonical Google and Zoom items stage once through their stored adapters'
);
tutorpress_live_lessons_assert(
	$delete_error( $deletes['null_stage'], 'live_lesson_delete_failed', 'Failed to delete live lesson.', 500 )
	&& $delete_once( $deletes['null_stage'], 'google_meet', 530, 'tutor-google-meet' )
	&& true === $deletes['error_stage']['same']
	&& $delete_error( $deletes['error_stage'], 'stage_rejected', 'Stage rejected.', 422 )
	&& $delete_once( $deletes['error_stage'], 'google_meet', 530, 'tutor-google-meet' )
	&& $delete_error( $deletes['malformed'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $delete_once( $deletes['malformed'], 'google_meet', 530, 'tutor-google-meet' )
	&& $delete_error( $deletes['bad_bytes'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $delete_once( $deletes['bad_bytes'], 'google_meet', 530, 'tutor-google-meet' )
	&& $delete_once( $deletes['unconfirmed'], 'zoom', 528, 'tutor_zoom_meeting' )
	&& 'Request successful.' === $deletes['unconfirmed']['packed']['data']['message']
	&& $deletes['unconfirmed_envelope'] === $deletes['unconfirmed']['packed']['data']['data']
	&& $delete_once( $deletes['confirmed'], 'google_meet', 530, 'tutor-google-meet' )
	&& 'Live lesson deleted successfully.' === $deletes['confirmed']['packed']['data']['message']
	&& array(
		'outcome'     => 'confirmed',
		'provider_id' => 'meet-1',
		'item'        => null,
		'diagnostics' => array(),
		'provenance'  => 'delete',
	) === $deletes['confirmed']['packed']['data']['data'],
	'null, error, malformed, unconfirmed, and confirmed stage outcomes stay on the delete rest boundary'
);
tutorpress_live_lessons_assert(
	true === $deletes['cascade']
	&& array( 'item', 'topic', 'course', 'can', 'input', 'adapter', 'now', 'token', 'read', 'update', 'affected' ) === $deletes['params']['stage']
	&& array( 'item', 'topic', 'course', 'can' ) === $deletes['params']['authorize']
	&& $deletes['params']['stage'] === $deletes['params']['orchestrator']
	&& array( 0, 0 ) === $deletes['lifecycle']
	&& is_string( $delete_encoded )
	&& false === strpos( $delete_encoded, 'hidden-sentinel' )
	&& false === strpos( $delete_encoded, 'fake-credential-value' )
	&& false === strpos( $delete_encoded, 'forged-provider' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'delete delegation stages only through stage_delete and performs no provider or metadata lifecycle'
);
tutorpress_live_lessons_pass( 'gate-true delete delegates convergent deletion' );

$duplicates = isset( $binding_probe['data']['duplicates'] ) && is_array( $binding_probe['data']['duplicates'] ) ? $binding_probe['data']['duplicates'] : array();
$duplicate_failed = static function ( $row, $code, $message, $status ) {
	return is_array( $row ) && 'error' === $row['packed']['kind'] && $code === $row['packed']['code'] && $message === $row['packed']['message'] && $status === $row['packed']['status'];
};
$duplicate_stopped = static function ( $row ) {
	return is_array( $row ) && array() === $row['duplicates'] && array() === $row['stages'] && array() === $row['dispatches'] && array() === $row['deletes'] && 0 === $row['adapters'] && array( 'zoom' ) === $row['ledger'] && array() === $row['services'] && array() === $row['writes'] && array() === $row['updates'] && array() === $row['metas'] && array() === $row['reads'] && array() === $row['queries'] && true === $row['boundary'] && array( 0, 0 ) === $row['lifecycle'] && true === $row['hosts'];
};
$duplicate_quiet = static function ( $row, $posts, $caps = array() ) use ( $duplicate_stopped ) {
	return $duplicate_stopped( $row ) && $posts === $row['posts'] && $caps === $row['caps'] && 1 === $row['bindings'];
};
$source_item = array(
	'ID'           => 530,
	'post_type'    => 'tutor-google-meet',
	'post_status'  => 'publish',
	'post_parent'  => 40,
	'post_author'  => 17,
	'post_title'   => 'Stored title',
	'post_content' => 'Stored notes',
	'menu_order'   => 2,
);
$source_topic = array(
	'ID'           => 40,
	'post_type'    => 'topics',
	'post_status'  => 'publish',
	'post_parent'  => 15,
	'post_author'  => 17,
	'post_title'   => 'Stored title',
	'post_content' => 'Stored notes',
	'menu_order'   => 2,
);
$source_course = array(
	'ID'           => 15,
	'post_type'    => 'courses',
	'post_status'  => 'publish',
	'post_parent'  => 0,
	'post_author'  => 17,
	'post_title'   => 'Row 15',
	'post_content' => 'Body',
	'menu_order'   => 1,
);
$explicit_topic = array(
	'ID'           => 71,
	'post_type'    => 'topics',
	'post_status'  => 'publish',
	'post_parent'  => 15,
	'post_author'  => 17,
	'post_title'   => 'Row 71',
	'post_content' => 'Body',
	'menu_order'   => 1,
);
$child_on_source = array(
	'ID'           => 92,
	'post_type'    => 'tutor-google-meet',
	'post_status'  => 'publish',
	'post_parent'  => 40,
	'post_author'  => 17,
	'post_title'   => 'Row 92',
	'post_content' => 'Body',
	'menu_order'   => 1,
);
$child_on_explicit = array(
	'ID'           => 91,
	'post_type'    => 'lesson',
	'post_status'  => 'publish',
	'post_parent'  => 71,
	'post_author'  => 17,
	'post_title'   => 'Row 91',
	'post_content' => 'Body',
	'menu_order'   => 1,
);
$google_duplicate_meta = array(
	'tutor-google-meet-start-datetime' => '2026-10-06 18:00:00',
	'tutor-google-meet-end-datetime'   => '2026-10-06 19:00:00',
	'tutor-google-meet-event-details'  => array(
		'id'    => 'evt-1',
		'title' => 'Lab',
	),
	'tutor-google-meet-link'           => 'https://meet.example/abc',
);
$google_meta_calls = array(
	array( 'meta', 530, 'tutor-google-meet-start-datetime' ),
	array( 'meta', 530, 'tutor-google-meet-end-datetime' ),
	array( 'meta', 530, 'tutor-google-meet-event-details' ),
	array( 'meta', 530, 'tutor-google-meet-link' ),
);
$duplicate_query = static function ( $topic_id ) use ( $child_types ) {
	return array(
		array(
			'posts',
			array(
				'post_parent'    => $topic_id,
				'post_type'      => $child_types,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			),
		),
	);
};
$duplicate_once = static function ( $row, $target, $target_course, $children, $bindings, $posts, $caps, $reads ) use ( $source_item, $source_topic, $source_course, $google_duplicate_meta, $google_meta_calls ) {
	$call = isset( $row['duplicates'][0] ) && is_array( $row['duplicates'][0] ) ? $row['duplicates'][0] : array();
	return is_array( $row )
		&& 1 === count( $row['duplicates'] )
		&& $source_item === $call['source']
		&& $source_topic === $call['topic']
		&& $source_course === $call['course']
		&& $target === $call['target']
		&& $target_course === $call['target_course']
		&& $children === $call['children']
		&& $google_duplicate_meta === $call['meta']
		&& false === $call['edit_posts']
		&& array() === $row['stages']
		&& array() === $row['dispatches']
		&& array() === $row['deletes']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& array() === $row['services']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& $google_meta_calls === $row['metas']
		&& $reads === $row['reads']
		&& $posts === $row['posts']
		&& $caps === $row['caps']
		&& $bindings === $row['bindings']
		&& true === $row['boundary']
		&& array( 0, 0 ) === $row['lifecycle']
		&& true === $row['hosts'];
};
$projected_duplicate = array(
	'id'                 => 540,
	'title'              => 'Stored title',
	'description'        => 'Stored notes',
	'type'               => 'google_meet',
	'topicId'            => 40,
	'courseId'           => 15,
	'status'             => 'scheduled',
	'sync_state'         => 'error',
	'sync_version'       => 0,
	'persisted_envelope' => false,
	'operation'          => null,
	'warning'            => null,
	'error'              => null,
	'resolution'         => array( 'legacy_resolution_required' ),
	'record'             => null,
);
$source_caps = array( array( 'can', 'edit_post', 15 ) );
$duplicate_encoded = json_encode( $duplicates );
tutorpress_live_lessons_assert(
	is_array( $duplicates ) && isset( $duplicates['false'] )
	&& false === $duplicates['false']['entered']
	&& true === $duplicates['proof'],
	'false-gate duplicate stays unentered and delegate_duplicate keeps its scan'
);
tutorpress_live_lessons_assert(
	$duplicate_failed( $duplicates['missing'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $duplicate_quiet( $duplicates['missing'], array( array( 'post', 501 ) ) )
	&& $duplicate_failed( $duplicates['wrong'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400 )
	&& $duplicate_quiet( $duplicates['wrong'], array( array( 'post', 502 ) ) )
	&& $duplicate_failed( $duplicates['draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $duplicate_quiet( $duplicates['draft'], array( array( 'post', 542 ), array( 'post', 40 ), array( 'post', 15 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['private'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $duplicate_quiet( $duplicates['private'], array( array( 'post', 519 ), array( 'post', 40 ), array( 'post', 15 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['missing_topic'], array( array( 'post', 503 ), array( 'post', 590 ) ) )
	&& $duplicate_failed( $duplicates['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['wrong_topic'], array( array( 'post', 504 ), array( 'post', 505 ) ) )
	&& $duplicate_failed( $duplicates['missing_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['missing_course'], array( array( 'post', 506 ), array( 'post', 507 ), array( 'post', 591 ) ) )
	&& $duplicate_failed( $duplicates['wrong_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['wrong_course'], array( array( 'post', 508 ), array( 'post', 509 ), array( 'post', 510 ) ) )
	&& $duplicate_failed( $duplicates['broken_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['broken_item'], array( array( 'post', 511 ), array( 'post', 512 ), array( 'post', 15 ) ) )
	&& $duplicate_failed( $duplicates['broken_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $duplicate_quiet( $duplicates['broken_course'], array( array( 'post', 513 ), array( 'post', 514 ), array( 'post', 515 ) ) )
	&& $duplicate_failed( $duplicates['denied'], 'rest_cannot_create', 'Sorry, you are not allowed to duplicate this live lesson.', 401 )
	&& $duplicate_quiet( $duplicates['denied'], array( array( 'post', 541 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) ),
	'missing relations, non-publish items, and denied source edit_post stop before duplicate delegation'
);
tutorpress_live_lessons_assert(
	$duplicate_failed( $duplicates['zoom'], 'duplication_not_supported', 'Duplication is only supported for Google Meet lessons.', 400 )
	&& $duplicate_quiet( $duplicates['zoom'], array( array( 'post', 528 ), array( 'post', 40 ), array( 'post', 15 ) ), $source_caps ),
	'publish Zoom is rejected before the direct Google duplicate service'
);
tutorpress_live_lessons_assert(
	$duplicate_failed( $duplicates['dest_missing'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $duplicate_quiet( $duplicates['dest_missing'], array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 699 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['dest_zero'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $duplicate_quiet( $duplicates['dest_zero'], array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['dest_wrong'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $duplicate_quiet( $duplicates['dest_wrong'], array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 502 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['dest_forged'], 'invalid_topic', 'Invalid topic ID or topic does not belong to the specified course.', 404 )
	&& $duplicate_quiet( $duplicates['dest_forged'], array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 640 ), array( 'post', 641 ) ), $source_caps )
	&& $duplicate_failed( $duplicates['dest_denied'], 'rest_cannot_create', 'Sorry, you are not allowed to duplicate this live lesson.', 401 )
	&& $duplicate_quiet( $duplicates['dest_denied'], array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 81 ), array( 'post', 8 ) ), array( array( 'can', 'edit_post', 15 ), array( 'can', 'edit_post', 8 ) ) ),
	'missing, wrong, and forged destinations and denied target courses stop before duplicate delegation'
);
$default_posts = array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 540 ), array( 'post', 40 ), array( 'post', 15 ) );
$default_caps  = array( array( 'can', 'edit_post', 15 ), array( 'can', 'edit_post', 15 ), array( 'can', 'edit_post', 15 ), array( 'can', 'edit_post', 15 ) );
$default_reads = array( array( 'read', 540, '_tutorpress_live_lesson_sync' ) );
$prepared_caps = array( array( 'can', 'edit_post', 15 ), array( 'can', 'edit_post', 15 ) );
$prepared_posts = array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ) );
tutorpress_live_lessons_assert(
	$duplicate_once( $duplicates['default'], $source_topic, $source_course, array( $child_on_source ), 2, $default_posts, $default_caps, $default_reads )
	&& $duplicate_query( 40 ) === $duplicates['default']['queries']
	&& 'response' === $duplicates['default']['packed']['kind']
	&& 'Google Meet lesson duplicated successfully.' === $duplicates['default']['packed']['data']['message']
	&& $projected_duplicate === $duplicates['default']['packed']['data']['data']
	&& $duplicate_once( $duplicates['explicit'], $explicit_topic, $source_course, array( $child_on_explicit ), 2, array( array( 'post', 530 ), array( 'post', 40 ), array( 'post', 15 ), array( 'post', 71 ), array( 'post', 15 ), array( 'post', 540 ), array( 'post', 40 ), array( 'post', 15 ) ), $default_caps, $default_reads )
	&& $duplicate_query( 71 ) === $duplicates['explicit']['queries']
	&& $projected_duplicate === $duplicates['explicit']['packed']['data']['data']
	&& $duplicate_once( $duplicates['null_result'], $source_topic, $source_course, array( $child_on_source ), 1, $prepared_posts, $prepared_caps, array() )
	&& $duplicate_query( 40 ) === $duplicates['null_result']['queries']
	&& $duplicate_failed( $duplicates['null_result'], 'duplicate_creation_failed', 'Failed to create duplicate live lesson.', 500 )
	&& false === $duplicates['null_result']['same']
	&& $duplicate_once( $duplicates['error_result'], $source_topic, $source_course, array( $child_on_source ), 1, $prepared_posts, $prepared_caps, array() )
	&& true === $duplicates['error_result']['same']
	&& $duplicate_failed( $duplicates['error_result'], 'duplicate_blocked', 'Duplicate blocked.', 409 ),
	'a valid Google duplicate passes canonical rows once and hands integer, null, and error results to rest outcome'
);
tutorpress_live_lessons_assert(
	array( 'source', 'topic', 'course', 'target', 'target_course', 'children', 'meta', 'can' ) === $duplicates['params']['live']
	&& $duplicates['params']['live'] === $duplicates['params']['service']
	&& is_string( $duplicate_encoded )
	&& false === strpos( $duplicate_encoded, 'hidden-sentinel' )
	&& false === strpos( $duplicate_encoded, 'fake-credential-value' )
	&& false === strpos( $duplicate_encoded, 'forged-provider' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'gate-true duplicate delegates only to the direct duplicate service and rest outcome'
);
tutorpress_live_lessons_pass( 'gate-true duplicate delegates direct Google duplication' );

$reconciles = isset( $binding_probe['data']['reconciles'] ) && is_array( $binding_probe['data']['reconciles'] ) ? $binding_probe['data']['reconciles'] : array();
$reconcile_failed = static function ( $row, $code, $message, $status ) {
	return is_array( $row ) && 'error' === $row['packed']['kind'] && $code === $row['packed']['code'] && $message === $row['packed']['message'] && $status === $row['packed']['status'];
};
$reconcile_stopped = static function ( $row ) {
	return is_array( $row ) && array() === $row['reconciles'] && array() === $row['stages'] && array() === $row['dispatches'] && array() === $row['deletes'] && array() === $row['duplicates'] && 0 === $row['adapters'] && array( 'zoom' ) === $row['ledger'] && array() === $row['services'] && array() === $row['writes'] && array() === $row['updates'] && array() === $row['metas'] && array() === $row['reads'] && array() === $row['options'] && array() === $row['takeovers'] && true === $row['boundary'] && array( 0, 0 ) === $row['lifecycle'] && true === $row['hosts'];
};
$reconcile_quiet = static function ( $row, $posts, $caps = array(), $bindings = 1 ) use ( $reconcile_stopped ) {
	return $reconcile_stopped( $row ) && $posts === $row['posts'] && $caps === $row['caps'] && $bindings === $row['bindings'];
};
$reconcile_item_row = array(
	'ID'           => 530,
	'post_type'    => 'tutor-google-meet',
	'post_status'  => 'publish',
	'post_parent'  => 40,
	'post_author'  => 17,
	'post_title'   => 'Stored title',
	'post_content' => 'Stored notes',
	'menu_order'   => 2,
);
$reconcile_zoom_row = $reconcile_item_row;
$reconcile_zoom_row['ID'] = 521;
$reconcile_zoom_row['post_type'] = 'tutor_zoom_meeting';
$reconcile_topic_row = array(
	'ID'           => 40,
	'post_type'    => 'topics',
	'post_status'  => 'publish',
	'post_parent'  => 15,
	'post_author'  => 17,
	'post_title'   => 'Stored title',
	'post_content' => 'Stored notes',
	'menu_order'   => 2,
);
$reconcile_course_row = array(
	'ID'           => 15,
	'post_type'    => 'courses',
	'post_status'  => 'publish',
	'post_parent'  => 0,
	'post_author'  => 17,
	'post_title'   => 'Row 15',
	'post_content' => 'Body',
	'menu_order'   => 1,
);
$reconcile_evidence = array(
	array( 'source' => 'acting_user', 'id' => 44, 'label' => 'Row 15' ),
	array( 'source' => 'acting_instructor', 'id' => 44, 'label' => 'Row 15' ),
	array( 'source' => 'course_author', 'id' => 17, 'label' => 'Row 15' ),
	array( 'source' => 'post_author', 'id' => 17, 'label' => 'Stored title' ),
);
$reconcile_actor = array( 'version' => 1, 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 44, 'operation_id' => 'op-1' );
$reconcile_owner_input = array( 'version' => 0, 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 44, 'evidence' => $reconcile_evidence );
$reconcile_caps = array( array( 'can', 'edit_post', 15 ) );
$reconcile_chain_posts = static function ( $id ) {
	return array( array( 'post', $id ), array( 'post', 40 ), array( 'post', 15 ) );
};
$reconcile_once = static function ( $row, $action, $adapter, $item, $input, $ledger, $service ) use ( $reconcile_topic_row, $reconcile_course_row ) {
	$call = isset( $row['reconciles'][0] ) && is_array( $row['reconciles'][0] ) ? $row['reconciles'][0] : array();
	$keys = isset( $call['input'] ) && is_array( $call['input'] ) ? array_keys( $call['input'] ) : array();
	return is_array( $row )
		&& 1 === count( $row['reconciles'] )
		&& $action === $call['action']
		&& $item === $call['item']
		&& $reconcile_topic_row === $call['topic']
		&& $reconcile_course_row === $call['course']
		&& $input === $call['input']
		&& $adapter === $call['adapter']
		&& 'UTC' === $call['zone']
		&& 1 === preg_match( '/^[0-9a-f]{32}$/', (string) $call['token'] )
		&& true === $call['read']
		&& true === $call['update']
		&& true === $call['affected']
		&& true === $call['write']
		&& 'lock-bytes' === $call['option']
		&& true === $call['takeover']
		&& false === in_array( 'provider_id', $keys, true )
		&& false === in_array( 'credentials', $keys, true )
		&& false === in_array( 'selected_owner_id', $keys, true )
		&& false === in_array( 'type', $keys, true )
		&& array() === $row['stages']
		&& array() === $row['dispatches']
		&& array() === $row['deletes']
		&& array() === $row['duplicates']
		&& 1 === $row['bindings']
		&& $ledger === $row['ledger']
		&& array( array( 'service', $service ) ) === $row['services']
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& array() === $row['metas']
		&& array( array( 'option', 'tutorpress_live_lesson_lock_8' ) ) === $row['options']
		&& array( array( 'takeover', 'tutorpress_live_lesson_lock_8', 'next-bytes', 'prior-bytes' ) ) === $row['takeovers']
		&& true === $row['boundary']
		&& array( 0, 0 ) === $row['lifecycle']
		&& true === $row['hosts'];
};
$identity_stopped = true;
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $identity_key ) {
	$identity_stopped = $identity_stopped && $reconcile_failed( $reconciles['identity'][ $identity_key ], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 ) && $reconcile_quiet( $reconciles['identity'][ $identity_key ], array(), array(), 0 );
}
$reconcile_schema = array(
	array( 'name' => 'action', 'type' => 'string', 'required' => true, 'enum' => array( 'inspect', 'retry', 'resolve_owner', 'abandon_unknown' ), 'default' => null, 'sanitize_callback' => '' ),
	array( 'name' => 'version', 'type' => 'integer', 'required' => true, 'enum' => null, 'default' => null, 'sanitize_callback' => '' ),
	array( 'name' => 'operation_id', 'type' => 'string', 'required' => false, 'enum' => null, 'default' => null, 'sanitize_callback' => '' ),
);
$reconcile_routes = array(
	'/live-lessons',
	'/live-lessons/zoom/users',
	'/live-lessons/google-meet/settings',
	'/live-lessons/(?P<id>[\d]+)',
	'/live-lessons/(?P<id>[\d]+)/duplicate',
);
$google_service = 'TutorPress_Live_Lesson_Provider_Adapter:google_meet';
$zoom_service   = 'TutorPress_Live_Lesson_Provider_Adapter:zoom';
$reconcile_encoded = json_encode( $reconciles );
tutorpress_live_lessons_assert(
	array_merge( $reconcile_routes, array( '/live-lessons/(?P<id>[\d]+)/attach/uncertain-create', '/live-lessons/(?P<id>[\d]+)/attach/legacy', '/live-lessons/(?P<id>[\d]+)/reconcile' ) ) === $reconciles['routes']['false']
	&& array_merge( $reconcile_routes, array( '/live-lessons/(?P<id>[\d]+)/attach/uncertain-create', '/live-lessons/(?P<id>[\d]+)/attach/legacy', '/live-lessons/(?P<id>[\d]+)/reconcile' ) ) === $reconciles['routes']['true']
	&& $reconcile_schema === $reconciles['schema']
	&& 'reconcile_item' === $reconciles['callback']
	&& 'check_lesson_permission' === $reconciles['permission']
	&& true === $reconciles['proof'],
	'false-gate registration includes the reconciliation route and the gate-true schema is only the approved actions'
);
tutorpress_live_lessons_assert(
	$identity_stopped
	&& $reconcile_failed( $reconciles['missing_operation'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $reconcile_quiet( $reconciles['missing_operation'], array(), array(), 0 )
	&& $reconcile_failed( $reconciles['zero_inspect'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $reconcile_quiet( $reconciles['zero_inspect'], array(), array(), 0 )
	&& $reconcile_failed( $reconciles['owner_operation'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $reconcile_quiet( $reconciles['owner_operation'], array(), array(), 0 )
	&& $reconcile_failed( $reconciles['owner_version'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $reconcile_quiet( $reconciles['owner_version'], array(), array(), 0 )
	&& $reconcile_failed( $reconciles['attach'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $reconcile_quiet( $reconciles['attach'], array(), array(), 0 ),
	'forbidden identity and incomplete version or operation input stop before adapter and orchestrator calls'
);
tutorpress_live_lessons_assert(
	$reconcile_failed( $reconciles['missing'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $reconcile_quiet( $reconciles['missing'], array( array( 'post', 501 ) ) )
	&& $reconcile_failed( $reconciles['wrong'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400 )
	&& $reconcile_quiet( $reconciles['wrong'], array( array( 'post', 502 ) ) )
	&& $reconcile_failed( $reconciles['draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $reconcile_quiet( $reconciles['draft'], $reconcile_chain_posts( 518 ), $reconcile_caps )
	&& $reconcile_failed( $reconciles['private'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $reconcile_quiet( $reconciles['private'], $reconcile_chain_posts( 519 ), $reconcile_caps )
	&& $reconcile_failed( $reconciles['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['missing_topic'], array( array( 'post', 503 ), array( 'post', 590 ) ) )
	&& $reconcile_failed( $reconciles['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['wrong_topic'], array( array( 'post', 504 ), array( 'post', 505 ) ) )
	&& $reconcile_failed( $reconciles['missing_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['missing_course'], array( array( 'post', 506 ), array( 'post', 507 ), array( 'post', 591 ) ) )
	&& $reconcile_failed( $reconciles['wrong_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['wrong_course'], array( array( 'post', 508 ), array( 'post', 509 ), array( 'post', 510 ) ) )
	&& $reconcile_failed( $reconciles['broken_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['broken_item'], array( array( 'post', 511 ), array( 'post', 512 ), array( 'post', 15 ) ) )
	&& $reconcile_failed( $reconciles['broken_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $reconcile_quiet( $reconciles['broken_course'], array( array( 'post', 513 ), array( 'post', 514 ), array( 'post', 515 ) ) )
	&& $reconcile_failed( $reconciles['denied'], 'rest_cannot_edit', 'Sorry, you are not allowed to edit this live lesson.', 401 )
	&& $reconcile_quiet( $reconciles['denied'], array( array( 'post', 516 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) ),
	'invalid chains, non-publish items, and denied edit_post stop before reconciliation lifecycle bindings'
);
$meet_fields = array(
	'match'  => false,
	'fields' => array(
		'end_at'      => '2026-10-07T17:00:00Z',
		'provider_id' => 'evt-9',
		'start_at'    => '2026-10-07T16:00:00Z',
		'timezone'    => 'UTC',
	),
);
$compared = static function ( $row ) use ( $meet_fields ) {
	return is_array( $row ) && 'response' === $row['packed']['kind'] && 'Request successful.' === $row['packed']['data']['message'] && $meet_fields === $row['packed']['data']['data'];
};
$owner_shape = array( 'owner_id' => 44, 'candidates' => array( array( 'id' => 44, 'label' => 'Row 15' ) ) );
$owner_payload = static function ( $row ) use ( $owner_shape ) {
	return is_array( $row ) && 'response' === $row['packed']['kind'] && 'Request successful.' === $row['packed']['data']['message'] && $owner_shape === $row['packed']['data']['data'];
};
$rejected_row = isset( $reconciles['rejected'] ) ? $reconciles['rejected'] : array();
tutorpress_live_lessons_assert(
	$reconcile_failed( $rejected_row, 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $rejected_row['reconciles']
	&& 0 === $rejected_row['adapters']
	&& array( 'zoom' ) === $rejected_row['ledger']
	&& array() === $rejected_row['services']
	&& array() === $rejected_row['options']
	&& array() === $rejected_row['takeovers']
	&& array() === $rejected_row['writes']
	&& array() === $rejected_row['updates']
	&& array() === $rejected_row['metas']
	&& $reconcile_chain_posts( 520 ) === $rejected_row['posts']
	&& $reconcile_caps === $rejected_row['caps']
	&& array( array( 'read', 520, '_tutorpress_live_lesson_sync' ) ) === $rejected_row['reads']
	&& true === $rejected_row['boundary'],
	'a rejected envelope does not select an adapter'
);
$envelope_data = static function ( $row ) {
	$data = is_array( $row ) && isset( $row['packed']['data']['data'] ) ? $row['packed']['data']['data'] : array();
	return is_array( $row ) && 'response' === $row['packed']['kind'] && 'Request successful.' === $row['packed']['data']['message'] && is_array( $data ) && 'unlinked' === $data['state'] && 2 === $data['version'] && 'zoom' === $data['provider'] && 'orphan_may_remain' === $data['warning'] && 11 === count( $data );
};
tutorpress_live_lessons_assert(
	$reconcile_once( $reconciles['absent_google'], 'inspect', 'google_meet', $reconcile_item_row, $reconcile_actor, array( 'zoom', 'google_meet' ), $google_service )
	&& 1 === $reconciles['absent_google']['adapters']
	&& array( array( 'read', 530, '_tutorpress_live_lesson_sync' ) ) === $reconciles['absent_google']['reads']
	&& $compared( $reconciles['absent_google'] )
	&& $reconcile_once( $reconciles['envelope_zoom'], 'inspect', 'zoom', $reconcile_item_row, $reconcile_actor, array( 'zoom', 'zoom' ), $zoom_service )
	&& array( array( 'read', 530, '_tutorpress_live_lesson_sync' ) ) === $reconciles['envelope_zoom']['reads']
	&& $compared( $reconciles['envelope_zoom'] )
	&& $reconcile_once( $reconciles['absent_zoom'], 'retry', 'zoom', $reconcile_zoom_row, $reconcile_actor, array( 'zoom', 'zoom' ), $zoom_service )
	&& array( array( 'read', 521, '_tutorpress_live_lesson_sync' ) ) === $reconciles['absent_zoom']['reads']
	&& $envelope_data( $reconciles['absent_zoom'] )
	&& $reconcile_once( $reconciles['envelope_google'], 'resolve_owner', 'google_meet', $reconcile_zoom_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& array( array( 'read', 521, '_tutorpress_live_lesson_sync' ) ) === $reconciles['envelope_google']['reads']
	&& $compared( $reconciles['envelope_google'] ),
	'stored envelope provider wins, and an absent envelope uses the canonical post type'
);
tutorpress_live_lessons_assert(
	$reconcile_once( $reconciles['retry'], 'retry', 'zoom', $reconcile_item_row, $reconcile_actor, array( 'zoom', 'zoom' ), $zoom_service )
	&& $envelope_data( $reconciles['retry'] )
	&& $reconcile_once( $reconciles['resolve'], 'resolve_owner', 'google_meet', $reconcile_item_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $compared( $reconciles['resolve'] )
	&& $reconcile_once( $reconciles['resolve_shape'], 'resolve_owner', 'google_meet', $reconcile_item_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $owner_payload( $reconciles['resolve_shape'] ),
	'inspect, retry, and resolve_owner resolve one canonical adapter and call only their orchestrator seam'
);
$resolve_encoded = json_encode( $reconciles['resolve_shape']['packed']['data'] );
tutorpress_live_lessons_assert(
	$reconcile_once( $reconciles['resolve_extra'], 'resolve_owner', 'google_meet', $reconcile_item_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $reconcile_failed( $reconciles['resolve_extra'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $reconcile_once( $reconciles['resolve_zero'], 'resolve_owner', 'google_meet', $reconcile_item_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $reconcile_failed( $reconciles['resolve_zero'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $reconcile_once( $reconciles['resolve_absent'], 'resolve_owner', 'google_meet', $reconcile_item_row, $reconcile_owner_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $reconcile_failed( $reconciles['resolve_absent'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& $reconcile_failed( $reconciles['null_retry'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $reconciles['null_retry']['reconciles'] )
	&& 'retry' === $reconciles['null_retry']['reconciles'][0]['action']
	&& $reconcile_failed( $reconciles['null_resolve'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $reconciles['null_resolve']['reconciles'] )
	&& 'resolve_owner' === $reconciles['null_resolve']['reconciles'][0]['action']
	&& $reconcile_failed( $reconciles['null_abandon'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $reconciles['null_abandon']['reconciles'] )
	&& 'abandon_unknown' === $reconciles['null_abandon']['reconciles'][0]['action']
	&& is_string( $resolve_encoded )
	&& false === strpos( $resolve_encoded, 'hidden-sentinel' )
	&& false === strpos( $resolve_encoded, 'selected_owner_id' )
	&& false === strpos( $resolve_encoded, '999' ),
	'owner resolution returns only the server candidate list and null reconciliation stays sync-invalid'
);
$abandon_item = $reconcile_zoom_row;
$abandon_item['ID'] = 520;
$abandon = isset( $reconciles['abandon'] ) ? $reconciles['abandon'] : array();
$abandon_call = isset( $abandon['reconciles'][0] ) ? $abandon['reconciles'][0] : array();
tutorpress_live_lessons_assert(
	1 === count( $abandon['reconciles'] )
	&& 'abandon_unknown' === $abandon_call['action']
	&& $abandon_item === $abandon_call['item']
	&& $reconcile_topic_row === $abandon_call['topic']
	&& $reconcile_course_row === $abandon_call['course']
	&& $reconcile_actor === $abandon_call['input']
	&& 'null' === $abandon_call['adapter']
	&& 'lock-bytes' === $abandon_call['option']
	&& true === $abandon_call['takeover']
	&& 0 === $abandon['adapters']
	&& array( 'zoom' ) === $abandon['ledger']
	&& array() === $abandon['services']
	&& array() === $abandon['reads']
	&& array() === $abandon['writes']
	&& array() === $abandon['updates']
	&& array() === $abandon['metas']
	&& array() === $abandon['stages']
	&& array() === $abandon['dispatches']
	&& array() === $abandon['duplicates']
	&& array( array( 'option', 'tutorpress_live_lesson_lock_8' ) ) === $abandon['options']
	&& array( array( 'takeover', 'tutorpress_live_lesson_lock_8', 'next-bytes', 'prior-bytes' ) ) === $abandon['takeovers']
	&& $envelope_data( $abandon )
	&& true === $reconciles['error']['same']
	&& $reconcile_failed( $reconciles['error'], 'live_lesson_retry_blocked', 'Retry blocked.', 409 )
	&& 1 === count( $reconciles['error']['reconciles'] )
	&& 'retry' === $reconciles['error']['reconciles'][0]['action']
	&& $reconcile_failed( $reconciles['null_inspect'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $reconciles['null_inspect']['reconciles'] )
	&& 'inspect' === $reconciles['null_inspect']['reconciles'][0]['action']
	&& is_string( $reconcile_encoded )
	&& false === strpos( $reconcile_encoded, 'hidden-sentinel' )
	&& false === strpos( $reconcile_encoded, 'fake-credential-value' )
	&& false === strpos( $reconcile_encoded, 'forged-provider' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'abandon_unknown uses bindings only and leaves the adapter ledger unchanged'
);
tutorpress_live_lessons_pass( 'gate-true reconciliation delegates ordinary actions' );

$attaches = isset( $binding_probe['data']['attaches'] ) && is_array( $binding_probe['data']['attaches'] ) ? $binding_probe['data']['attaches'] : array();
$attach_schema_row = static function ( $name, $type, $required ) {
	return array( 'name' => $name, 'type' => $type, 'required' => $required, 'enum' => null, 'default' => null, 'sanitize_callback' => '' );
};
$uncertain_schema = array(
	$attach_schema_row( 'provider_id', 'string', true ),
	$attach_schema_row( 'version', 'integer', true ),
	$attach_schema_row( 'operation_id', 'string', true ),
);
$legacy_schema = array(
	$attach_schema_row( 'provider_id', 'string', true ),
	$attach_schema_row( 'version', 'integer', true ),
	$attach_schema_row( 'selected_owner_id', 'integer', false ),
);
$attach_registered = array(
	array( 'route' => '/live-lessons/(?P<id>[\d]+)/attach/uncertain-create', 'schema' => $uncertain_schema, 'callback' => 'attach_item', 'permission' => 'check_lesson_permission', 'method' => 'POST' ),
	array( 'route' => '/live-lessons/(?P<id>[\d]+)/attach/legacy', 'schema' => $legacy_schema, 'callback' => 'attach_item', 'permission' => 'check_lesson_permission', 'method' => 'POST' ),
);
$attach_stopped = static function ( $row ) use ( $reconcile_stopped ) {
	return $reconcile_stopped( $row ) && isset( $row['attaches'] ) && array() === $row['attaches'];
};
$attach_quiet = static function ( $row, $posts, $caps = array(), $bindings = 1 ) use ( $attach_stopped ) {
	return $attach_stopped( $row ) && $posts === $row['posts'] && $caps === $row['caps'] && $bindings === $row['bindings'];
};
$attach_input = static function ( $row ) {
	$call = isset( $row['attaches'][0] ) && is_array( $row['attaches'][0] ) ? $row['attaches'][0] : array();
	return isset( $call['input'] ) && is_array( $call['input'] ) ? $call['input'] : array();
};
$uncertain_input = array( 'version' => 1, 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 44, 'provider_id' => 'evt-supplied', 'operation_id' => 'op-1' );
$legacy_input = array( 'version' => 0, 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 44, 'provider_id' => 'evt-supplied', 'evidence' => $reconcile_evidence, 'local' => array() );
$legacy_google_input = $legacy_input;
$legacy_google_input['local'] = array( 'tutor-google-meet-start-datetime' => '2026-10-08 15:00:00', 'tutor-google-meet-link' => 'https://meet.example/row' );
$legacy_selected_input = $legacy_input;
$legacy_selected_input['selected_owner_id'] = 27;
$google_meta_calls = array(
	array( 'meta', 530, 'tutor-google-meet-start-datetime' ),
	array( 'meta', 530, 'tutor-google-meet-end-datetime' ),
	array( 'meta', 530, 'tutor-google-meet-event-details' ),
	array( 'meta', 530, 'tutor-google-meet-link' ),
);
$zoom_meta_calls = array(
	array( 'meta', 521, '_tutor_zm_start_date' ),
	array( 'meta', 521, '_tutor_zm_start_datetime' ),
	array( 'meta', 521, '_tutor_zm_duration' ),
	array( 'meta', 521, '_tutor_zm_duration_unit' ),
	array( 'meta', 521, '_tutor_zm_for_course' ),
	array( 'meta', 521, '_tutor_zm_for_topic' ),
	array( 'meta', 521, '_tutor_zm_data' ),
);
$attach_once = static function ( $row, $kind, $adapter, $item, $input, $ledger, $service ) use ( $reconcile_topic_row, $reconcile_course_row ) {
	$call = isset( $row['attaches'][0] ) && is_array( $row['attaches'][0] ) ? $row['attaches'][0] : array();
	return is_array( $row )
		&& 1 === count( $row['attaches'] )
		&& array() === $row['reconciles']
		&& $kind === $call['kind']
		&& $item === $call['item']
		&& $reconcile_topic_row === $call['topic']
		&& $reconcile_course_row === $call['course']
		&& $input === $call['input']
		&& $adapter === $call['adapter']
		&& true === $call['write']
		&& 'UTC' === $call['zone']
		&& 1 === preg_match( '/^[0-9a-f]{32}$/', (string) $call['token'] )
		&& array() === $row['writes']
		&& array() === $row['updates']
		&& 1 === $row['adapters']
		&& $ledger === $row['ledger']
		&& array( array( 'service', $service ) ) === $row['services']
		&& true === $row['boundary']
		&& array( 0, 0 ) === $row['lifecycle']
		&& true === $row['hosts'];
};
$blocked_stopped = true;
foreach ( array( 'credentials', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $blocked_key ) {
	$blocked_stopped = $blocked_stopped
		&& $reconcile_failed( $attaches['blocked'][ 'uncertain_' . $blocked_key ], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
		&& $attach_quiet( $attaches['blocked'][ 'uncertain_' . $blocked_key ], array(), array(), 0 )
		&& $reconcile_failed( $attaches['blocked'][ 'legacy_' . $blocked_key ], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
		&& $attach_quiet( $attaches['blocked'][ 'legacy_' . $blocked_key ], array(), array(), 0 );
}
$shape_stopped = true;
foreach ( $attaches['shape'] as $shape_row ) {
	$shape_stopped = $shape_stopped && $reconcile_failed( $shape_row, 'invalid_operation_id', 'Invalid reconciliation input.', 400 ) && $attach_quiet( $shape_row, array(), array(), 0 );
}
$attach_encoded = json_encode( $attaches );
tutorpress_live_lessons_assert(
	false !== strpos( implode( "\n", $reconciles['routes']['false'] ), '/attach/' )
	&& $attach_registered === $attaches['registered']
	&& false === strpos( json_encode( $reconciles['schema'] ), 'provider_id' )
	&& true === $attaches['proof'],
	'false-gate routes include both attach paths and gate-true attach schemas stay disjoint from reconcile'
);
tutorpress_live_lessons_assert(
	$blocked_stopped
	&& $reconcile_failed( $attaches['blocked']['uncertain_selected_owner_id'], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
	&& $attach_quiet( $attaches['blocked']['uncertain_selected_owner_id'], array(), array(), 0 )
	&& $reconcile_failed( $attaches['blocked']['uncertain_kind'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $attach_quiet( $attaches['blocked']['uncertain_kind'], array(), array(), 0 )
	&& $reconcile_failed( $attaches['blocked']['legacy_kind'], 'invalid_operation_id', 'Invalid reconciliation input.', 400 )
	&& $attach_quiet( $attaches['blocked']['legacy_kind'], array(), array(), 0 )
	&& $shape_stopped,
	'each mixed or forbidden attach key is rejected before adapter resolution and orchestrator delegation'
);
tutorpress_live_lessons_assert(
	$reconcile_failed( $attaches['chain']['missing'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $attach_quiet( $attaches['chain']['missing'], array( array( 'post', 501 ) ) )
	&& $reconcile_failed( $attaches['chain']['wrong'], 'invalid_post_type', 'Invalid post type. Must be a live lesson.', 400 )
	&& $attach_quiet( $attaches['chain']['wrong'], array( array( 'post', 502 ) ) )
	&& $reconcile_failed( $attaches['chain']['draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $attach_quiet( $attaches['chain']['draft'], $reconcile_chain_posts( 518 ), $reconcile_caps )
	&& $reconcile_failed( $attaches['chain']['private'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $attach_quiet( $attaches['chain']['private'], $reconcile_chain_posts( 519 ), $reconcile_caps )
	&& $reconcile_failed( $attaches['chain']['missing_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['missing_topic'], array( array( 'post', 503 ), array( 'post', 590 ) ) )
	&& $reconcile_failed( $attaches['chain']['wrong_topic'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['wrong_topic'], array( array( 'post', 504 ), array( 'post', 505 ) ) )
	&& $reconcile_failed( $attaches['chain']['missing_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['missing_course'], array( array( 'post', 506 ), array( 'post', 507 ), array( 'post', 591 ) ) )
	&& $reconcile_failed( $attaches['chain']['wrong_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['wrong_course'], array( array( 'post', 508 ), array( 'post', 509 ), array( 'post', 510 ) ) )
	&& $reconcile_failed( $attaches['chain']['broken_item'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['broken_item'], array( array( 'post', 511 ), array( 'post', 512 ), array( 'post', 15 ) ) )
	&& $reconcile_failed( $attaches['chain']['broken_course'], 'invalid_topic', 'Invalid topic for this live lesson.', 404 )
	&& $attach_quiet( $attaches['chain']['broken_course'], array( array( 'post', 513 ), array( 'post', 514 ), array( 'post', 515 ) ) )
	&& $reconcile_failed( $attaches['chain']['denied'], 'rest_cannot_edit', 'Sorry, you are not allowed to edit this live lesson.', 401 )
	&& $attach_quiet( $attaches['chain']['denied'], array( array( 'post', 516 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) )
	&& $reconcile_failed( $attaches['chain']['legacy_draft'], 'live_lesson_not_found', 'Live lesson not found.', 404 )
	&& $attach_quiet( $attaches['chain']['legacy_draft'], $reconcile_chain_posts( 518 ), $reconcile_caps )
	&& array() === $attaches['chain']['legacy_draft']['metas']
	&& $reconcile_failed( $attaches['chain']['legacy_denied'], 'rest_cannot_edit', 'Sorry, you are not allowed to edit this live lesson.', 401 )
	&& $attach_quiet( $attaches['chain']['legacy_denied'], array( array( 'post', 516 ), array( 'post', 517 ), array( 'post', 18 ) ), array( array( 'can', 'edit_post', 18 ) ) )
	&& array() === $attaches['chain']['legacy_denied']['metas']
	&& $reconcile_failed( $attaches['chain']['rejected'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $attaches['chain']['rejected']['attaches']
	&& 0 === $attaches['chain']['rejected']['adapters']
	&& array() === $attaches['chain']['rejected']['metas']
	&& array( array( 'read', 520, '_tutorpress_live_lesson_sync' ) ) === $attaches['chain']['rejected']['reads']
	&& $reconcile_failed( $attaches['chain']['legacy_rejected'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& array() === $attaches['chain']['legacy_rejected']['attaches']
	&& 0 === $attaches['chain']['legacy_rejected']['adapters']
	&& array() === $attaches['chain']['legacy_rejected']['metas']
	&& array( array( 'read', 520, '_tutorpress_live_lesson_sync' ) ) === $attaches['chain']['legacy_rejected']['reads'],
	'invalid chains, non-publish items, denied edit_post, and a rejected envelope stop before adapter resolution'
);
tutorpress_live_lessons_assert(
	$attach_once( $attaches['live']['uncertain_google'], 'uncertain_create', 'google_meet', $reconcile_item_row, $uncertain_input, array( 'zoom', 'google_meet' ), $google_service )
	&& array() === $attaches['live']['uncertain_google']['metas']
	&& $envelope_data( $attaches['live']['uncertain_google'] )
	&& $attach_once( $attaches['live']['uncertain_zoom_envelope'], 'uncertain_create', 'zoom', $reconcile_item_row, $uncertain_input, array( 'zoom', 'zoom' ), $zoom_service )
	&& array() === $attaches['live']['uncertain_zoom_envelope']['metas']
	&& $attach_once( $attaches['live']['uncertain_zoom'], 'uncertain_create', 'zoom', $reconcile_zoom_row, $uncertain_input, array( 'zoom', 'zoom' ), $zoom_service )
	&& array() === $attaches['live']['uncertain_zoom']['metas']
	&& $attach_once( $attaches['live']['legacy_google'], 'legacy_identity', 'google_meet', $reconcile_item_row, $legacy_google_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $google_meta_calls === $attaches['live']['legacy_google']['metas']
	&& $envelope_data( $attaches['live']['legacy_google'] )
	&& $attach_once( $attaches['live']['legacy_zoom_empty'], 'legacy_identity', 'zoom', $reconcile_zoom_row, $legacy_input, array( 'zoom', 'zoom' ), $zoom_service )
	&& $zoom_meta_calls === $attaches['live']['legacy_zoom_empty']['metas']
	&& $attach_once( $attaches['live']['legacy_selected'], 'legacy_identity', 'google_meet', $reconcile_item_row, $legacy_selected_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $google_meta_calls === $attaches['live']['legacy_selected']['metas']
	&& $attach_once( $attaches['live']['legacy_google_envelope'], 'legacy_identity', 'google_meet', $reconcile_zoom_row, $legacy_input, array( 'zoom', 'google_meet' ), $google_service )
	&& $zoom_meta_calls === $attaches['live']['legacy_google_envelope']['metas']
	&& $reconcile_failed( $attaches['live']['null_uncertain'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $attaches['live']['null_uncertain']['attaches'] )
	&& 'uncertain_create' === $attaches['live']['null_uncertain']['attaches'][0]['kind']
	&& $reconcile_failed( $attaches['live']['null_legacy'], 'live_lesson_sync_invalid', 'Live lesson sync envelope is invalid.', 409 )
	&& 1 === count( $attaches['live']['null_legacy']['attaches'] )
	&& 'legacy_identity' === $attaches['live']['null_legacy']['attaches'][0]['kind']
	&& is_string( $attach_encoded )
	&& false === strpos( $attach_encoded, 'hidden-sentinel' )
	&& false === strpos( $attach_encoded, '"source":"client"' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'gate-true attachment delegates uncertain-create and legacy identity through separate orchestrator calls'
);
tutorpress_live_lessons_pass( 'gate-true attachment delegates uncertain-create and legacy identity' );

$omitted_probe = $run_controller_probe(
	<<<'PHP'
class TutorPress_Omitted_Probe {
	public static $inserts = 0;
}
class TutorPress_Omitted_Wpdb {
	public $options       = 'wp_options';
	public $rows_affected = 0;
	public function prepare( $query, $name, $prior ) {
		return array( $query, $name, $prior );
	}
	public function query( $prepared ) {
		$name  = $prepared[1];
		$prior = $prepared[2];
		if ( ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) || TutorPress_Live_Lessons_Store_Fake::$options[ $name ] !== $prior ) {
			$this->rows_affected = 0;
			return 0;
		}
		unset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] );
		$this->rows_affected = 1;
		return 1;
	}
}
class TutorPress_Live_Lessons_Omitted_Adapter implements TutorPress_Live_Lesson_Provider_Adapter {
	public $preflight_result = true;
	public $legacy           = null;
	public $item             = null;
	public $calls            = array();
	public function capabilities() {
		$this->calls[] = array( 'capabilities' );
		return array( 'delete_convergence' => true );
	}
	public function preflight( $owner_id ): bool {
		$this->calls[] = array( 'preflight', $owner_id );
		return $this->preflight_result;
	}
	public function settings( $owner_id ) { unset( $owner_id ); return null; }
	public function list_hosts( $owner_id ) { unset( $owner_id ); return null; }
	public function validate_host( $owner_id, $host_id ): bool {
		$this->calls[] = array( 'validate_host', $owner_id, $host_id );
		return false;
	}
	public function create( $owner_id, array $target ) { unset( $owner_id, $target ); return null; }
	public function fetch( $owner_id, $provider_id ) {
		$this->calls[] = array( 'fetch', $owner_id, $provider_id );
		return null;
	}
	public function update( $owner_id, $provider_id, array $target ) { unset( $owner_id, $provider_id, $target ); return null; }
	public function delete( $owner_id, $provider_id ) { unset( $owner_id, $provider_id ); return null; }
	public function compare_target( array $result, array $expected ) { unset( $result, $expected ); return null; }
	public function metadata_map( array $result, array $target ) { unset( $result, $target ); return null; }
	public function hydrate_legacy( array $local ) {
		$this->calls[] = array( 'hydrate_legacy' );
		unset( $local );
		return $this->legacy;
	}
	public function hydrate_item( array $local ) {
		$this->calls[] = array( 'hydrate_item' );
		unset( $local );
		return $this->item;
	}
}
function add_option( $name, $bytes, $deprecated = '', $autoload = false ) {
	unset( $deprecated, $autoload );
	return TutorPress_Live_Lessons_Store_Fake::add_option( $name, $bytes );
}
function add_post_meta( $id, $key, $value ) {
	unset( $id, $key, $value );
	TutorPress_Omitted_Probe::$inserts++;
	return false;
}
$GLOBALS['wpdb'] = new TutorPress_Omitted_Wpdb();
$adapter         = new TutorPress_Live_Lessons_Omitted_Adapter();
$course          = array( 'ID' => 15, 'post_type' => 'courses', 'post_author' => 17 );
$topic           = array( 'ID' => 4, 'post_type' => 'topics', 'post_parent' => 15 );
$now             = new DateTimeImmutable( '2026-10-07 12:00:00', new DateTimeZone( 'UTC' ) );
$token           = 'token-omitted';
$seq             = 800;
$allow           = static function ( $cap, $id ) { return 'edit_post' === $cap && 15 === (int) $id; };
$update          = static function () { return false; };
$affected        = static function () { return 0; };
$write           = static function () { return 0; };
$option          = static function () { return null; };
$takeover        = static function () { return false; };
$pack            = static function ( $result ) {
	if ( null === $result ) {
		return array( 'kind' => 'null' );
	}
	if ( is_array( $result ) && isset( $result['owner_id'], $result['candidates'] ) && ! isset( $result['envelope'] ) ) {
		return array(
			'kind'          => 'owner',
			'owner_id'      => (int) $result['owner_id'],
			'candidate_ids' => array_values( array_column( $result['candidates'], 'id' ) ),
		);
	}
	return array( 'kind' => 'other' );
};
$run = static function ( $callback ) use ( $adapter, $pack ) {
	$adapter->calls                   = array();
	TutorPress_Omitted_Probe::$inserts = 0;
	$result                           = $callback();
	return array(
		'calls'   => $adapter->calls,
		'inserts' => TutorPress_Omitted_Probe::$inserts,
		'result'  => $pack( $result ),
	);
};
$zoom_input = static function ( $author, $acting, $credentials = null ) {
	$input = array(
		'provider'             => 'zoom',
		'course_author_id'     => $author,
		'acting_instructor_id' => $acting,
		'host_id'              => 'host-1',
	);
	if ( null !== $credentials ) {
		$input['credentials'] = $credentials;
	}
	return $input;
};
$item_for = static function () use ( &$seq ) {
	$seq++;
	return array(
		'ID'           => $seq,
		'post_type'    => 'tutor_zoom_meeting',
		'post_status'  => 'publish',
		'post_parent'  => 4,
		'post_title'   => 'Zoom',
		'post_content' => 'Meet',
		'menu_order'   => 2,
	);
};
$read_for = static function ( $post_id, $payload ) {
	return static function ( $id, $key = null ) use ( $post_id, $payload ) {
		if ( (int) $id === (int) $post_id && '_tutorpress_live_lesson_sync' === $key && is_string( $payload ) ) {
			return array( array( 'meta_id' => 31, 'meta_value' => $payload, 'post_id' => (int) $post_id ) );
		}
		return array();
	};
};
$canonical = static function ( array $guard ) {
	return TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $guard );
};
$nine = static function ( array $input, $owner ) use ( $adapter, $course, $topic, $allow, $now, $token, $update, $affected, $write, $option, $takeover, $run, $item_for, $read_for, $canonical ) {
	$evidence = 55 === (int) $owner
		? array( array( 'source' => 'acting_instructor', 'id' => 55, 'label' => 'Instructor' ) )
		: array( array( 'source' => 'course_author', 'id' => 17, 'label' => 'Author' ) );
	$synced   = $canonical( array( 'version' => 1, 'state' => 'synced', 'provider' => 'zoom', 'provider_id' => 'meeting-1', 'operation' => array( 'kind' => 'update', 'provider' => 'zoom' ) ) );
	$inspect  = $canonical( array( 'version' => 3, 'provider' => 'zoom', 'provider_id' => 'meeting-1', 'owner_id' => $owner, 'operation' => array( 'operation_id' => 'op-inspect', 'kind' => 'update', 'provider' => 'zoom', 'provider_id' => 'meeting-1', 'owner_id' => $owner ) ) );
	$attach   = $canonical( array( 'version' => 4, 'state' => 'error', 'provider' => 'zoom', 'provider_id' => null, 'owner_id' => $owner, 'operation' => array( 'operation_id' => 'op-attach', 'kind' => 'create', 'provider' => 'zoom', 'provider_id' => null, 'owner_id' => $owner ) ) );
	$final    = $canonical( array( 'version' => 5, 'state' => 'pending', 'provider' => 'zoom', 'provider_id' => 'meeting-1', 'owner_id' => $owner, 'operation' => array( 'operation_id' => 'op-final', 'kind' => 'update', 'provider' => 'zoom', 'provider_id' => 'meeting-1', 'owner_id' => $owner ) ) );
	$legacy   = $input;
	$legacy['version']  = 0;
	$legacy['evidence'] = $evidence;
	$legacy['local']    = array( 'marker' => 'legacy' );
	$inspect_input      = $input;
	$inspect_input['version']      = 3;
	$inspect_input['operation_id'] = 'op-inspect';
	$attach_input                  = $input;
	$attach_input['provider_id']   = 'meeting-attach';
	$attach_input['operation_id']  = 'op-attach';
	$attach_input['version']       = 4;
	$final_input                   = $input;
	$final_input['version']        = 5;
	$final_input['operation_id']   = 'op-final';
	$create_item                   = $item_for();
	$update_item                   = $item_for();
	$delete_item                   = $item_for();
	unset( $delete_item['menu_order'] );
	$inspect_item = $item_for();
	$attach_item  = $item_for();
	$final_item   = $item_for();
	$resolve_item = $item_for();
	$init_item    = $item_for();
	$legacy_item  = $item_for();
	return array(
		'validate_create'          => $run( static function () use ( $create_item, $topic, $course, $allow, $input, $adapter, $now ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $topic, $course, $allow, $input, array(), $adapter, $now );
		} ),
		'stage_update'             => $run( static function () use ( $update_item, $topic, $course, $allow, $input, $adapter, $now, $token, $read_for, $synced, $update, $affected ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $update_item, $topic, $course, $allow, $input, $adapter, $now, $token, $read_for( $update_item['ID'], $synced ), $update, $affected );
		} ),
		'stage_delete'             => $run( static function () use ( $delete_item, $topic, $course, $allow, $input, $adapter, $now, $token, $read_for, $synced, $update, $affected ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::stage_delete( $delete_item, $topic, $course, $allow, $input, $adapter, $now, $token, $read_for( $delete_item['ID'], $synced ), $update, $affected );
		} ),
		'inspect'                  => $run( static function () use ( $inspect_item, $topic, $course, $allow, $inspect_input, $adapter, $now, $token, $read_for, $inspect, $option, $takeover ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::inspect( $inspect_item, $topic, $course, $allow, $inspect_input, $adapter, $now, $token, $read_for( $inspect_item['ID'], $inspect ), $option, $takeover );
		} ),
		'attach_provider_identity' => $run( static function () use ( $attach_item, $topic, $course, $allow, $attach_input, $adapter, $now, $token, $read_for, $attach, $option, $takeover ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::attach_provider_identity( $attach_item, $topic, $course, $allow, $attach_input, $adapter, $now, $token, $read_for( $attach_item['ID'], $attach ), $option, $takeover );
		} ),
		'finalize_update'          => $run( static function () use ( $final_item, $topic, $course, $allow, $final_input, $adapter, $now, $token, $read_for, $final, $update, $affected, $write, $option, $takeover ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::finalize_update( $final_item, $topic, $course, $allow, $final_input, $adapter, $now, $token, $read_for( $final_item['ID'], $final ), $update, $affected, $write, $option, $takeover );
		} ),
		'resolve_owner'            => $run( static function () use ( $resolve_item, $topic, $course, $allow, $legacy, $adapter, $now, $token, $read_for, $option, $takeover ) {
			return TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $resolve_item, $topic, $course, $allow, $legacy, $adapter, $now, $token, $read_for( $resolve_item['ID'], null ), $option, $takeover );
		} ),
		'initialize_legacy'        => $run( static function () use ( $init_item, $topic, $course, $allow, $legacy, $adapter, $now, $token, $read_for, $option, $takeover ) {
			$adapter->legacy = array( 'provider_id' => 'zoom-1' );
			return TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $init_item, $topic, $course, $allow, $legacy, $adapter, $now, $token, $read_for( $init_item['ID'], null ), $option, $takeover );
		} ),
		'attach_legacy_identity'   => $run( static function () use ( $legacy_item, $topic, $course, $allow, $legacy, $adapter, $now, $token, $read_for, $write, $option, $takeover ) {
			$adapter->legacy = array( 'provider_id' => null );
			$adapter->item   = array( 'title' => 'Zoom' );
			$legacy_attach   = $legacy;
			$legacy_attach['provider_id'] = 'meeting-legacy';
			return TutorPress_Live_Lesson_Sync_Orchestrator::attach_legacy_identity( $legacy_item, $topic, $course, $allow, $legacy_attach, $adapter, $now, $token, $read_for( $legacy_item['ID'], null ), $write, $option, $takeover );
		} ),
	);
};
$filled = array( 'account_id' => 'zoom-account', 'api_key' => 'zoom-key', 'api_secret' => 'zoom-secret' );
$author = $nine( $zoom_input( 17, 55 ), 17 );
$fallback = $nine( $zoom_input( 0, 55 ), 55 );
$present_filled = $nine( $zoom_input( 17, 55, $filled ), 17 );
$present_empty = $nine( $zoom_input( 17, 55, array() ), 17 );
$present_malformed = $nine( $zoom_input( 17, 55, 'malformed' ), 17 );
$google_omitted = $run( static function () use ( $topic, $course, $allow, $adapter, $now ) {
	return TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $topic, $course, $allow, array( 'provider' => 'google_meet', 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 55, 'host_id' => 'host-1' ), array(), $adapter, $now );
} );
$google_present = $run( static function () use ( $topic, $course, $allow, $adapter, $now, $filled ) {
	return TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $topic, $course, $allow, array( 'provider' => 'google_meet', 'acting_user_id' => 44, 'course_author_id' => 17, 'acting_instructor_id' => 55, 'host_id' => 'host-1', 'credentials' => $filled ), array(), $adapter, $now );
} );
$blocked_input = $zoom_input( 17, 55 );
$blocked_input['version'] = 0;
$blocked_input['evidence'] = array( array( 'source' => 'course_author', 'id' => 17, 'label' => 'Author' ) );
$blocked_input['local'] = array( 'marker' => 'legacy' );
$blocked_attach = $blocked_input;
$blocked_attach['provider_id'] = 'meeting-legacy';
$blocked = array(
	'resolve_owner' => $run( static function () use ( $adapter, $item_for, $topic, $course, $allow, $blocked_input, $now, $token, $read_for, $option, $takeover ) {
		$adapter->preflight_result = false;
		$item = $item_for();
		$result = TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $item, $topic, $course, $allow, $blocked_input, $adapter, $now, $token, $read_for( $item['ID'], null ), $option, $takeover );
		$adapter->preflight_result = true;
		return $result;
	} ),
	'initialize_legacy' => $run( static function () use ( $adapter, $item_for, $topic, $course, $allow, $blocked_input, $now, $token, $read_for, $option, $takeover ) {
		$adapter->preflight_result = false;
		$adapter->legacy = array( 'provider_id' => 'zoom-1' );
		$item = $item_for();
		$result = TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $item, $topic, $course, $allow, $blocked_input, $adapter, $now, $token, $read_for( $item['ID'], null ), $option, $takeover );
		$adapter->preflight_result = true;
		return $result;
	} ),
	'attach_legacy_identity' => $run( static function () use ( $adapter, $item_for, $topic, $course, $allow, $blocked_attach, $now, $token, $read_for, $write, $option, $takeover ) {
		$adapter->preflight_result = false;
		$adapter->legacy = array( 'provider_id' => null );
		$adapter->item = array( 'title' => 'Zoom' );
		$item = $item_for();
		$result = TutorPress_Live_Lesson_Sync_Orchestrator::attach_legacy_identity( $item, $topic, $course, $allow, $blocked_attach, $adapter, $now, $token, $read_for( $item['ID'], null ), $write, $option, $takeover );
		$adapter->preflight_result = true;
		return $result;
	} ),
);
$unresolved_input = $zoom_input( 17, 55 );
$unresolved_input['version'] = 0;
$unresolved_input['evidence'] = array();
$unresolved_input['local'] = array( 'marker' => 'legacy' );
$unresolved_attach = $unresolved_input;
$unresolved_attach['provider_id'] = 'meeting-legacy';
$unresolved = array(
	'resolve_owner' => $run( static function () use ( $item_for, $topic, $course, $allow, $unresolved_input, $adapter, $now, $token, $read_for, $option, $takeover ) {
		$item = $item_for();
		return TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $item, $topic, $course, $allow, $unresolved_input, $adapter, $now, $token, $read_for( $item['ID'], null ), $option, $takeover );
	} ),
	'initialize_legacy' => $run( static function () use ( $item_for, $topic, $course, $allow, $unresolved_input, $adapter, $now, $token, $read_for, $option, $takeover ) {
		$adapter->legacy = array( 'provider_id' => 'zoom-1' );
		$item = $item_for();
		return TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $item, $topic, $course, $allow, $unresolved_input, $adapter, $now, $token, $read_for( $item['ID'], null ), $option, $takeover );
	} ),
	'attach_legacy_identity' => $run( static function () use ( $item_for, $topic, $course, $allow, $unresolved_attach, $adapter, $now, $token, $read_for, $write, $option, $takeover ) {
		$adapter->legacy = array( 'provider_id' => null );
		$adapter->item = array( 'title' => 'Zoom' );
		$item = $item_for();
		return TutorPress_Live_Lesson_Sync_Orchestrator::attach_legacy_identity( $item, $topic, $course, $allow, $unresolved_attach, $adapter, $now, $token, $read_for( $item['ID'], null ), $write, $option, $takeover );
	} ),
);
$empty_read = static function () { return array(); };
$unchanged = array(
	'retry' => $run( static function () use ( $item_for, $topic, $course, $allow, $zoom_input, $adapter, $now, $token, $empty_read, $update, $affected, $write, $option, $takeover ) {
		return TutorPress_Live_Lesson_Sync_Orchestrator::retry( $item_for(), $topic, $course, $allow, $zoom_input( 17, 55 ), $adapter, $now, $token, $empty_read, $update, $affected, $write, $option, $takeover );
	} ),
	'abandon_unknown' => $run( static function () use ( $item_for, $topic, $course, $allow, $zoom_input, $now, $token, $empty_read, $update, $affected, $option, $takeover ) {
		return TutorPress_Live_Lesson_Sync_Orchestrator::abandon_unknown( $item_for(), $topic, $course, $allow, $zoom_input( 17, 55 ), $now, $token, $empty_read, $update, $affected, $option, $takeover );
	} ),
);
echo json_encode( array(
	'enabled' => true,
	'files' => count( tutorpress_live_lessons_production_files() ),
	'author' => $author,
	'fallback' => $fallback,
	'filled' => $present_filled,
	'empty' => $present_empty,
	'malformed' => $present_malformed,
	'google' => array( 'omitted' => $google_omitted, 'present' => $google_present ),
	'blocked' => $blocked,
	'unresolved' => $unresolved,
	'unchanged' => $unchanged,
) );
PHP
);
tutorpress_live_lessons_assert(
	0 === $omitted_probe['status'] && '' === $omitted_probe['err'] && is_array( $omitted_probe['data'] ),
	'omitted zoom credentials probe stays isolated ' . $omitted_probe['status'] . ' ' . $omitted_probe['err'] . ' ' . substr( (string) $omitted_probe['out'], 0, 800 )
);
$omitted_call = static function ( $method, $owner = null, $extra = null ) {
	if ( null === $owner ) {
		return array( $method );
	}
	return null === $extra ? array( $method, $owner ) : array( $method, $owner, $extra );
};
$omitted_null  = array( 'kind' => 'null' );
$omitted_owner = static function ( $id ) {
	return array( 'kind' => 'owner', 'owner_id' => $id, 'candidate_ids' => array( $id ) );
};
$omitted_seen = static function ( array $calls, $inserts, array $result ) {
	return array( 'calls' => $calls, 'inserts' => $inserts, 'result' => $result );
};
$omitted_expect = static function ( array $calls, $owner_id, $initialize ) use ( $omitted_seen, $omitted_null, $omitted_owner ) {
	$expected = array();
	foreach ( $calls as $method => $method_calls ) {
		$expected[ $method ] = $omitted_seen( $method_calls, ( 'initialize_legacy' === $method && $initialize ) ? 1 : 0, is_int( $owner_id ) && 'resolve_owner' === $method ? $omitted_owner( $owner_id ) : $omitted_null );
	}
	return $expected;
};
$omitted_paths = static function ( $owner ) use ( $omitted_call ) {
	return array(
		'validate_create'          => array( $omitted_call( 'preflight', $owner ), $omitted_call( 'validate_host', $owner, 'host-1' ) ),
		'stage_update'             => array( $omitted_call( 'preflight', $owner ), $omitted_call( 'validate_host', $owner, 'host-1' ) ),
		'stage_delete'             => array( $omitted_call( 'capabilities' ), $omitted_call( 'preflight', $owner ) ),
		'inspect'                  => array( $omitted_call( 'preflight', $owner ), $omitted_call( 'fetch', $owner, 'meeting-1' ) ),
		'attach_provider_identity' => array( $omitted_call( 'preflight', $owner ), $omitted_call( 'fetch', $owner, 'meeting-attach' ) ),
		'finalize_update'          => array( $omitted_call( 'preflight', $owner ), $omitted_call( 'fetch', $owner, 'meeting-1' ) ),
		'resolve_owner'            => array( $omitted_call( 'preflight', $owner ) ),
		'initialize_legacy'        => array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'preflight', $owner ) ),
		'attach_legacy_identity'   => array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'hydrate_item' ), $omitted_call( 'preflight', $owner ), $omitted_call( 'fetch', $owner, 'meeting-legacy' ) ),
	);
};
$filled_paths = $omitted_paths( 17 );
$filled_paths['resolve_owner'] = array();
$filled_paths['initialize_legacy'] = array( $omitted_call( 'hydrate_legacy' ) );
$filled_paths['attach_legacy_identity'] = array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'hydrate_item' ), $omitted_call( 'fetch', 17, 'meeting-legacy' ) );
$empty_paths = array(
	'validate_create'          => array(),
	'stage_update'             => array(),
	'stage_delete'             => array( $omitted_call( 'capabilities' ) ),
	'inspect'                  => array(),
	'attach_provider_identity' => array(),
	'finalize_update'          => array(),
	'resolve_owner'            => array(),
	'initialize_legacy'        => array( $omitted_call( 'hydrate_legacy' ) ),
	'attach_legacy_identity'   => array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'hydrate_item' ) ),
);
$omitted_blocked = array(
	'resolve_owner'          => $omitted_seen( array( $omitted_call( 'preflight', 17 ) ), 0, $omitted_null ),
	'initialize_legacy'      => $omitted_seen( array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'preflight', 17 ) ), 0, $omitted_null ),
	'attach_legacy_identity' => $omitted_seen( array( $omitted_call( 'hydrate_legacy' ), $omitted_call( 'hydrate_item' ), $omitted_call( 'preflight', 17 ) ), 0, $omitted_null ),
);
$omitted_unresolved = array(
	'resolve_owner'          => $omitted_seen( array(), 0, $omitted_null ),
	'initialize_legacy'      => $omitted_seen( array(), 0, $omitted_null ),
	'attach_legacy_identity' => $omitted_seen( array(), 0, $omitted_null ),
);
$omitted_unchanged = array(
	'retry'            => $omitted_seen( array(), 0, $omitted_null ),
	'abandon_unknown'  => $omitted_seen( array(), 0, $omitted_null ),
);
$omitted_google = array(
	'omitted' => $omitted_seen( array( $omitted_call( 'preflight', 44 ) ), 0, $omitted_null ),
	'present' => $omitted_seen( array( $omitted_call( 'preflight', 44 ) ), 0, $omitted_null ),
);
$omitted_source = static function ( $name ) {
	$method = new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', $name );
	$lines  = file( $method->getFileName() );
	return implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
};
$omitted_helper = $omitted_source( 'zoom_input_owner' );
$omitted_retry  = $omitted_source( 'retry' );
$omitted_abandon = $omitted_source( 'abandon_unknown' );
$omitted_methods = array( 'validate_create', 'stage_update', 'stage_delete', 'inspect', 'attach_provider_identity', 'finalize_update', 'resolve_owner', 'initialize_legacy', 'attach_legacy_identity' );
$omitted_special = array( 'resolve_owner', 'initialize_legacy', 'attach_legacy_identity' );
$omitted_wired   = true;
foreach ( $omitted_methods as $omitted_method ) {
	$omitted_wired = $omitted_wired && false !== strpos( $omitted_source( $omitted_method ), 'zoom_input_owner' );
}
foreach ( $omitted_special as $omitted_method ) {
	$omitted_body  = $omitted_source( $omitted_method );
	$omitted_wired = $omitted_wired && false !== strpos( $omitted_body, "array_key_exists( 'credentials', \$input )" ) && false !== strpos( $omitted_body, 'preflight' );
}
$omitted_encoded = json_encode( $omitted_probe['data'] );
tutorpress_live_lessons_assert(
	true === $omitted_probe['data']['enabled']
	&& 17 === $omitted_probe['data']['files']
	&& $omitted_expect( $omitted_paths( 17 ), 17, true ) === $omitted_probe['data']['author']
	&& $omitted_expect( $omitted_paths( 55 ), 55, true ) === $omitted_probe['data']['fallback'],
	'omitted zoom credentials derive the course author, then the acting instructor ' . substr( (string) $omitted_encoded, 0, 500 )
);
tutorpress_live_lessons_assert(
	$omitted_expect( $filled_paths, 17, true ) === $omitted_probe['data']['filled']
	&& $omitted_expect( $empty_paths, null, false ) === $omitted_probe['data']['empty']
	&& $omitted_expect( $empty_paths, null, false ) === $omitted_probe['data']['malformed']
	&& $omitted_google === $omitted_probe['data']['google'],
	'present zoom credentials and google ownership keep their adapter sequences ' . substr( (string) json_encode( array( 'filled' => $omitted_probe['data']['filled'], 'empty' => $omitted_probe['data']['empty'], 'google' => $omitted_probe['data']['google'] ) ), 0, 500 )
);
tutorpress_live_lessons_assert(
	$omitted_blocked === $omitted_probe['data']['blocked']
	&& $omitted_unresolved === $omitted_probe['data']['unresolved']
	&& $omitted_unchanged === $omitted_probe['data']['unchanged'],
	'omitted zoom preflight follows owner resolution and retry stays unchanged ' . substr( (string) json_encode( array( 'blocked' => $omitted_probe['data']['blocked'], 'unresolved' => $omitted_probe['data']['unresolved'], 'unchanged' => $omitted_probe['data']['unchanged'] ) ), 0, 500 )
);
tutorpress_live_lessons_assert(
	$omitted_wired
	&& false !== strpos( $omitted_helper, 'TutorPress_Live_Lesson_Owner_Resolver::zoom(' )
	&& false !== strpos( $omitted_helper, 'zoom_eligible' )
	&& strpos( $omitted_helper, 'TutorPress_Live_Lesson_Owner_Resolver::zoom(' ) < strpos( $omitted_helper, '$credentials' )
	&& false === strpos( $omitted_retry, 'zoom_input_owner' )
	&& false === strpos( $omitted_retry, 'credentials' )
	&& false === strpos( $omitted_retry, 'preflight' )
	&& false === strpos( $omitted_abandon, 'zoom_input_owner' )
	&& false === strpos( $omitted_abandon, 'credentials' )
	&& false === strpos( $omitted_abandon, 'preflight' )
	&& is_string( $omitted_encoded )
	&& false === strpos( $omitted_encoded, 'fake-credential-value' )
	&& false === strpos( $omitted_encoded, 'zoom-account' )
	&& false === strpos( $omitted_encoded, 'zoom-key' )
	&& false === strpos( $omitted_encoded, 'zoom-secret' )
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& false === class_exists( 'TutorPress_REST_Live_Lessons_Controller', false ),
	'omitted zoom credentials stay out of encoded output and the false gate stays eighteen files'
);
tutorpress_live_lessons_pass( 'omitted zoom credentials resolve the owner and require preflight' );

$matrix = isset( $binding_probe['data']['matrix'] ) && is_array( $binding_probe['data']['matrix'] ) ? $binding_probe['data']['matrix'] : array();
$matrix_denied_row = static function ( $row, $code ) {
	return is_array( $row )
		&& 'error' === $row['packed']['kind']
		&& $code === $row['packed']['code']
		&& 401 === $row['packed']['status']
		&& array( array( 'can', 'edit_post', 15 ) ) === $row['caps']
		&& array() === $row['metas']
		&& array() === $row['reads']
		&& array() === $row['services']
		&& 1 === $row['bindings']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& array() === $row['stages']
		&& array() === $row['dispatches']
		&& array() === $row['deletes']
		&& array() === $row['duplicates']
		&& array() === $row['reconciles']
		&& array() === $row['attaches']
		&& true === $row['boundary']
		&& array( 0, 0 ) === $row['lifecycle']
		&& array( array(), array() ) === $row['hosts'];
};
$matrix_false_row = static function ( $row, $code, $message, $status ) {
	return is_array( $row )
		&& 'error' === $row['packed']['kind']
		&& $code === $row['packed']['code']
		&& $message === $row['packed']['message']
		&& $status === $row['packed']['status']
		&& 0 === $row['bindings']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& true === $row['boundary'];
};
$matrix_identity_free = static function ( $names ) use ( $matrix ) {
	$blocked = isset( $matrix['identity']['names'] ) ? $matrix['identity']['names'] : array();
	if ( ! is_array( $names ) || array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) !== $blocked ) {
		return false;
	}
	foreach ( $blocked as $name ) {
		if ( in_array( $name, $names, true ) ) {
			return false;
		}
	}
	return true;
};
$matrix_rejected = static function ( $row ) {
	return is_array( $row )
		&& 'error' === $row['packed']['kind']
		&& 'live_lesson_supplied_identity' === $row['packed']['code']
		&& 400 === $row['packed']['status']
		&& 0 === $row['bindings']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& array() === $row['services']
		&& true === $row['boundary'];
};
$matrix_conflict_row = static function ( $row ) {
	return is_array( $row )
		&& 'error' === $row['packed']['kind']
		&& 'live_lesson_schedule_conflict' === $row['packed']['code']
		&& 400 === $row['packed']['status']
		&& 0 === $row['bindings']
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& true === $row['boundary'];
};
$matrix_delete_kept = static function ( $row, $adapter, $ledger ) {
	$call  = isset( $row['deletes'][0] ) && is_array( $row['deletes'][0] ) ? $row['deletes'][0] : array();
	$input = isset( $call['input'] ) && is_array( $call['input'] ) ? $call['input'] : array();
	$coded = is_array( $row ) ? json_encode( $row['deletes'] ) : '';
	return is_array( $row )
		&& 'live_lesson_supplied_identity' !== $row['packed']['code']
		&& 400 !== $row['packed']['status']
		&& 1 === count( $row['deletes'] )
		&& $adapter === $call['adapter']
		&& false === array_key_exists( 'provider_id', $input )
		&& is_string( $coded )
		&& false === strpos( $coded, 'evt-forged-delete' )
		&& $ledger === $row['ledger']
		&& array() === $row['duplicates']
		&& array() === $row['attaches'];
};
$matrix_duplicate_kept = static function ( $row ) {
	$call   = isset( $row['duplicates'][0] ) && is_array( $row['duplicates'][0] ) ? $row['duplicates'][0] : array();
	$source = isset( $call['source']['ID'] ) ? (int) $call['source']['ID'] : 0;
	$target = isset( $call['target']['ID'] ) ? (int) $call['target']['ID'] : 0;
	$coded  = is_array( $row ) ? json_encode( $row['duplicates'] ) : '';
	return is_array( $row )
		&& 'live_lesson_supplied_identity' !== $row['packed']['code']
		&& 400 !== $row['packed']['status']
		&& 1 === count( $row['duplicates'] )
		&& 530 === $source
		&& 40 === $target
		&& 0 === $row['adapters']
		&& array( 'zoom' ) === $row['ledger']
		&& is_string( $coded )
		&& false === strpos( $coded, 'evt-forged-duplicate' );
};
$matrix_attach_kept = static function ( $row, $adapter ) {
	$call  = isset( $row['attaches'][0] ) && is_array( $row['attaches'][0] ) ? $row['attaches'][0] : array();
	$input = isset( $call['input'] ) && is_array( $call['input'] ) ? $call['input'] : array();
	return is_array( $row )
		&& 1 === count( $row['attaches'] )
		&& $adapter === $call['adapter']
		&& 'evt-forged-attach' === ( isset( $input['provider_id'] ) ? $input['provider_id'] : '' )
		&& $adapter !== $input['provider_id'];
};
$matrix_schema = isset( $matrix['schemas'] ) && is_array( $matrix['schemas'] ) ? $matrix['schemas'] : array();
$matrix_uncertain_schema = array();
$matrix_legacy_schema    = array();
foreach ( isset( $matrix_schema['attach'] ) && is_array( $matrix_schema['attach'] ) ? $matrix_schema['attach'] : array() as $matrix_route => $matrix_fields ) {
	if ( false !== strpos( (string) $matrix_route, '/attach/uncertain-create' ) ) {
		$matrix_uncertain_schema = $matrix_fields;
	}
	if ( false !== strpos( (string) $matrix_route, '/attach/legacy' ) ) {
		$matrix_legacy_schema = $matrix_fields;
	}
}
$matrix_required = static function ( $fields, $name ) {
	foreach ( $fields as $field ) {
		if ( is_array( $field ) && $name === $field['name'] ) {
			return true === $field['required'];
		}
	}
	return null;
};
$matrix_present = static function ( $fields, $name ) {
	foreach ( $fields as $field ) {
		if ( is_array( $field ) && $name === $field['name'] ) {
			return true;
		}
	}
	return false;
};
$matrix_unregistered = true;
foreach ( array( 'inspect', 'retry', 'resolve_owner', 'abandon_unknown', 'uncertain', 'legacy_attach' ) as $matrix_false_key ) {
	$row = isset( $matrix['false'][ $matrix_false_key ] ) ? $matrix['false'][ $matrix_false_key ] : array();
	$matrix_unregistered = $matrix_unregistered && is_array( $row ) && false === $row['entered'] && true === $row['registered'] && 0 === $row['bindings'] && array( 'zoom' ) === $row['ledger'];
}
$matrix_denied_ok = isset( $matrix['denied'] )
	&& $matrix_denied_row( $matrix['denied']['collection'], 'rest_cannot_read' )
	&& $matrix_denied_row( $matrix['denied']['zoom'], 'rest_cannot_read' )
	&& $matrix_denied_row( $matrix['denied']['item'], 'rest_cannot_read' )
	&& $matrix_denied_row( $matrix['denied']['create'], 'rest_cannot_create' )
	&& $matrix_denied_row( $matrix['denied']['synced_patch'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['unlinked_patch'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['delete'], 'rest_cannot_delete' )
	&& $matrix_denied_row( $matrix['denied']['duplicate'], 'rest_cannot_create' )
	&& $matrix_denied_row( $matrix['denied']['inspect'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['retry'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['resolve_owner'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['abandon_unknown'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['uncertain'], 'rest_cannot_edit' )
	&& $matrix_denied_row( $matrix['denied']['legacy_attach'], 'rest_cannot_edit' );
tutorpress_live_lessons_assert(
	$matrix_denied_ok
	&& false === $matrix['false']['collection']['entered']
	&& false === $matrix['false']['zoom']['entered']
	&& false === $matrix['false']['item']['entered']
	&& false === $matrix['false']['create']['entered']
	&& false === $matrix['false']['synced_patch']['entered']
	&& false === $matrix['false']['unlinked_patch']['entered']
	&& false === $matrix['false']['delete']['entered']
	&& false === $matrix['false']['duplicate']['entered']
	&& $matrix_unregistered
	&& $matrix_identity_free( $matrix_schema['create'] )
	&& $matrix_identity_free( $matrix_schema['patch'] )
	&& $matrix_identity_free( $matrix_schema['delete'] )
	&& $matrix_identity_free( $matrix_schema['duplicate'] )
	&& $matrix_identity_free( $matrix_schema['reconcile'] )
	&& true === $matrix_required( $matrix_uncertain_schema, 'provider_id' )
	&& true === $matrix_required( $matrix_uncertain_schema, 'version' )
	&& true === $matrix_required( $matrix_uncertain_schema, 'operation_id' )
	&& false === $matrix_present( $matrix_uncertain_schema, 'selected_owner_id' )
	&& true === $matrix_required( $matrix_legacy_schema, 'provider_id' )
	&& true === $matrix_required( $matrix_legacy_schema, 'version' )
	&& false === $matrix_required( $matrix_legacy_schema, 'selected_owner_id' )
	&& true === $matrix_present( $matrix_legacy_schema, 'selected_owner_id' )
	&& false === $matrix_present( $matrix_legacy_schema, 'operation_id' )
	&& $matrix_conflict_row( $matrix['conflict']['create'] )
	&& $matrix_conflict_row( $matrix['conflict']['patch'] )
	&& $matrix_rejected( $matrix['supplied']['create'] )
	&& $matrix_rejected( $matrix['supplied']['patch'] )
	&& $matrix_rejected( $matrix['supplied']['reconcile'] )
	&& $matrix_delete_kept( $matrix['ignored']['delete_google'], 'google_meet', array( 'zoom', 'google_meet' ) )
	&& $matrix_delete_kept( $matrix['ignored']['delete_zoom'], 'zoom', array( 'zoom', 'zoom' ) )
	&& $matrix_duplicate_kept( $matrix['ignored']['duplicate'] )
	&& $matrix_attach_kept( $matrix['attach']['google'], 'google_meet' )
	&& $matrix_attach_kept( $matrix['attach']['envelope'], 'zoom' )
	&& $matrix_attach_kept( $matrix['attach']['zoom'], 'zoom' )
	&& $matrix_attach_kept( $matrix['attach']['legacy'], 'google_meet' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'gate-true routes deny broad edit_posts before adapter resolution and keep disjoint identity schemas'
);
tutorpress_live_lessons_pass( 'gate-true routes deny broad edit_posts and keep disjoint identity schemas' );

$pass_2b_codes = array(
	'collection'      => 'rest_cannot_read',
	'zoom'            => 'rest_cannot_read',
	'item'            => 'rest_cannot_read',
	'create'          => 'rest_cannot_create',
	'synced_patch'    => 'rest_cannot_edit',
	'unlinked_patch'  => 'rest_cannot_edit',
	'delete'          => 'rest_cannot_delete',
	'duplicate'       => 'rest_cannot_create',
	'inspect'         => 'rest_cannot_edit',
	'retry'           => 'rest_cannot_edit',
	'resolve_owner'   => 'rest_cannot_edit',
	'abandon_unknown' => 'rest_cannot_edit',
	'uncertain'       => 'rest_cannot_edit',
	'legacy_attach'   => 'rest_cannot_edit',
);
$pass_2b_denied   = true;
$pass_2b_identity = $create_error( $creates['forged'], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 ) && 0 === $creates['forged']['bindings'] && $create_stopped( $creates['forged'] );
foreach ( $pass_2b_codes as $pass_2b_path => $pass_2b_code ) {
	$pass_2b_denied = $pass_2b_denied && $matrix_denied_row( $matrix['denied'][ $pass_2b_path ], $pass_2b_code );
}
foreach ( array( 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId' ) as $pass_2b_key ) {
	$pass_2b_patch   = $updates['identity'][ $pass_2b_key ];
	$pass_2b_inspect = $reconciles['identity'][ $pass_2b_key ];
	$pass_2b_identity = $pass_2b_identity
		&& $update_error( $pass_2b_patch, 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
		&& 0 === $pass_2b_patch['bindings']
		&& $update_stopped( $pass_2b_patch )
		&& $reconcile_failed( $pass_2b_inspect, 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
		&& $reconcile_quiet( $pass_2b_inspect, array(), array(), 0 );
}
tutorpress_live_lessons_assert(
	$pass_2b_denied
	&& $zoom_kept( $zoom_case['author'], 15, 17 )
	&& $zoom_stopped( $zoom_case['missing'], array( 'clock', 'token', 'user', array( 'post', 99 ) ) )
	&& $zoom_stopped( $zoom_case['wrong_type'], array( 'clock', 'token', 'user', array( 'post', 16 ) ) )
	&& $zoom_stopped( $zoom_case['denied'], array( 'clock', 'token', 'user', array( 'post', 8 ), array( 'can', 'edit_post', 8 ) ) )
	&& $pass_2b_identity
	&& $forged_rows === $mutation_probe['data']['forged']
	&& $matrix_delete_kept( $matrix['ignored']['delete_google'], 'google_meet', array( 'zoom', 'google_meet' ) )
	&& $matrix_delete_kept( $matrix['ignored']['delete_zoom'], 'zoom', array( 'zoom', 'zoom' ) )
	&& $matrix_duplicate_kept( $matrix['ignored']['duplicate'] )
	&& true === $matrix_required( $matrix_uncertain_schema, 'provider_id' )
	&& true === $matrix_required( $matrix_uncertain_schema, 'operation_id' )
	&& false === $matrix_present( $matrix_uncertain_schema, 'selected_owner_id' )
	&& true === $matrix_required( $matrix_legacy_schema, 'provider_id' )
	&& false === $matrix_present( $matrix_legacy_schema, 'operation_id' )
	&& true === $matrix_present( $matrix_legacy_schema, 'selected_owner_id' )
	&& true === $blocked_stopped
	&& $reconcile_failed( $attaches['blocked']['uncertain_selected_owner_id'], 'live_lesson_supplied_identity', 'Supplied provider identity is not accepted.', 400 )
	&& $attach_quiet( $attaches['blocked']['uncertain_selected_owner_id'], array(), array(), 0 ),
	'gate-true authorization, owner-scoped users, server identity, attach shapes, and crafted denials stay apart'
);
tutorpress_live_lessons_pass( 'gate-true authorization and server identity stay apart' );

$google_case = $binding_probe['data']['google_settings'];
$google_boot = array( 'clock', 'token', 'user' );
$google_stopped = static function ( $read, $calls ) {
	return is_array( $read )
		&& 'error' === $read['packed']['kind']
		&& $calls === $read['calls']
		&& array( 'zoom' ) === $read['ledger']
		&& array() === $read['services']
		&& 0 === $read['adapters']
		&& array() === $read['settings']
		&& array() === $read['zoom_settings']
		&& array() === $read['preflight']
		&& array() === $read['hosts'];
};
$google_invalid_ok = is_array( $google_case ) && 12 === count( $google_case['invalid'] );
foreach ( $google_case['invalid'] as $google_invalid_read ) {
	$google_invalid_ok = $google_invalid_ok
		&& $google_stopped( $google_invalid_read, $google_boot )
		&& 1 === $google_invalid_read['bindings']
		&& 'invalid_course_id' === $google_invalid_read['packed']['code']
		&& 'Invalid course ID.' === $google_invalid_read['packed']['message']
		&& 404 === $google_invalid_read['packed']['status'];
}
$google_chain = array( 'clock', 'token', 'user', array( 'post', 15 ), array( 'can', 'edit_post', 15 ) );
$google_page  = array(
	'success' => true,
	'message' => 'Google Meet settings retrieved successfully.',
	'data'    => $google_case['script'],
);
$google_schema = array(
	array(
		'name'              => 'course_id',
		'type'              => 'integer',
		'required'          => true,
		'enum'              => null,
		'default'           => null,
		'sanitize_callback' => 'absint',
	),
);
$google_encoded = json_encode( $google_case['authorized']['packed']['data'] );
$google_quiet   = static function ( $read ) {
	$coded = json_encode( $read['calls'] );
	return is_string( $coded ) && false === strpos( $coded, 'edit_posts' );
};
tutorpress_live_lessons_assert(
	false === $google_case['false_gate']['entered']
	&& array( 'method' => 'GET', 'callback' => 'get_google_meet_settings', 'schema' => $google_schema ) === $google_case['false_schema']
	&& array( 'method' => 'GET', 'callback' => 'get_google_meet_settings', 'schema' => $google_schema ) === $google_case['true_schema']
	&& $google_invalid_ok
	&& $google_stopped( $google_case['missing'], array( 'clock', 'token', 'user', array( 'post', 99 ) ) )
	&& 'invalid_course_id' === $google_case['missing']['packed']['code']
	&& 404 === $google_case['missing']['packed']['status']
	&& $google_stopped( $google_case['wrong_type'], array( 'clock', 'token', 'user', array( 'post', 16 ) ) )
	&& 'invalid_course_id' === $google_case['wrong_type']['packed']['code']
	&& 404 === $google_case['wrong_type']['packed']['status']
	&& $google_stopped( $google_case['broad'], $google_chain )
	&& $google_quiet( $google_case['broad'] )
	&& 'rest_cannot_read' === $google_case['broad']['packed']['code']
	&& 'Sorry, you are not allowed to view live lessons for this course.' === $google_case['broad']['packed']['message']
	&& 401 === $google_case['broad']['packed']['status'],
	'false-gate Google settings stay on the addon response and unauthorized course ids stop before an adapter'
);
tutorpress_live_lessons_assert(
	'response' === $google_case['authorized']['packed']['kind']
	&& $google_page === $google_case['authorized']['packed']['data']
	&& array_merge( $google_chain, array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ) ) ) === $google_case['authorized']['calls']
	&& array( 'zoom', 'google_meet' ) === $google_case['authorized']['ledger']
	&& array( array( 'service', 'TutorPress_Live_Lesson_Provider_Adapter:google_meet' ) ) === $google_case['authorized']['services']
	&& 1 === $google_case['authorized']['bindings']
	&& 1 === $google_case['authorized']['adapters']
	&& array( 77 ) === $google_case['authorized']['settings']
	&& array() === $google_case['authorized']['zoom_settings']
	&& array() === $google_case['authorized']['preflight']
	&& array() === $google_case['authorized']['hosts']
	&& $google_stopped( $google_case['null_settings'], array() ) === false
	&& 'error' === $google_case['null_settings']['packed']['kind']
	&& 'google_meet_not_authorized' === $google_case['null_settings']['packed']['code']
	&& 'Google Meet is not authorized. Please configure Google Meet API credentials in Tutor LMS.' === $google_case['null_settings']['packed']['message']
	&& 400 === $google_case['null_settings']['packed']['status']
	&& 1 === $google_case['null_settings']['adapters']
	&& array( 77 ) === $google_case['null_settings']['settings']
	&& array( 'zoom', 'google_meet' ) === $google_case['null_settings']['ledger']
	&& $google_stopped( $google_case['null_owner'], $google_chain )
	&& 'google_owner_unavailable' === $google_case['null_owner']['packed']['code']
	&& 'A Google owner could not be resolved for this course.' === $google_case['null_owner']['packed']['message']
	&& 400 === $google_case['null_owner']['packed']['status']
	&& true === $google_case['guard']
	&& true === $google_case['order']
	&& is_string( $google_encoded )
	&& false === strpos( $google_encoded, 'hidden-sentinel' )
	&& false === strpos( $google_encoded, 'supplied-token-value' )
	&& false === strpos( $google_encoded, 'supplied-secret-value' )
	&& false === strpos( $google_encoded, 'supplied-credential-value' )
	&& false === strpos( $google_encoded, 'supplied-password-value' )
	&& false === strpos( $google_encoded, 'supplied-api-key-value' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'gate-true Google settings delegate to the authorized owner through one settings call'
);
tutorpress_live_lessons_pass( 'gate-true Google settings delegate to the authorized owner' );

$pass_2f_empty = true;
foreach ( $google_case['invalid'] as $pass_2f_invalid ) {
	$pass_2f_empty = $pass_2f_empty && array() === $pass_2f_invalid['settings'];
}
$pass_2f_page = $google_case['authorized']['packed']['data'];
$pass_2f_body = is_array( $pass_2f_page ) && isset( $pass_2f_page['data'] ) && is_array( $pass_2f_page['data'] ) ? $pass_2f_page['data'] : array();
$pass_2f_quiet = is_string( $google_encoded );
foreach ( array( 'supplied-token-value', 'supplied-secret-value', 'supplied-credential-value', 'supplied-password-value', 'supplied-api-key-value', 'hidden-sentinel' ) as $pass_2f_secret ) {
	$pass_2f_quiet = $pass_2f_quiet && false === strpos( $google_encoded, $pass_2f_secret );
}
$pass_2f_hits = 0;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__, 3 ) . '/assets/js/src', FilesystemIterator::SKIP_DOTS ) ) as $pass_2f_file ) {
	if ( ! $pass_2f_file->isFile() ) {
		continue;
	}
	$pass_2f_text = file_get_contents( $pass_2f_file->getPathname() );
	$pass_2f_hits += is_string( $pass_2f_text ) && false !== strpos( $pass_2f_text, 'google-meet/settings' ) ? 1 : 0;
}
tutorpress_live_lessons_assert(
	$pass_2f_empty
	&& array() === $google_case['missing']['settings']
	&& array() === $google_case['wrong_type']['settings']
	&& array() === $google_case['broad']['settings']
	&& $google_chain === $google_case['broad']['calls']
	&& $google_chain === array_slice( $google_case['authorized']['calls'], 0, count( $google_chain ) )
	&& array( 77 ) === $google_case['authorized']['settings']
	&& $google_case['script'] === $pass_2f_body
	&& array( 'is_authorized', 'ready', 'preferences' ) === array_keys( $pass_2f_body )
	&& 'Google Meet settings retrieved successfully.' === $pass_2f_page['message']
	&& array( 'method' => 'GET', 'callback' => 'get_google_meet_settings', 'schema' => $google_schema ) === $google_case['false_schema']
	&& 'check_permission' === $google_case['false_permission']
	&& $pass_2f_quiet
	&& 0 === $pass_2f_hits,
	'gate-true google settings keep the spy envelope and the false request'
);
tutorpress_live_lessons_pass( 'gate-true google settings keep the spy envelope and the false request' );

$topic_script = str_replace(
	'__TUTORPRESS_ROOT__',
	var_export( $root, true ),
	<<<'PHP'
<?php
$root = __TUTORPRESS_ROOT__;
require_once $root . '/tests/live-lessons/fakes.php';
require_once $root . '/tests/live-lessons/production-loader.php';

class TutorPress_Topic_Contents_Probe {
	public static $held    = false;
	public static $can     = 'allow';
	public static $missing = '';
	public static $meta    = array();
	public static $posts   = array();
	public static $queries = array();
	public static $caps    = array();
	public static $inserts = array();
	public static $added   = array();
	public static $deletes = array();
	public static $handlers = array();
	public static $stored  = array();
	public static $next_id = 100;
	public static $all_meta = array();
}

function tutorpress_topic_contents_post( $id, $type, $title, $order, $status, $parent ) {
	$post              = new stdClass();
	$post->ID          = $id;
	$post->post_type   = $type;
	$post->post_title  = $title;
	$post->menu_order  = $order;
	$post->post_status = $status;
	$post->post_parent = $parent;
	$post->post_content = '';
	$post->post_author  = 42;
	return $post;
}

function get_posts( $args ) {
	TutorPress_Topic_Contents_Probe::$queries[] = $args;
	$posts = array(
		tutorpress_topic_contents_post( 11, 'lesson', 'Read', 1, 'publish', 8 ),
		tutorpress_topic_contents_post( 12, 'tutor_quiz', 'Check', 2, 'publish', 8 ),
		tutorpress_topic_contents_post( 13, 'tutor_quiz', 'Draft', 3, 'draft', 8 ),
		tutorpress_topic_contents_post( 21, 'tutor-google-meet', 'Meet', 4, 'publish', 8 ),
		tutorpress_topic_contents_post( 22, 'tutor_zoom_meeting', 'Zoom', 5, 'publish', 8 ),
	);
	if ( TutorPress_Topic_Contents_Probe::$held ) {
		$posts[] = tutorpress_topic_contents_post( 31, 'tutor-google-meet', 'Held draft', 6, 'draft', 8 );
		$posts[] = tutorpress_topic_contents_post( 32, 'tutor_zoom_meeting', 'Held private', 7, 'private', 8 );
		$posts[] = tutorpress_topic_contents_post( 33, 'tutor-google-meet', 'Held pending', 8, 'pending', 8 );
		$posts[] = tutorpress_topic_contents_post( 34, 'tutor_zoom_meeting', 'Held future', 9, 'future', 8 );
	}
	return $posts;
}

function get_post( $id ) {
	TutorPress_Topic_Contents_Probe::$posts[] = (int) $id;
	if ( isset( TutorPress_Topic_Contents_Probe::$stored[ (int) $id ] ) ) {
		return TutorPress_Topic_Contents_Probe::$stored[ (int) $id ];
	}
	if ( 'topic' === TutorPress_Topic_Contents_Probe::$missing && 8 === (int) $id ) {
		return null;
	}
	if ( 'nontopic' === TutorPress_Topic_Contents_Probe::$missing && 8 === (int) $id ) {
		return tutorpress_topic_contents_post( 8, 'lesson', 'Not a topic', 0, 'publish', 7 );
	}
	if ( 'course' === TutorPress_Topic_Contents_Probe::$missing && 7 === (int) $id ) {
		return null;
	}
	if ( 'noncourse' === TutorPress_Topic_Contents_Probe::$missing && 7 === (int) $id ) {
		return tutorpress_topic_contents_post( 7, 'lesson', 'Not a course', 0, 'publish', 0 );
	}
	if ( 8 === (int) $id ) {
		return tutorpress_topic_contents_post( 8, 'topics', 'Topic', 0, 'publish', 7 );
	}
	if ( 7 === (int) $id ) {
		return tutorpress_topic_contents_post( 7, 'courses', 'Course', 0, 'publish', 0 );
	}
	return null;
}

function get_post_meta( $id, $key = '', $single = false ) {
	TutorPress_Topic_Contents_Probe::$meta[] = array( (int) $id, (string) $key, (bool) $single );
	if ( '' === (string) $key ) {
		return isset( TutorPress_Topic_Contents_Probe::$all_meta[ (int) $id ] ) ? TutorPress_Topic_Contents_Probe::$all_meta[ (int) $id ] : array();
	}
	if ( 'tutor_quiz_option' === $key && 12 === (int) $id ) {
		return array( 'quiz_type' => 'tutor_h5p_quiz' );
	}
	if ( '_tutorpress_live_lesson_sync' !== $key ) {
		return '';
	}
	if ( 22 === (int) $id ) {
		return array(
			'error'                   => null,
			'operation'               => null,
			'owner_id'                => 9,
			'provenance'              => null,
			'provider'                => 'zoom',
			'provider_id'             => 'evt-zoom-1',
			'reconciliation_required' => false,
			'schema_version'          => 1,
			'state'                   => 'synced',
			'version'                 => 6,
			'warning'                 => 'hidden-sentinel',
		);
	}
	if ( in_array( (int) $id, array( 31, 32, 33, 34 ), true ) ) {
		return array(
			'token'     => 'credential-token-value',
			'secret'    => 'credential-secret-value',
			'credential'=> 'credential-value',
			'password'  => 'credential-password-value',
			'api_key'   => 'credential-api-key-value',
			'note'      => 'hidden-sentinel',
		);
	}
	return '';
}

function current_user_can( $cap, $id = 0 ) {
	TutorPress_Topic_Contents_Probe::$caps[] = array( (string) $cap, (int) $id );
	if ( 'broad' === TutorPress_Topic_Contents_Probe::$can ) {
		return 'edit_posts' === $cap;
	}
	return 'edit_post' === $cap && 7 === (int) $id;
}

class TutorPress_Lesson_Video_Duration {
	public static function get_lesson_video_summary( $id ) {
		unset( $id );
		return array( 'source' => 'html5' );
	}
}

function tutor() {
	$tutor                    = new stdClass();
	$tutor->course_post_type = 'courses';
	return $tutor;
}

function maybe_unserialize( $data ) {
	return $data;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	unset( $wp_error );
	$id = ++TutorPress_Topic_Contents_Probe::$next_id;
	$post = tutorpress_topic_contents_post(
		$id,
		isset( $postarr['post_type'] ) ? $postarr['post_type'] : '',
		isset( $postarr['post_title'] ) ? $postarr['post_title'] : '',
		isset( $postarr['menu_order'] ) ? $postarr['menu_order'] : 0,
		isset( $postarr['post_status'] ) ? $postarr['post_status'] : '',
		isset( $postarr['post_parent'] ) ? $postarr['post_parent'] : 0
	);
	$post->post_content = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
	TutorPress_Topic_Contents_Probe::$stored[ $id ] = $post;
	TutorPress_Topic_Contents_Probe::$inserts[]     = $postarr;
	return $id;
}

function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
	unset( $unique );
	TutorPress_Topic_Contents_Probe::$added[] = array( (int) $post_id, (string) $meta_key, $meta_value );
	return true;
}

function wp_delete_post( $post_id, $force_delete = false ) {
	TutorPress_Topic_Contents_Probe::$deletes[] = array( (int) $post_id, true === $force_delete );
	unset( TutorPress_Topic_Contents_Probe::$stored[ (int) $post_id ] );
	return true;
}

function rest_authorization_required_code() {
	return 401;
}

$GLOBALS['wpdb'] = new class() {
	public $posts = 'wp_posts';
	public function query( $sql ) {
		return true;
	}
	public function prepare( $query, $id ) {
		unset( $id );
		return $query;
	}
	public function get_var( $query ) {
		unset( $query );
		return 4;
	}
};

class TutorPress_Topic_Array_Request extends WP_REST_Request implements ArrayAccess {
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->params );
	}
	public function offsetGet( $offset ): mixed {
		return $this->get_param( $offset );
	}
	public function offsetSet( $offset, $value ): void {
		$this->set_param( $offset, $value );
	}
	public function offsetUnset( $offset ): void {
		unset( $this->params[ $offset ] );
	}
}

require_once $root . '/includes/rest/class-rest-controller.php';
require_once $root . '/includes/rest/class-topics-controller.php';

$controller = new class() extends TutorPress_REST_Topics_Controller {
	public $enabled = false;
	public $copy_script = array( 'copied' => true );
	public $copies = array();
	protected function replacement_enabled() {
		return $this->enabled;
	}
	public function contents( $topic_id ) {
		return $this->get_topic_contents( $topic_id );
	}
	protected function copy_topic_children( array $source, array $course, array $destination, array $target, array $children, $can ) {
		$this->copies[] = array(
			'source'      => $source,
			'course'      => $course,
			'destination' => $destination,
			'target'      => $target,
			'children'    => $children,
			'edit_post'   => true === $can( 'edit_post', 7 ),
			'edit_posts'  => true === $can( 'edit_posts', 7 ),
		);
		return $this->copy_script;
	}
	public $cascade_script = null;
	public $cascades       = array();
	protected function cascade_execute( array $topic, array $course, array $children, $can ) {
		$this->cascades[] = array(
			'topic'      => $topic,
			'course'     => $course,
			'children'   => $children,
			'edit_post'  => true === $can( 'edit_post', 7 ),
			'edit_posts' => true === $can( 'edit_posts', 7 ),
		);
		return $this->cascade_script;
	}
};

class TutorPress_REST_Lessons_Controller {
	public function delete_item( $request ) {
		TutorPress_Topic_Contents_Probe::$handlers[] = array( 'lesson', (int) $request->get_param( 'id' ) );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}

class TutorPress_REST_Assignments_Controller {
	public function delete_item( $request ) {
		TutorPress_Topic_Contents_Probe::$handlers[] = array( 'assignment', (int) $request->get_param( 'id' ) );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}

class TutorPress_REST_Quizzes_Controller {
	public function delete_item( $request ) {
		TutorPress_Topic_Contents_Probe::$handlers[] = array( 'quiz', (int) $request->get_param( 'id' ) );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}

class TutorPress_REST_Live_Lessons_Controller {
	public function delete_item( $request ) {
		TutorPress_Topic_Contents_Probe::$handlers[] = array( 'live', (int) $request->get_param( 'id' ) );
		return new WP_Error( 'live_lesson_delete_failed', 'secret-child-error' );
	}
}

$topic_run = static function ( $enabled, $held, $can, $missing ) use ( $controller ) {
	TutorPress_Topic_Contents_Probe::$held    = $held;
	TutorPress_Topic_Contents_Probe::$can     = $can;
	TutorPress_Topic_Contents_Probe::$missing = $missing;
	TutorPress_Topic_Contents_Probe::$meta    = array();
	TutorPress_Topic_Contents_Probe::$posts   = array();
	TutorPress_Topic_Contents_Probe::$queries = array();
	TutorPress_Topic_Contents_Probe::$caps    = array();
	$controller->enabled                      = $enabled;
	$rows                                     = $controller->contents( 8 );
	$sync                                     = array();
	foreach ( TutorPress_Topic_Contents_Probe::$meta as $read ) {
		if ( '_tutorpress_live_lesson_sync' === $read[1] ) {
			$sync[] = array( $read[0], $read[2] );
		}
	}
	return array(
		'rows'  => $rows,
		'sync'  => $sync,
		'posts' => TutorPress_Topic_Contents_Probe::$posts,
		'query' => TutorPress_Topic_Contents_Probe::$queries,
		'caps'  => TutorPress_Topic_Contents_Probe::$caps,
	);
};

$slice = static function ( $method ) {
	$lines = file( $method->getFileName() );
	return implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
};
$contents_method = new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'get_topic_contents' );
$enabled_method  = new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'replacement_enabled' );
$contents_body   = $slice( $contents_method );
$enabled_body    = $slice( $enabled_method );
$duplicate_pack = static function ( $result ) {
	if ( $result instanceof WP_Error ) {
		$data = $result->get_error_data();
		return array(
			'kind'    => 'error',
			'code'    => $result->get_error_code(),
			'message' => $result->get_error_message(),
			'status'  => is_array( $data ) && isset( $data['status'] ) ? $data['status'] : null,
		);
	}
	if ( $result instanceof WP_REST_Response ) {
		return array( 'kind' => 'response', 'data' => $result->get_data() );
	}
	return array( 'kind' => 'other' );
};
$duplicate_run = static function ( $enabled, $id, $course_id, $can, $script ) use ( $controller, $duplicate_pack ) {
	TutorPress_Topic_Contents_Probe::$held    = false;
	TutorPress_Topic_Contents_Probe::$can     = $can;
	TutorPress_Topic_Contents_Probe::$missing = '';
	TutorPress_Topic_Contents_Probe::$inserts = array();
	TutorPress_Topic_Contents_Probe::$added   = array();
	TutorPress_Topic_Contents_Probe::$deletes = array();
	TutorPress_Topic_Contents_Probe::$stored  = array();
	$controller->copies                       = array();
	$controller->copy_script                  = $script;
	$controller->enabled                      = $enabled;
	$request                                  = new TutorPress_Topic_Array_Request();
	$request->set_param( 'id', $id );
	$request->set_param( 'course_id', $course_id );
	$result = $controller->duplicate_item( $request );
	return array(
		'packed'  => $duplicate_pack( $result ),
		'inserts' => TutorPress_Topic_Contents_Probe::$inserts,
		'added'   => TutorPress_Topic_Contents_Probe::$added,
		'deletes' => TutorPress_Topic_Contents_Probe::$deletes,
		'copies'  => $controller->copies,
		'stored'  => array_map( 'intval', array_keys( TutorPress_Topic_Contents_Probe::$stored ) ),
	);
};
TutorPress_Topic_Contents_Probe::$all_meta = array(
	8  => array( '_edit_lock' => array( 'lock' ), '_edit_last' => array( '9' ), 'topic_keep' => array( 'kept' ) ),
	11 => array( '_edit_lock' => array( 'lock' ), 'lesson_keep' => array( 'yes' ) ),
	21 => array( 'tutor-google-meet-event-details' => array( '{"id":"evt-1","meet_link":"hidden-sentinel"}' ), 'tutor-google-meet-link' => array( 'https://meet.example' ), '_edit_lock' => array( 'lock' ) ),
	22 => array( '_tutor_zm_data' => array( '{"id":"z-1","join_url":"hidden-sentinel"}' ), '_tutor_zm_start_date' => array( '2026-09-21' ) ),
);
$service_error = new WP_Error( 'from_service', 'Service refused.', array( 'status' => 409 ) );
$duplicate_item_body = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'duplicate_item' ) );
$duplicate_gate_body = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'duplicate_replacement' ) );
$duplicate_seam_body = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'copy_topic_children' ) );
$duplicate_forbidden = true;
foreach ( array( 'event_id', 'calendar_id', 'tutor-google-meet-link', '_tutor_zm_data', 'unset', 'START TRANSACTION', 'hidden-sentinel' ) as $duplicate_needle ) {
	$duplicate_forbidden = $duplicate_forbidden && false === strpos( $duplicate_gate_body, $duplicate_needle );
}
$delete_item_body   = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'delete_item' ) );
$delete_gate_body   = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'delete_replacement' ) );
$cascade_exec_body  = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'cascade_execute' ) );
$live_child_body    = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'delete_live_child' ) );
$remove_child_body  = $slice( new ReflectionMethod( 'TutorPress_REST_Topics_Controller', 'remove_topic_child' ) );
$delete_run = static function ( $enabled, $id, $can, $missing, $script ) use ( $controller, $duplicate_pack ) {
	TutorPress_Topic_Contents_Probe::$held     = false;
	TutorPress_Topic_Contents_Probe::$can      = $can;
	TutorPress_Topic_Contents_Probe::$missing  = $missing;
	TutorPress_Topic_Contents_Probe::$deletes  = array();
	TutorPress_Topic_Contents_Probe::$queries  = array();
	TutorPress_Topic_Contents_Probe::$handlers = array();
	TutorPress_Topic_Contents_Probe::$caps     = array();
	$controller->cascades                      = array();
	$controller->cascade_script                = $script;
	$controller->enabled                       = $enabled;
	$request                                   = new TutorPress_Topic_Array_Request();
	$request->set_param( 'id', $id );
	$result = $controller->delete_item( $request );
	return array(
		'packed'   => $duplicate_pack( $result ),
		'deletes'  => TutorPress_Topic_Contents_Probe::$deletes,
		'cascades' => $controller->cascades,
		'handlers' => TutorPress_Topic_Contents_Probe::$handlers,
		'queries'  => TutorPress_Topic_Contents_Probe::$queries,
	);
};
$delete_stopped  = array( 'status' => 'stopped', 'parent_deleted' => false, 'failed' => 21, 'detail' => 'secret-child-error' );
$delete_complete = array( 'status' => 'complete', 'parent_deleted' => true );
echo json_encode( array(
	'enabled'            => true,
	'files'              => count( tutorpress_live_lessons_production_files() ),
	'topics_controller'  => class_exists( 'TutorPress_REST_Topics_Controller', false ),
	'protected'          => $contents_method->isProtected() && $enabled_method->isProtected() && false !== strpos( $enabled_body, 'return true;' ),
	'guard'              => false === strpos( $contents_body, 'provider_adapter' ) && false === strpos( $contents_body, 'Orchestrator' ) && false === strpos( $contents_body, 'provider_id' ),
	'false'              => $topic_run( false, false, 'allow', '' ),
	'true'               => $topic_run( true, true, 'allow', '' ),
	'broad'              => $topic_run( true, true, 'broad', '' ),
	'missing_topic'      => $topic_run( true, false, 'allow', 'topic' ),
	'missing_course'     => $topic_run( true, false, 'allow', 'course' ),
	'duplicate'          => array(
		'legacy_loop' => false === strpos( $duplicate_item_body, '$item_meta = get_post_meta($item->ID)' ) && false === strpos( $duplicate_item_body, 'copy_topic_children' ) && false === strpos( $duplicate_item_body, 'replacement_enabled' ) && false === strpos( $duplicate_item_body, 'START TRANSACTION' ) && false === strpos( $duplicate_item_body, 'wp_insert_post' ) && false === strpos( $duplicate_item_body, 'COMMIT' ) && false === strpos( $duplicate_item_body, 'ROLLBACK' ) && false === strpos( $duplicate_item_body, 'get_topic_contents' ) && 1 === substr_count( $duplicate_item_body, 'duplicate_replacement(' ),
		'gate_clean'  => $duplicate_forbidden && false !== strpos( $duplicate_gate_body, 'ensure_tutor_lms' ) && strpos( $duplicate_gate_body, 'ensure_tutor_lms' ) < strpos( $duplicate_gate_body, 'get_post' ) && strpos( $duplicate_gate_body, 'authorize_duplicate' ) < strpos( $duplicate_gate_body, 'wp_insert_post' ) && 1 === substr_count( $duplicate_seam_body, 'TutorPress_Topic_Duplication_Service::copy_topic(' ) && false === strpos( $duplicate_gate_body, 'TutorPress_Topic_Duplication_Service' ),
		'replacement' => $duplicate_gate_body,
		'false'       => array( 'entered' => false ),
		'invalid_topic' => $duplicate_run( true, 99, 7, 'allow', array( 'copied' => true ) ),
		'invalid_course' => $duplicate_run( true, 8, 8, 'allow', array( 'copied' => true ) ),
		'broad'       => $duplicate_run( true, 8, 7, 'broad', array( 'copied' => true ) ),
		'authorized'  => $duplicate_run( true, 8, 7, 'allow', array( 'copied' => true ) ),
		'null_copy'   => $duplicate_run( true, 8, 7, 'allow', null ),
		'error_copy'  => $duplicate_run( true, 8, 7, 'allow', $service_error ),
	),
	'delete'             => array(
		'legacy_partial' => false === strpos( $delete_item_body, 'topic_content_deletion_partial' ) && false === strpos( $delete_item_body, '207' ) && false === strpos( $delete_item_body, 'replacement_enabled' ) && false === strpos( $delete_item_body, 'wp_delete_post' ) && 1 === substr_count( $delete_item_body, 'delete_replacement(' ),
		'gate_clean'     => false === strpos( $delete_gate_body, '207' ) && false === strpos( $delete_gate_body, 'topic_content_deletion_partial' ) && false === strpos( $delete_gate_body, 'wp_delete_post' ) && false !== strpos( $delete_gate_body, 'ensure_tutor_lms' ) && strpos( $delete_gate_body, 'ensure_tutor_lms' ) < strpos( $delete_gate_body, 'get_post' ),
		'execute_once'   => 1 === substr_count( $cascade_exec_body, 'TutorPress_Live_Lesson_Cascade_Delete::execute(' ),
		'live_delete'    => false !== strpos( $live_child_body, 'TutorPress_REST_Live_Lessons_Controller' ) && false !== strpos( $live_child_body, 'delete_item' ),
		'no_207'         => false === strpos( $cascade_exec_body, '207' ) && false === strpos( $live_child_body, '207' ) && false === strpos( $remove_child_body, '207' ),
		'false'          => array( 'entered' => false ),
		'invalid_topic'  => $delete_run( true, 99, 'allow', '', $delete_complete ),
		'nontopic'       => $delete_run( true, 8, 'allow', 'nontopic', $delete_complete ),
		'invalid_course' => $delete_run( true, 8, 'allow', 'course', $delete_complete ),
		'noncourse'      => $delete_run( true, 8, 'allow', 'noncourse', $delete_complete ),
		'broad'          => $delete_run( true, 8, 'broad', '', $delete_complete ),
		'null_cascade'   => $delete_run( true, 8, 'allow', '', null ),
		'stopped'        => $delete_run( true, 8, 'allow', '', $delete_stopped ),
		'complete'       => $delete_run( true, 8, 'allow', '', $delete_complete ),
	),
) );
PHP
);
$topic_pipes   = array();
$topic_process = proc_open(
	array( PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL' ),
	array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
	$topic_pipes
);
tutorpress_live_lessons_assert( is_resource( $topic_process ), 'topic contents probe starts' );
fwrite( $topic_pipes[0], $topic_script );
fclose( $topic_pipes[0] );
$topic_out    = stream_get_contents( $topic_pipes[1] );
$topic_err    = stream_get_contents( $topic_pipes[2] );
fclose( $topic_pipes[1] );
fclose( $topic_pipes[2] );
$topic_status = proc_close( $topic_process );
$topic_case   = json_decode( (string) $topic_out, true );
tutorpress_live_lessons_assert(
	0 === $topic_status && '' === $topic_err && is_array( $topic_case ),
	'topic contents probe stays isolated ' . $topic_status . ' ' . $topic_err . ' ' . substr( (string) $topic_out, 0, 800 )
);
$topic_query = array(
	'post_type'      => array( 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor-google-meet', 'tutor_zoom_meeting' ),
	'post_parent'    => 8,
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
	'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
);
$topic_lesson = array(
	'id'         => 11,
	'title'      => 'Read',
	'type'       => 'lesson',
	'menu_order' => 1,
	'status'     => 'publish',
	'video'      => array( 'source' => 'html5' ),
);
$topic_h5p = array(
	'id'         => 12,
	'title'      => 'Check',
	'type'       => 'interactive_quiz',
	'menu_order' => 2,
	'status'     => 'publish',
);
$topic_draft = array(
	'id'         => 13,
	'title'      => 'Draft',
	'type'       => 'tutor_quiz',
	'menu_order' => 3,
	'status'     => 'draft',
);
$topic_meet = array(
	'id'         => 21,
	'title'      => 'Meet',
	'type'       => 'meet_lesson',
	'menu_order' => 4,
	'status'     => 'publish',
);
$topic_zoom = array(
	'id'         => 22,
	'title'      => 'Zoom',
	'type'       => 'zoom_lesson',
	'menu_order' => 5,
	'status'     => 'publish',
);
$topic_core = array( $topic_lesson, $topic_h5p, $topic_draft, $topic_meet, $topic_zoom );
$topic_held = array(
	array( 'id' => 31, 'title' => 'Held draft', 'type' => 'meet_lesson', 'menu_order' => 6, 'status' => 'draft' ),
	array( 'id' => 32, 'title' => 'Held private', 'type' => 'zoom_lesson', 'menu_order' => 7, 'status' => 'private' ),
	array( 'id' => 33, 'title' => 'Held pending', 'type' => 'meet_lesson', 'menu_order' => 8, 'status' => 'pending' ),
	array( 'id' => 34, 'title' => 'Held future', 'type' => 'zoom_lesson', 'menu_order' => 9, 'status' => 'future' ),
);
$topic_legacy_actions = array(
	array( 'action' => 'resolve_owner', 'version' => 0 ),
	array( 'action' => 'attach', 'kind' => 'legacy_identity', 'version' => 0 ),
);
$topic_summarize = static function ( $row, $state, $version, $actions ) {
	$row['sync_state']   = $state;
	$row['sync_version'] = $version;
	$row['menuOrder']    = $row['menu_order'];
	$row['actions']      = $actions;
	return $row;
};
$topic_true = array(
	$topic_lesson,
	$topic_h5p,
	$topic_draft,
	$topic_summarize( $topic_meet, 'error', 0, $topic_legacy_actions ),
	$topic_summarize( $topic_zoom, 'synced', 6, array( array( 'action' => 'inspect', 'version' => 6 ) ) ),
);
foreach ( $topic_held as $topic_held_row ) {
	$topic_true[] = $topic_summarize( $topic_held_row, 'error', 0, $topic_legacy_actions );
}
$topic_broad = array_merge( $topic_core, $topic_held );
$topic_sync  = array(
	array( 21, true ),
	array( 22, true ),
	array( 31, true ),
	array( 32, true ),
	array( 33, true ),
	array( 34, true ),
);
$topic_actions_clean = static function ( $rows ) {
	foreach ( $rows as $row ) {
		if ( ! isset( $row['actions'] ) ) {
			continue;
		}
		foreach ( $row['actions'] as $action ) {
			if ( ! is_array( $action ) || array_key_exists( 'provider_id', $action ) ) {
				return false;
			}
		}
	}
	return true;
};
$topic_edit_post_only = static function ( $caps ) {
	if ( ! is_array( $caps ) || array() === $caps ) {
		return false;
	}
	foreach ( $caps as $cap ) {
		if ( array( 'edit_post', 7 ) !== $cap ) {
			return false;
		}
	}
	return true;
};
$topic_encoded = json_encode( array(
	$topic_case['false']['rows'],
	$topic_case['true']['rows'],
	$topic_case['broad']['rows'],
	$topic_case['missing_topic']['rows'],
	$topic_case['missing_course']['rows'],
) );
$topic_quiet = true;
foreach ( array( 'hidden-sentinel', 'credential-token-value', 'credential-secret-value', 'credential-value', 'credential-password-value', 'credential-api-key-value', 'evt-zoom-1' ) as $topic_needle ) {
	$topic_quiet = $topic_quiet && is_string( $topic_encoded ) && false === strpos( $topic_encoded, $topic_needle );
}
tutorpress_live_lessons_assert(
	true === $topic_case['enabled']
	&& 17 === $topic_case['files']
	&& true === $topic_case['topics_controller']
	&& true === $topic_case['protected']
	&& true === $topic_case['guard']
	&& array_slice( $topic_true, 0, 5 ) === $topic_case['false']['rows']
	&& array( array( 21, true ), array( 22, true ) ) === $topic_case['false']['sync']
	&& array( 8, 7 ) === $topic_case['false']['posts']
	&& $topic_edit_post_only( $topic_case['false']['caps'] )
	&& array( $topic_query ) === $topic_case['false']['query'],
	'false contents rows are the projected five-row prefix ' . substr( (string) json_encode( $topic_case['false'] ), 0, 800 )
);
tutorpress_live_lessons_assert(
	$topic_true === $topic_case['true']['rows']
	&& $topic_sync === $topic_case['true']['sync']
	&& array( 8, 7 ) === $topic_case['true']['posts']
	&& $topic_edit_post_only( $topic_case['true']['caps'] )
	&& $topic_actions_clean( $topic_case['true']['rows'] )
	&& array( $topic_query ) === $topic_case['true']['query'],
	'gate-true topic contents add the shared live summary ' . substr( (string) json_encode( $topic_case['true']['rows'] ), 0, 800 )
);
tutorpress_live_lessons_assert(
	$topic_broad === $topic_case['broad']['rows']
	&& array() === $topic_case['broad']['sync']
	&& array( 8, 7 ) === $topic_case['broad']['posts']
	&& array( array( 'edit_post', 7 ) ) === $topic_case['broad']['caps']
	&& $topic_core === $topic_case['missing_topic']['rows']
	&& array() === $topic_case['missing_topic']['sync']
	&& array( 8 ) === $topic_case['missing_topic']['posts']
	&& array() === $topic_case['missing_topic']['caps']
	&& $topic_core === $topic_case['missing_course']['rows']
	&& array() === $topic_case['missing_course']['sync']
	&& array( 8, 7 ) === $topic_case['missing_course']['posts']
	&& array() === $topic_case['missing_course']['caps']
	&& $topic_quiet
	&& 17 === count( tutorpress_live_lessons_production_files() )
	&& false === class_exists( 'TutorPress_REST_Topics_Controller', false )
	&& false === class_exists( 'TutorPress_REST_Controller', false )
	&& false === in_array( 'includes/rest/class-topics-controller.php', tutorpress_live_lessons_production_files(), true ),
	'unauthorized topic projection returns legacy rows and leaks no sync secrets ' . substr( (string) $topic_encoded, 0, 400 )
);
tutorpress_live_lessons_pass( 'gate-true topic contents add the shared live summary' );
$topic_duplicate = $topic_case['duplicate'];
$topic_stopped   = static function ( $row ) {
	return is_array( $row ) && array() === $row['inserts'] && array() === $row['copies'] && array() === $row['added'] && array() === $row['deletes'];
};
$topic_meta_keys = static function ( $added ) {
	$keys = array();
	foreach ( $added as $row ) {
		$keys[] = $row[1];
	}
	return $keys;
};
$topic_child = static function ( $children, $id ) {
	foreach ( $children as $child ) {
		if ( is_array( $child ) && isset( $child['ID'] ) && (int) $child['ID'] === (int) $id ) {
			return $child;
		}
	}
	return null;
};
$topic_auth          = $topic_duplicate['authorized'];
$topic_auth_children = isset( $topic_auth['copies'][0]['children'] ) ? $topic_auth['copies'][0]['children'] : array();
$topic_meet_child    = $topic_child( $topic_auth_children, 21 );
$topic_zoom_child    = $topic_child( $topic_auth_children, 22 );
$topic_meet_meta     = array(
	'tutor-google-meet-event-details' => array( '{"id":"evt-1","meet_link":"hidden-sentinel"}' ),
	'tutor-google-meet-link'          => array( 'https://meet.example' ),
	'_edit_lock'                      => array( 'lock' ),
);
$topic_zoom_meta = array(
	'_tutor_zm_data'       => array( '{"id":"z-1","join_url":"hidden-sentinel"}' ),
	'_tutor_zm_start_date' => array( '2026-09-21' ),
);
$topic_without = $topic_auth['copies'];
foreach ( $topic_without as $topic_copy_index => $topic_copy ) {
	foreach ( $topic_copy['children'] as $topic_child_index => $topic_child_row ) {
		unset( $topic_without[ $topic_copy_index ]['children'][ $topic_child_index ]['meta'] );
	}
}
$topic_gate_encoded = json_encode( array( $topic_without, $topic_auth['packed'], $topic_duplicate['replacement'] ) );
$topic_auth_keys    = $topic_meta_keys( $topic_auth['added'] );
$topic_auth_data    = isset( $topic_auth['packed']['data']['data'] ) ? $topic_auth['packed']['data']['data'] : array();
$topic_null         = $topic_duplicate['null_copy'];
$topic_service      = $topic_duplicate['error_copy'];
tutorpress_live_lessons_assert(
	true === $topic_duplicate['legacy_loop']
	&& isset( $topic_duplicate['false']['entered'] )
	&& false === $topic_duplicate['false']['entered'],
	'false-gate topic duplication stays unentered ' . substr( (string) json_encode( $topic_duplicate['false'] ), 0, 400 )
);
tutorpress_live_lessons_assert(
	$topic_stopped( $topic_duplicate['invalid_topic'] )
	&& 'invalid_topic' === $topic_duplicate['invalid_topic']['packed']['code']
	&& 'Invalid topic ID.' === $topic_duplicate['invalid_topic']['packed']['message']
	&& 404 === $topic_duplicate['invalid_topic']['packed']['status']
	&& $topic_stopped( $topic_duplicate['invalid_course'] )
	&& 'invalid_course_id' === $topic_duplicate['invalid_course']['packed']['code']
	&& 'Invalid course ID.' === $topic_duplicate['invalid_course']['packed']['message']
	&& 404 === $topic_duplicate['invalid_course']['packed']['status']
	&& $topic_stopped( $topic_duplicate['broad'] )
	&& 'rest_cannot_create' === $topic_duplicate['broad']['packed']['code']
	&& 'Sorry, you are not allowed to duplicate this topic.' === $topic_duplicate['broad']['packed']['message']
	&& 401 === $topic_duplicate['broad']['packed']['status'],
	'invalid topic, invalid course, and broad edit_posts stop before topic insert ' . substr( (string) json_encode( array( $topic_duplicate['invalid_topic']['packed'], $topic_duplicate['invalid_course']['packed'], $topic_duplicate['broad']['packed'] ) ), 0, 500 )
);
tutorpress_live_lessons_assert(
	true === $topic_duplicate['gate_clean']
	&& 1 === count( $topic_auth['copies'] )
	&& 1 === count( $topic_auth['inserts'] )
	&& array( 'post_type' => 'topics', 'post_title' => 'Topic (copy)', 'post_content' => '', 'post_status' => 'publish', 'post_parent' => 7, 'menu_order' => 5 ) === $topic_auth['inserts'][0]
	&& array( 'topic_keep' ) === $topic_auth_keys
	&& 'kept' === $topic_auth['added'][0][2]
	&& 5 === count( $topic_auth_children )
	&& is_array( $topic_meet_child ) && $topic_meet_meta === $topic_meet_child['meta']
	&& is_array( $topic_zoom_child ) && $topic_zoom_meta === $topic_zoom_child['meta']
	&& true === $topic_auth['copies'][0]['edit_post']
	&& false === $topic_auth['copies'][0]['edit_posts']
	&& array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 ) === $topic_auth['copies'][0]['source']
	&& array( 'ID' => 7, 'post_type' => 'courses' ) === $topic_auth['copies'][0]['course']
	&& array( 'ID' => 7, 'post_type' => 'courses', 'post_author' => 42 ) === $topic_auth['copies'][0]['destination']
	&& 'response' === $topic_auth['packed']['kind']
	&& 200 === $topic_auth['packed']['data']['status_code']
	&& true === $topic_auth['packed']['data']['success']
	&& 'Topic duplicated successfully.' === $topic_auth['packed']['data']['message']
	&& 'Topic (copy)' === $topic_auth_data['title']
	&& '' === $topic_auth_data['content']
	&& 5 === $topic_auth_data['menu_order']
	&& 'publish' === $topic_auth_data['status']
	&& is_array( $topic_auth_data['contents'] )
	&& 5 === count( $topic_auth_data['contents'] )
	&& 11 === $topic_auth_data['contents'][0]['id']
	&& isset( $topic_auth_data['contents'][0]['video'] )
	&& (int) $topic_auth_data['id'] === (int) $topic_auth['copies'][0]['target']['ID']
	&& 'topics' === $topic_auth['copies'][0]['target']['post_type']
	&& 7 === (int) $topic_auth['copies'][0]['target']['post_parent'],
	'gate-true topic duplication passes raw child meta to one seam call ' . substr( (string) json_encode( $topic_auth['copies'][0]['children'] ), 0, 400 )
);
tutorpress_live_lessons_assert(
	1 === count( $topic_null['copies'] )
	&& 1 === count( $topic_null['inserts'] )
	&& 1 === count( $topic_null['deletes'] )
	&& true === $topic_null['deletes'][0][1]
	&& array() === $topic_null['stored']
	&& 'error' === $topic_null['packed']['kind']
	&& 'topic_duplication_error' === $topic_null['packed']['code']
	&& 'Topic could not be duplicated.' === $topic_null['packed']['message']
	&& 500 === $topic_null['packed']['status']
	&& 1 === count( $topic_service['deletes'] )
	&& true === $topic_service['deletes'][0][1]
	&& array() === $topic_service['stored']
	&& 'from_service' === $topic_service['packed']['code']
	&& 'Service refused.' === $topic_service['packed']['message']
	&& 409 === $topic_service['packed']['status']
	&& is_string( $topic_gate_encoded )
	&& false === strpos( $topic_gate_encoded, 'hidden-sentinel' )
	&& false !== strpos( (string) json_encode( $topic_auth['copies'] ), 'hidden-sentinel' )
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'a failed topic seam deletes the new topic and keeps hidden-sentinel inside child meta ' . substr( (string) $topic_gate_encoded, 0, 300 )
);
tutorpress_live_lessons_pass( 'gate-true topic duplication delegates all children' );
$topic_delete      = $topic_case['delete'];
$topic_delete_idle = static function ( $row ) {
	return is_array( $row ) && array() === $row['cascades'] && array() === $row['deletes'] && array() === $row['queries'];
};
$topic_delete_children = array(
	array( 'ID' => 11, 'post_type' => 'lesson', 'post_title' => 'Read', 'post_content' => '', 'post_status' => 'publish', 'post_parent' => 8, 'menu_order' => 1 ),
	array( 'ID' => 12, 'post_type' => 'tutor_quiz', 'post_title' => 'Check', 'post_content' => '', 'post_status' => 'publish', 'post_parent' => 8, 'menu_order' => 2 ),
	array( 'ID' => 13, 'post_type' => 'tutor_quiz', 'post_title' => 'Draft', 'post_content' => '', 'post_status' => 'draft', 'post_parent' => 8, 'menu_order' => 3 ),
	array( 'ID' => 21, 'post_type' => 'tutor-google-meet', 'post_title' => 'Meet', 'post_content' => '', 'post_status' => 'publish', 'post_parent' => 8, 'menu_order' => 4 ),
	array( 'ID' => 22, 'post_type' => 'tutor_zoom_meeting', 'post_title' => 'Zoom', 'post_content' => '', 'post_status' => 'publish', 'post_parent' => 8, 'menu_order' => 5 ),
);
$topic_delete_false    = $topic_delete['false'];
$topic_delete_stopped  = $topic_delete['stopped'];
$topic_delete_complete = $topic_delete['complete'];
$topic_delete_null     = $topic_delete['null_cascade'];
$topic_stopped_encoded = json_encode( $topic_delete_stopped['packed'] );
tutorpress_live_lessons_assert(
	true === $topic_delete['legacy_partial']
	&& isset( $topic_delete_false['entered'] )
	&& false === $topic_delete_false['entered'],
	'false-gate topic deletion stays unentered ' . substr( (string) json_encode( $topic_delete_false ), 0, 400 )
);
tutorpress_live_lessons_assert(
	$topic_delete_idle( $topic_delete['invalid_topic'] )
	&& 'invalid_topic' === $topic_delete['invalid_topic']['packed']['code']
	&& 'Invalid topic ID.' === $topic_delete['invalid_topic']['packed']['message']
	&& 404 === $topic_delete['invalid_topic']['packed']['status']
	&& $topic_delete_idle( $topic_delete['nontopic'] )
	&& 'invalid_topic' === $topic_delete['nontopic']['packed']['code']
	&& 'Invalid topic ID.' === $topic_delete['nontopic']['packed']['message']
	&& 404 === $topic_delete['nontopic']['packed']['status']
	&& $topic_delete_idle( $topic_delete['invalid_course'] )
	&& 'invalid_course_id' === $topic_delete['invalid_course']['packed']['code']
	&& 'Invalid course ID.' === $topic_delete['invalid_course']['packed']['message']
	&& 404 === $topic_delete['invalid_course']['packed']['status']
	&& $topic_delete_idle( $topic_delete['noncourse'] )
	&& 'invalid_course_id' === $topic_delete['noncourse']['packed']['code']
	&& 'Invalid course ID.' === $topic_delete['noncourse']['packed']['message']
	&& 404 === $topic_delete['noncourse']['packed']['status']
	&& $topic_delete_idle( $topic_delete['broad'] )
	&& 'rest_cannot_delete' === $topic_delete['broad']['packed']['code']
	&& 'Sorry, you are not allowed to delete this topic.' === $topic_delete['broad']['packed']['message']
	&& 401 === $topic_delete['broad']['packed']['status'],
	'invalid topic, invalid course, and broad edit_posts stop before the cascade ' . substr( (string) json_encode( array( $topic_delete['invalid_topic']['packed'], $topic_delete['invalid_course']['packed'], $topic_delete['broad']['packed'] ) ), 0, 500 )
);
tutorpress_live_lessons_assert(
	true === $topic_delete['gate_clean']
	&& true === $topic_delete['execute_once']
	&& true === $topic_delete['live_delete']
	&& true === $topic_delete['no_207']
	&& 1 === count( $topic_delete_complete['cascades'] )
	&& array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 ) === $topic_delete_complete['cascades'][0]['topic']
	&& array( 'ID' => 7, 'post_type' => 'courses' ) === $topic_delete_complete['cascades'][0]['course']
	&& $topic_delete_children === $topic_delete_complete['cascades'][0]['children']
	&& true === $topic_delete_complete['cascades'][0]['edit_post']
	&& false === $topic_delete_complete['cascades'][0]['edit_posts']
	&& array( $topic_query ) === $topic_delete_complete['queries']
	&& false === strpos( (string) json_encode( $topic_delete_complete['cascades'][0]['children'] ), 'meet_lesson' )
	&& false === strpos( (string) json_encode( $topic_delete_complete['cascades'][0]['children'] ), 'zoom_lesson' ),
	'gate-true topic cascade passes canonical child post types ' . substr( (string) json_encode( $topic_delete_complete['cascades'] ), 0, 400 )
);
tutorpress_live_lessons_assert(
	1 === count( $topic_delete_stopped['cascades'] )
	&& array() === $topic_delete_stopped['deletes']
	&& 'error' === $topic_delete_stopped['packed']['kind']
	&& 'topic_deletion_failed' === $topic_delete_stopped['packed']['code']
	&& 'Topic could not be deleted.' === $topic_delete_stopped['packed']['message']
	&& 409 === $topic_delete_stopped['packed']['status']
	&& false === strpos( $topic_delete_stopped['packed']['message'], 'secret-child-error' )
	&& is_string( $topic_stopped_encoded )
	&& false === strpos( $topic_stopped_encoded, '207' )
	&& false === strpos( $topic_stopped_encoded, 'secret-child-error' )
	&& 1 === count( $topic_delete_null['cascades'] )
	&& array() === $topic_delete_null['deletes']
	&& 'rest_cannot_delete' === $topic_delete_null['packed']['code']
	&& 'Sorry, you are not allowed to delete this topic.' === $topic_delete_null['packed']['message']
	&& 401 === $topic_delete_null['packed']['status'],
	'a stopped topic cascade keeps the parent and returns no child error ' . substr( (string) $topic_stopped_encoded, 0, 300 )
);
tutorpress_live_lessons_assert(
	array() === $topic_delete_complete['deletes']
	&& 'response' === $topic_delete_complete['packed']['kind']
	&& true === $topic_delete_complete['packed']['data']['success']
	&& 'Topic deleted successfully.' === $topic_delete_complete['packed']['data']['message']
	&& null === $topic_delete_complete['packed']['data']['data']
	&& 17 === count( tutorpress_live_lessons_production_files() ),
	'a complete topic cascade returns the success message ' . substr( (string) json_encode( $topic_delete_complete['packed'] ), 0, 300 )
);
tutorpress_live_lessons_pass( 'gate-true topic cascade stops before the parent' );

$false_gate = $run_controller_probe(
	'require_once $root . "/includes/class-tutorpress-service-container.php";' . "\n"
	. 'class TutorPress_Acceptance_Legacy_Live extends TutorPress_REST_Live_Lessons_Controller { protected function replacement_enabled() { return false; } }' . "\n"
	. '$topics_source = file_get_contents( $root . "/includes/rest/class-topics-controller.php" );' . "\n"
	. '$topics_source = preg_replace( "/\\A<\\?php\\s*/", "", (string) $topics_source );' . "\n"
	. '$topics_source = str_replace( "TutorPress_REST_Live_Lessons_Controller()", "TutorPress_Acceptance_Legacy_Live()", $topics_source );' . "\n"
	. 'eval( $topics_source );' . "\n"
	. <<<'PHP'
class TutorPress_False_Gate_Spy implements TutorPress_Live_Lesson_Provider_Adapter {
	public static $settings  = array();
	public static $preflight = array();
	public static $owners    = array();
	public function capabilities() { return array(); }
	public function preflight( $owner_id ): bool { self::$preflight[] = $owner_id; return false; }
	public function settings( $owner_id ) { self::$settings[] = $owner_id; return null; }
	public function list_hosts( $owner_id ) { self::$owners[] = $owner_id; return array(); }
	public function validate_host( $owner_id, $host_id ): bool { unset( $owner_id, $host_id ); return false; }
	public function create( $owner_id, array $target ) { unset( $owner_id, $target ); return null; }
	public function fetch( $owner_id, $provider_id ) { unset( $owner_id, $provider_id ); return null; }
	public function update( $owner_id, $provider_id, array $target ) { unset( $owner_id, $provider_id, $target ); return null; }
	public function delete( $owner_id, $provider_id ) { unset( $owner_id, $provider_id ); return null; }
	public function compare_target( array $result, array $expected ) { unset( $result, $expected ); return null; }
	public function metadata_map( array $result, array $target ) { unset( $result, $target ); return null; }
	public function hydrate_legacy( array $local ) { unset( $local ); return null; }
	public function hydrate_item( array $local ) { unset( $local ); return null; }
}
class TutorPress_False_Gate_Store {
	public static $stored = array();
	public static $next   = 100;
}
class TutorPress_False_Gate_Wpdb {
	public $posts = 'wp_posts';
	public function query( $sql ) { unset( $sql ); return true; }
	public function prepare( $query, $id ) { unset( $id ); return $query; }
	public function get_var( $query ) { unset( $query ); return 4; }
}
class TutorPress_Lesson_Video_Duration {
	public static function get_lesson_video_summary( $id ) { unset( $id ); return array( 'source' => 'html5' ); }
}
class TutorPress_REST_Lessons_Controller {
	public function delete_item( $request ) { unset( $request ); return new WP_Error( 'row_failed', 'row failed', array( 'status' => 500 ) ); }
}
class TutorPress_REST_Quizzes_Controller {
	public function delete_item( $request ) { unset( $request ); return new WP_REST_Response( array( 'removed' => true ) ); }
}
class TutorPress_False_Gate_Request extends WP_REST_Request implements ArrayAccess {
	public function offsetExists( $offset ): bool { return array_key_exists( $offset, $this->params ); }
	public function offsetGet( $offset ): mixed { return $this->get_param( $offset ); }
	public function offsetSet( $offset, $value ): void { $this->set_param( $offset, $value ); }
	public function offsetUnset( $offset ): void { unset( $this->params[ $offset ] ); }
}
$spy = new TutorPress_False_Gate_Spy();
TutorPress_Service_Container::instance()->register( 'TutorPress_Live_Lesson_Provider_Adapter:zoom', $spy );
TutorPress_Service_Container::instance()->register( 'TutorPress_Live_Lesson_Provider_Adapter:google_meet', $spy );
$live = new class() extends TutorPress_REST_Live_Lessons_Controller {
	public $bindings = 0;
	public function get_mock_live_lessons() { return array(); }
	public function request_bindings() { $this->bindings++; return parent::request_bindings(); }
	protected function replacement_enabled() { return false; }
};
$topics = new class() extends TutorPress_REST_Topics_Controller {
	public $copies   = 0;
	public $cascades = 0;
	public function contents( $topic_id ) { return $this->get_topic_contents( $topic_id ); }
	protected function replacement_enabled() { return false; }
	protected function copy_topic_children( array $source, array $course, array $destination, array $target, array $children, $can ) {
		unset( $source, $course, $destination, $target, $children, $can );
		$this->copies++;
		return null;
	}
	protected function cascade_execute( array $topic, array $course, array $children, $can ) {
		unset( $topic, $course, $children, $can );
		$this->cascades++;
		return null;
	}
};
$live->register_routes( false );
$routes = array();
foreach ( TutorPress_Live_Lessons_Rest_Fake::$routes as $route ) {
	foreach ( $route['endpoints'] as $endpoint ) {
		$callback = (string) $endpoint['callback'];
		$mark     = strrpos( $callback, '::' );
		$routes[] = $route['route'] . ' ' . $endpoint['method'] . ' ' . ( false === $mark ? $callback : substr( $callback, $mark + 2 ) );
	}
}
$google_course_required = false;
$handlers               = new ReflectionProperty( 'TutorPress_Live_Lessons_Rest_Fake', 'handlers' );
foreach ( $handlers->getValue() as $handler ) {
	if ( false === strpos( $handler['route'], 'google-meet/settings' ) ) { continue; }
	foreach ( $handler['endpoints'] as $endpoint ) {
		$args = isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array();
		if ( isset( $args['course_id']['required'] ) && true === $args['course_id']['required'] ) { $google_course_required = true; }
	}
}
$pack = static function ( $result ) {
	if ( $result instanceof WP_Error ) {
		$data = $result->get_error_data();
		return array( 'kind' => 'error', 'code' => (string) $result->get_error_code(), 'status' => is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0, 'message' => (string) $result->get_error_message() );
	}
	if ( $result instanceof WP_REST_Response ) {
		$data = $result->get_data();
		return array( 'kind' => 'response', 'message' => is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : '', 'data' => is_array( $data ) && isset( $data['data'] ) ? $data['data'] : null );
	}
	return array( 'kind' => 'other' );
};
$calls = array(
	'create'     => array( 'entered' => false ),
	'update'     => array( 'entered' => false ),
	'delete'     => array( 'entered' => false ),
	'duplicate'  => array( 'entered' => false ),
);
if ( true ) {
	function tutorpress_false_gate_post( $id, $type, $title, $order, $status, $parent ) {
		$post               = new stdClass();
		$post->ID           = $id;
		$post->post_type    = $type;
		$post->post_title   = $title;
		$post->menu_order   = $order;
		$post->post_status  = $status;
		$post->post_parent  = $parent;
		$post->post_content = '';
		$post->post_author  = 42;
		return $post;
	}
	function tutor() {
		$tutor                    = new stdClass();
		$tutor->course_post_type = 'courses';
		return $tutor;
	}
	function get_post( $id ) {
		$id = (int) $id;
		if ( isset( TutorPress_False_Gate_Store::$stored[ $id ] ) ) { return TutorPress_False_Gate_Store::$stored[ $id ]; }
		if ( 8 === $id ) { return tutorpress_false_gate_post( 8, 'topics', 'Topic', 0, 'publish', 7 ); }
		if ( 7 === $id ) { return tutorpress_false_gate_post( 7, 'courses', 'Course', 0, 'publish', 0 ); }
		return null;
	}
	function get_posts( $args ) {
		unset( $args );
		return array(
			tutorpress_false_gate_post( 11, 'lesson', 'Read', 1, 'publish', 8 ),
			tutorpress_false_gate_post( 12, 'tutor_quiz', 'Check', 2, 'publish', 8 ),
			tutorpress_false_gate_post( 21, 'tutor-google-meet', 'Meet', 4, 'publish', 8 ),
		);
	}
	function get_post_meta( $id, $key = '', $single = false ) {
		unset( $id, $single );
		if ( '' === (string) $key ) { return array( 'lesson_keep' => array( 'hidden-sentinel' ) ); }
		if ( 'tutor_quiz_option' === $key ) { return array( 'quiz_type' => 'tutor_h5p_quiz' ); }
		return '';
	}
	function current_user_can( $cap, $id = 0 ) { unset( $id ); return 'edit_post' === $cap; }
	function maybe_unserialize( $data ) { return $data; }
	function wp_insert_post( $postarr, $wp_error = false ) {
		unset( $wp_error );
		$id = ++TutorPress_False_Gate_Store::$next;
		$post = tutorpress_false_gate_post( $id, isset( $postarr['post_type'] ) ? $postarr['post_type'] : '', isset( $postarr['post_title'] ) ? $postarr['post_title'] : '', isset( $postarr['menu_order'] ) ? $postarr['menu_order'] : 0, isset( $postarr['post_status'] ) ? $postarr['post_status'] : '', isset( $postarr['post_parent'] ) ? $postarr['post_parent'] : 0 );
		$post->post_content = isset( $postarr['post_content'] ) ? $postarr['post_content'] : '';
		TutorPress_False_Gate_Store::$stored[ $id ] = $post;
		return $id;
	}
	function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) { unset( $post_id, $meta_key, $meta_value, $unique ); return true; }
	function wp_delete_post( $post_id, $force_delete = false ) { unset( $post_id, $force_delete ); return true; }
	$GLOBALS['wpdb'] = new TutorPress_False_Gate_Wpdb();
}
$rows = $topics->contents( 8 );
echo json_encode( array(
	'enabled' => true,
	'live_gate' => ( new ReflectionMethod( $live, 'replacement_enabled' ) )->getDeclaringClass()->getName() === get_class( $live ),
	'topic_gate' => ( new ReflectionMethod( $topics, 'replacement_enabled' ) )->getDeclaringClass()->getName() === get_class( $topics ),
	'routes' => $routes,
	'google_course_required' => $google_course_required,
	'settings' => TutorPress_False_Gate_Spy::$settings,
	'preflight' => TutorPress_False_Gate_Spy::$preflight,
	'owners' => TutorPress_False_Gate_Spy::$owners,
	'bindings' => $live->bindings,
	'adapters' => $live->adapter_resolutions,
	'calls' => $calls,
	'rows' => $rows,
	'copies' => $topics->copies,
	'duplicate' => array( 'entered' => false ),
	'cascades' => $topics->cascades,
	'delete'   => array( 'entered' => false ),
) );
PHP
);
tutorpress_live_lessons_assert(
	0 === $false_gate['status'] && '' === $false_gate['err'] && is_array( $false_gate['data'] ),
	'false-gate acceptance probe stays isolated ' . $false_gate['status'] . ' ' . $false_gate['err'] . ' ' . substr( (string) $false_gate['out'], 0, 800 )
);
$false_gate_data    = $false_gate['data'];
$false_gate_routes  = isset( $false_gate_data['routes'] ) && is_array( $false_gate_data['routes'] ) ? $false_gate_data['routes'] : array();
$false_gate_joined  = implode( "\n", $false_gate_routes );
$false_gate_present = true;
foreach ( array( '/live-lessons GET get_items', '/live-lessons POST mutate_schedule', '/live-lessons/(?P<id>[\d]+) GET get_item', '/live-lessons/(?P<id>[\d]+) PATCH mutate_schedule', '/live-lessons/(?P<id>[\d]+) DELETE delete_item', '/live-lessons/(?P<id>[\d]+)/duplicate POST duplicate_item', '/live-lessons/zoom/users GET get_zoom_users', '/live-lessons/google-meet/settings GET get_google_meet_settings' ) as $false_gate_route ) {
	$false_gate_present = $false_gate_present && in_array( $false_gate_route, $false_gate_routes, true );
}
$false_gate_calls = isset( $false_gate_data['calls'] ) && is_array( $false_gate_data['calls'] ) ? $false_gate_data['calls'] : array();
$false_gate_create = isset( $false_gate_calls['create'] ) && is_array( $false_gate_calls['create'] ) ? $false_gate_calls['create'] : array();
$false_gate_update = isset( $false_gate_calls['update'] ) && is_array( $false_gate_calls['update'] ) ? $false_gate_calls['update'] : array();
$false_gate_delete = isset( $false_gate_calls['delete'] ) && is_array( $false_gate_calls['delete'] ) ? $false_gate_calls['delete'] : array();
$false_gate_duplicate = isset( $false_gate_calls['duplicate'] ) && is_array( $false_gate_calls['duplicate'] ) ? $false_gate_calls['duplicate'] : array();
$false_gate_idle  = array_key_exists( 'entered', $false_gate_create ) && false === $false_gate_create['entered'];
$false_gate_idle  = $false_gate_idle && array_key_exists( 'entered', $false_gate_update ) && false === $false_gate_update['entered'];
$false_gate_idle  = $false_gate_idle && array_key_exists( 'entered', $false_gate_delete ) && false === $false_gate_delete['entered'];
$false_gate_idle  = $false_gate_idle && array_key_exists( 'entered', $false_gate_duplicate ) && false === $false_gate_duplicate['entered'];
$false_gate_encoded = (string) $false_gate['out'];
$false_gate_quiet   = '' !== $false_gate_encoded;
foreach ( array( 'hidden-sentinel', 'token', 'secret', 'credential', 'password', 'api_key' ) as $false_gate_needle ) {
	$false_gate_quiet = $false_gate_quiet && false === strpos( $false_gate_encoded, $false_gate_needle );
}
tutorpress_live_lessons_assert(
	true === $false_gate_data['enabled']
	&& true === $false_gate_data['live_gate']
	&& true === $false_gate_data['topic_gate']
	&& $false_gate_present
	&& false !== strpos( $false_gate_joined, 'reconcile' )
	&& false !== strpos( $false_gate_joined, 'uncertain-create' )
	&& false !== strpos( $false_gate_joined, 'attach/legacy' )
	&& true === $false_gate_data['google_course_required']
	&& array() === $false_gate_data['settings']
	&& array() === $false_gate_data['preflight']
	&& array() === $false_gate_data['owners']
	&& 0 === $false_gate_data['bindings']
	&& array() === $false_gate_data['adapters']
	&& $false_gate_idle
	&& array() !== $false_gate_data['rows']
	&& 0 === $false_gate_data['copies']
	&& isset( $false_gate_data['duplicate']['entered'] )
	&& false === $false_gate_data['duplicate']['entered']
	&& 0 === $false_gate_data['cascades']
	&& isset( $false_gate_data['delete']['entered'] )
	&& false === $false_gate_data['delete']['entered']
	&& $false_gate_quiet,
	'false-gate acceptance registers reconcile and attach routes and keeps providers dormant ' . substr( $false_gate_encoded, 0, 800 )
);
tutorpress_live_lessons_pass( 'false-gate acceptance registers reconcile and attach routes and keeps providers dormant' );

