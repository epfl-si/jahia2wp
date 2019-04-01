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

/**
 * See https://www.php.net/manual/en/function.parse-str.php, 1st comment
 * Fix this : parse_str('foo=1&foo=2&foo=3'); -> $foo = array('foo' => '3'); but we want
 * $foo = array('foo' => array('1', '2', '3') );
 */
function proper_parse_str($str) {
  # result array
  $arr = array();

  # split on outer delimiter
  $pairs = explode('&', $str);

  # loop through each pair
  foreach ($pairs as $i) {
    # split into name and value
    list($name,$value) = explode('=', $i, 2);

    # if name already exists
    if( isset($arr[$name]) ) {
      # stick multiple values into an array
      if( is_array($arr[$name]) ) {
        $arr[$name][] = $value;
      }
      else {
        $arr[$name] = array($arr[$name], $value);
      }
    }
    # otherwise, simply stick it in a scalar
    else {
      $arr[$name] = $value;
    }
  }

  # return result array
  return $arr;
}
?>
