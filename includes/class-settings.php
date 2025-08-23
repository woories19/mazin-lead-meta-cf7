<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mazin_CF7_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Add plugin settings page under "Settings"
     */
    public function add_settings_page() {
        add_options_page(
            'Mazin CF7 Lead Meta',
            'Mazin CF7 Lead Meta',
            'manage_options',
            'mazin-cf7-lead-meta',
            [ $this, 'settings_page_html' ]
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'mazin_cf7_settings_group', 'mazin_cf7_enable_logging' );
        register_setting( 'mazin_cf7_settings_group', 'mazin_cf7_ip_service' );

        add_settings_section(
            'mazin_cf7_main_section',
            'Lead Meta Settings',
            null,
            'mazin_cf7_lead_meta'
        );

        add_settings_field(
            'mazin_cf7_ip_service',
            'IP Lookup Service',
            [ $this, 'ip_service_field_html' ],
            'mazin_cf7_lead_meta',
            'mazin_cf7_main_section'
        );

        add_settings_field(
            'mazin_cf7_enable_logging',
            'Enable Logging',
            [ $this, 'logging_field_html' ],
            'mazin_cf7_lead_meta',
            'mazin_cf7_main_section'
        );
    }

    /**
     * Render IP Service dropdown
     */
    public function ip_service_field_html() {
        $option = get_option( 'mazin_cf7_ip_service', 'ipapi' );
        ?>
        <select name="mazin_cf7_ip_service">
            <option value="ipapi" <?php selected( $option, 'ipapi' ); ?>>ipapi.co</option>
            <option value="ipwhois" <?php selected( $option, 'ipwhois' ); ?>>ipwho.is</option>
            <option value="ipinfo" <?php selected( $option, 'ipinfo' ); ?>>ipinfo.io</option>
        </select>
        <?php
    }

    /**
     * Render Logging checkbox
     */
    public function logging_field_html() {
        $option = get_option( 'mazin_cf7_enable_logging', 0 );
        ?>
        <label>
            <input type="checkbox" name="mazin_cf7_enable_logging" value="1" <?php checked( 1, $option ); ?> />
            E
