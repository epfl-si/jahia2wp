<?php
require_once __DIR__."/../lib/prometheus.php";

$apcAdapter = new Prometheus\Storage\APC();
$apcAdapter->flushAPC();
