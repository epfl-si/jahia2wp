<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package epfl
 */

?>

	</div><!-- #content -->

		<footer id="colophon" class="site-footer" role="contentinfo">
			<div class="wrap">
  			<div class="footer-content">
    			<nav class="footer-navigation" role="navigation">
      			
      			<?php

                    /* Configuration */
                    $epfl_allowed_langs = array('en', 'fr');
                    $epfl_default_lang = 'en';
                    /* If Polylang installed */
                    if(function_exists('pll_current_language'))
                    {
                        /* Get current lang */
                         $epfl_cur_lang = pll_current_language('slug');
                         /* Check if current lang is supported. If not, use default lang*/
                         if(!in_array($epfl_cur_lang, $epfl_allowed_langs)) $epfl_cur_lang=$epfl_default_lang;

                    }
                    else /* Polylang not installed */
                    {
                        $epfl_cur_lang = $epfl_default_lang;
                    }


        			if( $epfl_cur_lang == "fr" ) {
          			    $legal_link  = "https://mediacom.epfl.ch/disclaimer-fr";
          			    $access_link = "https://www.epfl.ch/accessibility.fr.shtml";
        			} else {
          			    $legal_link  = "https://mediacom.epfl.ch/disclaimer-en";
                        $access_link = "https://www.epfl.ch/accessibility.en.shtml";
        			}
        			
        		?>
      			
      			<ul class="nav footer-nav">
        			<li class="legal-notice"><a href="<?php echo $legal_link; ?>"><?php _e("Legal notice", "epfl"); ?></a></li>
        			<li class="access"><a href="<?php echo $access_link; ?>"><?php _e("Accessibility", "epfl"); ?></a></li>
      			</ul>
      			<?php if ( has_nav_menu( 'footer_nav' ) ) :
  							wp_nav_menu( array(
  								'theme_location' => 'footer_nav',
  								'menu_class'     => 'nav footer-nav',
  								'depth'          => 1,
  								'link_before'    => '',
  								'link_after'     => '',
  							) );
    				endif;?>
    			</nav><!-- .footer-navigation -->
  				<p class="site-info">
    				<span class="update"><?php the_modified_date( 'd.m.Y', __( 'Last update: ', 'epfl' ), ''); ?></span>
    				<span class="copyright">&copy; EPFL <?php the_modified_date('Y'); ?></span>
  				</p>
  				<p class="site-admin">
    				<a href="<?php echo wp_login_url(); ?>" title="<?php _e( 'Log into site admin', 'epfl' ); ?>" class="admin-link"><?php _e( 'Login', 'epfl' ); ?></a>
  				</p>
  			</div><!-- .wrap -->
			</div><!-- .footer-content -->
		</footer><!-- #colophon -->
	</div><!-- .site-content-contain -->
</div><!-- #page -->

<script src="https://static.epfl.ch/latest/scripts/epfl-jquery-built.js"></script>
<?php wp_footer(); ?>

</body>
</html>
