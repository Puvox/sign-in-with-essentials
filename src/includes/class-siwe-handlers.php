<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Sign_In_With_Essentials
 * @subpackage Sign_In_With_Essentials/admin
 * @author     Puvox Software <support@puvox.software>
 */
class Sign_In_With_Essentials_Handlers {

	/**
	 * Main plugin class
	 *
     * @var Sign_In_With_Essentials
     */
	private $parent;


	/**
	 * Holds the state to send with redirect. It will be json and url encoded before the redirect.
	 *
	 * @since 1.2.1
	 * @access private
	 * @var array $state
	 */
	private $state;

	/**
	 * GoogleAuth class
	 *
	 * @since 1.5.2
	 * @access private
	 * @var SIWE_GoogleAuth
	 */
	protected $module_google;


	/**
	 * MicrosoftAuth class
	 *
	 * @since 1.5.2
	 * @access private
	 * @var SIWE_MicrosoftAuth
	 */
	protected $module_microsoft;



	public function __construct( $parent ) {
		$this->parent = $parent;
		$this->module_google = new SIWE_GoogleAuth($this->parent);
		$this->module_microsoft = new SIWE_MicrosoftAuth($this->parent);
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
			$value = esc_attr ( wp_unslash( $_GET['siwe_forbidden_error'] ) );
			$message =  sprintf( __( 'You must have an email with a required domain (<code><b>%s</b></code>) to log in to this website.', 'sign-in-with-essentials' ), get_option( 'siwe_allowed_domains' ) );
			if ( ! ( $value === 'forbidden_mail_domain' ) ) {
				$message = $value;
			}
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

		if ( isset( $_GET['siwe_auth_redirect'] ) ) {
			$provider_name = sanitize_key( wp_unslash( $_GET['siwe_auth_redirect'] ) );
			if (! get_option( 'siwe_enable_'.$provider_name )) {
				wp_die( esc_attr__( 'login is disabled for this provider', 'sign-in-with-essentials' ) );
			}
			$redirect_to = sanitize_url ($this->parent->value($_GET, 'redirect_to', ''));
			if ($provider_name === 'google') {
				// Gather necessary elements for 'state' parameter.
				$this->state = [ 'redirect_to' => $redirect_to, 'provider'=> $provider_name];
				$url = $this->module_google->get_auth_url( $this->state );
			} else if ($provider_name === 'microsoft') {
				$this->state = [ 'redirect_to' => $redirect_to, 'provider'=> $provider_name];
				$url = $this->module_microsoft->get_auth_url( $this->state );
			}
			wp_redirect( $url );
			exit;
		}
	}

	/**
	 * Uses the code response from redirection-back to authenticate the user, before anything is rendered.
	 *
	 * @since 1.0.0
	 */
	public function check_authenticate_user() {
		if (!isset( $_GET['code'] ) && !isset( $_GET['error'] ) ) {
			return;
		}
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$redir_url = apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=' . urlencode($error) );
			wp_redirect( $redir_url );
			exit;
		}
		$redir_url = $this->parent->siwe_redirect_back_url();
		$is_query = str_contains ($redir_url, '?' );

		if (
			(   $is_query && array_key_exists(str_replace('?', '', $redir_url), $_GET) )
				||
			( ! $is_query && str_starts_with(sanitize_url($_SERVER['REQUEST_URI']), $redir_url ) )
		)
		{
			$res = $this->authenticate_user( sanitize_text_field($_GET['code']), sanitize_text_field($_GET['state']), true );
			if ($res['error']) {}
			wp_redirect($res['redir_to']);
			exit;
		}
	}

	public function authenticate_user($code, $state, $redirect_after_login = true) {
		if (empty($code) && empty($state)) {
			throw new Exception('No code or state provided');
		}
		$params = [ 'code' => $code, 'state' => $state, 'redirect_after_login' => $redirect_after_login, ];
		// Decode passed back state.
		$decoded_state = json_decode( base64_decode( $params['state']) );
		$provider = Sign_In_With_Essentials::value ($decoded_state, 'provider');
		if (! get_option( 'siwe_enable_'.$provider )) {
			return ['error'=>'login is disabled for this provider', 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=provider_disabled' )];
		}
		if ($provider === 'google') {
			$remote_user_data = $this->module_google->set_and_retrieve_user_by_code( $code );
		} else if ($provider === 'microsoft') {
			$remote_user_data = $this->module_microsoft->set_and_retrieve_user_by_code( $code );
		} else {
			throw new Exception('Unknown provider');
		}

		if (!$remote_user_data) {
			// Something went wrong, redirect to the login page.
			return ['error'=>'Could not validate user', 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=can_not_get_user_data_from_provider' )];
		}

		$email = $this->get_email_from_remote_data ($provider, $remote_user_data);
		if ((bool) get_option( 'siwe_email_sanitization_google', true )) {
			if ($provider === 'google') {
				$email = Sign_In_With_Essentials_Utility::sanitize_google_email( $email );
			}
		}

		$user_domain = explode( '@', $email )[1];

		// hooked to disallow user login/registration (i.e. banned emails) from external codes
		if ( ! apply_filters( 'siwe_permit_authorization', true, $email, $remote_user_data) ) {
			return ['error'=>'forbidden authorization', 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=forbidden_auth' )];
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
				if ($this->is_in_forbidden_domain ($user_domain)) {
					return ['error'=>'forbidden domain', 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=forbidden_mail_domain&siwe_domain=' . urlencode($user_domain) )];
				}
				// Redirect the user if registrations are disabled and there is no domain user registration override.
				if (! (get_option( 'users_can_register' ) || get_option( 'siwe_allow_registration_even_if_disabled' ))) {
					return ['error'=>'forbidden registrations', 'redir_to'=> apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?siwe_forbidden_error=new_registrations_are_forbidden' )];
				}
				$found_user = $this->create_user_by_email( $email );
			}
			$existing_user = $found_user;
			// if existing user is found eventually, then auth that
			wp_set_current_user( $found_user->ID, $found_user->user_login );
			wp_set_auth_cookie( $found_user->ID );
			do_action( 'wp_login', $found_user->user_login, $found_user ); // phpcs:ignore
		}
		$this->link_account( $provider, $email, $existing_user );
		$this->update_user_metas_by_remote_info ($existing_user->ID, $provider, $remote_user_data);

		// unless overriden in state, send them in profile page
		$redirect = sanitize_url (Sign_In_With_Essentials::value ($decoded_state, 'my_redirect_uri', admin_url('profile.php?siwe_redirected&provider='.$provider)) );
		return ['error'=>null, 'redir_to'=>apply_filters( 'siwe_redirect_after_login_url', $redirect, $existing_user ) ];
	}

	/**
	 * Creates a new user by email
	 *
	 * @since 1.0.0
	 * @param object $email The email address of the user.
	 * @return WP_User
	 */
	protected function create_user_by_email( $email ) {
		$pass_length  = max(12, (int) apply_filters( 'siwe_password_length', 16 ));// force > 12 length
		$user_pass    = wp_generate_password( $pass_length );
		$user_email   = $email;
		// set username as friendly as possible
		$user_email_data = explode( '@', $user_email );
		$user_login      = $user_email_data[0];
		while ( username_exists($user_login) ) {
			$user_login  = $user_login . wp_rand(1,9);
		}

		$user = array(
			'user_pass'       => $user_pass,
			'user_login'      => $user_email, //$user_login
			'user_email'      => $user_email,
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

	public function get_email_from_remote_data ($provider, $remote_user_data) {
		if ($provider === 'google') {
			return $remote_user_data->email;
		} else if ($provider === 'microsoft') {
			return $remote_user_data['mail'];
		} else {
			throw new Exception('Unknown provider');
		}
	}

	private function update_user_metas_by_remote_info ($userid, $provider, $remote_user_data) {
		// if user exists, link to account and return
		$first_name = null;
		$last_name = null;
		if ($provider === 'google') {
			$first_name = Sign_In_With_Essentials::value($remote_user_data, 'given_name');
			$last_name = Sign_In_With_Essentials::value($remote_user_data, 'family_name');
		} else if ($provider === 'microsoft') {
			$first_name = Sign_In_With_Essentials::value($remote_user_data, 'givenName');
			$last_name = Sign_In_With_Essentials::value($remote_user_data, 'surname');
		}
		if ($first_name) {
			update_user_meta( $userid, 'first_name', $first_name );
		}
		if ($last_name) {
			update_user_meta( $userid, 'last_name', $last_name );
		}
		if ($first_name || $last_name) {
			$dname = ($first_name ?: '') . ' ' . ($last_name ?: '');
			update_user_meta( $userid, 'display_name', $dname );
		}

		if ( (bool) get_option ('siwe_save_remote_info') ) {
			update_user_meta ( $userid, 'siwe_remote_info_' . $provider, apply_filters( 'siwe_save_userinfo', $remote_user_data ) );
			// $this->check_and_update_profile_pic ($userid, $remote_user_data);
		}
	}

	protected function link_account( $provider_name, $email, $wp_user_override = null ) {

		if ( ! $email ) {
			throw new Exception('No email provided');
		}

		if ( empty($provider_name) ) {
			throw new Exception('No provider_name provided');
		}

		$current_user = $wp_user_override ?: wp_get_current_user();

		if ( ! ( $current_user instanceof WP_User ) ) {
			throw new Exception('Can not retrieve current user');
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

	public function is_in_forbidden_domain($user_domain) {
		$restrict_to_domains = array_filter( explode( ',', get_option( 'siwe_allowed_domains' ) ) );
		$is_forbidden_domain = ! empty($restrict_to_domains) && ! in_array( $user_domain, $restrict_to_domains, true );
		return $is_forbidden_domain;
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
