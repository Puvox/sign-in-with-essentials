<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/public
 * @author     Puvox Software <support@puvox.software>
 */
class Sign_In_With_Essentials_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Google_Sign_Up_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Google_Sign_Up_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Google_Sign_Up_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Google_Sign_Up_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Adds the sign-in button to the login form.
	 */
	public function add_signin_button() {

		if ( get_option( 'siwe_show_on_login' ) ) {

			siwe_button();
		}
	}

	/**
	 * Builds the HTML for the sign in button.
	 *
	 * @return string
	 */
	public static function get_signin_button() {
		return sprintf(
			'<div id="siwe-container">
				<a id="siwe-anchor" href="%s">
					<img src="%s" alt="Sign in with Google" />
				</a>
			</div>',
			// Keep existing url query string intact.
			site_url( '?google_redirect&' ) . wp_kses_data (siwe_global_value ($_SERVER, 'QUERY_STRING')),
			esc_url( plugin_dir_url( __FILE__ ) . 'assets/web_light_rd_SI@1x.png' )
		);
	}

}
