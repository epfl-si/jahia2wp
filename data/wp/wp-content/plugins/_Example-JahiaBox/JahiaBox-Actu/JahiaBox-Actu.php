<?php

/*
<<<<< ======= EXAMPLE ====== >>>>>>


Plugin Name: JahiaBox-Actu
Plugin URI: http://jahia.epfl.ch/edition-de-contenu/liste-des-boites#faq-388245
Description: WordPress version of Jahia Box "Actualit&eacute;s"
Version: 0.1


*/



include_once realpath(plugin_dir_path( __FILE__ )).DIRECTORY_SEPARATOR.'JahiaBox-Actu-Widget.php';

/* Include language files for auto-translation */
load_theme_textdomain( 'JahiaBox-Actu', realpath(plugin_dir_path(__FILE__)) .DIRECTORY_SEPARATOR. 'languages' );

/* Adding CSS file */
wp_enqueue_style('JahiaBox-Actu', plugin_dir_url(__FILE__).'style.css');

class JahiaBoxActu
{

      public function __construct()
      {
         /* Widget registration so it can be visible on the admin panel */
         add_action('widgets_init', function(){register_widget('JahiaBoxActuWidget');});

      }

}


new JahiaBoxActu();

?>