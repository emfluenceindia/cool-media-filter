<?php
/**
 * Custom gallery shortcode.
 *
 * @package CoolMediaFilter
 */

/**
 * Custom gallery shortcode.
 *
 * @param string $result category.
 * @param array  $atts attachment.
 *
 * @return mixed
 */
function cmf_custom_gallery_shortcode( $result, $atts ) {
	if ( isset( $atts['category'] ) ) {
		$default_taxonomy = 'category';
		$default_taxonomy = apply_filters( 'emfl_mediacategory_taxonomy', $default_taxonomy );

		$category = $atts['category'];

		/**
		 * Check if $category is non-numeric.
		 * If yes, get term_id either by getting the category term or by getting the category object based on the value of $default_taxonomy
		 * If $category is already numeric, i.e. it is holding a term_id already the following code block would not execute.
		 */
		if ( ! is_numeric( $category ) ) {
			if ( 'category' !== $default_taxonomy ) {
				$term = get_term_by( 'slug', $category, $default_taxonomy );

				if ( false !== $term ) {
					$category = $term->term_id;
				} else {
					$category = '';
				}
			} else {
				$obj_category = get_category_by_slug( $category );
				if ( false !== $obj_category ) {
					$category = $obj_category->term_id;
				} else {
					$category = '';
				}
			}
		}

		/**
		 * Since we have a numeric value assigned against $category we will carry out further execution
		 * However, it is always safe to check whether $category is not empty beforehand.
		 */

		if ( '' !== $category ) {
			// Create a new empty array.
			$working_ids = array();

			/**
			 * We now check whether $default_taxonomy is 'category'.
			 * Based on that we shall prepare our argument since if it is not default category, we need to pass a tax_query parameter
			 */

			if ( 'category' !== $default_taxonomy ) {
				$args = array(
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'post_status' => null,
					'tax_query'   => array(
						array(
							'taxonomy' => $default_taxonomy,
							'field'    => 'id',
							'terms'    => $category,
						),
					),
				);
			} else {
				$args = array(
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'post_status' => null,
					'category'    => $category,
				);
			}

			/**
			* Add new property to $args to set attachment ID and fetch attachments for selected category and attached to POST ID
			*/

			if ( isset( $atts['id'] ) ) {
				if ( empty( $atts['id'] ) ) {
					$args['post_parent'] = get_the_ID();
				} else {
					$args['post_parent'] = $atts['id'];
				}
			}

			// get posts (attachments).
			$attachments = get_posts( $args );

			if ( ! empty( $attachments ) ) {
				// push each id in new $working_ids array.
				if ( isset( $atts['ids'] ) ) {
					$ids_present = explode( ',', $atts['ids'] );
					foreach ( $attachments as $attachment ) {
						if( in_array( $attachment->ID, $ids_present ) ) {
							$working_ids[] = $attachment->ID;
						}
					}
				} else {
					foreach ( $attachments as $attachment ) {
						$working_ids[] = $attachment->ID;
					}
				}

				$atts['ids'] = $working_ids;
			} else {
				$atts['ids'] = -1; // empty category. media files won't be displayed.
			}
		}

		if ( isset( $atts['ids'] ) ) {
			$result['include'] = implode( ',', $atts['ids'] );
		}

		$result['category'] = $atts['category'];
	}

	return $result;
}