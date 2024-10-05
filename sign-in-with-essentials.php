<?php
/**
 * @package           Sign_In_With_Essentials
 *
 * @wordpress-plugin
 * Plugin Name:       Sign In With Socials (Google, Apple, Microsoft)
 * Plugin URI:        https://www.github.com/puvox/sign-in-with-essentials
 * Description:       Adds a "Sign in with" Google/Apple/Microsoft functionality to your WordPress site. View <a href="https://wordpress.org/plugins/sign-in-with-essentials">Readme</a>.
 * Version:           1.4.0
 * Author:            Puvox Software
 * Author URI:        https://puvox.software
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sign-in-with-essentials
 * Domain Path:       /languages
 */


namespace {

	// If this file is called directly, abort.
	if ( ! defined( 'WPINC' ) ) {
		die;
	}

	define ( 'SIWE_PLUGIN_VERSION', '1.0.1' );
	define ('SIWE_DEFAULT_REDIRECT_PATH', '_AUTH_RESPONSE_SIWE_');
	define ('SIWE_SUPPORTED_PROVIDERS', ['google', 'microsoft', 'apple']);

	/**
	 * Get the authentication URL for provider
	 *
	 * @param string $provider Provider name (google, apple, microsoft)
	 * @param array $state Nonce to verify response from Google.
	 *
	 * @return string
	 */

	function siwe_get_auth_url($provider, $state = []) {
		return apply_filters('siwe_get_auth_url_callback', $provider, $state);
	}

	/**
	 * Get the login buttons html
	 *
	 * @return string
	 */
	function siwe_get_buttons() {
		return apply_filters('siwe_get_signin_buttons');
	}



	/**
	 * To manually/programatically authenticate users, you can use this function (this works on the premise that in $state there was set 'siwe_provider' key with a provider name)
	 *
	 * @param string $code manually provided code (eg. from $_GET['code'])
	 * @param string $state manually provided state (eg. from $_GET['state'])
	 * @param string $error manually provided error (eg. from $_GET['error'])
	 *
	 * @return bool
	 */
	function siwe_authenticate_user($code, $state, $error = null) {
		return apply_filters('siwe_authenticate_user_callback', $code, $state, $error);
	}

}



namespace Sign_In_With_Essentials {

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
	 * @author     Puvox Software <support@puvox.software>
	 */
	class Main {

		protected $plugin_name;
		protected $version;
		protected $actions = [];
		protected $filters = [];

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
			foreach (SIWE_SUPPORTED_PROVIDERS as $provider) {
				require_once __DIR__ . "/src/includes/class-module-$provider.php";
			}
			require_once __DIR__ . '/src/class-siwe-admin.php';
			require_once __DIR__ . '/src/class-siwe-public.php';
			require_once __DIR__ . '/src/includes/class-siwe-handlers.php';
		}

		public $plugin_handlers;

		private function load_classes() {
			$this->plugin_handlers = new Handlers( $this, $this->get_plugin_name(), $this->get_version() );
			$plugin_admin = new Admin( $this, $this->get_plugin_name(), $this->get_version() );
			$plugin_public = new Publics( $this, $this->get_plugin_name(), $this->get_version() );
			// If WordPress is running in WP_CLI.
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				new WPCLI();
			}
			// add public hook
			add_filter('siwe_get_auth_url_callback', [$this->plugin_handlers, 'get_auth_url_callback'], 5, 2);
			add_filter('siwe_authenticate_user_callback', [$this->plugin_handlers, 'authenticate_user_callback'], 5, 3);
			add_filter('siwe_get_signin_buttons', [$plugin_public, 'get_signin_buttons'], 5, 3);
		}

		public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
			$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
		}

		public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
			$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
		}

		/**
		 * Add a new action or filter to the collection to be registered with WordPress.
		 *
		 * @since    1.0.0
		 * @param    string $hook             The name of the WordPress action that is being registered.
		 * @param    object $component        A reference to the instance of the object on which the action is defined.
		 * @param    string $callback         The name of the function definition on the $component.
		 * @param    int    $priority         Optional. he priority at which the function should be fired. Default is 10.
		 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
		 */
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

		public function run_hooks() {
			foreach ( $this->filters as $hook ) {
				$callback = !empty($hook['component']) ? [ $hook['component'], $hook['callback'] ] : $hook['callback'];
				add_filter( $hook['hook'], $callback, $hook['priority'], $hook['accepted_args'] );
			}
			foreach ( $this->actions as $hook ) {
				$callback = !empty($hook['component']) ? [ $hook['component'], $hook['callback'] ] : $hook['callback'];
				add_action( $hook['hook'], $callback, $hook['priority'], $hook['accepted_args'] );
			}
		}

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

		public static function value($container, $name, $default = null) {
			if ($container === null) {
				throw new \Exception('Container is null');
			}
			if (is_array($container)) {
				return array_key_exists ($name, $container) ? $container[$name] : $default;
			} else {
				return property_exists ($container, $name) ? $container->$name : $default;
			}
		}

		public static function userFormat ($provider = null, $user_oid = null, $email = null, $first = null, $last = null, $full_name = null, $raw_data = null) {
			return  [
				'provider' => $provider,
				'email' => $email,
				'user_oid' => $user_oid,
				'first_name' => $first,
				'last_name' => $last,
				'full_name' => $full_name,
				'raw' => $raw_data,
			];
		}

		public static function siwe_redirect_back_url() {
			return get_option( 'siwe_custom_redir_url', '/'. SIWE_DEFAULT_REDIRECT_PATH);
		}

		public static function siwe_redirect_back_url_with_domain() {
			$custom_redir_url = self::siwe_redirect_back_url();
			$final_redir_url = str_contains( $custom_redir_url, '://' ) || str_starts_with($custom_redir_url, '//') ? $custom_redir_url : site_url( $custom_redir_url );
			return $final_redir_url;
		}

	}

	(new Main(SIWE_PLUGIN_VERSION))->run_hooks();
}
