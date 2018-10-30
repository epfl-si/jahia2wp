<?php

/**
 * Plugin Name: EPFL People shortcode
 * Description: display results from people. This shortcode is intended to be used only with the new
 * web2018 theme, so it's not activated by default
 * Version: 1.0
 * License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/

require_once 'shortcake-config.php';

/**
 * Return int > 0 if $person_a->nom > $person_b->nom.
 */
function epfl_people_person_compare($person_a, $person_b) {

  // normalize replace accents
  return strnatcmp(Utils::normalize($person_a->nom), Utils::normalize($person_b->nom));
}

/**
 * Process the shortcode
 */
function epfl_people_2018_process_shortcode( $attributes, $content = null )
{
  $attributes = shortcode_atts( array(
       'units'   => '',
       'scipers' => '',
       'columns' => '3',
    ), $attributes );

   // sanitize the parameters
  $units    = sanitize_text_field( $attributes['units'] );
  $scipers  = sanitize_text_field( $attributes['scipers'] );
  $columns  = sanitize_text_field( $attributes['columns'] );

  if ($columns !== 'list') {
    $columns = (is_numeric($columns) && intval($columns) <= 3 && intval($columns) >= 1) ? $columns : 3;
  }

  if ("" === $units and "" === $scipers) {
    return Utils::render_user_msg("People shortcode: Please check required parameters");
  }

  ("" !== $units) ? $parameter['units'] = $units : $parameter['scipers'] = $scipers;

  if (function_exists('pll_current_language')) {
    $current_language = pll_current_language();
    if(isset($current_language)) {
      $parameter['lang'] = $current_language;
    }
  }

  // the web service we use to retrieve the data
  $url = "https://people.epfl.ch/cgi-bin/wsgetpeople/";
  $url = add_query_arg($parameter, $url);

  // retrieve the data in JSON
  $items = Utils::get_items($url);

  if (false === $items) {
    return;
  }

  $persons = [];

  // Create a persons list
  foreach ($items as $item) {
    $persons[] = $item;
  }
  // Sort persons list alphabetically
  usort($persons, 'epfl_people_person_compare');

  // if supported delegate the rendering to the theme
  if (has_action("epfl_people_action"))
  {
    ob_start();

    try
    {
      do_action_ref_array("epfl_people_action", [$persons, $columns]);
      return ob_get_contents();
    }
    finally
    {
      ob_end_clean();
    }
  }   
  // otherwise the plugin does the rendering
  else 
  {
    return 'You must activate the epfl theme';
  }
}

// init action
add_action( 'init', function()
{
  // define the shortcode
  add_shortcode('epfl_people_2018', 'epfl_people_2018_process_shortcode');
});

add_action( 'register_shortcode_ui', ['ShortCakePeopleConfig', 'config'] );

?>
