<?php
/**
 * Model and controller for fields that *may not* be edited by users.
 *
 * The TL;DR of this module is that it manages the @link
 * is_protected_meta filter in a persistent way (using WordPress
 * options). Caller controller code may "blacklist" any and all meta
 * fields to prevent them from appearing in the "Custom Fields" box at
 * the bottom of the edit menu. A replacement meta box is provided to
 * display the blacklisted meta fields in a read-only table.
 */
namespace EPFL\AutoFields;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

require_once(__DIR__ . '/i18n.php');
use function EPFL\I18N\___;

require_once(__DIR__ . '/this-plugin.php');
use function EPFL\ThisPlugin\on_deactivate;

/**
 * Auto fields of a model class.
 *
 * An instance represents the auto fields associated to one model
 * class. Whenever some code non-interactively updates attributes, it
 * ought to call `AutoFields::of($model_class)->append($fields)`.
 * The AutoFieldsController makes sure to make these fields un-editable.
 */

class AutoFields
{
    private function __construct ($for_class)
    {
        $this->model_class = $for_class;
    }

    /**
     * Named constructor idiom.
     */
    static function of ($for_class) {
        $thisclass = get_called_class();
        return new $thisclass($for_class);
    }

    function get ()
    {
        $auto_fields = get_option($this->_get_option_key());
        if (! $auto_fields) { $auto_fields = []; }
        sort($auto_fields);
        return $auto_fields;
    }

    /**
     * Mark these fields as protected
     */
    function append ($fields)
    {
        $protected = $this->get();
        $changed = false;
        if (! is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $field) {
            if (false === array_search($field, $protected)) {
                array_push($protected, $field);
                $changed = true;
            }
        }
        if ($changed) {
            update_option($this->_get_option_key(), $protected);
        }
    }

    function clear () {
        delete_option($this->_get_option_key());
    }

    const SLUG = "epfl_ws_protected_fields";

    private function _get_option_key () {
        return static::SLUG . "_" . sanitize_key($this->model_class);
    }
}

/**
 * Controller helper class for the admin area of classes that have AutoFields.
 *
 * An instance of this class deals with one specific model class, and
 * arrange for its auto-fields to be shown read-only in the wp-admin
 * edit pages for items of that model class. In order to achieve this,
 * controller code is supposed to call @link hook and @link
 * add_meta_boxes as indicated in their respective docstrings.
 */
class AutoFieldsController {
    /**
     * An instance of the class represents the auto-fields controller for
     * one @param $model_class. Since the instance is stateless, it is fine to
     * construct multiple, ephemeral instances at the different hook
     * points in the controller.
     *
     * The following assumptions are made on @param $model_class:
     *
     * - The ::get() static function takes a post ID and returns NULL if there
     *   is no instance of the class with that post ID (even if the post ID does
     *   exist, but for a post that does not belong to the class)
     */
    function __construct ($model_class) {
        $this->model_class = $model_class;
    }

    /**
     * Should be called during the controller class' own
     * initialization, i.e. its own "hook" method.
     */
    function hook ()
    {
        add_filter("is_protected_meta", array($this, "filter_is_protected_meta"), 10, 3);
        on_deactivate(array($this, "clear"));
    }

    /**
     * Should be called at WordPress `add_meta_boxes` time, e.g. from the
     * model class' `register_meta_box_cb` parameter to @link register_post_type
     */
    function add_meta_boxes ()
    {
        add_meta_box("epfl_readonly_meta_" . $this->model_class,
                     ___("Automatic Custom Fields"),
                     array($this, "render_protected_meta_box"),
                     null, 'normal', 'high');
    }

    function filter_is_protected_meta ($is_protected, $meta_key, $unused_meta_type)
    {
        global $post;
        if ($this->model_class::get($post) &&
            in_array($meta_key, AutoFields::of($this->model_class)->get())) {
                return true;
            } else {
                return $is_protected;
            }
    }

    function render_protected_meta_box ()
    {
        global $post;
        if (! $this->model_class::get($post)) return;

        $meta = get_post_meta($post->ID);
        echo "<table>\n";
        foreach (AutoFields::of($this->model_class)->get() as $key) {
            echo "<tr><td>$key</td>\n";
            // All meta keys are single-valued
            $value = array_key_exists($key, $meta) ? $meta[$key][0] : NULL;
            if (preg_match('/^.*-(.*?)$/', $key, $matched)
                and method_exists($this, $method = ("render_meta_field_td_" .
                                                    $matched[1])))
            {
                $this->$method($key, $value);
            } else {
                $this->render_meta_field_td($value);
            }
            echo "</tr>\n";
        }
        echo "</table>\n";
    }

    const MAX_RENDER_LENGTH = 120;

    protected function _htmlify ($value, $max_length = NULL) {
        if ($max_length === NULL) {
            $max_length = static::MAX_RENDER_LENGTH;
        }
        if (strlen($value) > $max_length) {
            $value = substr($value, 0, $max_length) . "...";
        }
        return htmlspecialchars($value);
    }

    function render_meta_field_td ($value) {
        echo "<td>" . $this->_htmlify($value) . "</td>";
    }


    function render_meta_field_td_url ($key, $value) {
        echo '<td><a href="' . $this->_htmlify($value, 500) .'">' . $this->_htmlify($value) . "</td>";
    }

    function render_meta_field_td_epoch ($key, $value) {
        $this->render_meta_field_td($value);
        if (! preg_match('/^[1-9][0-9]*$/', $value)) return;
        $value_human = strftime('%c', $value);
        echo "<td>$value_human</td>";
    }

    static protected $_json_button_count = 0;
    function render_meta_field_td_json ($key, $value) {
        $this->render_meta_field_td($value);
        if (NULL !== json_decode($value)) {
            $button_id = "autofields_json_button" . static::$_json_button_count++;
            echo "<td><button id=\"$button_id\">" . ___("<code>console.log()</code> it"). "</button></td>\n";
?>
<script>
jQuery(function($) {
  $('#<?php echo $button_id; ?>').click(function(e) {
    e.stopPropagation();
    console.log('<?php echo "$key ="; ?>', JSON.parse("<?php echo static::escape_js_doublequoted($value); ?>"));
    return false;
  });
});
</script>
<?php
        }
    }

    static function escape_js_doublequoted ($value) {
        return addslashes($value);
    }

    function clear ()
    {
        AutoFields::of($this->model_class)->clear();
        return $this;  // Chainable
    }
}
