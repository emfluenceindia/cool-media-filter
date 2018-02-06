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
        public $text_domain;

        function __construct()
        {
            $this->plugin = plugin_basename( __FILE__ );
            $this->taxonomy = 'category';
            $this->post_type = 'attachment';
            $this->text_domain = 'cool-media-filter';

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
                //add_action( "plugin_action_links_$this->plugin", array( $this, 'action_links' ) );
                add_action( 'ajax_query_attachments_args', array( $this, 'ajax_attachment_query_builder' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_action' ) );
                add_action( 'wp_ajax_save-attachment-compat', array( $this, 'save_attachment') , 0 );
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
                    'update_count_callback' => array( $this, 'update_count' ),
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

            $callback_arg =  array( $this, 'update_count' );
        }

        //if_admin()... (all functions below this point will be executed if and only if we are inside admin)

        function set_attachment_category( $post_ID ) {

            //Attachment already has category.
            if( wp_get_object_terms( $post_ID, $this->taxonomy ) )
                return;

            //Get the default one
            $post_category = array( get_option( 'default_category' ) );

            //Set category
            if( $post_category ) {
                wp_set_post_categories( $post_ID, $post_category );
            }
        }

        function add_category_filter() {
            require_once plugin_dir_path( __FILE__ ) . 'classes/class-walker-category-filter.php';

            global $pagenow;

            if( 'upload.php' === $pagenow ) {

                if ($this->taxonomy !== 'category') {
                    $options = array(
                        'taxonomy' => $this->taxonomy,
                        'name' => $this->taxonomy,
                        'show_option_all' => __('All categories', $this->text_domain),
                        'hide_empty' => false,
                        'hierarchical' => true,
                        'orderby' => 'name',
                        'show_count' => true,
                        'walker' => new WalkerCategoryFilter(),
                        'value' => 'slug',
                    );
                } else {
                    $options = array(
                        'taxonomy' => $this->taxonomy,
                        'show_option_all' => __('All categories', $this->text_domain),
                        'hide_empty' => false,
                        'hierarchical' => true,
                        'orderby' => 'name',
                        'show_count' => true,
                        'walker' => new WalkerCategoryFilter(),
                        'value' => 'id',
                    );
                }

                wp_dropdown_categories($options);
            }
        }

        function bulk_admin_footer() {
            $terms = get_terms( $this->taxonomy, 'hide_empty=0' );

            if( $terms && !is_wp_error( $terms ) ) {
                //Prepare terms here...
                echo '<script type="text/javascript">';
                echo 'jQuery(window).load( function() {';

                echo 'jQuery(\'<optgroup style="color: #336600;" id="coolmediafilter_optgroup1" label="' .  html_entity_decode( __( 'Attach category &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
                echo 'jQuery(\'<optgroup style="color: #336600;" id="coolmediafilter_optgroup2" label="' .  html_entity_decode( __( 'Attach category &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';

                /**
                 * Categories under ADD group
                 */
                foreach( $terms as $term ) {
                    $str_add_option_item = esc_js( __ ( '', $this->text_domain ) . $term->name );

                    echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_add_" . $term->term_taxonomy_id . "').text('" . $str_add_option_item . "').appendTo('#coolmediafilter_optgroup1');";
                    echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_add_" . $term->term_taxonomy_id . "').text('" . $str_add_option_item . "').appendTo('#coolmediafilter_optgroup2');";
                }

                echo 'jQuery(\'<optgroup style="color: #ff0000;" id="coolmediafilter_optgroup3" label="' .  html_entity_decode( __( 'Detach category &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
                echo 'jQuery(\'<optgroup style="color: #ff0000;" id="coolmediafilter_optgroup4" label="' .  html_entity_decode( __( 'Detach category &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';


                /**
                 * Categories under REMOVE group
                 */
                foreach( $terms as $term ) {
                    $str_remove_option_item = esc_js( __( '', $this->text_domain ) . $term->name );

                    echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_remove_" . $term->term_taxonomy_id . "').text('" . $str_remove_option_item . "').appendTo('#coolmediafilter_optgroup3');";
                    echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_remove_" . $term->term_taxonomy_id . "').text('" . $str_remove_option_item . "').appendTo('#coolmediafilter_optgroup4');";
                }

                echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup5" label="' .  html_entity_decode( __( 'Bulk Action &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
                echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup6" label="' .  html_entity_decode( __( 'Bulk Action &raquo;', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';


                /**
                 * Remove all categories
                 */

                echo "jQuery('<option>').val('coolmediafilter_remove_0').text('" . esc_js(  __( 'Remove all categories', $this->text_domain ) ) . "').appendTo('#coolmediafilter_optgroup5');";
                echo "jQuery('<option>').val('coolmediafilter_remove_0').text('" . esc_js(  __( 'Remove all categories', $this->text_domain ) ) . "').appendTo('#coolmediafilter_optgroup6');";

                echo '})'; // anonymous function definition ends

                echo '</script>';
            }
        }

        function bulk_admin_action() {
            global $wpdb;

            // REQUEST['action'] is not set stop execution
            if( !isset( $_REQUEST['action'] ) ) {
                return;
            }

            //Check if 'action' is a category. If not stop execution
            $action = ( $_REQUEST['action'] !== -1 ) ? $_REQUEST['action'] : $_REQUEST['action2'];
            if( substr( $action, 0, 16 ) !== 'coolmediafilter_' ) { //need to check the correct position.
                return;
            }

            //Do a security check
            check_admin_referer( 'bulk-media' );

            //If Ids are not submitted stop execution.
            if( isset( $_REQUEST['media'] ) ) {
                $post_ids = array_map( 'intval', $_REQUEST['media'] );
            }

            if( empty( $post_ids ) ) {
                return;
            }

            $safe_sendback_url = admin_url( "upload.php?editCategory=1" );

            //Remember page number for safe redirect
            //If no current page is set, default to 0
            $current_page_number = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;
            $safe_sendback_url = add_query_arg( 'paged', $current_page_number, $safe_sendback_url );

            //Remember orderby settings for using when redirected.
            if( isset( $_REQUEST['orderby'] ) ) {
                $current_orderby = $_REQUEST['orderby'];
                $safe_sendback_url = esc_url( add_query_arg( 'orderby', $current_orderby, $safe_sendback_url ) );
            }

            //Remeber current order (ASC or DESC) settings for using when redirected.
            if( isset( $_REQUEST['order'] ) ) {
                $current_display_order = $_REQUEST['order'];
                $safe_sendback_url = esc_url( add_query_arg( 'order', $current_display_order, $safe_sendback_url ) );
            }

            //Remember author
            if( isset( $_REQUEST['author'] ) ) {
                $current_author = $_REQUEST['author'];
                $safe_sendback_url = esc_url( add_query_arg( 'author', $current_author, $safe_sendback_url ) );
            }

            //Start CRUD functionality

            foreach( $post_ids as $post_id ) {
                if( is_numeric( str_replace( 'coolmediafilter_add_', '', $action ) ) ) {
                    $category_id = str_replace( 'coolmediafilter_add_', '', $action );

                    //Run Insert or Update category routine
                    $wpdb->replace( $wpdb->term_relationships,
                        array(
                            'object_id'         => $post_id,
                            'term_taxonomy_id'  => $category_id,
                        ),
                        array(
                            '%d',
                            '%d'
                        )
                    );
                }
                elseif( is_numeric( str_replace( 'coolmediafilter_remove_', '', $action ) ) ) {
                    $category_id = str_replace( 'coolmediafilter_remove_', '', $action );

                    if( $category_id == 0 ) {
                        //Remove all category associations from all selected media
                        $wpdb->delete( $wpdb->term_relationships,
                            array(
                                'object_id' => $post_id
                            ),
                            array(
                                '%d'
                            )
                        );
                    } else {
                        //Remove selected category from selected media
                        $wpdb->delete( $wpdb->term_relationships,
                            array(
                                'object_id'         => $post_id,
                                'term_taxonomy_id'   => $category_id
                            ),
                            array(
                                '%d',
                                '%d'
                            )
                        );
                    }
                }
            }

            $this->update_count();
            wp_safe_redirect( $safe_sendback_url );
            exit();
        }

        /** Display update message after category edit */
        function bulk_admin_notice() {
            global $pagenow, $post_type;

            if( $pagenow === 'upload.php' && $post_type == 'attachment' && isset( $_GET['editCategory'] ) ) {
                echo '<div class="updated"><p>' . __('All changes are saved', $this->text_domain) . '</p></div>';
            }
        }

        function action_links( $links ) {
            //To be implemented
        }

        /**
         * @param array $query
         * @return array $query
         * Changing categories in gridview
         * Gets the original query via $query argument
         * We find intersecting keys in $query array and a taxonomy query
         * Then merge the those keys into main $query array
         */
        function ajax_attachment_query_builder( $query = array() ) {
            //We grab the original query which is already filtered by WordPress
            $tax_query = isset( $_REQUEST['query'] ) ? (array)$_REQUEST['query'] : array();
            
            //Get the taxonomies for attachments by names
            $att_taxonomies = get_object_taxonomies( 'attachment', 'names' );

            $tax_query = array_intersect_key( $tax_query, array_flip( $att_taxonomies ) );

            //Merge $tax_query into actual filtered WordPress query
            array_merge( $query, $tax_query );

            $query['tax_query'] = array( 'relation' => 'AND' );

            foreach( $att_taxonomies as $att_taxonomy ) {
                if( isset( $query[$att_taxonomy] ) && is_numeric( $query[$att_taxonomy] ) ) {
                    array_push( $query['tax_query'], array(
                            'taxonomy'  => $att_taxonomy,
                            'field'     => 'id',
                            'terms'     => $query[$att_taxonomy],
                        )
                    );
                }

                unset( $query[$att_taxonomy] );
            }

            return $query;
        }

        function enqueue_media_action() {
            require_once plugin_dir_path( __FILE__ ) . 'classes/class-walker-category-mediagrid-filter.php';

            global $pagenow;

            if( wp_script_is( 'media-editor' ) && 'upload.php' === $pagenow ) {
                if( $this->taxonomy !== 'category' ) {
                    $options = array(
                        'taxonomy'      => $this->taxonomy,
                        'hierarchical'  => true,
                        'hide_empty'    => false,
                        'show_count'    => true,
                        'orderby'       => 'name',
                        'value'         => 'id',
                        'echo'          => false,
                        'walker'        => new WalkerCategoryMediaGridFilter(),

                    );
                } else {
                    $options = array(
                        'taxonomy'      => $this->taxonomy,
                        'hierarchical'  => true,
                        'hide_empty'    => false,
                        'show_count'    => false,
                        'orderby'       => 'name',
                        'value'         => 'id',
                        'echo'          => false,
                        'walker'        => new WalkerCategoryMediaGridFilter(),
                    );
                }

                $attachment_terms = wp_dropdown_categories( $options );
                $attachment_terms = preg_replace( array( "/<select([^>]*)>/", "/<\/select>/" ), "", $attachment_terms );

                echo '<script type="text/javascript">';
                echo '/* <![CDATA[ */';
                echo 'var coolmediafilter_taxonomies = {"' . $this->taxonomy . '":{"list_title":"' . html_entity_decode( __( 'All categories', $this->text_domain ), ENT_QUOTES, 'UTF-8' ) . '","term_list":[' . substr( $attachment_terms, 2 ) . ']}};';
                echo '/* ]]> */';
                echo '</script>';

                wp_enqueue_script('coolmediafilter-media-views', plugins_url( 'js/coolmediafilter-media-views.js', __FILE__ ), array( 'media-views' ), '1.0.0', true );
            }

            wp_enqueue_style( 'coolmediafilter', plugins_url( 'css/coolmediafilter.css', __FILE__ ), array(), '1.0.0' );
        }

        /**
         * Save categories from attachment details page
         * Error handling:
         * 1. if REQUEST['id'] is not set
         * 2. if REQUEST['id'] is not an integer
         * 3. if REQUEST['attachment'] or REQUEST['attachment'][id] is empty
         * 4. if current user cannot edit current post
         * 5. if post type of current post is not 'attachment'
         */

        function save_attachment() {
            if( !isset($_REQUEST['id']) ) {
                wp_send_json_error();
            }

            $id = $_REQUEST['id'];
            if( ! $id === absint( $_REQUEST['id'] ) ) {
                wp_send_json_error();
            }

            if( empty( $_REQUEST['attachments'] ) || empty( $_REQUEST['attachments'][ $id ] ) ) {
                wp_send_json_error();
            }

            $attachment_data = $_REQUEST['attachments'][ $id ];

            check_ajax_referer( 'update_post_' . $id, 'nonce' );

            if ( !current_user_can( 'edit_post', $id ) ) {
                wp_send_json_error();
            }

            $post = get_post( $id, ARRAY_A );

            if( 'attachment' !== $post['post_type'] ) {
                wp_send_json_error();
            }

            //https://codex.wordpress.org/Plugin_API/Filter_Reference/attachment_fields_to_save
            $post = apply_filters( 'attachment_fields_to_save',  $post, $attachment_data );

            if( isset( $_POST['errors'] ) ) {
                $errors = $_POST['errors'];
                unset ( $_POST['errors'] );
            }

            wp_update_post( $post );

            foreach( get_attachment_taxonomies( $post ) as $obj_taxonomy ) {
                if( isset( $attachment_data[ $obj_taxonomy ] ) ) {
                    wp_set_object_terms( $id, array_map( 'trim', preg_split( '/,+/', $attachment_data[ $obj_taxonomy ] ) ), $obj_taxonomy, false );
                } else if( isset($_REQUEST['tax_input']) && isset( $_REQUEST['tax_input'][ $obj_taxonomy ] ) ) {
                    wp_set_object_terms( $id, $_REQUEST['tax_input'][ $obj_taxonomy ], $obj_taxonomy, false );
                } else {
                    wp_set_object_terms( $id, '', $obj_taxonomy, false );
                }
            }

            if ( ! $attachment = wp_prepare_attachment_for_js( $id ) ) {
                wp_send_json_error();
            }

            wp_send_json_success( $attachment );

        }

        /**
         * @param $form_fields
         * @param $post
         * @return mixed
         */
        function attachment_editable_fields( $form_fields, $post ) {

            foreach ( get_attachment_taxonomies( $post->ID ) as $obj_taxonomy ) {
                $terms = get_object_term_cache( $post->ID, $obj_taxonomy );

                $t = (array)get_taxonomy( $obj_taxonomy );
                if ( ! $t['public'] || ! $t['show_ui'] ) {
                    continue;
                }
                if ( empty($t['label']) ) {
                    $t['label'] = $obj_taxonomy;
                }
                if ( empty($t['args']) ) {
                    $t['args'] = array();
                }

                if ( false === $terms ) {
                    $terms = wp_get_object_terms($post->ID, $obj_taxonomy, $t['args']);
                }

                $values = array();

                foreach ( $terms as $term ) {
                    $values[] = $term->slug;
                }

                $t['value'] = join(', ', $values);
                $t['show_in_edit'] = false;

                if ( $t['hierarchical'] ) {
                    ob_start();

                    wp_terms_checklist( $post->ID, array( 'taxonomy' => $obj_taxonomy, 'checked_ontop' => false, 'walker' => new WalkerMediaTaxonomyCheckList() ) );

                    if ( ob_get_contents() != false ) {
                        $html = '<ul class="term-list">' . ob_get_contents() . '</ul>';
                    } else {
                        $html = '<ul class="term-list"><li>No ' . $t['label'] . '</li></ul>';
                    }

                    ob_end_clean();

                    $t['input'] = 'html';
                    $t['html'] = $html;
                }

                $form_fields[ $obj_taxonomy ] = $t;
            }

            return $form_fields;
        }

    }
}

if( class_exists( 'CoolMediaFilter') ) {
    $coolMediaFilter = new CoolMediaFilter();
    $coolMediaFilter->register();
}

require_once plugin_dir_path(__FILE__) . 'inc/plugin-actions.php';

//activate
register_activation_hook( __FILE__, array( 'PluginAction', 'activate' ) );

//deactivate
register_deactivation_hook( __FILE__, array( 'PluginAction', 'deactivate' ) );