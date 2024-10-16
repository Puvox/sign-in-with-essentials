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

	private $app;

	public $base_url = 'https://accounts.google.com/o/oauth2/v2/auth';

	public $scopes = [
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile', // given_name (eg. Elvis), family_name (eg. Presley), name (eg. Elvis Presley)
	];

	public $redirect_uri;

	public function __construct($app) {
		$this->app = $app;
		$this->redirect_uri = Sign_In_With_Essentials::siwe_redirect_back_url_with_domain();
	}
	/**
	 * Get the URL for sending user to Google for authentication.
	 *
	 * @since 1.5.2
	 *
	 * @param string $state Nonce to pass to Google to verify return of the original request.
	 */
	public function get_auth_url( $state ) {
		$scopes = urlencode( implode( ' ', apply_filters( 'siwe_scopes', $this->scopes, 'google' ) ) );
		$redirect_uri  = urlencode( $this->redirect_uri ); // already filtered outside
		$encoded_state = base64_encode( wp_json_encode( $state ) );
		return $this->base_url . '?scope=' . $scopes . '&redirect_uri=' . $redirect_uri . '&response_type=code&client_id=' . get_option( 'siwe_google_client_id' ) . '&state=' . $encoded_state . '&prompt=select_account';
	}


	/**
	 * Sets the access_token using the response code.
	 *
	 * @since 1.0.0
	 * @param string $code The code provided by Google's redirect.
	 *
	 * @return mixed Access token on success or WP_Error.
	 */
	public function retrieve_access_token_by_code( $code = '' ) {

		if ( ! $code ) {
			throw new \Exception ( 'No authorization code provided.' );
		}

		$args = array(
			'body' => array(
				'code'          => $code,
				'client_id'     => get_option( 'siwe_google_client_id' ),
				'client_secret' => get_option( 'siwe_google_client_secret' ),
				'redirect_uri'  => Sign_In_With_Essentials::siwe_redirect_back_url_with_domain(),
				'grant_type'    => 'authorization_code',
			),
		);

		$response = wp_remote_post( 'https://www.googleapis.com/oauth2/v4/token', $args );

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		/*
		error:
		  {
		    public $error => "invalid_grant"
		  	public $error_description => "Bad Request"
		  }

		success:
		  {
		    public $access_token =>  "yaG453h..."
		    public $expires_in   =>  int(3599)
		    public $scope        => "openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile"
		    public $token_type   => "Bearer"
		    public $id_token      => "eyAfd46iOiJSUzI..."
		  }
		*/

		if ( isset($body->access_token) && '' !== $body->access_token ) {
			return $body->access_token;
		}

		return false;
	}


	/**
	 * Get the user's info.
	 *
	 * @since 1.2.0
	 *
	 * @param string $token The user's token for authentication.
	 */
	public function get_user_by_access_token( $token ) {

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


	public function set_and_retrieve_user_by_code( $code ) {
		$token = $this->retrieve_access_token_by_code( $code );
		return $this->get_user_by_access_token( $token );
	}

}
