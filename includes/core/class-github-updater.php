<?php
/**
 * GitHub Auto-Updater
 *
 * Handles automatic updates from GitHub releases.
 *
 * @package CCS_Code_Snippets
 * @since 0.0.9
 * @since 0.1.1 Refactored with improved documentation and error handling
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_GitHub_Updater
 *
 * Provides automatic update functionality from GitHub releases.
 */
class CCS_GitHub_Updater {

    /**
     * Plugin file path.
     *
     * @var string
     */
    private $file;

    /**
     * GitHub username.
     *
     * @var string
     */
    private $user;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $repo;

    /**
     * GitHub access token (optional, for private repos).
     *
     * @var string
     */
    private $token;

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $slug;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $basename;

    /**
     * Cache duration in seconds.
     *
     * @var int
     */
    const CACHE_DURATION = 43200; // 12 hours

    /**
     * Initialize updater.
     *
     * @since 0.0.9
     * @since 0.1.3 Added support for database settings
     * @param string $file  Plugin file path
     * @param string $user  GitHub username (fallback)
     * @param string $repo  GitHub repository name (fallback)
     * @param string $token Optional. GitHub access token (fallback)
     */
    public function __construct( $file, $user, $repo, $token = '' ) {
        $this->file     = $file;
        $this->slug     = 'ccs-code-snippets';
        $this->basename = plugin_basename( $file );

        // Use settings from database, fallback to constants
        $settings = class_exists( 'CCS_Settings' ) ? CCS_Settings::get_settings() : [];
        $this->user  = ! empty( $settings['github_user'] ) ? $settings['github_user'] : $user;
        $this->repo  = ! empty( $settings['github_repo'] ) ? $settings['github_repo'] : $repo;
        $this->token = ! empty( $settings['github_token'] ) ? $settings['github_token'] : $token;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
    }

    /**
     * Check for plugin updates.
     *
     * @since 0.0.9
     * @since 0.1.1 Improved version comparison and error handling
     * @param object $transient Update transient object
     * @return object Modified transient
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->get_repository_info();

        if ( ! $remote || ! isset( $remote->tag_name ) ) {
            return $transient;
        }

        // Get local version
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $this->file );
        $local_version = $plugin_data['Version'];

        // Clean version numbers (remove 'v' prefix)
        $remote_version = preg_replace( '/[^0-9.]/', '', $remote->tag_name );

        // Compare versions
        if ( version_compare( $local_version, $remote_version, '<' ) ) {
            $response = new stdClass();
            $response->slug        = $this->slug;
            $response->plugin      = $this->basename;
            $response->new_version = $remote->tag_name;
            $response->package     = $remote->zipball_url;
            $response->url         = $remote->html_url;

            $transient->response[ $this->basename ] = $response;
        }

        return $transient;
    }

    /**
     * Provide plugin information for update screen.
     *
     * @since 0.0.9
     * @since 0.1.1 Improved data structure
     * @param false|object|array $result The result object or array
     * @param string             $action The type of information being requested
     * @param object             $args   Plugin API arguments
     * @return false|object Modified result
     */
    public function check_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $remote = $this->get_repository_info();

        if ( ! $remote ) {
            return $result;
        }

        $response = new stdClass();
        $response->name           = 'Code Snippets';
        $response->slug           = $this->slug;
        $response->version        = $remote->tag_name ?? '0.0.0';
        $response->download_link  = $remote->zipball_url ?? '';
        $response->sections       = [
            'description' => 'Automatic updates from GitHub',
            'changelog'   => $remote->body ?? 'No changelog available.',
        ];

        return $response;
    }

    /**
     * Get repository release information from GitHub API.
     *
     * @since 0.0.9
     * @since 0.1.1 Improved caching and error handling
     * @return false|object Release information or false on failure
     */
    private function get_repository_info() {
        // Check cache first
        $cache_key = 'ccs_gh_release_' . $this->repo;
        $cached = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Prepare API request
        $args = [
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ],
            'timeout' => 15,
        ];

        // Add authorization if token is provided
        if ( ! empty( $this->token ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->token;
        }

        // Make API request
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->user,
            $this->repo
        );

        $request = wp_remote_get( $url, $args );

        // Check for errors
        if ( is_wp_error( $request ) ) {
            error_log( 'CCS GitHub Updater Error: ' . $request->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $request );

        if ( 200 !== $response_code ) {
            error_log( sprintf( 'CCS GitHub Updater: API returned status code %d', $response_code ) );
            return false;
        }

        // Parse response
        $body = wp_remote_retrieve_body( $request );
        $data = json_decode( $body );

        if ( null === $data ) {
            error_log( 'CCS GitHub Updater: Failed to parse JSON response' );
            return false;
        }

        // Cache the result
        set_transient( $cache_key, $data, self::CACHE_DURATION );

        return $data;
    }

    /**
     * Fix folder name after download.
     *
     * GitHub downloads create folders with random suffixes.
     * This renames them to match the expected plugin slug.
     *
     * @since 0.0.13
     * @since 0.1.1 Improved documentation and error handling
     * @param string      $source        File source location
     * @param string      $remote_source Remote file source location
     * @param WP_Upgrader $upgrader      WP_Upgrader instance
     * @param array       $hook_extra    Extra arguments passed to hooked filters
     * @return string Modified source location
     */
    public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
        global $wp_filesystem;

        // Only process our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $source;
        }

        $target_file = 'ccs-code-snippets.php';
        $found_source = $source;

        // Check if the main plugin file is in the source directory
        if ( ! $wp_filesystem->exists( trailingslashit( $source ) . $target_file ) ) {
            // Look for the file in subdirectories
            $files = $wp_filesystem->dirlist( $source );

            if ( is_array( $files ) ) {
                foreach ( $files as $file ) {
                    if ( 'd' === $file['type'] ) {
                        $subdir = trailingslashit( $source ) . $file['name'];

                        if ( $wp_filesystem->exists( trailingslashit( $subdir ) . $target_file ) ) {
                            $found_source = $subdir;
                            break;
                        }
                    }
                }
            }
        }

        // Prepare destination path
        $destination_path = trailingslashit( $remote_source ) . $this->slug;

        // Remove existing folder if it exists
        if ( $wp_filesystem->exists( $destination_path ) ) {
            $wp_filesystem->delete( $destination_path, true );
        }

        // Move to correct location
        if ( $wp_filesystem->move( $found_source, $destination_path ) ) {
            return trailingslashit( $destination_path );
        }

        return trailingslashit( $found_source );
    }

    /**
     * Clear update cache.
     *
     * Useful for forcing an immediate update check.
     *
     * @since 0.1.1
     */
    public static function clear_cache() {
        delete_transient( 'ccs_gh_release_' . 'ccs-code-snippets' );
    }
}
