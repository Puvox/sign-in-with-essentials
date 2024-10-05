<?php

namespace Sign_In_With_Essentials;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sign_In_With_Essentials
 * @author     Puvox Software <support@puvox.software>
 */
class Publics {

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

		$this->parent->add_action( 'login_footer', null, function () {
			if ( get_option( 'siwe_show_on_login' ) ) {
				echo wp_kses_post( $this->get_signin_buttons());
			}
		});
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/siwe-public.js', array(), $this->version, false );
	}


	/**
	 * Builds the HTML for the sign in button.
	 *
	 * @return string
	 */
	public function get_signin_buttons($use_admin_url = true) {
		$enabled = array_filter( SIWE_SUPPORTED_PROVIDERS, function( $vendor ) {
			return get_option( 'siwe_enable_' . $vendor );
		});
		if (empty($enabled)) {
			return '';
		}
		$result = '<div id="siwe-container">';
		foreach ($enabled as $provider) {
			$redir_to = Main::value($_GET, 'redirect_to', '');
			$used_url = $use_admin_url ?
				// Keep existing url query string intact.
				site_url( '?siwe_auth_redirect=' . $provider ). '&redirect_to=' . $redir_to // . wp_kses_data (Sign_In_With_Essentials::value ($_SERVER, 'QUERY_STRING'))
					:
				$this->parent->plugin_handlers->get_auth_url_callback($provider, [ 'after_login_redirect' => $redir_to, 'siwe_provider'=> $provider ]);
			$result .= sprintf(
				'<a class="siwe-anchor" id="siwe-anchor-'. $provider .'" href="%s">
					<img src="%s" alt="Sign in with %s" />%s
				</a>',
				$used_url,
				apply_filters('siwe_button_images', esc_url( plugin_dir_url( __FILE__ ) . 'assets/login-with-' . $provider . '-neutral.png' ), $provider),
				ucfirst($provider),
				($provider === 'apple' && get_option( 'siwe_apple_forbid_hiden_email' )) ? '<p id="apple-forbid-hidden-mail">'.__('"Hide My Email" not allowed on this website', 'sign-in-with-essentials').'</p>' : ''
			);
		}//  apply_filters('siwe_get_auth_url_callback', $provider, $state);
		$result .= '</div>';
		return $result;
	}

}
