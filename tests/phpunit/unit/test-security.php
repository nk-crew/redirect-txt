<?php
/**
 * Tests for request handling, rule saving and logs.
 *
 * @package redirect-txt
 */

/**
 * Security test case.
 */
class SecurityTest extends WP_UnitTestCase {
	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * REQUEST_URI before the test.
	 *
	 * @var string|null
	 */
	private $request_uri;

	/**
	 * Create the admin once.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Start every test without a whitelisted host.
	 */
	public function set_up() {
		parent::set_up();
		Redirect_Txt_Redirects::$whitelist_host = '';
		$this->request_uri                      = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
	}

	/**
	 * Drop the whitelist filter a redirect may have added.
	 */
	public function tear_down() {
		remove_filter( 'allowed_redirect_hosts', 'Redirect_Txt_Redirects::filter_allowed_redirect_hosts' );
		remove_all_filters( 'home_url' );
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tear_down();
	}

	/**
	 * Run the real request handler and return the Location it sends, or null.
	 *
	 * @param string $request_uri REQUEST_URI.
	 * @param string $rules       Saved rules.
	 * @return string|null
	 */
	private function location_for( $request_uri, $rules ) {
		update_option( 'redirect_txt_rules', $rules, false );
		$_SERVER['REQUEST_URI'] = $request_uri;

		$stop = static function ( $location ) {
			throw new RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $stop );

		try {
			Redirect_Txt_Redirects::maybe_process_redirect();
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		return null;
	}

	/**
	 * A host built from the request is not whitelisted.
	 */
	public function test_regex_host_from_request_is_not_whitelisted() {
		$rules    = '^/u/([^/]+)$: https://$1.example.com/';
		$location = $this->location_for( '/u/evil.org?x=', $rules );

		$this->assertNotNull( $location );
		$this->assertSame( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( $location, PHP_URL_HOST ) );
		$this->assertSame( '', (string) Redirect_Txt_Redirects::$whitelist_host );
	}

	/**
	 * A reference that fills in one subdomain label keeps the rule's domain.
	 */
	public function test_regex_subdomain_label_is_whitelisted() {
		$rules = '^/u/([^/]+)$: https://$1.example.com/';

		$this->assertSame( 'blog.example.com', wp_parse_url( $this->location_for( '/u/blog', $rules ), PHP_URL_HOST ) );
		$this->assertSame( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( $this->location_for( '/u/a.evil.org', $rules ), PHP_URL_HOST ) );
	}

	/**
	 * A host written in the rule is still whitelisted.
	 */
	public function test_static_target_host_is_whitelisted() {
		$location = $this->location_for( '/docs/intro', '^/docs/(.*)$: https://docs.other.org/$1' );

		$this->assertSame( 'https://docs.other.org/intro', $location );
	}

	/**
	 * Saving keeps %-encodings and `<` that regex rules need.
	 */
	public function test_rules_are_saved_as_written() {
		wp_set_current_user( self::$admin_id );
		$rules = "^/(?<slug>[a-z]+)/old$: /new/\$1\n^/a(?<=a)b: /x\n/caf%C3%A9: /cafe\n/a: /b?x=%20y";

		$response = $this->save_rules( $rules );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $rules, get_option( 'redirect_txt_rules' ) );
	}

	/**
	 * A regex that does not compile is refused, with its line, and nothing is saved.
	 */
	public function test_invalid_regex_is_refused() {
		wp_set_current_user( self::$admin_id );
		update_option( 'redirect_txt_rules', '/kept: /rules', false );

		$response = $this->save_rules( "/ok: /fine\n^/(broken: /x" );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'Line 2', $data['response'] );
		$this->assertStringContainsString( '^/(broken: /x', $data['response'] );
		$this->assertSame( '/kept: /rules', get_option( 'redirect_txt_rules' ) );
	}

	/**
	 * POST rules to the REST endpoint.
	 *
	 * @param string $rules Rules.
	 * @return WP_REST_Response
	 */
	private function save_rules( $rules ) {
		$request = new WP_REST_Request( 'POST', '/redirect-txt/v1/update_rules' );
		$request->set_param( 'rules', $rules );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * A %-encoded request matches the rule for the same path.
	 */
	public function test_encoded_request_matches() {
		$this->assertStringEndsWith( '/cafe', (string) $this->location_for( '/caf%C3%A9', '/caf%C3%A9: /cafe' ) );
		$this->assertStringEndsWith( '/cafe', (string) $this->location_for( '/caf%C3%A9', '/café: /cafe' ) );
		$this->assertNull( $this->location_for( '/caf', '/caf%C3%A9: /cafe' ) );
	}

	/**
	 * REST requests on plain permalinks are not redirected.
	 */
	public function test_rest_route_query_is_protected() {
		$this->assertNull( $this->location_for( '/?rest_route=/wp/v2/posts', '/: /welcome' ) );
		$this->assertNotNull( $this->location_for( '/?other=1', '/: /welcome' ) );
	}

	/**
	 * Protected paths count from the install, and follow the REST prefix.
	 */
	public function test_protected_paths_in_a_subdirectory() {
		add_filter(
			'home_url',
			static function ( $url ) {
				return false === strpos( $url, '/subdir' ) ? rtrim( $url, '/' ) . '/subdir' : $url;
			}
		);
		$this->assertTrue( Redirect_Txt_Redirects::is_protected_path( '/subdir/wp-json/wp/v2/posts' ) );
		$this->assertTrue( Redirect_Txt_Redirects::is_protected_path( '/subdir/wp-admin' ) );
		$this->assertFalse( Redirect_Txt_Redirects::is_protected_path( '/subdir/blog' ) );
		remove_all_filters( 'home_url' );

		$prefix = static function () {
			return 'api';
		};
		add_filter( 'rest_url_prefix', $prefix );
		$this->assertTrue( Redirect_Txt_Redirects::is_protected_path( '/api/wp/v2/posts' ) );
		remove_filter( 'rest_url_prefix', $prefix );
	}

	/**
	 * A numeric `from` is a post ID. An author or a term with that ID is not that post.
	 */
	public function test_numeric_from_only_matches_posts() {
		global $wp_query;

		$user_id = self::factory()->user->create();
		$term_id = self::factory()->term->create();

		$wp_query->queried_object = get_userdata( $user_id );
		$this->assertFalse( Redirect_Txt_Redirects::match_url_to_rules( '/author/x', $user_id . ': /moved', false, true ) );

		$wp_query->queried_object = get_term( $term_id );
		$this->assertFalse( Redirect_Txt_Redirects::match_url_to_rules( '/tag/x', $term_id . ': /moved', false, true ) );

		$post_id                  = self::factory()->post->create();
		$wp_query->queried_object = get_post( $post_id );
		$match                    = Redirect_Txt_Redirects::match_url_to_rules( '/p', $post_id . ': /moved', false, true );
		$this->assertStringEndsWith( '/moved', $match['to'] );
	}

	/**
	 * Each log keeps its newest 1000 entries.
	 */
	public function test_logs_are_capped_per_kind() {
		$logs = array(
			array(
				'status'    => 301,
				'url_from'  => '/redirect',
				'timestamp' => 1,
			),
		);
		for ( $i = 0; $i < 1000; $i++ ) {
			$logs[] = array(
				'status'    => 404,
				'url_from'  => '/old-' . $i,
				'timestamp' => 1,
			);
		}
		Redirect_Txt_Logs::update_logs( $logs );

		Redirect_Txt_Logs::store_log(
			array(
				'status'   => 404,
				'url_from' => '/newest',
			)
		);

		$stored    = Redirect_Txt_Logs::get_logs();
		$not_found = wp_list_filter( $stored, array( 'status' => 404 ) );

		$this->assertCount( 1001, $stored );
		$this->assertCount( 1000, $not_found );
		$this->assertSame( '/newest', $stored[0]['url_from'] );
		$this->assertSame( '/redirect', $stored[1]['url_from'] );
		$this->assertSame( '/old-998', $stored[1000]['url_from'] );
	}

	/**
	 * Rules saved before rules were kept as written lost their %-octets. They still fire.
	 */
	public function test_rules_stored_by_old_sanitizer_still_match() {
		$typed_rules = array(
			'/caf%C3%A9: /cafe',
			'/%D0%BE-%D0%BD%D0%B0%D1%81: /about',
			'/my%20page: /new',
			'/p?a=1%262: /x',
		);

		foreach ( $typed_rules as $typed ) {
			list( $request, $target ) = explode( ': ', $typed );

			$this->assertStringEndsWith( $target, (string) $this->location_for( $request, sanitize_textarea_field( $typed ) ), $typed );
		}

		// The reduced request is tried for plain rules only.
		$this->assertNull( $this->location_for( '/caf%C3%A9', '^/caf$: /x' ) );
	}

	/**
	 * The 404 log records the request the way the matcher reads it, so a rule copied from it fires.
	 */
	public function test_404_log_records_request_as_matched() {
		global $wp_query;

		update_option( 'redirect_txt_settings', array( '404_logs' => 1 ) );
		Redirect_Txt_Logs::update_logs( array() );
		$request                = '/%D0%BA%D0%B0%D1%84%D0%B5';
		$_SERVER['REQUEST_URI'] = $request;
		$wp_query->set_404();

		Redirect_Txt_Logs::maybe_log_404();

		$logs = Redirect_Txt_Logs::get_logs();
		$this->assertSame( $request, $logs[0]['url_from'] );
		$this->assertStringEndsWith( '/cafe', (string) $this->location_for( $request, $logs[0]['url_from'] . ': /cafe' ) );
	}

	/**
	 * A host built from the request keeps the case the request gave it.
	 */
	public function test_regex_host_case_is_kept() {
		$this->assertSame( 'https://Blog.example.com/', $this->location_for( '/u/Blog', '^/u/([^/]+)$: https://$1.example.com/' ) );
		$this->assertSame( 'https://Blog.Example.com/', $this->location_for( '/u/Blog', '^/u/([^/]+)$: https://$1.Example.com/' ) );
	}

	/**
	 * A reference that picks the registrable domain, or the whole host, is not whitelisted.
	 */
	public function test_regex_host_without_fixed_domain_is_not_whitelisted() {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		$this->assertSame( $home_host, wp_parse_url( (string) $this->location_for( '/go/evil', '^/go/([a-z]+)$: https://www.$1.com/' ), PHP_URL_HOST ) );
		$this->assertSame( $home_host, wp_parse_url( (string) $this->location_for( '/out/134744072', '^/out/([^/]+)$: https://$1/' ), PHP_URL_HOST ) );
	}

	/**
	 * A protected path added as a full path under a subdirectory home still protects.
	 */
	public function test_protected_full_path_from_filter() {
		add_filter(
			'home_url',
			static function ( $url ) {
				return false === strpos( $url, '/blog' ) ? rtrim( $url, '/' ) . '/blog' : $url;
			}
		);
		$add_members = static function ( $paths ) {
			$paths[] = '/blog/members/';

			return $paths;
		};
		add_filter( 'redirect_txt_protected_paths', $add_members );

		$this->assertTrue( Redirect_Txt_Redirects::is_protected_path( '/blog/members/x' ) );
		$this->assertTrue( Redirect_Txt_Redirects::is_protected_path( '/blog/wp-admin' ) );
		$this->assertFalse( Redirect_Txt_Redirects::is_protected_path( '/blog/other' ) );

		remove_filter( 'redirect_txt_protected_paths', $add_members );
	}

	/**
	 * A plain rule with `+` matches the same request, as `from` is url-decoded too.
	 */
	public function test_plus_in_plain_rule_matches() {
		$this->assertStringEndsWith( '/x', (string) $this->location_for( '/a+b', '/a+b: /x' ) );
		$this->assertStringEndsWith( '/cpp', (string) $this->location_for( '/c++-guide', '/c++-guide: /cpp' ) );
		$this->assertNull( $this->location_for( '/a+b', '/a%20b: /space' ) );
	}

	/**
	 * A regex rule written with literal non-ASCII matches the %-encoded request.
	 */
	public function test_regex_with_literal_non_ascii_matches_encoded_request() {
		$this->assertStringEndsWith( '/cafe', (string) $this->location_for( '/%D0%BA%D0%B0%D1%84%D0%B5', '^/кафе$: /cafe' ) );
		$this->assertNull( $this->location_for( '/%D0%BA%D0%B0%D1%84%D0%B5-x', '^/кафе$: /cafe' ) );
	}

	/**
	 * A group takes its text from the form that matched: raw first, decoded only when raw fails.
	 *
	 * format_target_url decodes every target, and wp_safe_redirect encodes it again,
	 * so both forms send the same Location.
	 */
	public function test_regex_group_takes_the_form_that_matched() {
		$slug = '/%D0%BA%D0%B0%D1%84%D0%B5';
		$news = '/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D0%B8';

		// The raw request matches, so the group holds the %-encoded slug, as it did before.
		$raw = Redirect_Txt_Redirects::match_url_to_rules( '/news' . $slug, '^/news/(.+)$: /blog/$1' );
		$this->assertSame( '/blog/кафе', $raw['to'] );
		$this->assertStringEndsWith( '/blog' . $slug, (string) $this->location_for( '/news' . $slug, '^/news/(.+)$: /blog/$1' ) );

		// Only the decoded request matches, so the group holds the decoded slug.
		$decoded = Redirect_Txt_Redirects::match_url_to_rules( $news . $slug, '^/новости/(.+)$: /blog/$1' );
		$this->assertSame( '/blog/кафе', $decoded['to'] );
		$this->assertSame( $news . $slug, $decoded['from'] );
		$this->assertStringEndsWith( '/blog' . $slug, (string) $this->location_for( $news . $slug, '^/новости/(.+)$: /blog/$1' ) );
	}

	/**
	 * A regex rule written %-encoded matches the raw request as before.
	 */
	public function test_regex_with_encoded_pattern_still_matches() {
		$this->assertStringEndsWith( '/cafe', (string) $this->location_for( '/caf%C3%A9', '^/caf%C3%A9$: /cafe' ) );
	}

	/**
	 * A decoded match whose target is the same page encoded is a loop and is skipped.
	 */
	public function test_regex_decoded_match_to_itself_is_a_loop() {
		$this->assertNull( $this->location_for( '/%D0%BA%D0%B0%D1%84%D0%B5', '^/кафе/?$: /%D0%BA%D0%B0%D1%84%D0%B5/' ) );
	}
}
