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
	 * Redirect_Txt_Redirects constructor.
	 */
	public static function init() {
		if ( is_admin() || defined( 'WP_CLI' ) && WP_CLI ) {
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
	 * Prepare `from` and `to` URLs.
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	public static function format_url( $url ) {
		$url = urldecode( html_entity_decode( trim( $url ) ) );

		if ( preg_match( '/^www\./i', $url ) ) {
			$url = 'http://' . $url;
		}

		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			$from = $url;

			// Remove multiple slashes.
			$from = preg_replace( '/([^:])(\/{2,})/', '$1/', $from );
		} else {
			$complete_url = rtrim( home_url(), '/' ) . '/' . $url;

			// phpcs:ignore
			list( $uprotocol, $uempty, $uhost, $from ) = explode( '/', $complete_url, 4 );

			$from = '/' . $from;

			// Remove multiple slashes.
			$from = preg_replace( '#/+#', '/', $from );

			// Remove slash from the end of line.
			$from = strtolower( rtrim( $from, '/' ) );
		}

		return trim( $from );
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

			// Remove comments.
			$rules = preg_replace( '/#.*/', '', $rules );

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
	 * Get redirects from DB and prepare array.
	 *
	 * @param bool $allow_url_only_redirects - allow redirecting using URLs only.
	 * @param bool $allow_post_from - allow redirecting from post IDs.
	 *
	 * @return array
	 */
	public static function get_redirect_rules( $allow_url_only_redirects = true, $allow_post_from = false ) {
		$rules = get_option( 'redirect_txt_rules', '' );

		return self::parse_redirect_rules( $rules, $allow_url_only_redirects, $allow_post_from );
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
		 * If WordPress resides in a directory that is not the public root, we have to chop
		 * the pre-WP path off the requested path.
		 */
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed_home_url = wp_parse_url( home_url() );
		} else {
			$parsed_home_url = parse_url( home_url() ); // phpcs:ignore
		}

		if ( isset( $parsed_home_url['path'] ) && '/' !== $parsed_home_url['path'] ) {
			$url = preg_replace( '@' . $parsed_home_url['path'] . '@i', '', $url, 1 );
		}

		if ( empty( $url ) ) {
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
		}

		if ( ! empty( $parsed_requested_url['query'] ) ) {
			$requested_query_params = $parsed_requested_url['query'];
		}

		$queried_object = $wp_query->get_queried_object();

		foreach ( $redirects as $redirect ) {
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
				$from = self::format_url( $redirect['from'] );
			}

			// Post ID.
			if ( 'id' === $to_type ) {
				$to = (int) $redirect['to'];

				// RegEx.
			} elseif ( 'regex' === $from_type ) {
				$to = $redirect['to'];

				// URL.
			} else {
				$to = self::format_url( $redirect['to'] );
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
				$matched_path       = preg_match( '@' . $from . '@i', $url );
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
					$to   = preg_replace( '@' . $from . '@i', $to, $url );
					$to   = self::format_url( $to );
					$from = $url;
				}

				/**
				 * Whitelist redirect host.
				 */
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

				// Re-add the query params if they've not already been added by the wildcard
				// query params are forwarded to allow for attribution and marketing params to be maintained.
				if ( ! $match_query_params && ! empty( $requested_query_params ) && ! strpos( $to, '?' ) ) {
					$to .= '?' . $requested_query_params;
				}

				/**
				 * Filter the url to redirect to.
				 */
				$to = apply_filters( 'redirect_txt_redirect_to', $to );
				$to = esc_url_raw( $to );

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
		$allow_post_from          = ! ! $wp_query->get_queried_object();
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
			function( $base ) use ( $request ) {
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
