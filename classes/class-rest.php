<?php
/**
 * Rest API functions
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Redirect_Txt_Rest
 */
class Redirect_Txt_Rest extends WP_REST_Controller {
	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'redirect-txt/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Redirect_Txt_Rest constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Update Rules.
		register_rest_route(
			$namespace,
			'/update_rules/',
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ $this, 'update_rules' ],
				'permission_callback' => [ $this, 'update_rules_permission' ],
			]
		);

		// Update Settings.
		register_rest_route(
			$namespace,
			'/update_settings/',
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'update_settings_permission' ],
			]
		);
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_rules_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'User don\'t have permissions to change options.', 'redirect-txt' ), true );
		}

		return true;
	}

	/**
	 * Update Rules.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_rules( WP_REST_Request $req ) {
		$new_rules = $req->get_param( 'rules' );

		if ( isset( $new_rules ) ) {
			if ( ! is_string( $new_rules ) ) {
				return $this->error( 'invalid_rules', __( 'Rules must be a string.', 'redirect-txt' ), false, 400 );
			}

			// Rules are matched as written: sanitize_textarea_field would strip `%C3%A9` and `(?<name>)`.
			$new_rules = str_replace( "\0", '', wp_check_invalid_utf8( $new_rules, true ) );

			$invalid = Redirect_Txt_Redirects::find_invalid_regex_line( $new_rules );

			if ( $invalid ) {
				return $this->error(
					'invalid_regex',
					sprintf(
						// translators: %1$d - line number, %2$s - rule line.
						__( 'Line %1$d has an invalid regular expression: %2$s', 'redirect-txt' ),
						$invalid['number'],
						$invalid['line']
					),
					false,
					400
				);
			}

			update_option( 'redirect_txt_rules', $new_rules, false );
		}

		return $this->success( true );
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_settings_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'User don\'t have permissions to change options.', 'redirect-txt' ), true );
		}

		return true;
	}

	/**
	 * Update Settings.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_settings( WP_REST_Request $req ) {
		$new_settings = $req->get_param( 'settings' );

		Redirect_Txt_Settings::update( $new_settings );

		return $this->success( true );
	}

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success( $response ) {
		return new WP_REST_Response(
			[
				'success'  => true,
				'response' => $response,
			],
			200
		);
	}

	/**
	 * Error rest.
	 *
	 * @param mixed   $code       error code.
	 * @param mixed   $response   response data.
	 * @param boolean $true_error use true error response to stop the code processing.
	 * @param int     $status     HTTP status.
	 * @return mixed
	 */
	public function error( $code, $response, $true_error = false, $status = 401 ) {
		if ( $true_error ) {
			return new WP_Error( $code, $response, [ 'status' => $status ] );
		}

		return new WP_REST_Response(
			[
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			],
			$status
		);
	}
}
new Redirect_Txt_Rest();
