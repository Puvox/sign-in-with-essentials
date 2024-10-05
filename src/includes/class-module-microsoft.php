<?php

namespace Sign_In_With_Essentials;

class Siwe_Auth_microsoft {

	private $app;
	public function __construct($app) {
		$this->app = $app;
	}

	// if you change default, you need to add in API PERMISSIONS in Azure too ( https://stackoverflow.com/questions/72121247 )
	private $scopes = [
		// 'offline_access',
		'User.Read',
			//
			// {
			// 	@odata.context = "https://graph.microsoft.com/v1.0/$metadata#users/$entity"
			// 	userPrincipalName = "xyz@example.com"
			// 	mail = "xyz@example.com"
			// 	id = "123abcdef1a12a12"
			// 	displayName = "Elon Musk"
			// 	surname = "Musk"
			// 	givenName = "Elon"
			// 	preferredLanguage = "en-US"
			// 	mobilePhone = null
			// 	jobTitle = null
			// 	officeLocation = null
			// 	businessPhones = array(0)
			// }
			//
		// others: (below are not yet permitted, see comments in set_and_retrieve_user_by_code_auto function)
		// 'openid',
		// 'profile',
		// 'email',
			//
			// {
			// 	"ver": "2.0",
			// 	"iss": "https://login.microsoftonline.com/1234567a-1a23-4b5c-e123-12a345b67abc/v2.0",
			// 	"sub": "AAA...............qzc",
			// 	"aud": "ab12cdef-12a3-45bc-a123-12a34b56b789",
			// 	"exp": 1729791000,
			// 	"iat": 1729704999,
			// 	"nbf": 1729704999,
			// 	"name": "Franko Edwards",
			// 	"preferred_username": "example@gmail.com",
			// 	"oid": "00000000-0000-0000-123a-abcde1f23a45",
			// 	"email": "example@gmail.com",
			// 	"tid": "1234567a-1a23-4b5c-e123-12a345b67abc",
			// 	"aio": "DlAbCd*w............AbCdEF1"
			// }
	];

	public function getStructuredUser ($data) {
		// if returned from OID format
		if (array_key_exists('preferred_username', $data)) {
			return Main::userFormat('microsoft',
				$data['oid'],
				$data['email'],
				null,
				null,
				Main::value($data, 'name'),
				$data
			);
		} else {
			return Main::userFormat('microsoft',
			$data['id'],
			$data['userPrincipalName'],
			Main::value($data, 'givenName'),
			Main::value($data, 'surname'),
			Main::value($data, 'displayName'),
			$data
		);
		}
	}


	private $inited = false;
	private $provider;

	private function init_provider() {
		if (!$this->inited) {
			$this->app->siwe_vendor_autoload();
			// https://github.com/Trunkstar/oauth2-microsoft
			// https://github.com/myPHPnotes/source_code_sign_in_with_microsoft
			$provider = new \Trunkstar\OAuth2\Client\Provider\Microsoft([
				'clientId'        => get_option( 'siwe_microsoft_client_id' ),
				'clientSecret'    => get_option( 'siwe_microsoft_client_secret' ),
				'redirectUri'     => $this->app->plugin_handlers->get_redirect_back_uri('microsoft'),
				'urlAuthorize'	  =>'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'urlAccessToken'  =>'https://login.microsoftonline.com/common/oauth2/v2.0/token',
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
		if (!$token) {
			return null;
		}
		$userData = $this->get_user_by_access_token( $token );
		return $this->getStructuredUser ($userData);
	}

	// this automatically differentiates between OpenID and GRAPH api request/responses
	public function set_and_retrieve_user_by_code_auto( $code ) {
		$token = $this->retrieve_access_token_by_code( $code );
		// if 'openid' scope was included, then we can get user info directly from response
		$values = $token->getValues();
		$userData = null;
		if (array_key_exists('id_token', $values)) {
			// todo:
			exit('Temporarily this approach is disabled by Sign-in-with-essentials plugin, until the id-validation will be implemented to avoid blind trust on incoming data');
			// $scopes = explode(' ', $values['scope']);
			// if (in_array('openid', $scopes) && in_array('profile', $scopes) && in_array('email', $scopes)) {
			// 	$endcoded_id_token = $values['id_token'];
			// 	$exploded = explode('.', $endcoded_id_token);
			// 	$userData = json_decode(base64_decode($exploded[1]), true);
			// 	return $this->getStructuredUser ($userData);
			// }
		}
		// if not, then it was done by 'User.Read' permission, so we get user info using access_token
		if (!$token) {
			return null;
		}
		$userData = $this->get_user_by_access_token( $token );
		return $this->getStructuredUser ($userData);
	}
}
