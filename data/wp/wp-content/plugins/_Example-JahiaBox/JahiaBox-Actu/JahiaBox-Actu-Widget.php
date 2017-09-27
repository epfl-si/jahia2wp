<?php

/*

   <<<<< ======= EXAMPLE ====== >>>>>>
*/

class JahiaBoxActuWidget extends WP_Widget
{

   public function __construct()
   {
      $options = array('classname' => 'JahiaBoxActuWidget',
                       'description' => __('JahiaBox - News', 'JahiaBox-Actu'));
                       
      parent::__construct('JahiaBoxActu', 'JahiaBox-Actu', $options);
      
      /* Adding Shortcode */
      add_shortcode('JahiaBox-Actu', array($this, 'shortcodeDisplay'));
   }

    
   
   /*
      Display Widget
      
      $args       -> Display parameters
      $instance   -> Widget parameters stored in DB 
   
   */
   public function widget($args, $instance)
   {
      //echo "<div class=\"JahiaBoxActu\">Actu</div>";
      echo $args['before_widget'];
      echo $args['before_title'];
      echo apply_filters('widget_title', $instance['title']);
      echo $args['after_title'];

      $args = array(
          'timeout'     => 5,
          'redirection' => 5,
          'httpversion' => '1.0',
          'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
          'blocking'    => true,
          'headers'     => array(),
          'cookies'     => array(),
          'body'        => null,
          'compress'    => false,
          'decompress'  => true,
          'sslverify'   => true,
          'stream'      => false,
          'filename'    => null); 

          
      $response = wp_remote_get($instance['url'], $args);          

    ?>

    <div class="JahiaBoxActu"><?php echo $response['body']; ?> </div>

    <?php

      echo $args['after_widget'];
   } 

   /*
      Called when shortcode is used
      
      $atts       -> Array with configuration parameter
      $content    -> The content entered between 2 shortcode tags (if used).
   */   
   public function shortcodeDisplay($atts, $content)
   {
      /* Call to widget() function */
      the_widget( get_class(), $atts);
   }
   
   
   
   /*
      Display form to enter widget configuration 
      
      $instance   -> Widget parameters stored in DB 
   */
   public function form($instance)
   {
      $title = isset($instance['title']) ? $instance['title'] : '';
      $url   = isset($instance['url']) ? $instance['url'] : '';

    ?>

    <p>
        <label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'JahiaBox-Actu' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo  $title; ?>" />
        
        <label for="<?php echo $this->get_field_name( 'url' ); ?>"><?php _e( 'URL:', 'JahiaBox-Actu' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'url' ); ?>" name="<?php echo $this->get_field_name( 'url' ); ?>" type="text" value="<?php echo  $url; ?>" />
    </p>
    <?php      
   }


}

?>