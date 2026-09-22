<?php
/**
 * Tests for rules
 *
 * @package redirect-txt
 */

/**
 * Rules test case.
 */
class RulesTest extends WP_UnitTestCase {
    /**
     * Test relative paths format.
     */
    public function test_format_relative_paths() {
		// Support for relative paths.
        $this->assertEquals( Redirect_Txt_Redirects::format_url('test'), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('/test'), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('test/'), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('/test/'), '/test' );
    }

    /**
     * Test target URLs format.
	 *
	 * A `to` URL is never compared with anything, it only becomes the Location header.
	 * So it keeps the trailing slash and the case the rule asked for: dropping either
	 * makes WordPress answer with a second redirect that puts it back.
     */
    public function test_format_target_urls() {
		// Keep the trailing slash the rule asked for.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('/test/'), '/test/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('test/'), '/test/' );

		// And do not invent one that was not there.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('/test'), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('test'), '/test' );

		// Keep the case.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('/MixedCase/'), '/MixedCase/' );

		// Fragments and external URLs are untouched, as before.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('/test/#section'), '/test/#section' );
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('https://example.com/path/'), 'https://example.com/path/' );

		// The shared cleanup still applies.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url(' /test/ '), '/test/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('///multiple///slashes///'), '/multiple/slashes/' );

		// The site root is a path. `format_url` used to return '' here, so a request
		// for `/` never equalled it and a rule `/: /hello` matched nothing.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url('/'), '/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('/'), '/' );

		// An empty target stays empty, which is what the 403/404/410 rules rely on.
        $this->assertEquals( Redirect_Txt_Redirects::format_target_url(''), '' );
    }

    /**
     * Test external URLs format.
     */
    public function test_format_external_urls() {
        $this->assertEquals( Redirect_Txt_Redirects::format_url('https://example.com/'), 'https://example.com/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('http://example.com/'), 'http://example.com/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('https://example.com'), 'https://example.com' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('http://example.com'), 'http://example.com' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('www.example.com'), 'http://www.example.com' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('www.example.com/'), 'http://www.example.com/' );
    }

    /**
     * Test clean URLs.
     */
    public function test_clean_urls() {
		// Remove multiple slashes.
        $this->assertEquals( Redirect_Txt_Redirects::format_url('https://example.com///test//////multiple/slashes///'), 'https://example.com/test/multiple/slashes/' );

		// Trim spaces.
        $this->assertEquals( Redirect_Txt_Redirects::format_url(' test '), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('    test    '), '/test' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url(' https://example.com/ '), 'https://example.com/' );
        $this->assertEquals( Redirect_Txt_Redirects::format_url('    https://example.com/    '), 'https://example.com/' );
    }

    /**
     * Test protected paths.
	 *
	 * We should not allow redirects from wp-admin pages, wp-json pages, and the login page.
	 * Because we still need access to the WP admin and the REST API.
     */
    public function test_protected_paths() {
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-admin/'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-admin'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-admin/hello'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-adminhello'), false );

        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-json/'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-json'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-json/hello'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-jsonhello'), false );

        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-login.php'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-login.php?test=1'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-login.php/hello'), true );
        $this->assertEquals( Redirect_Txt_Redirects::is_protected_path('/wp-login.phphello'), true );
    }

    /**
     * Test match URLs.
     */
    public function test_match_urls() {
		// Simple match.
        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/test-2',
				"
					test-1: new-test-1
					test-2: new-test-2
				"
			),
			array(
				'from'      => '/test-2',
				'from_type' => 'url',
				'from_rule' => 'test-2',
				'to'        => '/new-test-2',
				'to_type'   => 'url',
				'to_rule'   => 'new-test-2',
				'status'    => 301,
			)
		);

		// Don't match.
        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/test',
				""
			),
			false
		);
    }

    /**
     * Test RegEx match URLs.
	 *
	 * The capture carries whatever it is given, trailing slash included, so `/test/url/`
	 * against `^/test/(.*)` targets `/new-test/url/` and not `/new-test/url`.
	 *
	 * A live request does not arrive that way. `maybe_process_redirect` strips the
	 * trailing slash before matching, because a rule anchored with `$` is written
	 * against the stripped form. So in production a regex rule still rebuilds its
	 * target from a slash-less URL and costs the second hop. Plain rules do not.
     */
    public function test_match_regex() {
        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/test/url/',
				"
					^/test/(.*): /new-test/$1
				"
			),
			array(
				'from'      => '/test/url/',
				'from_type' => 'regex',
				'from_rule' => '^/test/(.*)',
				'to'        => '/new-test/url/',
				'to_type'   => 'url',
				'to_rule'   => '/new-test/$1',
				'status'    => 301,
			)
		);

        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/testurl/',
				"
					^/test(.*)url: /new-test/
				"
			),
			array(
				'from'      => '/testurl/',
				'from_type' => 'regex',
				'from_rule' => '^/test(.*)url',
				'to'        => '/new-test/',
				'to_type'   => 'url',
				'to_rule'   => '/new-test/',
				'status'    => 301,
			)
		);

        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/test/?id=hello',
				"
					^/(.*)\?id=(.*): /$1?new-id=$2
				"
			),
			array(
				'from'      => '/test/?id=hello',
				'from_type' => 'regex',
				'from_rule' => '^/(.*)\?id=(.*)',
				'to'        => '/test/?new-id=hello',
				'to_type'   => 'url',
				'to_rule'   => '/$1?new-id=$2',
				'status'    => 301,
			)
		);

        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/2024/04/06/test/',
				"
					^/\d{4}/\d{2}/\d{2}/(.*): /$1
				"
			),
			array(
				'from'      => '/2024/04/06/test/',
				'from_type' => 'regex',
				'from_rule' => '^/\d{4}/\d{2}/\d{2}/(.*)',
				'to'        => '/test/',
				'to_type'   => 'url',
				'to_rule'   => '/$1',
				'status'    => 301,
			)
		);

        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/test.html',
				"
					^/(.*?)\.html$: /$1
				"
			),
			array(
				'from'      => '/test.html',
				'from_type' => 'regex',
				'from_rule' => '^/(.*?)\.html$',
				'to'        => '/test',
				'to_type'   => 'url',
				'to_rule'   => '/$1',
				'status'    => 301,
			)
		);
    }

    /**
     * Parse redirects from rules string.
     */
    public function test_parse_redirects_from_string() {
		$rules_large = "
			# 301 redirects:
			/test/: /new-test/ # test comments here
			test-2: new-test-2

			# Hash support:
			test-3: new-test-3#with-hash

			# 308 redirects:
			308:
			test-4: new-test-4

			# Post ID:
			1: 4

			# URL to Post ID:
			test-5: 2

			# 302 redirects:
			302:

			# External URLs:
			test-6: https://example.com/

			# RegEx support.
			^/test-7/(.*): /new-test-7/$1

			# You can use as many comments as you want to categorize your links better.
		";

		// Simple rule.
        $this->assertEquals(
			Redirect_Txt_Redirects::parse_redirect_rules('test: new-test'),
			array(
				array(
					'from'   => 'test',
					'to'     => 'new-test',
					'status' => 301,
				)
			)
		);

		// Multiple rules with comments and different statuses.
		// Skip rules with post ID in `from` field.
        $this->assertEquals(
			Redirect_Txt_Redirects::parse_redirect_rules($rules_large),
			array(
				array(
					'from'   => '/test/',
					'to'     => '/new-test/',
					'status' => 301,
				),
				array(
					'from'   => 'test-2',
					'to'     => 'new-test-2',
					'status' => 301,
				),
				array(
					'from'   => 'test-3',
					'to'     => 'new-test-3#with-hash',
					'status' => 301,
				),
				array(
					'from'   => 'test-4',
					'to'     => 'new-test-4',
					'status' => 308,
				),
				array(
					'from'   => 'test-5',
					'to'     => 2,
					'status' => 308,
				),
				array(
					'from'   => 'test-6',
					'to'     => 'https://example.com/',
					'status' => 302,
				),
				array(
					'from'   => '^/test-7/(.*)',
					'to'     => '/new-test-7/$1',
					'status' => 302,
				),
			)
		);

		// Keep only rules with post ID in `from` field.
        $this->assertEquals(
			Redirect_Txt_Redirects::parse_redirect_rules($rules_large, false, true),
			array(
				array(
					'from'   => '1',
					'to'     => '4',
					'status' => 308,
				),
			)
		);
    }

	/**
	 * A target the next request would match again is not a redirect.
	 *
	 * Matching lowercases the path and drops the trailing slash, and the browser
	 * does not send the fragment. Slash, case, an added query, and a same-path
	 * fragment all come back as the same request. A later rule for that path
	 * still applies. A target on another path does not.
	 */
	public function test_self_redirect_is_not_a_match() {
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/loop', "/loop: /loop/" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/loop/', "/loop: /loop/" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/case', "/case: /Case/" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/same', "/same: /same" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/q', "/q: /q?x=1" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/hash', "/hash: /hash#section" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/foo', "^/(.*): /\$1" )
		);
		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules(
				'/loop2',
				'/loop2: ' . home_url( '/loop2/' )
			)
		);

		$next = Redirect_Txt_Redirects::match_url_to_rules(
			'/loop',
			"/loop: /loop/\n/loop: /elsewhere/"
		);
		$this->assertEquals( '/elsewhere/', $next['to'] );
		$this->assertEquals( '/elsewhere/', $next['to_rule'] );
	}

	/**
	 * Rules that already redirected stay on the Location they asked for.
	 */
	public function test_real_redirects_keep_their_target() {
		$cases = array(
			array( '/old/', "/old/: /pricing/", '/pricing/' ),
			array( '/Upper/', "/Upper/: /MixedCase/", '/MixedCase/' ),
			array( '/root/', "/root/: /", '/' ),
			array( '/oldhash', "/oldhash: /newhash#section", '/newhash#section' ),
			array( '/withq?x=1', "/withq?x=1: /withq?x=2", '/withq?x=2' ),
			array( '/external', "/external: https://example.com/path/", 'https://example.com/path/' ),
			array( '/loop2', "/loop2: https://example.com/loop2/", 'https://example.com/loop2/' ),
			array( '/old?utm=1', "/old: /new/", '/new/?utm=1' ),
			array( '/a', "^/(.*): /x\$1", '/xa' ),
			array( '/', "/: /hello/", '/hello/' ),
		);

		foreach ( $cases as $case ) {
			$match = Redirect_Txt_Redirects::match_url_to_rules( $case[0], $case[1] );
			$this->assertIsArray( $match, $case[1] );
			$this->assertEquals( $case[2], $match['to'], $case[1] );
		}

		$blocked = Redirect_Txt_Redirects::match_url_to_rules(
			'/blocked',
			"404:\n/blocked: /blocked"
		);
		$this->assertEquals( 404, $blocked['status'] );
	}

	/**
	 * Plain rules on a site installed in a subdirectory.
	 *
	 * The request arrives with the home path on the front, and the Location has
	 * to keep it, because a path that starts with `/` is host-absolute. A home
	 * path that only appears later in the URL is not the prefix and is left alone.
	 */
	public function test_plain_rules_match_in_a_subdirectory() {
		add_filter( 'home_url', array( $this, 'append_subdir_to_home_url' ) );

		$match = Redirect_Txt_Redirects::match_url_to_rules( '/subdir/old/', "/old/: /new/" );
		$miss  = Redirect_Txt_Redirects::match_url_to_rules( '/2024/subdir/post', "/2024/post: /dest/" );

		remove_all_filters( 'home_url' );

		$this->assertIsArray( $match );
		$this->assertEquals( '/subdir/new/', $match['to'] );
		$this->assertFalse( $miss );
	}

	/**
	 * Filter callback. Puts the site in /subdir for one test.
	 *
	 * @param string $url Home URL.
	 * @return string
	 */
	public function append_subdir_to_home_url( $url ) {
		if ( false !== strpos( $url, '/subdir' ) ) {
			return $url;
		}

		return rtrim( $url, '/' ) . '/subdir';
	}

	/**
	 * `@` is a valid character in a pattern. It is not the delimiter.
	 */
	public function test_regex_pattern_may_contain_at_sign() {
		$match = Redirect_Txt_Redirects::match_url_to_rules(
			'/user/ada@example.com',
			'^/user/(.*)@example.com: /u/$1'
		);

		$this->assertEquals( '/u/ada', $match['to'] );
	}

	/**
	 * A post redirected to itself does not loop. A post redirected to another does.
	 */
	public function test_post_id_self_redirect_is_skipped() {
		$from_id = $this->factory->post->create(
			array(
				'post_title' => 'From',
				'post_name'  => 'from-post',
			)
		);
		$to_id   = $this->factory->post->create(
			array(
				'post_title' => 'To',
				'post_name'  => 'to-post',
			)
		);

		global $wp_query;
		$wp_query->queried_object    = get_post( $from_id );
		$wp_query->queried_object_id = $from_id;

		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/from-post', $from_id . ': ' . $from_id, true, true )
		);

		$match = Redirect_Txt_Redirects::match_url_to_rules( '/from-post', $from_id . ': ' . $to_id, true, true );
		$this->assertEquals( get_permalink( $to_id ), $match['to'] );
	}

	/**
	 * A target outside this install is not a loop, even on the same hostname.
	 * A query chain that an earlier rule finishes is not a loop either.
	 * A query that differs only by case is.
	 */
	public function test_leaving_the_install_is_not_a_loop() {
		add_filter( 'home_url', array( $this, 'append_subdir_to_home_url' ) );

		$home  = wp_parse_url( home_url() );
		$root  = $home['scheme'] . '://' . $home['host'] . ( empty( $home['port'] ) ? '' : ':' . $home['port'] );
		$leave = Redirect_Txt_Redirects::match_url_to_rules( '/subdir/old/', '/old/: ' . $root . '/old/' );
		$moved = Redirect_Txt_Redirects::match_url_to_rules( '/subdir/my-post', '^/(.*): ' . $root . '/$1' );

		remove_all_filters( 'home_url' );

		$this->assertEquals( $root . '/old/', $leave['to'] );
		$this->assertEquals( $root . '/my-post', $moved['to'] );

		$home = wp_parse_url( home_url() );
		$other_port = $home['scheme'] . '://' . $home['host'] . ':9999/page';
		$ported = Redirect_Txt_Redirects::match_url_to_rules( '/page', '/page: ' . $other_port );
		$this->assertEquals( $other_port, $ported['to'] );

		$chain = "/shop?currency=usd: /store\n/shop: /shop?currency=usd";
		$first = Redirect_Txt_Redirects::match_url_to_rules( '/shop', $chain );
		$second = Redirect_Txt_Redirects::match_url_to_rules( '/shop?currency=usd', $chain );
		$this->assertEquals( '/shop?currency=usd', $first['to'] );
		$this->assertEquals( '/store', $second['to'] );

		$this->assertFalse(
			Redirect_Txt_Redirects::match_url_to_rules( '/q?x=1', '/q?x=1: /q?X=1' )
		);

		$delimited = Redirect_Txt_Redirects::match_url_to_rules( '/a#', '^/a[#~!%`]: /ok' );
		$this->assertEquals( '/ok', $delimited['to'] );
	}
}
