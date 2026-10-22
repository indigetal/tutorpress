<?php
/**
 * Shared adapter fake. Requires the production interface from the test loader.
 * The ledger stores method names and owner, provider-ID, and target operands only.
 */

class TutorPress_Live_Lessons_Adapter_Fake implements TutorPress_Live_Lesson_Provider_Adapter {
	public static $calls = array();
	public static $queue = array();

	public function capabilities() { return $this->take( 'capabilities', array() ); }
	public function preflight( $owner_id ): bool { return $this->take( 'preflight', array( 'owner_id' => $owner_id ) ); }
	public function settings( $owner_id ) { return $this->take( 'settings', array( 'owner_id' => $owner_id ) ); }
	public function list_hosts( $owner_id ) { return $this->take( 'list_hosts', array( 'owner_id' => $owner_id ) ); }
	public function validate_host( $owner_id, $host_id ): bool { return $this->take( 'validate_host', array( 'owner_id' => $owner_id, 'host_id' => $host_id ) ); }
	public function create( $owner_id, array $target ) { return $this->take( 'create', array( 'owner_id' => $owner_id, 'target' => $this->target( $target ) ) ); }
	public function fetch( $owner_id, $provider_id ) { return $this->take( 'fetch', array( 'owner_id' => $owner_id, 'provider_id' => $provider_id ) ); }
	public function update( $owner_id, $provider_id, array $target ) { return $this->take( 'update', array( 'owner_id' => $owner_id, 'provider_id' => $provider_id, 'target' => $this->target( $target ) ) ); }
	public function delete( $owner_id, $provider_id ) { return $this->take( 'delete', array( 'owner_id' => $owner_id, 'provider_id' => $provider_id ) ); }
	public function compare_target( array $result, array $expected ) { return $this->take( 'compare_target', array( 'target' => $this->target( $expected ) ) ); }
	public function metadata_map( array $result, array $target ) { return $this->take( 'metadata_map', array( 'target' => $this->target( $target ) ) ); }
	public function hydrate_legacy( array $local ) { return $this->take( 'hydrate_legacy', array( 'target' => $this->target( $local ) ) ); }
	public function hydrate_item( array $local ) { return $this->take( 'hydrate_item', array( 'target' => $this->target( $local ) ) ); }

	private function take( $method, array $operands ) {
		self::$calls[] = array( $method, $operands );
		if ( ! isset( self::$queue[ $method ] ) || array() === self::$queue[ $method ] ) {
			tutorpress_live_lessons_fail( 'unstubbed adapter ' . $method );
		}
		return array_shift( self::$queue[ $method ] );
	}

	/** Drop credential, token, header, cookie, request, response, and exception values. */
	private function target( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$safe = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && 1 === preg_match( '/request|response|exception|credential|token|header|cookie|secret|password/i', $key ) ) { continue; }
			$safe[ $key ] = is_array( $item ) ? $this->target( $item ) : $item;
		}
		return $safe;
	}
}
