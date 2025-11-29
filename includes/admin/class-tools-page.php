<?php
/**
 * Tools Page Manager
 *
 * Handles the import/export tools page.
 *
 * @package CCS_Code_Snippets
 * @since 0.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_Tools_Page
 *
 * Manages import/export functionality and tools page.
 */
class CCS_Tools_Page {

    /**
     * Initialize tools page.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
        add_action( 'admin_init', [ $this, 'handle_export_import' ] );
    }

    /**
     * Register tools submenu page.
     *
     * @since 0.0.8
     */
    public function register_tools_page() {
        add_submenu_page(
            'edit.php?post_type=ccs_snippet',
            __( 'Code Snippets Tools', 'ccs-snippets' ),
            __( 'Tools', 'ccs-snippets' ),
            'manage_options',
            'ccs_tools',
            [ $this, 'render_tools_page' ]
        );
    }

    /**
     * Render tools page.
     *
     * @since 0.0.8
     * @since 0.1.1 Improved security and layout
     */
    public function render_tools_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ccs-snippets' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Code Snippets Tools', 'ccs-snippets' ); ?></h1>

            <!-- GitHub Settings Section -->
            <div style="margin-top:20px;">
                <?php CCS_Settings::render_github_settings(); ?>
            </div>

            <h2 style="margin-top: 40px;"><?php esc_html_e( 'Import & Export', 'ccs-snippets' ); ?></h2>

            <div style="display:flex; gap: 20px; margin-top:20px; flex-wrap: wrap;">
                <!-- Export Section -->
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px; max-width:400px;">
                    <h2><?php esc_html_e( 'Export Snippets', 'ccs-snippets' ); ?></h2>
                    <p><?php esc_html_e( 'Download all snippets as a JSON file for backup or migration.', 'ccs-snippets' ); ?></p>
                    <form method="post">
                        <?php wp_nonce_field( 'ccs_export', 'ccs_export_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="export_snippets">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Download Export', 'ccs-snippets' ); ?>
                        </button>
                    </form>
                </div>

                <!-- Import Section -->
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px; max-width:400px;">
                    <h2><?php esc_html_e( 'Import Snippets', 'ccs-snippets' ); ?></h2>
                    <p><?php esc_html_e( 'Upload a JSON export file to import snippets.', 'ccs-snippets' ); ?></p>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ccs_import', 'ccs_import_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="import_snippets">
                        <input
                            type="file"
                            name="import_file"
                            accept=".json"
                            required
                            style="margin-bottom:10px; display:block;"
                        >
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Import', 'ccs-snippets' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Safe Mode Info -->
            <div style="margin-top:30px; padding:15px; background:#fff3cd; border:1px solid #ffeeba; max-width:820px;">
                <h3>🆘 <?php esc_html_e( 'Safe Mode', 'ccs-snippets' ); ?></h3>
                <p>
                    <?php esc_html_e( 'If a snippet crashes your site, add this to your URL to disable all snippets:', 'ccs-snippets' ); ?>
                </p>
                <code><?php echo esc_url( site_url( '/wp-admin/?ccs_safe_mode=1' ) ); ?></code>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export and import requests.
     *
     * @since 0.0.8
     * @since 0.1.1 Added comprehensive validation and error handling
     */
    public function handle_export_import() {
        if ( ! isset( $_POST['ccs_action'] ) ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( $_POST['ccs_action'] );

        if ( 'export_snippets' === $action ) {
            $this->handle_export();
        } elseif ( 'import_snippets' === $action ) {
            $this->handle_import();
        }
    }

    /**
     * Handle snippet export.
     *
     * @since 0.1.1
     */
    private function handle_export() {
        // Verify nonce
        if ( ! check_admin_referer( 'ccs_export', 'ccs_export_nonce' ) ) {
            return;
        }

        // Get all snippets
        $snippets = get_posts( [
            'post_type'      => 'ccs_snippet',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ] );

        $export_data = [];

        foreach ( $snippets as $post ) {
            $tags = wp_get_post_terms( $post->ID, 'ccs_tags', [ 'fields' => 'names' ] );

            if ( is_wp_error( $tags ) ) {
                $tags = [];
            }

            $export_data[] = [
                'title'       => $post->post_title,
                'code'        => get_post_meta( $post->ID, '_ccs_code', true ),
                'type'        => get_post_meta( $post->ID, '_ccs_type', true ),
                'hook'        => get_post_meta( $post->ID, '_ccs_hook', true ),
                'priority'    => get_post_meta( $post->ID, '_ccs_priority', true ),
                'active'      => get_post_meta( $post->ID, '_ccs_active', true ),
                'description' => get_post_meta( $post->ID, '_ccs_description', true ),
                'tags'        => $tags,
            ];
        }

        // Set headers for download
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="snippets-export-' . gmdate( 'Y-m-d' ) . '.json"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /**
     * Handle snippet import.
     *
     * @since 0.1.1
     */
    private function handle_import() {
        // Verify nonce
        if ( ! check_admin_referer( 'ccs_import', 'ccs_import_nonce' ) ) {
            return;
        }

        // Validate file upload
        if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__( 'No file uploaded.', 'ccs-snippets' ) . '</p></div>';
            } );
            return;
        }

        // Verify file type
        $file_info = wp_check_filetype_and_ext(
            $_FILES['import_file']['tmp_name'],
            $_FILES['import_file']['name']
        );

        if ( 'json' !== $file_info['ext'] ) {
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__( 'Invalid file type. Please upload a JSON file.', 'ccs-snippets' ) . '</p></div>';
            } );
            return;
        }

        // Read and decode JSON
        $json_content = file_get_contents( $_FILES['import_file']['tmp_name'] );

        if ( false === $json_content ) {
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__( 'Failed to read file.', 'ccs-snippets' ) . '</p></div>';
            } );
            return;
        }

        $data = json_decode( $json_content, true );

        // Validate JSON structure
        if ( null === $data || ! is_array( $data ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__( 'Invalid JSON format.', 'ccs-snippets' ) . '</p></div>';
            } );
            return;
        }

        // Import snippets
        $imported = 0;
        $errors = 0;

        foreach ( $data as $item ) {
            // Validate required fields
            if ( ! isset( $item['title'] ) || ! isset( $item['code'] ) || ! isset( $item['type'] ) ) {
                $errors++;
                continue;
            }

            // Create snippet post
            $post_id = wp_insert_post( [
                'post_title'  => sanitize_text_field( $item['title'] ),
                'post_type'   => 'ccs_snippet',
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $post_id ) || ! $post_id ) {
                $errors++;
                continue;
            }

            // Save meta data
            update_post_meta( $post_id, '_ccs_code', wp_unslash( $item['code'] ) );

            // Validate and save type
            $allowed_types = [ 'html', 'css', 'php' ];
            $type = isset( $item['type'] ) ? sanitize_text_field( $item['type'] ) : 'html';
            if ( in_array( $type, $allowed_types, true ) ) {
                update_post_meta( $post_id, '_ccs_type', $type );
            }

            // Save other fields
            if ( isset( $item['hook'] ) ) {
                update_post_meta( $post_id, '_ccs_hook', sanitize_text_field( $item['hook'] ) );
            }

            if ( isset( $item['priority'] ) ) {
                update_post_meta( $post_id, '_ccs_priority', intval( $item['priority'] ) );
            }

            if ( isset( $item['active'] ) ) {
                update_post_meta( $post_id, '_ccs_active', intval( $item['active'] ) );
            }

            if ( isset( $item['description'] ) ) {
                update_post_meta( $post_id, '_ccs_description', sanitize_textarea_field( $item['description'] ) );
            }

            // Import tags
            if ( isset( $item['tags'] ) && is_array( $item['tags'] ) && ! empty( $item['tags'] ) ) {
                $sanitized_tags = array_map( 'sanitize_text_field', $item['tags'] );
                wp_set_object_terms( $post_id, $sanitized_tags, 'ccs_tags' );
            }

            $imported++;
        }

        // Clear cache
        CCS_Snippet_Executor::clear_cache();

        // Show success message
        $imported_count = $imported;
        $error_count = $errors;

        add_action( 'admin_notices', function() use ( $imported_count, $error_count ) {
            if ( $imported_count > 0 ) {
                printf(
                    '<div class="updated"><p>%s</p></div>',
                    sprintf(
                        esc_html(
                            _n(
                                '%d snippet imported successfully.',
                                '%d snippets imported successfully.',
                                $imported_count,
                                'ccs-snippets'
                            )
                        ),
                        $imported_count
                    )
                );
            }

            if ( $error_count > 0 ) {
                printf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        esc_html(
                            _n(
                                '%d snippet failed to import.',
                                '%d snippets failed to import.',
                                $error_count,
                                'ccs-snippets'
                            )
                        ),
                        $error_count
                    )
                );
            }
        } );
    }
}
