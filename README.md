# Azure Easy Auth Integration Module

The `azure_easy_auth` module is a project-agnostic Drupal custom module that integrates with Azure Active Directory / Microsoft Entra ID authentication to enforce user-level gating access control. It checks logging-in users against an allowed list of email/username principals.

---

## Key Features

1. **Gatekeeper Access Control**: Registers a `hook_user_login` event subscriber that terminates sessions of users not defined in the `azure_easy_auth.authorized_principals` settings configuration list.
2. **Guzzle Middleware Fix**: Includes a HTTP Client/Guzzle middleware that automatically maps the `userPrincipalName` (UPN) to the `mail` field in Graph API responses if the user's `mail` property is empty (preventing login failures on Azure AD tenants where email fields are unpopulated).
3. **Local Dev Sim Login**: Includes an endpoint `/user/simulate-login` for simulating Entra ID logins locally without requiring active Azure connectivity.

---

## Configuration

Add the following to your site's `sites/default/settings.php` to integrate this module with your environment variables:

```php
// Comma-separated list of approved emails or netIDs
$settings['azure_easy_auth.authorized_principals'] = array_filter(
  array_map('trim', explode(',', getenv('AUTHORIZED_PRINCIPALS') ?: ''))
);

// Flag to mark if the current environment is local development (enables simulate-login route)
$settings['is_local_dev'] = (getenv('IS_LOCAL_DEV') === 'true');
```

---

## Simulate Local Login

In local development environments (where `IS_LOCAL_DEV=true` is set), developers can bypass Azure configuration and login locally by visiting:

```text
http://localhost/user/simulate-login?email=user@example.com
```

---

## Running Unit Tests

To run the module unit tests, register the namespaces in your root `composer.json` file:

```json
"autoload-dev": {
    "psr-4": {
        "Drupal\\Tests\\azure_easy_auth\\": "modules/custom/azure_easy_auth/tests/src/",
        "Drupal\\azure_easy_auth\\": "modules/custom/azure_easy_auth/src/"
    }
}
```

Then run PHPUnit:

```bash
vendor/bin/phpunit modules/custom/azure_easy_auth/tests/src/Unit/
```
