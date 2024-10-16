<?php

class SIWE_MicrosoftAuth {

	private $app;

	private $provider;


	// if you change default, you need to add in API PERMISSIONS in Azure too
	private $scopes = [
		'User.Read'
			// User.Read:
			// 	@odata.context = "https://graph.microsoft.com/v1.0/$metadata#users/$entity"
			// 	userPrincipalName = "xyz@example.com"
			// 	id = "123abcdef1a12a12"
			// 	displayName = "Elon Musk"
			// 	surname = "Musk"
			// 	givenName = "Elon"
			// 	preferredLanguage = "en-US"
			// 	mail = "xyz@example.com"
			// 	mobilePhone = null
			// 	jobTitle = null
			// 	officeLocation = null
			// 	businessPhones = array(0)
		// others:
		// 'openid',
		// 'offline_access',
		// 'profile',
		// 'email',
	];

	public $redirect_uri;

	public function __construct($app) {
		$this->app = $app;
		$this->redirect_uri = Sign_In_With_Essentials::siwe_redirect_back_url_with_domain();
	}


	private $inited = false;

	private function init_provider() {
		if (!$this->inited) {
			$this->app->siwe_vendor_autoload();
			// https://github.com/Trunkstar/oauth2-microsoft
			// https://github.com/myPHPnotes/source_code_sign_in_with_microsoft
			$provider = new Trunkstar\OAuth2\Client\Provider\Microsoft([
				'clientId'                  => get_option( 'siwe_microsoft_client_id' ),
				'clientSecret'              => get_option( 'siwe_microsoft_client_secret' ),
				'redirectUri'               => $this->redirect_uri,
				'urlAuthorize'				=>'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'urlAccessToken'			=>'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			]);
			$this->provider = $provider;
		}
	}

	public function get_auth_url( $state ) {
		$this->init_provider();

		$scopes = apply_filters( 'siwe_scopes', $this->scopes, 'microsoft' );
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


	public function check_safety_state () {
		// if (empty($_GET['state']) || ($_GET['state'] !== $_COOKIE['ms_oauth2state'])) {
		// 			var_dump($_COOKIE); exit;
		// 			unset($_COOKIE['ms_oauth2state']);
		// 			exit('Invalid state');
		// }
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

		// We got an access token, let's now get the user's details
		$user = $this->provider->getResourceOwner($token);

		return $user->toArray(); // example of response is above in scope comments
	}


	public function set_and_retrieve_user_by_code( $code ) {
		$token = $this->retrieve_access_token_by_code( $code );
		return $this->get_user_by_access_token( $token );
	}
}
