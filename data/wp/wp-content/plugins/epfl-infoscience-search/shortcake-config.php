<?php

require_once 'utils.php';

Class InfoscienceSearchShortCakeConfig
{   
    private static function get_field_options() 
    {
        return array (
            array('value' => 'any', 'label' => esc_html__('Any field', 'epfl-infoscience-search')),
            array('value' => 'author', 'label' => esc_html__('Author', 'epfl-infoscience-search')),
            array('value' => 'title', 'label' => esc_html__('Title', 'epfl-infoscience-search')),
            array('value' => 'year', 'label' => esc_html__('Year', 'epfl-infoscience-search')),
            array('value' => 'unit', 'label' => esc_html__('Unit', 'epfl-infoscience-search')),
            array('value' => 'collection', 'label' => esc_html__('Collection', 'epfl-infoscience-search')),
            array('value' => 'journal', 'label' => esc_html__('Journal', 'epfl-infoscience-search')),
            array('value' => 'summary', 'label' => esc_html__('Summary', 'epfl-infoscience-search')),
            array('value' => 'keyword', 'label' => esc_html__('Keyword', 'epfl-infoscience-search')),
            array('value' => 'issn', 'label' => esc_html__('ISSN', 'epfl-infoscience-search')),
            array('value' => 'doi', 'label' => esc_html__('DOI', 'epfl-infoscience-search')),
        );
    }

    private static function get_limit_options() 
    {
        return array (
            array('value' => '10', 'label' => '10'),
            array('value' => '25', 'label' => '25'), 
            array('value' => '50', 'label' => '50'),
            array('value' => '100', 'label' => '100'),
            array('value' => '250', 'label' => '250'),
            array('value' => '500', 'label' => '500'),
            array('value' => '1000', 'label' => '1000'),
        );
    }

    private static function get_sort_options()
    {
        return array (
            array('value' => 'desc', 'label' => esc_html__('Descending', 'epfl-infoscience-search')),
            array('value' => 'asc', 'label' => esc_html__('Ascending', 'epfl-infoscience-search')),
        );
    }
    private static function get_operator_options()
    {
        return array (
            array('value' => 'and', 'label' => esc_html__('AND', 'epfl-infoscience-search')),
            array('value' => 'or', 'label' => esc_html__('OR', 'epfl-infoscience-search')),
            array('value' => 'and_not', 'label' => esc_html__('AND NOT', 'epfl-infoscience-search')),
       );
    }

    private static function get_format_options() 
    {
        return array (
            array('value' => 'short', 'label' => esc_html__('Short', 'epfl-infoscience-search')),
            array('value' => 'detailed', 'label' => esc_html__('Detailed', 'epfl-infoscience-search')),
       );
    }

    private static function get_summary_options() 
    {
        return array (
            array('value' => 'false', 'label' => esc_html__('Hide', 'epfl-infoscience-search')),
            array('value' => 'true', 'label' => esc_html__('Show', 'epfl-infoscience-search')),
       );
    }    

    private static function get_thumbnail_options() 
    {
        return array (
            array('value' => 'false', 'label' => esc_html__('Hide', 'epfl-infoscience-search')),
            array('value' => 'true', 'label' => esc_html__('Show', 'epfl-infoscience-search')),
       );
    }    

    private static function get_group_by_options() 
    {
        return array (
            array('value' => '', 'label' => ''),
            array('value' => 'year', 'label' => esc_html__('year as title', 'epfl-infoscience-search')),
            array('value' => 'doctype', 'label' => esc_html__('document type as title', 'epfl-infoscience-search')),
       );
    }

    public static function shortcode_ui_fields( $fields ) {
        # taken from https://github.com/humanmade/protected-embeds/blob/master/protected-embeds.php
        $fields['epfl-text'] = array(
            'template' => 'epfl-shortcode-ui-field',
        );
        $fields['epfl-checkbox'] = array(
            'template' => 'epfl-shortcode-ui-field-checkbox',
        );
        $fields['epfl-select'] = array(
            'template' => 'epfl-shortcode-ui-field-select',
        );
        $fields['epfl-radio'] = array(
            'template' => 'epfl-shortcode-ui-field-radio',
        );        
        return $fields;
    }

    public static function shortcode_ui_epfl_field_template() {
        # taken from https://github.com/humanmade/protected-embeds/blob/master/protected-embeds.php
        //@formatter:off
        ?>
    <script type="text/html" id="tmpl-epfl-shortcode-ui-field">
        <# if (data.title) { #>
            <# if ( 'true' == data.is_toggle ){ #> 
                <h3 class="infoscience_search_toggle_header"><a href="#">[-] {{ data.title }}</a></h3>
            <# } else { #>
                <h2>{{ data.title }}</h2>
            <# } #>
        <# } #>
        <div class="field-block epfl-shortcode-ui-field shortcode-ui-attribute-{{ data.attr }}">
            <label for="{{ data.attr }}">{{{ data.label }}}</label>
            <input type="text" class="regular-text" name="{{ data.attr }}" id="{{ data.id }}" value="{{ data.value }}" {{{ data.meta }}} />
            <# if ( typeof data.description == 'string' ) { #>
                        <p class="description">{{{ data.description }}}</p>
            <# } #>
        </div>
    </script>

    <script type="text/html" id="tmpl-epfl-shortcode-ui-field-checkbox">
        <# if (data.title) { #>
            <# if ( 'true' == data.is_toggle ){ #> 
                <h3 class="infoscience_search_toggle_header"><a href="#">[-] {{ data.title }}</a></h3>
            <# } else { #>
                <h2>{{ data.title }}</h2>
            <# } #>
        <# } #>
        <div class="field-block epfl-shortcode-ui-field-checkbox shortcode-ui-attribute-{{ data.attr }}">
                <label>{{{ data.label }}}<br><input type="checkbox" name="{{ data.attr }}" id="{{ data.id }}" value="{{ data.value }}" <# if ( 'true' == data.value ){ print('checked'); } #>></label>
            <# if ( typeof data.description == 'string' && data.description.length ) { #>
                <span>{{{ data.description }}}</span>
            <# } #>
        </div>
    </script>

    <script type="text/html" id="tmpl-epfl-shortcode-ui-field-select">
        <# if (data.title) { #>
            <# if ( 'true' == data.is_toggle ){ #> 
                <h3 class="infoscience_search_toggle_header"><a href="#">[-] {{ data.title }}</a></h3>
            <# } else { #>
                <h2>{{ data.title }}</h2>
            <# } #>
        <# } #>
	<div class="field-block epfl-shortcode-ui-field-select shortcode-ui-attribute-{{ data.attr }}">
		<label for="{{ data.id }}">{{{ data.label }}}</label>
		<select name="{{ data.attr }}" id="{{ data.id }}" {{{ data.meta }}}>
			<# _.each( data.options, function( option ) { #>

				<# if ( 'options' in option && 'label' in option ) { #>
					<optgroup label="{{ option.label }}">
						<# _.each( option.options, function( optgroupOption ) { #>
							<option value="{{ optgroupOption.value }}" <# if ( _.contains( _.isArray( data.value ) ? data.value : data.value.split(','), optgroupOption.value ) ) { print('selected'); } #>>{{ optgroupOption.label }}</option>
						<# }); #>
					</optgroup>
				<# } else { #>
					<option value="{{ option.value }}" <# if ( _.contains( _.isArray( data.value ) ? data.value : data.value.split(','), option.value ) ) { print('selected'); } #>>{{ option.label }}</option>
				<# } #>

			<# }); #>
		</select>
		<# if ( typeof data.description == 'string' && data.description.length ) { #>
			<p class="description">{{{ data.description }}}</p>
		<# } #>
	</div>
    </script>  

    <script type="text/html" id="tmpl-epfl-shortcode-ui-field-radio">
        <# if (data.title) { #>
            <# if ( 'true' == data.is_toggle ){ #> 
                <h3 class="infoscience_search_toggle_header"><a href="#">[-] {{ data.title }}</a></h3>
            <# } else { #>
                <h2>{{ data.title }}</h2>
            <# } #>
        <# } #>
        <div class="field-block epfl-shortcode-ui-field-radio shortcode-ui-attribute-{{ data.attr }}">
            <label>{{{ data.label }}}</label>
            <# _.each( data.options, function( option ) { #>
                <label>
                    <input type="radio" name="{{ data.attr }}" value="{{ option.value }}" <# if ( option.value == data.value ) { print('checked'); } #> />
                    {{ option.label }}
                </label>
            <# }); #>
            <# if ( typeof data.description == 'string' && data.description.length ) { #>
                <p class="description">{{{ data.description }}}</p>
            <# } #>
        </div>
    </script>
        <?php
        //@formatter:on
    }
    
    public static function load_epfl_infoscience_search_wp_admin_style($hook) {
        wp_enqueue_style('epfl-infoscience-search-shortcake-style.css', plugins_url('css/epfl-infoscience-search-shortcake-style.css', __FILE__));
    }

    public static function load_epfl_infoscience_search_wp_admin_js($hook) {
        wp_enqueue_script( 'epfl-infoscience-search-shortcake-javascript', plugin_dir_url( __FILE__ ) . 'js/epfl-infoscience-search-shortcake.js', array( 'shortcode-ui' ) );
    }    

    public static function config() 
    {
        if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

            # add custom epfl style
            add_filter( 'shortcode_ui_fields', array('InfoscienceSearchShortCakeConfig', 'shortcode_ui_fields'));
            add_action( 'admin_enqueue_scripts', ['InfoscienceSearchShortCakeConfig', 'load_epfl_infoscience_search_wp_admin_style'], 99);
            add_action( 'enqueue_shortcode_ui', array('InfoscienceSearchShortCakeConfig', 'load_epfl_infoscience_search_wp_admin_js'));
            add_action( 'print_shortcode_ui_templates', array('InfoscienceSearchShortCakeConfig', 'shortcode_ui_epfl_field_template'));
            
            $documentation_url = "https://infoscience.epfl.ch/help/search-tips?ln=en";
    
            $pattern_description = sprintf(
                esc_html__('%sSearch tips%s', 'epfl-infoscience-search'),
                '<a target="_blank" href="' . $documentation_url . '">', '</a>'
            );

            $thumbnails_description = esc_html__('Show illustration', 'epfl-infoscience-search');

            shortcode_ui_register_for_shortcode(
                'epfl_infoscience_search',
                array(
                    'label' => __('Add Infoscience search shortcode', 'epfl-infoscience-search'),
                    'listItemImage' => '<img src="' . plugins_url( 'img/infoscience-loupe.svg', __FILE__ ) . '" >',
                    'attrs'         => array(
                        # Content
                        array(
                            'title'         => __('Search for', 'epfl-infoscience-search'),
                            'attr'          => 'pattern',
                            'type'          => 'epfl-text',
                            'description'   => $pattern_description,
                            'meta'        => array(
                                'placeholder' => esc_html__('Search records for:'),
                            ),
                        ),
                        array(
                            'label'         => esc_html__('Field restriction', 'epfl-infoscience-search'),
                            'attr'          => 'field',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                        ),
                        /* TODO?:
                        array(
                            'label'         => esc_html__('Collection', 'epfl-infoscience-search'),
                            'attr'          => 'collection',
                            'type'          => 'epfl-text',
                            'meta'        => array(
                                'placeholder' => 'Infoscience/Research',
                            ),                            
                        ),*/
                        # Advanced content, dynamically hidden with javascript
                        array(
                            'title'         => esc_html__('Additional search keys', 'epfl-infoscience-search'),
                            'label'         => esc_html__('Second search text'),
                            'attr'          => 'operator2',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_operator_options(),
                            'is_toggle'     => 'true',
                        ),
                        array(
                            'attr'          => 'field2',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                        ),
                        array(
                            'attr'          => 'pattern2',
                            'type'          => 'epfl-text',
                            'meta'        => array(
                                'placeholder' => 'Search key',
                            ),  
                        ),
                        array(
                            'label'         => esc_html__('Third search text'),
                            'attr'          => 'operator3',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_operator_options(),
                        ),
                        array(
                            'attr'          => 'field3',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                        ),                        
                        array(
                            'attr'          => 'pattern3',
                            'type'          => 'epfl-text',
                            'meta'        => array(
                                'placeholder' => 'Search key',
                            ),
                        ),

                        # Presentation
                        array(
                            'title'         => esc_html__('Style', 'epfl-infoscience-search'),
                            'label'         => esc_html__('Format', 'epfl-infoscience-search'),
                            'attr'          => 'format',
                            'type'          => 'epfl-radio',
                            'options'       => InfoscienceSearchShortCakeConfig::get_format_options(),
                            'description'   => __('Detail level for a publication', 'epfl-infoscience-search'),
                            'value'         => 'short',
                        ),
                        array(
                            'label'         => esc_html__('Summary', 'epfl-infoscience-search'),
                            'attr'          => 'summary',
                            'type'          => 'epfl-radio',
                            'options'       => InfoscienceSearchShortCakeConfig::get_summary_options(),
                            'value' => 'false',
                        ),                        
                        array(
                            'label'         => esc_html__('Thumbnail', 'epfl-infoscience-search'),
                            'attr'          => 'thumbnail',
                            'type'          => 'epfl-radio',
                            'options'       => InfoscienceSearchShortCakeConfig::get_thumbnail_options(),
                            'value' => 'true',
                        ),
                        array(
                            'label'         => esc_html__('Sort', 'epfl-infoscience-search'),
                            'attr'          => 'sort',
                            'type'          => 'epfl-radio',
                            'options'       => InfoscienceSearchShortCakeConfig::get_sort_options(),
                            'value'         => 'desc',
                        ),                        
                        array(
                            'label'         => esc_html__('Group by', 'epfl-infoscience-search') . ' (1)',
                            'attr'          => 'group_by',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_group_by_options(),
                        ),
                        array(
                            'label'         => esc_html__('Group by', 'epfl-infoscience-search') . ' (2)',
                            'attr'          => 'group_by2',
                            'type'          => 'epfl-select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_group_by_options(),
                        ),
                    ),
    
                    'post_type'     => array( 'post', 'page' ),
                )
            );
        endif;
    }
}
?>
