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

    function get_additional_search_elements() {
        var additional_search_elements_start = $('.infoscience_search_toggle_header').parent('div');
        var additional_search_elements_end = $('.shortcode-ui-attribute-format').parent('div');
        var additional_search_elements = $(additional_search_elements_start).nextUntil($(additional_search_elements_end));
        
        // add the operator field
        additional_search_elements = additional_search_elements.add($(additional_search_elements_start).find('.shortcode-ui-attribute-operator2'));
        return additional_search_elements;
    }

    function toggle_title_content() {
        var title_to_toggle = $('.infoscience_search_toggle_header');

        $(get_additional_search_elements()).toggle();

        if ($(get_additional_search_elements()).first().is(":visible"))
        {
            $(title_to_toggle).find('a').text($(title_to_toggle).find('a').text().replace('[+]', '[-]'));
        } else {
            $(title_to_toggle).find('a').text($(title_to_toggle).find('a').text().replace('[-]', '[+]'));
            
        }
    }

    function set_toggle_title() {
        var title_to_toggle = $('.infoscience_search_toggle_header');
        $(title_to_toggle).click(function(){ toggle_title_content(); return false; });
        // set hidden or show, if pattern2 or pattern3 is set
        if (!($('input[name="pattern2"]').val() || $('input[name="pattern3"]').val())) {
            toggle_title_content();
        }
    }

    if ( 'undefined' !== typeof( wp.shortcake ) ) {
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_edit', function(shortcodeModel) {
            // are we in an infoscience shortcake ?
            if ($(".shortcode-ui-edit-epfl_infoscience_search")[0]){
                // fix Tinymce being applied on #inner_content
                $("#inner_content").attr("id", "inner_pas_content");
                // Set group by
                set_group_by_dynamic();

                var group_by = $("select[name='group_by']");

                group_by.on('change', function (e) {
                    set_group_by_dynamic();
                });

                set_toggle_title();
            }
        } );
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_new', function() {
            if ($(".shortcode-ui-edit-epfl_infoscience_search")[0]){
                // fix Tinymce being applied on #inner_content
                $("#inner_content").attr("id", "inner_pas_content");
                set_group_by_dynamic();

                var group_by = $("select[name='group_by']");

                group_by.on('change', function (e) {
                    set_group_by_dynamic();
                });

                set_toggle_title();
            }
        } );
        /*
        Other lifecycle, uncomment if needed
        
        wp.shortcake.hooks.addAction( 'shortcode-ui.render_destroy', function() {
        } );

        wp.shortcake.hooks.addAction( 'shortcode-ui.render_closed', function() {
        } );
        */
    }
});
