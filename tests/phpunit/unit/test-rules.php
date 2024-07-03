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
				'to'        => '/new-test/url',
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
				'to'        => '/new-test',
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
				'to'        => '/test',
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

		// Multiple rules with comments and different statuses.
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
}
