<?php
/**
 * Plugin Name: Code Snippets
 * Description: Create, edit, and assign PHP, CSS, and HTML snippets. Includes Safe Mode, Import/Export, GitHub Updater, Shortcodes, and Duplication.
 * Version: 0.0.16
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

class CCS_Code_Snippets_016 {

    public function __construct() {
        // Init (Post Types must still be registered at init)
        add_action( 'init', [ $this, 'register_content_types' ] );
        
        // Admin UI
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_snippet_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Columns
        add_filter( 'manage_ccs_snippet_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_ccs_snippet_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        
        // Row Actions
        add_filter( 'post_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
        add_action( 'admin_action_ccs_duplicate', [ $this, 'handle_duplication' ] );

        // AJAX
        add_action( 'wp_ajax_ccs_toggle_status', [ $this, 'ajax_toggle_status' ] );

        // Tools
        add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
        add_action( 'admin_init', [ $this, 'handle_export_import' ] );

        // EXECUTION ENGINE - Moved to 'plugins_loaded' (The Earliest Hook)
        // This ensures snippets with Priority 1-99 on 'init' will work correctly.
        add_action( 'plugins_loaded', [ $this, 'execute_snippets' ], 1 );
        
        // Shortcode
        add_shortcode( 'ccs_snippet', [ $this, 'render_shortcode' ] );

        // Updater
        new CCS_GitHub_Updater( __FILE__, CCS_GITHUB_USER, CCS_GITHUB_REPO, CCS_ACCESS_TOKEN );
    }

    // --- EXECUTION ENGINE (UPDATED) ---
    public function execute_snippets() {
        // Safe Mode Check
        if ( isset( $_GET['ccs_safe_mode'] ) && '1' === $_GET['ccs_safe_mode'] ) {
            if ( current_user_can( 'manage_options' ) ) add_action( 'wp_footer', function() { echo '<div style="position:fixed;bottom:10px;right:10px;background:red;color:white;padding:10px;">Safe Mode</div>'; } );
            return;
        }

        // We use direct DB query because 'get_posts' relies on CPTs which aren't registered at 'plugins_loaded' yet.
        global $wpdb;
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ccs_snippet' AND post_status = 'publish'";
        $snippet_ids = $wpdb->get_col( $sql );

        if ( ! $snippet_ids ) return;

        foreach ( $snippet_ids as $id ) {
            // Check Active Status
            $active = get_post_meta( $id, '_ccs_active', true );
            if ( $active === '' ) $active = 1;
            if ( ! $active ) continue;

            $type = get_post_meta( $id, '_ccs_type', true );
            $hook = get_post_meta( $id, '_ccs_hook', true );
            $prio = get_post_meta( $id, '_ccs_priority', true ) ?: 10;
            $code = get_post_meta( $id, '_ccs_code', true );

            if ( empty( $code ) || empty( $hook ) ) continue;

            // Logic Wrapper
            $logic = function() use ( $code, $type ) {
                if ( 'css' === $type ) echo '<style>' . $code . '</style>';
                elseif ( 'html' === $type ) echo $code;
                elseif ( 'php' === $type ) {
                    $code = $this->prepare_php_code( $code );
                    try { eval( '?>' . $code ); } catch ( \Throwable $e ) {
                        if ( current_user_can( 'manage_options' ) ) echo 'Snippet Error: ' . esc_html( $e->getMessage() );
                    }
                }
            };

            // Execute
            // If the user hook is 'plugins_loaded', run immediately to avoid loop issues
            if ( $hook === 'plugins_loaded' ) {
                 $logic();
            } else {
                 add_action( $hook, $logic, $prio );
            }
        }
    }

    private function prepare_php_code( $code ) {
        $trimmed = trim( $code );
        if ( empty( $trimmed ) ) return '';
        if ( stripos( $trimmed, '<?php' ) !== 0 && stripos( $trimmed, '<?' ) !== 0 ) {
            return "<?php\n" . $code;
        }
        return $code;
    }

    // --- SHORTCODE ---
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
            $code = $this->prepare_php_code( $code );
            try { eval( '?>' . $code ); } catch ( \Throwable $e ) {
                if ( current_user_can( 'manage_options' ) ) echo 'Error: ' . $e->getMessage();
            }
        }
        return ob_get_clean();
    }

    // --- SETUP ---
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

    // --- DUPLICATION ---
    public function add_duplicate_link( $actions, $post ) {
        if ( 'ccs_snippet' !== $post->post_type ) return $actions;
        $url = wp_nonce_url( admin_url( 'admin.php?action=ccs_duplicate&post=' . $post->ID ), 'ccs_duplicate_' . $post->ID );
        $actions['duplicate'] = '<a href="' . esc_url( $url ) . '" title="Duplicate this snippet">Duplicate</a>';
        return $actions;
    }

    public function handle_duplication() {
        if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) ) || ! isset( $_GET['action'] ) ) wp_die( 'No post to duplicate!' );
        $post_id = (int) $_GET['post'];
        check_admin_referer( 'ccs_duplicate_' . $post_id );
        $post = get_post( $post_id );
        if ( ! $post ) wp_die( 'Post creation failed: ' . $post_id );

        $new_post_id = wp_insert_post( [
            'post_title' => $post->post_title . ' (Copy)',
            'post_status' => 'draft',
            'post_type' => 'ccs_snippet',
            'post_author' => get_current_user_id()
        ] );

        if ( $new_post_id ) {
            foreach ( [ '_ccs_code', '_ccs_type', '_ccs_hook', '_ccs_priority', '_ccs_description' ] as $key ) {
                update_post_meta( $new_post_id, $key, get_post_meta( $post_id, $key, true ) );
            }
            update_post_meta( $new_post_id, '_ccs_active', 0 ); 
            wp_set_object_terms( $new_post_id, wp_get_post_terms( $post_id, 'ccs_tags', [ 'fields' => 'names' ] ), 'ccs_tags' );
            wp_redirect( admin_url( 'edit.php?post_type=ccs_snippet' ) );
            exit;
        }
    }

    // --- UI & TOOLS ---
    public function set_custom_columns( $c ) {
        return [ 'cb' => $c['cb'], 'ccs_active' => 'Status', 'title' => $c['title'], 'ccs_shortcode' => 'Shortcode', 'ccs_type' => 'Type', 'ccs_hook' => 'Hook', 'ccs_priority' => 'Prio', 'taxonomy-ccs_tags' => 'Tags' ];
    }

    public function render_custom_columns( $col, $id ) {
        if ( 'ccs_active' === $col ) {
            $a = get_post_meta( $id, '_ccs_active', true ); if($a==='') $a=1;
            echo '<div class="ccs-switch-wrapper"><label class="ccs-switch"><input type="checkbox" class="ccs-toggle-cb" data-id="'.$id.'" ' . checked($a,1,false) . '><span class="ccs-slider"></span></label></div>';
        }
        if ( 'ccs_shortcode' === $col ) echo '<code>[ccs_snippet id=' . $id . ']</code>';
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
        echo '<p class="description"><strong>PHP:</strong> Opening <code>&lt;?php</code> tags are added automatically if you skip them.</p>';
    }

    public function render_settings_box( $post ) {
        wp_nonce_field( 'ccs_save_snippet', 'ccs_nonce' );
        $type = get_post_meta( $post->ID, '_ccs_type', true ) ?: 'html';
        $hook = get_post_meta( $post->ID, '_ccs_hook', true ) ?: 'wp_head';
        $prio = get_post_meta( $post->ID, '_ccs_priority', true ) ?: 10;
        $desc = get_post_meta( $post->ID, '_ccs_description', true );
        $active = get_post_meta( $post->ID, '_ccs_active', true );
        if ( $active === '' ) $active = 1;

        echo '<div class="ccs-switch-wrapper" style="margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #ddd;">
                <label class="ccs-switch"><input type="checkbox" name="ccs_active" class="ccs-toggle-cb" value="1" data-id="' . $post->ID . '" ' . checked( $active, 1, false ) . '><span class="ccs-slider"></span></label>
                <span class="ccs-status-label ' . ($active ? 'active' : '') . '">' . ($active ? 'Active' : 'Inactive') . '</span>
              </div>';
        
        echo '<p style="background:#f0f0f1; padding:10px; font-size:12px;"><strong>Shortcode:</strong> <code>[ccs_snippet id=' . $post->ID . ']</code></p>';

        echo '<p><label><strong>Type:</strong></label><select name="ccs_type" style="width:100%">';
        foreach(['html'=>'HTML','css'=>'CSS','php'=>'PHP'] as $k=>$v) echo "<option value='$k' " . selected($type, $k, false) . ">$v</option>";
        echo '</select></p>';

        $hooks = [
            'wp_head' => 'Header (wp_head)',
            'wp_footer' => 'Footer (wp_footer)',
            'wp_body_open' => 'Body Open (wp_body_open)',
            'the_content' => 'Inside Content (the_content)',
            'init' => 'Run Everywhere (functions.php style)',
            'wp_enqueue_scripts' => 'Enqueue Scripts/Styles'
        ];

        echo '<p><label><strong>Target Hook:</strong></label><select id="ccs_hook_select" style="width:100%; margin-bottom: 5px;"><option value="">-- Select Common --</option>';
        foreach($hooks as $k=>$v) echo "<option value='$k' " . selected($hook, $k, false) . ">$v</option>";
        echo '</select><input type="text" name="ccs_hook" id="ccs_hook_input" value="' . esc_attr($hook) . '" style="width:100%" placeholder="Leave empty if using Shortcode only"></p>';

        echo '<p><label><strong>Priority:</strong></label><input type="number" name="ccs_priority" value="' . esc_attr($prio) . '" style="width:100%"></p>';
        echo '<p><label><strong>Description:</strong></label><textarea name="ccs_description" rows="4" style="width:100%; font-size:12px;">' . esc_textarea($desc) . '</textarea></p>';
        echo "<script>document.getElementById('ccs_hook_select').addEventListener('change', function(){ if(this.value) document.getElementById('ccs_hook_input').value = this.value; });</script>";
    }

    public function save_snippet_data( $post_id ) {
        if ( ! isset( $_POST['ccs_nonce'] ) || ! wp_verify_nonce( $_POST['ccs_nonce'], 'ccs_save_snippet' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( isset( $_POST['ccs_code'] ) ) update_post_meta( $post_id, '_ccs_code', $_POST['ccs_code'] );
        if ( isset( $_POST['ccs_type'] ) ) update_post_meta( $post_id, '_ccs_type', sanitize_text_field( $_POST['ccs_type'] ) );
        if ( isset( $_POST['ccs_hook'] ) ) update_post_meta( $post_id, '_ccs_hook', sanitize_text_field( $_POST['ccs_hook'] ) );
        if ( isset( $_POST['ccs_priority'] ) ) update_post_meta( $post_id, '_ccs_priority', intval( $_POST['ccs_priority'] ) );
        if ( isset( $_POST['ccs_description'] ) ) update_post_meta( $post_id, '_ccs_description', sanitize_textarea_field( $_POST['ccs_description'] ) );
        update_post_meta( $post_id, '_ccs_active', isset( $_POST['ccs_active'] ) ? 1 : 0 );
    }

    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || 'ccs_snippet' !== $screen->post_type ) return;
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            if ( function_exists( 'wp_enqueue_code_editor' ) ) {
                $settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
                if ( false !== $settings ) wp_add_inline_script( 'code-editor', sprintf( 'jQuery( function() { wp.codeEditor.initialize( "ccs_code_textarea", %s ); } );', wp_json_encode( $settings ) ) );
            }
        }
        $css = ".ccs-switch { position: relative; display: inline-block; width: 40px; height: 22px; vertical-align: middle; }
                .ccs-switch input { opacity: 0; width: 0; height: 0; }
                .ccs-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
                .ccs-slider:before { position: absolute; content: ''; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
                input:checked + .ccs-slider { background-color: #46b450; }
                input:checked + .ccs-slider:before { transform: translateX(18px); }
                .ccs-status-label { margin-left: 8px; font-weight: 600; font-size: 13px; color: #666; vertical-align: middle; }
                .ccs-status-label.active { color: #46b450; }";
        wp_add_inline_style( 'admin-bar', $css );
        $js = "jQuery(document).ready(function($) {
                $('.ccs-toggle-cb').on('change', function() {
                    var cb = $(this);
                    var status = cb.is(':checked') ? 1 : 0;
                    var label = cb.closest('.ccs-switch-wrapper').find('.ccs-status-label');
                    status ? label.text('Active').addClass('active') : label.text('Inactive').removeClass('active');
                    $.post(ajaxurl, { action: 'ccs_toggle_status', post_id: cb.data('id'), status: status, nonce: '" . wp_create_nonce('ccs_toggle_nonce') . "' });
                });
            });";
        wp_add_inline_script( 'common', $js );
    }

    public function ajax_toggle_status() {
        check_ajax_referer( 'ccs_toggle_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();
        update_post_meta( intval( $_POST['post_id'] ), '_ccs_active', intval( $_POST['status'] ) );
        wp_send_json_success();
    }

    public function register_tools_page() {
        add_submenu_page( 'edit.php?post_type=ccs_snippet', 'Tools', 'Tools', 'manage_options', 'ccs_tools', [ $this, 'render_tools_page' ] );
    }

    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1>Code Snippets Tools</h1>
            <div style="display:flex; gap: 20px; margin-top:20px;">
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; max-width:400px;">
                    <h2>Export Snippets</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'ccs_export', 'ccs_export_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="export_snippets">
                        <button type="submit" class="button button-primary">Download Export</button>
                    </form>
                </div>
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; max-width:400px;">
                    <h2>Import Snippets</h2>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ccs_import', 'ccs_import_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="import_snippets">
                        <input type="file" name="import_file" accept=".json" required style="margin-bottom:10px; display:block;">
                        <button type="submit" class="button button-secondary">Import</button>
                    </form>
                </div>
            </div>
            <div style="margin-top:30px; padding:15px; background:#fff3cd; border:1px solid #ffeeba; max-width:820px;">
                <h3>🆘 Safe Mode</h3>
                <code><?php echo site_url( '/wp-admin/?ccs_safe_mode=1' ); ?></code>
            </div>
        </div>
        <?php
    }

    public function handle_export_import() {
        if ( ! isset( $_POST['ccs_action'] ) ) return;
        if ( 'export_snippets' === $_POST['ccs_action'] && check_admin_referer( 'ccs_export', 'ccs_export_nonce' ) ) {
            $snippets = get_posts([ 'post_type' => 'ccs_snippet', 'posts_per_page' => -1 ]);
            $export_data = [];
            foreach ( $snippets as $post ) {
                $tags = wp_get_post_terms( $post->ID, 'ccs_tags', ['fields' => 'names'] );
                $export_data[] = [ 'title' => $post->post_title, 'code' => get_post_meta( $post->ID, '_ccs_code', true ), 'type' => get_post_meta( $post->ID, '_ccs_type', true ), 'hook' => get_post_meta( $post->ID, '_ccs_hook', true ), 'priority' => get_post_meta( $post->ID, '_ccs_priority', true ), 'active' => get_post_meta( $post->ID, '_ccs_active', true ), 'description' => get_post_meta( $post->ID, '_ccs_description', true ), 'tags' => $tags ];
            }
            header( 'Content-Type: application/json' );
            header( 'Content-Disposition: attachment; filename="snippets-export-' . date( 'Y-m-d' ) . '.json"' );
            echo json_encode( $export_data, JSON_PRETTY_PRINT );
            exit;
        }
        if ( 'import_snippets' === $_POST['ccs_action'] && check_admin_referer( 'ccs_import', 'ccs_import_nonce' ) ) {
            if ( empty( $_FILES['import_file']['tmp_name'] ) ) return;
            $data = json_decode( file_get_contents( $_FILES['import_file']['tmp_name'] ), true );
            if ( is_array( $data ) ) {
                foreach ( $data as $item ) {
                    $pid = wp_insert_post([ 'post_title' => $item['title'], 'post_type' => 'ccs_snippet', 'post_status' => 'publish' ]);
                    if ( $pid ) {
                        update_post_meta( $pid, '_ccs_code', $item['code'] );
                        update_post_meta( $pid, '_ccs_type', $item['type'] );
                        update_post_meta( $pid, '_ccs_hook', $item['hook'] );
                        update_post_meta( $pid, '_ccs_priority', $item['priority'] );
                        update_post_meta( $pid, '_ccs_active', $item['active'] );
                        update_post_meta( $pid, '_ccs_description', $item['description'] );
                        if ( ! empty( $item['tags'] ) ) wp_set_object_terms( $pid, $item['tags'], 'ccs_tags' );
                    }
                }
                add_action('admin_notices', function() { echo '<div class="updated"><p>Snippets imported!</p></div>'; });
            }
        }
    }
}

// Updater Class (Robust Version from v0.0.13)
class CCS_GitHub_Updater {
    private $file, $user, $repo, $token, $slug, $basename;
    
    public function __construct( $file, $user, $repo, $token = '' ) {
        $this->file = $file; $this->user = $user; $this->repo = $repo; $this->token = $token;
        $this->slug = 'ccs-code-snippets'; $this->basename = plugin_basename( $file );
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $remote = $this->get_repository_info();
        if ( $remote ) {
            $local = get_plugin_data( $this->file )['Version'];
            $remote_clean = preg_replace( '/[^0-9.]/', '', $remote->tag_name );
            if ( version_compare( $local, $remote_clean, '<' ) ) {
                $res = new stdClass(); $res->slug = $this->slug; $res->plugin = $this->basename; $res->new_version = $remote->tag_name; $res->package = $remote->zipball_url; $res->url = $remote->html_url;
                $transient->response[ $this->basename ] = $res;
            }
        }
        return $transient;
    }

    public function check_info( $false, $action, $arg ) {
        if ( 'plugin_information' !== $action || $arg->slug !== $this->slug ) return $false;
        $remote = $this->get_repository_info();
        if ( ! $remote ) return $false;
        $res = new stdClass(); $res->name = 'Code Snippets'; $res->slug = $this->slug; $res->version = $remote->tag_name; $res->download_link = $remote->zipball_url;
        $res->sections = [ 'description' => 'Github Update', 'changelog' => $remote->body ];
        return $res;
    }

    private function get_repository_info() {
        $cache_key = 'ccs_gh_release_' . $this->repo;
        if ( $cached = get_transient( $cache_key ) ) return $cached;
        $args = [ 'headers' => [ 'User-Agent' => 'WordPress' ] ];
        if ( $this->token ) $args['headers']['Authorization'] = "token {$this->token}";
        $request = wp_remote_get( "https://api.github.com/repos/{$this->user}/{$this->repo}/releases/latest", $args );
        if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) return false;
        $body = json_decode( wp_remote_retrieve_body( $request ) );
        set_transient( $cache_key, $body, 43200 );
        return $body;
    }

    public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
        global $wp_filesystem;
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) return $source;
        
        $target_file = 'ccs-code-snippets.php';
        $found_source = $source; 

        if ( ! $wp_filesystem->exists( trailingslashit( $source ) . $target_file ) ) {
            $files = $wp_filesystem->dirlist( $source );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( $file['type'] === 'd' ) {
                        $subdir = trailingslashit( $source ) . $file['name'];
                        if ( $wp_filesystem->exists( trailingslashit( $subdir ) . $target_file ) ) {
                            $found_source = $subdir;
                            break;
                        }
                    }
                }
            }
        }
        $destination_path = trailingslashit( $remote_source ) . $this->slug;
        if ( $wp_filesystem->exists( $destination_path ) ) $wp_filesystem->delete( $destination_path, true );
        if ( $wp_filesystem->move( $found_source, $destination_path ) ) return trailingslashit( $destination_path );
        return trailingslashit( $found_source );
    }
}

new CCS_Code_Snippets_016();