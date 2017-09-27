<?php
  
if ( ! function_exists( 'epfl_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function epfl_setup() {
  
  /**
    * Register menus
    */
    
	register_nav_menus( array(
		'sidebar_nav' => __( 'Sidebar menu', 'epfl' ),
		'footer_nav' => __( 'Footer menu', 'epfl' )
	) );
	
	/**
    * Set up My Child Theme's textdomain.
    *
    * Declare textdomain for this child theme.
    * Translations can be added to the /languages/ directory.
    */
    
    load_child_theme_textdomain( 'epfl', get_stylesheet_directory() . '/languages' );
    
} 
endif;
add_action( 'after_setup_theme', 'epfl_setup' );

/**
* Enqueue theme styles
*
* First we remove regular theme stylesheet and enqueue it again in a function. This allows to enqueue the child theme stylesheet *after* the parent theme's, which is best to keep a low selector specificity. 
*/ 

// Remove Twenty Seventeen styles

function dequeue_twentyseventeen_styles() {
    wp_dequeue_style( 'twentyseventeen-style' );
        wp_deregister_style( 'twentyseventeen-style' );
}
add_action( 'wp_print_styles', 'dequeue_twentyseventeen_styles' );

// enqueue styles for child theme
// @ https://digwp.com/2016/01/include-styles-child-theme/

function enqueue_theme_styles() {
	
	// enqueue parent styles
	wp_enqueue_style('parent-styles', get_template_directory_uri() .'/style.css');
	
	// enqueue extra stylesheets
	wp_enqueue_style('font-awesome', get_stylesheet_directory_uri() .'/assets/css/font-awesome.min.css');
	wp_enqueue_style('grid', get_stylesheet_directory_uri() .'/assets/css/stylisticss.grid.css');
	
	// enqueue child styles
	wp_enqueue_style('child-styles', get_stylesheet_uri() );
	wp_enqueue_style('compiled-styles', get_stylesheet_directory_uri() .'/assets/css/style.css');
	
}
add_action('wp_enqueue_scripts', 'enqueue_theme_styles', 10000000001 );

// Enqueue scripts

function epfl_scripts() {
	
	wp_enqueue_script( 'modernizr', get_stylesheet_directory_uri() .'/assets/js/modernizr.js', array(), '20151215', false );
	wp_enqueue_script( 'epfl-scripts', get_stylesheet_directory_uri() .'/assets/js/main.js', array(), '20151215', true );

}
add_action( 'wp_enqueue_scripts', 'epfl_scripts' );

// Dequeue Twenty Seventeen Fonts
function dequeue_fonts() {
    wp_dequeue_style( 'twentyseventeen-fonts' );
        wp_deregister_style( 'twentyseventeen-fonts' );
}
add_action( 'wp_print_styles', 'dequeue_fonts' );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
 
function unregister_parent_sidebars(){

	// Unregister Twenty Seventeen Sidebars
	unregister_sidebar( 'sidebar-1' );
}
add_action( 'widgets_init', 'unregister_parent_sidebars', 11 );

function epfl_widgets_init() {
  
  register_sidebar( array(
      'name'          => 'Homepage widgets',
      'id'            => 'homepage-widgets',
      'description'   => 'Widgets présents uniquement sur la homepage',
      'before_widget' => '<section id="%1$s" class="widget %2$s">',
  		'after_widget'  => '</section>',
  		'before_title'  => '<h3 class="widget-title">',
  		'after_title'   => '</h3>'
  ) );
  
  register_sidebar( array(
      'name'          => 'Page widget',
      'id'            => 'page-widgets',
      'description'   => 'Widgets présents sur toutes les pages, y compris la homepage',
      'before_widget' => '<section id="%1$s" class="widget %2$s">',
  		'after_widget'  => '</section>',
  		'before_title'  => '<h3 class="widget-title">',
  		'after_title'   => '</h3>'
  ) );
}
add_action( 'widgets_init', 'epfl_widgets_init' );

// add temp shortcode mp4_video button to Tinmce

function mp4_video($atts, $content = null) {
  extract(shortcode_atts(array(
    "src" => '',
    "width" => '',
    "height" => ''
  ), $atts));
  return '<video src="'.$src.'" width="'.$width.'" height="'.$height.'" controls autobuffer>';
}
add_shortcode('mp4', 'mp4_video');


function tiny_mce_add_buttons( $plugins ) {
  $plugins['mp4'] = get_template_directory_uri() . '/../epfl/js/tiny-mce/tiny-mce.js';
  return $plugins;
}

function tiny_mce_register_buttons( $buttons ) {
  $newBtns = array(
    'mp4'
  );
  $buttons = array_merge( $buttons, $newBtns );
  return $buttons;
}

add_action( 'init', 'tiny_mce_new_buttons' );

function tiny_mce_new_buttons() {
  add_filter( 'mce_external_plugins', 'tiny_mce_add_buttons' );
  add_filter( 'mce_buttons', 'tiny_mce_register_buttons' );
}

// temp breadcrumb function

function get_breadcrumb() {
       
    // Settings
    //$separator          = '&gt;';
    $breadcrums_id      = 'breadcrumbs';
    $breadcrums_class   = 'breadcrumbs';
    $home_title         = 'Homepage';
      
    // If you have any custom post types with custom taxonomies, put the taxonomy name below (e.g. product_cat)
    $custom_taxonomy    = 'product_cat';
       
    // Get the query & post information
    global $post,$wp_query;
      
       
    // Build the breadcrums
    echo '<ol id="' . $breadcrums_id . '" class="' . $breadcrums_class . '">';
       
    // Home page
    echo '<li class="item-home"><a class="bread-link bread-home" href="' . get_home_url() . '" title="' . $home_title . '">' . $home_title . '</a></li>';
    //echo '<li class="separator separator-home"> ' . $separator . ' </li>';
       
    if ( is_archive() && !is_tax() && !is_category() && !is_tag() ) {
          
        echo '<li class="item-current item-archive"><strong class="bread-current bread-archive">' . post_type_archive_title($prefix, false) . '</strong></li>';
          
    } else if ( is_archive() && is_tax() && !is_category() && !is_tag() ) {
          
        // If post is a custom post type
        $post_type = get_post_type();
          
        // If it is a custom post type display name and link
        if($post_type != 'post') {
              
            $post_type_object = get_post_type_object($post_type);
            $post_type_archive = get_post_type_archive_link($post_type);
          
            echo '<li class="item-cat item-custom-post-type-' . $post_type . '"><a class="bread-cat bread-custom-post-type-' . $post_type . '" href="' . $post_type_archive . '" title="' . $post_type_object->labels->name . '">' . $post_type_object->labels->name . '</a></li>';
            //echo '<li class="separator"> ' . $separator . ' </li>';
          
        }
          
        $custom_tax_name = get_queried_object()->name;
        echo '<li class="item-current item-archive"><strong class="bread-current bread-archive">' . $custom_tax_name . '</strong></li>';
          
    } else if ( is_single() ) {
          
        // If post is a custom post type
        $post_type = get_post_type();
          
        // If it is a custom post type display name and link
        if($post_type != 'post') {
              
            $post_type_object = get_post_type_object($post_type);
            $post_type_archive = get_post_type_archive_link($post_type);
          
            echo '<li class="item-cat item-custom-post-type-' . $post_type . '"><a class="bread-cat bread-custom-post-type-' . $post_type . '" href="' . $post_type_archive . '" title="' . $post_type_object->labels->name . '">' . $post_type_object->labels->name . '</a></li>';
            //echo '<li class="separator"> ' . $separator . ' </li>';
          
        }
          
        // Get post category info
        $category = get_the_category();
         
        if(!empty($category)) {
          
            // Get last category post is in
            $last_category = end(array_values($category));
              
            // Get parent any categories and create array
            $get_cat_parents = rtrim(get_category_parents($last_category->term_id, true, ','),',');
            $cat_parents = explode(',',$get_cat_parents);
              
            // Loop through parent categories and store in variable $cat_display
            $cat_display = '';
            foreach($cat_parents as $parents) {
                $cat_display .= '<li class="item-cat">'.$parents.'</li>';
                //$cat_display .= '<li class="separator"> ' . $separator . ' </li>';
            }
         
        }
          
        // If it's a custom post type within a custom taxonomy
        $taxonomy_exists = taxonomy_exists($custom_taxonomy);
        if(empty($last_category) && !empty($custom_taxonomy) && $taxonomy_exists) {
               
            $taxonomy_terms = get_the_terms( $post->ID, $custom_taxonomy );
            $cat_id         = $taxonomy_terms[0]->term_id;
            $cat_nicename   = $taxonomy_terms[0]->slug;
            $cat_link       = get_term_link($taxonomy_terms[0]->term_id, $custom_taxonomy);
            $cat_name       = $taxonomy_terms[0]->name;
           
        }
          
        // Check if the post is in a category
        if(!empty($last_category)) {
            echo $cat_display;
            echo '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';
              
        // Else if post is in a custom taxonomy
        } else if(!empty($cat_id)) {
              
            echo '<li class="item-cat item-cat-' . $cat_id . ' item-cat-' . $cat_nicename . '"><a class="bread-cat bread-cat-' . $cat_id . ' bread-cat-' . $cat_nicename . '" href="' . $cat_link . '" title="' . $cat_name . '">' . $cat_name . '</a></li>';
            //echo '<li class="separator"> ' . $separator . ' </li>';
            echo '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';
          
        } else {
              
            echo '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '" title="' . get_the_title() . '">' . get_the_title() . '</strong></li>';
              
        }
          
    } else if ( is_category() ) {
           
        // Category page
        echo '<li class="item-current item-cat"><strong class="bread-current bread-cat">' . single_cat_title('', false) . '</strong></li>';
           
    } else if ( is_page() ) {
           
        // Standard page
        if( $post->post_parent ){
               
            // If child page, get parents 
            $anc = get_post_ancestors( $post->ID );
               
            // Get parents in the right order
            $anc = array_reverse($anc);
               
            // Parent page loop
            if ( !isset( $parents ) ) $parents = null;
            foreach ( $anc as $ancestor ) {
                $parents .= '<li class="item-parent item-parent-' . $ancestor . '"><a class="bread-parent bread-parent-' . $ancestor . '" href="' . get_permalink($ancestor) . '" title="' . get_the_title($ancestor) . '">' . get_the_title($ancestor) . '</a></li>';
                //$parents .= '<li class="separator separator-' . $ancestor . '"> ' . $separator . ' </li>';
            }
               
            // Display parent pages
            echo $parents;
               
            // Current page
            echo '<li class="item-current item-' . $post->ID . '"><strong title="' . get_the_title() . '"> ' . get_the_title() . '</strong></li>';
               
        } else {
               
            // Just display current page if not parents
            echo '<li class="item-current item-' . $post->ID . '"><strong class="bread-current bread-' . $post->ID . '"> ' . get_the_title() . '</strong></li>';
               
        }
           
    } else if ( is_tag() ) {
           
        // Tag page
           
        // Get tag information
        $term_id        = get_query_var('tag_id');
        $taxonomy       = 'post_tag';
        $args           = 'include=' . $term_id;
        $terms          = get_terms( $taxonomy, $args );
        $get_term_id    = $terms[0]->term_id;
        $get_term_slug  = $terms[0]->slug;
        $get_term_name  = $terms[0]->name;
           
        // Display the tag name
        echo '<li class="item-current item-tag-' . $get_term_id . ' item-tag-' . $get_term_slug . '"><strong class="bread-current bread-tag-' . $get_term_id . ' bread-tag-' . $get_term_slug . '">' . $get_term_name . '</strong></li>';
       
    } elseif ( is_day() ) {
           
        // Day archive
           
        // Year link
        echo '<li class="item-year item-year-' . get_the_time('Y') . '"><a class="bread-year bread-year-' . get_the_time('Y') . '" href="' . get_year_link( get_the_time('Y') ) . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</a></li>';
        //echo '<li class="separator separator-' . get_the_time('Y') . '"> ' . $separator . ' </li>';
           
        // Month link
        echo '<li class="item-month item-month-' . get_the_time('m') . '"><a class="bread-month bread-month-' . get_the_time('m') . '" href="' . get_month_link( get_the_time('Y'), get_the_time('m') ) . '" title="' . get_the_time('M') . '">' . get_the_time('M') . ' Archives</a></li>';
        //echo '<li class="separator separator-' . get_the_time('m') . '"> ' . $separator . ' </li>';
           
        // Day display
        echo '<li class="item-current item-' . get_the_time('j') . '"><strong class="bread-current bread-' . get_the_time('j') . '"> ' . get_the_time('jS') . ' ' . get_the_time('M') . ' Archives</strong></li>';
           
    } else if ( is_month() ) {
           
        // Month Archive
           
        // Year link
        echo '<li class="item-year item-year-' . get_the_time('Y') . '"><a class="bread-year bread-year-' . get_the_time('Y') . '" href="' . get_year_link( get_the_time('Y') ) . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</a></li>';
        //echo '<li class="separator separator-' . get_the_time('Y') . '"> ' . $separator . ' </li>';
           
        // Month display
        echo '<li class="item-month item-month-' . get_the_time('m') . '"><strong class="bread-month bread-month-' . get_the_time('m') . '" title="' . get_the_time('M') . '">' . get_the_time('M') . ' Archives</strong></li>';
           
    } else if ( is_year() ) {
           
        // Display year archive
        echo '<li class="item-current item-current-' . get_the_time('Y') . '"><strong class="bread-current bread-current-' . get_the_time('Y') . '" title="' . get_the_time('Y') . '">' . get_the_time('Y') . ' Archives</strong></li>';
           
    } else if ( is_author() ) {
           
        // Auhor archive
           
        // Get the author information
        global $author;
        $userdata = get_userdata( $author );
           
        // Display author name
        echo '<li class="item-current item-current-' . $userdata->user_nicename . '"><strong class="bread-current bread-current-' . $userdata->user_nicename . '" title="' . $userdata->display_name . '">' . 'Author: ' . $userdata->display_name . '</strong></li>';
       
    } else if ( get_query_var('paged') ) {
           
        // Paginated archives
        echo '<li class="item-current item-current-' . get_query_var('paged') . '"><strong class="bread-current bread-current-' . get_query_var('paged') . '" title="Page ' . get_query_var('paged') . '">'.__('Page') . ' ' . get_query_var('paged') . '</strong></li>';
           
    } else if ( is_search() ) {
       
        // Search results page
        echo '<li class="item-current item-current-' . get_search_query() . '"><strong class="bread-current bread-current-' . get_search_query() . '" title="Search results for: ' . get_search_query() . '">Search results for: ' . get_search_query() . '</strong></li>';
       
    } elseif ( is_404() ) {
           
        // 404 page
        echo '<li>' . 'Error 404' . '</li>';
    }
   
    echo '</ol>';
       
}
?>