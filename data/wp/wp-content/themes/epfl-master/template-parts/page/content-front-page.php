<?php
/**
 * Template part for displaying page content in the front page.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package epfl
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class('homepage-content'); ?> >
	<div class="entry-content">
		<?php
			/* translators: %s: Name of current post */
			the_content( sprintf(
				__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'epfl' ),
				get_the_title()
			) );
		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->
