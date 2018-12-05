<?php

require_once __DIR__."/../lib/prometheus.php";


use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

$adapter = new Prometheus\Storage\APC();

$registry = new CollectorRegistry($adapter);
$renderer = new RenderTextFormat();
$result = $renderer->render($registry->getMetricFamilySamples());
header('Content-type: ' . RenderTextFormat::MIME_TYPE);
echo $result;