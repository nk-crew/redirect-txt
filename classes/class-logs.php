<?php
/**
 * Work with logs.
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Txt_Logs class.
 */
class Redirect_Txt_Logs {
	const DELETE_HOOK = 'redirect_txt_flush_logs';

	/**
	 * Init.
	 */
	public static function init() {
		add_filter(
			'redirect_txt_update_settings',
			function ( $settings ) {
				Redirect_Txt_Logs::flush_logs_schedule();

				return $settings;
			}
		);
		add_action( self::DELETE_HOOK, 'Redirect_Txt_Logs::flush_logs' );
		add_action( 'admin_init', 'Redirect_Txt_Logs::flush_logs_schedule' );

		if ( is_admin() || defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'template_redirect', 'Redirect_Txt_Logs::maybe_log_404', 1 );
		add_action( 'redirect_txt_redirect_hit', 'Redirect_Txt_Logs::log_redirect', 10, 2 );
	}

	/**
	 * Flush logs.
	 */
	public static function flush_logs() {
		$settings = Redirect_Txt_Settings::get();

		if ( $settings['redirect_logs'] >= 0 || $settings['404_logs'] >= 0 ) {
			$logs = self::get_logs();
			$time = time();

			$new_logs = array_filter(
				$logs,
				function( $log ) use ( &$settings, &$time ) {
					if ( 404 === $log['status'] && $settings['404_logs'] >= 0 ) {
						return $log['timestamp'] + DAY_IN_SECONDS * $settings['404_logs'] > $time;
					} elseif ( $settings['redirect_logs'] >= 0 ) {
						return $log['timestamp'] + DAY_IN_SECONDS * $settings['redirect_logs'] > $time;
					}

					return true;
				}
			);

			if ( count( $logs ) !== count( $new_logs ) ) {
				self::update_logs( $new_logs );
			}
		}
	}

	/**
	 * Schedule flush logs.
	 */
	public static function flush_logs_schedule() {
		$freq     = 'daily';
		$settings = Redirect_Txt_Settings::get();

		if ( $settings['redirect_logs'] >= 0 || $settings['404_logs'] >= 0 ) {
			if ( ! wp_next_scheduled( self::DELETE_HOOK ) ) {
				wp_schedule_event( time(), $freq, self::DELETE_HOOK );
			}
		} else {
			wp_clear_scheduled_hook( self::DELETE_HOOK );
		}
	}

	/**
	 * Get logs.
	 *
	 * @return array
	 */
	public static function get_logs() {
		return get_option( 'redirect_txt_logs', array() );
	}

	/**
	 * Update logs.
	 *
	 * @param array $logs - logs to save.
	 */
	public static function update_logs( $logs ) {
		update_option( 'redirect_txt_logs', $logs, false );
	}

	/**
	 * Store all logs (redirect and 404)
	 *
	 * @param array $data - data for log.
	 */
	public static function store_log( $data ) {
		$new_log = array_merge(
			// Defaults.
			array(
				'url_from'   => null,
				'url_to'     => null,
				'status'     => null,
				'from_rule'  => null,
				'from_type'  => null,
				'to_rule'    => null,
				'to_type'    => null,
				// phpcs:ignore
				'timestamp'  => current_time( 'timestamp' ),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
				'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( $_SERVER['HTTP_REFERER'] ) : '',
			),
			$data
		);

		$logs = self::get_logs();

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift( $logs, $new_log );

		self::update_logs( $logs );
	}

	/**
	 * Detect 404 and save logs.
	 */
	public static function maybe_log_404() {
		if ( ! is_404() ) {
			return;
		}

		$settings = Redirect_Txt_Settings::get();

		// Skip if logs for 404 disabled in settings.
		if ( ! $settings['404_logs'] ) {
			return;
		}

		self::store_log(
			array(
				// phpcs:ignore
				'url_from' => isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
				'status'   => 404,
			)
		);
	}

	/**
	 * Add logs for successful redirect.
	 *
	 * @param array $redirect - redirect data.
	 */
	public static function log_redirect( $redirect ) {
		$settings = Redirect_Txt_Settings::get();

		// Skip if logs for redirects disabled in settings.
		if ( ! $settings['redirect_logs'] ) {
			return;
		}

		self::store_log(
			array(
				'url_from'  => $redirect['from'],
				'url_to'    => $redirect['to'],
				'status'    => $redirect['status'],
				'from_rule' => $redirect['from_rule'],
				'from_type' => $redirect['from_type'],
				'to_rule'   => $redirect['to_rule'],
				'to_type'   => $redirect['to_type'],
			)
		);
	}
}

Redirect_Txt_Logs::init();
