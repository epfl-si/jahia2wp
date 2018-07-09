<?php
function get_mathjax_config() {
    $to_return = '';
    $to_return .= '<script type="text/x-mathjax-config">';
    $to_return .= '    MathJax.Hub.Config(';
    $to_return .= '        {';
    $to_return .= '            tex2jax: {';
    $to_return .= "                inlineMath: [['$','$'], ['\\\\(','\\\\)']],";
    $to_return .= '                processClass: "tex2jax_process",';
    $to_return .= '                ignoreClass: "no-tex2jax_process",';
    $to_return .= '                }';
    $to_return .= '        }';
    $to_return .= '    );';
    $to_return .= '</script>';

    return $to_return;
}
?>