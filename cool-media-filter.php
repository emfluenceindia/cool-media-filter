<?php
/**
 * @package: CoolMediaFilter
 */

/**
 * Plugin Name: Cool Media Filter
 * Plugin URI:
 * Description: Filter media files and attach or detach to or from selected category in bulk
 * Version: 1.0.0
 * Author: Subrata Sarkar
 * Author URI: http://subratasarkar.com
 * License: GPLv2 or later
 * Text Domain: cool-media-filter
 */

/**
 * The GNU General Public License is a free, copyleft license for software and other kinds of works.
 * The licenses for most software and other practical works are designed to take away your freedom to share and change the works. By contrast, the GNU General Public License is intended to guarantee your freedom to share and change all versions of a program--to make sure it remains free software for all its users. We, the Free Software Foundation, use the GNU General Public License for most of our software; it applies also to any other work released this way by its authors. You can apply it to your programs, too.
 * When we speak of free software, we are referring to freedom, not price. Our General Public Licenses are designed to make sure that you have the freedom to distribute copies of free software (and charge for them if you wish), that you receive source code or can get it if you want it, that you can change the software or use pieces of it in new free programs, and that you know you can do these things.
 * To protect your rights, we need to prevent others from denying you these rights or asking you to surrender the rights. Therefore, you have certain responsibilities if you distribute copies of the software, or if you modify it: responsibilities to respect the freedom of others.
 * For example, if you distribute copies of such a program, whether gratis or for a fee, you must pass on to the recipients the same freedoms that you received. You must make sure that they, too, receive or can get the source code. And you must show them these terms so they know their rights.
 * Developers that use the GNU GPL protect your rights with two steps: (1) assert copyright on the software, and (2) offer you this License giving you legal permission to copy, distribute and/or modify it.
 * For the developers' and authors' protection, the GPL clearly explains that there is no warranty for this free software. For both users' and authors' sake, the GPL requires that modified versions be marked as changed, so that their problems will not be attributed erroneously to authors of previous versions.
 * Some devices are designed to deny users access to install or run modified versions of the software inside them, although the manufacturer can do so. This is fundamentally incompatible with the aim of protecting users' freedom to change the software. The systematic pattern of such abuse occurs in the area of products for individuals to use, which is precisely where it is most unacceptable. Therefore, we have designed this version of the GPL to prohibit the practice for those products. If such problems arise substantially in other domains, we stand ready to extend this provision to those domains in future versions of the GPL, as needed to protect the freedom of users.
 * Finally, every program is threatened constantly by software patents. States should not allow patents to restrict development and use of software on general-purpose computers, but in those that do, we wish to avoid the special danger that patents applied to a free program could make it effectively proprietary. To prevent this, the GPL assures that patents cannot be used to render the program non-free.
*/

if( !class_exists( 'CoolMediaFilter' ) ) {
    class CoolMediaFilter
    {
        public $plugin;
        public $taxonomy;
        public $post_type;

        function __construct()
        {
            $this->plugin = plugin_basename( __FILE__ );
            $this->taxonomy = 'category';
            $this->post_type = 'attachment';

            $this->taxonomy = apply_filters( 'cool_media_taxonomy', $this->taxonomy );
        }

        function register()
        {
            add_action( 'init', array( $this, 'register_taxonomy' ) );
            add_action( 'init', array( $this, 'change_default_update_count_callback' ), 100 );

            add_filter( 'shortcode_atts_gallery', array( $this, 'register_gallery_shortcode' ) );

            if( is_admin() ) {
                add_action( 'add_attachment', array( $this, 'set_attachment_category' ) );
                add_action( 'edit_attachment', array( $this, 'set_attachment_category' ) );

                add_action( 'restrict_manage_posts', array( $this, 'add_category_filter' ) );
                add_action( 'admin_footer-upload.php', array( $this, 'bulk_admin_footer' ) );
                add_action( 'load-upload.php', array( $this, 'bulk_admin_action' ) );
                add_action( 'admin_notices', array( $this, 'bulk_admin_notice' ) );
                add_action( "plugin_action_links_$this->plugin", array( $this, 'action_links' ) );
                add_action( 'ajax_query_attachments_args', array( $this, 'ajax_query_attachment_args' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_action' ) );
                add_action( 'wp_ajax_save-attachment-compat', array( $this, 'save_attachment', 0 ) );
                add_action( 'attachment_fields_to_edit', array( $this, 'attachment_editable_fields' ) );
            }
        }

        function register_gallery_shortcode() {
            require_once plugin_dir_path() . 'inc/gallery-shortcode.php';
        }

        function register_taxonomy() {
            //$this->taxonomy = apply_filters(  'cool_media_taxonomy', $this->taxonomy );

            if( taxonomy_exists( $this->taxonomy ) ) {
                register_taxonomy_for_object_type( $this->taxonomy, $this->post_type );
            } else {
                $args = array(
                    'hierarchical'  => true,
                    'show_admin_column' => true,
                    'update_count_callback' => 'update_count',
                );

                register_taxonomy( $this->taxonomy, array( $this->post_type ), $args );
            }
        }

        function update_count() {
            global $wpdb;

            //$this->taxonomy = apply_filters(  'cool_media_taxonomy', $this->taxonomy );

            // query string with placeholders
            $str_query = "SELECT term_taxonomy_id, MAX(total) AS total FROM ((
                        SELECT tt.term_taxonomy_id, COUNT(*) AS total FROM $wpdb->term_relationships tr,
                        $wpdb->term_taxonomy tt WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s GROUP BY tt.term_taxonomy_id
                        ) UNION ALL (
                        SELECT term_taxonomy_id, 0 AS total FROM $wpdb->term_taxonomy WHERE taxonomy = %s
                        )) AS unioncount GROUP BY term_taxonomy_id";

            // prepare actual query by replacing placeholders with $default_taxonomy value.
            $query = $wpdb->prepare( $str_query, $this->taxonomy, $this->taxonomy );

            //run query to return results from database
            $rowsCount = $wpdb->get_results( $query );

            /**
             * Update counts of each term_taxonomy_id in wp_term_taxonomy table
             * column to update: count
             * value to update with: $rowCount->total
             * where: term_taxonomy_id
             * value: $rowCount->term_taxonomy_id
             */
            foreach( $rowsCount as $rowCount ) {
                $wpdb->update( $wpdb->term_taxonomy, array( 'count' => $rowCount->total), array( 'term_taxonomy_id' => $rowCount->term_taxonomy_id ) );
            }
        }

        function change_default_update_count_callback() {
            global $wp_taxonomies;

            if( $this->taxonomy === 'category' ) {
                if( !taxonomy_exists( $this->taxonomy ) )
                    return;
            }

            $callback_arg = &$wp_taxonomies['category']->update_count_callback;

            $callback_arg = 'update_count';
        }

        //If admin...

        function set_attachment_category( $post_id ) {

        }

        function add_category_filter() {

        }

        function bulk_admin_footer() {

        }

        function bulk_admin_action() {

        }

        function bulk_admin_notice() {

        }

        function action_links( $links ) {

        }

        function ajax_query_attachment_args( $query = array() ) {

        }

        function enqueue_media_action() {

        }

        function save_attachment() {

        }

        function attachment_editable_fields( $form_fields, $post ) {

        }

    }
}

if( class_exists( 'CoolMediaFilter') ) {
    $coolMediaFilter = new CoolMediaFilter();
    $coolMediaFilter->register();
}

require_once plugin_dir_path(__FILE__) . 'actions/actions.php';