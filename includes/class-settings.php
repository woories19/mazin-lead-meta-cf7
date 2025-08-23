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
        <p class="description">Choose the IP lookup service to use for geolocation.</p>
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
            Enable debug logging (only when WP_DEBUG is true)
        </label>
        <p class="description">Logs will be written to wp-content/mazin-cf7-meta.log</p>
        <?php
    }

    /**
     * Render the settings page HTML
     */
    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'mazin_cf7_settings_group' );
                do_settings_sections( 'mazin_cf7_lead_meta' );
                submit_button( 'Save Settings' );
                ?>
            </form>
            
            <h2>Available Meta Tags</h2>
            <p>Use these tags in your Contact Form 7 email templates:</p>
            <ul>
                <li><code>[_mazin_user_ip]</code> - User's IP address</li>
                <li><code>[_mazin_country_city]</code> - Country and city</li>
                <li><code>[_mazin_timezone]</code> - User's timezone</li>
                <li><code>[_mazin_browser]</code> - Browser type</li>
                <li><code>[_mazin_os]</code> - Operating system</li>
                <li><code>[_mazin_device]</code> - Device type</li>
            </ul>
        </div>
        <?php
    }
}
