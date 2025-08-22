<?php
if ( ! class_exists( 'Mazin_GitHub_Updater' ) ) {
    class Mazin_GitHub_Updater {
        private $file;
        private $plugin;
        private $basename;
        private $github_user;
        private $github_repo;

        public function __construct( $file, $github_user, $github_repo ) {
            $this->file       = $file;
            $this->basename   = plugin_basename( $file );
            $this->plugin     = get_plugin_data( $file );
            $this->github_user= $github_user;
            $this->github_repo= $github_repo;

            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
            add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
        }

        public function get_repo_release() {
            $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
            $response = wp_remote_get( $url, [ 'headers' => [ 'User-Agent' => 'WordPress' ] ] );
            if ( is_wp_error( $response ) ) return false;
            return json_decode( wp_remote_retrieve_body( $response ), true );
        }

        public function check_update( $transient ) {
            if ( empty( $transient->checked ) ) return $transient;

            $release = $this->get_repo_release();
            if ( ! $release ) return $transient;

            $latest_version = ltrim( $release['tag_name'], 'v' );
            $plugin_version = $this->plugin['Version'];

            if ( version_compare( $plugin_version, $latest_version, '<' ) ) {
                $obj = new stdClass();
                $obj->slug = dirname( $this->basename );
                $obj->new_version = $latest_version;
                $obj->url = $release['html_url'];
                $obj->package = $release['zipball_url'];
                $transient->response[ $this->basename ] = $obj;
            }

            return $transient;
        }

        public function plugin_info( $res, $action, $args ) {
            if( $action !== 'plugin_information' ) return $res;
            if( $args->slug !== dirname( $this->basename ) ) return $res;

            $release = $this->get_repo_release();
            if ( ! $release ) return $res;

            $res = new stdClass();
            $res->name = $this->plugin['Name'];
            $res->slug = dirname( $this->basename );
            $res->version = ltrim( $release['tag_name'], 'v' );
            $res->author = $this->plugin['Author'];
            $res->homepage = $this->plugin['AuthorURI'];
            $res->download_link = $release['zipball_url'];
            $res->sections = [
                'description' => $this->plugin['Description'],
                'changelog'   => $release['body']
            ];
            return $res;
        }
    }
}
