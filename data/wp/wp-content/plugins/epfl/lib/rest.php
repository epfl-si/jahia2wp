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
 *
 * An instance is constructed iff the normal Polylang construction process
 * *doesn't* complete the way we would want it to. The job is thereafter
 * to fix things up.
 */
class _ForcedPolylangChooser extends \PLL_Choose_Lang {
    function __construct() {
        global $polylang;
        parent::__construct($polylang);
        // Ensure that nav menus are diverted like they would be in
        // a frontend page:
        new \PLL_Frontend_Nav_Menu($polylang);
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
     * @param $relative_path The path to a local entry point, relative to the
     *              REST root (similar to parameters to @link GET_JSON
     *              or @link POST_JSON)
     *
     * @param $base (Optional) The URL of the expected caller of $path,
     *              or an instance of @link Site.
     */
    static function get_entrypoint_url ($relative_path, $base = NULL) {
        if (! $base) {
            $base = Site::this_site();
        }

        if ($base instanceof Site) {
            $base = $base->get_url();
        }

        if (! (preg_match('#/$#', $base))) {
            $base = "$base/";
        }
        return sprintf('%swp-json/%s/%s', $base,
                       _API_EPFL_PATH, $relative_path);
    }
}

REST_API::hook();

class RESTClientError extends \Exception {}
class RESTLocalError  extends RESTClientError {}
class RESTSSLError    extends RESTClientError {}
class RESTRemoteError extends RESTClientError {}

class RESTClient
{
    function __construct () {}

    function set_base_uri($base_uri) {
        $this->base_uri = $base_uri;
        return $this;  // Chainable
    }

    function GET_JSON ($url) {
        if (! isset($this)) {
            return (new static())->GET_JSON($url);
        }

        $url = $this->_canonicalize_url($url);
        return HALJSON::decode((new _RESTRequestCurl($url, 'GET'))->execute());
    }

    function POST_JSON ($url, $data) {
        if (! isset($this)) {
            return (new static())->POST_JSON($url, $data);
        }

        $url = $this->_canonicalize_url($url);
        return HALJSON::decode((new _RESTRequestCurl($url, 'POST'))
                               ->setup_POST($data)->execute());
    }
    /**
     * Like @link POST_JSON, but "fire and forget" i.e. do not wait
     * for a response.
     */
    function POST_JSON_ff ($url, $data) {
        if (! isset($this)) {
            return (new static())->POST_JSON_ff($url, $data);
        }

        $url = $this->_canonicalize_url($url);
        (new _RESTRequestSocketFireAndForget($url, 'POST'))
            ->setup_POST($data)->execute();
    }

    function _canonicalize_url ($url) {
        if (! preg_match('#^/#', parse_url($url, PHP_URL_PATH))) {
            if ($this->base_uri) {
                $url = $this->base_uri . $url;
            } else {
                throw new \Error("Cannot canonicalize root-less uri $url");
            }
        }
        return Site::this_site()->make_absolute_url($url);
    }
}

class _RESTRequestBase
{
    function __construct ($url, $method = 'GET') {
        $this->url = $url;
        $this->method = $method;

        $this->headers = [];
        $this->_add_header('Accept', 'application/json');
    }

    protected function _add_header ($header, $value) {
        $this->headers[] = array($header, $value);
    }

    public function setup_POST ($data) {
        $this->body = is_string($data) ? $data : json_encode($data);
        $this->_add_header('Content-Type', 'application/json');
        $this->_add_header('Content-Length', strlen($this->body));
        return $this;  // Chainable
    }

    protected function _get_connect_to () {
        if (! $this->_connect_to) {
            $host = parse_url($this->url, PHP_URL_HOST);
            $port = parse_url($this->url, PHP_URL_PORT);
            if (! $port) {
                $port = (parse_url($this->url, PHP_URL_SCHEME) === "http") ?
                         80 : 443;
            }
            $hostport = "$host:$port";

            /**
             * Filters the host:port to connect to while attempting to
             * perform a REST API call.
             *
             * In order to bypass proxies and caches, it is possible
             * to add a filter that rewrites a particular host:port to
             * another one. In that case, the Host: header will still
             * be sent as if from the original query.
             */
            $hostport_filtered = apply_filters("epfl_rest_rewrite_connect_to", $hostport, $host, $port);

            $exploded = explode(':', $hostport_filtered);
            $this->_connect_to = (object) array(
                    'is_altered'  => ($hostport_filtered !== $hostport),
                    'hostport'    => $hostport_filtered,
                    'host'        => $exploded[0]
                );
            if (count($exploded) > 1) {
                $this->_connect_to->port = $exploded[1];
            }
        }
        return $this->_connect_to;
    }
}

class _RESTRequestCurl extends _RESTRequestBase {
    function execute () {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($this->method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        $connect_to = $this->_get_connect_to();

        if ($connect_to->is_altered) {
            $host_orig = parse_url($this->url, PHP_URL_HOST);
            $port_orig = parse_url($this->url, PHP_URL_PORT);
            if (! $port_orig) {
                $port_orig = (parse_url($this->url, PHP_URL_SCHEME) === "http") ?
                           80 : 443;
            }
            $connectto = sprintf(
                '%s:%d:%s',
                $host_orig, $port_orig, $connect_to->hostport);
            curl_setopt($ch, CURLOPT_CONNECT_TO, array($connectto));

            # It is very likely that an altered connect-to be using
            # a fake certificate:
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,
                        $connect_to->host != 'localhost');
        }

        $curl_headers = array();
        foreach ($this->headers as $header) {
            list($header, $value) = $header;
            $curl_headers[] = "$header: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);

        if ($this->body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        }

        $result = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
            return $result;
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code === 0) {
                $curl_error = curl_error($ch);
                $msg = "$this->method $this->url: $curl_error";
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
                    "$this->method $this->url: remote HTTP status code $http_code");
                $e->http_code = $http_code;
            }
            throw $e;
        }
    }
}

class _RESTRequestSocketFireAndForget extends _RESTRequestBase {
    function __construct ($url, $method = 'POST') {
        parent::__construct($url, $method);

        $this->_add_header('Connection', 'close');
    }

    function execute () {
        $connect_to = $this->_get_connect_to();
        $verify_cert = ((! $connect_to->is_altered) &&
                        ($connect_to->host != 'localhost'));
        $ssl_params = array('ssl' =>
                            array('verify_peer'      => $verify_cert,
                                  'verify_peer_name' => $verify_cert));

        $tlscontext = stream_context_create($ssl_params);

        $fd = stream_socket_client(
            sprintf('tls://%s:%d', $connect_to->host, $connect_to->port),
            $errno, $errstr,
            ini_get("default_socket_timeout"),
            STREAM_CLIENT_CONNECT,
            $tlscontext);
        if (! $fd) {
            $e = new RESTLocalError("$this->method $this->url: $errstr");
            $e->sock_errno = $errno;
            throw $e;
        }

        $path = parse_url($this->url, PHP_URL_PATH);
        if ($query = parse_url($this->url, PHP_URL_QUERY)) {
            $path = "$path?$query";
        }

        $host = parse_url($this->url, PHP_URL_HOST);
        if ($port = parse_url($this->$url, PHP_URL_PORT)) {
            $hostheader = "$host:$port";
        } else {
            $hostheader = "$host";
        }

        $headers  = "$this->method $path HTTP/1.1\r\n";
        $headers  .= "Host: $hostheader\r\n";
        foreach ($this->headers as $header) {
            list($header, $value) = $header;
            $headers .= "$header: $value\r\n";
        }

        $headers .= "\r\n";
        fwrite($fd, $headers, strlen($headers));

        if ($this->body) {
            fwrite($fd, $this->body, strlen($this->body));
        }

        fclose($fd);
    }
}

if (php_sapi_name() !== 'cli') {
    add_filter('epfl_rest_rewrite_connect_to', function($hostport, $host, $port) {
        $myhostport = Site::my_hostport();
        if ($hostport === $myhostport ||
            $hostport === "$myhostport:443") {
            return "localhost:8443";
        } else {
            return $hostport;
        }
    }, 10, 3);
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
