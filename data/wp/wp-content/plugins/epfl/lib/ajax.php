<?php

/**
 * Support for AJAX in the wp-admin area
 */

namespace EPFL\AJAX;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

/**
 * An AJAX endpoint with XSRF protection
 *
 * An instance of this class represents all the endpoints with URLs of
 * the form /admin-ajax.php?action=epfl_${slug}_${method_stem}, where
 * $slug is passed to the constructor and $method_stem is the part
 * after "ajax_" of any PHP methods passed through @link
 * register_handlers.
 */
class Endpoint
{
    function __construct ($slug) {
        // $slug must be a valid JS identifier:
        assert(preg_match('/^[A-Za-z_][A-Za-z0-9_]+$/', $slug));
        $this->slug = $slug;
    }

    private function wp_ajax_action_name ($method_stem) {
        // IMPORTANT: the JS code served by @link output_js_prototype
        // must perform the exact same computation for the "action"
        // field.
        return sprintf('epfl_%s_%s', $this->slug, $method_stem);
    }

    private function nonce_name () {
        return 'ajax_nonce_' . $this->slug;
    }

    /**
     * Register $class as having AJAX handlers
     *
     * All methods whose name start with ajax_ in $class are set up as
     * handlers for the corresponding "action" in the sense of
     * @link https://codex.wordpress.org/AJAX_in_Plugins . These
     * methods can obtain the details of a GET AJAX request in $_GET,
     * and/or $_REQUEST; in the case of a proper POST request (with
     * Content-Type: application/json in the request), the decoded
     * JSON will be passed as a parameter to the handler instead.
     *
     * Handlers should return the data structure that they wish to
     * return to the AJAX caller (typically a PHP associative array).
     *
     * Handlers are protected by a nonce against XSRF attacks:
     * @see output_bindings
     *
     * @param $class The fully qualified class name. Tip: you can
     *               use the form `MyClass::class` to get a fully
     *               qualified class name.
     */
    function register_handlers ($class)
    {
        foreach (get_class_methods($class) as $method_name) {
            $matched = [];
            if (! preg_match("/^ajax_(.*)$/", $method_name, $matched)) continue;
            add_action(
                "wp_ajax_" . $this->wp_ajax_action_name($matched[1]),
                function() use ($class, $method_name) {
                    check_ajax_referer($this->nonce_name(), "_ajax_nonce");
                    if ($_SERVER['REQUEST_METHOD'] === "POST" &&
                        $_SERVER["CONTENT_TYPE"] === "application/json") {
                        $json_response = call_user_func(
                            array($class, $method_name),
                            json_decode(file_get_contents('php://input'), true));
                    } else {
                        $json_response = call_user_func(
                            array($class, $method_name));
                    }
                    echo json_encode($json_response, JSON_PRETTY_PRINT);
                    wp_die();  // That's the way WP AJAX rolls
                });
        }
    }

    static $_AJAXEndpoint_emitted;
    static private function output_js_AJAXEndpoint_once ()
    {
        if (self::$_AJAXEndpoint_emitted) return;
        $ajax_url = admin_url('admin-ajax.php');

            ?>
            <script>
            function AJAXEndpoint(slug, nonce) {
                var url = "<?php echo $ajax_url; ?>?_ajax_nonce=" + nonce;
<?php # IMPORTANT: action_name() below must make the exact same
      # computation as @link wp_ajax_action_name ?>
                function action_name (method_stem) {
                    return 'epfl_' +  slug + '_' + method_stem;
                }
                function ajax(method_stem, opts) {
                    var $ = window.jQuery;
                    return $.ajax($.extend({
                            url: url + '&action=' + action_name(method_stem),
                        }, (opts || {})));
                }
                return {
                    ajax: ajax,
                    post: function(method_stem, opts) {
                        if (! opts) {
                            opts = {};
                        }
                        if (! ('data' in opts)) {
                            opts.data = {};
                        }
                        if (typeof(opts.data) !== "string") {
                            opts.data = JSON.stringify(opts.data);
                        }
                        return ajax(method_stem, window.jQuery.extend(
                            {
                                type: 'POST',
                                contentType: "application/json",
                                dataType: "json"  // Expected in return
                            }, opts));
                    }
                };
            }
            </script>
            <?php

        self::$_AJAXEndpoint_emitted = true;
    }

    /**
     * @return Some JS code to encapsulate calling the AJAX endpoints
     *
     * window[$slug] will be set up as an object with .ajax() and
     * .post() methods, which work a lot like jQuery.ajax and
     * jQuery.post except the URL is replaced with a PHP-side method
     * stem (that is, the part after "ajax_" of methods of any and all
     * classes passed to @link register_handlers)
     *
     * The XMLHttpRequest will additionally be instrumented with a
     * Wordpress nonce to thwart Cross-Site Request Forgery (XSRF)
     * attacks.
     */
    function output_bindings ()
    {
        $this->output_js_AJAXEndpoint_once();
        printf(
            "<script>window.%s = new AJAXEndpoint('%s', '%s');</script>\n",
            $this->slug, $this->slug,
            wp_create_nonce($this->nonce_name()));
    }

    /**
     * Arrange for @link output_bindings() to be called
     */
    function admin_enqueue ()
    {
        add_action('admin_enqueue_scripts',
                   array($this, 'output_bindings'));
    }
}
