<?php
/**
 * Plugin Name: Mazin Lead Meta for CF7
 * Plugin URI:  https://github.com/woories19/mazin-lead-meta-cf7
 * Description: Capture IP, country, city, browser, OS & device info in Contact Form 7 emails.
 * Version:     0.5.0
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

// Init settings + handler
add_action('plugins_loaded', function() {
    new Mazin_CF7_Settings();
    new Mazin_CF7_Meta_Handler();
});