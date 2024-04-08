<?php
/**
 * Plugin assets functions.
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Txt Assets class.
 */
class Redirect_Txt_Assets {
	/**
	 * Redirect_Txt_Assets constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Loads the asset file for the given script or style.
	 * Returns a default if the asset file is not found.
	 *
	 * @param string $filepath The name of the file without the extension.
	 *
	 * @return array The asset file contents.
	 */
	public function get_asset_file( $filepath ) {
		$asset_path = redirect_txt()->plugin_path . $filepath . '.asset.php';

		if ( file_exists( $asset_path ) ) {
			return include $asset_path;
		}

		return [
			'dependencies' => [],
			'version'      => REDIRECT_TXT_VERSION,
		];
	}

	/**
	 * Enqueue admin pages assets.
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'tools_page_redirect-txt' !== $screen->id ) {
			return;
		}

		$asset_data = $this->get_asset_file( 'build/admin' );

		wp_enqueue_script(
			'redirect-txt-admin',
			redirect_txt()->plugin_url . 'build/admin.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_localize_script(
			'redirect-txt-admin',
			'redirectTxtAdminData',
			[
				'adminUrl' => admin_url(),
				'homeUrl'  => home_url(),
				'rules'    => get_option( 'redirect_txt_rules', '' ),
				'settings' => Redirect_Txt_Settings::get(),
				'logs'     => Redirect_Txt_Logs::get_logs(),
			]
		);

		wp_enqueue_style(
			'redirect-txt-admin',
			redirect_txt()->plugin_url . 'build/style-admin.css',
			[],
			$asset_data['version']
		);
	}
}

new Redirect_Txt_Assets();
