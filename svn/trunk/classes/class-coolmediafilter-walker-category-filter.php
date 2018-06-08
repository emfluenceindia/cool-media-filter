<?php

/**
 * Handle formatting of category filter dropdown options for list view.
 *
 * @package: CoolMediaFilter
 */
class CoolMediaFilterWalkerCategoryFilter extends Walker_CategoryDropdown {
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

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = ( 'category' !== $category->taxonomy ? 'slug' : 'id' );
		}

		$value = ( 'slug' === $args['value'] ? $category->slug : $category->term_id );
		if ( 0 === $args['selected'] && isset( $_GET['category_media'] ) && '' !== $_GET['category_media'] ) {  // custom taxonomy
			$args['selected'] = $_GET['category_media'];
		}

		$output .= '<option class="level-' . $depth . '" value="' . $value . '"';
		if ( (string) $value === (string) $args['selected'] ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . $cat_name;
		if ( $args['show_count'] ) {
			$output .= '&nbsp;&nbsp;(' . $category->count . ')';
		}
		$output .= "</option>\n";
	}
}