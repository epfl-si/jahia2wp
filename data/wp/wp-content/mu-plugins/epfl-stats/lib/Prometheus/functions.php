<?php

namespace Prometheus;

use Prometheus\CollectorRegistry;

/*
    Save a webservice call duration including source page and timestamp on which call occurs

    @param $url         -> Webservice URL call
    @param $duration    -> webservice call duration (microsec)
*/
function record_ws_call($url, $duration)
{

    global $wp;

    $adapter = new Storage\APC();

    $registry = new CollectorRegistry($adapter);

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. requests
     * @param string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     * @throws MetricsRegistrationException
     */
    $counter = $registry->registerCounter('epfl', 'shortcode', '', ['page', 'url', 'duration', 'timestamp']);
    $counter->incBy(1, [home_url( $wp->request ), $url, $duration, microtime(true)]);
}