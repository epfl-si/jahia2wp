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
        parent::__construct('' . $this->payload);
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
        $class = get_called_class();
        add_action('rest_api_init', function() use ($class, $path, $callback) {
            register_rest_route(_API_EPFL_PATH, $path, array(
                'methods' => 'GET',
                'callback' => function($data) use ($class, $callback) {
                    return $class::_call_and_APIfy($callback, $data);
                }
            ));
        });
    }

    static function POST_JSON ($path, ...$callback) {
        $class = get_called_class();
        add_action('rest_api_init', function() use ($class, $path, $callback) {
            register_rest_route(_API_EPFL_PATH, $path, array(
                'methods' => 'POST',
                'callback' => function($data) use ($class, $callback) {
                    return $class::_call_and_APIfy($callback, $data);
                }
            ));
        });
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
     * If the current query is a wp-json query, let Polylang know through
     * the 'pll_is_ajax_on_front' filter, and Polylang will take it from
     * there.
     */
    static function hook_polylang_lang_query_param () {
        $thisclass = get_called_class();
        add_filter('pll_is_ajax_on_front', function($isit) use ($thisclass) {
            if ($thisclass::_doing_rest_request()) {
                return true;
            } else {
                return $isit;
            }
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
}

REST_API::hook();


class RESTClientError extends \Exception
{
    static function build ($doingwhat, $url, $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code === 0) {
            $curl_error = curl_error($ch);
            $e = new RESTLocalError("$doingwhat $url: $curl_error");
            $e->curl_error = $curl_error;
        } else {
            $e = new RESTRemoteError(
                "$doingwhat $url: remote HTTP status code $http_code");
            $e->http_code = $http_code;
        }
        return $e;
    }
}

class RESTLocalError extends RESTClientError {}
class RESTRemoteError extends RESTClientError {}

class RESTClient
{
    private function __construct ($url) {
        if ($url instanceof REST_URL) {
            $url = $url->fully_qualified();
        }
        $this->url = $url;
    }

    private function get_curl () {
        if (! $this->curl) {
            $this->curl = curl_init($this->url);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
                'Accept: application/json'));

            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, TRUE);
            // Accept the self-signed certificate for traffic on
            // localhost (typically for the menu pubsub stuff)
            curl_setopt($this->curl, CURLOPT_CAINFO, '/etc/apache2/ssl/server.cert');
            if (preg_match('|^https://jahia2wp-httpd[:/]|', $this->url)) {
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            }

            return $this->curl;
        }

        return $this->curl;
    }

    function execute () {
        $ch = $this->get_curl();

        $result = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
            return HALJSON::decode($result);
        } else {
            throw RESTClientError::build("GET", $this->url, $ch);
        }
    }

    static function GET_JSON ($url) {
        $thisclass = get_called_class();
        return (new $thisclass($url))->execute();
    }

    function _setup_POST ($data) {
        $ch = $this->get_curl();

        $payload = is_string($data) ? $data : json_encode($data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    static function POST_JSON ($url, $data) {
        $thisclass = get_called_class();
        $that = new $thisclass($url);
        $that->_setup_POST($data);
        return $that->execute();
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
     * @return A REST_URL object rooted on whatever protocol,
     * host and port the HTTP client is using
     */
    static function local_wrt_request ($path) {
        $thisclass = get_called_class();
        $site_url = site_url();
        $site_path = parse_url($site_url, PHP_URL_PATH);
        if (! ($proto = $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = $_SERVER['HTTPS'] ? 'https' : 'http';
        }
        if (! ($hostport = $_SERVER['HTTP_X_FORWARDED_HOST'])) {
            if (! ($hostport = $_SERVER['HTTP_HOST'])) {
                $host = parse_url($site_url, PHP_URL_HOST);
                $port = parse_url($site_url, PHP_URL_PORT);
                $hostport = $port ? "$host:$port" : $host;
            }
        }
        return new $thisclass($path, "$proto://$hostport/$site_path");
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
        return $this->site_base . "/wp-json/$api_path/" . $this->path;
    }

    function __toString () {
        return $this->fully_qualified();
    }
}


/**
 * Parse the HAL links as per draft-kelly-json-hal-0X
 * @see http://stateless.co/hal_specification.html
 *
 * Even though the HAL standardization initiative appears to have
 * fizzled out, @link WP_REST_Server::embed_links provides for
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
        return new $thisclass($json);
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
