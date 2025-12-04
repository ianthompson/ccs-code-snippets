<?php
/**
 * Plugin Name: Code Snippets
 * Description: Create, edit, and assign PHP, CSS, and HTML snippets. Includes Safe Mode, Import/Export, GitHub Updater, Shortcodes, and Duplication.
 * Version: 0.1.3
 * Author: Custom AI
 * Text Domain: ccs-snippets
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package CCS_Code_Snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------------
// CONFIGURATIONS
// -------------------------------------------------------------------------
// GitHub auto-updater settings
// For public repos, leave CCS_ACCESS_TOKEN empty
// For private repos, add your GitHub personal access token
if ( ! defined( 'CCS_GITHUB_USER' ) ) {
    define( 'CCS_GITHUB_USER', 'ianthompson' );
}
if ( ! defined( 'CCS_GITHUB_REPO' ) ) {
    define( 'CCS_GITHUB_REPO', 'ccs-code-snippets' );
}
if ( ! defined( 'CCS_ACCESS_TOKEN' ) ) {
    define( 'CCS_ACCESS_TOKEN', '' );
}
// -------------------------------------------------------------------------

/**
 * Main Plugin Class
 *
 * Initializes all plugin components and handles autoloading.
 *
 * @since 0.0.1
 * @since 0.1.1 Refactored to use modular architecture
 */
class CCS_Code_Snippets {

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '0.1.3';

    /**
     * Plugin instance.
     *
     * @var CCS_Code_Snippets
     */
    private static $instance = null;

    /**
     * Component instances.
     *
     * @var array
     */
    private $components = [];

    /**
     * Get plugin instance.
     *
     * @return CCS_Code_Snippets
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since 0.0.1
     * @since 0.1.1 Refactored to use component-based architecture
     */
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * Define plugin constants.
     *
     * @since 0.1.1
     */
    private function define_constants() {
        if ( ! defined( 'CCS_PLUGIN_FILE' ) ) {
            define( 'CCS_PLUGIN_FILE', __FILE__ );
        }
        if ( ! defined( 'CCS_PLUGIN_DIR' ) ) {
            define( 'CCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        }
        if ( ! defined( 'CCS_PLUGIN_URL' ) ) {
            define( 'CCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        }
    }

    /**
     * Load required files.
     *
     * @since 0.1.1
     */
    private function load_dependencies() {
        // Core components
        require_once CCS_PLUGIN_DIR . 'includes/core/class-post-types.php';
        require_once CCS_PLUGIN_DIR . 'includes/core/class-snippet-executor.php';
        require_once CCS_PLUGIN_DIR . 'includes/core/class-github-updater.php';

        // Admin components (only load in admin)
        if ( is_admin() ) {
            require_once CCS_PLUGIN_DIR . 'includes/admin/class-settings.php';
            require_once CCS_PLUGIN_DIR . 'includes/admin/class-admin-ui.php';
            require_once CCS_PLUGIN_DIR . 'includes/admin/class-tools-page.php';
        }
    }

    /**
     * Initialize plugin components.
     *
     * @since 0.1.1
     */
    private function init_components() {
        // Core components (always loaded)
        $this->components['post_types'] = new CCS_Post_Types();
        $this->components['executor']   = new CCS_Snippet_Executor();

        // GitHub updater
        $this->components['updater'] = new CCS_GitHub_Updater(
            CCS_PLUGIN_FILE,
            CCS_GITHUB_USER,
            CCS_GITHUB_REPO,
            CCS_ACCESS_TOKEN
        );

        // Admin components (only in admin area)
        if ( is_admin() ) {
            $this->components['settings']   = new CCS_Settings();
            $this->components['admin_ui']   = new CCS_Admin_UI();
            $this->components['tools_page'] = new CCS_Tools_Page();
        }
    }

    /**
     * Get component instance.
     *
     * @since 0.1.1
     * @param string $component Component name
     * @return object|null Component instance or null if not found
     */
    public function get_component( $component ) {
        return isset( $this->components[ $component ] ) ? $this->components[ $component ] : null;
    }

    /**
     * Get plugin version.
     *
     * @since 0.1.1
     * @return string Plugin version
     */
    public static function get_version() {
        return self::VERSION;
    }
}

/**
 * Initialize the plugin.
 *
 * @since 0.1.1
 * @return CCS_Code_Snippets
 */
function ccs_code_snippets() {
    return CCS_Code_Snippets::get_instance();
}

// Initialize plugin
ccs_code_snippets();
