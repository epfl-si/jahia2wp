<?php

  /* Custom error page for 403 errors */

  // request variables
  $request_id  = $_SERVER["UNIQUE_ID"];
  $request_uri = $_SERVER["REQUEST_URI"];
  $request_ip  = $_SERVER["REMOTE_ADDR"];

  // is the requested page the login or wp-admin?
  $is_login = strpos($request_uri, "wp-login.php") !== false;
  $is_wp_admin = strpos($request_uri, "wp-admin") !== false;

  // are we inside the EPFL campus?
  $is_inside_epfl = strpos($request_ip, "128.17") !== false;

  // the error type
  $error_type = "other";

  // check if
  if (($is_login || $is_wp_admin) && !$is_inside_epfl)
  {
    $error_type = "vpn";
  }
?>
<html>
<head>
 <title>Access denied</title>
 <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
</head>
<body>
 <p>You cannot access this page for security reasons.</p><p>Please contact 1234@epfl.ch and give them this reference number: <strong><?php echo $request_id?></strong>.</p>
 <table>
   <tr><th>Request ID</th><td><?php echo $request_id?></td></tr>
   <tr><th>Request URI</th><td><?php echo $request_uri?></td></tr>
   <tr><th>Remote IP</th><td><?php echo $request_ip?></td></tr>
   <tr><th>Inside EPFL</th><td><?php echo $is_inside_epfl?></td></tr>
   <tr><th>Error type</th><td><?php echo $error_type?></td></tr>
 </table>
</body>
</html>

