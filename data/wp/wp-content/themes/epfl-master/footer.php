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
          			
        			$currentLang = pll_current_language(locale);
        			
        			if( $currentLang == "fr_FR" ) {
          			$linkLegal = "https://mediacom.epfl.ch/disclaimer-fr";
                $linkAccess = "https://www.epfl.ch/accessibility.fr.shtml";
        			} else {
          			$linkLegal = "https://mediacom.epfl.ch/disclaimer-en";
                $linkAccess = "https://www.epfl.ch/accessibility.en.shtml";
        			}
        			
        		?>
      			
      			<ul class="nav footer-nav">
        			<li class="legal-notice"><a href="<?php echo $linkLegal; ?>"><?php _e("Legal notice", "epfl"); ?></a></li>
        			<li class="access"><a href="<?php echo $linkAccess; ?>"><?php _e("Accessibility", "epfl"); ?></a></li>
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
