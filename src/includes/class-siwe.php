<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/includes
 */

/**
 * The core plugin class.
 *
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

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Sign_In_With_Essentials_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

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
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// If WordPress is running in WP_CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			new Sign_In_With_Essentials_WPCLI();
		}

		add_filter( 'siwe_instance', function() { return $this; } );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Google_Sign_Up_Loader. Orchestrates the hooks of the plugin.
	 * - Google_Sign_Up_Admin. Defines all hooks for the admin area.
	 * - Google_Sign_Up_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-siwe-loader.php';

		/**
		 * The class responsible for registering custom CLI commands.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-siwe-wpcli.php';

		/**
		 * A helpful ultility class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-siwe-utility.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-siwe-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-siwe-public.php';

		/**
		 * Handles all the Google Authentication methods.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-siwe-googleauth.php';

		$this->loader = new Sign_In_With_Essentials_Loader();
		add_action( 'siwe_plugin_loader_inited', $this->loader );

		/**
		 * Loads theme template functions.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/template-functions.php';

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Google_Sign_Up_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );

	}

	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'sign-in-with-essentials',
			false,
			plugin_dir_path( SIWE_PLUGIN_FILE ) . '/languages/'
		);

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Sign_In_With_Essentials_Admin( $this->get_plugin_name(), $this->get_version() );
		if (get_option('siwe_expose_class_instance')) {
			$GLOBALS['SIGN_IN_WITH_ESSENTIALS_INSTANCE_ADMIN'] = $plugin_admin;
		}
		$this->loader->add_action( 'admin_init', $plugin_admin, 'settings_api_init' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'settings_menu_init' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'disallow_email_changes' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'process_settings_export' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'process_settings_import' );
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'add_connect_button_to_profile' );
		$this->loader->add_action( 'login_init', $plugin_admin, 'check_login_redirection', 888 );
		$this->loader->add_filter( 'get_avatar', $plugin_admin, 'slug_get_avatar', 10, 5 );

		if ( isset( $_POST['_siwe_account_nonce'] ) ) {
			$this->loader->add_action( 'admin_init', $plugin_admin, 'disconnect_account' );
		}

		if ( isset( $_GET['google_redirect'] ) ) {
			$this->loader->add_action( 'template_redirect', $plugin_admin, 'google_auth_redirect' );
		}

		// Handle Google's response before anything is rendered.
		$redir_url = siwe_default_url();
		$is_query = str_contains ($redir_url, '?' );
		$contains_domain = str_contains( $redir_url, '://' );

		if (
			// if it contains another domain, then we don't need to check
			!$contains_domain &&
			isset( $_GET['code'] ) &&
			(
				(   $is_query && isset( $_GET[ str_replace('?', '', $redir_url) ] ) )
					||
				( ! $is_query && str_starts_with( siwe_global_value ($_SERVER, 'REQUEST_URI'), $redir_url ) )
			)
		)
		{
			$this->loader->add_action( 'init', $plugin_admin, 'authenticate_user' );
		}

		$this->loader->add_filter( 'plugin_action_links_' . $this->plugin_name . '/' . $this->plugin_name . '.php', $plugin_admin, 'add_action_links' );

		// Check if domain restrictions have kept a user from logging in.
		if ( isset( $_GET['google_login'] ) ) {
			$this->loader->add_filter( 'login_message', $plugin_admin, 'allowed_domains_error' );
		}

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Sign_In_With_Essentials_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'login_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'login_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'login_form', $plugin_public, 'add_signin_button' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Sign_In_With_Essentials_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
