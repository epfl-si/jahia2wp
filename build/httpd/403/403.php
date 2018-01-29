<?php

  // Custom 403 error page.
  //
  // This script finds the error type (e.g. "default" or "vpn")
  // and then includes 403-template.php which in turn includes
  // 403-{$error_type}.php.

  // request variables
  $request_id  = $_SERVER["UNIQUE_ID"];
  $request_uri = $_SERVER["REQUEST_URI"];
  $request_ip  = $_SERVER["REMOTE_ADDR"];

  // is the requested page the login or wp-admin?
  $is_login = strpos($request_uri, "wp-login") !== false;
  $is_wp_admin = strpos($request_uri, "wp-admin") !== false;

  // is the user's IP inside the EPFL campus?
  $is_inside_epfl = strpos($request_ip, "128.17") !== false;
  $is_inside_epfl_string = $is_inside_epfl ? "Yes" : "No";

  // the error type
  $error_type = "default";

  // check if the error is that the user is trying to access the
  // administration pages from outside the EPFL campus
  if ((($is_login || $is_wp_admin) && !$is_inside_epfl))
  {
    $error_type = "vpn";
  }

  include ("403-template.php");
?>