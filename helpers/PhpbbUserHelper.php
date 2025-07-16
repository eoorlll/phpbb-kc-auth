<?php
namespace tc\keycloak\helpers;

use phpbb\db\driver\driver_interface;
use phpbb\user_loader;
use phpbb\config\config;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\passwords\manager as password_manager;
use phpbb\auth\auth;

class PhpbbUserHelper
{
    protected $db;
    protected $config;
    protected $user_loader;
    protected $language;
    protected $request;
    protected $passwords;
    protected $auth;
    protected $phpbb_root_path;
    protected $phpEx;

    public function __construct(
        driver_interface $db,
        config $config,
        user_loader $user_loader,
        language $language,
        request_interface $request,
        password_manager $passwords,
        auth $auth,
        $phpbb_root_path,
        $phpEx
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->user_loader = $user_loader;
        $this->language = $language;
        $this->request = $request;
        $this->passwords = $passwords;
        $this->auth = $auth;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->phpEx = $phpEx;
    }

    public function get_or_create_user($user_info)
    {
        if (empty($user_info['username']) || empty($user_info['email'])) {
            throw new \InvalidArgumentException('Username and email are required');
        }

        $username_clean = utf8_clean_string($user_info['username']);
        $email = $user_info['email'];

        // Check if user exists by username or email
        $sql = 'SELECT * FROM ' . USERS_TABLE . '
                WHERE username_clean = \'' . $this->db->sql_escape($username_clean) . '\'
                OR user_email = \'' . $this->db->sql_escape($email) . '\'';
        $result = $this->db->sql_query($sql);
        $user_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($user_row) {
            // Update existing user if needed
            $this->update_user_if_needed($user_row, $user_info);
            return $user_row;
        }

        // Create new user
        return $this->create_new_user($user_info, $username_clean, $email);
    }

    protected function update_user_if_needed($user_row, $user_info)
    {
        $updates = [];
        
        // Update email if it changed
        if ($user_row['user_email'] !== $user_info['email']) {
            $updates['user_email'] = $user_info['email'];
        }
        
        // Update username if it changed (but not username_clean to avoid conflicts)
        if ($user_row['username'] !== $user_info['username']) {
            $updates['username'] = $user_info['username'];
        }
        
        if (!empty($updates)) {
            $sql = 'UPDATE ' . USERS_TABLE . ' 
                    SET ' . $this->db->sql_build_array('UPDATE', $updates) . '
                    WHERE user_id = ' . (int) $user_row['user_id'];
            $this->db->sql_query($sql);
        }
    }

    protected function create_new_user($user_info, $username_clean, $email)
    {
        // Check if we can load functions
        if (!function_exists('user_add')) {
            include($this->phpbb_root_path . 'includes/functions_user.' . $this->phpEx);
        }

        $user_row = array(
            'username'              => $user_info['username'],
            'username_clean'        => $username_clean,
            'user_email'            => $email,
            'user_type'             => USER_NORMAL,
            'user_ip'               => $this->request->get_ip(),
            'user_regdate'          => time(),
            'user_lastvisit'        => time(),
            'user_lang'             => $this->config['default_lang'],
            'user_timezone'         => $this->config['board_timezone'],
            'user_dateformat'       => $this->config['default_dateformat'],
            'user_style'            => (int) $this->config['default_style'],
            'group_id'              => $this->config['new_member_group_default'] ? $this->config['new_member_group_default'] : 2,
            'user_permissions'      => '',
            'user_sig'              => '',
            'user_occ'              => '',
            'user_interests'        => '',
            'user_actkey'           => '',
            'user_newpasswd'        => '',
            'user_form_salt'        => unique_id(),
            'user_password'         => $this->passwords->hash(uniqid('', true)),
        );

        // Add user using phpBB's function
        $user_id = user_add($user_row);
        
        if ($user_id) {
            // Reload user data
            $sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id;
            $result = $this->db->sql_query($sql);
            $new_user_row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
            
            return $new_user_row;
        }
        
        throw new \RuntimeException('Failed to create user');
    }
}