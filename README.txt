=== Sign In With Essentials ===
Contributors: puvoxsoftware, ttodua
Tags: Google, Apple, microsoft, login, register
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.2.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds functionality "Sign in with" Google/Microsoft/Apple (beta version)

== Description ==

This plugin (beta version) gives your users the ability to sign in with their Google/Microsoft/Apple accounts. (**Apple Sign In** is not yet integrated).
This is great for Agencies or sites that have lots of users and need a way to make signing in a quick and painless process.

= Available Options =
See all available options and their description on plugin's settings page. Here are some of them:
* Show/Hide the "Sign In with" button on the login form
* If a user is not already registered, during sign-in an account can be created for that email address (aliases are not allowed by default)
* If a user is already logged in to target social provider, they will be automatically redirected without much fuss
* Restrict users to be coming from only specific domain(s)
* Connect existing user accounts.
* Programmatic hooks & filters

= Notes =
- Active plugin development is handled on [Github](https://www.github.com/puvox/sign-in-with-essentials). Bugs and issues will be tracked and handled there.
- This plugin relies on external services, namely:
- - **Google Sign In** service: You can view the [service description](https://developers.google.com/identity/gsi/web/guides/overview) and [terms](https://developers.google.com/terms). To revise the connected services, you can visit [here](https://myaccount.google.com/connections).
- - **Microsoft Identity** services: You can view the [service description](https://learn.microsoft.com/en-us/entra/identity-platform/) and [terms](https://learn.microsoft.com/en-us/legal/termsofuse). To revise the connected services, you can visit [here](https://account.live.com/consent/Manage), [here](https://account.microsoft.com/privacy/app-access) or [here](https://account.live.com/consent/Manage?fref=home.drawers.privacy.app-and-services).
- - Also uses some composer vendor dependencies, which are beyong our monitoring or control.

== Installation ==

A) Enter your website "Admin Dashboard > Plugins > Add New" and enter the plugin name
or
B) Download plugin from WordPress.org, Extract the zip file and upload the container folder to "wp-content/plugins/"

== Frequently Asked Questions ==

= Where can I get a Client ID and Client Secret for Google? =

Due to the nature of Google's OAuth 2.0 security protocols, you will need to register an application with them to access the required APIs. (Don't worry if you do not understand, the process is fairly straight forward)

You will need to sign in to the [Google Developer Console](https://console.developers.google.com)

1. Go to the API Console.
2. From the projects list, select a project or create a new one.
3. If the APIs & services page isn't already open, open the console left side menu and select APIs & services.
4. On the left, click Credentials.
5. Click New Credentials, then select OAuth client ID.
6. Add the following in the "Authorized redirect URIs" section: `https://YOURDOMAIN.TLD/_AUTH_RESPONSE_SIWE_`
7. Click save and you may now use "Sign in with Essentials".


= Where can I get a Client ID and Client Secret for Microsoft ? =

These resources would help:

- https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade
- https://learn.microsoft.com/en-us/advertising/guides/authentication-oauth-register?view=bingads-13
- https://learn.microsoft.com/en-us/entra/identity-platform/publisher-verification-overview
- https://learn.microsoft.com/en-us/answers/questions/799042/adding-mfa-to-administrator-accounts-with-the-free

= todo =
- account-id based duplicate detection

== Screenshots ==

1. The login form with the "Sign in with" button added.
2. This is the second screen shot

== Changelog ==

= 1.0.1 =
* Pushed a completely reorganized version with dozens of changes

= 1.0.0 =
* Initial Release (plugin forked from https://github.com/tarecord/sign-in-with-google )
