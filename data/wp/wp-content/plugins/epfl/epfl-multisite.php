<?php

require_once(__DIR__ . '/lib/pod.php');
use \EPFL\Pod\Site;

function get_multisite_home_url () {
    $retval = Site::root()->get_url();
    if (function_exists('pll_current_language') &&
        pll_current_language() != pll_default_language()) {
        $retval .= sprintf('%s/', pll_current_language());
    }
    return $retval;
}
