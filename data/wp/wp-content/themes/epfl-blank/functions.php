<?php
/**
** activation theme
**/
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
 wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );

}

// Register header widget area

function epfl_blank_widgets_init() {
  
  register_sidebar( array(
      'name'          => 'Header widgets',
      'id'            => 'header-widgets',
      'description'   => 'Widgets présents au-dessus du header. Utiliser uniquement le widget "HTML personnalisé" pour ajouter votre bannière.',
      'before_widget' => '<div id="%1$s" class="widget %2$s">',
  		'after_widget'  => '</div>',
  		'before_title'  => '<h3 class="widget-title">',
  		'after_title'   => '</h3>'
  ) );
}
add_action( 'widgets_init', 'epfl_blank_widgets_init' );

?>