<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package epfl
 */

get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
  		
  		<?php if ( have_posts() ) : ?>
    		<header class="page-header">
    			<?php
    				the_archive_title( '<h1 class="page-title">', '</h1>' );
    				the_archive_description( '<div class="taxonomy-description">', '</div>' );
    			?>
    		</header><!-- .page-header -->
    	<?php endif; ?>
    	
    	<?php
    		if ( have_posts() ) : ?>
    			<?php
    			/* Start the Loop */
    			while ( have_posts() ) : the_post();
    
    				/*
    				 * Include the Post-Format-specific template for the content.
    				 * If you want to override this in a child theme, then include a file
    				 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
    				 */
    				get_template_part( 'template-parts/post/content', get_post_format() );
    
    			endwhile;
    
    			the_posts_pagination();
    
    		else :
    
    			get_template_part( 'template-parts/post/content', 'none' );
    
    		endif; ?>

		</main><!-- #main -->
	</div><!-- #primary -->
	<?php get_sidebar(); ?>
</div><!-- .wrap -->

<?php get_footer();
