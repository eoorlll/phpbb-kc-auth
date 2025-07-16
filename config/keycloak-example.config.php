<?php
//keycloak.config.php
return [
    'provider_url' => '',
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => 'http://localhost:8080/index.php',
    'post_logout_redirect_uri' => 'http://localhost:8080/',
    'scopes' => 'openid profile email',
    'verify_ssl' => true,
    'logout_from_keycloak' => true,
];
