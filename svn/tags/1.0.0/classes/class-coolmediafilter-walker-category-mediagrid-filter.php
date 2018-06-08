<?php

/**
 * Handle formatting of category filter dropdown options for list view.
 *
 * @package: CoolMediaFilter
 */
class CoolMediaFilterWalkerCategoryMediaGridFilter extends Walker_CategoryDropdown {
	/**
	 * Handle formatting of category filter dropdown options for list view.
	 *
	 * @param string $output HTML output of dropdown options.
	 * @param object $category Taxonomy object.
	 * @param int    $depth Default 0.
	 * @param array  $args Category filter argument. Default empty array.
	 * @param int    $id Category id. Default 0.
	 */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$pad      = str_repeat( '&nbsp;', $depth * 3 );
		$cat_name = apply_filters( 'list_cats', $category->name, $category );

		$output .= ',{"term_id":"' . $category->term_id . '",';

		$output .= '"term_name":"' . $pad . esc_attr( $cat_name );
		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;(' . $category->count . ')';
		}

		$output .= '"}';
	}
}