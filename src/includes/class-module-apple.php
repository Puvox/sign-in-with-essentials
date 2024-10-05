<?php

namespace Sign_In_With_Essentials;

class Siwe_Auth_apple {

	// https://github.com/patrickbussmann/oauth2-apple (current)
	// https://github.com/rasmusbe/sign-in-with-apple/tree/master
	// https://github.com/AzimoLabs/apple-sign-in-php-sdk
	// https://github.com/mikebronner/laravel-sign-in-with-apple
	// https://github.com/kasparsd/sign-in-with-apple

	private $app;
	public function __construct($app) {
		$this->app = $app;
	}

	private $scopes = [
		'name',
		'email'
	];

	private $inited = false;
	private $provider;

	private function init_provider() {
		if (!$this->inited) {
			$this->app->siwe_vendor_autoload();
			require_once __DIR__ . '/class-module-apple-helper.php';
			// $leeway is needed for clock skew
			\Firebase\JWT\JWT::$leeway = 60;
			$opts = [
				'clientId' => get_option( 'siwe_apple_client_id' ),
				'teamId' => get_option( 'siwe_apple_team_id' ),
				'keyFileId' => get_option( 'siwe_apple_key_id' ),
				'keyFilePath' => '_',
				'redirectUri' => $this->app->plugin_handlers->get_redirect_back_uri('apple'),
			];
			$provider = new \League\OAuth2\Client\Provider\Apple2($opts);
			$this->provider = $provider;
		}
	}

	public function get_auth_url( $state ) {
		$this->init_provider();
		$scopes = apply_filters( 'siwe_scopes', $this->scopes, 'apple' );
		$encoded_state = base64_encode( wp_json_encode( $state ) );
		$options = [
			'state' => $encoded_state, // 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
			'scope' => $scopes
		];
		$authUrl = $this->provider->getAuthorizationUrl($options);
		// $state = $provider->getState();
		// $this->setcookie ('ms_oauth2state', $state );
		return $authUrl;
	}



	/**
	 * Sets the access_token using the response code.
	 *
	 * @since 1.0.0
	 * @param string $code The code provided by Microsoft's redirect.
	 *
	 * @return mixed Access token on success or WP_Error.
	 */
	public function retrieve_access_token_by_code( $code = '' ) {
		$this->init_provider();

		if ( ! $code ) {
			throw new \Exception ( 'No authorization code provided.' );
		}

		// Try to get an access token (using the authorization code grant)
		$token = $this->provider->getAccessToken('authorization_code', [
			'code' => $code
		]);

		// Use this to interact with an API on the users behalf
		// echo $token->getToken();
		// $this->access_token = $token->getToken();

		return $token;
	}


	public function check_safety_state () {
		// if (isset($_POST['code']) &&((empty($_POST['state']) || ($_POST['state'] !== $_SESSION['oauth2state']))) {

		// 	unset($_SESSION['oauth2state']);
		// 	exit('Invalid state');

		// } else {
		// 			exit('Invalid state');
		// }
	}


	/**
	 * Get the user's info.
	 *
	 * @since 1.2.0
	 *
	 * @param string $token The user's token for authentication.
	 */
	public function get_user_by_access_token( $token ) {
		$this->init_provider();

		if ( ! $token ) {
			return;
		}

			// Optional: Now you have a token you can look up a users profile data
			// Important: The most details are only visible in the very first login!
			// In the second and third and ... ones you'll only get the identifier of the user!
		// We got an access token, let's now get the user's details
		$user = $this->provider->getResourceOwner($token);

		return $user->toArray(); // example of response is above in scope comments
	}


	public function set_and_retrieve_user_by_code( $code ) {
		$token = $this->retrieve_access_token_by_code( $code );
		if (!$token) {
			return null;
		}
		$userData = $this->get_user_by_access_token( $token );
		return $this->getStructuredUser ($userData);
	}


	public function getStructuredUser ($data) {
		return Main::userFormat('apple',
			$data['sub'], // "012345.12345ab1cd234e56f7890fbeab1ef23c.1234"
			$data['email'],	// "abcde1f2gh@privaterelay.appleid.com"
			null,
			null,
			null,
			$data  //  [ ..., isPrivateEmail = true ]
		);
	}

}
