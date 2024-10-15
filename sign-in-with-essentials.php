<?php
/**
 * @package           Sign_In_With_Essentials
 *
 * @wordpress-plugin
 * Plugin Name:       Sign In With Essentials
 * Plugin URI:        https://www.github.com/puvox/sign-in-with-essentials
 * Description:       Adds a "Sign in with Google" button to the login page, and allows users to sign up and login using Google.
 * Version:           1.0.1
 * Author:            Puvox Software
 * Author URI:        https://puvox.software
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sign-in-with-essentials
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define ( 'SIWE_PLUGIN_VERSION', '1.0.1' );

define ('SIWE_DEFAULT_REDIRECT_PATH', '?google_response');


/**
 * The core plugin class, that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/includes
 * @author     Puvox Software <support@puvox.software>
 */
class Sign_In_With_Essentials {

	protected $plugin_name;
	protected $version;
	protected $actions = [];
	protected $filters = [];

	/**
	 * MicrosoftAuth class
	 *
	 * @since 1.5.2
	 * @access private
	 * @var object
	 */
	protected $module_microsoft;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 *
	 * @param string $version The current version of the plugin.
	 */
	public function __construct( $version ) {

		$this->plugin_name = 'sign-in-with-essentials';
		$this->version     = $version;

		$this->load_dependencies();
		$this->set_locale();
		$this->load_classes();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}

	private function load_dependencies() {
		require_once __DIR__ . '/src/includes/class-siwe-utility.php';
		require_once __DIR__ . '/src/includes/class-siwe-wpcli.php';
		require_once __DIR__ . '/src/includes/class-module-google.php';
		require_once __DIR__ . '/src/includes/class-module-microsoft.php';
		require_once __DIR__ . '/src/class-siwe-admin.php';
		require_once __DIR__ . '/src/class-siwe-public.php';
	}

	private function load_classes() {
		$plugin_admin = new Sign_In_With_Essentials_Admin( $this, $this->get_plugin_name(), $this->get_version() );
		$plugin_public = new Sign_In_With_Essentials_Public( $this, $this->get_plugin_name(), $this->get_version() );
		if (get_option('siwe_expose_class_instance')) {
			do_action('SIGN_IN_WITH_ESSENTIALS_INSTANCE_ADMIN', $plugin_admin);
			do_action('SIGN_IN_WITH_ESSENTIALS_INSTANCE_PUBLIC', $plugin_public);
		}
		// If WordPress is running in WP_CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			new Sign_In_With_Essentials_WPCLI();
		}
		$this->module_microsoft = new SIWE_MicrosoftAuth( $this );
	}


	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook             The name of the WordPress action that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the action is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         Optional. he priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}



	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run_hooks() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}

	/**
	 * Define the locale for this plugin for internationalization, in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$this->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'sign-in-with-essentials',
			false,
			plugin_dir_path( dirname( __FILE__ ) )  . '/languages/'
		);
	}

	public function siwe_vendor_autoload() {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	public static function siwe_redirect_back_url() {
		return get_option( 'siwe_google_custom_redir_url', SIWE_DEFAULT_REDIRECT_PATH);
	}

	public static function siwe_array_value( $container, $name, $default = null) {
		return array_key_exists ($name, $container) ? $container[$name] : $default;
	}

}



(new Sign_In_With_Essentials(SIWE_PLUGIN_VERSION))->run_hooks();


/**
 * Get the button html as a string.
 *
 * @return string
 */
function siwe_get_button() {
	return Sign_In_With_Essentials_Public::get_signin_button();
}

/**
 * Get the Google authentication URL.
 *
 * @since 1.8.0
 *
 * @param array $state Nonce to verify response from Google.
 *
 * @return string
 */
// function siwe_get_google_auth_url( $state = array() ) {
// 	$client_id = get_option( 'siwe_google_client_id' );

// 	// Bail if there is no client ID.
// 	if ( ! $client_id ) {
// 		return '';
// 	}

// 	return ( new SIWE_GoogleAuth( $client_id ) )->get_google_auth_url( $state );
// }


