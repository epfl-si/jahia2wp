<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package epfl
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
  
	<header class="entry-header">
		<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
	</header><!-- .entry-header -->
	
	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="post-thumbnail">
			<a href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( 'epfl-list-thumb' ); ?>
			</a>
		</figure><!-- .post-thumbnail -->
  <?php else : ?>
    <figure class="post-thumbnail">
			<a href="<?php the_permalink(); ?>">
				<img src="<?php bloginfo( 'template_directory' ); ?>/assets/images/placeholder.png">
			</a>
		</figure><!-- .post-thumbnail -->
  
	<?php endif; ?>
	
	<div class="entry-content">
		<div class="entry-meta">
  		<time class="entry-date published updated" datetime="<?php the_time('c') ?>"><?php the_time('d.m.Y'); ?></time>
  		<?php the_category(); ?>
    </div>
		<?php the_excerpt(); ?>
	</div><!-- .entry-content -->

	
</article><!-- #post-<?php the_ID(); ?> -->
