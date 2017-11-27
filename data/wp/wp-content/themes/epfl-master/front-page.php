<?php
/**
 * The front page template file
 *
 * If the user has selected a static page for their homepage, this is what will
 * appear.
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
    
    		<?php // Show the selected frontpage content.
    		if ( have_posts() ) :
    			while ( have_posts() ) : the_post();
    				get_template_part( 'template-parts/page/content', 'front-page' );
    			endwhile;
    		else : // I'm not sure it's possible to have no posts when this page is shown, but WTH.
    			get_template_part( 'template-parts/post/content', 'none' );
    		endif; ?>
    
    	</main><!-- #main -->
    </div><!-- #primary -->
    
    <?php get_sidebar(); ?>

  </div><!-- .grid -->
</div><!-- .wrap -->

<?php get_footer();
