<?php
/*
 * Plugin Name: EPFL General settings
 * Description: General settings for allow users
 * Version:     0.6
 * Author:      <a href="mailto:wwp-admin@epfl.ch">wwp-admin@epfl.ch</a>
 * Text Domain: EPFL-settings
 */

function EPFL_settings_load_plugin_textdomain() {
  // wp-content/plugins/plugin-name/languages/EPFL-settings-fr_FR.mo
  load_plugin_textdomain( 'EPFL-settings', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'EPFL_settings_load_plugin_textdomain' );


function validate_breadcrumb($input) {
    $breadcrumb_option_format = "/(^\[[^\|\[\]]+\|[^\|\[\]]+\]){1}(>(\[[^\|\[\]]+\|[^\|\[\]]+\]){1})*$/";
    $matched = preg_match($breadcrumb_option_format, $input);

    if ($matched !== 1 && $input !== '') {
        $error_message = __ ('Incorrect breadcrumb', 'EPFL-settings');
        add_settings_error(
            'epfl:custom_breadcrumb',
            'validationError',
            $error_message,
            'error');
    }
    // Delete cache entry to force reload of breqdcrumb when option is updated
    delete_transient('base_breadcrumb');
    return $input;
}

function EPFL_settings_register_settings() {
   add_option( 'EPFL_settings_option_name', 'This is my option value.');
   register_setting( 'EPFL_settings_options_group', 'EPFL_settings_option_name', 'EPFL_settings_callback' );
   register_setting( 'EPFL_settings_options_group', 'blogname' );
   register_setting( 'EPFL_settings_options_group', 'blogdescription' );
   register_setting( 'EPFL_settings_options_group', 'WPLANG' );
   register_setting( 'EPFL_settings_options_group', 'epfl:custom_breadcrumb', 'validate_breadcrumb');
}
add_action( 'admin_init', 'EPFL_settings_register_settings' );

/**
 * If we don't have any custom tags in db, fetch it trough our sites repository
 * 
 * @return list of tags in the format "Tag1,Tag2,Tag3"
 */
function epfl_fetch_site_tags () {
  # how to fetch site name ? acronym, tag or what ?
  # going for site url
  $site_url = get_site_url();
  $tags = NULL;

  if ( (defined('WP_DEBUG') && WP_DEBUG) || false === ( $tags = get_transient( 'epfl_custom_tags' ) ) ) {
    // this code runs when there is no valid transient set
    // get the id of this site
    $tag_provider_url = 'https://wp-veritas.epfl.ch/api';
    $tags_and_urls = []; // [[tag, url], ...]
    $site = [];

    $url_site_to_id = $tag_provider_url . '/sites?site_url=' . rawurlencode($site_url);
    $site = Utils::get_items($url_site_to_id);

    #VERIFY_THIS:
    if (!empty($site)) {
      $site_id = $site[0]->id;
      $site_tags_url = $tag_provider_url . '/sites/' . $site_id . '/tags';
      $tags = Utils::get_items($site_tags_url);

      #VERIFY_THIS:
      foreach ($tags as $tag) {
        $tags_and_urls[] = [$tag->name, $tag->url];
      }
    }

    if ($tags_and_urls) {
      set_transient( 'epfl_custom_tags', $tags_and_urls, 4 * HOUR_IN_SECONDS );
      # persist into options too, as a fallback
      update_option('epfl:custom_tags', $tags_and_urls);
    } else {
      # no tags from remote server ? try to fetch the one in the local option)
      if (false === ( $tags_and_urls = get_option('epfl:custom_tags') ) ) {
        return NULL;
      }
    }
  }

  return $tags;
}

//add menu EPFL settings under settings menu
function EPFL_settings_register_options_page() {
  add_options_page('EPFL settings', 'EPFL settings', 'manage_options', 'EPFL_settings', 'EPFL_settings_options_page');
}
add_action('admin_menu', 'EPFL_settings_register_options_page');

//config settings page
function EPFL_settings_options_page()
{
?>
  <div>
  <?php screen_icon(); ?>
  <h2><?php echo __("General Settings", 'EPFL-settings');?></h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'EPFL_settings_options_group' ); ?>
  <?php $lang = get_site_option( 'WPLANG' );  $languages = get_available_languages();?>

  <table class="form-table">
    <tbody><tr>
      <th scope="row"><label for="blogname"><?php echo __ ("Site Title", 'EPFL-settings');?></label></th>
      <td><input type="text" id="blogname" name="blogname" value="<?php echo get_option('blogname'); ?>" />
        <p class="description" id="tagline-description"><?php echo __ ("Acronym (example : IC)", 'EPFL-settings');?></p>
        <p class="description" id="tagline-description"><a href="<?php admin_url(); ?> admin.php?page=mlang_strings"><?php echo __ ("Site Title translation", 'EPFL-settings');?></a></p></td>
    </tr>
    <tr>
      <th scope="row"><label for="blogdescription"><?php echo __ ("Tagline", 'EPFL-settings');?></label></th>
      <td><input type="text" id="blogdescription" name="blogdescription" value="<?php echo get_option('blogdescription'); ?>" />
      <p class="description" id="tagline-description"><?php echo __ ("Explicit name (example : School of Computer and Communication Sciences)", 'EPFL-settings');?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="WPLANG"><?php echo __ ("Site administration Language", 'EPFL-settings'    );?></label></th>
      <td><?php wp_dropdown_languages(array('name' => 'WPLANG', 'id' => 'site-language', 'selected' => $lang, 'languages' => $languages, 'show_available_translations' => false)); ?></td>
    </tr>
        <tr>
      <th scope="row"><label for="plugin:epfl_accred:unit"><?php echo __ ("Accred Unit", 'EPFL-settings');?></label></th>
      <td><label for="plugin:epfl_accred:unit"><?php echo get_option('plugin:epfl_accred:unit'); ?></label></th>
      <p class="description" id="tagline-description"><?php echo __ ("Accred unit allowed to manage this Wordpress site", 'EPFL-settings');?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="epfl:custom_breadcrumb"><?php echo __ ("Custom Breadcrumb", 'EPFL-settings');?></label></th>
      <td><input type="text" id="epfl:custom_breadcrumb" name="epfl:custom_breadcrumb" value="<?php echo get_option('epfl:custom_breadcrumb'); ?>" />
      <p class="description" id="tagline-description"><?php echo __ ("Format [label|url]>[label|url]>[label|url] (Example : [EPFL|www.epfl.ch]>[ENAC|www.enac.ch])", 'EPFL-settings');?></p>
      </td>
    </tr>
  </table>
  <?php  submit_button(); ?>
  </form>
  </div>
<?php
} ?>
