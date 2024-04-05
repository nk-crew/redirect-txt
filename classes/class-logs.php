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
	/**
	 * Redirect_Txt_Logs constructor.
	 */
	public function __construct() {
		if ( is_admin() || defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'maybe_log_404' ], 1 );
		add_action( 'redirect_txt_redirect_hit', [ $this, 'log_redirect' ], 10, 2 );
	}

	/**
	 * Store all logs (redirect and 404)
	 *
	 * @param array $data - data for log.
	 */
	public function store_log( $data ) {
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
				// phpcs:ignore
				'user_agent' => @strip_tags( $_SERVER['HTTP_USER_AGENT'] ),
				// phpcs:ignore
				'referrer'   => @strip_tags( $_SERVER['HTTP_REFERER'] ),
			),
			$data
		);

		$logs = get_option( 'redirect_txt_logs', array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift( $logs, $new_log );

		update_option( 'redirect_txt_logs', $logs );
	}

	/**
	 * Detect 404 and save logs.
	 */
	public function maybe_log_404() {
		if ( ! is_404() ) {
			return;
		}

		$this->store_log(
			array(
				// phpcs:ignore
				'url_from' => @strip_tags( $_SERVER['REQUEST_URI'] ),
				'status'   => 404,
			)
		);
	}

	/**
	 * Add logs for successful redirect.
	 *
	 * @param array $redirect - redirect data.
	 */
	public function log_redirect( $redirect ) {
		$this->store_log(
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

new Redirect_Txt_Logs();
