<?php

namespace tc\keycloak\auth\provider;

use phpbb\auth\provider\base;
use phpbb\user;
use Symfony\Component\DependencyInjection\ContainerInterface;

class auth_keycloak extends base
{
    protected $user;
    protected $phpbb_root_path;
    protected $phpEx;
    protected $phpbb_container;
    protected $workaroundLogout = false;

    public function __construct(
        user $user,
        $phpbb_root_path,
        $phpEx,
        ContainerInterface $phpbb_container
    ) {
        $this->user = $user;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->phpEx = $phpEx;
        $this->phpbb_container = $phpbb_container;
    }

    public function init()
    {
        // Nothing special needed here
    }

    /**
     * Standard login method - returns null to pass control to autologin
     */
    public function login($username, $password)
    {
        // For ACP, always use database authentication
        if ($this->is_acp_login()) {
            return $this->fallback_to_db_auth($username, $password);
        }

        return null;
    }

    /**
     * Autologin method - handles OIDC callback and automatic authentication
     */
    public function autologin()
    {
        // Skip if in ACP
        if ($this->is_acp_login()) {
            return array();
        }

        // Check if we should process OIDC
        if ($this->should_process_oidc()) {
            $this->workaroundLogout = false;
            return $this->process_oidc_login();
        }

        return array();
    }

    /**
     * Check if current request should be processed by OIDC
     */
    protected function should_process_oidc()
    {
        // Check if we have OIDC callback parameters
        $has_code = isset($_GET['code']);
        $has_state = isset($_GET['state']);
        
        // Check if this is a login page request
        $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
        $is_login_page = ($mode === 'login');
        
        // Check if workaround logout was triggered
        $called_get_login = $this->workaroundLogout;
        
        return ($has_code && $has_state) || $is_login_page || $called_get_login;
    }

    /**
     * Process OIDC login
     */
    protected function process_oidc_login()
    {
        try {
            $kc = $this->phpbb_container->get('tc.keycloak.keycloak_service');
            
            // Redirect to Keycloak If we don't have code yet
            if (!isset($_GET['code'])) {
                $kc->get_oidc_client()->authenticate();
                exit;
            }
            
            // Process the OIDC callback
            $user_info = $kc->handle_oidc_login();

            if (!$user_info) {
                return array();
            }

            $user_helper = $this->phpbb_container->get('tc.keycloak.phpbb_user_helper');
            $user_row = $user_helper->get_or_create_user($user_info);

            if (!$user_row || !isset($user_row['user_id'])) {
                return array();
            }

            return $user_row;
            
        } catch (\Exception $e) {
            error_log('KEYCLOAK: Authentication error in autologin - ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Workaround method to kill anonymous session and trigger autologin
     */
    public function get_login_data()
    {
        global $user;
        
        // Only process if not in ACP and session exists
        if (!$this->is_acp_login() && $this->session_exists()) {
            // Set flag to remember this logout is workaround
            $this->workaroundLogout = true;
            
            // Kill current session to force autologin on next request
            if (!empty($user->session_id)) {
                $user->session_kill();
            }
        }
        
        return array(
            'TEMPLATE_FILE' => false,
        );
    }

    /**
     * Check if a session exists
     */
    protected function session_exists()
    {
        global $user;
        return !empty($user->session_id);
    }

    /**
     * Validate session
     */
    public function validate_session($user)
    {
        // Session is always valid for Keycloak users
        return true;
    }

    /**
     * Check if this is an ACP login
     */
    protected function is_acp_login()
    {
        return (defined('IN_ADMIN') && IN_ADMIN) || 
               strpos($_SERVER['REQUEST_URI'], '/adm/') !== false ||
               strpos($_SERVER['SCRIPT_NAME'], '/adm/') !== false;
    }

    /**
     * Fallback to database authentication for ACP or emergency login
     */
    protected function fallback_to_db_auth($username, $password)
    {
        // Include the database auth provider
        if (!class_exists('phpbb\auth\provider\db')) {
            include($this->phpbb_root_path . 'phpbb/auth/provider/db.' . $this->phpEx);
        }
        
        // Create DB auth provider with required dependencies
        $db_auth = new \phpbb\auth\provider\db(
            $this->phpbb_container->get('dbal.conn'),
            $this->phpbb_container->get('config'),
            $this->phpbb_container->get('passwords.manager'),
            $this->phpbb_container->get('request'),
            $this->phpbb_container->get('user'),
            $this->phpbb_container,
            $this->phpbb_root_path,
            $this->phpEx
        );
        
        return $db_auth->login($username, $password);
    }

    /**
     * Logout method
     */
    public function logout($data, $new_session)
    {
        // Skip Keycloak logout if this is workaround logout
        if ($this->workaroundLogout) {
            return array();
        }
        
        // Regular logout
        try {
            $config_path = $this->phpbb_root_path . 'ext/tc/keycloak/config/keycloak.php';
            
            if (!file_exists($config_path)) {
                return array();
            }
            
            $config = require $config_path;

            $redirect = isset($config['post_logout_redirect_uri'])
                ? $config['post_logout_redirect_uri']
                : (isset($config['redirect_uri']) ? $config['redirect_uri'] : '');

            if (!empty($redirect) && isset($config['logout_from_keycloak']) && $config['logout_from_keycloak']) {
                $logout_url = rtrim($config['provider_url'], '/')
                    . '/protocol/openid-connect/logout?redirect_uri=' . urlencode($redirect);

                return array(
                    'redirect' => $logout_url,
                );
            }
        } catch (\Exception $e) {
            error_log('KEYCLOAK: Logout error - ' . $e->getMessage());
        }
        
        return array();
    }

    /**
     * Get auth plugin name
     */
    public function get_auth_plugin_name()
    {
        return 'keycloak';
    }
}