<?php

/**
 * @package: CoolMediaFilter
 */

class WalkerMediaTaxonomyCheckList extends Walker {
    var $tree_type = 'category';
    var $db_fields = array(
        'parent' => 'parent',
        'id'     => 'term_id'
    );

    function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "$indent<ul class='children'>\n";
    }

    function end_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul>\n";
    }

    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
        extract( $args );

        // Default taxonomy
        $taxonomy = 'category';
        // Add filter to change the default taxonomy
        $taxonomy = apply_filters( 'cool_media_taxonomy', $taxonomy );

        $name = 'tax_input[' . $taxonomy . ']';

        $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
        $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="' . $name . '[' . $category->slug . ']" id="in-' . $taxonomy . '-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters( 'the_category', $category->name ) ) . '</label>';
    }

    function end_el( &$output, $category, $depth = 0, $args = array() ) {
        $output .= "</li>\n";
    }
}