<?php
if( ! defined('WP_UNINSTALL_PLUGIN') )
    exit;

if(get_option('avatar_default') == 'ulogin')
    update_option('avatar_default', 'mystery');