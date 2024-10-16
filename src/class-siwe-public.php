<?php
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

	private $parent;
	private $plugin_name;
	private $version;

	public function __construct($parent, $plugin_name, $version ) {
		$this->parent = $parent;
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->init_hooks();
	}

	public function init_hooks() {
		$this->parent->add_action( 'login_enqueue_scripts', $this, 'enqueue_styles' );
		$this->parent->add_action( 'wp_enqueue_scripts', $this, 'enqueue_styles' );
		$this->parent->add_action( 'login_enqueue_scripts', $this, 'enqueue_scripts' );
		$this->parent->add_action( 'login_footer', $this, 'add_signin_button' );
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.js', array(), $this->version, false );
	}

	/**
	 * Adds the sign-in button to the login form.
	 */
	public function add_signin_button() {
		if ( get_option( 'siwe_show_on_login' ) ) {
			echo wp_kses_post( self::get_signin_button());
		}
	}

	/**
	 * Builds the HTML for the sign in button.
	 *
	 * @return string
	 */
	public static function get_signin_button() {
		$array = ['google', 'microsoft'];
		$enabled = array_filter( $array, function( $vendor ) {
			return get_option( 'siwe_enable_' . $vendor );
		});
		if (empty($enabled)) {
			return '';
		}
		$result = '<div id="siwe-container">';
		foreach ($enabled as $vendor) {
			$result .= sprintf(
				'<a id="siwe-anchor" href="%s">
					<img src="%s" alt="Sign in with %s" />
				</a>',
				// Keep existing url query string intact.
				site_url( '?siwe_auth_redirect=' . $vendor . '&' ) . wp_kses_data (Sign_In_With_Essentials::value ($_SERVER, 'QUERY_STRING')),
				esc_url( plugin_dir_url( __FILE__ ) . 'assets/login-with-' . $vendor . '-neutral.png' ),
				ucfirst($vendor),
			);
		}
		$result .= '</div>';
		return $result;
	}
}
