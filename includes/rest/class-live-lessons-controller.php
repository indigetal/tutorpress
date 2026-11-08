<?php
/**
 * Live Lessons REST Controller Class
 *
 * Handles REST API functionality for Live Lessons (Google Meet and Zoom integration).
 *
 * @package TutorPress
 * @since 1.5.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Live_Lessons_Controller extends TutorPress_REST_Controller {

    /** Canonical provider names passed to provider_adapter(). */
    public $adapter_resolutions = [];

    /**
     * Constructor.
     *
     * @since 1.5.0
     */
    public function __construct() {
        $this->rest_base = 'live-lessons';
    }

    /**
     * Register REST API routes.
     *
     * Production calls omit $enabled and therefore use true.
     * An explicit boolean is a test seam only.
     *
     * @since 1.5.0
     * @param bool|null $enabled Optional gate override for tests.
     * @return void
     */
    public function register_routes($enabled = null) {
        $enabled = null === $enabled ? true : $enabled;

        // Get live lessons for a topic or course
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get live lessons for.', 'tutorpress'),
                        ],
                        'topic_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to get live lessons for.', 'tutorpress'),
                        ],
                        'type' => [
                            'type'              => 'string',
                            'enum'              => ['google_meet', 'zoom'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Filter by live lesson type.', 'tutorpress'),
                        ],
                        'status' => [
                            'type'              => 'string',
                            'enum'              => ['scheduled', 'live', 'ended', 'cancelled'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Filter by live lesson status.', 'tutorpress'),
                        ],
                        'per_page' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'description'       => __('Number of results per page.', 'tutorpress'),
                        ],
                        'page' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'default'           => 1,
                            'minimum'           => 1,
                            'description'       => __('Page number for pagination.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'mutate_schedule'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => $this->with_schedule_contract(true, [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course for the live lesson.', 'tutorpress'),
                        ],
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic for the live lesson.', 'tutorpress'),
                        ],
                        'title' => [
                            'required'          => true,
                            'type'             => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the live lesson.', 'tutorpress'),
                        ],
                        'description' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The description of the live lesson.', 'tutorpress'),
                            'default'           => '',
                        ],
                        'type' => [
                            'required'          => true,
                            'type'             => 'string',
                            'enum'              => ['google_meet', 'zoom'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The type of live lesson.', 'tutorpress'),
                        ],
                        'start_date_time' => $this->schedule_arg(true, __('Start date and time as a minute wall clock.', 'tutorpress')),
                        'end_date_time' => $this->schedule_arg(true, __('End date and time as a minute wall clock.', 'tutorpress')),
                        'settings' => [
                            'type'              => 'object',
                            'description'       => __('Live lesson settings.', 'tutorpress'),
                            'properties'        => [
                                'timezone' => [
                                    'type'    => 'string',
                                    'default' => 'UTC',
                                ],
                                'duration' => [
                                    'type'    => 'integer',
                                    'minimum' => 1,
                                    'default' => 60,
                                ],
                                'allow_early_join' => [
                                    'type'    => 'boolean',
                                    'default' => true,
                                ],
                                'auto_record' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                                'require_password' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                                'waiting_room' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                            ],
                            'default'           => [],
                        ],
                        'provider_config' => [
                            'type'              => 'object',
                            'description'       => __('Provider-specific configuration.', 'tutorpress'),
                            'default'           => [],
                        ],
                    ]),
                ],
            ]
        );

        // Zoom users endpoint for Meeting Host dropdown
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/zoom/users',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_zoom_users'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Course ID for collaborative instructor Zoom access.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Google Meet settings endpoint
        $google_meet_endpoint = [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_google_meet_settings'],
            'permission_callback' => [$this, 'check_permission'],
        ];
        $google_meet_endpoint['args'] = [
            'course_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => __('Course ID for Google Meet settings.', 'tutorpress'),
            ],
        ];
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/google-meet/settings',
            [$google_meet_endpoint]
        );

        // Single live lesson operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => [
                        'id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the live lesson.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'mutate_schedule'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => $this->with_schedule_contract(true, [
                        'title' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the live lesson.', 'tutorpress'),
                        ],
                        'description' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The description of the live lesson.', 'tutorpress'),
                        ],
                        'start_date_time' => $this->schedule_arg(false, __('Start date and time as a minute wall clock.', 'tutorpress')),
                        'end_date_time' => $this->schedule_arg(false, __('End date and time as a minute wall clock.', 'tutorpress')),
                        'status' => [
                            'type'              => 'string',
                            'enum'              => ['scheduled', 'live', 'ended', 'cancelled'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The status of the live lesson.', 'tutorpress'),
                        ],
                        'settings' => [
                            'type'              => 'object',
                            'description'       => __('Live lesson settings.', 'tutorpress'),
                        ],
                        'provider_config' => [
                            'type'              => 'object',
                            'description'       => __('Provider-specific configuration.', 'tutorpress'),
                        ],
                    ]),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                ],
            ]
        );

        // Duplicate live lesson
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'duplicate_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => [
                        'topic_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Target topic ID for the duplicated lesson.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/attach/uncertain-create', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'attach_item'], 'permission_callback' => [$this, 'check_lesson_permission'],
            'args' => ['provider_id' => ['required' => true, 'type' => 'string'], 'version' => ['required' => true, 'type' => 'integer'], 'operation_id' => ['required' => true, 'type' => 'string']],
        ]]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/attach/legacy', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'attach_item'], 'permission_callback' => [$this, 'check_lesson_permission'],
            'args' => ['provider_id' => ['required' => true, 'type' => 'string'], 'version' => ['required' => true, 'type' => 'integer'], 'selected_owner_id' => ['type' => 'integer']],
        ]]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/reconcile', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'reconcile_item'], 'permission_callback' => [$this, 'check_lesson_permission'],
            'args' => ['action' => ['required' => true, 'type' => 'string', 'enum' => ['inspect', 'retry', 'resolve_owner', 'abandon_unknown']], 'version' => ['required' => true, 'type' => 'integer'], 'operation_id' => ['type' => 'string', 'validate_callback' => [$this, 'validate_reconcile_operation']]],
        ]]);
    }

    /**
     * True-gate minute wall-clock argument.
     *
     * @param bool   $required    Whether POST requires the field.
     * @param string $description Field description.
     * @return array<string,mixed>
     */
    private function schedule_arg($required, $description) {
        $arg = [
            'type'              => 'string',
            'validate_callback' => ['TutorPress_Live_Lesson_Datetime', 'is_wall_clock'],
            'sanitize_callback' => [$this, 'sanitize_wall_clock'],
            'description'       => $description,
        ];
        if ($required) {
            $arg['required'] = true;
        }
        return $arg;
    }

    /**
     * Return an accepted wall clock. Invalid values are rejected without sanitize_text_field.
     *
     * @param mixed $value Request value.
     * @return string|WP_Error
     */
    public function sanitize_wall_clock($value) {
        return TutorPress_Live_Lesson_Datetime::is_wall_clock($value) ? $value : new WP_Error('invalid_wall_clock', __('Invalid wall clock.', 'tutorpress'), ['status' => 400]);
    }

    /**
     * Add true-gate duration and timezone. False-gate arguments stay unchanged.
     *
     * @param bool                $enabled Gate value.
     * @param array<string,mixed> $args    Registered arguments.
     * @return array<string,mixed>
     */
    private function with_schedule_contract($enabled, $args) {
        if (!$enabled) {
            return $args;
        }
        $args['duration'] = [
            'type'              => 'object',
            'validate_callback' => [$this, 'validate_duration'],
            'sanitize_callback' => [$this, 'sanitize_duration'],
            'description'       => __('Typed duration for the live lesson.', 'tutorpress'),
        ];
        $args['timezone'] = [
            'type'              => 'string',
            'validate_callback' => [$this, 'validate_timezone'],
            'description'       => __('Approved timezone for the live lesson.', 'tutorpress'),
        ];
        return $args;
    }

    /**
     * Accept a Zoom duration without replacing the request value.
     *
     * @param mixed $value Request value.
     * @return true|WP_Error
     */
    public function validate_duration($value) {
        return is_array($this->duration_value($value)) ? true : new WP_Error('invalid_duration', __('Invalid duration.', 'tutorpress'), ['status' => 400]);
    }

    /**
     * Replace a duration with the typed Zoom value.
     *
     * @param mixed $value Request value.
     * @return array{value:int,unit:string}|WP_Error
     */
    public function sanitize_duration($value) {
        $typed = $this->duration_value($value);
        return is_array($typed) ? $typed : new WP_Error('invalid_duration', __('Invalid duration.', 'tutorpress'), ['status' => 400]);
    }

    /**
     * Typed duration, or null when the submitted pair is not a Zoom duration.
     *
     * @param mixed $value Request value.
     * @return array{value:int,unit:string}|null
     */
    private function duration_value($value) {
        return is_array($value) && array_key_exists('value', $value) && array_key_exists('unit', $value) ? TutorPress_Live_Lesson_Datetime::zoom_duration($value['value'], $value['unit']) : null;
    }

    /**
     * Accept only an approved timezone.
     *
     * @param mixed $value Request value.
     * @return true|WP_Error
     */
    public function validate_timezone($value) {
        return TutorPress_Live_Lesson_Datetime::is_approved_timezone($value) ? true : new WP_Error('invalid_timezone', __('Invalid timezone.', 'tutorpress'), ['status' => 400]);
    }

    /**
     * True-gate POST/PATCH seam. Incomplete requests stay schedule-only.
     *
     * @param WP_REST_Request $request Request.
     * @return array<string,mixed>|WP_Error
     */
    public function mutate_schedule($request) {
        $normalized = $this->normalize_mutation($request);
        $duration = is_wp_error($normalized) ? null : $request->get_param('duration');
        if (is_array($duration)) {
            $normalized['duration'] = $duration;
        }
        if (is_wp_error($normalized) || !$this->replacement_enabled()) {
            return is_wp_error($normalized) ? $normalized : $this->schedule_result($normalized);
        }
        if ($this->create_shaped($request, $normalized)) {
            return $this->delegate_create($request, $normalized);
        }
        return $this->update_shaped($request) ? $this->delegate_update($request, $normalized) : $this->schedule_result($normalized);
    }

    /**
     * Route-shaped collection POST. Incomplete schedule probes stay on the schedule seam.
     *
     * @param WP_REST_Request     $request    Request.
     * @param array<string,mixed> $normalized Normalized schedule.
     * @return bool
     */
    private function create_shaped($request, array $normalized) {
        $method = method_exists($request, 'get_method') ? $request->get_method() : (isset($request->method) ? $request->method : '');
        $type = $request->get_param('type');
        return 'POST' === strtoupper((string) $method) && is_string($request->get_param('title')) && ('google_meet' === $type || 'zoom' === $type) && isset($normalized['start_date_time'], $normalized['end_date_time']) && is_string($normalized['start_date_time']) && is_string($normalized['end_date_time']) && null !== $request->get_param('course_id') && null !== $request->get_param('topic_id');
    }

    /**
     * Delegate one authorized create. Lifecycle work stays in the orchestrator.
     *
     * @param WP_REST_Request     $request    Request.
     * @param array<string,mixed> $normalized Normalized schedule.
     * @return WP_Error|WP_REST_Response
     */
    private function delegate_create($request, array $normalized) {
        $bindings = $this->request_bindings();
        $course_id = $this->scalar_id($request->get_param('course_id'));
        $topic_id = $this->scalar_id($request->get_param('topic_id'));
        $course = is_array($course = ($course_id > 0 ? $bindings['lookup']($course_id) : null)) ? $course : [];
        $topic = is_array($topic = ($topic_id > 0 ? $bindings['lookup']($topic_id) : null)) ? $topic : [];
        $can = static function ($capability, $id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $id); };
        if (null === TutorPress_Live_Lesson_Authorization::authorize_create($topic, $course, $can)) {
            $linked = isset($topic['ID'], $topic['post_type'], $topic['post_parent'], $course['ID'], $course['post_type']) && 'topics' === $topic['post_type'] && 'courses' === $course['post_type'] && (int) $topic['post_parent'] === (int) $course['ID'] && (int) $course['ID'] > 0;
            return $linked ? new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to create live lessons in this course.', 'tutorpress'), ['status' => rest_authorization_required_code()]) : new WP_Error('invalid_topic', __('Invalid topic ID or topic does not belong to the specified course.', 'tutorpress'), ['status' => 404]);
        }
        $input = $this->map_mutation($request, $normalized, $course, $bindings['user_id']);
        $adapter = $this->provider_adapter(isset($input['provider']) ? $input['provider'] : '');
        $staged = $this->stage_live_create($topic, $course, $can, $input, is_array($children = $bindings['children']((int) $topic['ID'])) ? $children : [], $adapter, $bindings['clock'], $bindings['token']);
        $ready = $this->staged_create($staged);
        $result = is_array($ready) ? $this->dispatch_live_create($ready, $bindings['token'], $adapter, $bindings['read'], $bindings['update'], $bindings['affected'], $bindings['write'], $topic, $course, $can) : $staged;
        $accepted = is_array($ready) && is_array($result) && isset($result['envelope']) ? $this->accepted_bytes($result['envelope']) : null;
        return $this->rest_outcome('create', is_array($accepted) ? $accepted : (is_array($ready) && !is_wp_error($result) && null !== $result ? null : $result));
    }

    /** @param mixed $value Request id. @return int */
    private function scalar_id($value) {
        return is_int($value) && $value > 0 ? $value : (is_string($value) && 1 === preg_match('/\A[0-9]+\z/', $value) ? (int) $value : 0);
    }

    /** @param mixed $bytes Envelope bytes. @return array<string,mixed>|null */
    private function accepted_bytes($bytes) {
        $decoded = is_string($bytes) ? json_decode($bytes, true) : null;
        $accepted = is_array($decoded) ? TutorPress_Live_Lesson_Sync_Envelope::envelope($decoded) : null;
        return is_array($accepted) && TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes($accepted) === $bytes ? $accepted : null;
    }

    /** @param mixed $result Staging result. @return array<string,mixed>|null */
    private function staged_create($result) {
        return is_array($result) && isset($result['post_id'], $result['lock'], $result['owner_id'], $result['envelope']) && is_string($result['envelope']) && is_array($this->accepted_bytes($result['envelope'])) ? $result : null;
    }

    /**
     * @param array<string,mixed> $topic    Topic row.
     * @param array<string,mixed> $course   Course row.
     * @param callable            $can      Authorization callable.
     * @param array<string,mixed> $input    Mapped input.
     * @param array<int,mixed>    $children Topic children.
     * @param mixed               $adapter  Provider adapter.
     * @param DateTimeImmutable   $now      Injected clock.
     * @param mixed               $token    Injected token.
     * @return mixed
     */
    protected function stage_live_create(array $topic, array $course, $can, array $input, array $children, $adapter, DateTimeImmutable $now, $token) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending($topic, $course, $can, $input, $children, $adapter, $now, $token);
    }

    /**
     * @param array<string,mixed> $staged   Staged envelope.
     * @param mixed               $token    Injected token.
     * @param mixed               $adapter  Provider adapter.
     * @param callable            $read     Sync read.
     * @param callable            $update   Sync update.
     * @param callable            $affected Affected count.
     * @param callable            $write    Sync insert.
     * @param array<string,mixed> $topic    Topic row.
     * @param array<string,mixed> $course   Course row.
     * @param callable            $can      Authorization callable.
     * @return mixed
     */
    protected function dispatch_live_create(array $staged, $token, $adapter, $read, $update, $affected, $write, array $topic, array $course, $can) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create($staged, $token, $adapter, $read, $update, $affected, $write, $topic, $course, $can);
    }

    /**
     * Route-shaped item PATCH. Incomplete schedule probes stay on the schedule seam.
     *
     * @param WP_REST_Request $request Request.
     * @return bool
     */
    private function update_shaped($request) {
        $method = method_exists($request, 'get_method') ? $request->get_method() : (isset($request->method) ? $request->method : '');
        return 'PATCH' === strtoupper((string) $method) && $this->scalar_id($request->get_param('id')) > 0;
    }

    /**
     * Delegate one authorized PATCH by the canonical stored sync state.
     *
     * @param WP_REST_Request     $request    Request.
     * @param array<string,mixed> $normalized Normalized schedule.
     * @return WP_Error|WP_REST_Response
     */
    private function delegate_update($request, array $normalized) {
        $bindings = $this->request_bindings();
        $id = $this->scalar_id($request->get_param('id'));
        $item = $id > 0 ? $bindings['lookup']($id) : null;
        $missing = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]);
        if ($id < 1 || !is_array($item)) { return $missing; }
        $type = isset($item['post_type']) ? $item['post_type'] : '';
        if ('tutor-google-meet' !== $type && 'tutor_zoom_meeting' !== $type) { return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]); }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0));
        if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
        if (!$linked) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); };
        if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) { return $missing; }
        $stored = $bindings['envelope']((int) $item['ID']);
        $state = is_array($stored) && isset($stored['state']) ? $stored['state'] : '';
        if ('unlinked' !== $state && 'synced' !== $state) { return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]); }
        $provider = 'tutor_zoom_meeting' === $type ? 'zoom' : 'google_meet';
        $input = $this->map_mutation($request, $normalized, $course, $bindings['user_id']);
        $input['provider'] = $provider;
        $adapter = $this->provider_adapter($provider);
        $staged = 'unlinked' === $state ? $this->stage_live_unlinked($item, $topic, $course, $can, $input, $adapter, $bindings['clock'], $bindings['token'], $bindings['read'], $bindings['update'], $bindings['affected']) : $this->stage_live_synced($item, $topic, $course, $can, $input, $adapter, $bindings['clock'], $bindings['token'], $bindings['read'], $bindings['update'], $bindings['affected']);
        $ready = $this->staged_create($staged);
        $result = is_array($ready) ? ('unlinked' === $state ? $this->dispatch_live_create($ready, $bindings['token'], $adapter, $bindings['read'], $bindings['update'], $bindings['affected'], $bindings['write'], $topic, $course, $can) : $this->dispatch_live_synced($ready, $bindings['token'], $adapter, $bindings['read'], $bindings['update'], $bindings['affected'], $bindings['write'])) : $staged;
        $accepted = is_array($ready) && is_array($result) && isset($result['envelope']) ? $this->accepted_bytes($result['envelope']) : null;
        return $this->rest_outcome('update', is_array($accepted) ? $accepted : (is_array($ready) && !is_wp_error($result) && null !== $result ? null : $result));
    }

    /**
     * @param array<string,mixed> $item     Canonical item.
     * @param array<string,mixed> $topic    Topic row.
     * @param array<string,mixed> $course   Course row.
     * @param callable            $can      Authorization callable.
     * @param array<string,mixed> $input    Mapped input.
     * @param mixed               $adapter  Provider adapter.
     * @param DateTimeImmutable   $now      Injected clock.
     * @param mixed               $token    Injected token.
     * @param callable            $read     Sync read.
     * @param callable            $update   Sync update.
     * @param callable            $affected Affected count.
     * @return mixed
     */
    protected function stage_live_unlinked(array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $update, $affected);
    }

    /**
     * @param array<string,mixed> $item     Canonical item.
     * @param array<string,mixed> $topic    Topic row.
     * @param array<string,mixed> $course   Course row.
     * @param callable            $can      Authorization callable.
     * @param array<string,mixed> $input    Mapped input.
     * @param mixed               $adapter  Provider adapter.
     * @param DateTimeImmutable   $now      Injected clock.
     * @param mixed               $token    Injected token.
     * @param callable            $read     Sync read.
     * @param callable            $update   Sync update.
     * @param callable            $affected Affected count.
     * @return mixed
     */
    protected function stage_live_synced(array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::stage_update($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $update, $affected);
    }

    /**
     * @param array<string,mixed> $staged   Staged envelope.
     * @param mixed               $token    Injected token.
     * @param mixed               $adapter  Provider adapter.
     * @param callable            $read     Sync read.
     * @param callable            $update   Sync update.
     * @param callable            $affected Affected count.
     * @param callable            $write    Sync insert.
     * @return mixed
     */
    protected function dispatch_live_synced(array $staged, $token, $adapter, $read, $update, $affected, $write) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_update($staged, $token, $adapter, $read, $update, $affected, $write);
    }

    /**
     * Delegate one authorized DELETE. Lifecycle work stays in the orchestrator.
     *
     * Relationship and edit_post finish before adapter resolution. The staged
     * envelope is the only success payload. A confirmed dispatch state becomes
     * the existing delete success contract from that envelope's provider id.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_Error|WP_REST_Response
     */
    private function delegate_delete($request) {
        $bindings = $this->request_bindings();
        $id = $this->scalar_id($request->get_param('id'));
        $item = $id > 0 ? $bindings['lookup']($id) : null;
        $missing = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]);
        if ($id < 1 || !is_array($item)) { return $missing; }
        $type = isset($item['post_type']) ? $item['post_type'] : '';
        if ('tutor-google-meet' !== $type && 'tutor_zoom_meeting' !== $type) { return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]); }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0));
        if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
        if (!$linked) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); };
        if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) { return $missing; }
        $actor = (int) $bindings['user_id'];
        $author = isset($course['post_author']) ? (int) $course['post_author'] : 0;
        $input = ['acting_user_id' => $actor > 0 ? $actor : 0, 'course_author_id' => $author > 0 ? $author : 0, 'acting_instructor_id' => $actor > 0 ? $actor : 0];
        $adapter = $this->provider_adapter('tutor_zoom_meeting' === $type ? 'zoom' : 'google_meet');
        $staged = $this->stage_live_delete($item, $topic, $course, $can, $input, $adapter, $bindings['clock'], $bindings['token'], $bindings['read'], $bindings['update'], $bindings['affected']);
        $accepted = is_array($staged) && isset($staged['dispatch_state'], $staged['envelope']) && is_string($staged['envelope']) ? $this->accepted_bytes($staged['envelope']) : null;
        $confirmed = is_array($accepted) && 'confirmed' === $staged['dispatch_state'];
        $provider_id = $confirmed && isset($accepted['provider_id']) && is_string($accepted['provider_id']) && '' !== $accepted['provider_id'] ? $accepted['provider_id'] : null;
        return $this->rest_outcome('delete', $confirmed ? ['outcome' => 'confirmed', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => [], 'provenance' => 'delete'] : (is_array($accepted) ? $accepted : $staged));
    }

    /**
     * Direct-delete context. Later cascade handling calls this same argument order.
     *
     * @param array<string,mixed> $item     Canonical item.
     * @param array<string,mixed> $topic    Topic row.
     * @param array<string,mixed> $course   Course row.
     * @param callable            $can      Authorization callable.
     * @param array<string,mixed> $input    Server-owned actor context.
     * @param mixed               $adapter  Provider adapter.
     * @param DateTimeImmutable   $now      Injected clock.
     * @param mixed               $token    Injected token.
     * @param callable            $read     Sync read.
     * @param callable            $update   Sync update.
     * @param callable            $affected Affected count.
     * @return mixed
     */
    protected function stage_live_delete(array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected) {
        return TutorPress_Live_Lesson_Sync_Orchestrator::stage_delete($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $update, $affected);
    }

    /**
     * Gate-true direct Google duplicate. Invalid chains and Zoom stop first.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    private function delegate_duplicate($request) {
        $bindings = $this->request_bindings();
        $id = $this->scalar_id($request->get_param('id'));
        $item = $id > 0 ? $bindings['lookup']($id) : null;
        $missing = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]);
        if ($id < 1 || !is_array($item)) { return $missing; }
        $type = isset($item['post_type']) ? $item['post_type'] : '';
        if ('tutor-google-meet' !== $type && 'tutor_zoom_meeting' !== $type) { return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]); }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0));
        if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
        if (!$linked) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); };
        if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to duplicate this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) { return $missing; }
        if ('tutor-google-meet' !== $type) { return new WP_Error('duplication_not_supported', __('Duplication is only supported for Google Meet lessons.', 'tutorpress'), ['status' => 400]); }
        $raw = $request->get_param('topic_id');
        $target = $topic;
        $target_course = $course;
        if (null !== $raw && '' !== $raw) {
            $target_id = $this->scalar_id($raw);
            $target = $target_id > 0 ? $bindings['lookup']($target_id) : null;
            if (!is_array($target) || !isset($target['post_type'], $target['ID']) || 'topics' !== $target['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic ID or topic does not belong to the specified course.', 'tutorpress'), ['status' => 404]); }
            $target_course = $bindings['lookup']((int) (isset($target['post_parent']) ? $target['post_parent'] : 0));
        }
        $placed = is_array($target_course) && isset($target_course['post_type'], $target_course['ID'], $target['post_type'], $target['ID'], $target['post_parent']) && 'topics' === $target['post_type'] && 'courses' === $target_course['post_type'] && (int) $target['post_parent'] === (int) $target_course['ID'];
        if (!$placed) { return new WP_Error('invalid_topic', __('Invalid topic ID or topic does not belong to the specified course.', 'tutorpress'), ['status' => 404]); }
        if (null === TutorPress_Live_Lesson_Authorization::authorize_create($target, $target_course, $can)) { return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to duplicate this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        $children = $bindings['children']((int) $target['ID']);
        $metadata = $bindings['metadata']((int) $item['ID'], $type);
        $result = $this->duplicate_live($item, $topic, $course, $target, $target_course, is_array($children) ? $children : [], is_array($metadata) ? $metadata : [], $can);
        return $this->rest_outcome('duplicate', $result);
    }

    /**
     * One direct Google duplicate. No provider call or metadata write.
     *
     * @param array<string,mixed> $source        Source item.
     * @param array<string,mixed> $topic         Source topic.
     * @param array<string,mixed> $course        Source course.
     * @param array<string,mixed> $target        Destination topic.
     * @param array<string,mixed> $target_course Destination course.
     * @param array<int,mixed>    $children      Destination children.
     * @param array<string,mixed> $meta          Sanitized Google metadata.
     * @param callable            $can           edit_post callable.
     * @return mixed
     */
    protected function duplicate_live(array $source, array $topic, array $course, array $target, array $target_course, array $children, array $meta, $can) {
        return TutorPress_Live_Lesson_Duplicate_Service::duplicate($source, $topic, $course, $target, $target_course, $children, $meta, $can);
    }

    /** Version 0 and no operation for owner resolution. The other actions require both. */
    public function validate_reconcile_operation($value, $request) {
        $action = $request->get_param('action'); $version = $request->get_param('version');
        $version = is_int($version) || (is_string($version) && ctype_digit($version)) ? (int) $version : -1;
        $ok = 'resolve_owner' === $action ? 0 === $version && (null === $value || '' === $value) : in_array($action, ['inspect', 'retry', 'abandon_unknown'], true) && $version > 0 && is_string($value) && '' !== $value;
        return $ok ? true : new WP_Error('invalid_operation_id', __('Invalid reconciliation input.', 'tutorpress'), ['status' => 400]);
    }

    /** Gate-true reconciliation. Course edit_post finishes before envelope, adapter, or orchestrator work. */
    public function reconcile_item($request) {
        $params = $request->get_params(); foreach (['provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId'] as $identity) { if (array_key_exists($identity, $params)) { return new WP_Error('live_lesson_supplied_identity', __('Supplied provider identity is not accepted.', 'tutorpress'), ['status' => 400]); } }
        $checked = $this->validate_reconcile_operation($request->get_param('operation_id'), $request); if (is_wp_error($checked)) { return $checked; }
        $bindings = $this->request_bindings(); $id = is_array($bindings) ? $this->scalar_id($request->get_param('id')) : 0; $item = $id > 0 ? $bindings['lookup']($id) : null;
        $missing = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]); if ($id < 1 || !is_array($item)) { return $missing; }
        $type = isset($item['post_type']) ? $item['post_type'] : ''; if ('tutor-google-meet' !== $type && 'tutor_zoom_meeting' !== $type) { return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]); }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0)); if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID']; if (!$linked) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); }; if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) { return $missing; }
        $action = (string) $request->get_param('action'); $version = $request->get_param('version'); $actor = (int) $bindings['user_id'];
        $input = ['version' => is_int($version) || (is_string($version) && ctype_digit($version)) ? (int) $version : $version, 'acting_user_id' => $actor > 0 ? $actor : 0, 'course_author_id' => isset($course['post_author']) ? (int) $course['post_author'] : 0, 'acting_instructor_id' => $actor > 0 ? $actor : 0];
        if ('resolve_owner' === $action) { $input['evidence'] = $this->owner_evidence($item, $course, $actor); } else { $input['operation_id'] = $request->get_param('operation_id'); }
        if ('abandon_unknown' === $action) { return $this->reconcile_result($action, $this->run_reconcile($action, $item, $topic, $course, $can, $input, null, $bindings)); }
        $stored = $bindings['envelope']((int) $item['ID']); if (false === $stored) { return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]); }
        $provider = is_array($stored) && isset($stored['provider']) && is_string($stored['provider']) ? $stored['provider'] : ('tutor_zoom_meeting' === $type ? 'zoom' : 'google_meet');
        return $this->reconcile_result($action, $this->run_reconcile($action, $item, $topic, $course, $can, $input, $this->provider_adapter($provider), $bindings));
    }

    /** Server-owned item, course, and acting-user rows. Client identity is not copied. */
    private function owner_evidence(array $item, array $course, $actor) {
        $title = isset($course['post_title']) ? (string) $course['post_title'] : ''; $item_title = isset($item['post_title']) ? (string) $item['post_title'] : '';
        $author = isset($course['post_author']) ? (int) $course['post_author'] : 0; $posted = isset($item['post_author']) ? (int) $item['post_author'] : 0; $rows = [];
        foreach ([['acting_user', (int) $actor, $title], ['acting_instructor', (int) $actor, $title], ['course_author', $author, $title], ['post_author', $posted, $item_title]] as $row) { if ($row[1] > 0 && '' !== $row[2]) { $rows[] = ['source' => $row[0], 'id' => $row[1], 'label' => $row[2]]; } }
        return $rows;
    }

    /** Owner lists return directly. Bare errors, comparisons, and accepted envelopes stay on the outcome boundary. */
    private function reconcile_result($action, $result) {
        if (is_wp_error($result)) { return $this->rest_outcome($action, $result); }
        if (null === $result && in_array($action, ['retry', 'resolve_owner', 'abandon_unknown', 'attach'], true)) {
            return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]);
        }
        $owners = 'resolve_owner' === $action ? $this->accepted_owner($result) : null;
        if (is_array($owners)) { return rest_ensure_response($this->format_response($owners, __('Request successful.', 'tutorpress'))); }
        $accepted = is_array($result) && array_key_exists('envelope', $result) ? $this->accepted_bytes($result['envelope']) : false;
        return $this->rest_outcome($action, false === $accepted ? $result : (is_array($accepted) ? $accepted : null));
    }

    /** Exact owner id and candidate list. Any other array stays on the outcome path. */
    private function accepted_owner($result) {
        $keys = is_array($result) ? array_keys($result) : [];
        sort($keys, SORT_STRING);
        if (['candidates', 'owner_id'] !== $keys || !is_int($result['owner_id']) || $result['owner_id'] < 1 || !is_array($result['candidates'])) { return null; }
        $index = 0;
        foreach ($result['candidates'] as $key => $row) {
            $names = is_array($row) ? array_keys($row) : [];
            sort($names, SORT_STRING);
            if ($key !== $index || ['id', 'label'] !== $names || !is_int($row['id']) || $row['id'] < 1 || !is_string($row['label']) || '' === $row['label']) { return null; }
            $index++;
        }
        return in_array($result['owner_id'], array_column($result['candidates'], 'id'), true) ? $result : null;
    }

    /** One orchestrator method for the approved action. Abandon receives no adapter. */
    protected function run_reconcile($action, array $item, array $topic, array $course, $can, array $input, $adapter, array $bindings) {
        $now = $bindings['clock']; $token = $bindings['token']; $read = $bindings['read']; $option = $bindings['option']; $takeover = $bindings['takeover'];
        if ('inspect' === $action) { return TutorPress_Live_Lesson_Sync_Orchestrator::inspect($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $option, $takeover); }
        if ('retry' === $action) { return TutorPress_Live_Lesson_Sync_Orchestrator::retry($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $bindings['update'], $bindings['affected'], $bindings['write'], $option, $takeover); }
        if ('abandon_unknown' === $action) { return TutorPress_Live_Lesson_Sync_Orchestrator::abandon_unknown($item, $topic, $course, $can, $input, $now, $token, $read, $bindings['update'], $bindings['affected'], $option, $takeover); }
        return TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $option, $takeover);
    }

    /** Gate-true attachment. The path selects the kind. edit_post finishes before envelope, metadata, adapter, or orchestrator work. */
    public function attach_item($request) {
        $params = $request->get_params(); $route = method_exists($request, 'get_route') ? (string) $request->get_route() : (isset($request->route) ? (string) $request->route : '');
        $uncertain = false !== strpos($route, '/attach/uncertain-create'); $legacy = !$uncertain && false !== strpos($route, '/attach/legacy');
        $invalid = new WP_Error('invalid_operation_id', __('Invalid reconciliation input.', 'tutorpress'), ['status' => 400]);
        if ((!$uncertain && !$legacy) || array_key_exists('kind', $params)) { return $invalid; }
        $blocked = ['credentials', 'event_id', 'meeting_id', 'eventId', 'meetingId']; if ($uncertain) { $blocked[] = 'selected_owner_id'; }
        foreach ($blocked as $identity) { if (array_key_exists($identity, $params)) { return new WP_Error('live_lesson_supplied_identity', __('Supplied provider identity is not accepted.', 'tutorpress'), ['status' => 400]); } }
        $provider_id = array_key_exists('provider_id', $params) ? $params['provider_id'] : null; $version = array_key_exists('version', $params) && is_int($params['version']) ? $params['version'] : -1;
        $operation_present = array_key_exists('operation_id', $params); $operation = $operation_present ? $params['operation_id'] : null;
        $selected_present = array_key_exists('selected_owner_id', $params); $selected = $selected_present ? $params['selected_owner_id'] : null;
        $identity_ok = is_string($provider_id) && '' !== $provider_id;
        $shaped = $uncertain ? $identity_ok && $version >= 1 && $operation_present && is_string($operation) && '' !== $operation : $identity_ok && 0 === $version && !$operation_present && (!$selected_present || (is_int($selected) && $selected > 0));
        if (!$shaped) { return $invalid; }
        $bindings = $this->request_bindings(); $id = is_array($bindings) ? $this->scalar_id($request->get_param('id')) : 0; $item = $id > 0 ? $bindings['lookup']($id) : null;
        $missing = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]); if ($id < 1 || !is_array($item)) { return $missing; }
        $type = isset($item['post_type']) ? $item['post_type'] : ''; if ('tutor-google-meet' !== $type && 'tutor_zoom_meeting' !== $type) { return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]); }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0)); if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID']; if (!$linked) { return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]); }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); }; if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]); }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) { return $missing; }
        $stored = $bindings['envelope']((int) $item['ID']); if (false === $stored) { return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]); }
        $provider = is_array($stored) && isset($stored['provider']) && is_string($stored['provider']) ? $stored['provider'] : ('tutor_zoom_meeting' === $type ? 'zoom' : 'google_meet');
        $actor = (int) $bindings['user_id']; $input = ['version' => $version, 'acting_user_id' => $actor > 0 ? $actor : 0, 'course_author_id' => isset($course['post_author']) ? (int) $course['post_author'] : 0, 'acting_instructor_id' => $actor > 0 ? $actor : 0, 'provider_id' => $provider_id];
        if ($uncertain) { $input['operation_id'] = $operation; }
        if ($legacy) { $input['evidence'] = $this->owner_evidence($item, $course, $actor); $local = $bindings['metadata']((int) $item['ID'], $type); $input['local'] = is_array($local) ? $local : []; if (is_int($selected) && $selected > 0) { $input['selected_owner_id'] = $selected; } }
        return $this->reconcile_result('attach', $this->run_attach($uncertain ? 'uncertain_create' : 'legacy_identity', $item, $topic, $course, $can, $input, $this->provider_adapter($provider), $bindings));
    }

    /** Uncertain-create passes write, update, and affected. Legacy passes write and omits update and affected. */
    protected function run_attach($kind, array $item, array $topic, array $course, $can, array $input, $adapter, array $bindings) {
        $now = $bindings['clock']; $token = $bindings['token']; $read = $bindings['read']; $option = $bindings['option']; $takeover = $bindings['takeover'];
        if ('uncertain_create' === $kind) { return TutorPress_Live_Lesson_Sync_Orchestrator::attach_provider_identity($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $option, $takeover, $bindings['write'], $bindings['update'], $bindings['affected']); }
        return 'legacy_identity' === $kind ? TutorPress_Live_Lesson_Sync_Orchestrator::attach_legacy_identity($item, $topic, $course, $can, $input, $adapter, $now, $token, $read, $bindings['write'], $option, $takeover) : null;
    }

    /**
     * Accepted schedule. No provider or meta work.
     *
     * @param array<string,string> $normalized Normalized schedule.
     * @return array<string,string>
     */
    protected function schedule_result($normalized) {
        return $normalized;
    }

    /**
     * Select snake or camel start/end values and return snake_case keys.
     *
     * @param WP_REST_Request $request Request.
     * @return array<string,string>|WP_Error
     */
    public function normalize_mutation($request) {
        $params = $request->get_params();
        foreach (['provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId'] as $identity) {
            if (array_key_exists($identity, $params)) {
                return new WP_Error('live_lesson_supplied_identity', __('Supplied provider identity is not accepted.', 'tutorpress'), ['status' => 400]);
            }
        }
        $normalized = [];
        foreach ([['start_date_time', 'startDateTime'], ['end_date_time', 'endDateTime']] as $pair) {
            $snake = $request->get_param($pair[0]);
            $camel = $request->get_param($pair[1]);
            if (null !== $snake && null !== $camel && $snake !== $camel) {
                return new WP_Error('live_lesson_schedule_conflict', __('Schedule fields conflict.', 'tutorpress'), ['status' => 400]);
            }
            if (null === $snake && null !== $camel && !TutorPress_Live_Lesson_Datetime::is_wall_clock($camel)) {
                return new WP_Error('invalid_wall_clock', __('Invalid wall clock.', 'tutorpress'), ['status' => 400]);
            }
            if (null === $snake) {
                $snake = $camel;
            }
            if (null !== $snake) {
                $normalized[$pair[0]] = $snake;
            }
        }
        return $normalized;
    }

    /**
     * Map normalized schedule data to whitelisted domain input.
     *
     * The course row and acting user are explicit arguments. This method does not
     * look up posts, users, capabilities, storage, or providers.
     *
     * @param WP_REST_Request     $request         Request.
     * @param array<string,mixed> $normalized      Normalized schedule.
     * @param array<string,mixed> $course          Explicit course row.
     * @param int                 $acting_user_id  Explicit acting user.
     * @return array<string,mixed>
     */
    public function map_mutation($request, array $normalized, array $course, $acting_user_id) {
        $blocked = ['credentials', 'provider_id', 'event_id', 'meeting_id', 'eventId', 'meetingId'];
        $type = $request->get_param('type');
        $title = $request->get_param('title');
        $description = $request->get_param('description');
        $config = $request->get_param('provider_config');
        $settings = $request->get_param('settings');
        $zone = array_key_exists('timezone', $normalized) ? $normalized['timezone'] : $request->get_param('timezone');
        $typed = array_key_exists('duration', $normalized) ? $normalized['duration'] : $request->get_param('duration');
        $duration = null;
        if (is_array($typed) && array_key_exists('value', $typed) && array_key_exists('unit', $typed)) {
            $duration = TutorPress_Live_Lesson_Datetime::zoom_duration($typed['value'], $typed['unit']);
        }
        $kept = [];
        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if (!in_array($key, $blocked, true)) {
                    $kept[$key] = $value;
                }
            }
        }
        $actor = is_int($acting_user_id) || (is_string($acting_user_id) && ctype_digit($acting_user_id)) ? (int) $acting_user_id : 0;
        $author = isset($course['post_author']) ? $course['post_author'] : 0;
        $author = is_int($author) || (is_string($author) && ctype_digit($author)) ? (int) $author : 0;
        return [
            'provider'             => is_string($type) && ('google_meet' === $type || 'zoom' === $type) ? $type : '',
            'title'                => is_string($title) ? $title : '',
            'content'              => is_string($description) ? $description : '',
            'start'                => isset($normalized['start_date_time']) && is_string($normalized['start_date_time']) ? $normalized['start_date_time'] : '',
            'end'                  => isset($normalized['end_date_time']) && is_string($normalized['end_date_time']) ? $normalized['end_date_time'] : '',
            'timezone'             => is_string($zone) ? $zone : '',
            'duration'             => $duration,
            'host_id'              => is_array($config) && isset($config['host']) && is_string($config['host']) ? $config['host'] : '',
            'settings'             => $kept,
            'acting_user_id'       => $actor > 0 ? $actor : 0,
            'course_author_id'     => $author > 0 ? $author : 0,
            'acting_instructor_id' => $actor > 0 ? $actor : 0,
        ];
    }

    /**
     * Request bindings for gate-true routes. Null while the replacement gate is false.
     *
     * Lookup, children, and topics return canonical post arrays. Envelope returns
     * one accepted sync envelope, null when that row is absent, or false when the
     * stored bytes are malformed or ambiguous. Metadata returns whitelisted local
     * provider meta, or null when none remains, and does not resolve an adapter.
     * The capability closure checks only edit_post for the supplied course. Clock,
     * token, and user id come from overridable runtime loaders. Storage closures
     * read one option, compare-and-set one lock, read and compare-and-set sync meta,
     * report the affected count, and update or add one slashed provider value. Route handlers do
     * not call this seam or those closures.
     *
     * @return array{lookup:callable,children:callable,topics:callable,can:callable,clock:DateTimeImmutable,token:string,user_id:int,option:callable,takeover:callable,read:callable,envelope:callable,metadata:callable,update:callable,affected:callable,write:callable}|null
     */
    public function request_bindings() {
        if (!$this->replacement_enabled()) {
            return null;
        }
        return [
            'lookup'   => function ($post_id) {
                return $this->canonical_post($this->load_post((int) $post_id));
            },
            'children' => function ($topic_id) {
                $rows = [];
                foreach ($this->load_posts([
                    'post_parent'    => (int) $topic_id,
                    'post_type'      => ['lesson', 'tutor_quiz', 'tutor_assignments', 'tutor-google-meet', 'tutor_zoom_meeting'],
                    'post_status'    => ['publish', 'draft', 'private', 'pending', 'future'],
                    'posts_per_page' => -1,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                ]) as $post) {
                    $rows[] = $this->canonical_post($post);
                }
                return $rows;
            },
            'topics'   => function ($course_id) {
                $rows = [];
                foreach ($this->load_posts([
                    'post_parent'    => (int) $course_id,
                    'post_type'      => ['topics'],
                    'post_status'    => ['publish', 'draft', 'private', 'pending', 'future'],
                    'posts_per_page' => -1,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                ]) as $post) {
                    $rows[] = $this->canonical_post($post);
                }
                return $rows;
            },
            'can'      => function ($course_id) {
                return $this->load_current_user_can('edit_post', (int) $course_id);
            },
            'clock'    => $this->load_clock(),
            'token'    => $this->load_token(),
            'user_id'  => $this->load_user_id(),
            'option'   => function ($name) {
                return $this->load_option($name);
            },
            'takeover' => function ($name, $bytes, $prior) {
                return $this->load_option_update($name, $bytes, $prior);
            },
            'read'     => function ($post_id, $key) {
                return $this->load_meta_rows((int) $post_id, $key);
            },
            'envelope' => function ($post_id) {
                return $this->load_envelope((int) $post_id);
            },
            'metadata' => function ($post_id, $post_type) {
                return $this->metadata((int) $post_id, $post_type);
            },
            'update'   => function ($post_id, $key, $meta_id, $bytes, $prior) {
                return $this->load_meta_update((int) $post_id, $key, $meta_id, $bytes, $prior);
            },
            'affected' => function () {
                return $this->load_affected();
            },
            'write'    => function ($post_id, $key, $value) {
                return update_post_meta( $post_id, $key, wp_slash( $value ) );
            },
        ];
    }

    /** Replacement gate seam. False-gate callers never reach the loaders. */
    protected function replacement_enabled() { return true; }

    /** @param int $post_id Post ID. @return WP_Post|null */
    protected function load_post($post_id) { $post = get_post($post_id); return $post instanceof WP_Post ? $post : null; }

    /** @param array<string,mixed> $query Topic-child query. @return array<int,mixed> */
    protected function load_posts(array $query) { $posts = get_posts($query); return is_array($posts) ? $posts : []; }

    /** @param string $capability Capability. @param int $course_id Course ID. @return bool */
    protected function load_current_user_can($capability, $course_id) { return current_user_can($capability, $course_id); }

    /** @return DateTimeImmutable */
    protected function load_clock() { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }

    /** @return string */
    protected function load_token() { return bin2hex(random_bytes(16)); }

    /** @return int */
    protected function load_user_id() { return (int) get_current_user_id(); }

    /**
     * Canonical post array, or null when the value is not a post.
     *
     * @param mixed $post Post.
     * @return array<string,int|string>|null
     */
    private function canonical_post($post) {
        if (!($post instanceof WP_Post)) {
            return null;
        }
        $row = [];
        $ints = ['ID' => true, 'post_parent' => true, 'post_author' => true, 'menu_order' => true];
        foreach (['ID', 'post_type', 'post_status', 'post_parent', 'post_author', 'post_title', 'post_content', 'menu_order'] as $key) {
            $row[$key] = isset($ints[$key]) ? (int) $post->{$key} : (string) $post->{$key};
        }
        return $row;
    }

    /**
     * One accepted sync envelope, null when absent, or false when stored bytes are invalid.
     *
     * @param int $post_id Post ID.
     * @return array<string,mixed>|null|false
     */
    protected function load_envelope($post_id) {
        $rows = $this->load_meta_rows((int) $post_id, '_tutorpress_live_lesson_sync');
        if (!is_array($rows) || [] === $rows) {
            return null;
        }
        if (1 !== count($rows) || !isset($rows[0]['meta_value']) || !is_string($rows[0]['meta_value']) || '' === $rows[0]['meta_value']) {
            return false;
        }
        $decoded = json_decode($rows[0]['meta_value'], true);
        $stored = is_array($decoded) ? TutorPress_Live_Lesson_Sync_Envelope::envelope($decoded) : null;
        return is_array($stored) ? $stored : false;
    }

    /** @param int $post_id Post ID. @param mixed $post_type Post type. @return array<string,mixed>|null */
    protected function metadata($post_id, $post_type) { return $this->load_provider_meta($post_id, $post_type); }

    /**
     * Whitelisted Google or Zoom meta. Unknown post types do not read.
     *
     * @param int   $post_id   Post ID.
     * @param mixed $post_type Post type.
     * @return array<string,mixed>|null
     */
    protected function load_provider_meta($post_id, $post_type) {
        $keys = [
            'tutor-google-meet' => ['tutor-google-meet-start-datetime', 'tutor-google-meet-end-datetime', 'tutor-google-meet-event-details', 'tutor-google-meet-link'],
            'tutor_zoom_meeting' => ['_tutor_zm_start_date', '_tutor_zm_start_datetime', '_tutor_zm_duration', '_tutor_zm_duration_unit', '_tutor_zm_for_course', '_tutor_zm_for_topic', '_tutor_zm_data'],
        ];
        if (!is_string($post_type) || !isset($keys[$post_type])) {
            return null;
        }
        $json = ['tutor-google-meet-event-details' => true, '_tutor_zm_data' => true];
        $kept = [];
        foreach ($keys[$post_type] as $key) {
            $value = $this->load_post_meta((int) $post_id, $key);
            if (isset($json[$key])) {
                $value = is_array($value) ? $value : (is_string($value) && '' !== $value ? json_decode($value, true) : null);
                $value = is_array($value) ? $this->strip_sensitive_meta($value) : null;
                if (!is_array($value) || [] === $value) {
                    continue;
                }
            } elseif (false === $value || null === $value || '' === $value || !is_scalar($value)) {
                continue;
            }
            $kept[$key] = $value;
        }
        return [] === $kept ? null : $kept;
    }

    /** @param int $post_id Post ID. @param string $key Meta key. @return mixed */
    protected function load_post_meta($post_id, $key) { return get_post_meta($post_id, $key, true); }

    /** Drop credential and token keys from stored provider meta. @param mixed $value Meta value. @return mixed */
    protected function strip_sensitive_meta($value) {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            if (is_string($key) && 1 === preg_match('/token|secret|credential|password|api_key/i', $key)) {
                unset($value[$key]);
                continue;
            }
            $value[$key] = $this->strip_sensitive_meta($item);
        }
        return $value;
    }

    /** @param mixed $name Option name. @return string|null */
    protected function load_option($name) { $value = is_string($name) ? get_option($name) : null; return is_string($value) ? $value : null; }

    /** @param mixed $name Option name. @param mixed $bytes New bytes. @param mixed $prior Prior bytes. @return bool */
    protected function load_option_update($name, $bytes, $prior) { return TutorPress_Live_Lesson_Sync_Repository::compare_option_update($name, $bytes, $prior); }

    /** @param int $post_id Post ID. @param mixed $key Meta key. @return array<int,array<string,mixed>> */
    protected function load_meta_rows($post_id, $key) { return TutorPress_Live_Lesson_Sync_Repository::meta_rows($post_id, $key); }

    /** @param int $post_id Post ID. @param mixed $key Meta key. @param mixed $meta_id Meta ID. @param mixed $bytes New bytes. @param mixed $prior Prior bytes. @return bool */
    protected function load_meta_update($post_id, $key, $meta_id, $bytes, $prior) { return TutorPress_Live_Lesson_Sync_Repository::compare_meta_update($post_id, $key, $meta_id, $bytes, $prior); }

    /** @return int */
    protected function load_affected() { return TutorPress_Live_Lesson_Sync_Repository::last_affected(); }

    /**
     * Resolve one canonical provider adapter. Invalid providers do not touch the container.
     *
     * @param mixed $provider Provider slug.
     * @return TutorPress_Live_Lesson_Provider_Adapter|null
     */
    public function provider_adapter($provider) {
        $ids = [
            'google_meet' => 'TutorPress_Live_Lesson_Provider_Adapter:google_meet',
            'zoom'        => 'TutorPress_Live_Lesson_Provider_Adapter:zoom',
        ];
        if (!is_string($provider) || !isset($ids[$provider])) {
            return null;
        }
        $this->adapter_resolutions[] = $provider;
        $resolved = $this->load_service($ids[$provider]);
        return $resolved instanceof TutorPress_Live_Lesson_Provider_Adapter ? $resolved : null;
    }

    /** @param string $id Service ID. @return mixed */
    protected function load_service($id) { return TutorPress_Service_Container::instance()->get($id); }

    /**
     * Check permission for live lesson operations.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_lesson_permission($request) {
        // For now, use the base permission check
        // In future, this could include addon-specific permissions
        return $this->check_permission($request);
    }

    /**
     * Get live lessons.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request) {
        return $this->read_items($request);
    }

    /**
     * Authorized course collection. Called only after the replacement gate is true.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    private function read_items($request) {
        $bindings  = $this->request_bindings();
        $raw       = $request->get_param('course_id');
        $course_id = is_int($raw) ? $raw : (is_string($raw) && 1 === preg_match('/\A[0-9]+\z/', $raw) ? (int) $raw : 0);
        $course    = $course_id > 0 ? $bindings['lookup']($course_id) : null;
        if ($course_id < 1 || !is_array($course) || !isset($course['post_type']) || 'courses' !== $course['post_type']) {
            return new WP_Error('invalid_course_id', __('Invalid course ID.', 'tutorpress'), ['status' => 404]);
        }
        $can = static function ($capability, $id) use ($bindings) {
            return 'edit_post' === $capability && true === $bindings['can']((int) $id);
        };
        $denied = new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view live lessons for this course.', 'tutorpress'), ['status' => rest_authorization_required_code()]);
        if (null === TutorPress_Live_Lesson_Authorization::authorize_course($course, $can)) {
            return $denied;
        }
        $topics  = is_array($rows = $bindings['topics']($course_id)) ? $rows : [];
        $live    = ['tutor-google-meet' => true, 'tutor_zoom_meeting' => true];
        $lessons = [];
        foreach ($topics as $topic) {
            if (!is_array($topic) || !isset($topic['ID']) || (int) $topic['ID'] < 1) {
                continue;
            }
            foreach ($bindings['children']((int) $topic['ID']) as $child) {
                $type = is_array($child) ? (isset($child['post_type']) ? $child['post_type'] : '') : '';
                if (!isset($live[$type]) || 'publish' !== (isset($child['post_status']) ? $child['post_status'] : '') || !isset($child['ID'])) {
                    continue;
                }
                $stored              = $bindings['envelope']((int) $child['ID']);
                $child['sync_state'] = is_array($stored) && isset($stored['state']) ? $stored['state'] : 'error';
                $lessons[]           = $child;
            }
        }
        $args = [];
        foreach (['topic_id', 'type', 'per_page', 'page'] as $key) {
            $value = $request->get_param($key);
            if (null !== $value) {
                $args[$key] = $value;
            }
        }
        $queried = TutorPress_Live_Lesson_Collection_Query::query($course, $can, $topics, $lessons, $args);
        return is_array($queried) ? rest_ensure_response($queried) : $denied;
    }

    /**
     * Authorized item read. Called only after the replacement gate is true.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    private function read_item($request) {
        $bindings = $this->request_bindings();
        $raw      = $request->get_param('id');
        $id       = is_int($raw) ? $raw : (is_string($raw) && 1 === preg_match('/\A[0-9]+\z/', $raw) ? (int) $raw : 0);
        $missing  = new WP_Error('live_lesson_not_found', __('Live lesson not found.', 'tutorpress'), ['status' => 404]);
        $item     = $id > 0 ? $bindings['lookup']($id) : null;
        if ($id < 1 || null === $item) {
            return $missing;
        }
        if (!isset($item['post_type']) || ('tutor-google-meet' !== $item['post_type'] && 'tutor_zoom_meeting' !== $item['post_type'])) {
            return new WP_Error('invalid_post_type', __('Invalid post type. Must be a live lesson.', 'tutorpress'), ['status' => 400]);
        }
        $topic = $bindings['lookup']((int) (isset($item['post_parent']) ? $item['post_parent'] : 0));
        if (!is_array($topic) || !isset($topic['post_type']) || 'topics' !== $topic['post_type']) {
            return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]);
        }
        $course = $bindings['lookup']((int) (isset($topic['post_parent']) ? $topic['post_parent'] : 0));
        $linked = is_array($course) && isset($course['post_type'], $course['ID'], $topic['ID'], $item['post_parent'], $topic['post_parent']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
        if (!$linked) {
            return new WP_Error('invalid_topic', __('Invalid topic for this live lesson.', 'tutorpress'), ['status' => 404]);
        }
        $can = static function ($capability, $post_id) use ($bindings) {
            return 'edit_post' === $capability && true === $bindings['can']((int) $post_id);
        };
        $invalid = new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]);
        if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view this live lesson.', 'tutorpress'), ['status' => rest_authorization_required_code()]);
        }
        if ('publish' !== (isset($item['post_status']) ? $item['post_status'] : '')) {
            return $missing;
        }
        $envelope = $bindings['envelope']((int) $item['ID']);
        if (false === $envelope) {
            return $invalid;
        }
        $projected = TutorPress_Live_Lesson_Response_Projector::project_item($item, $topic, $course, $can, $envelope, $bindings['metadata']((int) $item['ID'], $item['post_type']));
        return is_array($projected) ? rest_ensure_response($this->format_response($projected, __('Live lesson retrieved successfully.', 'tutorpress'))) : $invalid;
    }

    /**
     * Map a dormant mutation result to a bare error or a REST success envelope.
     *
     * A positive duplicate id is the only post lookup. This helper does not
     * resolve a provider, write metadata, or run a lifecycle method.
     *
     * @param mixed $action Requested action.
     * @param mixed $result Operation result.
     * @return WP_Error|WP_REST_Response
     */
    public function rest_outcome($action, $result) {
        if (is_wp_error($result)) { return $result; }
        $action = is_string($action) ? $action : '';
        if (null === $result) { return $this->outcome_failure($action); }
        if ('duplicate' === $action && is_int($result) && $result > 0) { return $this->duplicate_projection($result); }
        if ('duplicate' === $action && !is_array($result)) { return new WP_Error('invalid_live_lesson', __('Invalid live lesson ID.', 'tutorpress'), ['status' => 404]); }
        $stored = is_array($result) ? TutorPress_Live_Lesson_Sync_Envelope::envelope($result) : null;
        if (is_array($stored)) { return rest_ensure_response($this->format_response($stored, 'create' === $action ? __('Live lesson created successfully.', 'tutorpress') : ('update' === $action ? __('Live lesson retrieved successfully.', 'tutorpress') : __('Request successful.', 'tutorpress')))); }
        $normalized = is_array($result) ? TutorPress_Live_Lesson_Provider_Result::from($result) : null;
        if (is_array($normalized) && 'confirmed' === $normalized['outcome'] && 'delete' === $normalized['provenance'] && null === $normalized['item']) { return rest_ensure_response($this->format_response($normalized, __('Live lesson deleted successfully.', 'tutorpress'))); }
        $compared = is_array($result) ? $this->outcome_comparison($result) : null;
        if (is_array($compared)) { return rest_ensure_response($this->format_response($compared, __('Request successful.', 'tutorpress'))); }
        return is_array($result) ? new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]) : $this->outcome_failure($action);
    }

    /** Bare failure for a null mutation result. No lookup or provider work. */
    private function outcome_failure($action) {
        if ('update' === $action) { return new WP_Error('live_lesson_update_failed', __('Failed to update live lesson.', 'tutorpress'), ['status' => 500]); }
        if ('delete' === $action) { return new WP_Error('live_lesson_delete_failed', __('Failed to delete live lesson.', 'tutorpress'), ['status' => 500]); }
        if ('duplicate' === $action) { return new WP_Error('duplicate_creation_failed', __('Failed to create duplicate live lesson.', 'tutorpress'), ['status' => 500]); }
        if ('inspect' === $action) { return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]); }
        return new WP_Error('live_lesson_creation_failed', __('Failed to create live lesson.', 'tutorpress'), ['status' => 500]);
    }

    /** Exact Google or Zoom comparison. Extra or unsafe keys are rejected. */
    private function outcome_comparison(array $result) {
        $keys = array_keys($result);
        sort($keys, SORT_STRING);
        $fields = isset($result['fields']) && is_array($result['fields']) ? $result['fields'] : [];
        $names = array_keys($fields);
        sort($names, SORT_STRING);
        $zoom = ['duration_minutes', 'provider_id', 'start_at', 'timezone'] === $names && is_string($fields['provider_id']) && is_string($fields['start_at']) && is_string($fields['timezone']) && is_int($fields['duration_minutes']);
        $meet = ['end_at', 'provider_id', 'start_at', 'timezone'] === $names && is_string($fields['provider_id']) && is_string($fields['start_at']) && is_string($fields['end_at']) && is_string($fields['timezone']);
        return ['fields', 'match'] === $keys && is_bool($result['match']) && ($zoom || $meet) ? ['match' => $result['match'], 'fields' => $fields] : null;
    }

    /** Load one duplicated Google Meet post through the gated request boundary. */
    private function duplicate_projection($id) {
        $bindings = $this->request_bindings();
        $failed = new WP_Error('duplicate_data_error', __('Duplicate created but failed to retrieve data.', 'tutorpress'), ['status' => 500]);
        $item = is_array($bindings) ? $bindings['lookup']((int) $id) : null;
        $parent = is_array($item) && isset($item['post_type'], $item['post_parent']) && 'tutor-google-meet' === $item['post_type'] ? (int) $item['post_parent'] : 0;
        $topic = $parent > 0 ? $bindings['lookup']($parent) : null;
        $course_id = is_array($topic) && isset($topic['post_type'], $topic['ID'], $topic['post_parent']) && 'topics' === $topic['post_type'] ? (int) $topic['post_parent'] : 0;
        $course = $course_id > 0 ? $bindings['lookup']($course_id) : null;
        $linked = is_array($course) && isset($course['post_type'], $course['ID']) && 'courses' === $course['post_type'] && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
        if (!$linked) { return $failed; }
        $can = static function ($capability, $post_id) use ($bindings) { return 'edit_post' === $capability && true === $bindings['can']((int) $post_id); };
        if (null === TutorPress_Live_Lesson_Authorization::authorize($item, $topic, $course, $can)) { return new WP_Error('cannot_duplicate_live_lesson', __('You do not have permission to duplicate live lessons.', 'tutorpress'), ['status' => 403]); }
        $envelope = $bindings['envelope']((int) $item['ID']);
        if (false === $envelope) { return new WP_Error('live_lesson_sync_invalid', __('Live lesson sync envelope is invalid.', 'tutorpress'), ['status' => 409]); }
        $projected = TutorPress_Live_Lesson_Response_Projector::project_item($item, $topic, $course, $can, $envelope, null);
        return is_array($projected) ? rest_ensure_response($this->format_response($projected, __('Google Meet lesson duplicated successfully.', 'tutorpress'))) : $failed;
    }

    /**
     * Get Zoom users for Meeting Host dropdown.
     * 
     * Integrates with Tutor LMS Zoom addon to fetch available Zoom users
     * using the same API credentials and methods that Tutor LMS uses.
     *
     * @since 1.5.6
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_zoom_users($request) {
        return $this->read_zoom_users($request);
    }

    /**
     * Authorized Zoom host list. Called only after the replacement gate is true.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    private function read_zoom_users($request) {
        $bindings  = $this->request_bindings();
        $raw       = $request->get_param('course_id');
        $course_id = is_int($raw) ? $raw : (is_string($raw) && 1 === preg_match('/\A[0-9]+\z/', $raw) ? (int) $raw : 0);
        $course    = $course_id > 0 ? $bindings['lookup']($course_id) : null;
        if ($course_id < 1 || !is_array($course) || !isset($course['post_type']) || 'courses' !== $course['post_type']) {
            return new WP_Error('invalid_course_id', __('Invalid course ID.', 'tutorpress'), ['status' => 404]);
        }
        $can = static function ($capability, $id) use ($bindings) {
            return 'edit_post' === $capability && true === $bindings['can']((int) $id);
        };
        $authorized = TutorPress_Live_Lesson_Authorization::authorize_course($course, $can);
        if (null === $authorized) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view live lessons for this course.', 'tutorpress'), ['status' => rest_authorization_required_code()]);
        }
        $owner_id = TutorPress_Live_Lesson_Owner_Resolver::zoom($authorized, isset($course['post_author']) ? $course['post_author'] : 0, $bindings['user_id']);
        if (null === $owner_id) {
            return new WP_Error('zoom_owner_unavailable', __('A Zoom owner could not be resolved for this course.', 'tutorpress'), ['status' => 400]);
        }
        $adapter = $this->provider_adapter('zoom');
        return rest_ensure_response($this->format_response($adapter->list_hosts($owner_id), __('Zoom users retrieved successfully.', 'tutorpress')));
    }

    /**
     * Get Google Meet settings and authorization status.
     * 
     * Checks if user has configured Google Meet and returns relevant settings
     * for the frontend Live Lessons form.
     *
     * @since 1.5.6
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_google_meet_settings($request) {
        return $this->read_google_settings($request);
    }

    /**
     * Authorized Google Meet settings. Called only after the replacement gate is true.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    private function read_google_settings($request) {
        $bindings  = $this->request_bindings();
        $raw       = $request->get_param('course_id');
        $course_id = is_int($raw) ? $raw : (is_string($raw) && 1 === preg_match('/\A[0-9]+\z/', $raw) ? (int) $raw : 0);
        $course    = $course_id > 0 ? $bindings['lookup']($course_id) : null;
        if ($course_id < 1 || !is_array($course) || !isset($course['post_type']) || 'courses' !== $course['post_type']) {
            return new WP_Error('invalid_course_id', __('Invalid course ID.', 'tutorpress'), ['status' => 404]);
        }
        $can = static function ($capability, $id) use ($bindings) {
            return 'edit_post' === $capability && true === $bindings['can']((int) $id);
        };
        $authorized = TutorPress_Live_Lesson_Authorization::authorize_course($course, $can);
        if (null === $authorized) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view live lessons for this course.', 'tutorpress'), ['status' => rest_authorization_required_code()]);
        }
        $owner_id = TutorPress_Live_Lesson_Owner_Resolver::google($authorized, $bindings['user_id']);
        if (null === $owner_id) {
            return new WP_Error('google_owner_unavailable', __('A Google owner could not be resolved for this course.', 'tutorpress'), ['status' => 400]);
        }
        $adapter  = $this->provider_adapter('google_meet');
        $settings = $adapter->settings($owner_id);
        if (null === $settings) {
            return new WP_Error('google_meet_not_authorized', __('Google Meet is not authorized. Please configure Google Meet API credentials in Tutor LMS.', 'tutorpress'), ['status' => 400]);
        }
        return rest_ensure_response($this->format_response($settings, __('Google Meet settings retrieved successfully.', 'tutorpress')));
    }

    /**
     * Get a single live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request) {
        return $this->read_item($request);
    }

    /**
     * Delete a live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_item($request) {
        return $this->delegate_delete($request);
    }

    /**
     * Duplicate a live lesson.
     *
     * Currently only supports Google Meet lessons as Zoom lessons don't have 
     * duplicate functionality in Tutor LMS frontend course builder.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_item($request) {
        return $this->delegate_duplicate($request);
    }
} 