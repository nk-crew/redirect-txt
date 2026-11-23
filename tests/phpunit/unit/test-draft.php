<?php
/**
 * Tests for inspecting unsaved redirect rules.
 *
 * @package redirect-txt
 */

/**
 * Draft inspection through the admin REST endpoint.
 */
class DraftTest extends WP_UnitTestCase {
	/**
	 * Send a draft to the registered endpoint.
	 *
	 * @param mixed       $rules   Draft rules.
	 * @param string|null $address URL or path to test.
	 * @return WP_REST_Response
	 */
	private function inspect( $rules, $address = null ) {
		$request = new WP_REST_Request( 'POST', '/redirect-txt/v1/inspect_draft' );
		$request->set_param( 'rules', $rules );
		$request->set_param( 'address', $address );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Users without access to settings cannot check a draft.
	 */
	public function test_draft_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 401, $this->inspect( '/old/: /new/' )->get_status() );
	}

	/**
	 * Reject invalid input and bound inspection independently of saving.
	 */
	public function test_invalid_and_oversized_drafts_are_rejected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 400, $this->inspect( array() )->get_status() );
		$this->assertSame( 413, $this->inspect( str_repeat( 'a', 256 * 1024 + 1 ) )->get_status() );
		$this->assertSame( 413, $this->inspect( '', str_repeat( 'a', 8193 ) )->get_status() );

		$result = $this->inspect( str_repeat( "/old/: /old/\n", 513 ), '/old/' );
		$this->assertSame( 'too_many_rules', $result->get_data()['response']['test']['outcome'] );
	}

	/**
	 * Suggestions preserve comments and spacing and strip the exact install prefix.
	 */
	public function test_draft_diagnostics_propose_only_supported_corrections() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'home', 'https://example.test/blog' );

		$draft = "# Example\r\n  /old/:/new/   # keep this\r\nhttps://example.test/blog/account/: / # homepage\r\nhttps://example.test:8443/blog/account/: /\r\nhttps://example.test/blogs/account/: /\r\nhttps://elsewhere.test/blog/account/: /\r\n^/(broken: /x\r\n302:\r\n/good/: /destination/";

		$response = $this->inspect( $draft );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array(
					'line'        => 1,
					'code'        => 'missing_separator',
					'replacement' => '  /old/: /new/   # keep this',
				),
				array(
					'line'        => 2,
					'code'        => 'absolute_source',
					'replacement' => '/account/: / # homepage',
				),
				array(
					'line' => 3,
					'code' => 'outside_site_source',
				),
				array(
					'line' => 4,
					'code' => 'outside_site_source',
				),
				array(
					'line' => 5,
					'code' => 'outside_site_source',
				),
				array(
					'line' => 6,
					'code' => 'invalid_regex',
				),
			),
			$response->get_data()['response']['diagnostics']
		);
	}

	/**
	 * A draft test uses the first matching rule and leaves settings and logs alone.
	 */
	public function test_first_draft_match_does_not_send_or_save_a_redirect() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'redirect_txt_rules', '/account/: /saved/', false );

		$hits = did_action( 'redirect_txt_redirect_hit' );
		$stop = function () {
			$this->fail( 'Draft testing must not send a redirect.' );
		};
		add_filter( 'wp_redirect', $stop );

		try {
			$response = $this->inspect( "# A draft\n^/(broken: /x\n/account/: /\n302:\n/account/: /later/", '/account/' );
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		$this->assertSame(
			array(
				'outcome'  => 'matched',
				'line'     => 2,
				'status'   => 301,
				'location' => '/',
				'rule'     => '/account/: /',
			),
			$response->get_data()['response']['test']
		);
		$this->assertArrayNotHasKey( 'Location', $response->get_headers() );
		$this->assertSame( $hits, did_action( 'redirect_txt_redirect_hit' ) );
		$this->assertSame( '/account/: /saved/', get_option( 'redirect_txt_rules' ) );
	}

	/**
	 * Later rules cannot turn an earlier self-redirect into a chain.
	 */
	public function test_draft_line_positions_do_not_change_match_priority() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = $this->inspect( "# A draft\n\n/shop: /shop?currency=usd\n/shop?currency=usd: /wrong\n/shop: /store", '/shop' );

		$this->assertSame( '/store', $result->get_data()['response']['test']['location'] );
		$this->assertSame( 4, $result->get_data()['response']['test']['line'] );
	}

	/**
	 * Draft URLs stay within the installation and report protected and post-only paths.
	 */
	public function test_draft_respects_request_boundaries() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'home', 'https://example.test/blog' );

		$draft = "/wp-admin/: /away/\n/: /away/\n24: /new/\n/account/: /";

		$cases = array(
			'/wp-admin/'                         => 'protected',
			'https://example.test/blog/wp-json/' => 'protected',
			'/?rest_route=/wp/v2/posts'          => 'protected',
			'/post-slug/'                        => 'post_resolution',
			'https://example.test/elsewhere/'    => 'invalid_address',
			'https://elsewhere.test/blog/'       => 'invalid_address',
			'https://example.test/blog/account/' => 'matched',
			'/account/'                          => 'matched',
		);

		foreach ( $cases as $address => $outcome ) {
			$response = $this->inspect( $draft, $address );
			$this->assertSame( $outcome, $response->get_data()['response']['test']['outcome'], $address );
		}
	}

	/**
	 * Reporting a match for an error status should not show a destination.
	 */
	public function test_draft_4xx_has_no_location() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->inspect( "410:\n/gone/: /", '/gone/' );
		$result   = $response->get_data()['response']['test'];
		$this->assertSame( 410, $result['status'] );
		$this->assertNull( $result['location'] );
	}
}
