<?php

  /* Custom error page for 403 errors */

 $msg = $_SERVER["UNIQUE_ID"];
?>
<html>
<head>
 <title>Access denied</title>
 <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
</head>
<body>
 <p>You cannot access this page for security reasons. Please contact 1234@epfl.ch and give them this reference number:</p>
 <p><strong><?php echo $msg?></strong></p>
</body>
</html>

