<?php
/**
 * Plugin Name: Code Snippets
 * Description: Create, edit, and assign PHP, CSS, and HTML snippets. Includes Safe Mode, Import/Export, GitHub Updater, Shortcodes, and Duplication.
 * Version: 0.0.11
 * Author: Custom AI
 * Text Domain: ccs-snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------------
// CONFIGURATION
// -------------------------------------------------------------------------
define( 'CCS_GITHUB_USER', 'ianthompson' ); 
define( 'CCS_GITHUB_REPO', 'ccs-code-snippets' );      
define( 'CCS_ACCESS_TOKEN', '' ); 
// -------------------------------------------------------------------------

class CCS_Code_Snippets_011 {

    public function __construct() {
        // Init
        add_action( 'init', [ $this, 'register_content_types' ] );
        
        // Admin UI
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_snippet_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Columns
        add_filter( 'manage_ccs_snippet_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_ccs_snippet_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        
        // Row Actions (Duplicate - NEW in 0.0.11)
        add_filter( 'post_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
        add_action( 'admin_action_ccs_duplicate', [ $this, 'handle_duplication' ] );

        // AJAX
        add_action( 'wp_ajax_ccs_toggle_status', [ $this, 'ajax_toggle_status' ] );

        // Tools
        add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
        add_action( 'admin_init', [ $this, 'handle_export_import' ] );

        // Execution
        add_action( 'init', [ $this, 'execute_snippets' ], 99 );
        
        // Shortcode
        add_shortcode( 'ccs_snippet', [ $this, 'render_shortcode' ] );

        // Updater
        new CCS_GitHub_Updater( __FILE__, CCS_GITHUB_USER, CCS_GITHUB_REPO, CCS_ACCESS_TOKEN );
    }

    /**
     * NEW: Add "Duplicate" link to row actions
     */
    public function add_duplicate_link( $actions, $post ) {
        if ( 'ccs_snippet' !== $post->post_type ) return $actions;
        
        $url = wp_nonce_url( 
            admin_url( 'admin.php?action=ccs_duplicate&post=' . $post->ID ), 
            'ccs_duplicate_' . $post->ID 
        );
        
        $actions['duplicate'] = '<a href="' . esc_url( $url ) . '" title="Duplicate this snippet">Duplicate</a>';
        return $actions;
    }

    /**
     * NEW: Handle Duplication Logic
     */
    public function handle_duplication() {
        if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) ) || ! isset( $_GET['action'] ) ) {
            wp_die( 'No post to duplicate has been supplied!' );
        }

        $post_id = (int) $_GET['post'];
        check_admin_referer( 'ccs_duplicate_' . $post_id );

        $post = get_post( $post_id );
        if ( ! $post ) wp_die( 'Post creation failed, could not find original post: ' . $post_id );

        // Create new post as Draft to prevent conflicts
        $new_post_args = [
            'post_title' => $post->post_title . ' (Copy)',
            'post_status' => 'draft',
            'post_type' => 'ccs_snippet',
            'post_author' => get_current_user_id()
        ];
        $new_post_id = wp_insert_post( $new_post_args );

        if ( $new_post_id ) {
            // Copy Meta
            $meta_keys = [ '_ccs_code', '_ccs_type', '_ccs_hook', '_ccs_priority', '_ccs_description' ];
            foreach ( $meta_keys as $key ) {
                $val = get_post_meta( $post_id, $key, true );
                update_post_meta( $new_post_id, $key, $val );
            }
            // Set as Inactive by default
            update_post_meta( $new_post_id, '_ccs_active', 0 );
            
            // Copy Tags
            $tags = wp_get_post_terms( $post_id, 'ccs_tags', [ 'fields' => 'names' ] );
            wp_set_object_terms( $new_post_id, $tags, 'ccs_tags' );

            wp_redirect( admin_url( 'edit.php?post_type=ccs_snippet' ) );
            exit;
        } else {
            wp_die( 'Post creation failed, could not copy original post: ' . $post_id );
        }
    }

    // --- EXISTING FUNCTIONALITY ---

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'ccs_snippet' );
        $post_id = intval( $atts['id'] );
        if ( ! $post_id || 'ccs_snippet' !== get_post_type( $post_id ) ) return '';
        
        $active = get_post_meta( $post_id, '_ccs_active', true );
        if ( $active === '' ) $active = 1;
        if ( ! $active ) return ''; 

        if ( isset( $_GET['ccs_safe_mode'] ) && '1' === $_GET['ccs_safe_mode'] ) return '';

        $code = get_post_meta( $post_id, '_ccs_code', true );
        $type = get_post_meta( $post_id, '_ccs_type', true );

        ob_start();
        if ( 'css' === $type ) echo '<style>' . $code . '</style>';
        elseif ( 'html' === $type ) echo $code;
        elseif ( 'php' === $type ) {
            try { eval( '?>' . $code ); } catch ( \Throwable $e ) {
                if ( current_user_can( 'manage_options' ) ) echo 'Error: ' . $e->getMessage();
            }
        }
        return ob_get_clean();
    }

    public function register_content_types() {
        register_post_type( 'ccs_snippet', [
            'labels' => [ 'name' => 'Snippets', 'singular_name' => 'Snippet', 'menu_name' => 'Code Snippets' ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-editor-code', 'supports' => [ 'title' ]
        ]);
        register_taxonomy( 'ccs_tags', [ 'ccs_snippet' ], [
            'hierarchical' => false, 'labels' => [ 'name' => 'Tags' ],
            'show_ui' => true, 'show_admin_column' => true
        ]);
    }

    public function execute_snippets() {
        if ( isset( $_GET['ccs_safe_mode'] ) && '1' === $_GET['ccs_safe_mode'] ) {
            if ( current_user_can( 'manage_options' ) ) add_action( 'wp_footer', function() { echo '<div style="position:fixed;bottom:10px;right:10px;background:red;color:white;padding:10px;">Safe Mode</div>'; } );
            return;
        }

        $snippets = get_posts( [ 'post_type' => 'ccs_snippet', 'post_status' => 'publish', 'posts_per_page' => -1 ] );
        foreach ( $snippets as $snippet ) {
            $active = get_post_meta( $snippet->ID, '_ccs_active', true );
            if ( $active === '' ) $active = 1;
            if ( ! $active ) continue;

            $type = get_post_meta( $snippet->ID, '_ccs_type', true );
            $hook = get_post_meta( $snippet->ID, '_ccs_hook', true );
            $prio = get_post_meta( $snippet->ID, '_ccs_priority', true ) ?: 10;
            $code = get_post_meta( $snippet->ID, '_ccs_code', true );

            if ( empty( $code ) || empty( $hook ) ) continue;

            add_action( $hook, function() use ( $code, $type ) {
                if ( 'css' === $type ) echo '<style>' . $code . '</style>';
                elseif ( 'html' === $type ) echo $code;
                elseif ( 'php' === $type ) {
                    try { eval( '?>' . $code ); } catch ( \Throwable $e ) {
                        if ( current_user_can( 'manage_options' ) ) echo 'Snippet Error: ' . esc_html( $e->getMessage() );
                    }
                }
            }, $prio );
        }
    }

    public function set_custom_columns( $c ) {
        return [ 'cb' => $c['cb'], 'ccs_active' => 'Status', 'title' => $c['title'], 'ccs_shortcode' => 'Shortcode', 'ccs_type' => 'Type', 'ccs_hook' => 'Hook', 'ccs_priority' => 'Prio', 'taxonomy-ccs_tags' => 'Tags' ];
    }

    public function render_custom_columns( $col, $id ) {
        if ( 'ccs_active' === $col ) {
            $a = get_post_meta( $id, '_ccs_active', true ); if($a==='') $a=1;
            echo '<div class="ccs-switch-wrapper"><label class="ccs-switch"><input type="checkbox" class="ccs-toggle-cb" data-id="'.$id.'" ' . checked($a,1,false) . '><span class="ccs-slider"></span></label></div>';
        }
        if ( 'ccs_shortcode' === $col ) {
            echo '<code>[ccs_snippet id=' . $id . ']</code>';
        }
        if ( 'ccs_type' === $col ) { $t = get_post_meta($id,'_ccs_type',true); echo "<span style='font-weight:bold; color:".($t=='php'?'#7e57c2':($t=='css'?'#29b6f6':'#ef5350'))."'>".strtoupper($t)."</span>"; }
        if ( 'ccs_hook' === $col ) echo '<code>'.esc_html(get_post_meta($id,'_ccs_hook',true)).'</code>';
        if ( 'ccs_priority' === $col ) echo get_post_meta($id,'_ccs_priority',true);
    }

    public function add_custom_meta_boxes() {
        add_meta_box( 'ccs_code_editor', 'Snippet Code', [ $this, 'render_code_editor' ], 'ccs_snippet', 'normal', 'high' );
        add_meta_box( 'ccs_snippet_settings', 'Configuration', [ $this, 'render_settings_box' ], 'ccs_snippet', 'side', 'default' );
    }

    public function render_code_editor( $post ) {
        $code = get_post_meta( $post->ID, '_ccs_code', true );
        echo '<textarea id="ccs_code_textarea" name="ccs_code" style="width:100%; min-height: 300px;">' . esc_textarea( $code ) . '</textarea>';
        echo '<p class="description"><strong>PHP:</strong> You can use <code>&lt;?php</code> tags or skip them.</p>';
    }

    public function render_settings_box( $post ) {
        wp_nonce_field( 'ccs_save_snippet', 'ccs_nonce' );
        $type = get_post_meta( $post->ID, '_ccs_type', true ) ?: 'html';
        $hook = get_post_meta( $post->ID, '_ccs_hook', true ) ?: 'wp_head';
        $prio = get_post_meta( $post->ID, '_ccs_priority', true ) ?: 10;
        $desc = get_pos
