<?php
/*
Plugin Name: Agent Smith (GitHub Edition)
Description: Redirects WordPress core updates to a GitHub repository instead of WP.org.
Version: 1.1.0
Author: Carbon Digital
Author URI: https://carbondigital.us
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentSmith {
    private $github_base_url;
    private $github_token;

    public function __construct() {
        $this->github_base_url = get_option( 'agent_smith_github_repo', '' );
        $this->github_token    = get_option( 'agent_smith_github_token', '' );

        add_filter( 'pre_set_site_transient_update_core', array( $this, 'check_core_updates' ) );
        add_action( 'admin_init', array( $this, 'register_settings_fields' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
    }

    public function check_core_updates( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }
        
        // Set defaults based on current installation.
        $current_version = get_bloginfo( 'version' );
        $locale          = get_locale();
        
        // These are the properties WordPress will look for.
        $transient->updates         = array();
        $transient->current         = $current_version;
        $transient->locale          = $locale;
        $transient->version_checked = $current_version;
        
        // Attempt to get update data from GitHub.
        $github_data = $this->make_github_request( 'core.json' );
        
        if ( $github_data && is_array( $github_data ) && ! empty( $github_data['current'] ) ) {
            // Build the update object exactly as WP expects.
            $update = new stdClass();
            $update->response = 'upgrade'; // Tells WP an upgrade is available.
            $update->current  = $github_data['current'];
            $update->locale   = isset( $github_data['locale'] ) ? $github_data['locale'] : $locale;
            $update->package  = isset( $github_data['package'] ) ? $github_data['package'] : ''; // Must be defined.
            
            // Return this update object.
            $transient->updates         = array( $update );
            $transient->version_checked = $update->current;
            $transient->current         = $update->current;
            $transient->locale          = $update->locale;
        } else {
            // If GitHub data isn’t valid, simulate no update available.
            $update = new stdClass();
            $update->response = 'latest';
            $update->current  = $current_version;
            $update->locale   = $locale;
            $update->package  = '';
            
            $transient->updates = array( $update );
        }
        
        return $transient;
    }
    
    private function make_github_request( $json_file ) {
        if ( empty( $this->github_base_url ) || empty( $this->github_token ) ) {
            return false;
        }
        
        // Build URL; ensure a trailing slash in the repo URL.
        $url = trailingslashit( $this->github_base_url ) . $json_file;
        
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->github_token,
                'Accept'        => 'application/vnd.github.v3.raw'
            )
        );
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }
    
    public function register_settings_fields() {
        register_setting( 'general', 'agent_smith_github_repo', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ) );
        
        register_setting( 'general', 'agent_smith_github_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        add_settings_field(
            'agent_smith_github_repo',
            'Agent Smith GitHub Repo URL',
            array( $this, 'settings_field_repo_callback' ),
            'general',
            'default'
        );
        
        add_settings_field(
            'agent_smith_github_token',
            'Agent Smith GitHub Token',
            array( $this, 'settings_field_token_callback' ),
            'general',
            'default'
        );
    }
    
    public function settings_field_repo_callback() {
        $value = esc_url( get_option( 'agent_smith_github_repo', '' ) );
        echo "<input type='url' id='agent_smith_github_repo' name='agent_smith_github_repo' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter the raw GitHub URL for updates (for example, <code>https://raw.githubusercontent.com/your-org/updates-repo/main</code>).</p>";
    }
    
    public function settings_field_token_callback() {
        $value = esc_attr( get_option( 'agent_smith_github_token', '' ) );
        echo "<input type='password' id='agent_smith_github_token' name='agent_smith_github_token' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter your GitHub Personal Access Token (PAT) with read access to the repository.</p>";
    }
    
    public function display_admin_notice() {
        if ( empty( $this->github_base_url ) || empty( $this->github_token ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Agent Smith Plugin:</strong> Please set the GitHub repo URL and Token in <a href="' . admin_url( 'options-general.php' ) . '">Settings > General</a>.</p></div>';
        }
    }
}

new AgentSmith();
