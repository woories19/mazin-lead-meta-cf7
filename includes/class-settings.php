<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mazin_CF7_Settings {
    const OPTION = 'mazin_cf7_settings';

    public static function defaults() {
        return [
            'enable'          => 1,
            'provider'        => 'ipapi',        // ipapi | ipinfo | ipstack
            'api_key'         => '',
            'append_to_email' => 1,
            'timeout'         => 4,              // seconds
        ];
    }

    public static function get_settings() {
        $saved = get_option( self::OPTION, [] );
        return wp_parse_args( $saved, self::defaults() );
    }

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register' ] );
        add_action( 'admin_menu', [ $this, 'menu' ] );
    }

    public function register() {
        register_setting(
            self::OPTION,
            self::OPTION,
            [ 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize' ] ]
        );

        add_settings_section(
            'mazin_cf7_main',
            __( 'Lead Metadata Settings', 'mazin-lead-meta-cf7' ),
            function () {
                echo '<p>' . esc_html__( 'Configure IP geolocation and email output for Contact Form 7 submissions.', 'mazin-lead-meta-cf7' ) . '</p>';
            },
            self::OPTION
        );

        add_settings_field(
            'enable',
            __( 'Enable collection', 'mazin-lead-meta-cf7' ),
            function () {
                $s = self::get_settings();
                echo '<label><input type="checkbox" name="' . self::OPTION . '[enable]" value="1" ' . checked( 1, (int) $s['enable'], false ) . ' /> ' . esc_html__( 'Resolve metadata (IP/Geo/UA) on submission', 'mazin-lead-meta-cf7' ) . '</label>';
            },
            self::OPTION,
            'mazin_cf7_main'
        );

        add_settings_field(
            'provider',
            __( 'Geolocation Provider', 'mazin-lead-meta-cf7' ),
            function () {
                $s = self::get_settings();
                $providers = [
                    'ipapi'  => 'ipapi.co (free, no key needed for light use)',
                    'ipinfo' => 'ipinfo.io (token optional for light use)',
                    'ipstack'=> 'ipstack.com (requires access_key)',
                ];
                echo '<select name="' . self::OPTION . '[provider]">';
                foreach ( $providers as $k => $label ) {
                    echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['provider'], $k, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
            },
            self::OPTION,
            'mazin_cf7_main'
        );

        add_settings_field(
            'api_key',
            __( 'API Key / Token', 'mazin-lead-meta-cf7' ),
            function () {
                $s = self::get_settings();
                echo '<input type="text" class="regular-text" name="' . self::OPTION . '[api_key]" value="' . esc_attr( $s['api_key'] ) . '" placeholder="optional" />';
            },
            self::OPTION,
            'mazin_cf7_main'
        );

        add_settings_field(
            'append_to_email',
            __( 'Auto-append to CF7 emails', 'mazin-lead-meta-cf7' ),
            function () {
                $s = self::get_settings();
                echo '<label><input type="checkbox" name="' . self::OPTION . '[append_to_email]" value="1" ' . checked( 1, (int) $s['append_to_email'], false ) . ' /> ' . esc_html__( 'Append a metadata block to the end of email body', 'mazin-lead-meta-cf7' ) . '</label>';
            },
            self::OPTION,
            'mazin_cf7_main'
        );

        add_settings_field(
            'timeout',
            __( 'HTTP Timeout (seconds)', 'mazin-lead-meta-cf7' ),
            function () {
                $s = self::get_settings();
                echo '<input type="number" min="2" max="10" name="' . self::OPTION . '[timeout]" value="' . esc_attr( (int) $s['timeout'] ) . '" />';
            },
            self::OPTION,
            'mazin_cf7_main'
        );
    }

    public function sanitize( $input ) {
        $out = self::defaults();
        $out['enable']          = empty( $input['enable'] ) ? 0 : 1;
        $out['provider']        = in_array( $input['provider'] ?? 'ipapi', [ 'ipapi', 'ipinfo', 'ipstack' ], true ) ? $input['provider'] : 'ipapi';
        $out['api_key']         = sanitize_text_field( $input['api_key'] ?? '' );
        $out['append_to_email'] = empty( $input['append_to_email'] ) ? 0 : 1;
        $out['timeout']         = max( 2, min( 10, (int) ( $input['timeout'] ?? 4 ) ) );
        return $out;
    }

    public function menu() {
        add_options_page(
            __( 'Mazin Lead Meta (CF7)', 'mazin-lead-meta-cf7' ),
            __( 'Mazin Lead Meta (CF7)', 'mazin-lead-meta-cf7' ),
            'manage_options',
            self::OPTION,
            [ $this, 'render' ]
        );
    }

    public function render() {
        $tags = [
            '[_mazin_user_ip]',
            '[_mazin_country]',
            '[_mazin_city]',
            '[_mazin_country_city]',
            '[_mazin_timezone]',
            '[_mazin_user_agent]',
            '[_mazin_browser]',
            '[_mazin_os]',
            '[_mazin_device]',
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Mazin Lead Metadata for CF7', 'mazin-lead-meta-cf7' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use these special mail-tags in your Contact Form 7 email body:', 'mazin-lead-meta-cf7' ) . '</p>';
        echo '<ul style="margin-left:18px;list-style:disc">';
        foreach ( $tags as $t ) {
            echo '<li><code>' . esc_html( $t ) . '</code></li>';
        }
        echo '</ul>';
        echo '<p>' . esc_html__( 'Tip: You do not need to add fields to the form—these are resolved automatically on submission.', 'mazin-lead-meta-cf7' ) . '</p>';
        echo '<hr />';
        echo '<form method="post" action="options.php">';
        settings_fields( self::OPTION );
        do_settings_sections( self::OPTION );
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
