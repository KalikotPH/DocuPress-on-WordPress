<?php
    /*
        Plugin Name: DocuPress
        Plugin URI: http://www.bytescrafter.net/projects/docupress
        Description: Documentation plugin foir WordPress
        Version: 0.1.0
        Author: Bytes Crafter
        Author URI:   https://www.bytescrafter.net/about-us
        Text Domain:  bytescrafter-usocketnet
        
        * @package      bytescrafter-docupress
        * @author       Bytes Crafter

        * @copyright    2020 Bytes Crafter
        * @version      0.1.0

        * @wordpress-plugin
        * WC requires at least: 2.5.0
        * WC tested up to: 5.4
    */

    #region WP Recommendation - Prevent direct initilization of the plugin.
    if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly
    if ( ! function_exists( 'is_plugin_active' ) ) 
    {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    #endregion

    class DocuPress {
        /**
         * The single class instance.
         *
         * @var $instance
         */
        private static $instance = null;

        /**
         * Path to the plugin directory
         *
         * @var $plugin_path
         */
        public $plugin_path;

        /**
         * URL to the plugin directory
         *
         * @var $plugin_url
         */
        public $plugin_url;

        /**
         * Theme templates directory path
         *
         * @var $theme_dir_path
         */
        public $theme_dir_path;

        /**
         * Path to template folder
         *
         * @var $theme_dir_path
         */
        public $template_path;

        /**
         * Post type name for documents
         *
         * @var $post_type
         */
        public $post_type = 'docs';

        /**
         * Current Page - is Docs Archive. Will be changed from Template Loader class
         *
         * @var $post_type
         */
        public $is_archive = false;

        /**
         * Current Page - is Docs Single. Will be changed from Template Loader class
         *
         * @var $post_type
         */
        public $is_single = false;

        /**
         * Main Instance
         * Ensures only one instance of this class exists in memory at any one time.
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
                self::$instance->plugin_init();
            }
            return self::$instance;
        }

        /**
         * Plugin init.
         */
        public function plugin_init() {
            $this->plugin_path    = plugin_dir_path( __FILE__ );
            $this->plugin_url     = plugin_dir_url( __FILE__ );
            $this->theme_dir_path = 'docupress/';
            $this->template_path  = $this->plugin_path . '/templates/';

            $this->include_dependencies();

            $this->maybe_setup();

            $this->add_image_sizes();

            // load textdomain.
            load_plugin_textdomain( 'docupress', false, basename( dirname( __FILE__ ) ) . '/languages' );

            // custom post type register.
            add_action( 'init', array( $this, 'register_post_type' ) );

            // Loads frontend scripts and styles.
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
            register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
        }

        /**
         * Activation hook.
         */
        public function activation_hook() {
            $author = get_role( 'author' );
            $admin  = get_role( 'administrator' );

            /* Add docupress manager role */
            $docupress_manager = add_role( 'docupress_manager', __( 'DocuPress Manager', 'docupress' ), $author->capabilities );

            $full_cap = array(
                'read_doc',
                'read_private_doc',
                'read_private_docs',
                'edit_doc',
                'edit_docs',
                'edit_others_docs',
                'edit_private_docs',
                'edit_published_docs',
                'delete_doc',
                'delete_docs',
                'delete_others_docs',
                'delete_private_docs',
                'delete_published_docs',
                'publish_docs',
            );

            /**
             * Add full capacities to admin and docs manager roles
             */
            foreach ( $full_cap as $cap ) {
                if ( null !== $admin ) {
                    $admin->add_cap( $cap );
                }
                if ( null !== $docupress_manager ) {
                    $docupress_manager->add_cap( $cap );
                }
            }

            // Create Docs page if not created.
            $settings = get_option( 'docupress_settings', array() );
            if ( ! $settings || ! $settings['docs_page_id'] ) {
                $docupress_page = wp_insert_post(
                    array(
                        'post_title'  => 'Documentation',
                        'post_type'   => 'page',
                        'post_author' => get_current_user_id(),
                        'post_status' => 'publish',
                        'post_name'   => 'docs',
                    )
                );

                if ( ! is_wp_error( $docupress_page ) ) {
                    $settings['docs_page_id'] = $docupress_page;

                    update_option( 'docupress_settings', $settings );
                }
            }

            // need to flush rules to reset permalinks.
            add_option( 'docupress_setup', 'pending' );
        }

        /**
         * Deactivation hook.
         */
        public function deactivation_hook() {
            /* Deactivation actions */
        }

        /**
         * Maybe run setup code and rewrite rules.
         */
        public function maybe_setup() {
            $docupress_archive_id = docupress()->get_option( 'docs_page_id', 'docupress_settings', false );
            $docs_page            = $docupress_archive_id ? get_post( $docupress_archive_id ) : false;
            $slug                 = $docs_page ? get_post_field( 'post_name', $docs_page ) : 'docs';

            if (
                get_option( 'docupress_setup', false ) === 'pending' ||
                get_option( 'docupress_current_slug', 'docs' ) !== $slug
            ) {
                add_action( 'init', 'flush_rewrite_rules', 11, 0 );
                add_action( 'admin_init', 'flush_rewrite_rules', 11, 0 );

                delete_option( 'docupress_setup' );
                update_option( 'docupress_current_slug', $slug );
            }
        }

        /**
         * Add image sizes.
         */
        public function add_image_sizes() {
            // custom image sizes.
            add_image_size( 'docupress_archive_sm', 20, 20, true );
            add_image_size( 'docupress_archive', 40, 40, true );
            add_filter( 'image_size_names_choose', array( $this, 'image_size_names_choose' ) );
        }

        /**
         * Custom image sizes
         *
         * @param array $sizes - registered image sizes.
         *
         * @return array
         */
        public function image_size_names_choose( $sizes ) {
            return array_merge(
                $sizes,
                array(
                    'docupress_archive_sm' => esc_html__( 'Archive Thumbnail Small (DocuPress)', 'docupress' ),
                    'docupress_archive'    => esc_html__( 'Archive Thumbnail (DocuPress)', 'docupress' ),
                )
            );
        }

        /**
         * Include dependencies
         */
        public function include_dependencies() {
            include_once docupress()->plugin_path . 'includes/class-template-loader.php';
            include_once docupress()->plugin_path . 'includes/class-walker-docs.php';
            include_once docupress()->plugin_path . 'includes/class-suggestion.php';

            if ( is_admin() ) {
                include_once docupress()->plugin_path . 'includes/admin/class-admin.php';
            }

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                include_once docupress()->plugin_path . 'includes/class-ajax.php';
            }
        }

        /**
         * Enqueue admin scripts
         *
         * Allows plugin assets to be loaded.
         *
         * @uses wp_enqueue_script()
         * @uses wp_localize_script()
         * @uses wp_enqueue_style
         */
        public function enqueue_scripts() {
            if ( ! ( $this->is_archive || $this->is_single ) ) {
                return;
            }

            wp_enqueue_style( 'docupress', docupress()->plugin_url . 'assets/css/style.min.css', array(), '2.2.0' );

            $deps = array( 'jquery' );
            if ( docupress()->get_option( 'show_anchor_links', 'docupress_single', true ) ) {
                wp_enqueue_script( 'anchor-js', docupress()->plugin_url . 'assets/vendor/anchor/anchor.min.js', array(), '4.1.1', true );
                $deps[] = 'anchor-js';
            }

            wp_enqueue_script( 'docupress', docupress()->plugin_url . 'assets/js/script.min.js', $deps, '2.2.0', true );
            wp_localize_script(
                'docupress',
                'docupress_vars',
                array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'docupress-ajax' ),
                )
            );

            // Custom script for AJAX.
            $ajax      = docupress()->get_option( 'ajax', 'docupress_single', true );
            $custom_js = docupress()->get_option( 'ajax_custom_js', 'docupress_single', '' );
            if ( $ajax && $custom_js ) {
                wp_add_inline_script(
                    'docupress',
                    '
                    (function ($) {
                        $(document).on("docupress_ajax_loaded", function (event, new_page) {
                            ' . $custom_js . '
                        });
                    }(jQuery));
                '
                );
            }
        }

        /**
         * Register the post type
         *
         * @return void
         */
        public function register_post_type() {
            $docupress_archive_id = docupress()->get_option( 'docs_page_id', 'docupress_settings', false );
            $docs_page            = $docupress_archive_id ? get_post( $docupress_archive_id ) : false;

            $labels = array(
                'name'               => $docs_page ? get_the_title( $docs_page ) : _x( 'DocuPress', 'Post Type General Name', 'docupress' ),
                'singular_name'      => _x( 'Doc', 'Post Type Singular Name', 'docupress' ),
                'menu_name'          => __( 'Documentation', 'docupress' ),
                'parent_item_colon'  => __( 'Parent Doc', 'docupress' ),
                'all_items'          => __( 'All Documentations', 'docupress' ),
                'view_item'          => __( 'View Documentation', 'docupress' ),
                'add_new_item'       => __( 'Add Documentation', 'docupress' ),
                'add_new'            => __( 'Add New', 'docupress' ),
                'edit_item'          => __( 'Edit Docs', 'docupress' ),
                'update_item'        => __( 'Update Documentation', 'docupress' ),
                'search_items'       => __( 'Search Documentation', 'docupress' ),
                'not_found'          => __( 'Not documentation found', 'docupress' ),
                'not_found_in_trash' => __( 'Not found in Trash', 'docupress' ),
            );

            $rewrite = array(
                'slug'       => $docs_page ? get_post_field( 'post_name', $docs_page ) : 'docs',
                'with_front' => false,
                'pages'      => true,
                'feeds'      => true,
            );

            $args = array(
                'labels'              => $labels,
                'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'page-attributes', 'comments' ),
                'hierarchical'        => true,
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_nav_menus'   => true,
                'show_in_admin_bar'   => true,
                'menu_position'       => 5,
                'menu_icon'           => 'dashicons-media-document',
                'can_export'          => true,
                'has_archive'         => $docs_page ? urldecode( get_page_uri( $docupress_archive_id ) ) : 'docs',
                'exclude_from_search' => false,
                'publicly_queryable'  => true,
                'show_in_rest'        => true,
                'rewrite'             => $rewrite,
                'map_meta_cap'        => true,
                'capability_type'     => array( 'doc', 'docs' ),
            );

            register_post_type( $this->post_type, $args );

            register_taxonomy(
                'docs_category',
                $this->post_type,
                array(
                    'label'              => esc_html__( 'Docs Categories', 'docupress' ),
                    'labels'             => array(
                        'menu_name' => esc_html__( 'Categories', 'docupress' ),
                    ),
                    'rewrite'            => array(
                        'slug' => 'docs-category',
                    ),
                    'hierarchical'       => false,
                    'publicly_queryable' => false,
                    'show_in_nav_menus'  => false,
                    'show_in_rest'       => true,
                    'show_admin_column'  => true,
                )
            );
        }

        /**
         * Get the value of a settings field
         *
         * @param string $option settings field name.
         * @param string $section the section name this field belongs to.
         * @param string $default default text if it's not found.
         *
         * @return mixed
         */
        public function get_option( $option, $section, $default = '' ) {

            $options = get_option( $section );

            if ( isset( $options[ $option ] ) ) {
                return 'off' === $options[ $option ] ? false : ( 'on' === $options[ $option ] ? true : $options[ $option ] );
            }

            return $default;
        }

        /**
         * Get template part or docs templates
         * Looks at the theme directory first
         *
         * @param string $name template file name.
         * @param string $data template data.
         */
        public function get_template_part( $name, $data = array() ) {
            $name = (string) $name;

            // lookup at docupress/name.php.
            $template = locate_template(
                array(
                    docupress()->theme_dir_path . "{$name}.php",
                )
            );

            // fallback to plugin default template.
            if ( ! $template && $name && file_exists( docupress()->template_path . "{$name}.php" ) ) {
                $template = docupress()->template_path . "{$name}.php";
            }

            if ( $template ) {
                $this->load_template( $template, $data );
            }
        }

        /**
         * Load template with additional data.
         *
         * @param string $template_path - template path.
         * @param array  $template_data - template data array.
         */
        public function load_template( $template_path, $template_data ) {
            if ( isset( $template_data ) && is_array( $template_data ) ) {
                // phpcs:ignore
                extract( $template_data );
            }

            if ( file_exists( $template_path ) ) {
                include $template_path;
            }
        }

        /**
         * Is Archive
         *
         * @return bool
         */
        public function is_archive() {
            return $this->is_archive;
        }

        /**
         * Is Single
         *
         * @return bool
         */
        public function is_single() {
            return $this->is_single;
        }

        /**
         * Get current document ID
         *
         * @return int
         */
        public function get_current_doc_id() {
            global $post;

            if ( $post->post_parent ) {
                $ancestors = get_post_ancestors( $post->ID );
                $root      = count( $ancestors ) - 1;
                $parent    = $ancestors[ $root ];
            } else {
                $parent = $post->ID;
            }

            return apply_filters( 'docupress_current_doc_id', $parent );
        }

        /**
         * Get document page title
         *
         * @return string
         */
        public function get_docs_page_title() {
            $title        = esc_html__( 'Documentation', 'docupress' );
            $docs_page_id = docupress()->get_option( 'docs_page_id', 'docupress_settings' );

            if ( $docs_page_id ) {
                $title = get_the_title( $docs_page_id );
            }

            return apply_filters( 'docupress_page_title', $title );
        }

        /**
         * Get document page content
         *
         * @return string
         */
        public function get_docs_page_content() {
            $content      = '';
            $docs_page_id = docupress()->get_option( 'docs_page_id', 'docupress_settings' );

            if ( $docs_page_id ) {
                $content = get_post_field( 'post_content', $docs_page_id );
            }

            return apply_filters( 'docupress_page_content', $content );
        }

        /**
         * Get breadcrumbs array
         *
         * @return array
         */
        public function get_breadcrumbs_array() {
            global $post;

            $result       = array();
            $docs_page_id = docupress()->get_option( 'docs_page_id', 'docupress_settings' );

            $result[] = array(
                'label'    => __( 'Home', 'docupress' ),
                'url'      => home_url( '/' ),
            );

            if ( $docs_page_id ) {
                $result[] = array(
                    'label' => get_the_title( $docs_page_id ) ? get_the_title( $docs_page_id ) : __( 'Docs', 'docupress' ),
                    'url'   => get_permalink( $docs_page_id ),
                );
            }

            if ( 'docs' === $post->post_type && $post->post_parent ) {
                $parent_id   = $post->post_parent;
                $temp_crumbs = array();

                while ( $parent_id ) {
                    $page          = get_post( $parent_id );
                    $temp_crumbs[] = array(
                        'label'    => get_the_title( $page->ID ),
                        'url'      => get_permalink( $page->ID ),
                    );
                    $parent_id     = $page->post_parent;
                }

                $temp_crumbs = array_reverse( $temp_crumbs );

                foreach ( $temp_crumbs as $crumb ) {
                    $result[] = $crumb;
                }
            }

            return apply_filters( 'docupress_breadcrumbs_array', $result );
        }

        /**
         * Next doc ID for the current doc page
         *
         * @return int
         */
        public function get_next_adjacent_doc_id() {
            global $post, $wpdb;

            $next_query = "SELECT ID FROM $wpdb->posts
            WHERE post_parent = $post->post_parent and post_type = 'docs' and post_status = 'publish' and menu_order > $post->menu_order
            ORDER BY menu_order ASC
            LIMIT 0, 1";

            // phpcs:ignore
            return (int) $wpdb->get_var( $next_query );
        }

        /**
         * Previous doc ID for the current doc page
         *
         * @return int
         */
        public function get_previous_adjacent_doc_id() {
            global $post, $wpdb;

            $prev_query = "SELECT ID FROM $wpdb->posts
            WHERE post_parent = $post->post_parent and post_type = 'docs' and post_status = 'publish' and menu_order < $post->menu_order
            ORDER BY menu_order DESC
            LIMIT 0, 1";

            // phpcs:ignore
            return (int) $wpdb->get_var( $prev_query );
        }

    } // DocuPress

    /**
     * Initialize the plugin
     *
     * @return \DocuPress
     */
    function docupress() {
        return DocuPress::instance();
    }
    docupress();
