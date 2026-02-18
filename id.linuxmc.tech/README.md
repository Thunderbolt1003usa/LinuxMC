# Identity Provider Documentation (id.linuxmc.tech)

This is the Identity Provider (IdP) component for the LinuxMC platform. It provides Single Sign-On (SSO) capabilities.

## Architecture
The IdP handles:
*   User Authentication (Login/Logout)
*   Session Management (Cross-Domain via Cookies)
*   OAuth2-like Auth Code Flow (custom implementation)

## Key Files
*   `sso.php`: Handles login requests and generation of one-time tokens.
*   `api_action.php`: Provides API endpoints for token verification and user info.
*   `auth.php`: Core authentication logic.
*   `totp.php`: Two-Factor Authentication helper.

## Usage
Service Providers should redirect users to `sso.php` with a `redirect` parameter.
Upon successful login, a callback is made to the SP with a token. The SP must verify this token via the API.
