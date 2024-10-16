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

	private $plugin_name;
	private $version;

	/**
	 * Main plugin class
	 *
     * @var Sign_In_With_Essentials
     */
	private $parent;

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
		$this->init_hooks();
	}

	public function init_hooks() {
		$this->parent->add_action( 'admin_init', $this, 'settings_api_init' );
		$this->parent->add_action( 'admin_menu', $this, 'settings_menu_init' );
		$this->parent->add_action( 'admin_enqueue_scripts', $this, 'enqueue_styles' );
		$this->parent->add_action( 'admin_init', $this, 'disallow_email_changes' );
		$this->parent->add_action( 'admin_init', $this, 'process_settings_export' );
		$this->parent->add_action( 'admin_init', $this, 'process_settings_import' );
		$this->parent->add_action( 'admin_init', $this, 'check_unlink_account' );
		$this->parent->add_action( 'edit_user_profile', $this, 'add_connect_button_to_profile' );
		$this->parent->add_action( 'show_user_profile', $this, 'add_connect_button_to_profile' );
		$this->parent->add_filter( 'plugin_action_links_' . $this->plugin_name . '/' . $this->plugin_name . '.php', $this, 'add_action_links' );
		// $this->parent->add_filter( 'get_avatar', $this, 'slug_get_avatar', 10, 5 ); // removed: pastebin/Bn6VKmZn
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
				'siwe_enable_google',
				__( 'Enable Google sign-in', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_enable_google',
						checked( get_option( 'siwe_enable_google' ), true, false ),
						esc_attr__( '', 'sign-in-with-essentials' ),
					);
				}
			],
			[
				'siwe_google_client_id',
				__( 'Google Client ID', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_google_client_id" id="siwe_google_client_id" type="text" size="50" value="' . esc_attr( get_option( 'siwe_google_client_id' ) ) . '"/>';
				},
				[ $this, 'input_validation' ],
			],
			[
				'siwe_google_client_secret',
				__( 'Google Client Secret', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_google_client_secret" id="siwe_google_client_secret" type="text" size="50" value="' . esc_attr( get_option( 'siwe_google_client_secret' ) ) . '"/>';
				},
				[ $this, 'input_validation' ]
			],
			[
				'siwe_enable_microsoft',
				__( 'Enable Microsoft sign-in', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_enable_microsoft',
						checked( get_option( 'siwe_enable_microsoft' ), true, false ),
						esc_attr__( '', 'sign-in-with-essentials' ),
					);
				},
			],
			[
				'siwe_microsoft_client_id',
				__( 'Microsoft Client ID', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_microsoft_client_id" id="siwe_microsoft_client_id" type="text" size="50" value="' . esc_attr( get_option( 'siwe_microsoft_client_id' ) ) . '"/>';
				},
				[ $this, 'input_validation' ],
			],
			[
				'siwe_microsoft_client_secret',
				__( 'Microsoft Client Secret value', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_microsoft_client_secret" id="siwe_microsoft_client_secret" type="text" size="50" value="' . esc_attr( get_option( 'siwe_microsoft_client_secret' ) ) . '"/>'.
					__( '(You can read plugin readme to find out more instructions)', 'sign-in-with-essentials' );
					'<br /><br /><hr /><br /><br />';
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
						esc_attr__( 'If registrations are disabled in site settings &gt; general, people will be still regsitered if they successfully login with this plugin', 'sign-in-with-essentials' ),
					);
				}
			],
			[
				'siwe_allowed_domains',
				__( 'Restrict To Domain', 'sign-in-with-essentials' ),
				function () {
					// Get the TLD and domain.
					$siwe_urlparts    = wp_parse_url( site_url() );
					$siwe_domain      = $siwe_urlparts['host'];
					$siwe_domainparts = explode( '.', $siwe_domain );
					// fix for localhost
					$siwe_domain = count( $siwe_domainparts ) === 1 ? $siwe_domainparts[0] : $siwe_domainparts[ count( $siwe_domainparts ) - 2 ] . '.' . $siwe_domainparts[ count( $siwe_domainparts ) - 1 ];

					?>
					<input name="siwe_allowed_domains" id="siwe_allowed_domains" type="text" size="50" value="<?php echo esc_html( get_option( 'siwe_allowed_domains' ) ); ?>" placeholder="<?php echo esc_attr( $siwe_domain ); ?>">
					<p class="description"><?php esc_html_e( 'Enter the domains (comma separated) to restrict new users email addresses to end with (eg. `example.com` permits mails like `someone@example.com`) or leave blank to allow all to register', 'sign-in-with-essentials' ); ?></p>
					<?php
				},
				[ $this, 'domain_input_validation' ]
			],
			[
				'siwe_email_sanitization_google',
				esc_attr__( 'Sanitize email addresses', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_email_sanitization_google',
						checked( get_option( 'siwe_email_sanitization_google', true ), true, false ),
						wp_kses_data( __('(Currently works on Google emails only) If enabled, user emails will be sanitized during registration to the base unique account (like <code>james.figard+123@gmail.com</code> to <code>jamesfigard@gmail.com</code> so you can avoid unlimited duplicate/spam registration from gmail aliases).', 'sign-in-with-essentials' )),
					);
				}
			],
			[
				'siwe_show_on_login',
				esc_attr__( 'Show Sign-in Buttons on Login Form', 'sign-in-with-essentials' ),
				function () {
					echo '<input type="checkbox" name="siwe_show_on_login" id="siwe_show_on_login" value="1" ' . checked( get_option( 'siwe_show_on_login' ), true, false ) . ' />';
				}
			],
			[
				// SEE EXAMPLE JSON RESPONSE IN `get_user_by_access_token` FUNCTION
				'siwe_save_remote_info',
				esc_attr__( 'Save received extra user info', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_save_remote_info',
						checked( get_option( 'siwe_save_remote_info' ), true, false ),
						esc_attr__( 'If you make use of them, some extra user info in addition to email (eg. full name, language, id, profile-picture and other peripherical infos), will be saved in user-metadatas.', 'sign-in-with-essentials' ),
					);
				}
			],
			// [
			// 	'siwe_disable_login_page',
			// 	esc_attr__( 'Disable WP login page and reditect users to Goolge Sign In', 'sign-in-with-essentials' ),
			// 	function () {
			// 		echo '<input type="checkbox" name="siwe_disable_login_page" id="siwe_disable_login_page" value="1" ' . checked( get_option( 'siwe_disable_login_page' ), true, false ) . ' />';
			// 	}
			// ],
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
						esc_attr__( 'Allow users to unlink their account from linked provider (when you do not want users could control themselves, you should uncheck this option).', 'sign-in-with-essentials' ),
					);
				}
			],
			[
				'siwe_custom_redir_url',
				esc_attr__( 'Custom redirect-back url', 'sign-in-with-essentials' ),
				function () {
					echo '<input name="siwe_custom_redir_url" id="siwe_custom_redir_url" type="text" size="50" value="' . esc_attr( $this->parent->siwe_redirect_back_url() ). '" placeholder="/'. SIWE_DEFAULT_REDIRECT_PATH . '" />';
					echo sprintf(
						'<p>%s</p>',
						wp_kses_data( __( 'Custom redirect-back url used in Provider\'s "Redirect Back Urls" settings page (you can use relative path or even full domain links, like <code>https://example.com/whatever</code>)', 'sign-in-with-essentials' ) ),
					);
				},
				[ 'input_validation' ]
			],
			[
				'siwe_expose_class_instance',
				esc_attr__( 'Expose plugin instance', 'sign-in-with-essentials' ),
				function () {
					echo sprintf(
						'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /><p class="description">%3$s</p>',
						'siwe_expose_class_instance',
						checked( get_option( 'siwe_expose_class_instance', true ), true, false ),
						wp_kses_data( __( 'If you want instantiated class of this plugin to be available for other plugins under <code>$GLOBALS[\'SIGN_IN_WITH_ESSENTIALS_INSTANCE_PUBLIC\']</code> and <code>$GLOBALS[\'SIGN_IN_WITH_ESSENTIALS_INSTANCE_ADMIN\']</code>', 'sign-in-with-essentials' ) ),
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
			'https://wordpress.org/plugins/sign-in-with-essentials/#faq-header',
			esc_attr__( 'Learn More', 'sign-in-with-essentials' )
		);
	}


	/**
	 * Process a settings export that generates a .json file of the shop settings
	 */
	public function process_settings_export() {

		if ( empty( $_POST['siwe_action'] ) || 'export_settings' !== $_POST['siwe_action'] ) {
			return;
		}

		if ( ! wp_verify_nonce( $this->parent->value ($_POST, 'siwe_export_nonce'), 'siwe_export_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = array(
			'siwe_enable_google'               => get_option( 'siwe_enable_google' ),
			'siwe_enable_microsoft'               => get_option( 'siwe_enable_microsoft' ),
			'siwe_google_client_id'               => get_option( 'siwe_google_client_id' ),
			'siwe_google_client_secret'           => get_option( 'siwe_google_client_secret' ),
			'siwe_user_default_role'       		=> get_option( 'siwe_user_default_role' ),
			'siwe_allowed_domains'     	 => get_option( 'siwe_allowed_domains' ),
			'siwe_email_sanitization_google'      => get_option( 'siwe_email_sanitization_google' ),
			'siwe_allow_registration_even_if_disabled' => get_option( 'siwe_allow_registration_even_if_disabled' ),
			'siwe_show_unlink_in_profile'         => get_option( 'siwe_show_unlink_in_profile' ),
			'siwe_show_on_login'                  => get_option( 'siwe_show_on_login' ),
			'siwe_allow_mail_change'              => get_option( 'siwe_allow_mail_change' ),
			'siwe_custom_redir_url'        => $this->parent->siwe_redirect_back_url(),
			'siwe_expose_class_instance'          => get_option( 'siwe_expose_class_instance', true ),
		);

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

		if ( ! wp_verify_nonce( $this->parent->value ($_POST, 'siwe_import_nonce'), 'siwe_import_nonce' ) ) {
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
	 * Add "Link with" button to user profile settings.
	 *
	 * @since 1.3.1
	 */
	public function add_connect_button_to_profile() {
		$is_manager = current_user_can( 'manage_options' );
		$user_id = get_current_user_id();
		$is_self_profile_page = defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE;
		$is_managed = $is_manager && !$is_self_profile_page && isset($_GET['user_id']);
		if ($is_managed) {
			$user_id = intval($_GET['user_id']);
		}
		$array = ['google', 'microsoft'];
		foreach ($array as $provider) {
			$linked_account = get_user_meta( $user_id, 'siwe_account_'.$provider, true );
			?>
			<h2><?php esc_attr_e( 'Sign In With '.ucfirst($provider), 'sign-in-with-essentials' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php if (!$is_managed) { esc_attr_e( 'Connect', 'sign-in-with-essentials' ); } ?></th>
					<td>
					<?php if ( $linked_account ) : ?>
						<?php echo esc_attr( $linked_account ); ?>
						<?php if ( $is_manager || get_option( 'siwe_show_unlink_in_profile' ) ) { ?>
							<a class="unlink-button" target="_blank" href="<?php echo esc_attr( admin_url()); ?>?siwe_unlink_account=<?php echo esc_attr( $provider ); ?>&nonce_value=<?php echo wp_create_nonce( 'siwe_unlink_account'); ?>&target_user=<?php echo $user_id;?>&provider=<?php echo $provider;?>"><?php esc_attr_e( 'Unlink', 'sign-in-with-essentials' ); ?></a>
						<?php } ?>
					<?php elseif (!$is_managed) : ?>
						<a class="ConnectWithSiweButton" href="<?php echo esc_attr( site_url( '?siwe_auth_redirect='.$provider ) ); ?>"><?php esc_attr_e( 'Connect to '.ucfirst($provider), 'sign-in-with-essentials' ); ?></a>
						<span class="description"><?php esc_attr_e( 'Connect your user profile so you can sign in with '.ucfirst($provider), 'sign-in-with-essentials' ); ?></span>
					<?php else: ?>
						<span class="description"><?php esc_attr_e( 'Not linked', 'sign-in-with-essentials' ); ?></span>
					<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	/**
	 * Remove usermeta for current user and unlink account.
	 *
	 * @since 1.3.1
	 */
	public function check_unlink_account() {

		if ( !isset( $_GET['siwe_unlink_account'] ) ) {
			return;
		}
		$is_manager = current_user_can( 'manage_options' );

		// if user not allowed to unlink, then return
		if ( ! $is_manager  && ! get_option( 'siwe_show_unlink_in_profile' ) )
			return;

		if (! wp_verify_nonce( $this->parent->value ($_GET, 'nonce_value'), 'siwe_unlink_account' ) ) {
			wp_die( esc_attr__( 'Unauthorized', 'sign-in-with-essentials' ) );
		}

		$user_id = get_current_user_id();
		if ($is_manager && isset($_GET['target_user'])) {
			$user_id = intval($_GET['target_user']);
		}
		if ( ! $user_id ) {
			return;
		}

		$provider = sanitize_key( $_GET['provider'] );
		$this->parent->plugin_handlers->unlink_account( $user_id, $provider);
		exit('unlinked');
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
