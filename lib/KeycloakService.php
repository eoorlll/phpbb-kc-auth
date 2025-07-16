<?php
namespace tc\keycloak\lib;

// Try to load autoloader from different possible locations
$autoload_paths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

use Jumbojett\OpenIDConnectClient;

class KeycloakService
{
    protected $config;
    protected $oidc;
    
    public function __construct()
    {
        $config_path = __DIR__ . '/../config/keycloak.config.php';
        if (file_exists($config_path)) {
            $this->config = require $config_path;
        } else {
            throw new \Exception('Keycloak config file not found');
        }
        
        $this->init_oidc_client();
    }
    
    protected function init_oidc_client()
    {
        if (!class_exists('Jumbojett\OpenIDConnectClient')) {
            error_log('KEYCLOAK: OpenIDConnectClient class not found');
            throw new \Exception('OpenIDConnectClient class not found');
        }

        try {
            $this->oidc = new OpenIDConnectClient(
                $this->config['provider_url'],
                $this->config['client_id'],
                $this->config['client_secret']
            );

            $this->oidc->setRedirectURL($this->config['redirect_uri']);
            
            // Add scopes
            if (isset($this->config['scopes'])) {
                // addScope expects an array, convert string to array if needed
                if (is_string($this->config['scopes'])) {
                    $scopes = explode(' ', $this->config['scopes']);
                    $this->oidc->addScope($scopes);
                } else if (is_array($this->config['scopes'])) {
                    $this->oidc->addScope($this->config['scopes']);
                }
            }
            
            // Set SSL verification
            if (isset($this->config['verify_ssl'])) {
                $this->oidc->setVerifyPeer($this->config['verify_ssl']);
                $this->oidc->setVerifyHost($this->config['verify_ssl']);
            }

        } catch (\Exception $e) {
            error_log('KEYCLOAK: Failed to initialize OIDC client: ' . $e->getMessage());
            throw new \Exception('Failed to initialize Keycloak client');
        }
    }
    
    public function handle_oidc_login()
    {
        try {
            // Check if we have the authorization code
            if (!isset($_GET['code'])) {
                error_log('KEYCLOAK: No authorization code in request');
                return null;
            }

            // Authenticate with the authorization code
            $this->oidc->authenticate();

            // Get user info
            $userInfo = $this->oidc->requestUserInfo();
            
            if (!$userInfo) {
                error_log('KEYCLOAK: Failed to get user info');
                return null;
            }

            $email = isset($userInfo->email) ? $userInfo->email : null;
            $username = isset($userInfo->preferred_username) ? $userInfo->preferred_username : null;
            
            // Fallback to other possible username fields
            if (empty($username)) {
                $username = isset($userInfo->name) ? $userInfo->name : null;
            }
            if (empty($username)) {
                $username = isset($userInfo->sub) ? $userInfo->sub : null;
            }

            if (empty($email) || empty($username)) {
                error_log('KEYCLOAK: Missing required user info - email or username');
                return null;
            }

            return [
                'username' => $username,
                'email' => $email,
                'access_token' => $this->oidc->getAccessToken(),
                'id_token' => $this->oidc->getIdToken(),
            ];
            
        } catch (\Exception $e) {
            error_log('KEYCLOAK: Login error - ' . $e->getMessage());
            return null;
        }
    }
    
    public function get_oidc_client()
    {
        return $this->oidc;
    }
}