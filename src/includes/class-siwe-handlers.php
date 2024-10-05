<?php
namespace Sign_In_With_Essentials;
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sign_In_With_Essentials
 * @author     Puvox Software <support@puvox.software>
 */
class Handlers {

	/**
	 * Main plugin class
	 *
     * @var Main
     */
	private $parent;

	/**
	 * @access private
	 * @var Siwe_Auth_google
	 */
	protected $module_google;

	/**
	 * @access private
	 * @var Siwe_Auth_microsoft
	 */

	protected $module_microsoft;
	/**
	 * @access private
	 * @var Siwe_Auth_apple
	 */
	protected $module_apple;



	public function __construct( $parent ) {
		$this->parent = $parent;
		foreach (SIWE_SUPPORTED_PROVIDERS as $provider) {
			$className = '\\Sign_In_With_Essentials\\Siwe_Auth_' . $provider;
			$module = 'module_'.$provider;
			$this->$module = new $className($this->parent);
		}
		$this->init_hooks();
	}

	public function init_hooks () {
		$this->parent->add_action( 'login_init', $this, 'check_login_redirection', 888 );
		$this->parent->add_action( 'template_redirect', $this, 'auth_redirect' );
		$this->parent->add_action( 'init', $this, 'check_authenticate_user' );
		$this->check_forbidden_login();
	}



	private function check_forbidden_login() {

		// Check if domain restrictions have kept a user from logging in.
		if ( isset( $_GET['siwe_forbidden_error'] ) ) {
			$message = esc_attr ( wp_unslash( $_GET['siwe_forbidden_error'] ) );
			$this->parent->add_filter( 'login_message', null, function () use ($message) {
				return '<div id="login_error" style="color:red"> ' . $message . '</div>';
			});
		}
	}
	/**
	 * Redirect the user to get authenticated by 3rd party provider.
	 *
	 * @since    1.0.0
	 */
	public function auth_redirect() {
		$provider = Main::value($_GET, 'siwe_auth_redirect');
		if ( !empty( $provider ) ) {
			// When you try to enter e.g. `example.com/wp-admin`, wp at first redirects your to url like:
			//     example.com/wp-login.php?redirect_to=wp-admin
			// There `redirect_to` query is added by wordpress for internal tracking
			// So, it is not the "redirection-back" url used by provider at all, but saved in state date for later use by us
			$state = [
				'after_login_redirect' => sanitize_url (Main::value($_GET, 'redirect_to', '')),
				'siwe_provider'=> $provider
			];
			$url = $this->get_auth_url_callback($provider, $state);
			wp_redirect( $url );
			exit;
		}
	}

	public function get_auth_url_callback($provider_name, $state) {
		$provider_name = sanitize_key( wp_unslash( $provider_name ) );
		if (!in_array($provider_name, SIWE_SUPPORTED_PROVIDERS)) {
			exit ( __( 'Invalid SIWE provider', 'sign-in-with-essentials' ) );
		}
		if (! get_option( 'siwe_enable_'.$provider_name )) {
			wp_die( __( 'login is disabled for this provider', 'sign-in-with-essentials' ) );
		}
		$classname = 'module_'.$provider_name;
		$url = $this->$classname->get_auth_url( $state );
		return $url;
	}


	public function get_redirect_back_uri($provider) {
		return apply_filters ('siwe_redirect_back_uri', Main::siwe_redirect_back_url_with_domain(), $provider);
	}

	/**
	 * Uses the code response from redirection-back to authenticate the user, before anything is rendered.
	 *
	 * @since 1.0.0
	 */
	public function check_authenticate_user() {
		$code_get = Main::value($_GET, 'code');
		$error_get = Main::value($_GET, 'error');
		$code_post = Main::value($_POST, 'code');
		$error_post = Main::value($_POST, 'error');
		$code_final = $code_get ?: $code_post;
		$error_final = $error_get ?: $error_post;
		if (empty ($code_final) && empty($error_final)) {
			return;
		}
		$state_get = Main::value($_GET, 'state');
		$state_post = Main::value($_POST, 'state');
		$state_final = $state_get ?: $state_post;

		$redir_url = $this->parent->siwe_redirect_back_url();
		$is_query = str_contains ($redir_url, '?' );

		if (
			(   $is_query && array_key_exists(str_replace('?', '', $redir_url), $_GET) )
				||
			( ! $is_query && str_starts_with(sanitize_url($_SERVER['REQUEST_URI']), $redir_url ) )
		)
		{
			$res = $this->authenticate_user_callback( $code_final, $state_final, $error_final );
			// if ($res['error']) {
			// 	throw new Exception($res['error']);
			// }
			wp_redirect($res['redir_to']);
			exit;
		}
	}

	public function authenticate_user_callback($code, $state, $error) {
		$code = sanitize_text_field($code);
		$state = sanitize_text_field($state);
		$error = sanitize_text_field($error);
		if ( !empty( $error ) ) {
			return $this->redir_back_err( __('Could not validate user', 'sign-in-with-essentials') . ' : '.  urlencode($error));
		}
		if (empty($code) || empty($state)) {
			return $this->redir_back_err( __('No state or code provided', 'sign-in-with-essentials') );
		}

		// Decode passed back state.
		$decoded_state = json_decode( base64_decode( $state ) );
		$provider = sanitize_key(esc_attr(Main::value ($decoded_state, 'siwe_provider', '')));
		if (! get_option( 'siwe_enable_'.$provider )) {
			return $this->redir_back_err( __('login is disabled for this provider: ', 'sign-in-with-essentials'). $provider  );
		}
		$module = 'module_'.$provider;
		$remote_user_data = $this->$module->set_and_retrieve_user_by_code( $code );

		if (!$remote_user_data) {
			// Something went wrong, redirect to the login page.
			return $this->redir_back_err( __('Could not validate user, no provider userdata', 'sign-in-with-essentials') );
		}

		$email = $remote_user_data['email'];
		// ########################################
		// ###### provider specific handling ######
		// ########################################
		if ($provider === 'google') {
			if ((bool) get_option( 'siwe_email_sanitization_google', true )) {
				$email = Utility::sanitize_google_email( $email );
			}
		}
		if ($provider === 'apple') {
			if (get_option( 'siwe_apple_forbid_hiden_email' )) {
				if ($remote_user_data['raw']['isPrivateEmail']) {
					return $this->redir_back_err( __('Hidden emails are not allowed by website administrator. You can visit https://account.apple.com/account/manage > "Sign in with apple" and Remove(stop using) this service, and then re-try to login again', 'sign-in-with-essentials') );
				}
			}
		}
		// ########################################
		// ###################################

		$user_domain = explode( '@', $email )[1];

		// hooked to disallow user login/registration (i.e. banned emails) from external codes
		if ( ! apply_filters( 'siwe_permit_authorization', true, $email, $remote_user_data) ) {
			return $this->redir_back_err( __('Forbidden for user', 'sign-in-with-essentials') );
		}

		if ( is_user_logged_in() ) {
			// If the user is logged in, just connect the authenticated social account to that
			$existing_user = wp_get_current_user();
		}
		else {
			// if not logged in,
			$found_user = null;
			$linked_user = get_users([
				'meta_key'   => 'siwe_account_' . $provider,
				'meta_value' => $email,
			]);
			// locate if the provider email meta was linked to any existing account
			if ( ! empty( $linked_user ) ) {
				$found_user = $linked_user[0];
			}
			// locat if email is directly used by any existing account
			else {
				$found_user = get_user_by( 'email', $email );
			}
			// if user was not found, then we should create it
			if ( !$found_user ) {
				// check if domain is forbidden
				if (!$this->is_allowed_domain ($user_domain)) {
					return $this->redir_back_err( __('Forbidden domain was used: ', 'sign-in-with-essentials') .  urlencode($user_domain)  );
				}
				// Redirect the user if registrations are disabled and there is no domain user registration override.
				if (! (get_option( 'users_can_register' ) || get_option( 'siwe_allow_registration_even_if_disabled' ))) {
					return $this->redir_back_err( __('Registrations forbidden ', 'sign-in-with-essentials') . urlencode($user_domain) );
				}
				$found_user = self::wp_user_create_by_mail( $email );
			}
			// if existing user is found eventually, then auth that
			self::wp_user_sign_in($found_user);
			$existing_user = $found_user;
		}
		$this->link_account( $provider, $email, $existing_user );
		$this->update_user_metas_by_remote_info ($existing_user->ID, $provider, $remote_user_data);

		// unless overriden in state, send them in profile page
		$redirect = sanitize_url (Main::value ($decoded_state, 'my_redirect_uri', admin_url('profile.php?siwe_redirected&provider='.$provider)) );
		return ['error'=>null, 'redir_to'=>apply_filters( 'siwe_redirect_after_login_url', $redirect, $existing_user ) ];
	}

	private function redir_back_err($err) {
		return ['error'=>$err, 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=' . $err )];
	}
	/**
	 * Creates a new user by email
	 *
	 * @since 1.0.0
	 * @param object $email The email address of the user.
	 * @return WP_User
	 */
	public static function wp_user_create_by_mail( $email ) {
		$pass_length  = max(12, (int) apply_filters( 'siwe_password_length', 16 ));// force > 12 length
		// set username as friendly as possible
		$user_email_data = explode( '@', $email );
		$user_login      = $user_email_data[0];
		while ( username_exists($user_login) ) {
			$user_login  = $user_login . wp_rand(1,9);
		}

		$user = array(
			'user_pass'       => wp_generate_password( $pass_length ),
			'user_login'      => $email, //$user_login
			'user_email'      => $email,
			'user_registered' => gmdate( 'Y-m-d H:i:s' ),
			'role'            => get_option( 'siwe_user_default_role', 'subscriber' ),
		);
		$user = apply_filters ('siwe_pre_wp_insert_user', $user);
		$new_user_id = wp_insert_user( $user );
		do_action ('siwe_after_wp_insert_user', $new_user_id );

		if ( is_wp_error( $new_user_id ) ) {
			do_action ('siwe_error_wp_insert_user', $new_user_id );
			wp_die( esc_attr( $new_user_id->get_error_message() ) . ' <a href="' . esc_url( wp_login_url() ). '">Return to Log In</a>' );
		} else {
			return get_user_by( 'id', $new_user_id );
		}
	}

	public static function wp_user_sign_in($user) {
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore
	}


	private function update_user_metas_by_remote_info ($userid, $provider, $remote_user_data) {
		$first_name = $remote_user_data['first_name'];
		$last_name = $remote_user_data['last_name'];
		if ($first_name) {
			update_user_meta( $userid, 'first_name', $first_name );
		}
		if ($last_name) {
			update_user_meta( $userid, 'last_name', $last_name );
		}
		if ($first_name || $last_name || !empty($remote_user_data['full_name'])) {
			if (!empty($remote_user_data['full_name'])) {
				update_user_meta( $userid, 'display_name', $remote_user_data['full_name'] );
			} else {
				update_user_meta( $userid, 'display_name', ($first_name ?: '') . ' ' . ($last_name ?: ''));
			}
		}
		if ( (bool) get_option ('siwe_save_remote_info') ) {
			update_user_meta ( $userid, 'siwe_remote_info_' . $provider, apply_filters( 'siwe_save_userinfo', $remote_user_data ) );
			// $this->check_and_update_profile_pic ($userid, $remote_user_data);
		}
	}


	protected function link_account( $provider_name, $email, $wp_user_override = null ) {

		if ( ! $email ) {
			throw new \Exception('No email provided');
		}

		if ( empty($provider_name) ) {
			throw new \Exception('No provider_name provided');
		}

		$current_user = $wp_user_override ?: wp_get_current_user();

		if ( ! ( $current_user instanceof \WP_User ) ) {
			throw new \Exception('Can not retrieve current user');
		}

		add_user_meta( $current_user->ID, 'siwe_account_'.$provider_name, $email, true );
	}

	/**
	 * Remove usermeta for current user and provider account email.
	 *
	 * @since 1.3.1
	 */
	public function unlink_account($user_id, $provider ) {
		return delete_user_meta( $user_id, 'siwe_account_'.$provider  );
	}

	public function is_allowed_domain($user_domain) {
		$whitelisted_domains = array_filter( explode( ',', get_option( 'siwe_allowed_domains' ) ) );
		$is_allowed = empty($whitelisted_domains) || in_array( $user_domain, $whitelisted_domains, true );
		// also check if the domain is forbidden by a filter
		if ($is_allowed) {
			$forbidden_domains = array_filter( explode( ',', get_option( 'siwe_forbidden_domains' ) ) );
			if (!empty($forbidden_domains) && in_array( $user_domain, $forbidden_domains, true )) {
				$is_allowed = false;
			}
		}
		return $is_allowed;
	}

	/**
	 * Disable Login page & redirect directly to provider's login
	 *
	 * @since 1.3.1
	 */
	public function check_login_redirection()
	{
		// todo: select which social to use
		// if ( boolval( get_option( 'siwe_disable_login_page' ) ) )
		// {
		// 	// Skip only logout action
		// 	$action = $this->parent->value ($_REQUEST, 'action');
		// 	if ( ! empty( $action ) &&  ! in_array( trim( strtolower( $action )), ["logout", "registration"] ) ) {
		// 		$this->google_auth_redirect();
		// 	}
		// }
	}
}
