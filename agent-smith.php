<?php
/*
Plugin Name: Agent Smith (GitHub Edition)
Description: Redirects WordPress core, plugin, and theme updates to a GitHub repository instead of WP.org.
Version: 1.0.0
Author: Carbon Digital
Author URI: https://carbondigital.us
*/

if (!defined('ABSPATH')) {
    exit;
}

class AgentSmith {
    private $github_base_url;

    public function __construct() {
        // Load GitHub URL from options
        $this->github_base_url = get_option('agent_smith_github_repo', '');

        // WordPress filters for updates
        add_filter('pre_set_site_transient_update_core', array($this, 'check_core_updates'), 10, 2);
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_plugin_updates'), 10, 2);
        add_filter('pre_set_site_transient_update_themes', array($this, 'check_theme_updates'), 10, 2);

        // Register settings field in General Settings
        add_action('admin_init', array($this, 'register_settings_field'));

        // Admin notice if no GitHub URL is set
        add_action('admin_notices', array($this, 'display_admin_notice'));
    }

    public function check_core_updates($transient, $transient_name) {
        return $this->fetch_updates($transient, 'core.json', 'updates');
    }

    public function check_plugin_updates($transient, $transient_name) {
        return $this->fetch_updates($transient, 'plugins.json', 'response');
    }

    public function check_theme_updates($transient, $transient_name) {
        return $this->fetch_updates($transient, 'themes.json', 'response');
    }

    private function fetch_updates($transient, $json_file, $response_key) {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        if (!isset($transient->$response_key)) {
            $transient->$response_key = array();
        }

        $response = $this->make_github_request($json_file);
        
        if ($response && is_array($response)) {
            foreach ($response as $slug => $data) {
                if (!empty($data)) {
                    $transient->$response_key[$slug] = (object) $data;
                }
            }
        }

        return $transient;
    }

    private function make_github_request($json_file) {
        if (empty($this->github_base_url)) {
            return false;
        }

        $url = "{$this->github_base_url}/{$json_file}";
        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function register_settings_field() {
        // Add the setting to the database if it doesn't exist
        register_setting('general', 'agent_smith_github_repo', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ));

        // Add the settings field
        add_settings_field(
            'agent_smith_github_repo',
            'Agent Smith GitHub Repo URL',
            array($this, 'settings_field_callback'),
            'general',
            'default'
        );
    }

    public function settings_field_callback() {
        $value = esc_url(get_option('agent_smith_github_repo', ''));
        echo "<input type='url' id='agent_smith_github_repo' name='agent_smith_github_repo' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter the raw GitHub URL for updates.<br>Example: <code>https://raw.githubusercontent.com/your-org/updates-repo/main</code></p>";
    }

    public function display_admin_notice() {
        if (empty($this->github_base_url)) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Agent Smith Plugin:</strong> Please set the GitHub repo URL in <a href="' . admin_url('options-general.php') . '">Settings > General</a>.</p></div>';
        }
    }
}

new AgentSmith();
