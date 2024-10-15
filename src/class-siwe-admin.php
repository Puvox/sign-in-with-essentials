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
class Sign_In_With_Essentials_Admin {

	private $parent;
	private $plugin_name;
	private $version;

	/**
	 * The user's information.
	 *
	 * @since 1.2.0
	 * @access private
	 * @var WP_User|false $user The user data.
	 */
	private $user;

	/**
	 * Holds the state to send with Google redirect. It will be
	 * json and url encoded before the redirect.
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
	 * @var object
	 */
	private $google_auth;

	/**
	 * Enable google pic feature
	 *
	 * Currently, link to google picture is not reliable, as it is dynamic link which is not like CDN, so, we should avoid using direct link
	 * todo: save small thumb per user locally
	 *
	 * @since 1.5.2
	 * @access private
	 * @var object
	 */
	private $enable_google_pic = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name   The name of this plugin.
	 * @param      string $version       The version of this plugin.
	 */
	public function __construct( $parent, $plugin_name, $version ) {
		$this->parent = $parent;
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->google_auth = new SIWE_GoogleAuth( get_option( 'siwe_google_client_id' ) );
		$this->init_hooks();
	}


	public function init_hooks() {
		$this->parent->add_action( 'admin_init', $this, 'settings_api_init' );
		$this->parent->add_action( 'admin_menu', $this, 'settings_menu_init' );
		$this->parent->add_action( 'admin_enqueue_scripts', $this, 'enqueue_styles' );
		$this->parent->add_action( 'admin_init', $this, 'disallow_email_changes' );
		$this->parent->add_action( 'admin_init', $this, 'process_settings_export' );
		$this->parent->add_action( 'admin_init', $this, 'process_settings_import' );
		$this->parent->add_action( 'show_user_profile', $this, 'add_connect_button_to_profile' );
		$this->parent->add_action( 'login_init', $this, 'check_login_redirection', 888 );
		$this->parent->add_filter( 'get_avatar', $this, 'slug_get_avatar', 10, 5 );
		$this->parent->add_action( 'admin_init', $this, 'disconnect_account' );
		if ( isset( $_GET['google_redirect'] ) ) {
			$this->parent->add_action( 'template_redirect', $this, 'google_auth_redirect' );
		}
		$this->parent->add_action( 'init', $this, 'check_authenticate_user' );
		$this->parent->add_filter( 'plugin_action_links_' . $this->plugin_name . '/' . $this->plugin_name . '.php', $this, 'add_action_links' );

		// Check if domain restrictions have kept a user from logging in.
		if ( isset( $_GET['google_login'] ) ) {
			$this->parent->add_filter( 'login_message', $this, 'allowed_domains_error' );
		}
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url(__FILE__) . 'assets/siwe-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Add the plugin settings link found on the plugin page.
	 *
	 * @since    1.0.0
	 * @param array $links The links to add to the plugin page.
	 */
	public function add_action_links( $links ) {
		$mylinks = array(
			'<a href="' . admin_url( 'options-general.php?page=siwe_settings' ) . '">' . esc_attr__( 'Settings', 'sign-in-with-essentials' ) . '</a>',
		);
		return array_merge( $links, $mylinks );
	}

	/**
	 * Add "Connect With Google" button to user profile settings.
	 *
	 * @since 1.3.1
	 */
	public function add_connect_button_to_profile() {

		$url            = site_url( '?google_redirect' );
		$linked_account = get_user_meta( get_current_user_id(), 'siwe_google_account', true );
		?>
		<h2><?php esc_attr_e( 'Sign In With Google', 'sign-in-with-essentials' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_attr_e( 'Connect', 'sign-in-with-essentials' ); ?></th>
				<td>
				<?php if ( $linked_account ) : ?>
					<?php echo esc_attr( $linked_account ); ?>
					<?php if ( current_user_can( 'manage_options' ) || get_option( 'siwe_show_unlink_in_profile' ) ) { ?>
						<form method="post">
							<input type="submit" role="button" value="<?php esc_attr_e( 'Unlink Account', 'sign-in-with-essentials' ); ?>">
							<?php wp_nonce_field( 'siwe_unlink_account', '_siwe_account_nonce' ); ?>
						</form>
					<?php } ?>
				<?php else : ?>
					<a id="ConnectWithGoogleButton" href="<?php echo esc_attr( $url ); ?>"><?php esc_attr_e( 'Connect to Google', 'sign-in-with-essentials' ); ?></a>
					<span class="description"><?php esc_attr_e( 'Connect your user profile so you can sign in with Google', 'sign-in-with-essentials' ); ?></span>
				<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public $google_logo_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>';

	/**
	 * Initialize the settings menu.
	 *
	 * @since 1.0.0
	 */
	public function settings_menu_init() {
		$prefix_logo = '<span style="width:12px; height:12px; display: inline-block;">' . $this->google_logo_svg . '</span>';
		add_options_page(
			__( 'Sign in with Essentials', 'sign-in-with-essentials' ), // The text to be displayed for this actual menu item.
			$prefix_logo . ' '.  __( 'Sign in with Essentials', 'sign-in-with-essentials' ), // The title to be displayed on this menu's corresponding page.
			'manage_options',                                   // Which capability can see this menu.
			'siwe_settings',                                    // The unique ID - that is, the slug - for this menu item.
			array( $this, 'settings_page_render' )              // The name of the function to call when rendering this menu's page.
		);

	}

	/**
	 * Register the admin settings section.
	 *
	 * @since    1.0.0
	 */
	public function settings_api_init() {

		add_settings_section(
			'siwe_section',
			'',
			[ $this, 'siwe_section' ],
			'siwe_settings'
		);

		$settings_array = [
			[
				'siwe_google_client_id',
				__( 'Client ID', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_google_client_id" id="siwe_google_client_id" type="text" size="50" value="' . esc_attr( get_option( 'siwe_google_client_id' ) ) . '"/>';
				},
				[ $this, 'input_validation' ],
			],
			[
				'siwe_google_client_secret',
				__( 'Client Secret', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_google_client_secret" id="siwe_google_client_secret" type="text" size="50" value="' . esc_attr( get_option( 'siwe_google_client_secret' ) ) . '"/>';
				},
				[ $this, 'input_validation' ]
			],
			[
				'siwe_user_default_role',
				__( 'Default New User Role', 'sign-in-with-essentials' ),
				function () {
					?>
					<select name="siwe_user_default_role" id="siwe_user_default_role">
						<?php
						$siwe_roles = get_editable_roles();
						foreach ( $siwe_roles as $key => $value ) :
							$siwe_selected = '';
							if ( get_option( 'siwe_user_default_role', 'subscriber' ) === $key ) {
								$siwe_selected = 'selected';
							}
							?>

							<option value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr($siwe_selected); ?>><?php echo esc_attr( $value['name'] ); ?></option>

						<?php endforeach; ?>

					</select>
					<?php
				}
			],
			[
				'siwe_allow_registration_even_if_disabled',
				__( 'Allow registrations even if globally disabled', 'sign-in-with-essentials' ),
				function () {

					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_allow_registration_even_if_disabled',
						checked( get_option( 'siwe_allow_registration_even_if_disabled' ), true, false ),
						esc_attr__( 'If registrations are disabled in site settings &gt; general, people will be still regsitered if they successfully login with Google Sign In', 'sign-in-with-essentials' ),
					);
				}
			],
			[
				'siwe_google_allowed_domains',
				__( 'Restrict To Domain', 'sign-in-with-essentials' ),
				function () {
					// Get the TLD and domain.
					$siwe_urlparts    = wp_parse_url( site_url() );
					$siwe_domain      = $siwe_urlparts['host'];
					$siwe_domainparts = explode( '.', $siwe_domain );
					// fix for localhost
					$siwe_domain = count( $siwe_domainparts ) === 1 ? $siwe_domainparts[0] : $siwe_domainparts[ count( $siwe_domainparts ) - 2 ] . '.' . $siwe_domainparts[ count( $siwe_domainparts ) - 1 ];

					?>
					<input name="siwe_google_allowed_domains" id="siwe_google_allowed_domains" type="text" size="50" value="<?php echo esc_html( get_option( 'siwe_google_allowed_domains' ) ); ?>" placeholder="<?php echo esc_attr( $siwe_domain ); ?>">
					<p class="description"><?php esc_html_e( 'Enter the domains (comma separated) to restrict new users email addresses to end (eg. `example.com` permits mails like `someone@example.com`) to or leave blank to allow all', 'sign-in-with-essentials' ); ?></p>
					<?php
				},
				[ $this, 'domain_input_validation' ]
			],
			[
				'siwe_google_email_sanitization',
				esc_attr__( 'Sanitize email addresses', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_google_email_sanitization',
						checked( get_option( 'siwe_google_email_sanitization', true ), true, false ),
						wp_kses_data( __('If enabled, user emails will be sanitized during registration to the base unique account (like <code>james.figard+123@gmail.com</code> to <code>jamesfigard@gmail.com</code> so you can avoid unlimited duplicate/spam registration from gmail aliases).', 'sign-in-with-essentials' )),
					);
				}
			],
			[
				'siwe_show_on_login',
				esc_attr__( 'Show Google Signup Button on Login Form', 'sign-in-with-essentials' ),
				function () {
					echo '<input type="checkbox" name="siwe_show_on_login" id="siwe_show_on_login" value="1" ' . checked( get_option( 'siwe_show_on_login' ), true, false ) . ' />';
				}
			],
			[
				// SEE EXAMPLE JSON RESPONSE IN `get_user_by_token` FUNCTION
				'siwe_google_save_userinfo',
				esc_attr__( 'Save user info received from Google', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_google_save_userinfo',
						checked( get_option( 'siwe_google_save_userinfo' ), true, false ),
						esc_attr__( 'If enabled, user info  (full name, language, id, profile-picture and other info, received from google after successful authorization), will be saved in user-metadatas.', 'sign-in-with-essentials' ),
					);
				}
			],
			($this->enable_google_pic ? [
				'siwe_google_use_profile_pic',
				esc_attr__( 'Use google profile images for user', 'sign-in-with-essentials' ),
				function () {
					echo '<input type="checkbox" name="siwe_google_use_profile_pic" id="siwe_google_use_profile_pic" value="1" ' . checked( get_option( 'siwe_google_use_profile_pic', true ), true, false ) . ' />';
					echo sprintf('<p class="description"><i>%s</i></p>', wp_kses_data(__( 'You can use google profile thumb as an alternative to gravatar icon by default (Above <code>Save user info</code> checkbox needs to be enabled to use this feature. Also note, that this is not relable, as google forbids frequent loading of that url)',  'sign-in-with-essentials')) );
				}
			] : []),
			[
				'siwe_disable_login_page',
				esc_attr__( 'Disable WP login page and reditect users to Goolge Sign In', 'sign-in-with-essentials' ),
				function () {
					echo '<input type="checkbox" name="siwe_disable_login_page" id="siwe_disable_login_page" value="1" ' . checked( get_option( 'siwe_disable_login_page' ), true, false ) . ' />';
				}
			],
			[
				'siwe_allow_mail_change',
				esc_attr__( 'Allow regular user to change own email', 'sign-in-with-essentials' ),
				function () {
					echo '<input type="checkbox" name="siwe_allow_mail_change" id="siwe_allow_mail_change" value="1" ' . checked( get_option( 'siwe_allow_mail_change' ), true, false ) . ' />';
				}
			],
			[
				'siwe_show_unlink_in_profile',
				esc_attr__( 'Show unlink button in profile', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_show_unlink_in_profile',
						checked( get_option( 'siwe_show_unlink_in_profile' ), true, false ),
						esc_attr__( 'Allow users to unlink their account from google (when you do not want users could control themselves, you should uncheck this option).', 'sign-in-with-essentials' ),
					);
				}
			],
			[
				'siwe_google_custom_redir_url',
				esc_attr__( 'Custom redirect-back url', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_google_custom_redir_url" id="siwe_google_custom_redir_url" type="text" size="50" value="' . esc_attr( $this->parent->siwe_redirect_back_url() ). '" placeholder="'. SIWE_DEFAULT_REDIRECT_PATH . '" />';
					echo sprintf(
						'<p>%s</p>',
						wp_kses_data( __( 'Custom redirect-back url for Google (you can use relative path or even full domain links, like <code>https://example.com/whatever</code>)', 'sign-in-with-essentials' ) ),
					);
				},
				[ 'custom_login_input_validation' ]
			],
			[
				'siwe_expose_class_instance',
				esc_attr__( 'Expose plugin instance', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_expose_class_instance',
						checked( get_option( 'siwe_expose_class_instance', true ), true, false ),
						wp_kses_data( __( 'If you want instantiated class of SignInWithGoogle to be available for other plugins under <code>$GLOBALS[\'SIGN_IN_WITH_ESSENTIALS_INSTANCE_PUBLIC\']</code> and <code>$GLOBALS[\'SIGN_IN_WITH_ESSENTIALS_INSTANCE_ADMIN\']</code>', 'sign-in-with-essentials' ) ),
					);
				}
			],
		];

		foreach ($settings_array as $opts) {
			if (empty($opts)) {
				continue;
			}
			$id = $opts[0];
			add_settings_field( $id, $opts[1], $opts[2], 'siwe_settings', 'siwe_section' );
			register_setting( 'siwe_settings', $id, $opts[3] ?? [] );
		}
	}

	/**
	 * Settings section callback function.
	 *
	 * This function is needed to add a new section.
	 *
	 * @since    1.0.0
	 */
	public function siwe_section() {
		echo sprintf(
			'<p>%s <a href="%s" rel="noopener" target="_blank">%s</a></p>',
			esc_attr__( 'Please paste in the necessary credentials so that we can authenticate your users.', 'sign-in-with-essentials' ),
			'https://wordpress.org/plugins/sign-in-with-essentials/#where%20can%20i%20get%20a%20client%20id%20and%20client%20secret%3F',
			esc_attr__( 'Learn More', 'sign-in-with-essentials' )
		);
	}



	/**
	 * Callback function for validating the form inputs.
	 *
	 * @since    1.0.0
	 * @param string $input The input supplied by the field.
	 */
	public function input_validation( $input ) {

		// Strip all HTML and PHP tags and properly handle quoted strings.
		$sanitized_input = wp_strip_all_tags( stripslashes( $input ) );

		return $sanitized_input;
	}

	/**
	 * Callback function for validating the form inputs.
	 *
	 * @since    1.0.0
	 * @param string $input The input supplied by the field.
	 */
	public function domain_input_validation( $input ) {

		// Strip all HTML and PHP tags and properly handle quoted strings.
		$sanitized_input = wp_strip_all_tags( stripslashes( $input ) );

		if ( '' !== $sanitized_input && ! Sign_In_With_Essentials_Utility::verify_domain_list( $sanitized_input ) ) {

			add_settings_error(
				'siwe_settings',
				esc_attr( 'domain-error' ),
				esc_attr__( 'Please make sure you have a proper comma separated list of domains.', 'sign-in-with-essentials' ),
				'error'
			);
		}

		return $sanitized_input;
	}

	/**
	 * Callback function for validating custom login param input.
	 *
	 * @since    1.0.0
	 * @param string $input The input supplied by the field.
	 */
	public function custom_login_input_validation( $input ) {
		// Strip all HTML and PHP tags and properly handle quoted strings.
		$sanitized_input = wp_strip_all_tags( stripslashes( $input ) );

		return $sanitized_input;
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 */
	public function settings_page_render() {

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// show error/update messages.
		settings_errors( 'siwe_messages' );
		?>
		<div class="wrap">
			<h2><?php esc_attr_e( 'Sign In With Essentials Settings', 'sign-in-with-essentials' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'siwe_settings' ); ?>
				<?php do_settings_sections( 'siwe_settings' ); ?>
				<p class="submit">
					<input name="submit" type="submit" id="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'sign-in-with-essentials' ); ?>" />
				</p>
			</form>
			<div class="metabox-holder">
				<div class="postbox" style="display:flex; justify-content: space-around;">
					<div class="postbox">
						<div class="inside">
							<h3><span><?php esc_attr_e( 'Export Settings', 'sign-in-with-essentials' ); ?></span></h3>
							<p><?php esc_attr_e( 'Export the plugin settings for this site as a .json file.', 'sign-in-with-essentials' ); ?></p>
							<form method="post">
								<p><input type="hidden" name="siwe_action" value="export_settings" /></p>
								<p>
									<?php wp_nonce_field( 'siwe_export_nonce', 'siwe_export_nonce' ); ?>
									<?php submit_button( esc_attr__( 'Export', 'sign-in-with-essentials' ), 'secondary', 'submit', false ); ?>
								</p>
							</form>
						</div><!-- .inside -->
					</div>

					<div class="postbox">
						<div class="inside">
							<h3><span><?php esc_attr_e( 'Import Settings', 'sign-in-with-essentials' ); ?></span></h3>
							<p><?php esc_attr_e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', 'sign-in-with-essentials' ); ?></p>
							<form method="post" enctype="multipart/form-data">
								<p>
									<input type="file" name="import_file"/>
								</p>
								<p>
									<input type="hidden" name="siwe_action" value="import_settings" />
									<?php wp_nonce_field( 'siwe_import_nonce', 'siwe_import_nonce' ); ?>
									<?php submit_button( esc_attr__( 'Import', 'sign-in-with-essentials' ), 'secondary', 'submit', false ); ?>
								</p>
							</form>
						</div><!-- .inside -->
					</div><!-- .postbox -->
				</div><!-- .postbox -->
			</div><!-- .metabox-holder -->
		</div>

		<?php

	}

	/**
	 * Redirect the user to get authenticated by Google.
	 *
	 * @since    1.0.0
	 */
	public function google_auth_redirect() {

		// Gather necessary elements for 'state' parameter.
		$redirect_to = sanitize_url ($this->parent->siwe_array_value($_GET, 'redirect_to'));

		$this->state = array(
			'redirect_to' => $redirect_to,
		);

		$url = $this->google_auth->get_google_auth_url( $this->state );
		wp_redirect( $url );
		exit;
	}

	/**
	 * Uses the code response from Google to authenticate the user.
	 *
	 * @since 1.0.0
	 */
	public function check_authenticate_user($code = null, $state = null, $redirect_after_login = true) {
		// Handle Google's response before anything is rendered.
		$redir_url = $this->parent->siwe_redirect_back_url();
		$is_query = str_contains ($redir_url, '?' );
		$contains_domain = str_contains( $redir_url, '://' );

		if (
			// if it contains another domain, then we don't need to check
			!$contains_domain &&
			isset( $_GET['code'] ) &&
			(
				(   $is_query && isset( $_GET[ str_replace('?', '', $redir_url) ] ) )
					||
				( ! $is_query && str_starts_with( sanitize_url( $this->parent->siwe_array_value ($_SERVER, 'REQUEST_URI') ), $redir_url ) )
			)
		)
		{
			$this->authenticate_user( $code, $state, $redirect_after_login );
		}
	}

	public function authenticate_user($code = null, $state = null, $redirect_after_login = true) {

		$params = [];
		if (empty($code) && empty($_GET['code'])) {
			throw new Exception('No code provided');
		}
		if (empty($state) && empty($_GET['state'])) {
			throw new Exception('No state provided');
		}
		$params['code'] = $code ?: sanitize_text_field( $this->parent->siwe_array_value( $_GET, 'code' ));
		$params['state'] = $state ?: sanitize_text_field( $this->parent->siwe_array_value( $_GET, 'state' ));
		$params['redirect_after_login'] = true;

		$access_token = $this->set_access_token( $params['code'] );
		$this->user = $this->get_user_by_token( $access_token );

		if (!$this->user) {
			// Something went wrong, redirect to the login page.
			return ['error'=>'Could not validate user'];
		}

		// If the user is logged in, just connect the authenticated Google account.
		if ( is_user_logged_in() ) {

			// link the account.
			$this->connect_account( $this->user->email );

		} else {

			// Check if a user is linked to this Google account.
			$linked_user = get_users(
				array(
					'meta_key'   => 'siwe_google_account',
					'meta_value' => $this->user->email,
				)
			);

			// If user is linked to Google account, sign them in. Otherwise, check the domain
			// and create the user if necessary.
			$validUser = null;
			if ( ! empty( $linked_user ) ) {

				$validUser = $linked_user[0];
			} else {

				$result_user = $this->find_by_email_or_create( $this->user );

				if (is_int($result_user)) {
					$validUser = get_user_by( 'id', $result_user );
					// link the account.
					$this->connect_account( $this->user->email, $validUser );
				}
			}

			if ( $validUser ) {

				wp_set_current_user( $validUser->ID, $validUser->user_login );
				wp_set_auth_cookie( $validUser->ID );
				do_action( 'wp_login', $validUser->user_login, $validUser ); // phpcs:ignore
			}
		}

		// Decode passed back state.
		$state     = json_decode( base64_decode( $params['state']) );

		$redirect = !empty($state->my_redirect_uri ) ? $state->my_redirect_uri  : admin_url('profile.php?siwe_redirected'); // Send users to the dashboard by default.

		// If user is linked to Google account, sign them in. Otherwise, check the domain
		// and create the user if necessary.
		$user = null;
		if ( ! empty( $linked_user ) ) {
			$user = $linked_user[0];
		} else {
			$this->check_allowed_domains();
			$user = $this->find_by_email_or_create( $this->user );
		}

		// Log in the user.
		if ( $user ) {
			$validUser = $user;
		}

		if ( $validUser ) {

			wp_set_current_user( $validUser->ID, $validUser->user_login );
			wp_set_auth_cookie( $validUser->ID );
			do_action( 'wp_login', $validUser->user_login, $validUser ); // phpcs:ignore

			if ( (bool) get_option ('siwe_google_save_userinfo') ) {
				update_user_meta ( $validUser->ID, 'siwe_google_userinfo', apply_filters( 'siwe_saved_google_userinfo', $this->user ) );
				$this->check_and_update_profile_pic ($validUser->ID, $this->user);
			}
		}

		if ( isset( $state->redirect_to ) && '' !== $state->redirect_to ) {
			$redirect_to = $state->redirect_to;
		} else {
			$redirect_to = admin_url(); // Send users to the dashboard by default.
		}

		if ( !array_key_exists('redirect_after_login_url', $params) ) {
			$params['redirect_after_login_url'] = $redirect;
		}
		if ( !$params['redirect_after_login'] ) {
			return $params['redirect_after_login_url'];
		}

		$requested_redirect_to = sanitize_url ($this->parent->siwe_array_value ($_REQUEST, 'redirect_to'));
		if ( empty ($requested_redirect_to) )
			$requested_redirect_to = $params['redirect_after_login_url'] ;

		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user ); // phpcs:ignore

		if ($redirect_after_login && !empty($redirect_to)) {
			wp_redirect( apply_filters( 'siwe_google_redirect_after_login_url', $redirect_to ) ); //phpcs:ignore
			exit;
		}
		return ['error'=>null, 'redirecting_to'=>$redirect_to];
	}

	/**
	 * Displays a message to the user if domain restriction is in use and their domain does not match.
	 *
	 * @since    1.0.0
	 * @param string $message The message to show the user on the login screen.
	 */
	public function allowed_domains_error( $message ) {
		// translators: The required domain.
		$message = '<div id="login_error"> ' . sprintf( __( 'You must have an email with a required domain (<strong>%s</strong>) to log in to this website using Google.', 'sign-in-with-essentials' ), get_option( 'siwe_google_allowed_domains' ) ) . '</div>';
		return $message;
	}

	/**
	 * Process a settings export that generates a .json file of the shop settings
	 */
	public function process_settings_export() {

		if ( empty( $_POST['siwe_action'] ) || 'export_settings' !== $_POST['siwe_action'] ) {
			return;
		}

		if ( ! wp_verify_nonce( $this->parent->siwe_array_value ($_POST, 'siwe_export_nonce'), 'siwe_export_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = array(
			'siwe_google_client_id'               => get_option( 'siwe_google_client_id' ),
			'siwe_google_client_secret'           => get_option( 'siwe_google_client_secret' ),
			'siwe_user_default_role'       => get_option( 'siwe_user_default_role' ),
			'siwe_google_allowed_domains'      => get_option( 'siwe_google_allowed_domains' ),
			'siwe_google_email_sanitization'      => get_option( 'siwe_google_email_sanitization' ),
			'siwe_allow_registration_even_if_disabled' => get_option( 'siwe_allow_registration_even_if_disabled' ),
			'siwe_show_unlink_in_profile'         => get_option( 'siwe_show_unlink_in_profile' ),
			'siwe_show_on_login'                  => get_option( 'siwe_show_on_login' ),
			'siwe_allow_mail_change'              => get_option( 'siwe_allow_mail_change' ),
			'siwe_google_custom_redir_url'        => $this->parent->siwe_redirect_back_url(),
			'siwe_expose_class_instance'          => get_option( 'siwe_expose_class_instance', true ),
		);
		if ($this->enable_google_pic) {
			$settings['siwe_google_use_profile_pic'] = get_option( 'siwe_google_use_profile_pic', true );
		}

		ignore_user_abort( true );

		nocache_headers();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=siwe-settings-export-' . gmdate( 'm-d-Y' ) . '.json' );
		header( 'Expires: 0' );

		echo wp_json_encode( $settings );
		exit;
	}

	/**
	 * Process a settings import from a json file
	 */
	public function process_settings_import() {

		if ( empty( $_POST['siwe_action'] ) || 'import_settings' !== $_POST['siwe_action'] ) {
			return;
		}

		if ( ! wp_verify_nonce( $this->parent->siwe_array_value ($_POST, 'siwe_import_nonce'), 'siwe_import_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if (! isset($_FILES['import_file'] ) || ! isset($_FILES['import_file']['name'] ) || ! isset($_FILES['import_file']['tmp_name'] )) {
			wp_die( esc_attr__( 'Please upload a file to import', 'sign-in-with-essentials' ) );
		}

		$path = sanitize_file_name ($_FILES['import_file']['name']);
		$extension = end( explode( '.', $path) );

		if ( 'json' !== $extension ) {
			wp_die( esc_attr__( 'Please upload a valid .json file', 'sign-in-with-essentials' ) );
		}

		$file = sanitize_file_name ($_FILES['import_file']['tmp_name']);
		// Retrieve the settings from the file and convert the json object to an array.
		$content = file_get_contents( $file ); // phpcs:ignore
		$settings = (array) json_decode( $content );

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=siwe_settings' ) );

		exit;
	}

	/**
	 * Sets the access_token using the response code.
	 *
	 * @since 1.0.0
	 * @param string $code The code provided by Google's redirect.
	 *
	 * @return mixed Access token on success or WP_Error.
	 */
	protected function set_access_token( $code = '' ) {

		if ( ! $code ) {
			throw new \Exception ( 'No authorization code provided.' );
		}

		// Sanitize auth code.
		$code = sanitize_text_field( $code );

		$custom_redir_url = $this->parent->siwe_redirect_back_url();

		$final_redir_url = str_contains( $custom_redir_url, '://' ) ? $custom_redir_url : site_url( $custom_redir_url );

		$args = array(
			'body' => array(
				'code'          => $code,
				'client_id'     => get_option( 'siwe_google_client_id' ),
				'client_secret' => get_option( 'siwe_google_client_secret' ),
				'redirect_uri'  => $final_redir_url,
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
	 * Add usermeta for current user and Google account email.
	 *
	 * @since 1.3.1
	 * @param string $email The users authenticated Google account email.
	 */
	protected function connect_account( $email = '', $wp_user = null ) {

		if ( ! $email ) {
			return false;
		}

		$current_user = $wp_user ?: wp_get_current_user();

		if ( ! ( $current_user instanceof WP_User ) ) {
			return false;
		}

		return add_user_meta( $current_user->ID, 'siwe_google_account', $email, true );
	}

	/**
	 * Remove usermeta for current user and Google account email.
	 *
	 * @since 1.3.1
	 */
	public function disconnect_account() {

		if ( !isset( $_POST['_siwe_account_nonce'] ) ) {
			return;
		}
		// if user not allowed to unlink, then return
		if ( ! current_user_can( 'manage_options' ) && ! get_option( 'siwe_show_unlink_in_profile' ) )
			return;

		if (! wp_verify_nonce( $this->parent->siwe_array_value ($_POST, '_siwe_account_nonce'), 'siwe_unlink_account' ) ) {
			wp_die( esc_attr__( 'Unauthorized', 'sign-in-with-essentials' ) );
		}

		$current_user = wp_get_current_user();

		if ( ! ( $current_user instanceof WP_User ) ) {
			return false;
		}

		return delete_user_meta( $current_user->ID, 'siwe_google_account' );
	}

	/**
	 * Gets a user by email or creates a new user.
	 *
	 * @since 1.0.0
	 * @param object $user_data  The Google+ user data object.
	 */
	protected function find_by_email_or_create( $user_data ) {

		$user                           = get_user_by( 'email', $user_data->email );
		$user_email 					= $user_data->email;
		if ((bool) get_option( 'siwe_google_email_sanitization', true )) {
			$user_email = Sign_In_With_Essentials_Utility::sanitize_google_email( $user_email );
		}

		// The user doesn't have the correct domain, don't authenticate them.
		$restrict_to_domains = array_filter( explode( ', ', get_option( 'siwe_google_allowed_domains' ) ) );
		$user_domain = explode( '@', $user_email );

		$is_forbidden_domain = ! empty($restrict_to_domains) && ! in_array( $user_domain[1], $restrict_to_domains, true );
		$is_whitelisted_domain = ! empty($restrict_to_domains) && in_array( $user_domain[1], $restrict_to_domains, true );

		if ( $is_forbidden_domain ) {
			wp_redirect( apply_filters( 'siwe_forbidden_registration_redirect', wp_login_url() . '?google_login=incorrect_domain' ) );
			exit;
		}

		$user = get_user_by( 'email', $user_email );

		// Redirect the user if registrations are disabled and there is no domain user registration override.
		$forbid_registration =
			! $user &&
			(
				! get_option( 'users_can_register' )
					&&
				! get_option( 'siwe_allow_registration_even_if_disabled' )
					&&
				( empty($restrict_to_domains) || ! $is_whitelisted_domain )
			);

		if ( $forbid_registration ) {
			wp_redirect( apply_filters( 'siwe_forbidden_registration_redirect', site_url( 'wp-login.php?registration=disabled' ) ) );
			exit;
		}

		// hooked to disallow user login/registration (i.e. banned emails) from external codes
		if ( ! apply_filters( 'siwe_permit_authorization', true, $user_data, $user, $user_email ) ) {
			wp_redirect( apply_filters( 'siwe_forbidden_registration_redirect', site_url( 'wp-login.php?registration=disabled&userstatus=disallowed' ) ) );
			exit;
		}

		if ( false !== $user ) {
			update_user_meta( $user->ID, 'first_name', $user_data->given_name );
			update_user_meta( $user->ID, 'last_name', $user_data->family_name );
			return $user;
		}

		$pass_length  = (int) apply_filters( 'siwe_password_length', 16 );
		$user_pass    = wp_generate_password( max(12, $pass_length) );
		$user_email   = $user_data->email;
		// set username as friendly as possible
		$user_email_data = explode( '@', $user_email );
		$user_login      = $user_email_data[0];
		while ( username_exists($user_login) ) {
			$user_login  = $user_login . wp_rand(1,10);
		}

		$user = array(
			'user_pass'       => $user_pass,
			'user_login'      => $user_email, //$user_login
			'user_email'      => $user_email,
			'display_name'    => $user_data->given_name . ' ' . $user_data->family_name,
			'first_name'      => $user_data->given_name,
			'last_name'       => $user_data->family_name,
			'user_registered' => gmdate( 'Y-m-d H:i:s' ),
			'role'            => get_option( 'siwe_user_default_role', 'subscriber' ),
		);
		$user = apply_filters ('siwe_pre_insert_user', $user, $user_data);
		$new_user_id = wp_insert_user( $user );
		do_action ('siwe_after_new_user_insert', $new_user_id );

		if ( is_wp_error( $new_user_id ) ) {
			do_action ('siwe_new_user_creation_error', $new_user_id );
			wp_die( wp_kses_data( $new_user_id->get_error_message() ) . ' <a href="' . esc_url( wp_login_url() ). '">Return to Log In</a>' );
			return false;
		} else {
			return $new_user_id;
		}

	}


	/**
	 * Check & update user's profile pic
	 *
	 * @since 1.2.0
	 *
	 * @param string $user_id The user's id
	 * @param object $user_data Obtained user-data from google
	 */
	protected function check_and_update_profile_pic( $user_id, $user_data ) {
		if (!$this->enable_google_pic || ! get_option('siwe_google_use_profile_pic', true)) return;

		if ( property_exists( $user_data, 'picture' ) ) {
			// todo: instead of below, we need to download and save the thumb locally for user
			// update_user_meta( $user_id, 'siwe_profile_image', $user_data->picture);
		}
	}

	function slug_get_avatar( $avatar, $id_or_email_or_object, $size, $default, $alt ) {
		if (!$this->enable_google_pic || ! get_option('siwe_google_use_profile_pic', true)) $avatar;

		$uid = null;
		if (is_numeric ($id_or_email_or_object)) {
			$uid = $id_or_email_or_object;
		} else if( is_string ($id_or_email_or_object) && is_email( $id_or_email_or_object ) ){
			$user = get_user_by( 'email', $id_or_email_or_object );
			if( $user ){
				$uid = $user->ID;
			}
		}

		//if not user ID, return
		if( ! is_numeric( $uid ) ){
			return $avatar;
		}

		// Find URL of saved avatar
		$saved = get_user_meta( $uid, 'siwe_profile_image', true );

		//check if it is a URL
		if( filter_var( $saved, FILTER_VALIDATE_URL ) ) {
			//return saved image
			$result = preg_replace ('/src=(\'|")(.*?)(\'|")/', 'src=\''. esc_url( $saved ) .'\'', $avatar);
			return $result;
		}

		//return normal
		return $avatar;
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

	/**
	 * Checks if the user has the right email domain.
	 *
	 * @since 1.2.0
	 */
	protected function check_allowed_domains() {
		// The user doesn't have the correct domain, don't authenticate them.
		$domains     = array_filter( explode( ', ', get_option( 'siwe_google_allowed_domains' ) ) );
		$user_domain = explode( '@', $this->user->email );

		if ( ! empty( $domains ) && ! in_array( $user_domain[1], $domains, true ) ) {
			wp_redirect( wp_login_url() . '?google_login=incorrect_domain' );
			exit;
		}
	}


	/**
	 * Disable Login page & redirect directly to google login
	 *
	 * @since 1.3.1
	 */
	public function check_login_redirection()
	{
		if ( boolval( get_option( 'siwe_disable_login_page' ) ) )
		{
			// Skip only logout action
			$action = $this->parent->siwe_array_value ($_REQUEST, 'action');
			if ( ! empty( $action ) &&  ! in_array( trim( strtolower( $action )), ["logout", "registration"] ) ) {
				$this->google_auth_redirect();
			}
		}
	}



	/**
	 * Disable User email modifications
	 *    https://wordpress.stackexchange.com/a/363376/33667
	 * @since 1.3.1
	 */
	public function disallow_email_changes()
	{
		if ( ! current_user_can( 'manage_options' ) && ! get_option('siwe_allow_mail_change') )
		{

			add_action( 'admin_print_scripts', function() {
				if (defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE) {
					?><script> document.addEventListener('DOMContentLoaded', event => {
						document.querySelector("#your-profile #email").setAttribute("disabled", "disabled");
						document.querySelector("#email-description").innerHTML = "<p><i><?php esc_attr_e( 'Email address change is disabled by administrator', 'sign-in-with-essentials'); ?></i></p>";
					});
					</script><?php
				}
			});

			add_action( 'personal_options_update',
				function ($user_id) {
					if ( !current_user_can( 'manage_options' ) ) {
						$user = get_user_by('id', $user_id );
						$_POST['email'] = $user->user_email; // reset back to original, so user can't modify
					}
				},
				5
			);
		}
	}

}
