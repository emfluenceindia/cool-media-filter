function updateAccess( obj ) {
    var selectedCatIds = "";
    var accessBox =  jQuery( obj ).closest( '.access-box' );
    var roleSlug = jQuery( accessBox ).find(".hidden_role").attr("value");
    var catList = jQuery( obj ).closest( '.access-box' ).find( '.category-list' );
    jQuery( catList ).children().each( function() {
        if( jQuery(this).is(":checked") ) {
        	selectedCatIds += jQuery(this).val() + ",";
            /*alert( jQuery( obj ).attr("value") );
            alert( jQuery(this).val() );
            alert( roleSlug );*/
        }
    });

    var idLen = selectedCatIds.length;
    if( idLen > 0 ) {
    	selectedCatIds = selectedCatIds.substring(0, idLen - 1);
    }
    
    //alert( selectedCatIds );

    jQuery.ajax({
    	type: 'POST',
    	url: category_access_ajax.url, //wp_localize_script: 844
    	data: {
    		action: 'category_access', //wp_ajax_category_access: 71
    		user_role: roleSlug,
    		selected_cats: selectedCatIds,
    	},
    	success: function( result ) {
    		alert( result );
    	}
    });
}