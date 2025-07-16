<?php
/**
 * info_acp_keycloak.php
 */

if(!defined('IN_PHPBB')) {
    exit;
}

if(empty($lang) || !is_array($lang)) {
    $lang = array();
}

$lang = array_merge( $lang, array(
    'ACP_KEYCLOAK_TITLE' => 'Keycloak Authentication',
    'ACP_KEYCLOAK_SETTINGS' => 'Keycloak Settings',
    'ACP_KEYCLOAK_CONFIG' => 'Keycloak Configuration',
    'KEYCLOAK_PROVIDER_URL' => 'Provider URL',
    'KEYCLOAK_CLIENT_ID' => 'Client ID',
    'KEYCLOAK_CLIENT_SECRET' => 'Client Secret',
    'KEYCLOAK_REDIRECT_URI' => 'Redirect URI',
) );