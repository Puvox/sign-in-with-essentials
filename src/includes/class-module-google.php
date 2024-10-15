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

	public $base_url = 'https://accounts.google.com/o/oauth2/v2/auth';

	public $client_id;

	public $scopes;

	public $redirect_uri;

	public function __construct( $client_id ) {
		$this->client_id = $client_id;

		$scopes[]     = 'https://www.googleapis.com/auth/userinfo.email';
		$scopes[]     = 'https://www.googleapis.com/auth/userinfo.profile';
		$this->scopes = urlencode( implode( ' ', $scopes ) );

		$custom_redir_url = Sign_In_With_Essentials::siwe_redirect_back_url();

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


	/**
	 * Get the user's info.
	 *
	 * @since 1.2.0
	 *
	 * @param string $token The user's token for authentication.
	 */
	protected function get_user_by_token( $token ) {

		if ( ! $token ) {
			return;
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		);

		$result = wp_remote_request( 'https://www.googleapis.com/userinfo/v2/me', $args );

		$json = json_decode( wp_remote_retrieve_body( $result ) );
		//
		// 	{
		// 		public $id             =>  "123456789123456789123"
		// 		public $email          => "example@gmail.com"
		// 		public $verified_email => bool(true)
		// 		public $name           => string(8) "Firstname Lastname"
		// 		public $given_name     => string(2) "Firstname"
		// 		public $family_name    => string(5) "Lastname"
		// 		public $picture        => string(98) "https://lh3.google-user-content.com/a/xyzxyzxyzxyz=s96-c"
		// 		public $locale         => string(2) "en"
		// 	}
		//
		return $json;
	}
}
