# LinuxMC Documentation

## Overview
LinuxMC is a comprehensive web platform consisting of multiple services hosted under the `linuxmc.tech` domain. The project is structured into several subdomains, each serving a specific purpose within the ecosystem.

### Structure
The project is organized in the `/var/www` directory:

*   **Main Website (`linuxmc.tech`)**: Located in `html/`. Serves as the primary entry point and service provider.
*   **Identity Provider (`id.linuxmc.tech`)**: Located in `id.linuxmc.tech/`. Handles Single Sign-On (SSO), user authentication, and profile management.
*   **File Server (`files.linuxmc.tech`)**: Located in `files/`. Used for hosting static files and downloads.
*   *Note: `assets/` and `secret/` directories are internal or ignored.*

## Services

### 1. Main Website (`linuxmc.tech`)
*   **Path**: `/var/www/html/`
*   **Technologies**: PHP, HTML, CSS, JavaScript.
*   **Key Files**:
    *   `index.php`: Landing page.
    *   `login_callback.php`: Handles the SSO callback from `id.linuxmc.tech`.
    *   `session_check.php`: Verifies active sessions.
    *   `ipinfo.php`: Displays IP information.

### 2. Identity Provider (`id.linuxmc.tech`)
*   **Path**: `/var/www/id.linuxmc.tech/`
*   **Function**: Acts as a central authentication server (SSO) for the LinuxMC network.
*   **Key Files**:
    *   `sso.php`: The main entry point for Single Sign-On. Redirects unauthenticated users to login or authenticated users back to the service provider with a token.
    *   `api_action.php`: Handles API requests, including token verification (`verify_sso_token`).
    *   `auth.php`: Processes login attempts and session management.
    *   `dashboard.php`: User profile management.

### 3. File Server (`files.linuxmc.tech`)
*   **Path**: `/var/www/files/`
*   **Function**: Serves static content.

## SSO Integration Guide

To use `id.linuxmc.tech` as an SSO provider for your application (Service Provider):

1.  **Redirect to SSO**:
    Redirect the user to:
    ```
    https://id.linuxmc.tech/sso.php?redirect=YOUR_CALLBACK_URL
    ```
    *   `redirect`: The URL where the user should be returned after authentication (e.g., `https://linuxmc.tech/login_callback.php`).
    *   *Note*: The redirect URL must be whitelisted in `id.linuxmc.tech/sso.php`.

2.  **Handle Callback**:
    The user will be redirected back to your `redirect` URL with a `sso_token` parameter:
    ```
    YOUR_CALLBACK_URL?sso_token=OneTimeToken123
    ```

3.  **Verify Token**:
    Your server must verify this token by making a server-to-server HTTP request (e.g., using cURL) to the API:
    ```
    GET https://id.linuxmc.tech/api_action.php?action=verify_sso_token&token=OneTimeToken123
    ```

4.  **API Response**:
    If valid, the API returns a JSON response:
    ```json
    {
      "success": true,
      "user": {
        "user_id": 1,
        "username": "User",
        "avatar_url": "...",
        "realname": "..."
      }
    }
    ```
    *   Establish a local session for the user based on this data.
    *   The token is deleted immediately after verification (One-Time Use).

## Security Analysis

### Potential Vulnerabilities
*   **SQL Injection**: Ensure all database interaction uses Prepared Statements (`$pdo->prepare`). Current code in `sso.php` and `api_action.php` appears to use them correctly.
*   **XSS (Cross-Site Scripting)**: User input must be escaped using `htmlspecialchars()` before output.
    *   *Check*: Verify `$_GET['redirect']` usage in `sso.php`.
*   **CSRF (Cross-Site Request Forgery)**:
    *   `id.linuxmc.tech` implements CSRF tokens for login forms.
    *   Ensure all state-changing actions (like profile updates) also require a valid CSRF token.
*   **Session Security**:
    *   `session_set_cookie_params` is used with `secure`, `httponly`, and `samesite=Lax`. This is good practice.
    *   Ensure `domain` is set correctly for cross-subdomain sessions (`.linuxmc.tech`).
*   **Hardcoded Credentials**:
    *   Avoid storing credentials directly in code. Use environment variables or configuration files outside the web root (handled via `secret/db.php`).

### Credentials Placeholder
*   **Database**: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` (configured in `secret/db.php`)
*   **Mail Server**: `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS` (configured in `includes/mailer.php` or `secret/mail_config.php`)

## Deployment
This repository contains the source code for the entire LinuxMC platform.
*   `html/` -> Deploy to Web Root for `linuxmc.tech`
*   `id.linuxmc.tech/` -> Deploy to Web Root for `id.linuxmc.tech`
*   `files/` -> Deploy to Web Root for `files.linuxmc.tech`
