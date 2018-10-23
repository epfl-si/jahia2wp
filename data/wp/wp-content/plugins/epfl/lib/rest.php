<?php
/* Copyright © 2018 École Polytechnique Fédérale de Lausanne, Switzerland */
/* All Rights Reserved, except as stated in the LICENSE file. */

/**
 * Utilities for REST APIs in the EPFL plugin
 */

namespace EPFL\REST;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

use \WP_Error;
use \Throwable;

require_once(__DIR__ . '/pod.php');
use \EPFL\Pod\Site;

const _API_EPFL_PATH = 'epfl/v1';


class RESTAPIError extends \Exception {
    function __construct($http_code, $payload) {
        if (is_string($payload)) {
            $payload = array(
                'status' => 'ERROR',
                'msg'    => $payload
            );
        }
        $this->status = $http_code;
        $this->payload = $payload;
        parent::__construct($this->payload['msg']);
    }
}

/**
 * Ancillary class used by @link REST_API::hook_polylang_lang_query_param
 */
class _ForcedPolylangChooser extends \PLL_Choose_Lang {
    function __construct() {
        global $polylang;
        parent::__construct($polylang);
    }
}

/**
 * Static-only class for registering REST API endpoints
 */
class REST_API {
    static function hook () {
        get_called_class()::hook_polylang_lang_query_param();
    }

    /**
     * Register a GET route that serves JSON.
     *
     * @param $path     A regex for paths that should be matched under
     *                  /epfl/v1. Should start with a slash ('/'). May
     *                  extract URL pieces using named regex patterns,
     *                  e.g. '/mywhatever/(?P<id>[0-9]+)'; in which
     *                  case the matched bits will be available in
     *                  $callback as e.g. $data['id'], where $data is
     *                  $callback's first argument. CAVEAT: plain old
     *                  positional patterns (with parentheses) in the
     *                  regex *cannot* be used for extraction!
     *                  WP_REST_Server::dispatch() has this
     *                  undocumented "feature" of dropping @link
     *                  preg_match matches whose key is_int().
     *
     * @param $callback The function, method or class method that will
     *                  be called to handle $path. (To pass a method
     *                  or class method, use an array of size two; see
     *                  "closures" the in PHP documentation.) Will be
     *                  called as mycallback($req), where $req is an
     *                  instance of @link WP_REST_Request . URL pieces
     *                  matched by named regex patterns in $path will
     *                  be available as e.g. $req['id'], while query
     *                  parameters (after the question mark) are
     *                  available as e.g. $req->get_param('myparam').
     *                  Shall return a data structure that will be
     *                  converted to JSON and served.
     */
    static function GET_JSON ($path, ...$callback) {
        $thisclass = get_called_class();
        static::_do_at_time(
            'rest_api_init',
            function() use ($thisclass, $path, $callback) {
                register_rest_route(_API_EPFL_PATH, $path, array(
                    'methods' => 'GET',
                    'callback' => function($data) use ($thisclass, $callback) {
                        return $thisclass::_call_and_APIfy($callback, $data);
                    }
                ));
            });
    }

    static function POST_JSON ($path, ...$callback) {
        $thisclass = get_called_class();
        static::_do_at_time(
            'rest_api_init',
            function() use ($thisclass, $path, $callback) {
                register_rest_route(_API_EPFL_PATH, $path, array(
                    'methods' => 'POST',
                    'callback' => function($data) use ($thisclass, $callback) {
                        return $thisclass::_call_and_APIfy($callback, $data);
                    }
                ));
            });
    }

    static private function _do_at_time ($action_name, $callable) {
        if (did_action($action_name)) {
            call_user_func($callable);
        } else {
            add_action($action_name, $callable);
        }
    }

    /**
     * Call $callback with parameters $param_list, and ensure that the
     * result is either a JSONable structure or a @link WP_Error.
     */
    static function _call_and_APIfy ($cb, ...$params) {
        try {
            if ($cb[0] instanceof \Closure) {
                $cb = $cb[0];
            }

            return call_user_func_array($cb, $params);
        } catch (Throwable $t) {  // Non-goal: PHP5 support
            error_log($t);
            return get_called_class()::_serve_error($t);
        }
    }

    /**
     * @param Throwable $t
     * @return WP_Error
     */
    static function _serve_error ($t) {
        $t_class_slug = preg_replace('/^-/', '',
                                     str_replace('\\', '-', get_class($t)));
        if ($t instanceof RESTAPIError) {
            return new \WP_REST_Response($t->payload, $t->status);
        } else {
            return new WP_Error("ERROR:$t_class_slug", $t->getMessage());
        }
    }

    /**
     * Have Polylang set the current language from the ?lang=XX query parameter.
     *
     * If the current query is not a wp-json query, do nothing. Otherwise,
     * get Polylang to use $_REQUEST['lang'] by hook or by crook.
     */
    static function hook_polylang_lang_query_param () {
        if (! static::_doing_rest_request()) return;

        // The easy way: when Languages -> Settings -> URL
        // modifications is set to "The language is set from the
        // directory name in pretty permalinks", and except for the
        // root site (see below), Polylang does the needful for
        // AJAX-on-front requests.
        add_filter('pll_is_ajax_on_front', function($isit) { return true; });

        // The hard way: there is some code in PLL_Frontend::init() to
        // short-circuit language detection entirely (regardless of
        // Languages -> Settings -> URL modifications) if /wp-json/ is
        // detected at the start of the URL. Ironically, the test is
        // sort of buggy (only works on the root site). We can force
        // the issue like this:
        add_action('pll_no_language_defined', function() {
            $chooser = new _ForcedPolylangChooser();
            $chooser->init();
        });
    }

    /**
     * @return Whether the current query is for one of the REST handlers
     *         registered with this class.
     */
    static function _doing_rest_request () {
        return (false !== strpos($_SERVER['REQUEST_URI'],
                                 '/wp-json/' . _API_EPFL_PATH));
    }

    /**
     * @param $path The path to a local entry point, relative to the
     *              REST root (similar to parameters to @link GET_JSON
     *              or @link POST_JSON)
     *
     * @param $wrt_url (Optional) The URL of the expected caller of $path.
     *                 If $wrt_url is a localhost URL, then the returned
     *                 URL will be too.
     *
     * @return An instance of @link REST_URL that can be used to reach
     *         that entry point
     */
    static function get_entrypoint_url ($path, $wrt_url = NULL) {
        if ($wrt_url && 'localhost' === parse_url($wrt_url, PHP_URL_HOST)) {
            return REST_URL::remote(
                Site::this_site()->get_localhost_url(),
                $path);
        } else {
            return REST_URL::local_canonical($path);
        }
    }
}

REST_API::hook();


class RESTClientError extends \Exception
{
    static function build ($doingwhat, $url, $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code === 0) {
            $curl_error = curl_error($ch);
            $msg = "$doingwhat $url: $curl_error";
            $curl_errno = curl_errno($ch);
            if (FALSE !== array_search($curl_errno,
                                       array(CURLE_SSL_CERTPROBLEM,
                                             CURLE_SSL_CACERT))) {
                $e = new RESTSSLError($msg);
            } else {
                $e = new RESTLocalError($msg);
            }
            $e->curl_error = $curl_error;
            $e->curl_errno = $curl_errno;
        } else {
            $e = new RESTRemoteError(
                "$doingwhat $url: remote HTTP status code $http_code");
            $e->http_code = $http_code;
        }
        return $e;
    }
}

class RESTLocalError  extends RESTClientError {}
class RESTSSLError    extends RESTClientError {}
class RESTRemoteError extends RESTClientError {}

class RESTClient
{
    private function __construct ($url, $method = 'GET') {
        if ($url instanceof REST_URL) {
            $url = $url->fully_qualified();
        }
        $this->url = $url;
        $this->method = $method;
        $this->ch = curl_init($this->url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        if ($method === 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
        }

        $this->headers = [];
        $this->_add_headers('Accept: application/json');

        if (preg_match('|^https?://localhost|', $this->url)) {
            # Local traffic - Our cert is fake, deal with it
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            # As a special case for localhost, mess with the Host: header
            # to prevent Apache from going all 404 on us.
            $this->_add_headers('Host: ' . Site::my_hostport());
        } else {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        }
    }

    function execute () {
        $result = curl_exec($this->ch);

        if (curl_getinfo($this->ch, CURLINFO_HTTP_CODE) == 200) {
            return HALJSON::decode($result);
        } else {
            throw RESTClientError::build($this->method, $this->url, $this->ch);
        }
    }

    static function GET_JSON ($url) {
        $thisclass = get_called_class();
        return (new $thisclass($url, 'GET'))->execute();
    }


    static function POST_JSON ($url, $data) {
        $thisclass = get_called_class();
        return (new $thisclass($url, 'POST'))->_setup_POST($data)->execute();
    }

    function _setup_POST ($data) {
        $payload = is_string($data) ? $data : json_encode($data);
        $this->_add_headers(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload));
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);
        return $this;  // Chainable
    }

    /**
     * Don't fall prey to https://stackoverflow.com/a/15134580/435004
     */
    private function _add_headers (...$headers) {
        $this->headers = array_merge($this->headers, $headers);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
    }
}


/**
 * @example
 *
 * assert 'https://www.epfl.ch/labs/mylab/wp-json/epfl/v1/foo/bar' ===
 *        REST_URL::remote('https://www.epfl.ch/labs/mylab',
 *                         'foo/bar')->fully_qualified();
 *
 * # Given get_site_url() === 'https://www.epfl.ch/labs/mylab':
 *
 * assert 'https://www.epfl.ch/labs/mylab/wp-json/epfl/v1/foo/bar' ===
 *        REST_URL::local_canonical('foo/bar')->fully_qualified();
 * assert '/labs/mylab/wp-json/epfl/v1/foo/bar' ===
 *        REST_URL::local_canonical('foo/bar')->relative_to_root();
 */
class REST_URL
{
    private function __construct ($path, $site_base) {
        $this->path = $path;
        $this->site_base = $site_base;
    }

    /**
     * @return A REST_URL object rooted on the @link site_url
     * value (set in-database)
     */
    static function local_canonical ($path) {
        $thisclass = get_called_class();
        return new $thisclass($path, site_url());
    }

    /**
     * @return A REST_URL object rooted on whatever protocol, host and
     * port the HTTP client is using. Specifically, if
     * $_SERVER["REMOTE_ADDR"] is a localhost address, return a URL
     * based on localhost.
     */
    static function local_wrt_request ($path) {
        if (! ($proto = $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = $_SERVER['HTTPS'] ? 'https' : 'http';
        }

        if (static::_current_request_is_localhost()) {
            $hostport = 'localhost:' . $_SERVER['SERVER_PORT'];
        } elseif (! ($hostport = $_SERVER['HTTP_X_FORWARDED_HOST'])) {
            if (! ($hostport = $_SERVER['HTTP_HOST'])) {
                $hostport = static::_my_hostport();
            }
        }

        $thisclass = get_called_class();
        $site_url = site_url();
        $site_path = parse_url($site_url, PHP_URL_PATH);
        return new $thisclass($path, "$proto://$hostport$site_path");
    }

    static function remote ($site_base_url, $path_below_epfl_v1) {
        $thisclass = get_called_class();
        return new $thisclass($path_below_epfl_v1, $site_base_url);
    }

    function relative_to_root () {
        return parse_url($this->fully_qualified(), PHP_URL_PATH);
    }

    function fully_qualified () {
        $api_path = _API_EPFL_PATH;
        $site_base = preg_replace('#/+$#', '', $this->site_base);
        return $site_base . "/wp-json/$api_path/" . $this->path;
    }

    function __toString () {
        return $this->fully_qualified();
    }

    static private function _current_request_is_localhost () {
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if (0 === strpos($remote_ip, '127.')) {
            return true;
        } elseif ($remote_ip === "::1") {
            return true;
        } else {
            return false;
        }
    }
}


/**
 * Parse the HAL links as per draft-kelly-json-hal-0X
 * @see http://stateless.co/hal_specification.html
 *
 * Even though the HAL standardization initiative appears to have
 * fizzled out, @link WP_REST_Server::add_link provides for
 * emitting it - One just has to parse it client-side, so there.
 */
class HALJSON
{
    protected function __construct ($json) {
        foreach (json_decode($json) as $k => $v) {
            $this->$k = $v;
        }
    }

    static function decode ($json) {
        $thisclass = get_called_class();
        if (preg_match('/^\s*[{]/s', $json)) {
            return new $thisclass($json);
        } else {
            # No point in HALJSON'ing an array
            return json_decode($json);
        }
    }

    /**
     * @return An URL or NULL
     */
    function get_link ($rel) {
        $links = $this->_links->$rel;
        if (! $links) {
            return NULL;
        }
        if ($links[0]) {
            return $links[0]->href;
        } else {
            return $links->href;
        }
    }
}
