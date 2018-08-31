<?php

// register shortcake UI
add_action( 'register_shortcode_ui', 'epfl_toggle' );

function epfl_toggle() {

  // if supported delegate the rendering to the theme
  if (has_action("epfl_toggle_action")) {

    ob_start();

    try {

       do_action("epfl_toggle_action", $fields);

       return ob_get_contents();

    } finally {

        ob_end_clean();
    }

  // otherwise the plugin does the rendering
  } else {

      return 'You must activate the epfl theme';
  }

  $fields = [];
  for ( $i = 0; $i < 10; $i++) {
    array_push($fields, [
      'label' => '<hr><hr><h3>' . esc_html__('Title', 'epfl') . '</h3>',
      'attr' => 'label'.$i,
      'description' => esc_html__('The title of the collapsable', 'epfl'),
      'type' => 'text',
    ]);
    array_push($fields, [
      'label' => '<h2>' . esc_html__('Description', 'epfl') . '</h2>' ,
      'attr' => 'desc' . $i,
      'description' => esc_html__('Content shown when collapsable is opened', 'epfl'),
      'type' => 'textarea',
    ]);
  }

  global $iconDirectory;
	shortcode_ui_register_for_shortcode(
		'epfl_toggle',
		array(
      'label' => esc_html__( 'Toggle', 'epfl'),
      'attrs' => $fields,
      'listItemImage' => '<img src="' . $iconDirectory . 'toggle.png' . '">',
    )
	);
}