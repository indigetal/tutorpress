<?php
/**
 * TutorPress Service Container
 *
 * Provides dependency injection capabilities and centralized service management
 * for service registration and discovery.
 *
 * @package TutorPress
 * @since 1.13.17
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service container for dependency injection and service management.
 *
 * @since 1.13.17
 */
class TutorPress_Service_Container {

    /**
     * Registered services (instances).
     *
     * @var array
     */
    private $services = [];

    /**
     * Service factories (callables).
     *
     * @var array
     */
    private $factories = [];

    /**
     * Dependency ids recorded when a factory resolves.
     *
     * @var array
     */
    private $dependencies = [];

    /**
     * Singleton instance.
     *
     * @var TutorPress_Service_Container|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return TutorPress_Service_Container
     */
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a service instance.
     *
     * @param string $id Service identifier
     * @param mixed $service Service instance
     * @return void
     */
    public function register(string $id, $service): void {
        $this->services[$id] = $service;
    }

    /**
     * Register a service factory (lazy loading).
     *
     * @param string $id Service identifier
     * @param callable $factory Factory function
     * @return void
     */
    public function register_factory(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    /**
     * Get a service by ID.
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws InvalidArgumentException If service not found
     */
    public function get(string $id) {
        // Return existing service
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Create service using factory
        if (isset($this->factories[$id])) {
            $service = call_user_func($this->factories[$id], $this);
            $this->services[$id] = $service;
            return $service;
        }

        throw new InvalidArgumentException("Service '{$id}' not found");
    }

    /**
     * Check if a service is registered.
     *
     * @param string $id Service identifier
     * @return bool True if service exists
     */
    public function has(string $id): bool {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    /**
     * Get all registered service IDs.
     *
     * @return array Service identifiers
     */
    public function get_all_services(): array {
        return array_keys(array_merge($this->services, $this->factories));
    }

    /**
     * Dependency ids recorded for one resolved service.
     *
     * @param string $id Service identifier.
     * @return array
     */
    public function dependencies(string $id): array {
        return isset($this->dependencies[$id]) ? $this->dependencies[$id] : array();
    }

    /**
     * Whether get() has already built this service.
     *
     * @param string $id Service identifier.
     * @return bool
     */
    public function resolved(string $id): bool {
        return isset($this->services[$id]);
    }

    /**
     * Private constructor (singleton pattern).
     */
    private function __construct() {
        $this->register_foundation_services();
        $this->register_read_services();
        $this->register_provider_services();
        $this->register_mutation_services();
    }

    /**
     * Register static repository, envelope, authorization, and response-projector services.
     *
     * @return void
     */
    private function register_foundation_services(): void {
        $this->register_factory(
            'live_lesson.repository',
            static function () {
                return TutorPress_Live_Lesson_Sync_Repository::class;
            }
        );
        $this->register_factory(
            'live_lesson.envelope',
            static function () {
                return TutorPress_Live_Lesson_Sync_Envelope::class;
            }
        );
        $this->register_factory(
            'live_lesson.authorization',
            static function () {
                return TutorPress_Live_Lesson_Authorization::class;
            }
        );
        $this->register_factory(
            'live_lesson.response_projector',
            static function () {
                return TutorPress_Live_Lesson_Response_Projector::class;
            }
        );
    }

    /**
     * Register ordering, collection-query, and topic-projector services.
     *
     * @return void
     */
    private function register_read_services(): void {
        $this->register_factory(
            'live_lesson.ordering',
            function () {
                $this->note_dependencies('live_lesson.ordering', array());
                return TutorPress_Live_Lesson_Ordering::class;
            }
        );
        $this->register_factory(
            'live_lesson.collection_query',
            function () {
                $this->get('live_lesson.authorization');
                $this->note_dependencies('live_lesson.collection_query', array('live_lesson.authorization'));
                return TutorPress_Live_Lesson_Collection_Query::class;
            }
        );
        $this->register_factory(
            'live_lesson.topic_projector',
            function () {
                $this->get('live_lesson.authorization');
                $this->get('live_lesson.response_projector');
                $this->note_dependencies('live_lesson.topic_projector', array('live_lesson.authorization', 'live_lesson.response_projector'));
                return TutorPress_Live_Lesson_Topic_Projector::class;
            }
        );
    }

    /**
     * Record the dependency ids a factory resolved.
     *
     * @param string $id Service identifier.
     * @param array  $ids Dependency identifiers.
     * @return void
     */
    private function note_dependencies(string $id, array $ids): void {
        $this->dependencies[$id] = array_values($ids);
    }

    /**
     * Register the owner resolver and the Google adapter.
     *
     * @return void
     */
    private function register_provider_services(): void {
        $this->register_factory(
            'live_lesson.owner_resolver',
            static function () {
                return TutorPress_Live_Lesson_Owner_Resolver::class;
            }
        );
        $this->register_factory(
            'TutorPress_Live_Lesson_Provider_Adapter:google_meet',
            function () {
                return new TutorPress_Google_Meet_Provider_Adapter(
                    function ( $owner_id ) { return $this->google_ready( $owner_id ); },
                    function () { return $this->current_user_id(); },
                    function ( $user_id ) { $this->switch_user( $user_id ); },
                    function ( $user_id ) { $this->switch_user( $user_id ); },
                    function () { return $this->google_calendar(); },
                    function () { return $this->google_settings(); }
                );
            }
        );
        $this->register_factory(
            'TutorPress_Live_Lesson_Provider_Adapter:zoom',
            function () {
                return new TutorPress_Zoom_Provider_Adapter(
                    function () { return $this->current_user_id(); },
                    function ( $user_id ) { $this->switch_user( $user_id ); },
                    function ( $user_id ) { $this->switch_user( $user_id ); },
                    function () { return $this->zoom_credentials(); },
                    function () { return $this->zoom_token(); },
                    function () { return $this->zoom_meetings(); },
                    function () { return $this->zoom_users(); }
                );
            }
        );
    }

    /**
     * Current WordPress user id.
     *
     * @return int
     */
    private function current_user_id() {
        return get_current_user_id();
    }

    /**
     * Switch the current WordPress user.
     *
     * @param mixed $user_id User id.
     * @return void
     */
    private function switch_user( $user_id ) {
        wp_set_current_user( (int) $user_id );
    }

    /**
     * Whether the owner's Google credential file is present.
     *
     * @param mixed $owner_id Owner user id.
     * @return bool
     */
    private function google_ready( $owner_id ) {
        $user = get_userdata( (int) $owner_id );
        if ( ! ( $user instanceof WP_User ) || ! is_string( $user->user_login ) || '' === $user->user_login ) {
            return false;
        }
        $upload = wp_upload_dir();
        $base   = is_array( $upload ) && isset( $upload['basedir'] ) && is_string( $upload['basedir'] ) ? $upload['basedir'] : '';
        return '' !== $base && is_file( trailingslashit( $base ) . 'tutor-json/' . md5( $user->user_login ) . '-credential.json' );
    }

    /**
     * Google calendar surface for the already switched owner.
     *
     * @return stdClass|null
     */
    private function google_calendar() {
        if ( ! class_exists( 'TutorPro\GoogleMeet\GoogleEvent\GoogleEvent' ) ) {
            return null;
        }
        $client = new \TutorPro\GoogleMeet\GoogleEvent\GoogleEvent();
        if ( ! is_object( $client->service ) || ! is_object( $client->service->events ) ) {
            return null;
        }
        $calendar                     = new stdClass();
        $calendar->events             = $client->service->events;
        $calendar->current_calendar  = isset( $client->current_calendar ) ? $client->current_calendar : 'primary';
        return $calendar;
    }

    /**
     * Sanitized Google settings envelope for the already switched owner.
     *
     * @return array|null
     */
    private function google_settings() {
        if ( ! class_exists( 'TutorPro\GoogleMeet\GoogleEvent\GoogleEvent' ) || ! class_exists( 'TutorPro\GoogleMeet\Settings\Settings' ) ) {
            return null;
        }
        $client     = new \TutorPro\GoogleMeet\GoogleEvent\GoogleEvent();
        $stored     = maybe_unserialize( get_user_meta( get_current_user_id(), \TutorPro\GoogleMeet\Settings\Settings::META_KEY, true ) );
        $stored     = is_array( $stored ) ? $stored : array();
        $formatted  = array();
        foreach ( \TutorPro\GoogleMeet\Settings\Settings::default_settings() as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['name'] ) || ! is_string( $row['name'] ) ) {
                continue;
            }
            $name                 = $row['name'];
            $formatted[ $name ]   = array(
                'value'   => array_key_exists( $name, $stored ) ? $stored[ $name ] : ( isset( $row['default_value'] ) ? $row['default_value'] : null ),
                'label'   => isset( $row['label'] ) ? $row['label'] : '',
                'type'    => isset( $row['type'] ) ? $row['type'] : '',
                'options' => isset( $row['options'] ) ? $row['options'] : null,
            );
        }
        $authorized = true === $client->is_app_permitted();
        $loaded     = true === $client->is_credential_loaded();
        return array(
            'is_authorized'     => $authorized,
            'has_credentials'   => $loaded,
            'settings'          => $formatted,
            'authorization_url' => ( ! $authorized && $loaded ) ? $client->get_consent_screen_url() : null,
            'setup_url'         => admin_url( 'admin.php?page=google-meet&tab=set-api' ),
        );
    }

    /**
     * Current user's Zoom API strings.
     *
     * @return array
     */
    private function zoom_credentials() {
        $data  = $this->zoom_api();
        $clean = array();
        foreach ( array( 'account_id', 'api_key', 'api_secret' ) as $key ) {
            $clean[ $key ] = isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? $data[ $key ] : '';
        }
        return $clean;
    }

    /**
     * Whether the switched owner has the three Zoom API strings.
     *
     * @return bool
     */
    private function zoom_token() {
        $data = $this->zoom_api();
        foreach ( array( 'account_id', 'api_key', 'api_secret' ) as $key ) {
            if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) || '' === $data[ $key ] ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Meetings endpoint for the switched owner.
     *
     * @return object|null
     */
    private function zoom_meetings() {
        return $this->zoom_endpoint( '\Zoom\Endpoint\Meetings' );
    }

    /**
     * Users endpoint for the switched owner.
     *
     * @return object|null
     */
    private function zoom_users() {
        return $this->zoom_endpoint( '\Zoom\Endpoint\Users' );
    }

    /**
     * Zoom API array from the current user.
     *
     * @return array
     */
    private function zoom_api() {
        if ( ! class_exists( 'TUTOR_ZOOM\Zoom' ) ) {
            return array();
        }
        $data = ( new \TUTOR_ZOOM\Zoom( false ) )->get_api();
        return is_array( $data ) ? $data : array();
    }

    /**
     * Zoom endpoint object for one Tutor Pro class name.
     *
     * @param string $class_name Endpoint class.
     * @return object|null
     */
    private function zoom_endpoint( $class_name ) {
        $data   = $this->zoom_api();
        $key    = isset( $data['api_key'] ) && is_string( $data['api_key'] ) ? $data['api_key'] : '';
        $secret = isset( $data['api_secret'] ) && is_string( $data['api_secret'] ) ? $data['api_secret'] : '';
        if ( '' === $key || '' === $secret || ! function_exists( 'tutor_utils' ) ) {
            return null;
        }
        $client = tutor_utils()->get_package_object( true, $class_name, $key, $secret );
        return is_object( $client ) ? $client : null;
    }

    /**
     * Register orchestrator, duplication, and cascade services separately.
     *
     * @return void
     */
    private function register_mutation_services(): void {
        $this->register_factory(
            'live_lesson.orchestrator',
            function () {
                return $this->resolve_class(
                    'live_lesson.orchestrator',
                    array(
                        'live_lesson.repository',
                        'live_lesson.envelope',
                        'live_lesson.authorization',
                        'live_lesson.owner_resolver',
                        'live_lesson.ordering',
                        'live_lesson.response_projector',
                        'TutorPress_Live_Lesson_Provider_Adapter:google_meet',
                        'TutorPress_Live_Lesson_Provider_Adapter:zoom',
                    ),
                    TutorPress_Live_Lesson_Sync_Orchestrator::class
                );
            }
        );
        $shared = array( 'live_lesson.authorization', 'live_lesson.ordering', 'live_lesson.repository', 'live_lesson.response_projector' );
        $this->register_factory(
            'live_lesson.direct_duplicate',
            function () use ( $shared ) {
                return $this->resolve_class( 'live_lesson.direct_duplicate', $shared, TutorPress_Live_Lesson_Duplicate_Service::class );
            }
        );
        $this->register_factory(
            'live_lesson.topic_duplication',
            function () use ( $shared ) {
                return $this->resolve_class( 'live_lesson.topic_duplication', $shared, TutorPress_Topic_Duplication_Service::class );
            }
        );
        $this->register_factory(
            'live_lesson.cascade_delete',
            function () {
                return $this->resolve_class( 'live_lesson.cascade_delete', array( 'live_lesson.authorization' ), TutorPress_Live_Lesson_Cascade_Delete::class );
            }
        );
    }

    /**
     * Resolve dependency ids, record them, and return the static class name.
     *
     * @param string $id Service identifier.
     * @param array  $dependencies Dependency identifiers.
     * @param string $class_name Class string.
     * @return string
     */
    private function resolve_class( $id, array $dependencies, $class_name ) {
        foreach ( $dependencies as $dependency ) {
            $this->get( $dependency );
        }
        $this->note_dependencies( $id, $dependencies );
        return $class_name;
    }

    /**
     * Prevent cloning.
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'tutorpress'), '1.13.17');
    }

    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing is forbidden.', 'tutorpress'), '1.13.17');
    }
}
