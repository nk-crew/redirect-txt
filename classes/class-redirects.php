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
	private $whitelist_host;

	/**
	 * Redirect_Txt_Redirects constructor.
	 */
	public function __construct() {
		if ( is_admin() || defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'parse_request', [ $this, 'maybe_process_redirect' ], 1 );

		// Additional redirection check for post ID used in `from` field.
		// We need this to check the actual wp_query of the current post loaded.
		add_action( 'wp', [ $this, 'maybe_process_redirect' ], 1 );
	}

	/**
	 * Prepare `from` and `to` URLs.
	 *
	 * @param string $url - url string.
	 *
	 * @return string
	 */
	private function format_url( $url ) {
		$url = urldecode( html_entity_decode( $url ) );

		if ( preg_match( '/^www\./i', $url ) ) {
			$url = 'http://' . $url;
		}

		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			$from = $url;

			// Remove multiple slashes.
			$from = preg_replace( '/([^:])(\/{2,})/', '$1/', $from );
		} else {
			$complete_url = rtrim( home_url(), '/' ) . '/' . $url;

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
	public function get_valid_status_codes() {
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
	 * @param bool $allow_url_only_redirects - allow redirecting using URLs only.
	 * @param bool $allow_post_from - allow redirecting from post IDs.
	 *
	 * @return array
	 */
	public function get_redirect_rules( $allow_url_only_redirects = true, $allow_post_from = false ) {
		$redirects = array();
		$rules     = get_option( 'redirect_txt_rules', '' );

		if ( is_string( $rules ) && $rules ) {
			// Remove comments.
			$rules = preg_replace( '/#.*/', '', $rules );

			// Split string by lines.
			$lines = preg_split( "/\r\n|\n|\r/", $rules );

			$valid_status_codes = $this->get_valid_status_codes();
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
	public function filter_allowed_redirect_hosts( $hosts ) {
		$without_www = preg_replace( '/^www\./i', '', $this->whitelist_host );
		$with_www    = 'www.' . $without_www;

		$hosts[] = $without_www;
		$hosts[] = $with_www;

		return array_unique( $hosts );
	}

	/**
	 * Check if URL match any of redirect rules and return it.
	 *
	 * @param string $requested_path - path to check.
	 *
	 * @return bool|array
	 */
	public function match_redirect( $requested_path ) {
		global $wp_query;

		// Allow redirects from post IDs.
		// `$wp_query` is available in the `wp` hook only.
		$allow_url_only_redirects = current_action() !== 'wp';
		$allow_post_from          = $wp_query->get_queried_object();

		$redirects = $this->get_redirect_rules( $allow_url_only_redirects, $allow_post_from );

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
			$requested_path = preg_replace( '@' . $parsed_home_url['path'] . '@i', '', $requested_path, 1 );
		}

		if ( empty( $requested_path ) ) {
			$requested_path = '/';
		}

		// Normalized path is used for matching but not for replace.
		$normalized_requested_path = strtolower( $requested_path );

		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed_requested_path = wp_parse_url( $normalized_requested_path );
		} else {
			// phpcs:ignore
			$parsed_requested_path = parse_url( $normalized_requested_path );
		}

		// Normalize the request path with and without query strings, for comparison later.
		$normalized_requested_path_no_query = '';
		$requested_query_params             = '';

		if ( ! empty( $parsed_requested_path['path'] ) ) {
			$normalized_requested_path_no_query = untrailingslashit( stripslashes( $parsed_requested_path['path'] ) );
		}

		if ( ! empty( $parsed_requested_path['query'] ) ) {
			$requested_query_params = $parsed_requested_path['query'];
		}

		$queried_object = $wp_query->get_queried_object();

		foreach ( $redirects as $redirect ) {
			$from = is_numeric( $redirect['from'] ) ? (int) $redirect['from'] : $this->format_url( $redirect['from'] );
			$to   = is_numeric( $redirect['to'] ) ? (int) $redirect['to'] : $this->format_url( $redirect['to'] );

			// Check if the redirection destination is valid, otherwise just skip it (unless this is a 4xx request).
			if ( empty( $to ) && ! in_array( $redirect['status'], array( 403, 404, 410 ), true ) ) {
				continue;
			}

			$matched_path = false;

			// Redirect from current post ID.
			if ( is_int( $from ) ) {
				if ( $from === $queried_object->ID ) {
					$from         = get_permalink( $from );
					$matched_path = true;
				} else {
					continue;
				}
			}

			$match_query_params = strpos( $from, '?' );

			if ( ! $matched_path ) {
				$to_match     = ( ! $match_query_params && ! empty( $normalized_requested_path_no_query ) ) ? $normalized_requested_path_no_query : $normalized_requested_path;
				$matched_path = $to_match === $from;
			}

			if ( $matched_path ) {
				// Redirect to post.
				if ( is_int( $to ) ) {
					$to = get_permalink( $to );
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
					$this->whitelist_host = $parsed_redirect['host'];
					add_filter( 'allowed_redirect_hosts', array( $this, 'filter_allowed_redirect_hosts' ) );
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
					'to'        => $to,
					'status'    => $redirect['status'],
					'rule_from' => $redirect['from'],
					'rule_to'   => $redirect['to'],
				];
			}
		}

		return false;
	}

	/**
	 * Check URL for available redirect and process it.
	 *
	 * @return void
	 */
	public function maybe_process_redirect() {
		if ( is_admin() ) {
			return;
		}

		$requested_path = esc_url_raw( apply_filters( 'redirect_txt_requested_path', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ?? '' ) );
		$requested_path = untrailingslashit( stripslashes( $requested_path ) );

		$match_redirect = $this->match_redirect( $requested_path );

		if ( ! $match_redirect ) {
			return;
		}

		do_action( 'redirect_txt_redirect_hit', $match_redirect );

		// Use default status code if an invalid value is set.
		if ( ! isset( $this->get_valid_status_codes()[ $match_redirect['status'] ] ) ) {
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

new Redirect_Txt_Redirects();
