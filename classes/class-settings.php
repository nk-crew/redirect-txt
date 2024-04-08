<?php
/**
 * Plugin settings.
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Txt_Settings class.
 */
class Redirect_Txt_Settings {
	/**
	 * Get default options.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return [
			'404_logs'      => 0,
			'redirect_logs' => 0,
		];
	}

	/**
	 * Get settings.
	 */
	public static function get() {
		return array_merge(
			self::get_defaults(),
			get_option( 'redirect_txt_settings', [] ) ?? []
		);
	}

	/**
	 * Update settings.
	 *
	 * @param array $new_settings - new settings to save.
	 */
	public static function update( $new_settings ) {
		if ( ! is_array( $new_settings ) ) {
			return;
		}

		$pending_new_settings = [];
		$new_settings         = array_merge( self::get(), $new_settings );
		$defaults             = self::get_defaults();

		// Clean new settings array:
		// - save only supported settings.
		// - remove setting if it is a default.
		foreach ( $defaults as $name => $val ) {
			if ( $new_settings[ $name ] !== $val ) {
				$pending_new_settings[ $name ] = $new_settings[ $name ];
			}
		}

		update_option( 'redirect_txt_settings', apply_filters( 'redirect_txt_update_settings', $pending_new_settings ), false );
	}
}
