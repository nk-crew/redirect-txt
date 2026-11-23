<?php
/**
 * Tests for inspecting unsaved redirect rules.
 *
 * @package redirect-txt
 */

/**
 * Rule inspection through the admin REST endpoint.
 */
class RuleInspectionTest extends WP_UnitTestCase {
	/**
	 * Send rule text to the registered endpoint.
	 *
	 * @param mixed $rules Rule text.
	 * @return WP_REST_Response
	 */
	private function inspect( $rules ) {
		$request = new WP_REST_Request( 'POST', '/redirect-txt/v1/inspect_rules' );
		$request->set_param( 'rules', $rules );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Users without access to settings cannot inspect the rules.
	 */
	public function test_inspection_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 401, $this->inspect( '/old/: /new/' )->get_status() );
	}

	/**
	 * Reject invalid or oversized text.
	 */
	public function test_invalid_inspection_text_is_rejected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 400, $this->inspect( array() )->get_status() );
		$this->assertSame( 400, $this->inspect( "/old/: /new\0" )->get_status() );
		$this->assertSame( 413, $this->inspect( str_repeat( 'a', 256 * 1024 + 1 ) )->get_status() );
	}

	/**
	 * Warnings refer to the draft's original positions without changing saved rules.
	 */
	public function test_only_affected_rows_are_reported() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'redirect_txt_rules', '/saved/: /destination/' );

		$draft    = "# Existing draft\r\n/old/:/new/ # Add a space\nhttps://example.test/legacy/: /\r^/(broken: /new/\n309:\n302:\n42: 43\n/keep/: /destination/#details\n/out/: https://example.test/";
		$response = $this->inspect( $draft );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array( 'line' => 1, 'code' => 'invalid_rule' ),
				array( 'line' => 2, 'code' => 'absolute_source' ),
				array( 'line' => 3, 'code' => 'invalid_regex' ),
				array( 'line' => 4, 'code' => 'unsupported_status' ),
			),
			$response->get_data()['response']
		);
		$this->assertSame( '/saved/: /destination/', get_option( 'redirect_txt_rules' ) );
		$this->assertSame( array(), $this->inspect( '/account/: /' )->get_data()['response'] );
	}
}
