 <?php

   // Debug informations for the 403 page.

 ?>

 <table style="margin-top:10em">
 <table>
   <tr><th>Request ID</th><td><?php echo $request_id ?></td></tr>
   <tr><th>Request URI</th><td><?php echo htmlentities($request_uri) ?></td></tr>
   <tr><th>Remote IP</th><td><?php echo $request_ip ?></td></tr>
   <tr><th>Inside EPFL</th><td><?php echo $is_inside_epfl_string ?></td></tr>
   <tr><th>Error type</th><td><?php echo $error_type ?></td></tr>
 </table>