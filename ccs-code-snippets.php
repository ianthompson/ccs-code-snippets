<?php
/**
 * Plugin Name: Code Snippets
 * Description: Create, edit, and assign PHP, CSS, and HTML snippets. Includes Safe Mode, Import/Export, and GitHub Auto-Updates.
 * Version: 0.0.9
 * Author: Custom AI
 * Text Domain: ccs-snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------------
// CONFIGURATION: EDIT THIS TO MATCH YOUR GITHUB REPO
// -------------------------------------------------------------------------
define( 'CCS_GITHUB_USER', 'ianthompson' ); // e.g. 'johndoe'
define( 'CCS_GITHUB_REPO', 'css-code-snippets' );      // e.g. 'ccs-code-snippets'
define( 'CCS_ACCESS_TOKEN', '' );                   // Leave empty for Public repos. Fill for Private.
// -------------------------------------------------------------------------

class CCS_Code_Snippets_009 {

    public function __construct() {
        // 1. Init
        add_action( 'init', [ $this, 'register_content_types' ] );
        
        // 2. Admin UI
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_snippet_data' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Admin Columns
        add_filter( 'manage_ccs_snippet_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_ccs_snippet_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        
        // Toggle Switch AJAX
        add_action( 'wp_ajax_ccs_toggle_status', [ $this, 'ajax_toggle_status' ] );

        // 3. New Tools Menu (Import/Export)
        add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
        add_action( 'admin_init', [ $this, 'handle_export_import' ] );

        // 4. Frontend Execution
        add_action( 'init', [ $this, 'execute_snippets' ], 99 );

        // 5. Initialize Updater
        new CCS_GitHub_Updater( __FILE__, CCS_GITHUB_USER, CCS_GITHUB_REPO, CCS_ACCESS_TOKEN );
    }

    // ... [EXISTING FUNCTIONS FROM v0.0.8 BELOW] ...

    private function is_safe_mode() {
        if ( isset( $_GET['ccs_safe_mode'] ) && '1' === $_GET['ccs_safe_mode'] ) return true;
        return false;
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
                    <p>Download a JSON file containing all your snippets.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'ccs_export', 'ccs_export_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="export_snippets">
                        <button type="submit" class="button button-primary">Download Export File</button>
                    </form>
                </div>
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; max-width:400px;">
                    <h2>Import Snippets</h2>
                    <p>Upload a previously exported JSON file.</p>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ccs_import', 'ccs_import_nonce' ); ?>
                        <input type="hidden" name="ccs_action" value="import_snippets">
                        <input type="file" name="import_file" accept=".json" required style="margin-bottom:10px; display:block;">
                        <button type="submit" class="button button-secondary">Import Snippets</button>
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
                $export_data[] = [
                    'title' => $post->post_title,
                    'code' => get_post_meta( $post->ID, '_ccs_code', true ),
                    'type' => get_post_meta( $post->ID, '_ccs_type', true ),
                    'hook' => get_post_meta( $post->ID, '_ccs_hook', true ),
                    'priority' => get_post_meta( $post->ID, '_ccs_priority', true ),
                    'active' => get_post_meta( $post->ID, '_ccs_active', true ),
                    'description' => get_post_meta( $post->ID, '_ccs_description', true ),
                    'tags' => $tags
                ];
            }
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: application/json' );
            header( 'Content-Disposition: attachment; filename="snippets-export-' . date( 'Y-m-d' ) . '.json"' );
            echo json_encode( $export_data, JSON_PRETTY_PRINT );
            exit;
        }

        if ( 'import_snippets' === $_POST['ccs_action'] && check_admin_referer( 'ccs_import', 'ccs_import_nonce' ) ) {
            if ( empty( $_FILES['import_file']['tmp_name'] ) ) return;
            $json = file_get_contents( $_FILES['import_file']['tmp_name'] );
            $data = json_decode( $json, true );
            if ( is_array( $data ) ) {
                foreach ( $data as $item ) {
                    $post_id = wp_insert_post([ 'post_title' => $item['title'], 'post_type' => 'ccs_snippet', 'post_status' => 'publish' ]);
                    if ( $post_id ) {
                        update_post_meta( $post_id, '_ccs_code', $item['code'] );
                        update_post_meta( $post_id, '_ccs_type', $item['type'] );
                        update_post_meta( $post_id, '_ccs_hook', $item['hook'] );
                        update_post_meta( $post_id, '_ccs_priority', $item['priority'] );
                        update_post_meta( $post_id, '_ccs_active', $item['active'] );
                        update_post_meta( $post_id, '_ccs_description', $item['description'] );
                        if ( ! empty( $item['tags'] ) ) wp_set_object_terms( $post_id, $item['tags'], 'ccs_tags' );
                    }
                }
                add_action('admin_notices', function() { echo '<div class="updated"><p>Snippets imported successfully!</p></div>'; });
            }
        }
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

    public function add_custom_meta_boxes() {
        add_meta_box( 'ccs_code_editor', 'Snippet Code', [ $this, 'render_code_editor' ], 'ccs_snippet', 'normal', 'high' );
        add_meta_box( 'ccs_snippet_settings', 'Configuration', [ $this, 'render_settings_box' ], 'ccs_snippet', 'side', 'default' );
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
        $desc = get_post_meta( $post->ID, '_ccs_description', true );
        $active = get_post_meta( $post->ID, '_ccs_active', true );
        if ( $active === '' ) $active = 1;

        echo '<div class="ccs-switch-wrapper" style="margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #ddd;">
                <label class="ccs-switch"><input type="checkbox" name="ccs_active" class="ccs-toggle-cb" value="1" data-id="' . $post->ID . '" ' . checked( $active, 1, false ) . '><span class="ccs-slider"></span></label>
                <span class="ccs-status-label ' . ($active ? 'active' : '') . '">' . ($active ? 'Active' : 'Inactive') . '</span>
              </div>';
        
        echo '<p><label><strong>Type:</strong></label><select name="ccs_type" style="width:100%">';
        foreach(['html'=>'HTML','css'=>'CSS','php'=>'PHP'] as $k=>$v) echo "<option value='$k' " . selected($type, $k, false) . ">$v</option>";
        echo '</select></p>';

        $hooks = ['wp_head'=>'Header','wp_footer'=>'Footer','wp_body_open'=>'Body Open','the_content'=>'Content','init'=>'Init','wp_enqueue_scripts'=>'Enqueue'];
        echo '<p><label><strong>Target Hook:</strong></label><select id="ccs_hook_select" style="width:100%; margin-bottom: 5px;"><option value="">-- Select Common --</option>';
        foreach($hooks as $k=>$v) echo "<option value='$k' " . selected($hook, $k, false) . ">$v</option>";
        echo '</select><input type="text" name="ccs_hook" id="ccs_hook_input" value="' . esc_attr($hook) . '" style="width:100%"></p>';

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

    public function set_custom_columns( $c ) {
        return [ 'cb' => $c['cb'], 'ccs_active' => 'Status', 'title' => $c['title'], 'ccs_type' => 'Type', 'ccs_hook' => 'Hook', 'ccs_priority' => 'Prio', 'taxonomy-ccs_tags' => 'Tags', 'date' => $c['date'] ];
    }

    public function render_custom_columns( $col, $id ) {
        if ( 'ccs_active' === $col ) {
            $a = get_post_meta( $id, '_ccs_active', true ); if($a==='') $a=1;
            echo '<div class="ccs-switch-wrapper"><label class="ccs-switch"><input type="checkbox" class="ccs-toggle-cb" data-id="'.$id.'" ' . checked($a,1,false) . '><span class="ccs-slider"></span></label></div>';
        }
        if ( 'ccs_type' === $col ) { $t = get_post_meta($id,'_ccs_type',true); echo "<span style='font-weight:bold; color:".($t=='php'?'#7e57c2':($t=='css'?'#29b6f6':'#ef5350'))."'>".strtoupper($t)."</span>"; }
        if ( 'ccs_hook' === $col ) echo '<code>'.esc_html(get_post_meta($id,'_ccs_hook',true)).'</code>';
        if ( 'ccs_priority' === $col ) echo get_post_meta($id,'_ccs_priority',true);
    }

    public function execute_snippets() {
        if ( $this->is_safe_mode() ) {
            if ( current_user_can( 'manage_options' ) ) add_action( 'wp_footer', function() { echo '<div style="position:fixed;bottom:10px;right:10px;background:red;color:white;padding:10px;">Safe Mode: Snippets Disabled</div>'; } );
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
                        if ( current_user_can( 'manage_options' ) ) echo '<strong>Snippet Error:</strong> ' . esc_html( $e->getMessage() );
                    }
                }
            }, $prio );
        }
    }
}

/**
 * ----------------------------------------------------------------------
 * CLASS: GitHub Auto-Updater
 * ----------------------------------------------------------------------
 */
class CCS_GitHub_Updater {
    private $file;
    private $user;
    private $repo;
    private $token;
    private $slug;
    private $basename;

    public function __construct( $file, $user, $repo, $token = '' ) {
        $this->file = $file;
        $this->user = $user;
        $this->repo = $repo;
        $this->token = $token;
        $this->slug = 'ccs-code-snippets'; // Must match folder name
        $this->basename = plugin_basename( $file );

        // Hooks
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $remote = $this->get_repository_info();
        if ( ! $remote ) return $transient;

        $current_version = get_plugin_data( $this->file )['Version'];

        // Compare Versions
        if ( version_compare( $current_version, $remote->tag_name, '<' ) ) {
            $res = new stdClass();
            $res->slug = $this->slug;
            $res->plugin = $this->basename;
            $res->new_version = $remote->tag_name;
            $res->package = $remote->zipball_url;
            $res->url = $remote->html_url;
            $transient->response[ $this->basename ] = $res;
        }

        return $transient;
    }

    public function check_info( $false, $action, $arg ) {
        if ( 'plugin_information' !== $action || $arg->slug !== $this->slug ) return $false;

        $remote = $this->get_repository_info();
        if ( ! $remote ) return $false;

        $res = new stdClass();
        $res->name = 'Code Snippets';
        $res->slug = $this->slug;
        $res->version = $remote->tag_name;
        $res->author = 'Custom AI';
        $res->homepage = $remote->html_url;
        $res->sections = [ 'description' => 'Github Update', 'changelog' => $remote->body ];
        $res->download_link = $remote->zipball_url;

        return $res;
    }

    private function get_repository_info() {
        // Simple Caching
        $cache_key = 'ccs_gh_release_' . $this->repo;
        $cached = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $url = "https://api.github.com/repos/{$this->user}/{$this->repo}/releases/latest";
        $args = [ 'headers' => [ 'User-Agent' => 'WordPress' ] ];
        if ( $this->token ) $args['headers']['Authorization'] = "token {$this->token}";

        $request = wp_remote_get( $url, $args );
        if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) return false;

        $body = json_decode( wp_remote_retrieve_body( $request ) );
        set_transient( $cache_key, $body, HOUR_IN_SECONDS * 12 );

        return $body;
    }

    // Fixes the folder name issue (GitHub zips are named repo-version)
    public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
        global $wp_filesystem;
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) return $source;

        $correct_source = trailingslashit( $remote_source ) . $this->slug;
        $wp_filesystem->move( $source, $correct_source );
        return $correct_source;
    }
}

new CCS_Code_Snippets_009();
