# Keycloak Authentication for phpBB

## Requirements

- phpBB 3.2.0 or higher
- PHP 7.0 or higher
- Keycloak server

## Installation

1. Copy the extension to `ext/tc/keycloak/`
2. Navigate to the extension directory
3. Install dependencies: `composer install --no-dev`
4. Copy `config/keycloak-example.config.php` to `config/keycloak.config.php`
5. Configure your Keycloak settings in `config/keycloak.config.php`
6. Enable the extension in ACP → Customise → Manage extensions
7. Set authentication method to "Tc.keycloak" in ACP → General → Authentication

## Configuration

Edit `config/keycloak.config.php` with your Keycloak settings:

```php
return [
    'provider_url' => 'https://your-keycloak-server/realms/your-realm',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://your-forum/index.php',
    'post_logout_redirect_uri' => 'https://your-forum/',
    'scopes' => 'openid profile email',
    'verify_ssl' => true,
    'logout_from_keycloak' => true,
];
```

## How it works

- All users authenticate through Keycloak for forum access
- Admin users authenticate through database for ACP access
- Users are automatically created and synchronized with Keycloak data
