<?php
/**
 * Rest API functions
 *
 * @package @@plugin_name
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
			update_option( 'redirect_txt_rules', sanitize_textarea_field( $new_rules ) );
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

		if ( is_array( $new_settings ) ) {
			$current_settings = get_option( 'redirect_txt_settings', [] );
			update_option( 'redirect_txt_settings', array_merge( $current_settings, $new_settings ) );
		}

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
	 * @return mixed
	 */
	public function error( $code, $response, $true_error = false ) {
		if ( $true_error ) {
			return new WP_Error( $code, $response, [ 'status' => 401 ] );
		}

		return new WP_REST_Response(
			[
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			],
			401
		);
	}
}
new Redirect_Txt_Rest();
