<?php
/**
 * Plugin Name:       Redirect.txt
 * Description:       A simple yet powerful redirection plugin. Provide a simple list of URLs and their destinations, and Redirect.txt will take care of the rest.
 * Requires at least: 6.2
 * Requires PHP:      7.2
 * Version:           0.1.0
 * Author:            Redirect.txt Team
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       redirect-txt
 *
 * @package           redirect-txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'REDIRECT_TXT_VERSION' ) ) {
	define( 'REDIRECT_TXT_VERSION', '0.1.1' );
}

/**
 * Redirect_Txt Class
 */
class Redirect_Txt {
	/**
	 * The single class instance.
	 *
	 * @var $instance
	 */
	private static $instance = null;

	/**
	 * Main Instance
	 * Ensures only one instance of this class exists in memory at any one time.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Name of the plugin
	 *
	 * @var $plugin_name
	 */
	public $plugin_name;

	/**
	 * Basename of plugin main file
	 *
	 * @var $plugin_basename
	 */
	public $plugin_basename;

	/**
	 * Path to the plugin directory
	 *
	 * @var $plugin_path
	 */
	public $plugin_path;

	/**
	 * URL to the plugin directory
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;

	/**
	 * Redirect_Txt constructor.
	 */
	public function __construct() {
		/* We do nothing here! */
	}

	/**
	 * Init options
	 */
	public function init() {
		$this->plugin_name     = esc_html__( 'Redirect.txt', 'redirect-txt' );
		$this->plugin_basename = plugin_basename( __FILE__ );
		$this->plugin_path     = plugin_dir_path( __FILE__ );
		$this->plugin_url      = plugin_dir_url( __FILE__ );

		// load textdomain.
		load_plugin_textdomain( 'redirect-txt', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// include helper files.
		$this->include_dependencies();
	}

	/**
	 * Activation Hook
	 */
	public function activation_hook() {
		// Nothing here yet.
	}

	/**
	 * Deactivation Hook
	 */
	public function deactivation_hook() {
		// Nothing here yet.
	}

	/**
	 * Include dependencies
	 */
	private function include_dependencies() {
		require_once $this->plugin_path . 'classes/class-admin.php';
		require_once $this->plugin_path . 'classes/class-assets.php';
		require_once $this->plugin_path . 'classes/class-rest.php';
		require_once $this->plugin_path . 'classes/class-redirects.php';
		require_once $this->plugin_path . 'classes/class-logs.php';
	}
}

/**
 * Function works with the Redirect_Txt class instance
 *
 * @return object Redirect_Txt
 */
function redirect_txt() {
	return Redirect_Txt::instance();
}
add_action( 'plugins_loaded', 'redirect_txt' );

register_activation_hook( __FILE__, [ redirect_txt(), 'activation_hook' ] );
register_deactivation_hook( __FILE__, [ redirect_txt(), 'deactivation_hook' ] );
