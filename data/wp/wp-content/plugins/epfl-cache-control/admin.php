<?php

if ( !defined('ABSPATH') || !is_admin() ) {
    header( 'HTTP/1.1 403 Forbidden' );
    exit(   'HTTP/1.1 403 Forbidden' );
}

function cache_control_admin_action_links( $links ) {
    return array_merge(
        array('settings' => '<a href="' . admin_url( 'options-general.php?page=cache_control' ) . '">' . esc_html__( 'Settings', 'cache-control' ) . '</a>',
        ),
        $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( 'cache-control/cache-control.php' ),
            'cache_control_admin_action_links' );

function cache_control_admin_actions( $links, $file ) {
    if ( plugin_basename( 'cache-control/cache-control.php' ) === $file ) {
        return array_merge(
            $links,
            array(
                'documentation' => '<a href="https://www.ctrl.blog/projects/wp-cache-control-documentation">Documentation</a>'
	      ));
    }
    return $links;
}
add_filter( 'plugin_row_meta', 'cache_control_admin_actions', 10, 2 );

function cache_control_add_options_submenu_page() {
    add_submenu_page(
        'options-general.php',        // append to Settings sub-menu
        'Cache-Control Options',      // title
        'Cache-Control',              // menu label
        'manage_options',             // required role
        'cache_control',              // options-general.php?page=cache_control
        'cache_control_options_page'  // display page callback
    );
}

add_action( 'admin_menu', 'cache_control_add_options_submenu_page' );

function cache_control_install() {
    global $cache_control_options;
    foreach ($cache_control_options as $key => $option) {
        add_option( 'cache_control_' . $option['id'] . '_max_age',    $option['max_age'] );
        add_option( 'cache_control_' . $option['id'] . '_s_maxage',   $option['s_maxage'] );
        add_option( 'cache_control_' . $option['id'] . '_staleerror', $option['staleerror'] );
        add_option( 'cache_control_' . $option['id'] . '_stalereval', $option['stalereval'] );
        if ( isset( $option['paged'] ) )
            add_option( 'cache_control_' . $option['id'] . '_paged', $option['paged'] );
}   }

register_activation_hook( __FILE__, 'cache_control_install' );

function cache_control_uninstall() {
    global $cache_control_options;
    foreach ($cache_control_options as $key => $option) {
        delete_option( 'cache_control_' . $option['id'] . '_max_age' );
        delete_option( 'cache_control_' . $option['id'] . '_s_maxage' );
        delete_option( 'cache_control_' . $option['id'] . '_staleerror' );
        delete_option( 'cache_control_' . $option['id'] . '_stalereval' );
        if ( isset( $option['paged'] ) )
            delete_option( 'cache_control_' . $option['id'] . '_paged' );
        if ( isset( $option['_mmulti'] ) )
            delete_option( 'cache_control_' . $option['id'] . '_mmulti' );
}   }

register_uninstall_hook( __FILE__, 'cache_control_uninstall' );

function cache_control_options_page() {
    global $cache_control_options;
    if ( ! isset( $_REQUEST['settings-updated'] ) )
          $_REQUEST['settings-updated'] = false; ?>

     <div class="wrap">
           <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
          <?php if ( isset( $_POST ) && count( $_POST ) > 10 ) {
              foreach ($cache_control_options as $key => $option) {
                  $option_keys = array( 'cache_control_' . $option['id'] . '_max_age',
                                        'cache_control_' . $option['id'] . '_s_maxage',
                                        'cache_control_' . $option['id'] . '_staleerror',
                                        'cache_control_' . $option['id'] . '_stalereval',
                                        'cache_control_' . $option['id'] . '_paged',
                                        'cache_control_' . $option['id'] . '_mmulti'
                  );
                  foreach ( $option_keys as $key => $option_key ) {
                      if ( isset( $_POST[$option_key] ) && is_int( intval( $_POST[$option_key] ) ) )
                          update_option( $option_key, intval( $_POST[$option_key] ) );
                      elseif ( !isset( $_POST[$option_key] ) && get_option( $option_key, FALSE ) !== FALSE )  // checkbox
                          update_option( $option_key, intval( 0 ) );
              }   }   } ?>
          <div id="poststuff">
               <div id="post-body">
                    <div id="post-body-content">
                         <p>Use this page to configure acceptable freshness and caching behavior for your website. Please review each setting carefully and make sure it’s set approperiately for your website.</p>
                         <p><a href="https://www.ctrl.blog/projects/wp-cache-control-documentation">Extensive documentation</a> is available.</p>
                         <?php if ( isset( $_POST ) && count( $_POST ) > 10 ) { ?><p style="color:green">Saved.</p> <?php } ?>
                         <form method="post" action="options-general.php?page=cache_control">
                              <?php settings_fields( 'wporg_options' ); ?>
                              <?php $options = get_option( 'wporg_hide_meta' ); ?>
                              <table class="form-table">
                                    <tr>
                                        <th></th>
                                        <th scope="column">max-age</th>
                                        <th scope="column">s-maxage</th>
                                        <th scope="column">stale-if-error</th>
                                        <th scope="column">stale-while-revalidate</th>
                                        <th scope="column">Pagination factor</th>
                                        <th scope="column">Old age multiplier</th>
                                    </tr>
                                    <?php foreach ($cache_control_options as $key => $option) { ?>
                                    <tr>
                                        <th scope="row"><?php print $option['name'] ?></th>
                                        <td><input type="number" name="cache_control_<?php print $option['id']; ?>_max_age" id="cache_control_<?php print $option['id']; ?>_max_age" value="<?php print get_option( 'cache_control_' . $option['id'] . '_max_age', $option['max_age'] ) ?>"></td>
                                        <td><input type="number" name="cache_control_<?php print $option['id']; ?>_s_maxage" id="cache_control_<?php print $option['id']; ?>_s_maxage" value="<?php print get_option( 'cache_control_' . $option['id'] . '_s_maxage', $option['s_maxage'] ) ?>"></td>
                                        <td><input type="number" name="cache_control_<?php print $option['id']; ?>_staleerror" id="cache_control_<?php print $option['id']; ?>_staleerror" value="<?php print get_option( 'cache_control_' . $option['id'] . '_staleerror', $option['staleerror'] ) ?>"></td>
                                        <td><input type="number" name="cache_control_<?php print $option['id']; ?>_stalereval" id="cache_control_<?php print $option['id']; ?>_stalereval" value="<?php print get_option( 'cache_control_' . $option['id'] . '_stalereval', $option['stalereval'] ) ?>"></td>
                                        <?php if ( isset( $option['paged'] ) ) { ?>
                                        <td><input type="number" name="cache_control_<?php print $option['id']; ?>_paged" id="cache_control_<?php print $option['id']; ?>_paged" value="<?php print get_option( 'cache_control_' . $option['id'] . '_paged', $option['paged'] ) ?>"></td>
                                        <?php } else { ?><td></td><?php } ?>
                                        <?php if ( isset( $option['mmulti'] ) ) { ?>
                                        <td><label><input type="checkbox" name="cache_control_<?php print $option['id']; ?>_mmulti" id="cache_control_<?php print $option['id']; ?>_mmulti" value="1" <?php if(get_option( 'cache_control_' . $option['id'] . '_mmulti', $option['mmulti'] ) == 1) print ' checked="checked"'; ?>> Set by last modified/comment date.</label></td>
                                        <?php } else { ?><td></td><?php } ?>
                                    </tr>
                                    <?php } ?>
                              </table>
                              <br/>
                              <input type="submit" value="Save" class="button-primary">
                              <?php if ( isset( $_POST ) && count( $_POST ) > 10 ) { ?><p style="color:green;display:inline;margin-left: 16px;">Saved.</p> <?php } ?>
                         </form>
                         
                         <p>All values are set <strong style="color:blue">in seconds</strong>.</p>

                         <h3>Example HTTP response header</h3>
                         <pre>Cache-Control: max-age=$max-age, s_maxage=$s-maxage, stale-if-error=$stale-if-error, stale-while-revalidate=$stale-while-revalidate</pre>

                         <h3>Documentation</h3>
                         
                         <p>See the <a href="https://www.ctrl.blog/projects/wp-cache-control-documentation">extensive documentation</a> for details about each option, and hints on how to best configure caching for your website.</p>
                    </div>
               </div>
          </div>
     </div>
     <style>.form-table {width: auto;margin-left:-6px;} tr:nth-child(even) {background: #fff} tr th:first-of-type {padding-left: 6px;} tr td:nth-of-type(1) input {width: 120px} tr td:nth-of-type(2) input {width: 100px;} tr td:nth-of-type(3) input, tr td:nth-of-type(4) input, tr td:nth-of-type(5) input {width: 80px;} th[scope="column"]:not(:first-of-type) {padding: 15px 10px; width: auto;}</style>
<?php }

