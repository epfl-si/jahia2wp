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

<?php if( is_page_template('page-templates/homepage-fullwidth.php') ) : ?>
  <div class="wrap">
	<div id="primary" class="content-area homepage-fullwidth">
		<main id="main" class="site-main" role="main">
			<?php
			while ( have_posts() ) : the_post();

				get_template_part( 'template-parts/page/content', 'front-page' );

				// If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;

			endwhile; // End of the loop.
			?>

		</main><!-- #main -->
	</div><!-- #primary -->
</div><!-- .wrap -->
<?php elseif( is_page_template('page-templates/homepage-fullwidth-faculty.php') ) : ?>
  <div class="wrap">
	<div id="primary" class="content-area homepage-fullwidth">
		<main id="main" class="site-main layout-faculty" role="main">
			<?php
			while ( have_posts() ) : the_post();

				get_template_part( 'template-parts/page/content', 'front-page' );

				// If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;

			endwhile; // End of the loop.
			?>

		</main><!-- #main -->
	</div><!-- #primary -->
</div><!-- .wrap -->

<?php else: ?>

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

<?php endif;
  get_footer();
