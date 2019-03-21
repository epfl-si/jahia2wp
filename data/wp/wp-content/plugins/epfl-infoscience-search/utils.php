<?php

Class InfoscienceSearchUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }
}

/**
 * Return a user message
 */
function render_user_msg($msg) {
    $html = '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
    $html .= '<strong> Warning </strong>' . $msg;
    $html .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
    $html .= '  <span aria-hidden="true">&times;</span>';
    $html .= '</button>';
    $html .= '</div>';
    return $html;
}

?>
