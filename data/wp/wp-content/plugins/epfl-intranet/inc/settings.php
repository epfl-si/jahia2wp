<?php

namespace EPFL;

if (! defined('ABSPATH')) {
    die('Access denied.');
}


/**
 * A base class for settings.
 *
 * Uses the settings API rather than writing our own <form>s and validators
 * therefor.
 *
 * Encapsulates the slug and other batons to pass to various functions
 * of the WordPress API, as well as WordPress persistence and the
 * is_plugin_active_for_network() quirks thereof.
 *
 * Subclasses must define:
 *
 *     const SLUG
 *
 *     function hook()
 *        -> and should not forget to call parent::hook()
 *
 * @see
 * https://wordpress.stackexchange.com/questions/100023/settings-api-with-arrays-example
 * except that we use one wp_options row per setting, allowing for
 * straightforward integration with the "wp option update" CLI
 * functionality
 */

if (! class_exists('EPFL\SettingsBase')):
class SettingsBase
{
    public function hook()
    { 
    }


    /************************ Data concerns ***********************/

    /**
     * @return The current setting for $key
     */
    public function get ($key)
    {
        $optname = $this->option_name($key);
        if ( $this->is_network_version() ) {
            return get_site_option( $optname );
        } else {
            return get_option( $optname );
        }
    }

    /**
     * @return Update the current setting for $key
     */
    public function update ($key, $value)
    {
        $optname = $this->option_name($key);
        if ( $this->is_network_version() ) {
            return update_site_option( $optname );
        } else {
            return update_option( $optname , $value);
        }
    }

    /**
     * @returns Whether this plugin is currently network activated
     */
    var $_is_network_version = null;
    function is_network_version()
    {
        if ($this->_is_network_version === null) {
            if (! function_exists('is_plugin_active_for_network')) {
                require_once(ABSPATH . '/wp-admin/includes/plugin.php');
            }

            $this->_is_network_version = (bool) is_plugin_active_for_network(plugin_basename(__FILE__));
        }

        return $this->_is_network_version;
    }

    /****************** Settings page concerns *******************/

    /**
     * Standard rendering function for the settings form.
     *
     * Regurgitates every knob previously registered by add_settings_section(),
     * add_settings_field() etc.
     */
    function render ()
    {
        $title = $GLOBALS['title'];
        echo("<div class=\"wrap\">
        <h2>$title</h2>
        <form action=\"options.php\" method=\"POST\">\n");
        settings_fields( $this->option_group() );
        do_settings_sections( $this::SLUG );
        submit_button();
        echo "        </form>\n";
        echo("</div>");
    }

    function render_default_field_text ($args)
    {
        printf(
            '<input type="text" name="%1$s" id="%2$s" value="%3$s" class="regular-text" />',
            $this->option_name($args['key']),
            $args['key'],
            $args['value']
        );
        if ($args['help']) {
            echo '<br /><i>' . $args['help'] . '</i>';
        }
    }

    function render_default_field_select ($args)
    {
        printf(
            '<select name="%1$s" id="%2$s">',
            $this->option_name($args['key']),
            $args['key']
        );

        foreach ($args['options'] as $val => $title) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                $val,
                selected($val, $args['value'], false),
                $title
            );
        }
        print '</select>';
        if ($args['help']) {
            echo '<br /><i>' . $args['help'] . '</i>';
        }
    }

    function render_default_field_radio ($args)
    {
        foreach ($args['options'] as $val => $title) {
            printf(
                '<p><input type="radio" type="radio" id="%1$s" name="%1$s" value="%2$s" %3$s/>%4$s</p>',
                $this->option_name($args['key']),
                $val,
                checked($val, $args['value'], false),
                $title
            );
        }
        print '</select>';
        if ($args['help']) {
            echo '<br /><i>' . $args['help'] . '</i>';
        }
    }

    /*************** "Teach WP OO" concerns **********************/

    function option_name ($key)
    {
        if ($this->is_network_version()) {
            return "plugin:" . $this::SLUG . ":network:" . $key;
        } else {
            return "plugin:" . $this::SLUG . ":" . $key;
        }
    }

    function option_group()
    {
        return "plugin:" . $this::SLUG . ":group";
    }

    /**
     * Like WordPress' add_options_page(), only simpler
     *
     * Must be called exactly once in an overridden hook() method.
     *
     * - Ensure that add_options_page() is actually called in the admin_menu
     *   action, as it won't work from admin_init for some reason
     * - The render callback is always the "render" method
     */
    function add_options_page ($page_title, $menu_title, $capability)
    {
        $self = $this;
        add_action('admin_menu',
                   function() 
                       use ($self, $page_title, $menu_title, $capability) {
                       add_options_page(
                           $page_title, $menu_title, $capability,
                           $this::SLUG,
                           array($self, 'render'));
                   });
    }

    /**
     * Like WordPress' add_settings_section, only simpler
     *
     * @param $key The section name; recommended: make it start with 'section_'
     *             The render callback is simply the method named "render_$key"
     * @param $title The title
     */
    function add_settings_section ($key, $title)
    {
        add_settings_section(
            $key,                                       // ID
            $title,                                     // Title
            array($this, 'render_' . $key),             // print output
            $this::SLUG                                 // menu slug, see action_admin_menu()
        );
    }

    /**
     * Like WordPress' add_settings_field, only simpler
     *
     * @param $parent_section_key The parent section's key (i.e., the value
     #        that was passed as $key to ->add_settings_section())
     * @param $key The field name (same as the argument to ->get())
     * @param $title The title
     * @param options Like the last argument to WordPress'
     *        add_settings_field(), except 'value' need not be escaped
     *        and $options['key'] will automatically be set to $key
     *        (to be used in multi-purpose rendering methods)
     *
     * The render callback is the method named "render_field_$key", if
     * it exists, or "render_default_field_$options['type']".
     */
    function add_settings_field ($parent_section_key, $key, $title, $options)
    {
        $options['key'] = $key;

        if (array_key_exists('value', $options)) {
            $options['value'] = esc_attr($options['value']);
        } else {
            $options['value'] = esc_attr($this->get($key));
        }

        if (array_key_exists('type', $options) &&
            !method_exists($this, "render_field_$key")) {
            $render_method = "render_default_field_" . $options["type"];
        } else {
            $render_method = "render_field_$key";
        }
        add_settings_field(
            $this->option_name($key),                   // ID
            $title,                                     // Title
            array($this, $render_method),               // print output
            $this::SLUG,                                // menu slug, see action_admin_menu()
            $parent_section_key,                        // parent section
            $options
        );
    }

    /**
     * Like WordPress' register_setting, only simpler
     *
     * If a method called "sanitize_$key" exists, it is automagically
     * used as $args["sanitize_callback"] (unless that key is set
     * explicitly).
     */
    function register_setting ($key, $args)
    {
        if ( (! array_key_exists("sanitize_callback", $args)) and
             method_exists($this, "sanitize_$key") ) {
            $args["sanitize_callback"] = array($this, "sanitize_$key");
        }
        register_setting(
            $this->option_group(),
            $this->option_name($key),
            $args);
    }

    /**
     * Like WordPress' add_settings_error, only simpler
     */
    function add_settings_error( $key, $msg )
    {           
        add_settings_error(
            $this->option_group(),
            'number-too-low',
            'Number must be between 1 and 1000.'
        );
    }
}
endif;
