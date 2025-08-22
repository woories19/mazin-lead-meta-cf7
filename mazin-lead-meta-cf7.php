<?php
/**
 * Plugin Name: Mazin Lead Meta for CF7
 * Plugin URI:  https://github.com/woories19/mazin-lead-meta-cf7
 * Description: Capture IP, country, city, browser, OS & device info in Contact Form 7 emails.
 * Version:     0.2
 * Author:      Mazin Digital
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

/**
 * Logger helper – writes to plugin folder (mazin-lead-meta.log)
 */
function mazin_cf7_log($message) {
    $file = MAZIN_CF7_PATH . 'mazin-lead-meta.log';
    $date = date("Y-m-d H:i:s");
    error_log("[$date] $message\n", 3, $file);
}

// Init settings + handler
add_action('plugins_loaded', function() {
    try {
        new Mazin_CF7_Settings();
        new Mazin_CF7_Meta_Handler();
        mazin_cf7_log("Plugin initialized successfully.");
    } catch (Exception $e) {
        mazin_cf7_log("Init error: " . $e->getMessage());
    }
});

// Init GitHub updater
if( is_admin() ) {
    try {
        new Mazin_GitHub_Updater( __FILE__, 'woories19', 'mazin-lead-meta-cf7' );
        mazin_cf7_log("GitHub updater loaded.");
    } catch (Exception $e) {
        mazin_cf7_log("Updater error: " . $e->getMessage());
    }
}

/**
 * Filter: Ensure CF7 mail tags get replaced with our meta data
 */
add_filter('wpcf7_special_mail_tags', function($output, $name, $html) {
    $name = strtolower($name);

    switch ($name) {
        case '_mazin_user_ip':
            $output = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            break;

        case '_mazin_country_city':
            $country = get_transient('mazin_cf7_country');
            $city    = get_transient('mazin_cf7_city');
            if ($country && $city) {
                $output = $country . ', ' . $city;
            } else {
                $output = 'Unknown';
                mazin_cf7_log("Geo data missing for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
            }
            break;

        case '_mazin_timezone':
            $tz = get_transient('mazin_cf7_timezone');
            $output = $tz ?: 'Unknown';
            break;

        case '_mazin_browser':
            $browser = get_transient('mazin_cf7_browser');
            $output = $browser ?: 'Unknown';
            break;

        case '_mazin_os':
            $os = get_transient('mazin_cf7_os');
            $output = $os ?: 'Unknown';
            break;

        case '_mazin_device':
            $device = get_transient('mazin_cf7_device');
            $output = $device ?: 'Unknown';
            break;
    }

    return $output;
}, 10, 3);
