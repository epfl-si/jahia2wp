<?php

/*
Plugin Name: EPFL Library Plugins
Plugin URI:
Description: provides a shortcode to transmit parameters to specific Library APP
    and get external content from this external source according to the
    transmitted parameters.
Version: 1.0
Author: Raphaël REY & Sylvain VUILLEUMIER
Author URI: https://people.epfl.ch/raphael.rey
Author URI: https://people.epfl.ch/sylvain.vuilleumier
License: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

/*
USAGE: [epfl_library_external_content url="xxx"]
Required parameter:
- url: url source of the external content

Optional parameters :
- script_url: url of an additional js script (required if script_name)
- script_name: name of the script in order to be able to call it (required if script_url)
- css_url: url of an additional css stylesheet (required if css_name)
- css_name: name of the css stylesheet (required if css_url)

The plugin will transmit the arguments of the current url to the external content url.

*/

// function epfl_library_external_content_log($message) {
//
//     if (WP_DEBUG === true) {
//         if (is_array($message) || is_object($message)) {
//             error_log(print_r($message, true));
//         } else {
//             error_log($message);
//         }
//     }
// }

function external_content_urlExists($url)
{
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

    $response = curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode <= 400) {
        return true;
    } else {
        return false;
    }
    curl_close($handle);
}

function epfl_library_external_content_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
                'url' => '',
                'script_name' => '',
                'script_url' => '',
                'css_name' => '',
                'css_url' => ''
    ), $attributes));

    if (url == ''){
      $error = new WP_Error('URL missing', 'The url parameter is missing', $url);
      // epfl_library_external_content_log($error);
      return 'ERROR: url parameter empty.';
    }
    // Add optional css
    if ($css_name != '' and $css_url != ''){
        wp_enqueue_style($css_name, $css_url);
    }

    // Add optional script
    if ($script_name != '' and $script_url != ''){
        wp_enqueue_script($script_name, $script_url);
    }

    // Test the final concatened url
    if (external_content_urlExists($url)) {
        $response = wp_remote_get($url . '?' . $_SERVER['QUERY_STRING']);
        $page = $response['body'];
        return $page;
    } else {
        $error = new WP_Error('not found', 'The page cannot be displayed', $url);
        return 'ERROR: page not found.';
        // epfl_library_external_content_log($error);
    }
}

add_shortcode('epfl_library_external_content', 'epfl_library_external_content_process_shortcode');


/*

EPFL Library BEAST redirection
Description: Automatically redirect to the search tool BEAST depending of the url arguments

FONCTIONNEMENT:
    Les url http://library.epfl.ch/beast/ ou http://library.epfl.ch/en/beast/ renvoient
    à BEAST. La variante en majuscules pour BEAST http://library.epfl.ch/BEAST/ fonctionne
    également. Des paramètres peuvent être ajouté pour effectuer directement une recherche:
        - renvoi par défaut vers: http://beast-epfl.hosted.exlibrisgroup.com/primo_library/libweb/action/search.do?vid=EPFL
        - l'option "?isbn=9781849965378,1849965374" effectue une recherche avancée sur
        les isbn dans l'onglet par défaut (livres + périodiques)
        - L'option "?query=" effectue une recherche simple dans l'onglet "tout"
        - L'option et "?record=" effectue une recherche simple dans l'onglet "tout" en ajoutant au préalable
        ebi01_prod devant et en vérifiant que les numéros contiennent 9 caractères. Si
        des caractères manquent, des 0 sont ajoutés.

    En séparant les isbn ou identifiants par des virgules, des requetes sur plusieurs
    isbns sont possibles.

    Les langues sont prises en compte. Dans Primo, les langues sont gérées dans les préférences.
    Pour obtenir la langue de la page d'origine, il faut:
        1. Détecter la langue de la page d'origine (présence de "/en/" ou non dans l'url)
        2. Ajouter "&prefLang=en_US" ou rien à l'url ("&prefLang=fr_FR" provoque des problèmess)

    La langue test sert à vérifier si le script doit être exécuté. Il ne l'est pas si
    "/edit/" se trouve dans le pathname.

UTILISATION:
    1. Compléter/adapter les patterns
    2. Donner une url et les patterns en paramètre au constructeur
    3. Récupérer l'url de redirection via obj.getDestUrl()
        Exemple: window.location.href = new Url_redirect(window.location.href, PATTERNS).getDestUrl();
*/


function epfl_library_beast_redirect_process_shortcode($attributes, $content = null){
    return '<script>
    <!-- inject:js -->
    "use strict";function Url_redirect(e,t){var r=this,n=t;r.params_to_analyse=Object.keys(n.params);var s=function(e){var t=e.indexOf("//")+2,r=e.indexOf("/",t),n=e.indexOf("?",r);return-1===n&&(r=e.length),t>=2&&r>=0?e.substring(r,n):""},a=function(e){var t=e.indexOf("?");return-1!==t?e.substring(t):""},i=function(e){for(var t=0;t<e.length;t++)e[t].match(/^\d{3,9}$/)&&(e[t].length<9&&(e[t]="0".repeat(9-e[t].length).concat(e[t])),e[t]="ebi01_prod".concat(e[t]));return e},l=function(e){for(var t="",r=0;r<e.length;r++)0===r?t=e[0]:t+="+OR+"+e[r];return t};r.url_src={url:e},Object.defineProperty(r.url_src,"search",{get:function(){return a(r.url_src.url)}}),Object.defineProperty(r.url_src,"pathname",{get:function(){return s(r.url_src.url)}}),Object.defineProperty(r,"paramsList",{get:function(){var e=[];if(r.url_src.search.length>0)for(var t=r.url_src.search.substring(1),n=t.split("&"),s=0;s<n.length;s++){var a=n[s].split("=");if(a.length>1){var i=a[0],l=a[1].split(",");e.push({key:i,values:l})}}return e}}),Object.defineProperty(r,"lang",{get:function(){for(var e=0;e<n.lang.length;e++)if(r.url_src.pathname.indexOf(n.lang[e].test)>-1&&!1===n.lang[e].default)return e;return n.lang.length-1}}),r.url_dest={},Object.defineProperty(r.url_dest,"key",{get:function(){for(var e=0;e<r.paramsList.length;e++)if(r.params_to_analyse.indexOf(r.paramsList[e].key)>-1)return r.paramsList[e].key;return null}}),Object.defineProperty(r.url_dest,"values",{get:function(){for(var e=0;e<r.paramsList.length;e++)if(r.paramsList[e].key===r.url_dest.key){var t=r.paramsList[e].values;return"record"===r.url_dest.key&&(t=i(t)),t}return[]}}),Object.defineProperty(r.url_dest,"path",{get:function(){return l(r.url_dest.values)}}),r.getDestUrl=function(){var e=n.default_url;return r.url_dest.key&&r.url_dest.path&&(e=n.params[r.url_dest.key]+r.url_dest.path),e+=n.lang[r.lang].path}}const PATTERNS={default_url:"https://beast-epfl.hosted.exlibrisgroup.com/primo-explore/search?vid=EPFL",params:{isbn:"https://beast-epfl.hosted.exlibrisgroup.com/primo-explore/search?nstitution=EPFL&search_scope=default_scope&vid=EPFL&query=isbn,contains,",record:"https://beast-epfl.hosted.exlibrisgroup.com/primo-explore/search?institution=EPFL&search_scope=all_blended&vid=EPFL&query=any,contains,",query:"https://beast-epfl.hosted.exlibrisgroup.com/primo-explore/search?institution=EPFL&search_scope=all_blended&vid=EPFL&query=any,contains,",issn:"https://beast-epfl.hosted.exlibrisgroup.com/primo-explore/search?institution=EPFL&search_scope=default_scope&vid=EPFL&query=issn,contains,",fulltext:"https://kissrv117.epfl.ch/beast/redirect?nebis_id="},lang:[{name:"ed",test:"/edit/",path:"",default:!1},{name:"en",test:"/en/",path:"&prefLang=en_US",default:!1},{name:"default",test:"",path:"",default:!0}]};var link=new Url_redirect(window.location.href,PATTERNS);0!==link.lang&&(window.location.href=link.getDestUrl());
    <!-- endinject -->
            </script>';
}
add_shortcode('epfl_library_beast_redirect', 'epfl_library_beast_redirect_process_shortcode');
?>
