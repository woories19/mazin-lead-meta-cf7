<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mazin_CF7_Meta_Handler {

    public function __construct() {
        add_filter( 'wpcf7_special_mail_tags', [$this, 'replace_meta_tags'], 10, 3 );
    }

    /**
     * Replace special CF7 tags with user metadata
     */
    public function replace_meta_tags( $output, $name, $html ) {
        $name = strtolower( $name );
        $meta = $this->get_meta_data();

        if ( isset( $meta[$name] ) ) {
            $this->log_event("Replacing tag: {$name} with value: {$meta[$name]}");
            return $meta[$name];
        }

        return $output; // default CF7 behavior
    }

    /**
     * Collect meta data (IP, location, device info)
     */
    private function get_meta_data() {
        $ip = $this->get_user_ip();
        $location = $this->get_location_from_ip( $ip );
        $ua_data = $this->parse_user_agent();

        return [
            '_mazin_user_ip'     => $ip ?: 'Unknown',
            '_mazin_country_city'=> $location ?: 'Unknown',
            '_mazin_timezone'    => $this->get_timezone() ?: 'Unknown',
            '_mazin_browser'     => $ua_data['browser'] ?? 'Unknown',
            '_mazin_os'          => $ua_data['os'] ?? 'Unknown',
            '_mazin_device'      => $ua_data['device'] ?? 'Unknown',
        ];
    }

    private function get_user_ip() {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return sanitize_text_field( $_SERVER[$key] );
            }
        }
        return null;
    }

    private function get_location_from_ip( $ip ) {
        if (!$ip) return null;

        $url = "http://ip-api.com/json/{$ip}?fields=country,city,status";
        $response = wp_remote_get($url, ['timeout' => 5]);

        if ( is_wp_error($response) ) {
            $this->log_event("IP lookup failed: " . $response->get_error_message());
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body($response), true );
        if ( isset($data['status']) && $data['status'] === 'success' ) {
            return $data['country'] . ', ' . $data['city'];
        }

        $this->log_event("IP lookup returned invalid data for {$ip}");
        return null;
    }

    private function get_timezone() {
        return wp_timezone_string();
    }

    private function parse_user_agent() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'Desktop';

        if (preg_match('/mobile/i', $ua)) $device = 'Mobile';
        if (preg_match('/tablet/i', $ua)) $device = 'Tablet';

        if (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';

        if (preg_match('/Windows/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Macintosh/i', $ua)) $os = 'MacOS';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';

        return [
            'browser' => $browser,
            'os'      => $os,
            'device'  => $device,
        ];
    }

    /**
     * Write logs for debugging
     */
    private function log_event($message) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            $log_file = WP_CONTENT_DIR . '/mazin-cf7-meta.log';
            $time = date("Y-m-d H:i:s");
            error_log("[{$time}] {$message}\n", 3, $log_file);
        }
    }
}
