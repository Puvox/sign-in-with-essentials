=== Sign In With Socials (Google, Apple, Microsoft) ===
Contributors: puvoxsoftware, ttodua
Tags: Google, Apple, microsoft, login, register
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.4.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds functionality "Sign in with" Google/Microsoft/Apple (beta version)

== Description ==

Allow users to login with Google/Microsoft/Apple accounts. Lightweight, no bloatware packages included.
Useful for sites that need a quick way for their users to sign-in.

= Available Options =
See all available options and their description on plugin's settings page. Here are some of them:
* Show/Hide the "Sign In with" button on the login form
* If a user is not already registered, during sign-in an account can be created for that email address (aliases are not allowed by default)
* If a user is already logged in to target social provider, they will be automatically redirected without much fuss
* Restrict users to be coming from only specific domain(s)
* Connect existing user accounts.
* WP-CLI available! See `/src/includes/class-wp-cli.php` header for supported list.
* One redirect-back link `https://YOURDOMAIN.TLD/_AUTH_RESPONSE_SIWE_` for all providers.

= Programmatic access =
Public functions:
* `siwe_authenticate_user($code, $state, $error = null)`
* `siwe_get_auth_url()`
* `siwe_get_buttons()`

Hooks:
* dozens of hooks, look into any source file to find out specific part


== Installation ==

A) Enter your website "Admin Dashboard > Plugins > Add New" and enter the plugin name
or
B) Download plugin from WordPress.org, Extract the zip file and upload the container folder to "wp-content/plugins/"

== Frequently Asked Questions ==

= Where can I get a Client ID & Secret for Google? =

You will need to sign in to the [Google Developer Console](https://console.developers.google.com)

1. Go to the API Console.
2. From the projects list, select a project or create a new one.
3. If the APIs & services page isn't already open, open the console left side menu and select APIs & services.
4. On the left, click Credentials > New > OAuth client ID.


= Where can I get a Client ID & Secret for Microsoft ? =

These resources would help:

- [Azure Portal](https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
- Publisher verification: [overview](https://learn.microsoft.com/en-us/entra/identity-platform/publisher-verification-overview) and [partner portal](https://partner.microsoft.com/dashboard/v2/account-settings/account-management/home)
- [authentication-oauth-register](https://learn.microsoft.com/en-us/advertising/guides/authentication-oauth-register)
- [faq - 2fa](https://learn.microsoft.com/en-us/answers/questions/799042/adding-mfa-to-administrator-accounts-with-the-free)


= Where can I get a Client ID & Secret for Apple ? =

These resources would help:

- [Developer Center](https://developer.apple.com/)
- [How to generate keys](https://developer.apple.com/help/account/configure-app-capabilities/create-a-sign-in-with-apple-private-key)
- [team id](https://developer.apple.com/account/#/membership/) && [key id](https://developer.apple.com/account/resources/authkeys/list)
- Youtube videos: [1](https://www.youtube.com/watch?v=UafqYgRoIC0) or [2](https://www.youtube.com/watch?v=Deyt6dJAjbE) or [3](https://nextendweb.com/nextend-social-login-docs/provider-apple/) or [4](https://plugins.miniorange.com/login-with-apple-using-wordpress-social-login)


= [Apple only] workaround for localhost =
**Apple Sign In** service does not work with `localhost`, `http` or IP-only domains, so if you develop on local host, so follow below steps:

**STEP 1**
instead of `localhost` you will need to invent some placeholder domain (anything except `example.com`) e.g. `site.com` and use that domain instead of `localhost` in Apple Redirect-Back urls. Then, to redirect `site.com` to your localhost, use either (A) or (B) choice:
- (A) Use redirection extension (like [this](https://github.com/einaregilsson/Redirector) or others) in browser to redirect queries from `site.com` to `localhost`, and then jump to **STEP 2** below.
or
- (B) Add virtual local domain name e.g. `site.com` (except `example.com`) pointing to `127.0.0.1`. See the 1-3 lines about "How to setup virtual host": https://gist.github.com/ttodua/b5f54429c00dad6e052b6ccbda08dcb0#file-readme-md . However, if you are not able to change your wordpress installation domain from `localhost` at this moment, then use this code from **STEP 2**

**STEP 2**
Add this code somewhere (eg. functions.php) to replace the redirect back url for Apple specifically:
`add_filter('siwe_redirect_back_uri', function ($url, $provider) { return ($provider === 'apple' ? 'https://site.com/_AUTH_RESPONSE_SIWE_' : $url); }, 10, 2);`


= Notes about 3rd party services =
- This plugin relies on external services, namely:
- - **Google Sign In** service: View [service description](https://developers.google.com/identity/gsi/web/guides/overview) and [terms](https://developers.google.com/terms). To revise the connected services, visit [here](https://myaccount.google.com/connections).
- - **Microsoft Identity** services: View [service description](https://learn.microsoft.com/en-us/entra/identity-platform/) and [terms](https://learn.microsoft.com/en-us/legal/termsofuse). To revise the connected services, visit [here](https://account.live.com/consent/Manage) or [here](https://account.microsoft.com/privacy/app-access)
- - **Apple Sign In** services: View [service description](https://developer.apple.com/documentation/sign_in_with_apple). To revise the connected services, visite [here](https://account.apple.com/account/manage)
- - Also uses some composer vendor dependencies, which are beyong our monitoring or control.

= Github =
- Active plugin development is handled on [Github](https://www.github.com/puvox/sign-in-with-essentials). Bugs and issues will be tracked and handled there.

= Todo =
- account-id based duplicate detection


== Screenshots ==

1. The login form with the "Sign in with" button added.
2. This is the second screen shot

== Changelog ==

= 1.0.1 =
* Pushed a completely reorganized version with dozens of changes

= 1.0.0 =
* Initial Release (plugin based on https://github.com/tarecord/sign-in-with-google )
