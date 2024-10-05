<?php

namespace Sign_In_With_Essentials;

/**
 * Defines the SIWE_Auth_Google class.
 *
 * @since      1.5.2
 *
 * @package    Sign_In_With_Essentials
 */

/**
 * The SIWE_Auth_Google class.
 *
 * Handles the entire Google Authentication process.
 */
class Siwe_Auth_google {

	private $app;

	public $base_url = 'https://accounts.google.com/o/oauth2/v2/auth';

	public $scopes = [
		// https://developers.google.com/identity/protocols/oauth2/web-server
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile', // given_name (eg. Elvis), family_name (eg. Presley), name (eg. Elvis Presley)
	];

	public function __construct($app) {
		$this->app = $app;
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
		$redirect_uri  = urlencode( $this->app->plugin_handlers->get_redirect_back_uri('google') ); // already filtered outside
		$encoded_state = base64_encode( wp_json_encode( $state ) );
		return $this->base_url . '?scope=' . $scopes . '&redirect_uri=' . $redirect_uri . '&response_type=code&client_id=' . get_option( 'siwe_google_client_id' ) . '&state=' . $encoded_state . '&prompt=select_account'; // '&access_type=offline'
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
				'redirect_uri'  => $this->app->plugin_handlers->get_redirect_back_uri('google'),
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

		$json = json_decode( wp_remote_retrieve_body( $result ), true );
		//
		// 	{
		// 		 $id             => "123456789123456789123"
		// 		 $email          => "example@gmail.com"
		// 		 $verified_email => bool(true)
		// 		 $name           => string(8) "Firstname Lastname"
		// 		 $given_name     => string(2) "Firstname"
		// 		 $family_name    => string(5) "Lastname"
		// 		 $picture        => string(98) "https://lh3.google-user-content.com/a/xyzxyzxyzxyz=s96-c"
		// 		 $locale         => string(2) "en"
		// 	}
		//
		return $json;
	}

	public function getStructuredUser ($data) {
		return Main::userFormat('google',
			$data['id'],
			$data['email'],
			Main::value($data, 'given_name'),
			Main::value($data, 'family_name'),
			Main::value($data, 'name'),
			$data
		);
	}

	public function set_and_retrieve_user_by_code( $code ) {
		$token = $this->retrieve_access_token_by_code( $code );
		if (!$token) {
			return null;
		}
		$user = $this->get_user_by_access_token( $token );
		return $this->getStructuredUser ($user);
	}

}
