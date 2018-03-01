<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package epfl
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php

		if ( is_single() ) :
			the_title( '<h1 class="entry-title">', '</h1>' );
			
		else :
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); 
			
		endif;
		?>
	</header><!-- .entry-header -->

	<?php if ( has_post_thumbnail() && ! is_single() ) : ?>
		<figure class="post-thumbnail post-list-thumbnail">
			<a href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( 'epfl-list-thumb' ); ?>
			</a>
		</figure><!-- .post-thumbnail -->
  <?php elseif ( ! has_post_thumbnail() && ! is_single()  ) : ?>
    <figure class="post-thumbnail post-list-placeholder">
			<a href="<?php the_permalink(); ?>">
				<img src="<?php bloginfo( 'template_directory' ); ?>/assets/images/placeholder.png">
			</a>
		</figure><!-- .post-thumbnail -->
  <?php elseif ( has_post_thumbnail() && is_single() ) : ?>
		<figure class="post-thumbnail single-post-thumbnail">
			<a href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( 'epfl-featured-img' ); ?>
			</a>
		</figure><!-- .post-thumbnail -->
  
	<?php endif; ?>

	<div class="entry-content">
		<?php
		if ( is_home() || ( is_front_page() && is_home() ) ) : ?>
		<div class="entry-meta">
  		<time class="entry-date published updated" datetime="<?php the_time('c') ?>"><?php the_time('d.m.Y'); ?></time>
  		<?php the_category(); ?>
    </div>
		  <?php the_excerpt(); ?>
		<?php elseif ( is_single() ) :
  		/* translators: %s: Name of current post */
  		the_content( sprintf(
  			__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'epfl' ),
  			get_the_title()
  		) );
  
  		wp_link_pages( array(
  			'before'      => '<div class="page-links">' . __( 'Pages:', 'epfl' ),
  			'after'       => '</div>',
  			'link_before' => '<span class="page-number">',
  			'link_after'  => '</span>',
  		) );
    endif;
		?>
	</div><!-- .entry-content -->

	<?php
	if ( is_single() ) :?>
	
	<footer class="post-footer">
  	<div class="entry-meta">
    	<p class="post-author"><span class="label"><?php _e( 'Author', 'epfl' ); ?>:</span> <?php echo ('<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . get_the_author() . '</a></span>');?></p>
  	</div><!-- .entry-meta -->
	</footer>
	
	<?php endif; ?>

</article><!-- #post-## -->
