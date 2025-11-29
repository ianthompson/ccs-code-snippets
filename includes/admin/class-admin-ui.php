<?php
/**
 * Admin UI Manager
 *
 * Handles all admin interface functionality including meta boxes, columns, and assets.
 *
 * @package CCS_Code_Snippets
 * @since 0.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_Admin_UI
 *
 * Manages the admin interface for code snippets.
 */
class CCS_Admin_UI {

    /**
     * Initialize admin UI.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_snippet_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Admin columns
        add_filter( 'manage_ccs_snippet_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_ccs_snippet_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );

        // Row actions
        add_filter( 'post_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
        add_action( 'admin_action_ccs_duplicate', [ $this, 'handle_duplication' ] );

        // AJAX
        add_action( 'wp_ajax_ccs_toggle_status', [ $this, 'ajax_toggle_status' ] );
    }

    /**
     * Register meta boxes.
     *
     * @since 0.0.1
     */
    public function add_meta_boxes() {
        add_meta_box(
            'ccs_code_editor',
            'Snippet Code',
            [ $this, 'render_code_editor' ],
            'ccs_snippet',
            'normal',
            'high'
        );

        add_meta_box(
            'ccs_snippet_settings',
            'Configuration',
            [ $this, 'render_settings_box' ],
            'ccs_snippet',
            'side',
            'default'
        );
    }

    /**
     * Render code editor meta box.
     *
     * @since 0.0.1
     * @since 0.1.1 Added better escaping
     * @param WP_Post $post Current post object
     */
    public function render_code_editor( $post ) {
        $code = get_post_meta( $post->ID, '_ccs_code', true );
        ?>
        <div class="ccs-editor-wrapper" style="position: relative;">
            <textarea
                id="ccs_code_textarea"
                name="ccs_code"
                style="width:100%; min-height: 300px; resize: vertical;"
            ><?php echo esc_textarea( $code ); ?></textarea>
        </div>
        <p class="description">
            <strong>PHP:</strong> Opening <code>&lt;?php</code> tags are added automatically if you skip them.
        </p>
        <style>
            /* Make CodeMirror editor resizable */
            .CodeMirror {
                min-height: 300px;
                height: auto;
                resize: vertical;
                overflow: auto;
                border: 1px solid #ddd;
            }
            .CodeMirror-scroll {
                min-height: 300px;
            }
        </style>
        <?php
    }

    /**
     * Render settings meta box.
     *
     * @since 0.0.1
     * @since 0.1.1 Added security nonce and better validation
     * @param WP_Post $post Current post object
     */
    public function render_settings_box( $post ) {
        wp_nonce_field( 'ccs_save_snippet', 'ccs_nonce' );

        $type     = get_post_meta( $post->ID, '_ccs_type', true ) ?: 'html';
        $hook     = get_post_meta( $post->ID, '_ccs_hook', true ) ?: 'wp_head';
        $priority = get_post_meta( $post->ID, '_ccs_priority', true ) ?: 10;
        $desc     = get_post_meta( $post->ID, '_ccs_description', true );
        $active   = get_post_meta( $post->ID, '_ccs_active', true );

        if ( '' === $active ) {
            $active = 1;
        }

        // Active/Inactive Toggle
        ?>
        <div class="ccs-switch-wrapper" style="margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #ddd;">
            <label class="ccs-switch">
                <input
                    type="checkbox"
                    name="ccs_active"
                    class="ccs-toggle-cb"
                    value="1"
                    data-id="<?php echo esc_attr( $post->ID ); ?>"
                    <?php checked( $active, 1 ); ?>
                >
                <span class="ccs-slider"></span>
            </label>
            <span class="ccs-status-label <?php echo $active ? 'active' : ''; ?>">
                <?php echo $active ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <!-- Shortcode Info -->
        <p style="background:#f0f0f1; padding:10px; font-size:12px;">
            <strong>Shortcode:</strong>
            <code>[ccs_snippet id=<?php echo esc_html( $post->ID ); ?>]</code>
        </p>

        <!-- Type Selection -->
        <p>
            <label><strong>Type:</strong></label>
            <select name="ccs_type" style="width:100%">
                <?php
                $types = [
                    'html' => 'HTML',
                    'css'  => 'CSS',
                    'php'  => 'PHP',
                ];
                foreach ( $types as $key => $label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $key ),
                        selected( $type, $key, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
        </p>

        <!-- Hook Selection -->
        <?php
        $common_hooks = [
            'wp_head'            => 'Header (wp_head)',
            'wp_footer'          => 'Footer (wp_footer)',
            'wp_body_open'       => 'Body Open (wp_body_open)',
            'the_content'        => 'Inside Content (the_content)',
            'init'               => 'Run Everywhere (functions.php style)',
            'wp_enqueue_scripts' => 'Enqueue Scripts/Styles',
        ];
        ?>
        <p>
            <label><strong>Target Hook:</strong></label>
            <select id="ccs_hook_select" style="width:100%; margin-bottom: 5px;">
                <option value="">-- Select Common --</option>
                <?php
                foreach ( $common_hooks as $key => $label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $key ),
                        selected( $hook, $key, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
            <input
                type="text"
                name="ccs_hook"
                id="ccs_hook_input"
                value="<?php echo esc_attr( $hook ); ?>"
                style="width:100%"
                placeholder="Leave empty if using Shortcode only"
            >
        </p>

        <!-- Priority -->
        <p>
            <label><strong>Priority:</strong></label>
            <input
                type="number"
                name="ccs_priority"
                value="<?php echo esc_attr( $priority ); ?>"
                style="width:100%"
            >
        </p>

        <!-- Description -->
        <p>
            <label><strong>Description:</strong></label>
            <textarea
                name="ccs_description"
                rows="4"
                style="width:100%; font-size:12px;"
            ><?php echo esc_textarea( $desc ); ?></textarea>
        </p>

        <script>
        document.getElementById('ccs_hook_select').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('ccs_hook_input').value = this.value;
            }
        });
        </script>
        <?php
    }

    /**
     * Save snippet meta data.
     *
     * @since 0.0.1
     * @since 0.1.1 Added comprehensive sanitization and validation
     * @param int $post_id Post ID
     */
    public function save_snippet_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['ccs_nonce'] ) || ! wp_verify_nonce( $_POST['ccs_nonce'], 'ccs_save_snippet' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Verify post type
        if ( 'ccs_snippet' !== get_post_type( $post_id ) ) {
            return;
        }

        // Only admins can save snippets (required for PHP execution safety)
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save code (stored as-is for flexibility, execution is controlled)
        if ( isset( $_POST['ccs_code'] ) ) {
            // Note: We don't sanitize code here as it needs to be stored exactly as written
            // Security is enforced at execution time with proper escaping
            update_post_meta( $post_id, '_ccs_code', wp_unslash( $_POST['ccs_code'] ) );
        }

        // Save type
        if ( isset( $_POST['ccs_type'] ) ) {
            $allowed_types = [ 'html', 'css', 'php' ];
            $type = sanitize_text_field( $_POST['ccs_type'] );
            if ( in_array( $type, $allowed_types, true ) ) {
                update_post_meta( $post_id, '_ccs_type', $type );
            }
        }

        // Save hook
        if ( isset( $_POST['ccs_hook'] ) ) {
            update_post_meta( $post_id, '_ccs_hook', sanitize_text_field( $_POST['ccs_hook'] ) );
        }

        // Save priority
        if ( isset( $_POST['ccs_priority'] ) ) {
            update_post_meta( $post_id, '_ccs_priority', intval( $_POST['ccs_priority'] ) );
        }

        // Save description
        if ( isset( $_POST['ccs_description'] ) ) {
            update_post_meta( $post_id, '_ccs_description', sanitize_textarea_field( $_POST['ccs_description'] ) );
        }

        // Save active status
        update_post_meta( $post_id, '_ccs_active', isset( $_POST['ccs_active'] ) ? 1 : 0 );

        // Clear snippet cache when saving
        CCS_Snippet_Executor::clear_cache();
    }

    /**
     * Set custom admin columns.
     *
     * @since 0.0.1
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function set_custom_columns( $columns ) {
        return [
            'cb'               => $columns['cb'],
            'ccs_active'       => 'Status',
            'title'            => $columns['title'],
            'ccs_shortcode'    => 'Shortcode',
            'ccs_type'         => 'Type',
            'ccs_hook'         => 'Hook',
            'ccs_priority'     => 'Priority',
            'taxonomy-ccs_tags' => 'Tags',
        ];
    }

    /**
     * Render custom column content.
     *
     * @since 0.0.1
     * @since 0.1.1 Added proper escaping
     * @param string $column Column name
     * @param int    $post_id Post ID
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'ccs_active':
                $active = get_post_meta( $post_id, '_ccs_active', true );
                if ( '' === $active ) {
                    $active = 1;
                }
                ?>
                <div class="ccs-switch-wrapper">
                    <label class="ccs-switch">
                        <input
                            type="checkbox"
                            class="ccs-toggle-cb"
                            data-id="<?php echo esc_attr( $post_id ); ?>"
                            <?php checked( $active, 1 ); ?>
                        >
                        <span class="ccs-slider"></span>
                    </label>
                </div>
                <?php
                break;

            case 'ccs_shortcode':
                echo '<code>[ccs_snippet id=' . esc_html( $post_id ) . ']</code>';
                break;

            case 'ccs_type':
                $type = get_post_meta( $post_id, '_ccs_type', true );
                $colors = [
                    'php'  => '#7e57c2',
                    'css'  => '#29b6f6',
                    'html' => '#ef5350',
                ];
                $color = isset( $colors[ $type ] ) ? $colors[ $type ] : '#666';
                printf(
                    '<span style="font-weight:bold; color:%s;">%s</span>',
                    esc_attr( $color ),
                    esc_html( strtoupper( $type ) )
                );
                break;

            case 'ccs_hook':
                $hook = get_post_meta( $post_id, '_ccs_hook', true );
                if ( $hook ) {
                    echo '<code>' . esc_html( $hook ) . '</code>';
                }
                break;

            case 'ccs_priority':
                echo esc_html( get_post_meta( $post_id, '_ccs_priority', true ) );
                break;
        }
    }

    /**
     * Add duplicate link to row actions.
     *
     * @since 0.0.11
     * @param array   $actions Existing actions
     * @param WP_Post $post Post object
     * @return array Modified actions
     */
    public function add_duplicate_link( $actions, $post ) {
        if ( 'ccs_snippet' !== $post->post_type ) {
            return $actions;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url( 'admin.php?action=ccs_duplicate&post=' . $post->ID ),
            'ccs_duplicate_' . $post->ID
        );

        $actions['duplicate'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url( $url ),
            esc_attr__( 'Duplicate this snippet', 'ccs-snippets' ),
            esc_html__( 'Duplicate', 'ccs-snippets' )
        );

        return $actions;
    }

    /**
     * Handle snippet duplication.
     *
     * @since 0.0.11
     * @since 0.1.1 Improved validation and error handling
     */
    public function handle_duplication() {
        // Verify we have a post ID
        if ( ! isset( $_GET['post'] ) ) {
            wp_die( esc_html__( 'No post to duplicate!', 'ccs-snippets' ) );
        }

        $post_id = absint( $_GET['post'] );

        // Verify nonce
        check_admin_referer( 'ccs_duplicate_' . $post_id );

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to duplicate snippets.', 'ccs-snippets' ) );
        }

        // Get original post
        $post = get_post( $post_id );

        if ( ! $post || 'ccs_snippet' !== $post->post_type ) {
            wp_die( esc_html__( 'Invalid snippet ID.', 'ccs-snippets' ) );
        }

        // Create duplicate
        $new_post_id = wp_insert_post( [
            'post_title'  => $post->post_title . ' (Copy)',
            'post_status' => 'draft',
            'post_type'   => 'ccs_snippet',
            'post_author' => get_current_user_id(),
        ] );

        if ( is_wp_error( $new_post_id ) ) {
            wp_die( esc_html__( 'Failed to create duplicate snippet.', 'ccs-snippets' ) );
        }

        // Copy meta data
        $meta_keys = [ '_ccs_code', '_ccs_type', '_ccs_hook', '_ccs_priority', '_ccs_description' ];
        foreach ( $meta_keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            update_post_meta( $new_post_id, $key, $value );
        }

        // Set as inactive by default
        update_post_meta( $new_post_id, '_ccs_active', 0 );

        // Copy taxonomy terms
        $terms = wp_get_post_terms( $post_id, 'ccs_tags', [ 'fields' => 'names' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            wp_set_object_terms( $new_post_id, $terms, 'ccs_tags' );
        }

        // Redirect to snippets list
        wp_safe_redirect( admin_url( 'edit.php?post_type=ccs_snippet' ) );
        exit;
    }

    /**
     * Enqueue admin assets.
     *
     * @since 0.0.1
     * @since 0.1.1 Better organization and security
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();

        if ( ! $screen || 'ccs_snippet' !== $screen->post_type ) {
            return;
        }

        // Enqueue code editor on edit screens
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            $this->enqueue_code_editor();
        }

        // Enqueue styles and scripts for all snippet admin pages
        $this->enqueue_toggle_styles();
        $this->enqueue_toggle_scripts();
    }

    /**
     * Enqueue WordPress code editor.
     *
     * @since 0.1.1
     * @since 0.1.3 Enhanced with linting, hints, and custom configuration
     */
    private function enqueue_code_editor() {
        if ( ! function_exists( 'wp_enqueue_code_editor' ) ) {
            return;
        }

        // Get default settings for PHP
        $settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );

        if ( false === $settings ) {
            return;
        }

        // Enhance CodeMirror settings
        $settings['codemirror'] = array_merge(
            $settings['codemirror'],
            [
                // Enable line wrapping
                'lineWrapping'     => true,

                // Show lint warnings/errors
                'gutters'          => [ 'CodeMirror-lint-markers' ],
                'lint'             => true,

                // Enable autocomplete
                'extraKeys'        => [
                    'Ctrl-Space' => 'autocomplete',
                    'Cmd-/'      => 'toggleComment',
                    'Ctrl-/'     => 'toggleComment',
                ],

                // Better matching
                'matchBrackets'    => true,
                'autoCloseBrackets' => true,
                'matchTags'        => true,
                'autoCloseTags'    => true,

                // Show active line
                'styleActiveLine'  => true,

                // Better scrolling
                'viewportMargin'   => 10,
            ]
        );

        wp_add_inline_script(
            'code-editor',
            sprintf(
                'jQuery( function($) {
                    var editorSettings = %s;
                    var editor = wp.codeEditor.initialize( "ccs_code_textarea", editorSettings );

                    if ( editor ) {
                        // Add help text for keyboard shortcuts
                        $("<p class=\"description\" style=\"margin-top:10px;\"><strong>Tips:</strong> Press <kbd>Ctrl+Space</kbd> for autocomplete, <kbd>Ctrl+/</kbd> to toggle comments. Editor shows warnings in the left gutter.</p>").insertAfter("#ccs_code_textarea");
                    }
                } );',
                wp_json_encode( $settings )
            )
        );
    }

    /**
     * Enqueue toggle switch styles.
     *
     * @since 0.1.1
     */
    private function enqueue_toggle_styles() {
        $css = "
            .ccs-switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 22px;
                vertical-align: middle;
            }
            .ccs-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .ccs-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 34px;
            }
            .ccs-slider:before {
                position: absolute;
                content: '';
                height: 16px;
                width: 16px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }
            input:checked + .ccs-slider {
                background-color: #46b450;
            }
            input:checked + .ccs-slider:before {
                transform: translateX(18px);
            }
            .ccs-status-label {
                margin-left: 8px;
                font-weight: 600;
                font-size: 13px;
                color: #666;
                vertical-align: middle;
            }
            .ccs-status-label.active {
                color: #46b450;
            }
        ";

        wp_add_inline_style( 'admin-bar', $css );
    }

    /**
     * Enqueue toggle switch JavaScript.
     *
     * @since 0.1.1
     */
    private function enqueue_toggle_scripts() {
        $js = sprintf(
            "jQuery(document).ready(function($) {
                $('.ccs-toggle-cb').on('change', function() {
                    var cb = $(this);
                    var status = cb.is(':checked') ? 1 : 0;
                    var label = cb.closest('.ccs-switch-wrapper').find('.ccs-status-label');

                    if (status) {
                        label.text('Active').addClass('active');
                    } else {
                        label.text('Inactive').removeClass('active');
                    }

                    $.post(ajaxurl, {
                        action: 'ccs_toggle_status',
                        post_id: cb.data('id'),
                        status: status,
                        nonce: '%s'
                    });
                });
            });",
            wp_create_nonce( 'ccs_toggle_nonce' )
        );

        wp_add_inline_script( 'common', $js );
    }

    /**
     * Handle AJAX toggle status request.
     *
     * @since 0.0.7
     * @since 0.1.1 Improved security checks
     */
    public function ajax_toggle_status() {
        // Verify nonce
        check_ajax_referer( 'ccs_toggle_nonce', 'nonce' );

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
        }

        // Validate post ID
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id || 'ccs_snippet' !== get_post_type( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Invalid snippet ID' ] );
        }

        // Update status
        $status = isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 0;
        update_post_meta( $post_id, '_ccs_active', $status );

        // Clear cache
        CCS_Snippet_Executor::clear_cache();

        wp_send_json_success( [ 'status' => $status ] );
    }
}
