<?php
/**
 * An assortment of functions for themes to utilize.
 *
 * @since      1.6.0
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/includes
 */

/**
 * Output the sign in button.
 *
 * @return void
 */
function siwe_button() {
	echo wp_kses_post(siwe_get_button());
}

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
function siwe_get_google_auth_url( $state = array() ) {
	$client_id = get_option( 'siwe_google_client_id' );

	// Bail if there is no client ID.
	if ( ! $client_id ) {
		return '';
	}

	return ( new SIWE_GoogleAuth( $client_id ) )->get_google_auth_url( $state );
}
