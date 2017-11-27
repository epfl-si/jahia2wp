<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package epfl
 */

get_header(); ?>

<div class="wrap">
  <div class="grid">
  	<div id="primary" class="content-area col col-l-8">
  		<main id="main" class="site-main" role="main">
  			<?php
  			while ( have_posts() ) : the_post();
  
  				get_template_part( 'template-parts/page/content', 'page' );
  
  				// If comments are open or we have at least one comment, load up the comment template.
  				if ( comments_open() || get_comments_number() ) :
  					comments_template();
  				endif;
  
  			endwhile; // End of the loop.
  			?>
  
  		</main><!-- #main -->
  	</div><!-- #primary -->
    
    <?php get_sidebar(); ?>

  </div><!-- .grid -->
</div><!-- .wrap -->

<?php get_footer();
