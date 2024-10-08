<?php
/**
 * Defines the SIWE_GoogleAuth class.
 *
 * @since      1.5.2
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/includes
 */

/**
 * The SIWE_GoogleAuth class.
 *
 * Handles the entire Google Authentication process.
 */
class SIWE_GoogleAuth {

	/**
	 * The base API url.
	 *
	 * @since 1.5.2
	 * @var string
	 */
	public $base_url = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * The client ID.
	 *
	 * @since 1.5.2
	 * @var string
	 */
	public $client_id;

	/**
	 * The scopes needed to access user information
	 *
	 * @since 1.5.2
	 * @var array
	 */
	public $scopes;

	/**
	 * The URL to redirect back to after authentication.
	 *
	 * @since 1.5.2
	 * @var string
	 */
	public $redirect_uri;

	/**
	 * Set up the class.
	 *
	 * @since 1.5.2
	 *
	 * @param string $client_id The Client ID used to authenticate the request.
	 */
	public function __construct( $client_id ) {
		$this->client_id = $client_id;

		$scopes[]     = 'https://www.googleapis.com/auth/userinfo.email';
		$scopes[]     = 'https://www.googleapis.com/auth/userinfo.profile';
		$this->scopes = urlencode( implode( ' ', $scopes ) );

		$custom_redir_url = siwe_default_url();

		$final_redir_url = str_contains( $custom_redir_url, '://' ) || str_starts_with($custom_redir_url, '//') ? $custom_redir_url : site_url( $custom_redir_url );

		$this->redirect_uri = $final_redir_url;
	}

	/**
	 * Get the URL for sending user to Google for authentication.
	 *
	 * @since 1.5.2
	 *
	 * @param string $state Nonce to pass to Google to verify return of the original request.
	 */
	public function get_google_auth_url( $state ) {
		return $this->google_auth_url( $state );
	}

	/**
	 * Builds out the Google redirect URL
	 *
	 * @since    1.5.2
	 *
	 * @param string $state Nonce to pass to Google to verify return of the original request.
	 */
	private function google_auth_url( $state ) {
		$scopes = apply_filters( 'siwe_scopes', $this->scopes );

		$redirect_uri  = urlencode( $this->redirect_uri );
		$encoded_state = base64_encode( wp_json_encode( $state ) );
		return $this->base_url . '?scope=' . $scopes . '&redirect_uri=' . $redirect_uri . '&response_type=code&client_id=' . $this->client_id . '&state=' . $encoded_state . '&prompt=select_account';
	}
}
