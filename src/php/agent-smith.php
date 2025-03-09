<?php
/*
Plugin Name: Agent Smith (GitHub Edition) – Final Fix
Description: Overrides WordPress core updates using GitHub data and always returns a complete update transient.
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
        // Load our settings.
        $this->github_base_url = get_option( 'agent_smith_github_repo', '' );
        $this->github_token    = get_option( 'agent_smith_github_token', '' );

        // Override core updates.
        add_filter( 'pre_set_site_transient_update_core', array( $this, 'check_core_updates' ) );

        // Options page.
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Build a complete core update transient with all required properties.
     */
    public function check_core_updates( $transient ) {
        // Get current version and locale.
        $current_version = get_bloginfo( 'version' );
        $locale          = get_locale();

        // Attempt to fetch update info from GitHub.
        $github_data = $this->make_github_request( 'core.json' );

        // Create an update object.
        $update = new stdClass();
        if ( $github_data && is_array( $github_data ) && ! empty( $github_data['current'] ) ) {
            $update->response = 'upgrade';
            $update->current  = $github_data['current'];
            $update->locale   = isset( $github_data['locale'] ) ? $github_data['locale'] : $locale;
            $update->package  = isset( $github_data['package'] ) ? $github_data['package'] : '';
        } else {
            // If GitHub data isn’t available, simulate “latest”.
            $update->response = 'latest';
            $update->current  = $current_version;
            $update->locale   = $locale;
            $update->package  = '';
        }
        // Build the required packages object.
        $update->packages = new stdClass();
        $update->packages->full         = $update->package;
        $update->packages->partial      = '';
        $update->packages->new_bundled  = '';
        $update->packages->no_content   = '';

        // Build our new transient.
        $new_transient = new stdClass();
        $new_transient->updates         = array( $update );
        $new_transient->current         = $update->current;
        $new_transient->locale          = $update->locale;
        $new_transient->version_checked = $update->current;
        global $wpdb;
        $new_transient->php_version  = PHP_VERSION;
        $new_transient->mysql_version = ( isset( $wpdb ) && is_object( $wpdb ) ) ? $wpdb->db_version() : '';

        return $new_transient;
    }

    /**
     * Create a dedicated settings page under Settings → Agent Smith.
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
     * Register our settings.
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
                echo '<p>Configure Agent Smith to override WordPress core updates with data from your GitHub repository.</p>';
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
     * Output the settings page HTML.
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
     * Output the GitHub Repo URL field.
     */
    public function settings_field_repo_callback() {
        $value = esc_url( get_option( 'agent_smith_github_repo', '' ) );
        echo "<input type='url' id='agent_smith_github_repo' name='agent_smith_github_repo' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter the raw GitHub URL (e.g., <code>https://raw.githubusercontent.com/your-org/updates-repo/main</code>).</p>";
    }

    /**
     * Output the GitHub Token field.
     */
    public function settings_field_token_callback() {
        $value = esc_attr( get_option( 'agent_smith_github_token', '' ) );
        echo "<input type='password' id='agent_smith_github_token' name='agent_smith_github_token' value='{$value}' class='regular-text' />";
        echo "<p class='description'>Enter your GitHub Personal Access Token with read access to the repository.</p>";
    }

    /**
     * Make an authenticated request to GitHub for the specified JSON file.
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

new AgentSmith();
