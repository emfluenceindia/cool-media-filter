<?php

/**
 * @package: CoolMediaFilter
 */

class WalkerCategoryFilter extends Walker_CategoryDropdown {
    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
        $pad = str_repeat( '&nbsp;', $depth * 3 );
        $cat_name = apply_filters( 'list_cats', $category->name, $category );

        if( ! isset( $args['value'] ) ) {
            $args['value'] = ( $category->taxonomy != 'category' ? 'slug' : 'id' );
        }

        $value = ( $args['value']=='slug' ? $category->slug : $category->term_id );
        if ( 0 == $args['selected'] && isset( $_GET['category_media'] ) && '' != $_GET['category_media'] ) {  // custom taxonomy
            $args['selected'] = $_GET['category_media'];
        }

        $output .= '<option class="level-' . $depth . '" value="' . $value . '"';
        if ( (string) $value === (string) $args['selected'] ) {
            $output .= ' selected="selected"';
        }
        $output .= '>';
        $output .= $pad . $cat_name;
        if ( $args['show_count'] )
            $output .= '&nbsp;&nbsp;(' . $category->count . ')';

        $output .= "</option>\n";
    }
}