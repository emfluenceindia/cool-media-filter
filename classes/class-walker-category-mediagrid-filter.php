<?php

/**
 * @package: CoolMediaFilter
 */

class WalkerCategoryMediaGridFilter extends Walker_CategoryDropdown {
    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
        $pad = str_repeat( '&nbsp;', $depth * 3 );

        $cat_name = apply_filters( 'list_cats', $category->name, $category );

        // {"term_id":"1","term_name":"no category"}
        $output .= ',{"term_id":"' . $category->term_id . '",';

        $output .= '"term_name":"' . $pad . esc_attr( $cat_name );
        if ( $args['show_count'] ) {
            $output .= '&nbsp;&nbsp;('. $category->count .')';
        }
        $output .= '"}';
    }
}