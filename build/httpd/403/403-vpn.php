<?php

  // Message shown for the "vpn" error type.

?>

<p>
  You cannot access the administration pages from outside EPFL.
</p>

<p>
  Please use <a href="https://epnet.epfl.ch/Remote-Internet-Access">a VPN client</a>
  to connect.
</p>

<?php if($ip_v == "ipv6") : ?>
<p>
  <strong>Important note:</strong> you have an IPv6 address. If you are using
  VPN, make sure your IPv6 traffic also goes through the VPN, or use IPv4.
</p>
<?php endif; ?>

<p>
  If you have troubles using VPN, you can
  <a href="mailto:1234@epfl.ch?Subject=VPN%20access">contact the help desk.</a>
</p>