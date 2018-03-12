(function(){
    /**
     * Create a new MediaLibraryTaxonomyFilter we later will instantiate
     */
    var MediaLibraryCategoryFilter = wp.media.view.AttachmentFilters.extend({
        id: 'media-category-filter',

        createFilters: function() {
            var filters = {};
            // Formats the 'terms' we've included via wp_localize_script()
            _.each( MediaLibraryCategoryFilterOptions.terms || {}, function( value, index ) {
                //alert(value.term_id);
                filters[ value.term_id ] = {
                    text: value.name,
                    //value: value.term_id,
                    props: {
                        // The WP_Query var for the taxonomy
                        category: value.term_id,
                    }
                };
            });
            filters.all = {
                // Default label
                text:  'All categories',
                props: {
                    // The WP_Query var for the taxonomy
                    category: ''
                },
                priority: 10
            };
            this.filters = filters;
        }
    });
    /**
     * Extend and override wp.media.view.AttachmentsBrowser to include our new filter
     */
    var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
    wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
        createToolbar: function() {
            // Make sure to load the original toolbar
            AttachmentsBrowser.prototype.createToolbar.call( this );
            this.toolbar.set( 'MediaLibraryCategoryFilter', new MediaLibraryCategoryFilter({
                controller: this.controller,
                model:      this.collection.props,
                priority: -75
            }).render() );
        }
    });
})()