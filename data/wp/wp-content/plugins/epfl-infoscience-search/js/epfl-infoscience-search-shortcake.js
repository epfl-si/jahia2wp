jQuery( document ).ready( function( $ ) {
    function set_toggle_event() {
    }

    if ( 'undefined' !== typeof( wp.shortcake ) ) {
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_edit', function(shortcodeModel) {
            set_toggle_event();
        } );
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_new', function() {
            set_toggle_event();
        } );
        /*
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_destroy', function() {
        } );

        wp.shortcake.hooks.addAction( 'shortcode-ui.render_closed', function() {
        } );
        */
    }
});
