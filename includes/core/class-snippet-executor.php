<?php
/**
 * Snippet Execution Engine
 *
 * Handles the execution of code snippets with security controls.
 *
 * @package CCS_Code_Snippets
 * @since 0.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_Snippet_Executor
 *
 * Executes code snippets with proper security measures and error handling.
 */
class CCS_Snippet_Executor {

    /**
     * Initialize the executor.
     */
    public function __construct() {
        // Execute snippets at the earliest safe point
        add_action( 'plugins_loaded', [ $this, 'execute_snippets' ], 1 );

        // Register shortcode
        add_shortcode( 'ccs_snippet', [ $this, 'render_shortcode' ] );
    }

    /**
     * Execute all active snippets.
     *
     * Loads and executes snippets from the database with Safe Mode support.
     *
     * @since 0.0.1
     * @since 0.1.1 Added enhanced security and error logging
     */
    public function execute_snippets() {
        // Safe Mode Check
        if ( $this->is_safe_mode_active() ) {
            if ( current_user_can( 'manage_options' ) ) {
                add_action( 'wp_footer', [ $this, 'render_safe_mode_banner' ] );
                add_action( 'admin_footer', [ $this, 'render_safe_mode_banner' ] );
            }
            return;
        }

        // Get active snippets via direct DB query (CPTs not registered yet at plugins_loaded)
        $snippets = $this->get_active_snippets();

        if ( empty( $snippets ) ) {
            return;
        }

        foreach ( $snippets as $snippet ) {
            $this->execute_single_snippet( $snippet );
        }
    }

    /**
     * Get all active snippets from the database.
     *
     * Uses direct database query since post types aren't registered at plugins_loaded.
     *
     * @since 0.1.1
     * @return array Array of snippet data
     */
    private function get_active_snippets() {
        global $wpdb;

        $cache_key = 'ccs_active_snippets';
        $snippets = wp_cache_get( $cache_key, 'ccs_snippets' );

        if ( false === $snippets ) {
            $sql = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'ccs_snippet',
                'publish'
            );
            $snippet_ids = $wpdb->get_col( $sql );

            $snippets = [];
            foreach ( $snippet_ids as $id ) {
                $active = get_post_meta( $id, '_ccs_active', true );
                if ( '' === $active ) {
                    $active = 1;
                }

                if ( ! $active ) {
                    continue;
                }

                $snippets[] = [
                    'id'       => $id,
                    'type'     => get_post_meta( $id, '_ccs_type', true ),
                    'hook'     => get_post_meta( $id, '_ccs_hook', true ),
                    'priority' => get_post_meta( $id, '_ccs_priority', true ) ?: 10,
                    'code'     => get_post_meta( $id, '_ccs_code', true ),
                    'title'    => get_the_title( $id ),
                ];
            }

            wp_cache_set( $cache_key, $snippets, 'ccs_snippets', 3600 );
        }

        return $snippets;
    }

    /**
     * Execute a single snippet.
     *
     * @since 0.1.1
     * @param array $snippet Snippet data array
     */
    private function execute_single_snippet( $snippet ) {
        if ( empty( $snippet['code'] ) || empty( $snippet['hook'] ) ) {
            return;
        }

        $logic = function() use ( $snippet ) {
            $this->render_snippet_code( $snippet );
        };

        // If the hook is 'plugins_loaded', execute immediately to avoid recursion
        if ( 'plugins_loaded' === $snippet['hook'] ) {
            $logic();
        } else {
            add_action( $snippet['hook'], $logic, (int) $snippet['priority'] );
        }
    }

    /**
     * Render snippet code based on type.
     *
     * @since 0.1.1
     * @param array $snippet Snippet data array
     */
    private function render_snippet_code( $snippet ) {
        $type = $snippet['type'];
        $code = $snippet['code'];
        $id   = $snippet['id'];

        try {
            if ( 'css' === $type ) {
                // Output CSS with automatic ID for easier identification
                echo '<style id="ccs-snippet-' . esc_attr( $id ) . '">' . $code . '</style>';
            } elseif ( 'html' === $type ) {
                // Output HTML directly - already validated at save time
                echo $code;
            } elseif ( 'php' === $type ) {
                // WARNING: eval() is inherently dangerous
                // Only admins with manage_options can create/edit snippets
                // Snippets execute for all users once activated by admin
                $code = $this->prepare_php_code( $code );

                // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Required for snippet functionality
                eval( '?>' . $code );
            }
        } catch ( \Throwable $e ) {
            // Log error instead of displaying
            $this->log_snippet_error( $id, $snippet['title'], $e );

            // Only show errors to admins
            if ( current_user_can( 'manage_options' ) ) {
                echo '<!-- Snippet Error (ID: ' . esc_html( $id ) . '): ' . esc_html( $e->getMessage() ) . ' -->';
            }
        }
    }

    /**
     * Prepare PHP code for execution.
     *
     * Automatically adds opening PHP tag if missing.
     *
     * @since 0.0.15
     * @param string $code Raw PHP code
     * @return string Prepared PHP code
     */
    private function prepare_php_code( $code ) {
        $trimmed = trim( $code );

        if ( empty( $trimmed ) ) {
            return '';
        }

        if ( 0 !== stripos( $trimmed, '<?php' ) && 0 !== stripos( $trimmed, '<?' ) ) {
            return "<?php\n" . $code;
        }

        return $code;
    }

    /**
     * Render shortcode output.
     *
     * @since 0.0.10
     * @since 0.1.1 Added security improvements
     * @param array $atts Shortcode attributes
     * @return string Rendered output
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'ccs_snippet' );
        $post_id = intval( $atts['id'] );

        if ( ! $post_id || 'ccs_snippet' !== get_post_type( $post_id ) ) {
            return '';
        }

        // Check if snippet is active
        $active = get_post_meta( $post_id, '_ccs_active', true );
        if ( '' === $active ) {
            $active = 1;
        }
        if ( ! $active ) {
            return '';
        }

        // Respect Safe Mode
        if ( $this->is_safe_mode_active() ) {
            return '';
        }

        $code = get_post_meta( $post_id, '_ccs_code', true );
        $type = get_post_meta( $post_id, '_ccs_type', true );
        $title = get_the_title( $post_id );

        ob_start();
        $this->render_snippet_code( [
            'id'    => $post_id,
            'type'  => $type,
            'code'  => $code,
            'title' => $title,
        ] );
        return ob_get_clean();
    }

    /**
     * Check if Safe Mode is active.
     *
     * @since 0.1.1
     * @return bool True if safe mode is active
     */
    private function is_safe_mode_active() {
        return isset( $_GET['ccs_safe_mode'] ) && '1' === $_GET['ccs_safe_mode'];
    }

    /**
     * Render Safe Mode banner.
     *
     * @since 0.1.1
     */
    public function render_safe_mode_banner() {
        echo '<div style="position:fixed;bottom:10px;right:10px;background:#dc3232;color:white;padding:15px 20px;z-index:999999;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.3);font-weight:bold;">🆘 Safe Mode Active</div>';
    }

    /**
     * Log snippet execution error.
     *
     * @since 0.1.1
     * @param int    $snippet_id Snippet post ID
     * @param string $title Snippet title
     * @param \Throwable $error Error object
     */
    private function log_snippet_error( $snippet_id, $title, $error ) {
        if ( function_exists( 'error_log' ) ) {
            error_log( sprintf(
                'CCS Snippet Error [ID: %d, Title: %s]: %s in %s:%d',
                $snippet_id,
                $title,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            ) );
        }
    }

    /**
     * Clear snippet cache.
     *
     * @since 0.1.1
     */
    public static function clear_cache() {
        wp_cache_delete( 'ccs_active_snippets', 'ccs_snippets' );
    }
}
