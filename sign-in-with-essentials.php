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

/**
 * The code that runs during plugin activation.
 */
function sign_in_with_essentials_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'src/includes/class-siwe-activation-hooks.php';
	Sign_In_With_Essentials_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function sign_in_with_essentials_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'src/includes/class-siwe-activation-hooks.php';
	Sign_In_With_Essentials_Activator::deactivate();
}

register_activation_hook( __FILE__, 'sign_in_with_essentials_activate' );
register_deactivation_hook( __FILE__, 'sign_in_with_essentials_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'src/includes/class-siwe.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function sign_in_with_essentials_run() {

	define( 'SIWE_PLUGIN_FILE', basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );
	$plugin = new Sign_In_With_Essentials( '1.8.0' );
	$plugin->run();
	if (get_option('siwe_expose_class_instance', true)) {
		$GLOBALS['SIGN_IN_WITH_ESSENTIALS_INSTANCE_PUBLIC'] = $plugin;
	}

}
sign_in_with_essentials_run();


function siwe_default_url() {
	return get_option( 'siwe_google_custom_redir_url', '?google_response' );
}

function siwe_global_value( $container, $name) {
	if ( ! array_key_exists ($name, $container) ) {
		return '';
	}
	$value = $container[$name];
	if ( $value === '' || $value === null || ( is_array( $value ) && count( $value ) === 0 ) ) {
		return $container[$name];
	}
	return esc_attr( wp_unslash( $value ) );
}

function siwe_array_value( $container, $name, $default = null) {
	return array_key_exists ($name, $container) ? $container[$name] : $default;
}
