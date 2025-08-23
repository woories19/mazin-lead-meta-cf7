<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mazin_CF7_Meta_Handler {

    public function __construct() {
        add_filter( 'wpcf7_special_mail_tags', [$this, 'replace_meta_tags'], 10, 3 );
        add_action( 'init', [$this, 'test_meta_data'] );
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
        // Check for Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        
        // Check for other proxy headers
        $proxy_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($proxy_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return sanitize_text_field($ip);
                }
            }
        }

        // Fallback to REMOTE_ADDR
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return null;
    }

    private function get_location_from_ip( $ip ) {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->log_event("Invalid IP address: {$ip}");
            return null;
        }

        // Skip private/local IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $this->log_event("Private/local IP detected: {$ip}");
            return 'Local Network';
        }

        $service = get_option('mazin_cf7_ip_service', 'ipapi');
        $location = null;

        switch ($service) {
            case 'ipapi':
                $location = $this->lookup_ipapi($ip);
                break;
            case 'ipwhois':
                $location = $this->lookup_ipwhois($ip);
                break;
            case 'ipinfo':
                $location = $this->lookup_ipinfo($ip);
                break;
            default:
                $location = $this->lookup_ipapi($ip);
        }

        if (!$location) {
            $this->log_event("All IP lookup services failed for IP: {$ip}");
        }

        return $location;
    }

    private function lookup_ipapi($ip) {
        $url = "http://ip-api.com/json/{$ip}?fields=country,city,status";
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'user-agent' => 'Mazin-CF7-Plugin/1.0'
        ]);

        if (is_wp_error($response)) {
            $this->log_event("IP-API lookup failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] === 'success') {
            $location = '';
            if (!empty($data['country'])) $location .= $data['country'];
            if (!empty($data['city'])) $location .= (!empty($location) ? ', ' : '') . $data['city'];
            return $location ?: null;
        }

        $this->log_event("IP-API returned invalid data for {$ip}: " . $body);
        return null;
    }

    private function lookup_ipwhois($ip) {
        $url = "https://ipwho.is/{$ip}";
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'user-agent' => 'Mazin-CF7-Plugin/1.0'
        ]);

        if (is_wp_error($response)) {
            $this->log_event("IPWhois lookup failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['success']) && $data['success'] === true) {
            $location = '';
            if (!empty($data['country'])) $location .= $data['country'];
            if (!empty($data['city'])) $location .= (!empty($location) ? ', ' : '') . $data['city'];
            return $location ?: null;
        }

        $this->log_event("IPWhois returned invalid data for {$ip}: " . $body);
        return null;
    }

    private function lookup_ipinfo($ip) {
        $url = "https://ipinfo.io/{$ip}/json";
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'user-agent' => 'Mazin-CF7-Plugin/1.0'
        ]);

        if (is_wp_error($response)) {
            $this->log_event("IPInfo lookup failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['country']) || isset($data['city'])) {
            $location = '';
            if (!empty($data['country'])) $location .= $data['country'];
            if (!empty($data['city'])) $location .= (!empty($location) ? ', ' : '') . $data['city'];
            return $location ?: null;
        }

        $this->log_event("IPInfo returned invalid data for {$ip}: " . $body);
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
        elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
        elseif (preg_match('/Opera|OPR/i', $ua)) $browser = 'Opera';

        if (preg_match('/Windows NT 10/i', $ua)) $os = 'Windows 10/11';
        elseif (preg_match('/Windows NT 6.3/i', $ua)) $os = 'Windows 8.1';
        elseif (preg_match('/Windows NT 6.2/i', $ua)) $os = 'Windows 8';
        elseif (preg_match('/Windows NT 6.1/i', $ua)) $os = 'Windows 7';
        elseif (preg_match('/Windows NT 6.0/i', $ua)) $os = 'Windows Vista';
        elseif (preg_match('/Windows NT 5.1/i', $ua)) $os = 'Windows XP';
        elseif (preg_match('/Windows/i', $ua)) $os = 'Windows';
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
        if ( get_option('mazin_cf7_enable_logging', 0) && defined('WP_DEBUG') && WP_DEBUG ) {
            $log_file = WP_CONTENT_DIR . '/mazin-cf7-meta.log';
            $time = date("Y-m-d H:i:s");
            $ip = $this->get_user_ip() ?: 'Unknown';
            error_log("[{$time}] [IP: {$ip}] {$message}\n", 3, $log_file);
        }
    }

    /**
     * Test method to verify plugin functionality
     * Call this from browser: ?mazin_test_meta=1
     */
    public function test_meta_data() {
        if (isset($_GET['mazin_test_meta']) && current_user_can('manage_options')) {
            $meta = $this->get_meta_data();
            echo '<h2>Mazin CF7 Meta Test Results</h2>';
            echo '<pre>';
            print_r($meta);
            echo '</pre>';
            
            echo '<h3>Raw Server Data</h3>';
            echo '<pre>';
            echo 'REMOTE_ADDR: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Not set') . "\n";
            echo 'HTTP_X_FORWARDED_FOR: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not set') . "\n";
            echo 'HTTP_CLIENT_IP: ' . ($_SERVER['HTTP_CLIENT_IP'] ?? 'Not set') . "\n";
            echo 'HTTP_CF_CONNECTING_IP: ' . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'Not set') . "\n";
            echo 'HTTP_USER_AGENT: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') . "\n";
            echo '</pre>';
            exit;
        }
    }
}
