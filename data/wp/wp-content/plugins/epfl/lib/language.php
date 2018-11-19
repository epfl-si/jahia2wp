<?php
/**
 * Utilities about languages, using Polylang if installed
*/

namespace EPFL\Language;

function get_current_or_default_language() {
    $default_lang = 'en';
    $allowed_langs = array('en', 'fr');
    $language = $default_lang;
    
    /* If Polylang installed */
    if(function_exists('pll_current_language'))
    {
        $current_lang = pll_current_language('slug');
        // Check if current lang is supported. If not, use default lang
        $language = (in_array($current_lang, $allowed_langs)) ? $current_lang : $default_lang;
    } else {
        $lang = get_bloginfo("language");

        if ($lang === 'fr-FR') {
            $language = 'fr';
        }
    }

    return $language;
}