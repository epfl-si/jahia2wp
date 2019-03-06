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
 * Sort an array on the key with another array
 * Used for the sorting by sciper list
 */
function epfl_people_sortArrayByArray($data,$orderArray) {
  $result = array(); // result array
  foreach($orderArray as $key => $value) { // loop
      foreach ($data as $k => $val) {
          if ($data[$k]->sciper === $value) {
             $result[$key] = $data[$k];
          }
      }
  }
  return $result;
}

/**
 * Process the shortcode
 */
function epfl_people_2018_process_shortcode( $attributes, $content = null )
{
  $attributes = shortcode_atts( array(
       'units'    => '',
       'scipers'  => '',
       'function' => '',
       'columns'  => '3',
    ), $attributes );


  // if supported delegate the rendering to the theme
  if(!has_action("epfl_people_action"))
  {
    return Utils::render_user_msg('You must activate the epfl theme');
  }

  // sanitize the parameters
  $units    = sanitize_text_field( $attributes['units'] );
  $scipers  = sanitize_text_field( $attributes['scipers'] );
  $function = sanitize_text_field( $attributes['function']);
  $columns  = sanitize_text_field( $attributes['columns'] );

  if ($columns !== 'list') {
    $columns = (is_numeric($columns) && intval($columns) <= 3 && intval($columns) >= 1) ? $columns : 3;
  }

  if ("" === $units and "" === $scipers) {
    return Utils::render_user_msg("People shortcode: Please check required parameters");
  }

  ("" !== $units) ? $parameter['units'] = $units : $parameter['scipers'] = $scipers;

  if ("" !== $function) {    
    if (strpos($function, ",") !== false) {
      $functions = explode(",", $function);
      $result = "";
      foreach ($functions as $function) {
        if ($result === "") {
          $result = $function;
        } else {
          $result = $result . '+or+' . $function;
        }
      }
      $parameter['position'] = $result;
    } else {
      $parameter['position'] = $function;
    }
  }

  if (function_exists('pll_current_language')) {
    $current_language = pll_current_language();
    if ($current_language != false) {
      $parameter['lang'] = $current_language;
    }
  }

  // the web service we use to retrieve the data
  $url = "https://people.epfl.ch/cgi-bin/wsgetpeople/";
  $url = add_query_arg($parameter, $url);
  
  // retrieve the data in JSON
  $items = Utils::get_items($url);

  if (false === $items) {
    return Utils::render_user_msg("People shortcode: Error retrieving items");
  }

  // If webservice returns an error
  if(property_exists($items, 'Error'))
  {
    return Utils::render_user_msg("People shortcode: Webservice error: ".$items->Error->text);
  }

  // Create a persons list
  $persons = [];
  foreach ($items as $item) {
    $persons[] = $item;
  }

  if ("" !== $units) {
    // Sort persons list alphabetically when units
    usort($persons, 'epfl_people_person_compare');
  } else {
    // Respect given order when sciper
    $scipers =  array_map('intval', explode(',', $parameter['scipers']));
    $persons = epfl_people_sortArrayByArray($persons, $scipers);
  }
  
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

// init action
add_action( 'init', function()
{
  // define the shortcode
  add_shortcode('epfl_people_2018', 'epfl_people_2018_process_shortcode');
});

add_action( 'register_shortcode_ui', ['ShortCakePeopleConfig', 'config'] );

?>
