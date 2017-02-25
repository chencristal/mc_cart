<?php

if (!defined('MC_CART_NAME')) {
    define('MC_CART_NAME', 'Mc_cart');
    define('MC_CART_MACHINE', 'Mc_cart');
    define('MC_CART_VER', '0.0.1');
    define('MC_CART_DESC', 'Mailchimp API for Cartthrob Addon');
    define('MC_CART_DB_SETTINGS', 'mc_cart_settings');
    define('MC_CART_PROVIDERS', PATH_THIRD.'mc_cart/providers/');
}

if (defined('PATH_THEMES'))
{
    if ( ! defined('PATH_THIRD_THEMES'))
    {
        define('PATH_THIRD_THEMES', PATH_THEMES.'../user/');
    }

    if ( ! defined('URL_THIRD_THEMES'))
    {
        define('URL_THIRD_THEMES', get_instance()->config->slash_item('theme_folder_url').'user/');
    }
}
