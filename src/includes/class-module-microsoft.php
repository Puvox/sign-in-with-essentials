<?php

class SIWE_MicrosoftAuth {

	private $app;

	public function __construct($app) {

		$this->app = $app;
		$this->app->siwe_vendor_autoload();
		add_action('init', array($this, 'test'));
	}



	// https://github.com/Trunkstar/oauth2-microsoft
	// https://learn.microsoft.com/en-us/answers/questions/799042/adding-mfa-to-administrator-accounts-with-the-free
	// https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade
	// https://learn.microsoft.com/en-us/advertising/guides/authentication-oauth-register?view=bingads-13
	// https://learn.microsoft.com/en-us/entra/identity-platform/publisher-verification-overview
	// https://github.com/myPHPnotes/source_code_sign_in_with_microsoft
	public function test() {
		$provider = new Trunkstar\OAuth2\Client\Provider\Microsoft([
			// Required
			'clientId'                  => '',
			'clientSecret'              => '',
			'redirectUri'               => 'http://localhost:1111/_siwe_login_',
			// Optional
		]);

		if (!str_contains($_SERVER['REQUEST_URI'],'_siwe_login')) {
			return;
		}
		if (isset($_GET['error'])) {
			// If the user denied the request then you will get an error response
			var_dump($_REQUEST);
			exit;
		}
		if (!isset($_GET['code'])) {

			// If we don't have an authorization code then get one
			$options = [
				'state' => 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
				'scope' => ['User.Read'] // array or string
			];

			// $authorizationUrl = $provider->getAuthorizationUrl($options);
			$authUrl = $provider->getAuthorizationUrl($options);
			$state =  $provider->getState();
			$this->setcookie ('ms_oauth2state', $state );
			header('Location: ' . $authUrl);
			exit;

		// Check given state against previously stored one to mitigate CSRF attack
// 		} elseif (empty($_GET['state']) || ($_GET['state'] !== $_COOKIE['ms_oauth2state'])) {
// var_dump($_COOKIE); exit;
// 			unset($_COOKIE['ms_oauth2state']);
// 			exit('Invalid state');

		} else {

			// Try to get an access token (using the authorization code grant)
			$token = $provider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);

			// Optional: Now you have a token you can look up a users profile data
			try {

				// We got an access token, let's now get the user's details
				$user = $provider->getResourceOwner($token);

				// Use these details to create a new profile
				print('Hello %s!');
				var_dump($user);

			} catch (Exception $e) {

				// Failed to get user details
				var_dump('Oh dear...', $e);
				exit;
			}

			// Use this to interact with an API on the users behalf
			echo $token->getToken();
			exit;
		}
	}
}
