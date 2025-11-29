<?php
/**
 * Post Types and Taxonomies
 *
 * Registers custom post types and taxonomies.
 *
 * @package CCS_Code_Snippets
 * @since 0.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_Post_Types
 *
 * Handles registration of custom post types and taxonomies.
 */
class CCS_Post_Types {

    /**
     * Initialize post types.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    /**
     * Register snippet post type.
     *
     * @since 0.0.1
     * @since 0.1.1 Improved labels and settings
     */
    public function register_post_types() {
        $labels = [
            'name'                  => _x( 'Snippets', 'Post type general name', 'ccs-snippets' ),
            'singular_name'         => _x( 'Snippet', 'Post type singular name', 'ccs-snippets' ),
            'menu_name'             => _x( 'Code Snippets', 'Admin Menu text', 'ccs-snippets' ),
            'name_admin_bar'        => _x( 'Snippet', 'Add New on Toolbar', 'ccs-snippets' ),
            'add_new'               => __( 'Add New', 'ccs-snippets' ),
            'add_new_item'          => __( 'Add New Snippet', 'ccs-snippets' ),
            'new_item'              => __( 'New Snippet', 'ccs-snippets' ),
            'edit_item'             => __( 'Edit Snippet', 'ccs-snippets' ),
            'view_item'             => __( 'View Snippet', 'ccs-snippets' ),
            'all_items'             => __( 'All Snippets', 'ccs-snippets' ),
            'search_items'          => __( 'Search Snippets', 'ccs-snippets' ),
            'not_found'             => __( 'No snippets found.', 'ccs-snippets' ),
            'not_found_in_trash'    => __( 'No snippets found in Trash.', 'ccs-snippets' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-editor-code',
            'menu_position'      => 80,
            'capability_type'    => 'post',
            'capabilities'       => [
                'create_posts' => 'manage_options',
            ],
            'map_meta_cap'       => true,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,
        ];

        register_post_type( 'ccs_snippet', $args );
    }

    /**
     * Register snippet tags taxonomy.
     *
     * @since 0.0.1
     * @since 0.1.1 Improved labels
     */
    public function register_taxonomies() {
        $labels = [
            'name'              => _x( 'Tags', 'taxonomy general name', 'ccs-snippets' ),
            'singular_name'     => _x( 'Tag', 'taxonomy singular name', 'ccs-snippets' ),
            'search_items'      => __( 'Search Tags', 'ccs-snippets' ),
            'all_items'         => __( 'All Tags', 'ccs-snippets' ),
            'edit_item'         => __( 'Edit Tag', 'ccs-snippets' ),
            'update_item'       => __( 'Update Tag', 'ccs-snippets' ),
            'add_new_item'      => __( 'Add New Tag', 'ccs-snippets' ),
            'new_item_name'     => __( 'New Tag Name', 'ccs-snippets' ),
            'menu_name'         => __( 'Tags', 'ccs-snippets' ),
        ];

        $args = [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
        ];

        register_taxonomy( 'ccs_tags', [ 'ccs_snippet' ], $args );
    }
}
