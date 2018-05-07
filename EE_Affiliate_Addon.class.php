<?php

// define the plugin directory path and URL
define('EE_AFFILIATE_WP_BASENAME', plugin_basename(EE_AFFILIATE_WP_PLUGIN_FILE));
define('EE_AFFILIATE_WP_PATH', plugin_dir_path(__FILE__));
define('EE_AFFILIATE_WP_URL', plugin_dir_url(__FILE__));



/**
 *
 * Class  EE_Affiliate_Addon
 *
 * @package         Event Espresso
 * @subpackage      eea-new-addon
 * @author          Darren Ethier
 *
 */
class EE_Affiliate_Addon extends EE_Addon
{

    public static function register_addon()
    {
        // register addon via Plugin API
        EE_Register_Addon::register(
            'EE_Affiliate_Addon',
            array(
                'version'                   => EE_AFFILIATE_WP_VERSION,
                'plugin_slug'           => 'eea-affiliatewp',
                'min_core_version' => EE_AFFILIATE_WP_CORE_VERSION_REQUIRED,
                'main_file_path'        => EE_AFFILIATE_WP_PLUGIN_FILE,
                'autoloader_paths' => array(
                    'EE_Affiliate_Addon'                        => EE_AFFILIATE_WP_PATH . 'EE_Affiliate_Addon.class.php',
                ),
                'module_paths' => array( EE_AFFILIATE_WP_PATH . 'EED_Affiliate_WP.module.php' ),
                // if plugin update engine is being used for auto-updates. not needed if PUE is not being used.
                'pue_options'           => array(
                    'pue_plugin_slug'       => 'eea-affiliatewp',
                    'plugin_basename'   => EE_AFFILIATE_WP_BASENAME,
                    'checkPeriod'               => '24',
                    'use_wp_update'         => false,
                )
            )
        );
    }
}
