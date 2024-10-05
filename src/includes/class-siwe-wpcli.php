<?php

/**
 * Register all wpcli commands for the plugin.
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/includes
 * @author     Puvox Software <support@puvox.software>
 */
class Sign_In_With_Essentials_WPCLI {


	public function args_map () {
		return [
			'client_id' => [
				'type' => 'string',
				'allow_empty' => true,
				'scope'=> 'google',
			],
			'client_secret' => [
				'type' => 'string',
				'allow_empty' => true,
				'scope'=> 'google',
			],
			'email_sanitization' => [
				'type' => 'bool',
				'scope'=> 'google',
			],
			'allowed_domains' => [
				'type' => 'string',
				'allow_empty' => true,
				'scope'=> 'google',
			],
			'custom_redir_url' => [
				'type' => 'string',
				'allow_empty' => true,
				'sanitizer' => 'sanitize_text_field',
				'scope'=> 'google',
			],
			'save_userinfo' => [
				'type' => 'bool',
				'scope'=> 'google',
			],
			'use_profile_pic' => [
				'type' => 'bool',
				'scope'=> 'google',
			],
			'user_default_role' => [
				'type' => 'string',
				'allow_empty' => false,
				'allowed_values' => array_keys( get_editable_roles() )
			],
			'allow_registration_even_if_disabled' => [
				'type' => 'bool',
			],
			'expose_class_instance' => [
				'type' => 'bool',
			],
			'show_on_login' => [
				'type' => 'bool',
			],
			'allow_mail_change' => [
				'type' => 'bool',
			],
			'show_unlink_in_profile' => [
				'type' => 'bool',
			],
			'disable_login_page' => [
				'type' => 'bool',
			],
		];
	}

	/**
	 * Allows updating of Sign In With Essentials's settings
	 *
	 * ## OPTIONS
	 *
	 * example:
	 *     wp siwe settings --whatever=myvalue
	 *
	 *
	 * [--client_id=<client_id>]
	 * : Your Oauth Client ID from console.developers.google.com
	 *
	 * [--client_secret=<client_secret>]
	 * : Your Oauth Client Secret from console.developers.google.com
	 *
	 * [--user_default_role=<role>]
	 * : The role new users should have.
	 *
	 * [--allowed_domains=<domains>]
	 * : comma separated domains list
	 *
	 * [--save_userinfo=<1|0>]
	 * : Save some fields provided from google login to user meta.
	 *
	 * [--custom_redir_url=<url>]
	 * : You can set custom redirect back url
	 *
	 * [--expose_class_instance=<1|0>]
	 * : Expose SIWE class instance to global $GLOBALS
	 *
	 * [--email_sanitization=<1|0>]
	 * : Sanitize emails (a+b.c@gmail.com -> abc@gmail.com) to unique google account, to avoid duplicate/spammy aliases of gmail.
	 *
	 * [--allow_registration_even_if_disabled=<1|0>]
	 * : Allow registration through social sign in, even if site has disabled registrations.
	 *
	 * [--use_profile_pic=<1|0>]
	 * : Allow registration through social sign in, even if site has disabled registrations.
	 *
	 * [--show_on_login=<1|0>]
	 * : Show the "Sign In With XYZ" button on the login form.
	 *
	 * [--allow_mail_change=<1|0>]
	 * : Allow regular users to change their emails by themselves.
	 *
	 * [--show_unlink_in_profile=<1|0>]
	 * : Show the Unlink button for users in their profile
	 *
	 * [--disable_login_page=<1|0>]
	 * : Disable user & password fields on login page, instead suggest users to login with social sign in.
	 *
	 *
	 * @when after_wp_load
	 *
	 * @param array $assoc_args An associative array of settings and values to update.
	 */
	public function settings( $args = array(), $assoc_args = array() ) {

		// Quit if no arguments are provided.
		if ( empty( $assoc_args ) ) {
			WP_CLI::warning( "Empty input" );
			return;
		}

		// Sanitize everything.
		$sanitized_args = $this->sanitize_args( $assoc_args );
		$args_map = $this->args_map();

		foreach ( $sanitized_args as $key => $value ) {
			if ( ! array_key_exists( $key,  $args_map) ) {
				WP_CLI::error( 'Invalid setting: ' . $key );
			}

			$arg_opts = $args_map[ $key ];
			$allow_empty = siwe_array_value ($arg_opts, 'allow_empty', true);

			if ( empty ($value) &&  $allow_empty === false ) {
				WP_CLI::error( "Empty value not allowed for: $key" );
			}

			$allowed_values = siwe_array_value ($arg_opts, 'allowed_values', null);
			if ($allowed_values !== null) {
				if ( ! in_array( $value, $allowed_values ) ) {
					WP_CLI::error( "$key value should be among: " . implode( ', ', $allowed_values ) );
				}
			}

			// update value
			if ($arg_opts['type'] === 'bool') {
				assert( $value === '1' || $value === '0' , 'value should be 1 or 0' );
				$value = boolval( $value );
			}

			$sanitizer = siwe_array_value ($arg_opts, 'sanitizer', null);
			if ($sanitizer !== null) {
				$new_value = call_user_func ($sanitizer, $value);
				if ($new_value !== $value) {
					WP_CLI::error( "Invalid value for $key, input value: $value does not match sanitized value: $new_value");
				}
				$value = $new_value;
			}

			$scope = siwe_array_value ($arg_opts, 'scope', null);
			$prefix = $scope  ? 'siwe_google_' : 'siwe_';
			$result = update_option( $prefix . $key, $value );

			if ( ! $result ) {
				WP_CLI::warning( "Skipping $key - Setting already matches" );
			} else {
				WP_CLI::success( "Updated $key");
			}
		}

	}

	public function __construct() {
		WP_CLI::add_command( 'siwe', $this );
	}


	/**	 * Sanitize command arguments
	 *
	 * @since 1.2.2
	 *
	 * @param array $args An array of arguments to sanitize.
	 */
	private function sanitize_args( $args = array() ) {
		$sanitized_assoc_args = array();

		// Just return if $args is empty.
		if ( empty( $args ) ) {
			return;
		}

		foreach ( $args as $key => $value ) {
			$sanitized_assoc_args[ $key ] = sanitize_text_field( $value );
		}

		return $sanitized_assoc_args;
	}

}
