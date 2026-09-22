<?php
/**
 * Process redirects.
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Txt_Redirects class.
 */
class Redirect_Txt_Redirects {
	/**
	 * Store whitelisted host
	 *
	 * @var array
	 */
	public static $whitelist_host;

	/**
	 * True while checking whether an earlier rule would catch the next request.
	 *
	 * The check calls match_url_to_rules again. Without this it would decide
	 * the inner call is a loop and never see the earlier rule.
	 *
	 * @var bool
	 */
	private static $checking_repeat = false;

	/**
	 * Redirect_Txt_Redirects constructor.
	 */
	public static function init() {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		add_action( 'parse_request', 'Redirect_Txt_Redirects::maybe_process_redirect', 1 );

		// Additional redirection check for post ID used in `from` field.
		// We need this to check the actual wp_query of the current post loaded.
		add_action( 'wp', 'Redirect_Txt_Redirects::maybe_process_redirect', 1 );
	}

	/**
	 * Get URL type: [url,id,regex]
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	public static function get_url_type( $url ) {
		$type = 'url';

		// Post ID.
		if ( is_numeric( $url ) ) {
			$type = 'id';

			// Simple detection for regular expression if URL contains starts with ^
			// .
		} elseif ( preg_match( '/^\^/i', $url ) ) {
			$type = 'regex';
		}

		return $type;
	}

	/**
	 * Prepare a URL, without deciding what it is for.
	 *
	 * Resolves a relative URL against the site, collapses repeated slashes and trims
	 * whitespace. Leaves the case and the trailing slash alone; the two callers below
	 * want different things from those.
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = urldecode( html_entity_decode( trim( $url ) ) );

		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '/^www\./i', $url ) ) {
			$url = 'http://' . $url;
		}

		if ( self::is_absolute_url( $url ) ) {
			// Remove multiple slashes.
			return trim( preg_replace( '/([^:])(\/{2,})/', '$1/', $url ) );
		}

		$complete_url = rtrim( home_url(), '/' ) . '/' . $url;

		// phpcs:ignore
		list( $uprotocol, $uempty, $uhost, $path ) = explode( '/', $complete_url, 4 );

		$path = '/' . $path;

		// Remove multiple slashes.
		return trim( preg_replace( '#/+#', '/', $path ) );
	}

	/**
	 * Check whether a URL carries its own scheme.
	 *
	 * @param string $url - url string.
	 *
	 * @return bool
	 */
	private static function is_absolute_url( $url ) {
		return (bool) preg_match( '/^https?:\/\//i', $url );
	}

	/**
	 * Prepare a `from` URL for matching.
	 *
	 * Lowercases the path and drops the trailing slash, because the requested URL is
	 * put through the same treatment before the comparison. Both sides have to agree.
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	public static function format_url( $url ) {
		$url = self::normalize_url( $url );

		// An absolute URL is matched as written.
		if ( self::is_absolute_url( $url ) ) {
			return $url;
		}

		$url = strtolower( rtrim( $url, '/' ) );

		// rtrim turns the homepage into ''. A request for `/` is not empty, so the
		// two sides would never be equal and `/: /hello` would match nothing.
		if ( '' === $url ) {
			return '/';
		}

		return $url;
	}

	/**
	 * Prepare a `to` URL for the Location header.
	 *
	 * A target is never compared with anything, so it keeps the case and the trailing
	 * slash the rule asked for. Dropping the slash makes WordPress answer the
	 * slash-less URL with a second redirect that puts it back, and every such redirect
	 * then costs two hops instead of one.
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	public static function format_target_url( $url ) {
		return self::normalize_url( $url );
	}

	/**
	 * Home path when WordPress is installed in a subdirectory, without a trailing slash.
	 *
	 * Empty at the domain root, where the request path and a rule path already agree.
	 *
	 * @return string
	 */
	private static function home_path_prefix() {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( home_url() );
		} else {
			$parsed = parse_url( home_url() ); // phpcs:ignore
		}

		if ( empty( $parsed['path'] ) || '/' === $parsed['path'] ) {
			return '';
		}

		return untrailingslashit( $parsed['path'] );
	}

	/**
	 * Drop the subdirectory prefix from a request or a `from` path.
	 *
	 * Only a real prefix counts. A home path of `/blog` must not eat the same
	 * segment out of `/2024/blog/post`. The target is not passed through here:
	 * a Location that starts with `/` is host-absolute, so it still needs the prefix.
	 *
	 * @param string $url Path, optionally with a query.
	 * @return string
	 */
	private static function strip_home_path( $url ) {
		$prefix = self::home_path_prefix();

		if ( '' === $prefix ) {
			return $url;
		}

		$stripped = preg_replace( '#^' . preg_quote( $prefix, '#' ) . '(?=/|$|\?)#i', '', $url, 1 );

		if ( null === $stripped || '' === $stripped ) {
			return '/';
		}

		if ( '?' === $stripped[0] ) {
			return '/' . $stripped;
		}

		return $stripped;
	}

	/**
	 * Delimiter that is not already inside the pattern, plus the case flag.
	 *
	 * The pattern is a user string. Wrapping it in `@` makes a pattern that
	 * contains `@` fail with "Unknown modifier".
	 *
	 * @param string $pattern Regular expression without delimiters.
	 * @return string
	 */
	private static function delimited_pattern( $pattern ) {
		$delimiters = array( '#', '~', '!', '%', '`', "\x01" );

		foreach ( $delimiters as $delimiter ) {
			if ( false === strpos( $pattern, $delimiter ) ) {
				return $delimiter . $pattern . $delimiter . 'i';
			}
		}

		return "\x01" . $pattern . "\x01i";
	}

	/**
	 * A 3xx sends a Location. The 4xx codes are answers, not redirects.
	 *
	 * @param int $status HTTP status from the rule.
	 * @return bool
	 */
	private static function sends_location( $status ) {
		return ! in_array( (int) $status, array( 403, 404, 410 ), true );
	}

	/**
	 * Host, ignoring a leading www so it matches the redirect whitelist.
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	private static function bare_host( $host ) {
		$host = strtolower( $host );

		if ( 0 === strpos( $host, 'www.' ) ) {
			return substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Port the URL is served on, including the scheme default.
	 *
	 * @param array $parts Parsed URL.
	 * @return int
	 */
	private static function url_port( $parts ) {
		if ( isset( $parts['port'] ) ) {
			return (int) $parts['port'];
		}

		if ( isset( $parts['scheme'] ) && 'https' === strtolower( $parts['scheme'] ) ) {
			return 443;
		}

		return 80;
	}

	/**
	 * Whether a path is the install, or sits under its subdirectory.
	 *
	 * @param string $path Path, without a query.
	 * @return bool
	 */
	private static function path_is_inside_home( $path ) {
		$prefix = self::home_path_prefix();

		if ( '' === $prefix ) {
			return true;
		}

		if ( 0 !== stripos( $path, $prefix ) ) {
			return false;
		}

		$rest = substr( $path, strlen( $prefix ) );

		return '' === $rest || '/' === $rest[0];
	}

	/**
	 * Path and query of a Location that points at this site.
	 *
	 * Null when the next request would not hit this install: another host, another
	 * port, or a path outside the subdirectory. The fragment is dropped because
	 * the browser does not send it.
	 *
	 * @param string $url Location or path.
	 * @return string|null
	 */
	private static function location_on_this_site( $url ) {
		$hash = strpos( $url, '#' );

		if ( false !== $hash ) {
			$url = substr( $url, 0, $hash );
		}

		if ( ! self::is_absolute_url( $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url() );

		if ( empty( $parts['host'] ) || empty( $home['host'] ) ) {
			return null;
		}

		if ( self::bare_host( $parts['host'] ) !== self::bare_host( $home['host'] ) ) {
			return null;
		}

		// 80 and 443 with no port written in the URL are the same install seen
		// over http and https. An explicit port, such as :8443, is another listener.
		$target_has_port = isset( $parts['port'] );
		$home_has_port   = isset( $home['port'] );

		if ( ( $target_has_port || $home_has_port ) && self::url_port( $parts ) !== self::url_port( $home ) ) {
			return null;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';

		if ( ! self::path_is_inside_home( $path ) ) {
			return null;
		}

		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		return $path;
	}

	/**
	 * Key the matcher compares: lower case, no trailing slash, query kept, fragment gone.
	 *
	 * @param string $url Path, optionally with a query.
	 * @return string
	 */
	private static function match_key( $url ) {
		$query     = '';
		$query_pos = strpos( $url, '?' );

		if ( false !== $query_pos ) {
			$query = strtolower( substr( $url, $query_pos ) );
			$url   = substr( $url, 0, $query_pos );
		}

		$url = strtolower( untrailingslashit( $url ) );

		if ( '' === $url ) {
			$url = '/';
		}

		return $url . $query;
	}

	/**
	 * Key of a Location on this site, or null when it leaves the site.
	 *
	 * @param string $url Location or path.
	 * @return string|null
	 */
	private static function on_site_key( $url ) {
		$relative = self::location_on_this_site( $url );

		if ( null === $relative ) {
			return null;
		}

		return self::match_key( self::strip_home_path( $relative ) );
	}

	/**
	 * True when both keys are the same request this rule would match again.
	 *
	 * A `from` that contains a query only matches that query. A `from` that does
	 * not ignores the query, so a target that only adds one is the same path.
	 *
	 * @param string $from_key Match key of the rule.
	 * @param string $to_key   Match key of the Location.
	 * @return bool
	 */
	private static function keys_repeat( $from_key, $to_key ) {
		if ( false !== strpos( $from_key, '?' ) ) {
			return $from_key === $to_key;
		}

		$from_path = strstr( $from_key, '?', true );
		$to_path   = strstr( $to_key, '?', true );

		if ( false === $from_path ) {
			$from_path = $from_key;
		}

		if ( false === $to_path ) {
			$to_path = $to_key;
		}

		return $from_path === $to_path;
	}

	/**
	 * A regex whose replacement, requested again, replaces to the same Location.
	 *
	 * @param string $pattern     Pattern as written.
	 * @param string $replacement Replacement as written.
	 * @param string $to          Location this request would send.
	 * @return bool
	 */
	private static function regex_target_repeats( $pattern, $replacement, $to ) {
		$next = self::location_on_this_site( $to );

		if ( null === $next ) {
			return false;
		}

		$next = untrailingslashit( self::strip_home_path( $next ) );

		// The matcher turns that empty string back into `/` before the pattern runs.
		if ( '' === $next ) {
			$next = '/';
		}

		$delimited = self::delimited_pattern( $pattern );

		if ( ! preg_match( $delimited, $next ) ) {
			return false;
		}

		$next_to  = self::format_target_url( preg_replace( $delimited, $replacement, $next ) );
		$next_key = self::on_site_key( $next_to );
		$to_key   = self::on_site_key( $to );

		if ( null === $next_key || null === $to_key ) {
			return false;
		}

		return $next_key === $to_key;
	}

	/**
	 * The next request would hit this same rule and get this same Location.
	 *
	 * @param string $from_type url, regex, or id.
	 * @param string $from      Match key, pattern, or permalink.
	 * @param string $raw_to    Target as written. Regex replacements need this.
	 * @param string $to        Location this request would send.
	 * @return bool
	 */
	private static function target_repeats( $from_type, $from, $raw_to, $to ) {
		if ( 'regex' === $from_type ) {
			return self::regex_target_repeats( $from, $raw_to, $to );
		}

		$to_key = self::on_site_key( $to );

		if ( null === $to_key ) {
			return false;
		}

		if ( 'id' === $from_type ) {
			$from_key = self::on_site_key( $from );

			return null !== $from_key && self::keys_repeat( $from_key, $to_key );
		}

		return self::keys_repeat( $from, $to_key );
	}

	/**
	 * An earlier rule matches the Location this rule would send.
	 *
	 * That request never comes back to this rule, so the redirect is a chain.
	 *
	 * @param array  $earlier  Rules parsed before this one.
	 * @param string $location Location this rule would send.
	 * @return bool
	 */
	private static function earlier_rule_matches( $earlier, $location ) {
		if ( empty( $earlier ) ) {
			return false;
		}

		$next = self::location_on_this_site( $location );

		if ( null === $next ) {
			return false;
		}

		// maybe_process_redirect strips the slash before this matcher runs.
		$next = untrailingslashit( $next );

		if ( '' === $next ) {
			$next = '/';
		}

		$lines  = array();
		$status = null;

		foreach ( $earlier as $rule ) {
			if ( $status !== (int) $rule['status'] ) {
				$status  = (int) $rule['status'];
				$lines[] = $status . ':';
			}

			$lines[] = $rule['from'] . ': ' . $rule['to'];
		}

		return false !== self::match_url_to_rules( $next, implode( "\n", $lines ) );
	}

	/**
	 * Get valid HTTP status codes and their labels.
	 *
	 * @return array
	 */
	public static function get_valid_status_codes() {
		$status_codes = array(
			301 => esc_html__( 'Moved Permanently', 'redirect-txt' ),
			302 => esc_html__( 'Found', 'redirect-txt' ),
			303 => esc_html__( 'See Other', 'redirect-txt' ),
			307 => esc_html__( 'Temporary Redirect', 'redirect-txt' ),
			308 => esc_html__( 'Permanent Redirect', 'redirect-txt' ),
			403 => esc_html__( 'Forbidden', 'redirect-txt' ),
			404 => esc_html__( 'Not Found', 'redirect-txt' ),
			410 => esc_html__( 'Gone', 'redirect-txt' ),
		);

		$additional_status_codes = apply_filters( 'redirect_txt_additional_status_codes', array() );

		if ( empty( $additional_status_codes ) ) {
			return $status_codes;
		}

		$status_code_array = $status_codes + $additional_status_codes;

		ksort( $status_code_array, SORT_NUMERIC );

		return $status_code_array;
	}

	/**
	 * Parse redirects and prepare array.
	 *
	 * @param string $rules - string with rules.
	 * @param bool   $allow_url_only_redirects - allow redirecting using URLs only.
	 * @param bool   $allow_post_from - allow redirecting from post IDs.
	 *
	 * @return array
	 */
	public static function parse_redirect_rules( $rules, $allow_url_only_redirects = true, $allow_post_from = false ) {
		$redirects = array();

		if ( is_string( $rules ) && $rules ) {

			/**
			 * Remove comments, but keep hashes in links.
			 *
			 * This regex does two things:
			 *  1. ^[ \t]*#.*$: Removes lines that start with a hash (optionally preceded by whitespace).
			 *  2. (?<=\s)#.*$: Removes inline comments that start with a hash preceded by whitespace.
			 *
			 * Explanation of the new part:
			 *  • (?<=\s): Positive lookbehind, ensures the hash is preceded by whitespace
			 *  • #.*$: Matches the hash and everything after it until the end of the line
			 */
			$rules = preg_replace( '/^[ \t]*#.*$|(?<=\s)#.*$/m', '', $rules );

			// Split string by lines.
			$lines = preg_split( "/\r\n|\n|\r/", $rules );

			$valid_status_codes = self::get_valid_status_codes();
			$status             = apply_filters( 'redirect_txt_default_status', 301 );

			// Parse each line and prepare redirect array.
			foreach ( $lines as $line ) {
				$line = trim( $line );

				// Skip empty line.
				if ( ! $line ) {
					continue;
				}

				// This is a status line.
				foreach ( $valid_status_codes as $status_code => $status_label ) {
					if ( $line === $status_code . ':' ) {
						$status = $status_code;

						continue 2;
					}
				}

				// Split line by colon.
				$parts = preg_split( '/: /', $line );

				// Check if we have 2 parts.
				if ( count( $parts ) !== 2 ) {
					continue;
				}

				$from = trim( $parts[0] );
				$to   = trim( $parts[1] );

				if ( ! $from || ! $to ) {
					continue;
				}

				// Support for post IDs in `from` field.
				$from_is_post = is_numeric( $from );

				if ( ! $allow_post_from && $from_is_post ) {
					continue;
				}
				if ( ! $allow_url_only_redirects && ! $from_is_post ) {
					continue;
				}

				$redirects[] = [
					'from'   => $from,
					'to'     => $to,
					'status' => $status,
				];
			}
		}

		return $redirects;
	}

	/**
	 * Apply whitelisted host to allowed_redirect_hosts filter
	 *
	 * @param array $hosts Array of hosts.
	 *
	 * @return array
	 */
	public static function filter_allowed_redirect_hosts( $hosts ) {
		$without_www = preg_replace( '/^www\./i', '', self::$whitelist_host );
		$with_www    = 'www.' . $without_www;

		$hosts[] = $without_www;
		$hosts[] = $with_www;

		return array_unique( $hosts );
	}

	/**
	 * Check if URL match any of rules and return it.
	 *
	 * @param string $url - full URL to check if it match anything in rules list.
	 * @param string $rules - rules list.
	 * @param bool   $allow_url_only_redirects - allow redirecting using URLs only.
	 * @param bool   $allow_post_from - allow redirecting from post IDs.
	 *
	 * @return bool|array
	 */
	public static function match_url_to_rules( $url, $rules, $allow_url_only_redirects = true, $allow_post_from = false ) {
		global $wp_query;

		$redirects = self::parse_redirect_rules( $rules, $allow_url_only_redirects, $allow_post_from );

		if ( empty( $redirects ) ) {
			return false;
		}

		/**
		 * If WordPress resides in a directory that is not the public root, chop that
		 * prefix off the requested path. The same prefix is removed from a plain `from`
		 * below, so the two sides still meet. Only a real prefix is removed.
		 */
		$url = self::strip_home_path( $url );

		if ( '' === $url ) {
			$url = '/';
		}

		// Normalized path is used for matching but not for replace.
		$normalized_requested_url = strtolower( $url );

		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed_requested_url = wp_parse_url( $normalized_requested_url );
		} else {
			// phpcs:ignore
			$parsed_requested_url = parse_url( $normalized_requested_url );
		}

		// Normalize the request path with and without query strings, for comparison later.
		$normalized_requested_url_no_query = '';
		$requested_query_params            = '';

		if ( ! empty( $parsed_requested_url['path'] ) ) {
			$normalized_requested_url_no_query = untrailingslashit( stripslashes( $parsed_requested_url['path'] ) );

			// untrailingslashit( '/' ) is ''. That is the homepage, and `from` uses `/` for it.
			if ( '' === $normalized_requested_url_no_query ) {
				$normalized_requested_url_no_query = '/';
			}
		}

		if ( ! empty( $parsed_requested_url['query'] ) ) {
			$requested_query_params = $parsed_requested_url['query'];
		}

		$queried_object = $wp_query->get_queried_object();

		foreach ( $redirects as $index => $redirect ) {
			$from_type    = self::get_url_type( $redirect['from'] );
			$to_type      = self::get_url_type( $redirect['to'] );
			$matched_path = false;

			// Post ID.
			if ( 'id' === $from_type ) {
				$from = (int) $redirect['from'];

				// RegEx.
			} elseif ( 'regex' === $from_type ) {
				$from = $redirect['from'];

				// URL.
			} else {
				$from = self::strip_home_path( self::format_url( $redirect['from'] ) );
			}

			// Post ID.
			if ( 'id' === $to_type ) {
				$to = (int) $redirect['to'];

				// RegEx.
			} elseif ( 'regex' === $from_type ) {
				$to = $redirect['to'];

				// URL.
			} else {
				$to = self::format_target_url( $redirect['to'] );
			}

			// Check if the redirection destination is valid, otherwise just skip it (unless this is a 4xx request).
			if ( empty( $to ) && ! in_array( $redirect['status'], array( 403, 404, 410 ), true ) ) {
				continue;
			}

			// Redirect from current post ID.
			if ( 'id' === $from_type ) {
				if ( $from === $queried_object->ID ) {
					$from         = get_permalink( $from );
					$matched_path = true;
				} else {
					continue;
				}
			}

			$match_query_params = strpos( $from, '?' );

			// RegEx.
			if ( 'regex' === $from_type ) {
				$match_query_params = false;
				$matched_path       = preg_match( self::delimited_pattern( $from ), $url );
			}

			if ( ! $matched_path ) {
				$to_match     = ( ! $match_query_params && ! empty( $normalized_requested_url_no_query ) ) ? $normalized_requested_url_no_query : $normalized_requested_url;
				$matched_path = $to_match === $from;
			}

			if ( $matched_path ) {
				// Redirect to post.
				if ( 'id' === $to_type ) {
					$to = get_permalink( $to );
				}

				// Regex URL.
				if ( 'regex' === $from_type ) {
					$to   = preg_replace( self::delimited_pattern( $from ), $to, $url );
					$to   = self::format_target_url( $to );
					$from = $url;
				}

				/**
				 * Whitelist redirect host.
				 *
				 * The probe below calls this function again. It must not replace the
				 * host this redirect is about to send.
				 */
				if ( ! self::$checking_repeat ) {
					if ( function_exists( 'wp_parse_url' ) ) {
						$parsed_redirect = wp_parse_url( $to );
					} else {
						// phpcs:ignore
						$parsed_redirect = parse_url( $to );
					}

					if ( is_array( $parsed_redirect ) && ! empty( $parsed_redirect['host'] ) ) {
						self::$whitelist_host = $parsed_redirect['host'];
						add_filter( 'allowed_redirect_hosts', 'Redirect_Txt_Redirects::filter_allowed_redirect_hosts' );
					}
				}

				// Re-add the query params if they've not already been added by the wildcard
				// query params are forwarded to allow for attribution and marketing params to be maintained.
				if ( ! $match_query_params && ! empty( $requested_query_params ) && ! strpos( $to, '?' ) ) {
					$to .= '?' . $requested_query_params;
				}

				/**
				 * Filter the url to redirect to.
				 */
				if ( ! self::$checking_repeat ) {
					$to = apply_filters( 'redirect_txt_redirect_to', $to );
				}
				$to = esc_url_raw( $to );

				// The next request is lowercased and loses its slash and its fragment
				// before this comparison runs again. A target that differs only by those
				// is this same rule, and sending it loops. An earlier rule that would
				// catch that request is a chain, not a loop, so this rule still fires.
				// The probe still rejects an earlier rule that is itself a loop.
				if ( self::sends_location( $redirect['status'] ) ) {
					$repeats         = self::target_repeats(
						$from_type,
						'regex' === $from_type ? $redirect['from'] : $from,
						$redirect['to'],
						$to
					);
					$earlier_catches = false;

					if ( $repeats && ! self::$checking_repeat ) {
						$saved_host            = self::$whitelist_host;
						self::$checking_repeat = true;
						$earlier_catches       = self::earlier_rule_matches( array_slice( $redirects, 0, $index ), $to );
						self::$checking_repeat = false;
						self::$whitelist_host  = $saved_host;
					}

					if ( $repeats && ! $earlier_catches ) {
						continue;
					}
				}

				return [
					'from'      => $from,
					'from_type' => $from_type,
					'from_rule' => $redirect['from'],
					'to'        => $to,
					'to_type'   => $to_type,
					'to_rule'   => $redirect['to'],
					'status'    => $redirect['status'],
				];
			}
		}

		return false;
	}

	/**
	 * Check if URL match any of redirect rules and return it.
	 *
	 * @param string $requested_url - url to check.
	 *
	 * @return bool|array
	 */
	public static function match_redirect( $requested_url ) {
		global $wp_query;

		// Allow redirects from post IDs.
		// `$wp_query` is available in the `wp` hook only.
		$allow_url_only_redirects = current_action() !== 'wp';
		$allow_post_from          = (bool) $wp_query->get_queried_object();
		$rules                    = get_option( 'redirect_txt_rules', '' );

		return self::match_url_to_rules( $requested_url, $rules, $allow_url_only_redirects, $allow_post_from );
	}

	/**
	 * Check if path is protected.
	 * We have to skip the admin, login and rest paths.
	 *
	 * @param string $request - request path.
	 *
	 * @return bool
	 */
	public static function is_protected_path( $request ) {
		$request = rtrim( $request, '/' );

		$protected = apply_filters(
			'redirect_txt_protected_paths',
			[
				'/wp-login.php',
				'/wp-admin/',
				'/wp-json/',
			]
		);

		$not_protected = array_filter(
			$protected,
			function ( $base ) use ( $request ) {
				if (
					$base === $request ||
					rtrim( $base, '/' ) === $request ||
					substr( $request, 0, strlen( $base ) ) === $base
				) {
					return true;
				}

				return false;
			}
		);

		return ! empty( $not_protected );
	}

	/**
	 * Check URL for available redirect and process it.
	 *
	 * @return void
	 */
	public static function maybe_process_redirect() {
		if ( is_admin() ) {
			return;
		}

		$requested_url = esc_url_raw( apply_filters( 'redirect_txt_requested_url', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ?? '' ) );

		/**
		 * The trailing slash goes before matching, and it has to. A rule anchored with
		 * `$`, such as `^/old$`, is written against this stripped form, so a request for
		 * `/old/` only reaches it once the slash is gone. The cost is that a regex rule
		 * rebuilds its target from a URL that has already lost the slash, so a request
		 * for `/old/thing/` still redirects to `/new/thing` and WordPress adds the slash
		 * back with a second hop. Plain rules do not pay that, because their target comes
		 * from the rule rather than from the request.
		 */
		$requested_url = untrailingslashit( stripslashes( $requested_url ) );

		// Skip protected paths.
		if ( self::is_protected_path( $requested_url ) ) {
			return;
		}

		$match_redirect = self::match_redirect( $requested_url );

		if ( ! $match_redirect ) {
			return;
		}

		do_action( 'redirect_txt_redirect_hit', $match_redirect );

		// Use default status code if an invalid value is set.
		if ( ! isset( self::get_valid_status_codes()[ $match_redirect['status'] ] ) ) {
			$match_redirect['status'] = apply_filters( 'redirect_txt_default_status', 301 );
		}

		// We only support 'true' 3xx redirects; handle predefined 4xx here.
		if ( 403 === $match_redirect['status'] || 410 === $match_redirect['status'] ) {
			wp_die(
				'',
				'',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$match_redirect['status']
			);
			return;
		}

		if ( 404 === $match_redirect['status'] ) {
			/**
			 * We must do this manually and not rely on $wp_query->handle_404()
			 * to prevent default "Plain" permalinks from "soft 404"-ing
			 */
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include_once get_query_template( '404' );
			return;
		}

		wp_safe_redirect( $match_redirect['to'], $match_redirect['status'], 'Redirect.txt' );

		exit();
	}
}

Redirect_Txt_Redirects::init();
