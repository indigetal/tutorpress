<?php
/**
 * Provider-neutral live-lesson adapter.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owner-scoped operations. A known provider ID is a separate server-owned operand. */
interface TutorPress_Live_Lesson_Provider_Adapter {
	/** Exact booleans for settings, hosts, and delete_convergence. */
	public function capabilities();
	/** Boolean credential readiness for the owner. Returns no secret values. */
	public function preflight( $owner_id ): bool;
	/** Sanitized settings for the owner, or null. Returns no credential or token values. */
	public function settings( $owner_id );
	/** Host-list outcome for the owner. Returns no credential or token values. */
	public function list_hosts( $owner_id );
	/** Boolean host membership for that owner account. */
	public function validate_host( $owner_id, $host_id ): bool;
	/** Create from a durable target. The provider ID is a result, not an operand. */
	public function create( $owner_id, array $target );
	/** Fetch one server-owned provider ID. */
	public function fetch( $owner_id, $provider_id );
	/** Update one server-owned provider ID from a durable target. */
	public function update( $owner_id, $provider_id, array $target );
	/** Delete one server-owned provider ID. */
	public function delete( $owner_id, $provider_id );
	/** Compare a normalized result with canonical expected data. */
	public function compare_target( array $result, array $expected );
	/** Pure Tutor Pro-compatible meta map, or null. Performs no write. */
	public function metadata_map( array $result, array $target );
	/** Hydrate a legacy identity from compatible local data. */
	public function hydrate_legacy( array $local );
	/** Hydrate an item from compatible local data. */
	public function hydrate_item( array $local );
}
