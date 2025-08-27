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
     * Private constructor (singleton pattern).
     */
    private function __construct() {
        // Empty - services registered externally
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
