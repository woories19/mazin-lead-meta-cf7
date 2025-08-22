<?php
if (!class_exists('Mazin_GitHub_Updater')) {
class Mazin_GitHub_Updater {
    private $file;
    private $basename;
    private $username;
    private $repo;
    private $api_url;

    public function __construct($file, $username, $repo) {
        $this->file     = $file;
        $this->basename = plugin_basename($file); // mazin-lead-meta-cf7/mazin-lead-meta-cf7.php
        $this->username = $username;
        $this->repo     = $repo;
        $this->api_url  = "https://api.github.com/repos/{$this->username}/{$this->repo}";

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api',                           [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection',             [$this, 'fix_github_folder_name'], 10, 4);
    }

    private function get_latest_release() {
        $cache_key = 'mazin_cf7_github_release_' . md5($this->repo);
        $cached = get_site_transient($cache_key);
        if ($cached) return $cached;

        $args = ['headers' => [
            'User-Agent' => 'WordPress; Mazin Lead Meta CF7',
            'Accept'     => 'application/vnd.github+json'
        ]];
        $res = wp_remote_get($this->api_url . '/releases/latest', $args);
        if (is_wp_error($res)) return false;
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code !== 200) return false;

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($body)) return false;

        set_site_transient($cache_key, $body, 30 * MINUTE_IN_SECONDS);
        return $body;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $release = $this->get_latest_release();
        if (!$release || empty($release['tag_name'])) return $transient;

        $new_version = ltrim($release['tag_name'], 'v');
        $plugin_data = get_plugin_data($this->file, false, false);
        if (!isset($plugin_data['Version'])) return $transient;

        if (version_compare($plugin_data['Version'], $new_version, '<')) {
            // Prefer a release asset zip named appropriately
            $package = '';
            if (!empty($release['assets']) && is_array($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    if (!empty($asset['browser_download_url']) && preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                        $package = $asset['browser_download_url'];
                        break;
                    }
                }
            }
            // Fallback to zipball_url
            if (!$package && !empty($release['zipball_url'])) {
                $package = $release['zipball_url'];
            }

            $obj = new stdClass();
            $obj->slug        = dirname($this->basename); // mazin-lead-meta-cf7
            $obj->plugin      = $this->basename;          // full plugin basename
            $obj->new_version = $new_version;
            $obj->url         = $release['html_url'] ?? "https://github.com/{$this->username}/{$this->repo}";
            $obj->package     = $package;

            $transient->response[$this->basename] = $obj;
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') return $res;
        if (empty($args->slug) || $args->slug !== dirname($this->basename)) return $res;

        $release = $this->get_latest_release();
        if (!$release) return $res;

        $plugin_data = get_plugin_data($this->file, false, false);

        $info = new stdClass();
        $info->name          = $plugin_data['Name'] ?? 'Mazin Lead Meta for CF7';
        $info->slug          = dirname($this->basename);
        $info->plugin_name   = $this->basename;
        $info->version       = ltrim($release['tag_name'] ?? '0.0.0', 'v');
        $info->author        = '<a href="https://mazindigital.com">Mazin Digital</a>';
        $info->homepage      = $release['html_url'] ?? "https://github.com/{$this->username}/{$this->repo}";
        $info->download_link = (!empty($release['assets'][0]['browser_download_url']))
            ? $release['assets'][0]['browser_download_url']
            : ($release['zipball_url'] ?? '');
        $info->sections      = [
            'description' => $plugin_data['Description'] ?? '',
            'changelog'   => $release['body'] ?? '',
        ];
        return $info;
    }

    // GitHub zips extract to repo-hash folder; rename to plugin dir so WP can replace correctly
    public function fix_github_folder_name($source, $remote_source, $upgrader, $hook_extra) {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) return $source;

        $proper_folder_name = dirname($this->basename); // mazin-lead-meta-cf7
        $parts = explode('/', untrailingslashit($source));
        $folder = end($parts);

        if ($folder !== $proper_folder_name) {
            $new_source = trailingslashit(dirname($source)) . $proper_folder_name . '/';
            @rename($source, $new_source);
            return $new_source;
        }
        return $source;
    }
}}
