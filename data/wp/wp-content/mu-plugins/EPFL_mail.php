<?php
add_filter( 'wp_mail_from', function ( $email ) {
  return 'noreply@epfl.ch';
} );