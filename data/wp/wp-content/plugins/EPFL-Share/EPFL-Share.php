<?php
/*
Plugin Name: EPFL-Share
Description: Provide share button for EPFL websites
Version: 0.1
Author: <a href="Mailto:wwp-admin@epfl.ch">wwp-admin@epfl.ch</a>
*/


function EPFLShareEnqueueStyle()
{
  /* Adding CSS file */
  wp_enqueue_style('EPFL-Share', plugin_dir_url(__FILE__).'style.css');
  /* Adding JavaScript file */
  wp_enqueue_script('EPFL-Share', plugin_dir_url(__FILE__).'EPFL-Share.js');
}

add_action( 'wp_enqueue_scripts', 'EPFLShareEnqueueStyle' );

class EPFLShare
{
  /* List of networks on which EPFL websites can share */
  private $sharing_networks = array(
    'facebook' => '<li class="%li_class%"><i style="%style%" alt="Facebook" Title="Facebook" class="EPFLShare EPFLShareFacebookBackground" onclick=\'EPFLSharePopup("https://www.facebook.com/sharer/sharer.php?u=%encoded_post_url%")\'><ss style="%inner_style%" class="EPFLShareSvg EPFLShareFacebookSvg"></ss></i></li>',
    'twitter' => '<li class="%li_class%"><i style="%style%" alt="Twitter" Title="Twitter" class="EPFLShare EPFLShareTwitterBackground" onclick=\'EPFLSharePopup("http://twitter.com/intent/tweet?text=%post_title%&url=%encoded_post_url%&hashtags=epfl,epflcampus")\'><ss style="%inner_style%" class="EPFLShareSvg EPFLShareTwitterSvg"></ss></i></li>',
    'linkedin' => '<li class="%li_class%"><i style="%style%" alt="LinkedIn" Title="LinkedIn" class="EPFLShare EPFLShareLinkedinBackground" onclick=\'EPFLSharePopup("http://www.linkedin.com/shareArticle?mini=true&url=%encoded_post_url%&title=%post_title%")\'><ss style="%inner_style%" class="EPFLShareSvg EPFLShareLinkedinSvg"></ss></i></li>',
    'google_plus' => '<li class="%li_class%"><i style="%style%" alt="Google+" Title="Google+" class="EPFLShare EPFLShareGoogleplusBackground" onclick=\'EPFLSharePopup("https://plus.google.com/share?url=%encoded_post_url%")\'><ss style="%inner_style%" class="EPFLShareSvg EPFLShareGoogleplusSvg"></ss></i></li>',
    'email' => '<li class="%li_class%"><i style="%style%" alt="Email" Title="Email" class="EPFLShare EPFLShareEmailBackground" onclick="window.location.href = \'mailto:?subject=\' + decodeURIComponent(\'%post_title%\' ).replace(\'&\', \'%26\') + \'&body=\' + decodeURIComponent(\'%encoded_post_url%\' )"><ss style="display:block" class="EPFLShareSvg EPFLShareEmailSvg"></ss></i></li>'
  );


  /*
  Class constructor
  */
  public function __construct()
  {
    add_shortcode('EPFL-Share', array($this, 'shortcodeDisplay'));
  }


  /*
  Get http/https protocol at the website
  */
  public function get_http_protocol()
  {

    if( isset( $_SERVER['HTTPS'] ) && ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' )
    {
      return "https://";
    }
    else
    {
      return "http://";
    }
  }

  /*
  Sanitize post title
  */
  public function sanitize_post_title( $post_title )
  {

    $post_title = html_entity_decode( $post_title, ENT_QUOTES, 'UTF-8' );
    $post_title = rawurlencode( $post_title );
    $post_title = str_replace( '#', '%23', $post_title );
    $post_title = esc_html( $post_title );

    return $post_title;
  }

  /*
  Returns HTML code with buttons
  */
  public function getButtonsCode()
  {

    global $post;
    if ( is_front_page() )
    {
      $target_url = esc_url( home_url() );
    }
    elseif ( ! is_singular() && $type == 'vertical' )
    {
      $target_url = html_entity_decode( esc_url( the_champ_get_http() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
    }
    elseif ( isset( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] )
    {
      $target_url = html_entity_decode( esc_url( $this->get_http_protocol() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
    }
    elseif ( get_permalink( $post -> ID ) )
    {
      $target_url = get_permalink( $post -> ID );
    }
    else
    {
      $target_url = html_entity_decode( esc_url( $this->get_http_protocol() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
    }

    $post_title = $this->sanitize_post_title($post->post_title);

    $code = '<div class="EPFLShareHorizontal">'.
            '<ul class="EPFLShareUl">';

    foreach($this->sharing_networks as $network)
    {
      $code .= str_replace(
        array("%li_class%",
              "%style%",
              "%inner_style%",
              "%post_url%",
              "%encoded_post_url%",
              "%post_title%"),
        array("EPFLShareRound",
              "width:24px;height:24px;border-radius:999px;",
              "display:block;border-radius:999px;",
              $target_url,
              urlencode($target_url),
              $post_title),
        $network);

        $code .= "\n";
      }
      $code .= "</ul></div>";

      return $code;
    }


    /*
    Called when we use short code
    */
    public function shortcodeDisplay($atts, $content)
    {
      return $this->getButtonsCode();
    }
}

new EPFLShare();

/*
Function to call from theme to display share buttons
*/
function EPFLShareButtonDisplay()
{
  $instance = new EPFLShare();
  echo $instance->getButtonsCode();
}


?>
