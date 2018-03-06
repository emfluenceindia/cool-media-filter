//Purpose is to update caps for present role
//First remove all available caps from selected role and add selected caps to it

function updateRoleCaps( obj ) {
    //Get the values...
    var container = jQuery( obj ).closest( ".user_role_update_list" );
    var roleKey = jQuery( container ).find( ".role_key" ).val();
    //alert( jQuery( container ).attr( "class" ) );
    //alert( roleKey );

    var selectedCaps = "";
    jQuery ( container ).find( ".single-cap" ).each( function() {
        if( jQuery( this ).is( ":checked" ) ) {
            selectedCaps+= jQuery( this ).attr( "name" ) + ",";
            //alert( jQuery( this ).attr( "name" ) );
        }
    } );

    jQuery.ajax({
        type: 'POST',
        url: role_permission_ajax.url,
        data: {
            action: 'role_permission',
            role_key: roleKey,
            new_caps: selectedCaps,
        },
        success: function( result ) {
            //alert( selectedCaps );
            alert( result );
        }
    });

    //alert( selectedCaps );
}