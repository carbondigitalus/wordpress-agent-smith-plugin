<?php
/*
Plugin Name: Agent Smith (GitHub Edition) – Plugins & Themes Only
Description: Overrides plugin and theme updates by rebuilding their transients using data from your GitHub repository.
Version: 1.0.0
Author: Carbon Digital
Author URI: https://carbondigital.us
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentSmith_MU {
    private $github_base_url;
    private $github_token;

    public function __construct() {
        // Load settings.
        $this->github_base_url = get_option( 'agent_smith_github_repo', '' );
        $this->github_token    = get_option( 'agent_smith_github_token', '' );

        // Override plugin updates.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'build_plugin_update_transient' ) );
        // Override theme updates.
        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'build_theme_update_transient' ) );

        // Force update the transients on admin load.
        add_action( 'admin_init', array( $this, 'force_plugin_update_transient' ), 1 );
        add_action( 'admin_init', array( $this, 'force_theme_update_transient' ), 1 );

        // Options page: In multisite, add to network admin; otherwise, add to admin menu.
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', array( $this, 'add_options_page' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        }
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Build the plugin update transient using GitHub data.
     */
    public function build_plugin_update_transient( $transient ) {
        // Initialize a new object if needed.
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }
        // Attempt to fetch data from GitHub.
        $github_data = $this->make_github_request( 'plugins.json' );
        if ( $github_data && is_array( $github_data ) ) {
            $response = array();
            foreach ( $github_data as $plugin_slug => $data ) {
                if ( ! empty( $data ) ) {
                    // Convert the data to an object.
                    $response[$plugin_slug] = (object) $data;
                }
            }
            $transient->response = $response;
        }
        return $transient;
    }

    /**
     * Build the theme update transient using GitHub data.
     */
    public function build_theme_update_transient( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }
        $github_data = $this->make_github_request( 'themes.json' );
        if ( $github_data && is_array( $github_data ) ) {
            $response = array();
            foreach ( $github_data as $theme_slug => $data ) {
                if ( ! empty( $data ) ) {
                    $response[$theme_slug] = (object) $data;
                }
            }
            $transient->response = $response;
        }
        return $transient;
    }

    /**
     * Force-update the plugin update transient on admin load.
     */
    public function force_plugin_update_transient() {
        if ( is_admin() && function_exists( 'update_site_transient' ) ) {
            delete_site_transient( 'update_plugins' );
            if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
            }
            $transient = $this->build_plugin_update_transient( new stdClass() );
            if ( is_object( $transient ) ) {
                update_site_transient( 'update_plugins', $transient );
            }
        }
    }

    /**
     * Force-update the theme update transient on admin load.
     */
    public function force_theme_update_transient() {
        if ( is_admin() && function_exists( 'update_site_transient' ) ) {
            delete_site_transient( 'update_themes' );
            if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
            }
            $transient = $this->build_theme_update_transient( new stdClass() );
            if ( is_object( $transient ) ) {
                update_site_transient( 'update_themes', $transient );
            }
        }
    }

    /**
     * Add an options page.
     */
    public function add_options_page() {
        add_options_page(
            'Agent Smith',
            'Agent Smith',
            'manage_options',
            'agent-smith',
            array( $this, 'settings_page_content' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'agent_smith_settings', 'agent_smith_github_repo', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ) );
        register_setting( 'agent_smith_settings', 'agent_smith_github_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        add_settings_section(
            'agent_smith_main_section',
            'Agent Smith Settings',
            function() {
                echo '<p>Configure Agent Smith with your GitHub repository settings.</p>';
            },
            'agent-smith'
        );
        add_settings_field(
            'agent_smith_github_repo',
            'GitHub Repo URL',
            array( $this, 'settings_field_repo_callback' ),
            'agent-smith',
            'agent_smith_main_section'
        );
        add_settings_field(
            'agent_smith_github_token',
            'GitHub Personal Access Token (PAT)',
            array( $this, 'settings_field_token_callback' ),
            'agent-smith',
            'agent_smith_main_section'
        );
    }

    /**
     * Render the options page.
     */
    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1>Agent Smith Settings</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'agent_smith_settings' );
                    do_settings_sections( 'agent-smith' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the GitHub Repo URL field.
     */
    public function settings_field_repo_callback() {
        $value = esc_url( get_option( 'agent_smith_github_repo', '' ) );
        echo "<input type='url' id='agent_smith_github_repo' name='agent_smith_github_repo' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter the raw GitHub URL (e.g., <code>https://raw.githubusercontent.com/your-org/updates-repo/main</code>).</p>";
    }

    /**
     * Render the GitHub Token field.
     */
    public function settings_field_token_callback() {
        $value = esc_attr( get_option( 'agent_smith_github_token', '' ) );
        echo "<input type='password' id='agent_smith_github_token' name='agent_smith_github_token' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter your GitHub Personal Access Token with read access to the repository.</p>";
    }

    /**
     * Make an authenticated request to GitHub for a JSON file.
     */
    private function make_github_request( $json_file ) {
        if ( empty( $this->github_base_url ) || empty( $this->github_token ) ) {
            return false;
        }
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
}

new AgentSmith_MU();
