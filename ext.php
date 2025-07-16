<?php

namespace tc\keycloak;

class ext extends \phpbb\extension\base
{
    public function is_enableable() {
        return true;
    }

    public function enable_step($old_state)
    {
        switch($old_state) {
            case '':
                return 'complete';

            default:
                return parent::enable_step($old_state);
        }
    }

    public function disable_step($old_state) {
        switch ($old_state) {
            case '':
                global $phpbb_container;

                $config = $phpbb_container->get('config');
                $config->set('auth_provider', 'db');

                return 'complete';

            default:
                return parent::disable_step($old_state);
        }
    }
}
