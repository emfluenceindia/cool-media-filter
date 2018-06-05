/**
 * Manage role caps.
 *
 * @param obj
 */

function cmf_UpdateRoleCaps( obj ) {
    jQuery( obj ).val("Working...");
    jQuery( obj ).attr("disabled", "disabled");

    //Get the values...
    var container = jQuery( obj ).closest( ".user_role_update_list" );
    var roleKey = jQuery( container ).find( ".role_key" ).val();

    var selectedCaps = "";
    jQuery ( container ).find( ".single-cap" ).each( function() {
        if( jQuery( this ).is( ":checked" ) ) {
            selectedCaps = selectedCaps + jQuery( this ).attr( "name" ) + ",";
        }
    } );

    if( jQuery.trim(selectedCaps) != "" ) {
        selectedCaps = selectedCaps.slice( 0, -1 );
    }

    window.setTimeout(function(){
        jQuery.ajax({
            type: 'POST',
            url: role_permission_ajax.url,
            data: {
                action: 'role_permission',
                role_key: roleKey,
                new_caps: selectedCaps,
            },
            success: function( result ) {
                jQuery( obj ).val("Update Permission");
                jQuery( obj ).removeAttr("disabled");
    
            }
        });
    }, 2000);
}