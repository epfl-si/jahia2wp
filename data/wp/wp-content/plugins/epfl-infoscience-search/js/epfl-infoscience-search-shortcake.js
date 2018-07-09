jQuery( document ).ready( function( $ ) {
    function set_group_by_dynamic() {
        var group_by = $("select[name='group_by']");
        var all_options = $("select[name='group_by'] option");
        var group_by2 = $("select[name='group_by2']");
        var group_by2_label = $("label[for='" + $(group_by2).attr('id') + "']");

        var default_values = [];

        $(all_options).each(function() {
            default_values.push([this.value, this.text]);
        });

        var group_by2_selected = $(group_by2).val();

        var selected = $(group_by).find(':selected').val();

		if (selected.indexOf('year') != -1) {
            $(group_by2).html("");
            $(default_values).each(function(i, v){
                if (v[0] != 'year') {
                    $(group_by2).append($("<option>", { value: v[0], html: v[1] }));                        
                }
            });
            
            $(group_by2).prop('disabled', false);
            $(group_by2_label).css("color", 'black');
            $(group_by2).val(group_by2_selected);
		} else if (selected.indexOf('doctype') != -1) {
            $(group_by2).html("");
            $(default_values).each(function(i, v){
                if (v[0] != 'doctype') {
                    $(group_by2).append($("<option>", { value: v[0], html: v[1] }));
                }
            });
            $(group_by2).prop('disabled', false);
            $(group_by2_label).css("color", 'black');
            $(group_by2).val(group_by2_selected);
		} else {
            $(group_by2).html("");
            $(group_by2).append($("<option>", { value: '', html: ' ' }));
			$(group_by2).prop('disabled', true);
            $(group_by2_label).css("color", 'grey');
		}
    }

    if ( 'undefined' !== typeof( wp.shortcake ) ) {
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_edit', function(shortcodeModel) {
            set_group_by_dynamic();

            var group_by = $("select[name='group_by']");

            group_by.on('change', function (e) {
                set_group_by_dynamic();
            });
        } );
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_new', function() {
            set_group_by_dynamic();
            set_group_by_dynamic();

            var group_by = $("select[name='group_by']");

            group_by.on('change', function (e) {
                set_group_by_dynamic();
            });

        } );
        /*
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_destroy', function() {
        } );

        wp.shortcake.hooks.addAction( 'shortcode-ui.render_closed', function() {
        } );
        */
    }
});
