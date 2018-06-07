<?php
/**
 * Description: Cool Media Filter.
 *
 * @package CoolMediaFilter
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

if ( ! class_exists( 'CoolMediaFilter' ) ) {
	/**
	 * Class CoolMediaFilter
	 */
	class CoolMediaFilter {

		/**
		 * Plugin Name
		 *
		 * @var string
		 */
		public $plugin;

		/**
		 * Taxonomy Name
		 *
		 * @var string
		 */
		public $taxonomy;

		/**
		 * Post Type Name
		 *
		 * @var string
		 */
		public $post_type;

		/**
		 * Text Domain cool-media-filter
		 *
		 * @var string
		 */
		public $text_domain;

		/**
		 * CoolMediaFilter constructor.
		 */
		public function __construct() {
			$this->plugin      = plugin_basename( __FILE__ );
			$this->taxonomy    = 'category';
			$this->post_type   = 'attachment';
			$this->text_domain = 'cool-media-filter';
			$this->taxonomy    = apply_filters( 'cool_media_taxonomy', $this->taxonomy );
		}

		/**
		 * Series of functions are hooked into Actions and Filters.
		 */
		public function cmf_register() {
			add_action( 'init', array( $this, 'cmf_register_taxonomy_for_post_type' ), 0 );
			add_action( 'init', array( $this, 'cmf_change_default_update_count_callback' ), 100 );

			add_filter( 'shortcode_atts_gallery', array( $this, 'cmf_register_gallery_shortcode' ) );

			if ( is_admin() ) {
				add_action( 'add_attachment', array( $this, 'cmf_set_attachment_category' ) );
				add_action( 'edit_attachment', array( $this, 'cmf_set_attachment_category' ) );

				add_action( 'restrict_manage_posts', array( $this, 'cmf_add_category_filter' ) );
				add_action( 'admin_footer-upload.php', array( $this, 'cmf_bulk_admin_footer' ) );

				add_action( 'load-upload.php', array( $this, 'cmf_bulk_admin_action' ) );

				add_action( "plugin_action_links_$this->plugin", array( $this, 'cmf_action_links' ) );

				add_action( 'ajax_query_attachments_args', array( $this, 'cmf_ajax_attachment_query_builder' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'cmf_enqueue_media_action' ) );

				add_action( 'wp_ajax_save-attachment-compat', array( $this, 'cmf_save_attachment' ), 0 );
				add_action( 'attachment_fields_to_edit', array( $this, 'cmf_attachment_editable_fields' ) );

				add_action( 'admin_enqueue_scripts', array( $this, 'cmf_localize_scripts' ) );
				add_action( 'wp_ajax_category_access', array( $this, 'cmf_update_role_category_access' ) );
				add_action( 'wp_ajax_role_permission', array( $this, 'cmf_update_caps_by_role' ) );
			}

			add_action( 'admin_init', array( $this, 'cmf_restrict_category_item_access_by_user_role' ) );

			// ---
			add_action( 'load-upload.php', array( $this, 'cmf_load_media_by_category_access' ) );

			add_filter( 'ajax_query_attachments_args', array( $this, 'cmf_load_media_library_by_category_access' ), 10, 1 );

			add_action( 'admin_menu', array( $this, 'cmf_create_plugin_admin_menu' ) );
			add_action( 'admin_post_new_user_role', array( $this, 'cmf_save_user_role' ) );
			add_action( 'admin_notices', array( $this, 'cmf_maybe_display_notice' ) );
		}

		/**
		 * Description: Filters out and lists media files in grid view by accessible categories of current user's role.
		 *
		 * @param array $query accepts array. default empty array.
		 * @return array
		 */
		public function cmf_load_media_library_by_category_access( $query = array() ) {
			$cats = $this->cmf_get_accessible_categories();

			$tax_query = array(
				array(
					'taxonomy' => $this->taxonomy,
					'field'    => 'id',
					'terms'    => $cats,
				),
			);

			if ( ! current_user_can( 'update_core' ) ) {
				$query['tax_query'] = $tax_query;
			}

			return $query;
		}

		/**
		 * Description: Loads media files by accessible categories.
		 */
		public function cmf_load_media_by_category_access() {
			add_action( 'pre_get_posts', array( $this, 'cmf_load_media_files_by_category_restriction_for_current_user_role' ) );
		}

		/**
		 * Description: Loads media files by accessible categories.
		 */
		public function cmf_load_media_files_by_category_restriction_for_current_user_role() {
			global $wp_query;
			$this->cmf_load_media_by_role( $wp_query );

		}

		/**
		 * Description: Loads media by role.
		 *
		 * @param string $query accepts query object.
		 */
		public function cmf_load_media_by_role( $query ) {
			if ( ! is_admin() ) {
				return;
			}

			// Get array of categories accessible by current user role to pass in $tax_query.
			$cats = $this->cmf_get_accessible_categories();

			if ( $query->is_main_query() ) {
				if ( $this->post_type === $query->get( 'post_type' ) ) {
					if ( ! current_user_can( 'administrator' ) ) {
						$tax_query = array(
							array(
								'taxonomy' => $this->taxonomy,
								'field'    => 'id',
								'terms'    => $cats,
								'operator' => 'IN',
							),
						);

						$query->set( 'tax_query', $tax_query );

					}
				}
			}
		}

		/**
		 * Script localization.
		 */
		public function cmf_localize_scripts() {
			wp_enqueue_script( 'role_category_access', plugin_dir_url( __FILE__ ) . 'js/coolmediafilter-category-restrict.js', array( 'jquery' ), '1.0.0', true );

			wp_localize_script(
				'role_category_access', 'category_access_ajax', array(
					'url'   => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'cmf_role_access_nonce' ),
				)
			);

			wp_enqueue_script( 'update_role_permission', plugin_dir_url( __FILE__ ) . 'js/coolmediafilter-update-role-permission.js', array( 'jquery' ), '1.0.0', true );

			wp_localize_script(
				'update_role_permission', 'role_permission_ajax', array(
					'url'   => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'cmf_update_role_permissions_nonce' ),
				)
			);
		}

		/**
		 * Remove all existing caps from current role
		 * Add new caps to this role
		 */
		public function cmf_update_caps_by_role() {
			$role_key = isset( $_POST['role_key'] ) ? sanitize_text_field( wp_unslash( $_POST['role_key'] ) ) : 'Not defined';
			$new_caps = isset( $_POST['new_caps'] ) ? sanitize_text_field( wp_unslash( $_POST['new_caps'] ) ) : '';

			$old_caps = get_role( $role_key )->capabilities;

			global $wp_roles;

			// Remove caps.
			foreach ( $old_caps as $old_cap_key => $old_cap_value ) {
				$wp_roles->remove_cap( $role_key, $old_cap_key );
			}

			// Add new caps.
			$new_caps_array = explode( ',', $new_caps );

			foreach ( $new_caps_array as $new_cap ) {
				$wp_roles->add_cap( $role_key, $new_cap );
			}

			die();
		}

		/**
		 * Update role-category access.
		 */
		public function cmf_update_role_category_access() {
			$user_role     = 'Not defined';
			$selected_cats = 'None selected';
			$site_id       = -1;

			if ( isset( $_POST['user_role'] ) && '' !== $_POST['user_role'] ) {
				$user_role = sanitize_text_field( wp_unslash( $_POST['user_role'] ) );
			}

			if ( isset( $_POST['selected_cats'] ) && '' !== $_POST['selected_cats'] ) {
				$selected_cats = sanitize_text_field( wp_unslash( $_POST['selected_cats'] ) );
			}

			if ( isset( $_POST['site_id'] ) && '' !== $_POST['site_id'] ) {
				$site_id = (int)( $_POST['site_id'] );
			}

			// We have all the information back from AJAX. Now we are good to save them in our map table.
			global $wpdb;

			/**
			 * Table name
			 *
			 * @var string
			 */
			$table_name = explode( '_', $wpdb->prefix )[0] . '_category_role';

			$cats = explode( ',', $selected_cats );

			// Remove all Previous category map before inserting new categories.
			$this->cmf_delete_all_previous_role_category_map( $table_name, $user_role, $site_id );

			if ( ! empty( $cats ) ) {
				foreach ( $cats as $cat ) {
					$wpdb->insert(
						$table_name,
						array(
							'site_id'   => $site_id,
							'user_role' => $user_role,
							'cat_id'    => $cat,
						),
						array(
							'%d',
							'%s',
							'%d',
						)
					);
				}
			}

			die();
		}

		/**
		 * Description: Remove all category-role map from wp_role_category table based on current role and site_id.
		 *
		 * @param string $table_name accepts a table name. required.
		 * @param string $role       accepts role name. required.
		 * @param null   $site_id    accepts site_id as integer. optional. default is null.
		 */
		public function cmf_delete_all_previous_role_category_map( $table_name, $role, $site_id = null ) {
			$query = "DELETE FROM $table_name WHERE user_role = '" . $role . "' AND  site_id = $site_id";
			global $wpdb;

			$wpdb->query( $wpdb->prepare( $query ) ); // WPCS: unprepared SQL OK.
		}

		/**
		 * Redirection.
		 */
		public function cmf_redirect_to_role_page_after_submission() {
			wp_safe_redirect( admin_url( 'admin.php?page=cmf-new-user-role' ) );
		}

		/**
		 * Custom gallery shortcode.
		 */
		public function cmf_custom_gallery_shortcode() {
			require_once plugin_dir_path() . 'inc/coolmediafilter-gallery-shortcode.php';
		}

		/**
		 * Register taxonomy.
		 */
		public function cmf_register_taxonomy_for_post_type() {
			if ( taxonomy_exists( $this->taxonomy ) ) {
				register_taxonomy_for_object_type( $this->taxonomy, $this->post_type );
			} else {
				$args = array(
					'hierarchical'          => true,
					'show_admin_column'     => true,
					'update_count_callback' => array( $this, 'cmf_update_count' ),
				);

				register_taxonomy( $this->taxonomy, array( $this->post_type ), $args );
			}
		}

		/**
		 * Description: updates item count for each taxonomy.
		 */
		public function cmf_update_count() {
			global $wpdb;

			// query string with placeholders.
			$str_query = "SELECT term_taxonomy_id, MAX(total) AS total FROM ((
                        SELECT tt.term_taxonomy_id, COUNT(*) AS total FROM $wpdb->term_relationships tr,
                        $wpdb->term_taxonomy tt WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s GROUP BY tt.term_taxonomy_id
                        ) UNION ALL (
                        SELECT term_taxonomy_id, 0 AS total FROM $wpdb->term_taxonomy WHERE taxonomy = %s
                        )) AS unioncount GROUP BY term_taxonomy_id";

			// prepare actual query by replacing placeholders with $default_taxonomy value.
			$query = $wpdb->prepare( $str_query, $this->taxonomy, $this->taxonomy ); // WPCS: unprepared SQL OK.

			// Try to get from cache.
			$rows_count = wp_cache_get( 'rows_count' );

			if ( false === $rows_count ) {
				// Run query to return results from database.
				$rows_count = $wpdb->get_results( $query ); // WPCS: unprepared SQL OK.
				wp_cache_set( 'rows_count', $rows_count );
			}

			/**
			 * Update counts of each term_taxonomy_id in wp_term_taxonomy table
			 * column to update: count
			 * value to update with: $row_count->total
			 * where: term_taxonomy_id
			 * value: $row_count->term_taxonomy_id
			 */
			foreach ( $rows_count as $row_count ) {
				$wpdb->update( $wpdb->term_taxonomy, array( 'count' => $row_count->total ), array( 'term_taxonomy_id' => $row_count->term_taxonomy_id ) );
			}
		}

		/**
		 * Callback function for changing default update count.
		 */
		public function cmf_change_default_update_count_callback() {
			global $wp_taxonomies;

			if ( 'category' === $this->taxonomy ) {
				if ( ! taxonomy_exists( $this->taxonomy ) ) {
					return;
				}
			}

			$callback_arg = &$wp_taxonomies['category']->update_count_callback;

			$callback_arg = array( $this, 'cmf_update_count' );
		}

		/**
		 * Description: Set attachment category.
		 *
		 * @param int $post_ID accepts post_id. required.
		 */
		public function cmf_set_attachment_category( $post_ID ) {

			// Attachment already has category.
			if ( wp_get_object_terms( $post_ID, $this->taxonomy ) ) {
				return;
			}

			// Get the default one.
			$post_category = array( get_option( 'default_category' ) );

			// Set category.
			if ( $post_category ) {
				wp_set_post_categories( $post_ID, $post_category );
			}
		}

		/**
		 * Description: Prepare dropdown values.
		 */
		public function cmf_bulk_admin_footer() {
			$role_cats = $this->cmf_get_accessible_categories();

			$args = array(
				'hide_empty' => false,
				'include'    => $role_cats,
			);

			$terms = get_terms( $this->taxonomy, $args );

			if ( $terms && ! is_wp_error( $terms ) ) {
				// Prepare terms here...
				echo '<script type="text/javascript">';
				echo 'jQuery(window).load( function() {';

				echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup1" label="' . html_entity_decode( __( 'Attach to:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
				echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup2" label="' . html_entity_decode( __( 'Attach to:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';

				/**
				 * Categories under ADD group.
				 */
				foreach ( $terms as $term ) {
					$str_add_option_item = esc_js( __( '- ', 'cool-media-filter' ) . $term->name );

					echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_add_" . $term->term_taxonomy_id . "').text('" . $str_add_option_item . "').appendTo('#coolmediafilter_optgroup1');";
					echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_add_" . $term->term_taxonomy_id . "').text('" . $str_add_option_item . "').appendTo('#coolmediafilter_optgroup2');";
				}

				echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup3" label="' . html_entity_decode( __( 'Detach from:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
				echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup4" label="' . html_entity_decode( __( 'Detach from:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';

				/**
				 * Categories under REMOVE group
				 */
				foreach ( $terms as $term ) {
					$str_remove_option_item = esc_js( __( '- ', 'cool-media-filter' ) . $term->name );

					echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_remove_" . $term->term_taxonomy_id . "').text('" . $str_remove_option_item . "').appendTo('#coolmediafilter_optgroup3');";
					echo "jQuery('<option style=\"color: #000000;\">').val('coolmediafilter_remove_" . $term->term_taxonomy_id . "').text('" . $str_remove_option_item . "').appendTo('#coolmediafilter_optgroup4');";
				}

				$term_count = wp_count_terms( $this->taxonomy, $args );

				if ( $term_count > 1 ) {

					echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup5" label="' . html_entity_decode( __( 'Bulk action:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action\']");';
					echo 'jQuery(\'<optgroup id="coolmediafilter_optgroup6" label="' . html_entity_decode( __( 'Bulk action:', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '">\').appendTo("select[name=\'action2\']");';

					/**
					 * Remove all categories
					 */

					echo "jQuery('<option>').val('coolmediafilter_remove_0').text('" . esc_js( __( 'Remove all categories', 'cool-media-filter' ) ) . "').appendTo('#coolmediafilter_optgroup5');";
					echo "jQuery('<option>').val('coolmediafilter_remove_0').text('" . esc_js( __( 'Remove all categories', 'cool-media-filter' ) ) . "').appendTo('#coolmediafilter_optgroup6');";
				}

				echo '})'; // anonymous function definition ends.

				echo '</script>';
			}
		}

		/**
		 * Handle the custom Bulk action.
		 *
		 * @action load-upload.php
		 */
		public function cmf_bulk_admin_action() {
			global $wpdb;

			// REQUEST['action'] is not set stop execution.
			if ( ! isset( $_REQUEST['action'] ) ) {
				return;
			}

			// Check if 'action' is a category. If not stop execution.
			if ( $_REQUEST['action'] !== -1 ) {
				$action = $_REQUEST['action'];
			} else {
				$action = $_REQUEST['action2'];
			}

			if ( substr( $action, 0, 16 ) !== 'coolmediafilter_' ) { // need to check the correct position.
				return;
			}

			//Get the selected category id from $action.
			$selected_category_id = (int)substr( $action, strrpos( $action, '_' ) + 1 );

			if ( isset( $_REQUEST['_ajax_nonce'] ) ) {
				$ajax_nonce = $_REQUEST['_ajax_nonce'];
			} else {
				$ajax_nonce = wp_create_nonce();
			}

			// Do a security check.
			check_admin_referer( 'bulk-media' );

			// If Ids are not submitted stop execution.
			if ( isset( $_REQUEST['media'] ) ) {
				$post_ids = array_map( 'intval', $_REQUEST['media'] );
			}

			if ( empty( $post_ids ) ) {
				return;
			}

			$safe_sendback_url = admin_url( 'upload.php?upload.php?mode=list&attachment-filter&m=0&cat=' . $selected_category_id . '&filter_action=Filter&s&action=-1&paged=1&action2=-1&affected&_ajax_nonce=' . $ajax_nonce . '&ps' );
			//$safe_sendback_url = admin_url( 'upload.php?editCategory=1' );

			// Remember page number for safe redirect.
			// If no current page is set, default to 0.
			$current_page_number = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;
			$safe_sendback_url   = add_query_arg( 'paged', $current_page_number, $safe_sendback_url );

			// Remember orderby settings for using when redirected.
			if ( isset( $_REQUEST['orderby'] ) ) {
				$current_orderby   = $_REQUEST['orderby'];
				$safe_sendback_url = add_query_arg( 'orderby', $current_orderby, $safe_sendback_url );
			}

			// Remeber current order (ASC or DESC) settings for using when redirected.
			if ( isset( $_REQUEST['order'] ) ) {
				$current_display_order = $_REQUEST['order'];
				$safe_sendback_url     = add_query_arg( 'order', $current_display_order, $safe_sendback_url );
			}

			// Remember author.
			if ( isset( $_REQUEST['author'] ) ) {
				$current_author    = $_REQUEST['author'];
				$safe_sendback_url = add_query_arg( 'author', $current_author, $safe_sendback_url );
			}

			// Start CRUD functionality.
			foreach ( $post_ids as $post_id ) {
				if ( is_numeric( str_replace( 'coolmediafilter_add_', '', $action ) ) ) {
					$category_id = str_replace( 'coolmediafilter_add_', '', $action );

					// Run Insert or Update category routine.
					$wpdb->replace(
						$wpdb->term_relationships,
						array(
							'term_taxonomy_id' => $category_id,
							'object_id'        => $post_id,
						),
						array(
							'%d',
							'%d',
						)
					);
				} elseif ( is_numeric( str_replace( 'coolmediafilter_remove_', '', $action ) ) ) {
					$category_id = str_replace( 'coolmediafilter_remove_', '', $action );

					if ( 0 === $category_id ) {
						// Remove all category associations from all selected media.
						$wpdb->delete(
							$wpdb->term_relationships,
							array(
								'object_id' => $post_id,
							),
							array(
								'%d',
							)
						);
					} else {
						// Remove selected category from selected media.
						$wpdb->delete(
							$wpdb->term_relationships,
							array(
								'object_id'        => $post_id,
								'term_taxonomy_id' => $category_id,
							),
							array(
								'%d',
								'%d',
							)
						);
					}
				}
			}

			$this->cmf_update_count();
			wp_safe_redirect( $safe_sendback_url );
			exit();
		}

		/** Display update message after category edit */
		public function cmf_bulk_admin_notice() {
			global $pagenow, $post_type;

			if ( 'upload.php' === $pagenow && $post_type === $this->post_type && isset( $_GET['editCategory'] ) ) {
				echo esc_html( '<div class="updated"><p>' . __( 'All changes are saved', 'cool-media-filter' ) . '</p></div>' );
			}
		}

		/**
		 * To be implemented.
		 *
		 * @param array $links accepts an array of string values.
		 */
		public function cmf_action_links( $links ) {
			// To be implemented.
		}

		/**
		 * Attachment Query Builder.
		 *
		 * @action ajax_query_attachments_args
		 * @param array $query Optional.
		 * @return array $query Returns query object.
		 * Changing categories in gridview.
		 * Gets the original query via $query argument
		 * We find intersecting keys in $query array and a taxonomy query
		 * Then merge the those keys into main $query array
		 */
		public function cmf_ajax_attachment_query_builder( $query = array() ) {
			// We grab the original query which is already filtered by WordPress.
			$tax_query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();

			// Get the taxonomies for attachments by names.
			$att_taxonomies = get_object_taxonomies( 'attachment', 'names' );
			$tax_query = array_intersect_key( $tax_query, array_flip( $att_taxonomies ) );

			// Merge $tax_query into WordPress query.
			$query = array_merge( $query, $tax_query );

			$query['tax_query'] = array( 'relation' => 'AND' );

			foreach ( $att_taxonomies as $att_taxonomy ) {
				if ( isset( $query[ $att_taxonomy ] ) && is_numeric( $query[ $att_taxonomy ] ) ) {
					array_push(
						$query['tax_query'], array(
							'taxonomy' => $att_taxonomy,
							'field'    => 'id',
							'terms'    => $query[ $att_taxonomy ],
						)
					);
				}

				unset( $query[ $att_taxonomy ] );
			}

			return $query;
		}

		/**
		 * Description: Add category filter.
		 */
		public function cmf_add_category_filter() {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-coolmediafilter-walker-category-filter.php';

			global $pagenow;

			if ( 'upload.php' === $pagenow ) {

				// First we find which role current user is in.
				// Then we find category_ids allowed to be accessed by current role.
				// All other category_ids will go into exclude array.
				$role_cats = $this->cmf_get_accessible_categories();

				if ( 'category' !== $this->taxonomy ) {
					$options = array(
						'taxonomy'        => $this->taxonomy,
						'name'            => $this->taxonomy,
						'show_option_all' => __( 'All categories', 'cool-media-filter' ),
						'hide_empty'      => false,
						'hierarchical'    => true,
						'orderby'         => 'name',
						'show_count'      => true,
						'walker'          => new CoolMediaFilterWalkerCategoryFilter(),
						'value'           => 'slug',
						'include'         => $role_cats,
					);
				} else {
					$options = array(
						'taxonomy'        => $this->taxonomy,
						'show_option_all' => __( 'All categories', 'cool-media-filter' ),
						'hide_empty'      => false,
						'hierarchical'    => true,
						'orderby'         => 'name',
						'show_count'      => true,
						'walker'          => new CoolMediaFilterWalkerCategoryFilter(),
						'value'           => 'id',
						'include'         => $role_cats,
					);
				}

				wp_dropdown_categories( $options );
			}
		}

		/**
		 * Adds category dropdown in grid view mode
		 */
		function cmf_enqueue_media_action() {
			require_once plugin_dir_path( __FILE__ ) . 'classes/class-coolmediafilter-walker-category-mediagrid-filter.php';

			global $pagenow;

			if ( wp_script_is( 'media-editor' ) && 'upload.php' === $pagenow ) {
				if ( 'category' !== $this->taxonomy ) {
					$options = array(
						'hierarchical' => true,
						'taxonomy'     => $this->taxonomy,
						'hide_empty'   => false,
						'show_count'   => true,
						'orderby'      => 'name',
						'order'        => 'ASC',
						'value'        => 'id',
						'echo'         => false,
						'walker'       => new CoolMediaFilterWalkerCategoryMediaGridFilter(),

					);
				} else {
					$options = array(
						'hierarchical' => true,
						'taxonomy'     => $this->taxonomy,
						'hide_empty'   => false,
						'show_count'   => true,
						'orderby'      => 'name',
						'order'        => 'ASC',
						'value'        => 'id',
						'echo'         => false,
						'walker'       => new CoolMediaFilterWalkerCategoryMediaGridFilter(),
					);
				}

				$attachment_terms = wp_dropdown_categories( $options );
				$attachment_terms = preg_replace( array( '/<select([^>]*)>/', '/<\/select>/' ), '', $attachment_terms );

				echo '<script type="text/javascript">';
				echo '/* <![CDATA[ */';
				echo 'var coolmediafilter_taxonomies = {"' . $this->taxonomy . '":{"list_title":"' . html_entity_decode( __( 'All categories', 'cool-media-filter' ), ENT_QUOTES, 'UTF-8' ) . '","term_list":[' . substr( $attachment_terms, 2 ) . ']}};';
				echo '/* ]]> */';
				echo '</script>';

				wp_enqueue_script( 'coolmediafilter-media-views', plugins_url( 'js/coolmediafilter-media-views.js', __FILE__ ), array( 'media-views' ), '1.0.0', true );

				$cats = $this->cmf_get_accessible_categories();

				$filtered_cats = array(
					'hide_empty' => false,
					'include'    => $cats,
					'orderby'    => 'name',
					'order'      => 'ASC',
					'show_count' => true,
				);

				wp_localize_script(
					'coolmediafilter-media-views', 'MediaLibraryCategoryFilterOptions',
					array(
						'terms' => get_terms(
							$this->taxonomy,
							$filtered_cats
						),
					)
				);
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

		function cmf_save_attachment() {
			if ( ! isset( $_REQUEST['id'] ) ) {
				wp_send_json_error();
			}

			$id = $_REQUEST['id'];
			if ( ! absint( $_REQUEST['id'] === $id ) ) {
				wp_send_json_error();
			}

			if ( empty( $_REQUEST['attachments'] ) || empty( $_REQUEST['attachments'][ $id ] ) ) {
				wp_send_json_error();
			}

			$attachment_data = $_REQUEST['attachments'][ $id ];
			if( ! is_numeric( $_REQUEST['attachments'][ $id ] ) && ! absint( $_REQUEST['attachments'][ $id ] === $attachment_data ) ) {
				wp_send_json_error();
			}

			check_ajax_referer( 'update_post_' . $id, 'nonce' );

			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_send_json_error();
			}

			$post = get_post( $id, ARRAY_A );

			// if( 'attachment' !== $post['post_type'] ) {
			if ( $this->post_type !== $post['post_type'] ) {
				wp_send_json_error();
			}

			// https://codex.wordpress.org/Plugin_API/Filter_Reference/attachment_fields_to_save
			$post = apply_filters( 'attachment_fields_to_save', $post, $attachment_data );

			if ( isset( $_POST['errors'] ) ) {
				$errors = sanitize_text_field( wp_unslash( $_POST['errors'] ) );
				unset( $_POST['errors'] );
			}

			wp_update_post( $post );

			foreach ( get_attachment_taxonomies( $post ) as $obj_taxonomy ) {
				if ( isset( $attachment_data[ $obj_taxonomy ] ) ) {
					wp_set_object_terms( $id, array_map( 'trim', preg_split( '/,+/', $attachment_data[ $obj_taxonomy ] ) ), $obj_taxonomy, false );
				} elseif ( isset( $_REQUEST['tax_input'] ) && isset( $_REQUEST['tax_input'][ $obj_taxonomy ] ) ) {
					wp_set_object_terms( $id, $_REQUEST['tax_input'][ $obj_taxonomy ], $obj_taxonomy, false );
				} else {
					wp_set_object_terms( $id, '', $obj_taxonomy, false );
				}
			}

			if ( ! $attachment = wp_prepare_attachment_for_js( $id ) ) {
				// if ( wp_prepare_attachment_for_js( $id ) != $attachment ) {
				wp_send_json_error();
			}

			wp_send_json_success( $attachment );

		}

		/**
		 * @param $form_fields
		 * @param $post
		 * @return mixed
		 */
		function cmf_attachment_editable_fields( $form_fields, $post ) {

			foreach ( get_attachment_taxonomies( $post->ID ) as $obj_taxonomy ) {
				$terms = get_object_term_cache( $post->ID, $obj_taxonomy );

				$t = (array) get_taxonomy( $obj_taxonomy );
				if ( ! $t['public'] || ! $t['show_ui'] ) {
					continue;
				}
				if ( empty( $t['label'] ) ) {
					$t['label'] = $obj_taxonomy;
				}
				if ( empty( $t['args'] ) ) {
					$t['args'] = array();
				}

				if ( false === $terms ) {
					$terms = wp_get_object_terms( $post->ID, $obj_taxonomy, $t['args'] );
				}

				$values = array();

				foreach ( $terms as $term ) {
					$values[] = $term->slug;
				}

				$t['value']        = join( ', ', $values );
				$t['show_in_edit'] = false;

				if ( $t['hierarchical'] ) {
					ob_start();

					wp_terms_checklist(
						$post->ID, array(
							'taxonomy'      => $obj_taxonomy,
							'checked_ontop' => false,
							'walker'        => new CoolMediaFilterWalkerMediaTaxonomyCheckList(),
						)
					);

					if ( ob_get_contents() != false ) {
						$html = '<ul class="term-list">' . ob_get_contents() . '</ul>';
					} else {
						$html = '<ul class="term-list"><li>No ' . $t['label'] . '</li></ul>';
					}

					ob_end_clean();

					$t['input'] = 'html';
					$t['html']  = $html;
				}

				$form_fields[ $obj_taxonomy ] = $t;
			}

			return $form_fields;
		}

		/**
		 *
		 */
		function cmf_create_plugin_admin_menu() {
			$page_title = __( 'Cool Media Filter' );
			$menu_title = __( 'Cool Media Filter' );
			$capability = 'manage_options';
			$menu_slug  = 'user-category-access';
			$callback   = array( $this, 'cmf_plugin_options_page' );
			$menu_icon  = 'dashicons-filter';
			$position   = 4;

			add_menu_page(
				$page_title,
				$menu_title,
				$capability,
				$menu_slug,
				$callback,
				$menu_icon,
				$position
			);

			if ( current_user_can( 'update_core' ) ) {

				add_submenu_page(
					$menu_slug,
					__( 'Overview' ),
					__( 'Overview' ),
					'read',
					'cmf-plugin-overview',
					array( $this, 'cmf_overview_markup' )
				);

				add_submenu_page(
					'user-category-access',
					__( 'All Roles' ),
					__( 'Roles and Permissions' ),
					'manage_options',
					'cmf-user-roles',
					array( $this, 'list_user_roles' )
				);

				add_submenu_page(
					'user-category-access',
					'Add New Role',
					'Add New Role',
					'manage_options',
					'cmf-new-user-role',
					array( $this, 'cmf_add_user_role_markup' )
				);

				add_submenu_page(
					'user-category-access',
					'Category Access',
					'Category Access',
					'manage_options',
					'cmf-manage-category-access',
					array( $this, 'cmf_restrict_category_access_by_role' )
				);

				remove_submenu_page( $menu_slug, $menu_slug );
			}
		}

		/**
		 * Register options page
		 * Purpose: Display categories with ability to select user roles for accessing category items
		 */

		function cmf_restrict_category_item_access_by_user_role() {
			add_option( 'category_item_access_by_role', 'Category Access By Role' );
			register_setting( 'category_item_access', 'category_item_access_by_role', 'category_access_callback' );
		}

		/**
		 * Create option page for settings
		 * Not required ?
		 */
		function cmf_category_access_option_page() {
			add_options_page( 'Category Access', 'Restrict access to category items', 'manage_options', 'user-category-access', array( $this, 'cmf_plugin_options_page' ) );
		}

		/**
		 *
		 */
		function cmf_plugin_options_page() {
			echo '<div class="wrap"><h1>Cool Media Filter</h1></div>';
		}

		/**
		 * @param null $user
		 * @return null|WP_User
		 */
		function cmf_get_current_user( $user = null ) {
			$user = $user ? new WP_User( $user ) : wp_get_current_user();
			return $user;
		}

		/**
		 * Get current site
		 */
		function cmf_get_current_site() {
			$current_site = get_blog_details();
			return $current_site;
		}

		/**
		 * Get current user's role
		 */
		function cmf_get_current_user_role() {
			$user = $this->cmf_get_current_user();
			return $user->roles ? $user->roles[0] : false;
		}

		/**
		 * Get category_ids accessible by current user role
		 */
		function cmf_get_accessible_categories() {

			$filter_cats = array();

			global $wpdb;

			$user_role    = $this->cmf_get_current_user_role();
			$current_site = $this->cmf_get_current_site();

			$query = 'SELECT * FROM wp_category_role WHERE site_id = ' . $current_site->id . " AND user_role = '" . $user_role . "'";

			$result = $wpdb->get_results( $query, OBJECT );

			foreach ( $result as $item ) {
				array_push( $filter_cats, (int) $item->cat_id );
			}

			return $filter_cats;
		}

		/**
		 * Get category_ids excluded for current user role
		 */
		function cmf_get_category_exclusion_for_current_user_role() {
			$all_cats  = get_categories();
			$user_cats = $this->cmf_get_accessible_categories();

			// $exclusion = array_diff( $all_cats, $user_cats );
			return $all_cats;
		}


		/**
		 * Plugin page overview markup.
		 */
		function cmf_overview_markup() {

			$html  = '<div class="wrap overview">';
			$html .= '<h1>Overview</h1>';
			$html .= '<p><b>Cool Media Filter</b> lets you</p>';
			$html .= '<ul>';
			$html .= '<li><a href="' . admin_url( 'admin.php?page=cmf-new-user-role' ) . '"><i class="fa fa-plus-square"></i>&nbsp;&nbsp;Create New User Role</a> and set Role Permissions while creating the same.</li>';
			$html .= '<li><a href="' . admin_url( 'admin.php?page=cmf-user-roles' ) . '"><i class="fa fa-users"></i>&nbsp;Manage Permissions</a> for Roles using a friendly AJAX based UI.</li>';
			$html .= '<li><a href="' . admin_url( 'admin.php?page=cmf-manage-category-access' ) . '"><i class="fa fa-file-photo-o"></i>&nbsp;&nbsp;Restrict Media Category Access</a> for different user roles from an one-page AJAX based UI.</li>';
			$html .= '<li><a href="' . admin_url( 'upload.php?mode=grid' ) . '"><i class="fa fa-filter"></i>&nbsp;&nbsp;Filter Media</a> by selecting a Category from Category dropdown.</li>';
			$html .= '<li><a href="' . admin_url( 'upload.php?mode=list' ) . '"><i class="fa fa-gear"></i>&nbsp;Assign or Remove</a> Category from selected media files as a Bulk Action.</li>';
			$html .= '</ul>';
			$html .= '<p><i class="fa fa-github"></i>&nbsp;<a target="_blank" href="https://github.com/emfluenceindia/cool-media-filter">https://github.com/emfluenceindia/cool-media-filter</a></p>';
			$html .= '</div>';

			echo $html;
		}

		/**
		 * Creates and returns a list of assignable caps when adding a role.
		 *
		 * @return array List of assignable caps
		 */
		function cmf_assignable_caps_list() {
			$caps = array();

			array_push(
				$caps,
				'read',
				'read_private_pages',
				'read_private_posts',
				'edit_users',
				'manage_options',
				'edit_posts',
				'edit_others_posts',
				'publish_posts',
				'edit_pages',
				'edit_others_pages',
				'publish_pages',
				'list_users',
				'create_users',
				'upload_files'
			);

			return $caps;
		}

		/**
		 *
		 */
		function list_user_roles() {
			echo '<div class="wrap"><h1>Roles and Permissions</h1></div>';
			$roles = $this->cmf_get_all_roles();

			foreach ( $roles as $role ) {
				if ( strtolower( $role['name'] ) === 'administrator' ) {
					continue;
				} ?>
				<div class="user_role_update_list">
					<h3><?php echo $role['name']; ?></h3>
					<?php
					$role_slug = sanitize_title_with_dashes( $role['name'] );
					// Get assignable caps
					$caps = $this->cmf_assignable_caps_list();
					?>
					<div>
					<!-- Iterate through roles -->
					<?php
					foreach ( $roles as $key => $value ) {
						if ( 'administrator' === $key || $role_slug !== $key ) {
							continue;
						}
						?>

						<input type="hidden" class="role_key" value="<?php echo $key; ?>" />

						<?php
						// Get array of caps for this role.
						$caps_by_role = $value['capabilities'];
						$arr_per_role_cap = array();
						foreach ( $caps_by_role as $role_cap_key => $role_cap_value ) {
							// Put each cap_value in another array to be used for comparison
							array_push( $arr_per_role_cap, $role_cap_key );
						}

						foreach ( $caps as $cap ) {
							// iterate thorugh all assignable caps.
							// Inner loop to iterate through all available caps for current role.
							// Check if current $arr_per_role_cap has the $cap in it. If yes, auto check the caps checkbox.
							?>

							<p><input class="single-cap" 
							<?php
							if ( in_array( $cap, $arr_per_role_cap ) ) {
								echo 'checked'; }
?>
 type="checkbox" 
	<?php
	if ( 'read' === $cap ) {
								echo 'disabled'; }
?>
 id="<?php echo $cap . '_' . $role_slug; ?>" name="<?php echo $cap; ?>">
										<span><label style="text-transform: capitalize;" for="<?php echo $cap . '_' . $role_slug; ?>">
										<?php echo str_replace( '_', ' ', $cap ); ?>
									</label></span></p>
						<?php
						}
					}
					?>
					</div>
					<input type="button" onclick="cmf_UpdateRoleCaps( this );" class="button button-primary" value="Update Permission" />
				</div>
			<?php
			}
		}

		function cmf_display_notice( $notice ) {
		?>
			<div class="error notice add-role-page-notice">
				<?php echo $notice; ?>
			</div>
		<?php
		}

		/**
		 * Creates and returns an array of default caps to be used in Add Role page UI.
		 *
		 * @return array
		 */
		function cmf_default_role_caps() {
			$default_caps = array(
				'read_private_posts' => __( 'Read Private Posts' ),
				'read_private_pages' => __( 'Read Private Pages' ),
				'edit_pages'         => __( 'Edit Pages' ),
				'edit_others_pages'  => __( 'Edit Others Pages'),
				'publish_pages'      => __( 'Publish Pages' ),
				'publish_posts'      => __( 'Publish Posts' ),
				'edit_posts'         => __( 'Edit Posts' ),
				'edit_others_posts'  => __( 'Edit Others Posts' ),
				'list_users'         => __( 'List Users' ),
				'edit_users'         => __( 'Edit Users' ),
				'create_users'       => __( 'Create Users' ),
				'manage_options'     => __( 'Manage Options' ),
			);

			return $default_caps;
		}

		/**
		 * Look into: https://wordpress.org/plugins/capability-manager-enhanced/
		 */
		function cmf_add_user_role_markup() {
			?>
			<div class="wrap">
				<h1>Add Role</h1>
				<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
					<table class="form-table">
						<tr class="first">
							<th>Role name</th>
							<td><input type="text" id="coolmedia_role_name" name="coolmedia_role_name" placeholder="Example: News Editor" /></td>
						</tr>
						<tr>
							<th>Capabilities</th>
							<td>
								<div></div>
								<ul>
									<li>
										<input type="checkbox" id="read" name="read" disabled="disabled" checked="checked">
										<label for="read">Read</label>
									</li>
									<?php
									$default_caps = $this->cmf_default_role_caps();
									foreach( $default_caps as $key => $value ) { ?>
										<li>
											<input type="checkbox" id="<?php echo $key ?>" name="<?php echo $key ?>">
											<label for="<?php echo $key ?>"><?php echo $value; ?></label>
										</li>
									<?php }
									?>
								</ul>
							</td>
						</tr>
						<tr>
							<th>
								<?php wp_nonce_field( 'coolmedia-role-save', 'coolmedia-role-nonce' ); ?>
								<input type="hidden" name="action" value="new_user_role">
							</th>
							<td><?php submit_button( 'Save Role' ); ?></td>
						</tr>
					</table>
				</form>
			</div>
		<?php
		}


		/**
		 * Validates nonce on Role add sceeen.
		 *
		 * @return bool|false|int
		 */
		function cmf_role_nonce_is_valid() {
			if ( ! isset( $_POST['coolmedia-role-nonce'] ) ) {
				return false;
			}

			$field  = sanitize_text_field( wp_unslash( $_POST['coolmedia-role-nonce'] ) );
			$action = 'coolmedia-role-save';

			return wp_verify_nonce( $field, $action );
		}

		/**
		 * http://unitedwebsoft.in/blog/wordpress-create-custom-role-and-capability-grant-access-to-only-our-custom-admin-menu/
		 * https://code.tutsplus.com/tutorials/creating-custom-admin-pages-in-wordpress-2--cms-26926
		 * https://code.tutsplus.com/tutorials/creating-custom-admin-pages-in-wordpress-3--cms-27017
		 * https://premium.wpmudev.org/blog/handling-form-submissions/
		 * http://www.bethedev.com/2016/12/insert-data-in-database-using-form-in.html
		 */

		/**
		 * Save new User Role
		 *
		 * @return WP_Error
		 */

		function cmf_save_user_role() {

			if ( ! ( $this->cmf_role_nonce_is_valid() && current_user_can( 'manage_options' ) ) ) {
				// Error
				return new WP_Error( 'You are not authorized to perform this operation' );
			}

			// Is role slug set?
			if ( ! isset( $_POST['coolmedia_role_name'] ) || empty( $_POST['coolmedia_role_name'] ) ) {
				// Error. Role name not supplied
				$notice = 'Role name was not supplied';
				update_option( 'coolmedia_role_add_error_message', $notice, 'no' );

				wp_redirect( admin_url( 'admin.php?page=cmf-new-user-role' ) );

			} else {

				// Does the role already exist?
				// https://docs.ultimatemember.com/article/164-getrole
				$role_name = sanitize_text_field( wp_unslash( $_POST['coolmedia_role_name'] ) );
				$role_slug = sanitize_title_with_dashes( $role_name );
				$role      = get_role( $role_slug );

				if ( ! empty( $role ) ) {
					// Error. Role already exists
					$notice = 'Role "' . $role_name . '" already exists';
					update_option( 'coolmedia_role_add_error_message', $notice, 'no' );

					wp_redirect( admin_url( 'admin.php?page=cmf-new-user-role' ) );
				} else {
					// Safe to create the new role
					$role_caps = array(
						'read' => true,
					);

					$default_caps = $this->cmf_default_role_caps();
					foreach( $default_caps as $key => $value ) {
						if( isset( $_POST[ $key ] ) && ! empty( $_POST[ $key ] ) ) {
							$role_caps[ $key ] = true;
						}
					}

					add_role( $role_slug, $role_name, $role_caps );

					// Redirect back to form page
					wp_redirect( admin_url( 'admin.php?page=cmf-user-roles' ) );
				}
			}
		}

		function cmf_maybe_display_notice() {
			$notice = get_option( 'coolmedia_role_add_error_message', false );

			if ( $notice ) {
				delete_option( 'coolmedia_role_add_error_message' );
				$this->cmf_display_notice( $notice );
			}
		}

		function cmf_get_category_id_array() {
			$cats    = $this->cmf_get_all_categories();
			$cat_ids = array();

			foreach ( $cats as $cat ) {
				array_push( $cat_ids, $cat->term_id );
			}

			return $cat_ids;
		}

		/**
		 *
		 */
		function cmf_get_cleaned_up_category_ids_by_user_role() {
			$all_cats = $this->cmf_get_category_id_array();
		}


		/**
		 * https://wordpress.stackexchange.com/questions/1482/restricting-users-to-view-only-media-library-items-they-have-uploaded
		 */

		/**
		 * Markup for Restricts category access by role.
		 */
		function cmf_restrict_category_access_by_role() {
		?>
			<div class="wrap">
				<h1>Media Access Restriction</h1>

			<?php
			$cats  = $this->cmf_get_all_categories();
			$roles = $this->cmf_get_all_roles();

			// $ar = $this->cmf_get_category_id_array();
			$u = wp_get_current_user();
			$r = (array) $u->roles;

			$current_site = get_blog_details();
			$site_id      = $current_site->id;

			foreach ( $roles as $role ) {
				if ( strtolower( $role['name'] ) == 'administrator' ) { // Administrator has full access. We skip this
					continue;
				}
				?>
				<div class="access-box">
					<input type="hidden" class="hidden_role" value="<?php echo sanitize_title_with_dashes( $role['name'] ); ?>" />
					<input type="hidden" class="site_id" value="<?php echo $site_id; ?>" />
					<h3 style="margin: 0 auto; font-weight: normal;">
						<?php echo $role['name']; ?>
					</h3>

					<?php foreach ( $cats as $cat ) { ?>
						<div class="category-list">
						<?php
						// Get records from wp_caegory_role table
						global $wpdb;
						$query = 'SELECT *
                        FROM wp_category_role WHERE site_id = ' . $site_id . " AND user_role = '" . sanitize_title_with_dashes( $role['name'] ) . "' AND cat_id = " . $cat->term_id;

						$result = $wpdb->get_results( $query, OBJECT );
						?>
							<input class="cat-check" 
							<?php
							if ( ! empty( $result ) ) {
								echo 'checked'; }
?>
 type="checkbox" value="<?php echo $cat->term_id; ?>" />&nbsp; <?php echo $cat->name; ?>
						</div>
					<?php } ?>

					<input type="button" onclick="cmf_updateAccess(this);" value="Update" class="access_update button button-primary" />

				</div>
			<?php } ?>

			</div>
		<?php
		}

		/**
		 * @param bool $editable_only
		 * @return mixed|void
		 */
		function cmf_get_all_roles( $editable_only = true ) {
			global $wp_roles;

			$all_roles = $wp_roles->roles;

			if ( ! $editable_only ) {
				return $all_roles;
			} else {
				$editable_roles = apply_filters( 'editable_roles', $all_roles );
				return $editable_roles;
			}
		}


		/**
		 * Gets all categories.
		 *
		 * @return array
		 */
		function cmf_get_all_categories() {
			$args = array(
				'hide_empty' => false,
				'taxonomy'   => $this->taxonomy,
				'orderby'    => 'name',
				'order'      => 'ASC',
			);

			$cats = get_categories( $args );

			return $cats;
		}

		/**
		 * Creates mapping table when plugin is installed.
		 */
		static function activate() {
			global $wpdb;
			$map_table = $wpdb->prefix . 'category_role';

			if ( $wpdb->get_var( "show tables like '$map_table'" ) != $map_table ) {

				$sql = 'CREATE TABLE ' . $map_table . ' (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `site_id` mediumint(9) NOT NULL,
                `user_role` mediumtext NOT NULL,
                `cat_id` mediumint(9) NOT NULL,
                UNIQUE KEY id ( id ));';

				require_once ABSPATH . '/wp-admin/includes/upgrade.php';

				dbDelta( $sql );
			}
		}

		static function deactivate() {

		}

		/**
		 * When uninstalled, remove all role-category map record from wp_role_category table
		 * Delete wp_role_category_table
		 * Reset all existing role capabilities similar to subscriber (?)... well, need a bit more thinking on this!
		 */
		static function uninstall() {

		}

	}
}

if ( class_exists( 'CoolMediaFilter' ) ) {
	$cool_media_filter = new CoolMediaFilter();
	$cool_media_filter->cmf_register();
}

require_once plugin_dir_path( __FILE__ ) . 'inc/class-coolmediafilter-plugin-actions.php';

// activate
register_activation_hook( __FILE__, array( 'CoolMediaFilter', 'activate' ) );

// deactivate
register_deactivation_hook( __FILE__, array( 'CoolMediaFilter', 'deactivate' ) );

// uninstall
register_uninstall_hook( __FILE__, array( 'CoolMediaFilter', 'uninstall' ) );
