<?php
/**
 * Plugin admin functions.
 *
 * @package redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect_Txt Admin class.
 */
class Redirect_Txt_Admin {
	/**
	 * Redirect_Txt_Admin constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
	}

	/**
	 * Register admin menu.
	 *
	 * Add new Redirect.txt Settings admin menu.
	 */
	public function register_admin_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_submenu_page(
			'tools.php',
			esc_html__( 'Redirect.txt', 'redirect-txt' ),
			esc_html__( 'Redirect.txt', 'redirect-txt' ),
			'manage_options',
			'redirect-txt',
			[ $this, 'print_admin_page' ]
		);
	}

	/**
	 * Print admin page.
	 */
	public function print_admin_page() {
		?>
		<div class="redirect-txt-admin-root"></div>
		<?php
	}

	/**
	 * Add page class to body.
	 *
	 * @param string $classes - body classes.
	 */
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();

		if ( 'tools_page_redirect-txt' !== $screen->id ) {
			return $classes;
		}

		$classes .= ' redirect-txt-admin-page';

		// Sub page.
		$page_name = 'rules';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['sub_page'] ) && $_GET['sub_page'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$page_name = esc_attr( sanitize_text_field( $_GET['sub_page'] ) );
		}

		$classes .= ' redirect-txt-admin-page-' . $page_name;

		return $classes;
	}
}

new Redirect_Txt_Admin();
