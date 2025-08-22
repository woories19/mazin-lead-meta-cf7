<?php
/**
 * Plugin Name: Mazin Lead Meta for CF7
 * Plugin URI:  https://github.com/woories19/mazin-lead-meta-cf7
 * Description: Capture IP, country, city, browser, OS & device info in Contact Form 7 emails.
 * Version:     1.0.0
 * Author URI:  https://mazindigital.com
 * License:     GPL2
 * Text Domain: mazin-lead-meta-cf7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MAZIN_CF7_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAZIN_CF7_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once MAZIN_CF7_PATH . 'includes/class-settings.php';
require_once MAZIN_CF7_PATH . 'includes/class-meta-handler.php';
require_once MAZIN_CF7_PATH . 'updater/class-github-updater.php';

// Init settings + handler
add_action('plugins_loaded', function() {
    new Mazin_CF7_Settings();
    new Mazin_CF7_Meta_Handler();
});

// Init GitHub updater
if( is_admin() ) {
    new Mazin_GitHub_Updater( __FILE__, 'woories19', 'mazin-lead-meta-cf7' );
}

add_filter('wpcf7_special_mail_tags', function($output, $name, $html) {
    switch ($name) {
        case '_mazin_user_ip':
            $output = $_SERVER['REMOTE_ADDR'] ?? '';
            break;
        case '_mazin_country_city':
            $output = get_option('mazin_last_country_city', 'Unknown');
            break;
        case '_mazin_timezone':
            $output = get_option('mazin_last_timezone', 'Unknown');
            break;
        case '_mazin_browser':
            $output = get_option('mazin_last_browser', 'Unknown');
            break;
        case '_mazin_os':
            $output = get_option('mazin_last_os', 'Unknown');
            break;
        case '_mazin_device':
            $output = get_option('mazin_last_device', 'Unknown');
            break;
    }
    return $output;
}, 10, 3);