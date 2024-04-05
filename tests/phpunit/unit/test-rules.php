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
     * Test match URLs.
     */
    public function test_match_urls() {
		// Simple match.
        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				home_url() . '/test-2',
				"
					test-1: new-test-1
					test-2: new-test-2
				"
			),
			array(
				'from'      => '/test-2',
				'to'        => '/new-test-2',
				'status'    => 301,
				'rule_from' => 'test-2',
				'rule_to'   => 'new-test-2',
			)
		);

		// Don't match.
        $this->assertEquals(
			Redirect_Txt_Redirects::match_url_to_rules(
				home_url() . '/test',
				""
			),
			false
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

			# 308 redirects:
			308:
			test-3: new-test-3

			# Post ID:
			1: 4

			# URL to Post ID:
			test-4: 2

			# 302 redirects:
			302:

			# External URLs:
			test-5: https://example.com/

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
					'to'     => 'new-test-3',
					'status' => 308,
				),
				array(
					'from'   => 'test-4',
					'to'     => 2,
					'status' => 308,
				),
				array(
					'from'   => 'test-5',
					'to'     => 'https://example.com/',
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
