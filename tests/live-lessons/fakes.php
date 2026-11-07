<?php
/**
 * Test-only live-lesson fakes.
 * Loads neither WordPress nor Composer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/abspath/' );
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Request {
	public $method = '';
	public $route  = '';
	public $params = array();

	public function set_param( $key, $value ) {
		$key = (string) $key;
		if ( TutorPress_Live_Lessons_Rest_Fake::secret_key( $key ) ) {
			unset( $this->params[ $key ] );
			return;
		}
		$this->params[ $key ] = $value;
	}

	public function get_param( $key ) {
		$key = (string) $key;
		if ( TutorPress_Live_Lessons_Rest_Fake::secret_key( $key ) ) {
			return null;
		}
		$present = array_key_exists( $key, $this->params );
		TutorPress_Live_Lessons_Rest_Fake::note_param( $key, $present ? $this->params[ $key ] : null, $present );
		return $present ? $this->params[ $key ] : null;
	}

	public function get_params() {
		$params = array();
		foreach ( $this->params as $key => $value ) {
			if ( ! TutorPress_Live_Lessons_Rest_Fake::secret_key( (string) $key ) ) {
				$params[ $key ] = $value;
			}
		}
		return $params;
	}
}

class WP_REST_Response {
	public $data   = null;
	public $status = 200;

	public function __construct( $data = null, $status = 200 ) {
		$this->set_data( $data );
		$this->status = (int) $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data( $data ) {
		$this->data = $data;
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	class WP_REST_Controller {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( is_wp_error( $response ) || $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( $response );
	}
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
	const EDITABLE  = 'POST, PUT, PATCH';
	const DELETABLE = 'DELETE';
}

class TutorPress_Live_Lessons_Hook_Fake {
	public static $calls  = array();
	public static $throws = array();
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook ) {
		$args = array_slice( func_get_args(), 1 );
		TutorPress_Live_Lessons_Hook_Fake::$calls[] = array( $hook, $args );
		if ( ! empty( TutorPress_Live_Lessons_Hook_Fake::$throws[ $hook ] ) ) {
			throw array_shift( TutorPress_Live_Lessons_Hook_Fake::$throws[ $hook ] );
		}
	}
}

function tutorpress_live_lessons_forbid_external( $boundary ) {
	tutorpress_live_lessons_fail( 'unstubbed ' . $boundary . ' access' );
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request() {
		tutorpress_live_lessons_forbid_external( 'http' );
	}
}

class TutorPress_Live_Lessons_Store_Fake {
	public static $posts                = array();
	public static $meta                 = array();
	public static $fail_deletes         = array();
	public static $meta_result          = null;
	public static $meta_result_refusals = 0;
	public static $options              = array();
	public static $autoload             = array();
	public static $affected             = 0;
	public static $cache                = array();
	public static $next_post_id         = 1;
	public static $next_meta_id         = 1;

	public static function insert_publish_post( $data = array() ) {
		$id                    = self::$next_post_id++;
		$data['ID']            = $id;
		$data['post_status']   = 'publish';
		self::$posts[ $id ]    = $data;
		return $id;
	}

	public static function add_meta( $post_id, $key, $value ) {
		if ( '_tutorpress_live_lesson_sync' === $key && in_array( self::$meta_result, array( 'wp_error', 'false', 'zero' ), true ) ) {
			self::$meta_result_refusals++;
			if ( 'wp_error' === self::$meta_result ) {
				return new WP_Error( 'sync_write_failed', 'sync write failed' );
			}
			if ( 'false' === self::$meta_result ) {
				return false;
			}
			return 0;
		}
		$id          = self::$next_meta_id++;
		self::$meta[] = array(
			'meta_id'    => $id,
			'post_id'    => (int) $post_id,
			'meta_key'   => $key,
			'meta_value' => $value,
		);
		return $id;
	}

	public static function meta_rows( $post_id, $key ) {
		$rows = array();
		foreach ( self::$meta as $row ) {
			if ( (int) $row['post_id'] === (int) $post_id && $row['meta_key'] === $key ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/** Return the stored value only when this post and key have one row. */
	public static function meta_bytes( $post_id, $key ) {
		$rows = self::meta_rows( $post_id, $key );
		return 1 === count( $rows ) ? $rows[0]['meta_value'] : null;
	}

	/** Write one row when post, key, meta_id, and prior bytes all match. */
	public static function update_meta_bytes( $post_id, $key, $meta_id, $bytes, $prior ) {
		$rows = self::meta_rows( $post_id, $key );
		if ( 1 !== count( $rows ) || $rows[0]['meta_id'] !== $meta_id || $rows[0]['meta_value'] !== $prior ) {
			self::$affected = 0;
			return false;
		}
		foreach ( self::$meta as $i => $row ) {
			if ( $row['meta_id'] === $meta_id ) {
				self::$meta[ $i ]['meta_value'] = $bytes;
				self::$affected                 = 1;
				self::$cache[]                  = $key;
				return true;
			}
		}
		self::$affected = 0;
		return false;
	}

	public static function delete_post( $post_id ) {
		if ( ! empty( self::$fail_deletes[ $post_id ] ) ) {
			return false;
		}
		unset( self::$posts[ $post_id ] );
		foreach ( self::$meta as $i => $row ) {
			if ( (int) $row['post_id'] === (int) $post_id ) {
				unset( self::$meta[ $i ] );
			}
		}
		self::$meta = array_values( self::$meta );
		return true;
	}

	public static function add_option( $name, $bytes ) {
		if ( isset( self::$options[ $name ] ) ) {
			self::$affected = 0;
			return false;
		}
		self::$options[ $name ] = $bytes;
		self::$autoload[ $name ] = false;
		self::$affected         = 1;
		return true;
	}

	public static function update_option_bytes( $name, $bytes, $prior ) {
		if ( ! isset( self::$options[ $name ] ) || self::$options[ $name ] !== $prior ) {
			self::$affected = 0;
			return false;
		}
		self::$options[ $name ] = $bytes;
		self::$affected         = 1;
		self::$cache[]          = $name;
		return true;
	}

	public static function delete_option_bytes( $name, $prior ) {
		if ( ! isset( self::$options[ $name ] ) || self::$options[ $name ] !== $prior ) {
			self::$affected = 0;
			return false;
		}
		unset( self::$options[ $name ], self::$autoload[ $name ] );
		self::$affected = 1;
		self::$cache[]  = $name;
		return true;
	}
}

class TutorPress_Live_Lessons_Boundary_Fake {
	public static $links = array();
	public static $caps  = array();
	public static $now   = '1970-01-01 00:00:00';
	public static $uuids = array();

	public static function link( $item, $topic, $course ) {
		self::$links[ (int) $item ] = array( (int) $topic, (int) $course );
	}

	public static function topic_course( $item ) {
		return isset( self::$links[ (int) $item ] ) ? self::$links[ (int) $item ] : array( 0, 0 );
	}

	public static function allow( $cap, $object_id, $allowed ) {
		self::$caps[ $cap . ':' . (int) $object_id ] = (bool) $allowed;
	}

	public static function can( $cap, $object_id = 0 ) {
		$key = $cap . ':' . (int) $object_id;
		return isset( self::$caps[ $key ] ) && self::$caps[ $key ];
	}

	public static function uuid() {
		return self::$uuids ? array_shift( self::$uuids ) : '';
	}

	public static $user      = 0;
	public static $stack     = array();
	public static $factories = array();
	public static $outcomes  = array();
	public static $ledger    = array();

	public static function switch_to( $user_id, $callback ) {
		self::$stack[] = self::$user;
		self::$user    = (int) $user_id;
		try { return $callback(); }
		finally { self::$user = array_pop( self::$stack ); }
	}

	public static function script( $name, $factory, $outcomes ) {
		self::$factories[ $name ] = $factory;
		self::$outcomes[ $name ]  = array_values( $outcomes );
	}

	public static function call( $name, $payload = null ) {
		unset( $payload );
		self::$ledger[] = $name;
		if ( ! isset( self::$factories[ $name ] ) ) {
			tutorpress_live_lessons_forbid_external( 'provider' );
		}
		return call_user_func( self::$factories[ $name ], array_shift( self::$outcomes[ $name ] ) );
	}

	/** Library-visible provider shapes. No discarded HTTP status and no stored outcome. */
	public static function provider_shapes() {
		$event               = new stdClass();
		$event->id           = 'evt-1';
		$event->hangoutLink  = 'https://meet.example/a';
		$event->htmlLink     = 'https://calendar.example/a';
		$event->kind         = 'calendar#event';
		$event->eventType    = 'default';
		$event->organizer    = 'owner';
		$event->recurrence   = array();
		$event->reminders    = array();
		$event->status       = 'confirmed';
		$event->transparency = 'opaque';
		$event->visibility   = 'default';
		return array(
			'google_event'             => $event,
			'google_response'          => true,
			'google_service_exception' => array( 'code' => 404 ),
			'google_network'           => 'connect_exception',
			'zoom_success'             => array( 'id' => 'z-1', 'code' => 201 ),
			'zoom_error'               => array( 'message' => 'Invalid meeting' ),
			'zoom_empty'               => array(),
			'zoom_malformed'           => null,
			'zoom_token'               => false,
			'zoom_5xx'                 => 'server_exception',
			'zoom_transport'           => 'connect_exception',
		);
	}
}

class TutorPress_Live_Lessons_Rest_Fake {
	public static $routes      = array();
	public static $params      = array();
	public static $permissions = array();
	public static $results     = array();
	public static $gates       = array();
	private static $handlers   = array();

	public static function secret_key( $key ) {
		return in_array( (string) $key, array( 'api_key', 'api_secret', 'token', 'access_token', 'refresh_token', 'client_secret', 'password' ), true );
	}

	public static function note_param( $key, $value, $present ) {
		$key = (string) $key;
		if ( self::secret_key( $key ) ) {
			return;
		}
		if ( in_array( $key, array( 'course_id', 'topic_id', 'id' ), true ) ) {
			self::$params[] = array( 'name' => $key, 'id' => is_numeric( $value ) ? (int) $value : 0 );
			return;
		}
		self::$params[] = array( 'name' => $key, 'present' => (bool) $present );
	}

	public static function note_permission( $name, $request, $result ) {
		$ids    = array();
		$params = ( $request instanceof WP_REST_Request ) ? $request->get_params() : array();
		foreach ( array( 'course_id', 'topic_id', 'id' ) as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$ids[ $key ] = is_numeric( $params[ $key ] ) ? (int) $params[ $key ] : 0;
			}
		}
		if ( $result instanceof WP_Error ) {
			$data     = $result->get_error_data();
			$recorded = array(
				'error'  => (string) $result->get_error_code(),
				'status' => is_array( $data ) && isset( $data['status'] ) && is_numeric( $data['status'] ) ? (int) $data['status'] : 0,
			);
		} elseif ( true === $result ) {
			$recorded = true;
		} else {
			$recorded = false;
		}
		self::$permissions[] = array(
			'callback' => (string) $name,
			'ids'      => $ids,
			'result'   => $recorded,
		);
	}

	public static function note_result( $value ) {
		if ( $value instanceof WP_Error ) {
			self::$results[] = array( 'kind' => 'wp_error', 'code' => (string) $value->get_error_code() );
		} elseif ( $value instanceof WP_REST_Response ) {
			self::$results[] = array( 'kind' => 'response', 'status' => (int) $value->status );
		} else {
			self::$results[] = array( 'kind' => 'other' );
		}
		return $value;
	}

	public static function note_gate() {
		self::$gates[] = true;
	}

	public static function register( $namespace, $route, $args, $override ) {
		$rows = array();
		foreach ( self::endpoint_list( $args ) as $endpoint ) {
			$methods = isset( $endpoint['methods'] ) ? $endpoint['methods'] : '';
			$list    = is_array( $methods ) ? $methods : array( $methods );
			$schema  = self::schema_fields( isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array() );
			$callback    = self::callback_name( isset( $endpoint['callback'] ) ? $endpoint['callback'] : '' );
			$permission  = self::callback_name( isset( $endpoint['permission_callback'] ) ? $endpoint['permission_callback'] : '' );
			foreach ( $list as $method ) {
				$rows[] = array(
					'method'              => (string) $method,
					'callback'            => $callback,
					'permission_callback' => $permission,
					'schema'              => $schema,
				);
			}
		}
		self::$routes[] = array(
			'namespace' => (string) $namespace,
			'route'     => (string) $route,
			'override'  => (bool) $override,
			'endpoints' => $rows,
		);
		self::$handlers[] = array(
			'namespace' => (string) $namespace,
			'route'     => (string) $route,
			'endpoints' => self::endpoint_list( $args ),
		);
		return true;
	}

	/**
	 * Validate every present registered argument, then sanitize, then call the handler.
	 * A false or WP_Error validation result rejects before sanitization and the handler.
	 * Only a successful sanitize callback replaces a value.
	 */
	public static function dispatch( $request ) {
		$endpoint = self::match_endpoint( $request );
		if ( null === $endpoint ) {
			return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
		}
		$args    = isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : array();
		$present = self::present_args( $request, $args );
		$invalid = array();
		foreach ( $present as $name => $arg ) {
			if ( empty( $arg['validate_callback'] ) || ! is_callable( $arg['validate_callback'] ) ) {
				continue;
			}
			$valid = call_user_func( $arg['validate_callback'], $request->params[ $name ], $request, $name );
			if ( false === $valid || $valid instanceof WP_Error ) {
				$invalid[ $name ] = $valid instanceof WP_Error ? (string) $valid->get_error_code() : 'invalid';
			}
		}
		if ( $invalid ) {
			return self::invalid_param( $invalid );
		}
		foreach ( $present as $name => $arg ) {
			if ( ! isset( $arg['sanitize_callback'] ) || ! is_callable( $arg['sanitize_callback'] ) ) {
				continue;
			}
			$sanitized = call_user_func( $arg['sanitize_callback'], $request->params[ $name ], $request, $name );
			if ( $sanitized instanceof WP_Error ) {
				$invalid[ $name ] = (string) $sanitized->get_error_code();
				continue;
			}
			$request->set_param( $name, $sanitized );
		}
		if ( $invalid ) {
			return self::invalid_param( $invalid );
		}
		$callback = isset( $endpoint['callback'] ) ? $endpoint['callback'] : null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'rest_invalid_handler', 'The handler for the route is invalid.', array( 'status' => 500 ) );
		}
		return call_user_func( $callback, $request );
	}

	private static function match_endpoint( $request ) {
		$route  = (string) $request->route;
		$method = strtoupper( (string) $request->method );
		foreach ( self::$handlers as $handler ) {
			if ( (string) $handler['route'] !== $route ) {
				continue;
			}
			foreach ( $handler['endpoints'] as $endpoint ) {
				if ( is_array( $endpoint ) && self::method_matches( isset( $endpoint['methods'] ) ? $endpoint['methods'] : '', $method ) ) {
					return $endpoint;
				}
			}
		}
		return null;
	}

	private static function method_matches( $methods, $method ) {
		$list = is_array( $methods ) ? $methods : explode( ',', (string) $methods );
		foreach ( $list as $item ) {
			if ( strtoupper( trim( (string) $item ) ) === $method ) {
				return true;
			}
		}
		return false;
	}

	private static function present_args( $request, $args ) {
		$present = array();
		foreach ( $args as $name => $arg ) {
			if ( ! is_string( $name ) || self::secret_key( $name ) || ! is_array( $arg ) ) {
				continue;
			}
			if ( ! array_key_exists( $name, $request->params ) || null === $request->params[ $name ] ) {
				continue;
			}
			$present[ $name ] = $arg;
		}
		return $present;
	}

	private static function invalid_param( $params ) {
		return new WP_Error( 'rest_invalid_param', 'Invalid parameter.', array( 'status' => 400, 'params' => $params ) );
	}

	private static function endpoint_list( $args ) {
		if ( ! is_array( $args ) ) {
			return array();
		}
		if ( array_key_exists( 'methods', $args ) || array_key_exists( 'callback', $args ) || array_key_exists( 'permission_callback', $args ) ) {
			return array( $args );
		}
		$list = array();
		foreach ( $args as $endpoint ) {
			if ( is_array( $endpoint ) ) {
				$list[] = $endpoint;
			}
		}
		return $list;
	}

	private static function callback_name( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $class . '::' . $callback[1];
		}
		return '';
	}

	private static function schema_fields( $args ) {
		$fields = array();
		foreach ( $args as $name => $arg ) {
			if ( ! is_string( $name ) || self::secret_key( $name ) || ! is_array( $arg ) ) {
				continue;
			}
			$fields[] = array(
				'name'              => $name,
				'type'              => isset( $arg['type'] ) && is_string( $arg['type'] ) ? $arg['type'] : '',
				'required'          => isset( $arg['required'] ) && true === $arg['required'],
				'enum'              => self::scalar_list( isset( $arg['enum'] ) ? $arg['enum'] : null ),
				'default'           => array_key_exists( 'default', $arg ) ? self::scalar_or_null( $arg['default'] ) : null,
				'sanitize_callback' => self::callback_name( isset( $arg['sanitize_callback'] ) ? $arg['sanitize_callback'] : '' ),
			);
		}
		return $fields;
	}

	private static function scalar_list( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				return null;
			}
		}
		return array_values( $value );
	}

	private static function scalar_or_null( $value ) {
		return is_scalar( $value ) ? $value : null;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
		return TutorPress_Live_Lessons_Rest_Fake::register( $namespace, $route, $args, $override );
	}
}

class TutorPress_Live_Lessons_Service_Fake {
	public static $factories = array();
	public static $resolved  = array();
	public static $returns   = array();
	public static $fallbacks = array();
	public static $topics    = array();
	public static $children  = array();
	public static $localized = array();

	public static function register( $id, $factory ) {
		self::$factories[ (string) $id ] = $factory;
	}

	public static function get( $id ) {
		$id                 = (string) $id;
		self::$resolved[]   = $id;
		if ( array_key_exists( $id, self::$returns ) ) {
			return self::$returns[ $id ];
		}
		$factory = isset( self::$factories[ $id ] ) ? self::$factories[ $id ] : null;
		$value   = is_callable( $factory ) ? call_user_func( $factory ) : null;
		self::$returns[ $id ] = $value;
		return $value;
	}

	public static function note_fallback( $route ) {
	}

	public static function topic_rows( $topic_id, $course_id, $rows ) {
		$topic_id  = (int) $topic_id;
		$course_id = (int) $course_id;
		$denied    = $topic_id <= 0 || $course_id <= 0;
		self::$topics[] = array(
			'topic_id'  => $topic_id,
			'course_id' => $course_id,
			'denied'    => $denied,
		);
		if ( $denied ) {
			return null;
		}
		return is_array( $rows ) ? $rows : array();
	}

	public static function child_handler( $kind, $child_id ) {
		self::$children[] = array(
			'kind'     => (string) $kind,
			'child_id' => (int) $child_id,
		);
		return true;
	}

	public static function localize( $handle, $object_name, $data ) {
		self::$localized[] = array(
			'handle' => (string) $handle,
			'name'   => (string) $object_name,
			'data'   => self::strip_secrets( $data ),
		);
		return true;
	}

	private static function strip_secrets( $data ) {
		if ( ! is_array( $data ) ) {
			return is_scalar( $data ) || null === $data ? $data : null;
		}
		$clean = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && TutorPress_Live_Lessons_Rest_Fake::secret_key( $key ) ) {
				continue;
			}
			$clean[ $key ] = self::strip_secrets( $value );
		}
		return $clean;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $data ) {
		return TutorPress_Live_Lessons_Service_Fake::localize( $handle, $object_name, $data );
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

