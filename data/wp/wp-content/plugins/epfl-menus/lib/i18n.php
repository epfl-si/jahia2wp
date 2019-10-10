<?php

namespace EPFL\I18N;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

const TEXTDOMAIN = 'epfl';

function ___ ($text) {
    return __($text, TEXTDOMAIN);
}

function __x ($text, $context) {
    return _x($text, $context, TEXTDOMAIN);
}

function __e ($text) {
    return _e($text, TEXTDOMAIN);
}

function __n ($text, $plural, $number) {
    return _n($text, $plural, $number, TEXTDOMAIN);
}


function esc_html___ ($text) {
    return esc_html__($text, TEXTDOMAIN);
}

function get_current_language () {
    return function_exists('pll_current_language') ? pll_current_language() : NULL;
}
