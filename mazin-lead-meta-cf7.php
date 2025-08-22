<?php
/**
 * Plugin Name: Mazin Lead Meta for CF7
 * Plugin URI:  https://github.com/woories19/mazin-lead-meta-cf7
 * Description: Capture IP, country, city, browser, OS & device info in Contact Form 7 emails.
 * Version:     0.3.0
 * Author:      Mazin Digital
 * Author URI:  https://mazindigital.com
 * License:     GPL2
 * Text Domain: mazin-lead-meta-cf7
 */

if (!defined('ABSPATH')) exit;

define('MAZIN_CF7_PATH', plugin_dir_path(__FILE__));
define('MAZIN_CF7_URL',  plugin_dir_url(__FILE__));
define('MAZIN_CF7_SLUG', plugin_basename(__FILE__)); // mazin-lead-meta-cf7/mazin-lead-meta-cf7.php

// Includes (keep your structure)
require_once MAZIN_CF7_PATH . 'includes/class-settings.php';
require_once MAZIN_CF7_PATH . 'includes/class-meta-handler.php';
require_once MAZIN_CF7_PATH . 'updater/class-github-updater.php';

/* ----------------- Logging ----------------- */
function mazin_cf7_log($message, $context = []) {
    $file = WP_CONTENT_DIR . '/mazin-lead-meta.log';
    $time = date('Y-m-d H:i:s');
    $line = '['.$time.'] '.$message;
    if (!empty($context)) {
        $line .= ' | ' . wp_json_encode($context);
    }
    error_log($line . "\n", 3, $file);
}

/* ----------------- IP helpers ----------------- */
function mazin_cf7_client_ip() {
    $keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',  // Proxies
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];
    foreach ($keys as $k) {
        if (empty($_SERVER[$k])) continue;
        $parts = explode(',', $_SERVER[$k]);
        foreach ($parts as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/* ----------------- UA parsing ----------------- */
function mazin_cf7_parse_ua($ua) {
    $ua = (string)($ua ?? '');
    // Browser
    $browser = 'Unknown';
    if (preg_match('/Edg\//', $ua))                     $browser = 'Edge';
    elseif (preg_match('/OPR\//', $ua))                 $browser = 'Opera';
    elseif (preg_match('/Chrome\/(?!.*Chromium)/', $ua))$browser = 'Chrome';
    elseif (preg_match('/Firefox\//', $ua))             $browser = 'Firefox';
    elseif (preg_match('/Safari\/.*Version\//', $ua))   $browser = 'Safari';

    // OS
    $os = 'Unknown';
    if (preg_match('/Windows NT 10\.0/', $ua))          $os = 'Windows 10/11';
    elseif (preg_match('/Windows NT 6\./', $ua))        $os = 'Windows 7/8';
    elseif (preg_match('/Mac OS X/', $ua))              $os = 'macOS';
    elseif (preg_match('/Android/', $ua))               $os = 'Android';
    elseif (preg_match('/(iPhone|iPad|iPod)/', $ua))    $os = 'iOS';
    elseif (preg_match('/Linux/', $ua))                 $os = 'Linux';

    // Device
    $device = 'Desktop';
    if (preg_match('/iPad|Tablet/i', $ua))              $device = 'Tablet';
    elseif (preg_match('/Mobile|iPhone|Android/i', $ua))$device = 'Mobile';

    return [$browser, $os, $device];
}

/* ----------------- Geo lookup ----------------- */
function mazin_cf7_geo_lookup($ip, $settings) {
    if (!$ip) return ['country'=>'', 'city'=>'', 'timezone'=>''];

    $provider = $settings['provider'] ?? 'ipapi';
    $key      = trim($settings['api_key'] ?? '');
    $timeout  = (int)($settings['timeout'] ?? 4);

    switch ($provider) {
        case 'ipinfo':
            $url = "https://ipinfo.io/" . rawurlencode($ip) . "/json";
            if ($key) $url .= "?token=" . rawurlencode($key);
            break;
        case 'ipstack':
            if (!$key) return ['country'=>'', 'city'=>'', 'timezone'=>''];
            $url = "http://api.ipstack.com/" . rawurlencode($ip) . "?access_key=" . rawurlencode($key);
            break;
        case 'ipapi':
        default:
            $url = "https://ipapi.co/" . rawurlencode($ip) . "/json/";
            break;
    }

    $res = wp_remote_get($url, [
        'timeout' => $timeout,
        'headers' => ['User-Agent' => 'WordPress; Mazin Lead Meta CF7']
    ]);

    if (is_wp_error($res) || (int)wp_remote_retrieve_response_code($res) !== 200) {
        mazin_cf7_log('Geo API request failed', ['provider'=>$provider, 'ip'=>$ip, 'error'=>is_wp_error($res)?$res->get_error_message():'HTTP '.wp_remote_retrieve_response_code($res)]);
        return ['country'=>'', 'city'=>'', 'timezone'=>''];
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return ['country'=>'', 'city'=>'', 'timezone'=>''];

    // Normalize keys
    $country = $data['country_name'] ?? ($data['country'] ?? '');
    // ipinfo uses country code (e.g., "PK"); if you want full names there, prefer ipapi/ipstack.
    $city    = $data['city'] ?? '';
    $tz      = $data['timezone'] ?? ($data['time_zone'] ?? '');

    return [
        'country'  => is_string($country) ? $country : '',
        'city'     => is_string($city) ? $city : '',
        'timezone' => is_string($tz) ? $tz : '',
    ];
}

/* ----------------- Collect meta (per submission) ----------------- */
function mazin_cf7_collect_meta() {
    $settings = class_exists('Mazin_CF7_Settings') ? Mazin_CF7_Settings::get_settings() : Mazin_CF7_Settings::defaults();

    $ip  = mazin_cf7_client_ip();
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    [$browser, $os, $device] = mazin_cf7_parse_ua($ua);

    $geo = ['country'=>'', 'city'=>'', 'timezone'=>''];
    if (!empty($settings['enable'])) {
        $geo = mazin_cf7_geo_lookup($ip, $settings);
    }

    $meta = [
        'ip'       => $ip ?: 'Unknown',
        'country'  => $geo['country'] ?: 'Unknown',
        'city'     => $geo['city'] ?: 'Unknown',
        'timezone' => $geo['timezone'] ?: 'Unknown',
        'browser'  => $browser ?: 'Unknown',
        'os'       => $os ?: 'Unknown',
        'device'   => $device ?: 'Unknown',
        'ua'       => $ua ?: '',
    ];

    mazin_cf7_log('Collected meta', $meta);
    return $meta;
}

/* ----------------- Replace tags (rock-solid) ----------------- */
/**
 * We replace our placeholders directly in the outgoing email components.
 * This guarantees no raw [_mazin_*] appears, even if CF7’s internal
 * special-tag system doesn’t pick them up.
 */
add_filter('wpcf7_mail_components', function($components) {
    $meta = mazin_cf7_collect_meta();

    $pairs = [
        '[_mazin_user_ip]'      => $meta['ip'],
        '[_mazin_country]'      => $meta['country'],
        '[_mazin_city]'         => $meta['city'],
        '[_mazin_country_city]' => trim($meta['city'] && $meta['country'] ? $meta['city'].', '.$meta['country'] : ($meta['country'] ?: $meta['city'] ?: 'Unknown')),
        '[_mazin_timezone]'     => $meta['timezone'],
        '[_mazin_user_agent]'   => $meta['ua'],
        '[_mazin_browser]'      => $meta['browser'],
        '[_mazin_os]'           => $meta['os'],
        '[_mazin_device]'       => $meta['device'],
    ];

    foreach (['subject','body','additional_headers'] as $part) {
        if (!empty($components[$part])) {
            $components[$part] = strtr($components[$part], $pairs);
        }
    }

    // If your settings auto-append a block in class-meta-handler.php that uses tags,
    // the replacements above will convert them to real values. No duplication here.

    return $components;
}, 5); // run early, before other filters

/* ----------------- (Optional) also register as CF7 special tags ----------------- */
add_filter('wpcf7_special_mail_tags', function($replaced, $name) {
    $name = strtolower($name);
    if (strpos($name, '_mazin_') !== 0) return $replaced;

    $m = mazin_cf7_collect_meta();

    switch ($name) {
        case '_mazin_user_ip':       return $m['ip'];
        case '_mazin_country':       return $m['country'];
        case '_mazin_city':          return $m['city'];
        case '_mazin_country_city':  return trim($m['city'] && $m['country'] ? $m['city'].', '.$m['country'] : ($m['country'] ?: $m['city'] ?: 'Unknown'));
        case '_mazin_timezone':      return $m['timezone'];
        case '_mazin_user_agent':    return $m['ua'];
        case '_mazin_browser':       return $m['browser'];
        case '_mazin_os':            return $m['os'];
        case '_mazin_device':        return $m['device'];
        default: return $replaced;
    }
}, 10, 2);

/* ----------------- Bootstrap & Updater ----------------- */
add_action('plugins_loaded', function() {
    try {
        new Mazin_CF7_Settings();
        new Mazin_CF7_Meta_Handler(); // your original class (kept)
        mazin_cf7_log('Plugin initialized.');
    } catch (Throwable $e) {
        mazin_cf7_log('Init error', ['error'=>$e->getMessage()]);
    }
});

if (is_admin()) {
    try {
        // updated class below (replace its file too)
        new Mazin_GitHub_Updater(__FILE__, 'woories19', 'mazin-lead-meta-cf7');
        mazin_cf7_log('GitHub updater loaded.');
    } catch (Throwable $e) {
        mazin_cf7_log('Updater error', ['error'=>$e->getMessage()]);
    }
}
