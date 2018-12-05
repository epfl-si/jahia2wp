<?PHP

    /* Exception */
    require_once __DIR__."/Prometheus/Exception/MetricNotFoundException.php";
    require_once __DIR__."/Prometheus/Exception/MetricsRegistrationException.php";
    require_once __DIR__."/Prometheus/Exception/MetricNotFoundException.php";

    /* Storage */
    require_once __DIR__."/Prometheus/Storage/Adapter.php";
    require_once __DIR__."/Prometheus/Storage/APC.php";

    /* Base */
    require_once __DIR__."/Prometheus/Collector.php";
    require_once __DIR__."/Prometheus/CollectorRegistry.php";
    require_once __DIR__."/Prometheus/Counter.php";
    require_once __DIR__."/Prometheus/Gauge.php";
    require_once __DIR__."/Prometheus/Histogram.php";
    require_once __DIR__."/Prometheus/MetricFamilySamples.php";
    require_once __DIR__."/Prometheus/PushGateway.php";
    require_once __DIR__."/Prometheus/RenderTextFormat.php";
    require_once __DIR__."/Prometheus/Sample.php";


/*
<?php

require_once __DIR__."/../lib/load-prometheus.php";

use Prometheus\CollectorRegistry;


$adapter = new Prometheus\Storage\APC();

$registry = new CollectorRegistry($adapter);


/**
 * @param string $namespace e.g. cms
 * @param string $name e.g. requests
 * @param string $help e.g. The number of requests made.
 * @param array $labels e.g. ['controller', 'action']
 * @return Counter
 * @throws MetricsRegistrationException
 */
$counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
$counter->incBy(1, ['blue']);
$counter->incBy(2, ['green']);
echo "OK\n";


*/