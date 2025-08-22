<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mazin_CF7_Meta_Handler {

    public function __construct() {
        // CF7 mail-tags (works even if the form doesn’t define these fields)
        add_filter( 'wpcf7_special_mail_tags', [ $this, 'special_tags' ], 10, 3 );
        // Optionally append metadata block to the email body
        add_filter( 'wpcf7_mail_components', [ $this, 'maybe_append_block' ], 10, 3 );
    }

    /** Hook: resolve our custom special mail-tags */
    public function special_tags( $output, $name, $html ) {
        $tag = strtolower( $name );
        if ( strpos( $tag, '_mazin_' ) !== 0 ) {
            return $output;
        }

        $settings = class_exists( 'Mazin_CF7_Settings' ) ? Mazin_CF7_Settings::get_settings() : [];
        $enabled  = ! empty( $settings['enable'] );
        $ip       = $this->get_client_ip();
        $ua_raw   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua       = $this->parse_user_agent( $ua_raw );
        $geo      = $enabled ? $this->geo_lookup( $ip, $settings ) : [];

        switch ( $tag ) {
            case '_mazin_user_ip':       return $ip;
            case '_mazin_user_agent':    return $ua_raw;
            case '_mazin_browser':       return $ua['browser'];
            case '_mazin_os':            return $ua['os'];
            case '_mazin_device':        return $ua['device'];
            case '_mazin_country':       return $geo['country']  ?? '';
            case '_mazin_city':          return $geo['city']     ?? '';
            case '_mazin_country_city':  return $this->join_nonempty( [ $geo['city'] ?? '', $geo['country'] ?? '' ] );
            case '_mazin_timezone':      return $geo['timezone'] ?? '';
            default: return $output;
        }
    }

    /** Hook: auto-append a neat block if enabled */
    public function maybe_append_block( $components, $contact_form, $instance ) {
        $settings = class_exists( 'Mazin_CF7_Settings' ) ? Mazin_CF7_Settings::get_settings() : [];
        if ( empty( $settings['append_to_email'] ) ) return $components;

        $block = "\n\n--- Lead Metadata (Mazin) ---\n"
               . "IP: [_mazin_user_ip]\n"
               . "Location: [_mazin_country_city]\n"
               . "Timezone: [_mazin_timezone]\n"
               . "Browser: [_mazin_browser]\n"
               . "OS: [_mazin_os]\n"
               . "Device: [_mazin_device]\n";

        if ( isset( $components['body'] ) && strpos( $components['body'], '[_mazin_user_ip]' ) === false ) {
            $components['body'] .= $block;
        }
        return $components;
    }

    /** ---------------- Utilities ---------------- */

    private function join_nonempty( array $parts, $sep = ', ' ) {
        return implode( $sep, array_values( array_filter( array_map( 'trim', $parts ) ) ) );
    }

    /** Try to get a real client IP (respecting proxies) */
    private function get_client_ip() {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        foreach ( $keys as $k ) {
            if ( empty( $_SERVER[ $k ] ) ) continue;
            $iplist = explode( ',', $_SERVER[ $k ] );
            foreach ( $iplist as $ip ) {
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        // fall back (may be private if behind proxy)
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /** UA → Browser/OS/Device (lightweight) */
    private function parse_user_agent( $ua ) {
        $ua = (string) $ua;

        // Browser
        $browser = 'Unknown';
        if ( preg_match( '/Edg\\//', $ua ) )               $browser = 'Edge';
        elseif ( preg_match( '/OPR\\//', $ua ) )            $browser = 'Opera';
        elseif ( preg_match( '/Chrome\\//', $ua ) && !preg_match('/Chromium/', $ua) ) $browser = 'Chrome';
        elseif ( preg_match( '/Firefox\\//', $ua ) )        $browser = 'Firefox';
        elseif ( preg_match( '/Safari\\//', $ua ) && preg_match('/Version\\//', $ua) ) $browser = 'Safari';

        // OS
        $os = 'Unknown';
        if ( preg_match( '/Windows NT 10\\.0/', $ua ) )          $os = 'Windows 10/11';
        elseif ( preg_match( '/Windows NT 6\\./', $ua ) )         $os = 'Windows 7/8';
        elseif ( preg_match( '/Mac OS X/', $ua ) )                $os = 'macOS';
        elseif ( preg_match( '/Android/', $ua ) )                 $os = 'Android';
        elseif ( preg_match( '/(iPhone|iPad|iPod)/', $ua ) )      $os = 'iOS';
        elseif ( preg_match( '/Linux/', $ua ) )                   $os = 'Linux';

        // Device
        $device = 'Desktop';
        if ( preg_match( '/iPad|Tablet/i', $ua ) )                $device = 'Tablet';
        elseif ( preg_match( '/Mobile|iPhone|Android/i', $ua ) )  $device = 'Mobile';

        return [
            'browser' => $browser,
            'os'      => $os,
            'device'  => $device,
        ];
    }

    /** IP → Geo via chosen provider */
    private function geo_lookup( $ip, $settings ) {
        if ( empty( $ip ) ) return [];

        $provider = $settings['provider'] ?? 'ipapi';
        $key      = trim( $settings['api_key'] ?? '' );
        $timeout  = (int) ( $settings['timeout'] ?? 4 );

        switch ( $provider ) {
            case 'ipinfo':
                $url = 'https://ipinfo.io/' . rawurlencode( $ip ) . '/json';
                if ( $key ) $url .= '?token=' . rawurlencode( $key );
                break;

            case 'ipstack':
                if ( ! $key ) return [];
                $url = 'http://api.ipstack.com/' . rawurlencode( $ip ) . '?access_key=' . rawurlencode( $key );
                break;

            case 'ipapi':
            default:
                $url = 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/';
                break;
        }

        $res = wp_remote_get( $url, [ 'timeout' => $timeout, 'headers' => [ 'User-Agent' => 'WordPress' ] ] );
        if ( is_wp_error( $res ) ) return [];
        if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return [];

        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! is_array( $data ) ) return [];

        // Normalize keys across providers
        $country  = $data['country_name'] ?? ( $data['country'] ?? '' ); // ipinfo uses country code in 'country'
        if ( ! empty( $data['country'] ) && strlen( $data['country'] ) === 2 && ! empty( $data['country_name'] ) ) {
            $country = $data['country_name'];
        }
        // ipinfo city: 'city'; ipapi/ipstack: 'city'
        $city     = $data['city'] ?? '';
        // ipapi: timezone; ipstack: time_zone; ipinfo: timezone
        $timezone = $data['timezone'] ?? ( $data['time_zone'] ?? '' );

        return [
            'country'  => is_string( $country ) ? $country : '',
            'city'     => is_string( $city ) ? $city : '',
            'timezone' => is_string( $timezone ) ? $timezone : '',
        ];
    }
}
