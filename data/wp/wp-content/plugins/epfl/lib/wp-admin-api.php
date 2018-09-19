<?php

/**
 * Homemade HTML forms and AJAX in the wp-admin area, with XSRF protection.
 */

namespace EPFL\AdminAPI;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

/**
 * An endpoint for AJAX or Web 1.0-style ("custom POST handler") forms
 * in the wp-admin pages, with XSRF protection
 *
 * An instance of this class represents a number of PHP methods on the
 * server side (registered with @link register_handlers), and all the
 * information that the client-side HTML or JS needs in order to
 * successfully invoke them (that includes an "action" demultiplexer
 * and an XSRF nonce, both emitted as
 * application/x-www-form-urlencoded POST fields; see @link
 * output_bindings).
 */
class Endpoint
{
    function __construct ($slug) {
        // $slug must be a valid JS identifier:
        assert(preg_match('/^[A-Za-z_][A-Za-z0-9_]+$/', $slug));
        $this->slug = $slug;
    }

    private function get_action_name ($method_stem) {
        // IMPORTANT: the JS code served by @link output_js_prototype
        // must perform the exact same computation for the "action"
        // field.
        return sprintf('epfl_%s_%s', $this->slug, $method_stem);
    }

    private function nonce_name () {
        return 'xsrf_nonce_' . $this->slug;
    }

    /**
     * Register $class as having AJAX and/or custom POST handlers
     *
     * All methods in $class whose name is like ajax_${method_stem}
     * are set up as so-called "WP-AJAX" handlers, available at URL
     * /admin-ajax.php?action=epfl_${slug}_${method_stem}, where $slug
     * is the constructor parameter. Likewise, all methods in $class
     * whose name matches admin_post_${method_stem} are hooked up as
     * so-called "custom POST handlers", at address
     * /post.php?action=epfl_${slug}_${method_stem}.
     *
     * WP-AJAX handler methods can obtain the details of a GET AJAX
     * request in $_GET, and/or $_REQUEST; in the case of a proper
     * JSON POST request (with Content-Type: application/json in the
     * request), the decoded JSON will be passed as a parameter to the
     * handler method instead. WP-AJAX handler methods shall return
     * the data structure that they wish to return to the AJAX caller
     * (typically a PHP associative array).
     *
     * Custom POST handler methods get the parameters using $_REQUEST
     * / $_POST as usual; they shall return the URL (relative to
     * /wp-admin) the user's browser shall be 301-redirected to.
     * (Serving actual HTML pages only out of GET requests is a best
     * practice to prevent issues with the browser's reload button.)
     *
     * Neither WP-AJAX nor custom-post method handlers need to check
     * the nonce by themselves for XSRF protection; this will have
     * been done already by code in this class before control enters
     * the handlers.
     *
     * @see https://codex.wordpress.org/AJAX_in_Plugins WP-AJAX API definition
     * @see https://codex.wordpress.org/Plugin_API/Action_Reference/admin_post_(action) Custom POST handlers API definition
     *
     * @param $class The fully qualified class name. Tip: you can
     *               use the form `MyClass::class` to get a fully
     *               qualified class name.
     */
    function register_handlers ($class)
    {
        $self = $this;
        foreach (get_class_methods($class) as $method_name) {
            $matched = [];
            if (preg_match("/^ajax_(.*)$/", $method_name, $matched)) {
                add_action(
                    "wp_ajax_" . $this->get_action_name($matched[1]),
                    function() use ($self, $class, $method_name) {
                        $self->_handle_ajax($class, $method_name);
                    });
            }
            if (preg_match("/^admin_post_(.*)$/", $method_name, $matched)) {
                add_action(
                    "admin_post_" . $this->get_action_name($matched[1]),
                    function() use ($self, $class, $method_name) {
                        $self->_handle_admin_post($class, $method_name);
                    });
            }
        }
    }

    function _handle_ajax ($class, $method_name) {
        check_ajax_referer($this->nonce_name());
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
    }

    function _handle_admin_post ($class, $method_name) {
        check_admin_referer($this->nonce_name());
        $redirect_url = call_user_func(array($class, $method_name));
        wp_redirect(admin_url($redirect_url));
        exit();
    }


    static $_endpoint_emitted;
    /**
     * Output the Endpoint constructor into the PHP document flow.
     */
    static private function output_js_endpoint_once ()
    {
        if (self::$_endpoint_emitted) return;

            ?>
            <script>
            function Endpoint(slug, nonce) {
                var web10POSTUrl = "<?php echo admin_url('admin-post.php'); ?>",
                    ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
<?php # IMPORTANT: action_name() below must make the exact same
      # computation as @link get_action_name ?>
                function action_name (method_stem) {
                    return 'epfl_' +  slug + '_' + method_stem;
                }
                function ajax (method_stem, opts) {
                    var $ = window.jQuery;
                    return $.ajax($.extend({
                            url: ajaxUrl + '?_ajax_nonce=' + nonce + 
                                 '&action=' + action_name(method_stem),
                        }, (opts || {})));
                }
                return {
                    ajax: ajax,
                    post: function (method_stem, opts) {
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
                    },
                    asWPAdminPostForm: function (method_stem) {
                        var $ = window.jQuery;
                        var $form = $('<form></form>').appendTo($('body'));
                        $form.attr({
                                method: 'POST',
                                action: web10POSTUrl
                             });

                        $form.inputHidden = function(name, value) {
                            var $input = $('<input type="hidden"></input>')
                                       .appendTo($form);
                            $input.attr({
                                  id: name,<?php # Cargo-culted ?>
                                  name: name,
                                  value: value});
                            return $input;
                        };
                        $form.inputHidden('_wpnonce', nonce);
                        $form.inputHidden('action', action_name(method_stem));
                        return $form;
                    }
                };
            }
            </script>
            <?php

        self::$_endpoint_emitted = true;
    }

    /**
     * Echoes some JS code to encapsulate calling the AJAX endpoints
     *
     * window[$slug] will be set up as an object with .ajax(), .post()
     * and .asWPAdminPostForm() methods that encapsulate (hide) the
     * demultiplexing and XSRF protection concerns from the caller.
     * window[$slug].ajax() and window[$slug].post() work a lot like
     * jQuery.ajax and jQuery.post respectively, except the URL
     * parameter is replaced with a PHP-side method stem (that is, the
     * part after "ajax_" of methods of the class(es) passed to @link
     * register_handlers).
     *
     * window[$slug].asWPAdminPostForm(actionSlug) creates and returns
     * a jQuery object containing a newly constructed <form> element;
     * likewise, the actionSlug parameter corresponds do the part
     * after "admin_post_" in the name of class(es) passed to @link
     * register_handlers. Call .inputHidden(name, value) on the
     * returned jQuery object to add form fields to your liking (in
     * addition to the ones set up automatically, see below)
     *
     * In both cases, the appropriate piping is performed transparently:
     * the `action` POST field is set up to match the 
     * @link wp_ajax_(action) or @link admin_post_(action) in the
     * Wordpress API, and a nonce field (_wpnonce for the
     * asWPAdminPostForm case; _ajax_nonce for the ajax / post case)
     * is set up to a suitably generated XSRF protection token.
     */
    function output_bindings ()
    {
        $this->output_js_endpoint_once();
        printf(
            "<script>window.%s = new Endpoint('%s', '%s');</script>\n",
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
