<?php
/**
 * epfl functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package epfl
 */

if ( ! function_exists( 'epfl_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function epfl_setup() {
		/*
		 * Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on epfl, use a find and replace
		 * to change 'epfl' to the name of your theme in all the template files.
		 */
		load_theme_textdomain( 'epfl', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );
        add_image_size( 'epfl-list-thumb', 480, 270, true );

		/**
     * Register menus
     */
    
    register_nav_menus( array(
		  'top'    => __( 'Primary menu', 'epfl' ),
    	'sidebar_nav' => __( 'Sidebar menu', 'epfl' ),
    	'footer_nav' => __( 'Footer menu', 'epfl' )
    ) );

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		// Set up the WordPress core custom background feature.
		add_theme_support( 'custom-background', apply_filters( 'epfl_custom_background_args', array(
			'default-color' => 'ffffff',
			'default-image' => '',
		) ) );

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		/**
		 * Add support for core custom logo.
		 *
		 * @link https://codex.wordpress.org/Theme_Logo
		 */
		add_theme_support( 'custom-logo', array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		) );
	}
endif;
add_action( 'after_setup_theme', 'epfl_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function epfl_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'epfl_content_width', 640 );
}
add_action( 'after_setup_theme', 'epfl_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
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
    'name'          => 'Page widgets',
    'id'            => 'page-widgets',
    'description'   => 'Widgets présents sur toutes les pages, y compris la homepage',
    'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>'
  ) );
}
add_action( 'widgets_init', 'epfl_widgets_init' );

/**
 * Filter the except length to 20 words.
 *
 * @param int $length Excerpt length.
 * @return int (Maybe) modified excerpt length.
 */
function custom_excerpt_length( $length ) {
    return 24;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );

/**
 * add temp shortcode mp4_video button to Tinmce
 */

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
  $plugins['mp4'] = get_template_directory_uri() . '/assets/js/tiny-mce/tiny-mce.js';
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


/*
 * Parses the URL of the current page to return an array that allows to easily construct the HTML
 * for the breadcrumb in get_breadcrumb() function.
 *
 * The returned array is of the form ["https://localhost" => "locahost", "https://localhost/site1" => "site1"]
 * for the URL https://localhost/site1
 */
function construct_breadcrumb_from_url() {
    $breadcrumb_parts = Array();
    // Constructs an array mapping URLs to names. For example, on the site https://localhost/site1 :
    // ["https://localhost/site1" => "site1", "https://localhost" => "locahost"]
    $temp_url = site_url();
    while ($temp_url != 'http:/' && $temp_url != 'https:/') {
        $label = basename($temp_url);
        if ($label === 'www.epfl.ch') {
            $label = 'EPFL';
        } else {
            $matched = preg_match('/(.*).epfl.ch$/', $label, $matches, PREG_OFFSET_CAPTURE);
            if ($matched) {
                /* First element of $matches contains an array where the first element is the full
                 * string matched by the regex.
                 * The second element contains an array where the first element is the string matched
                 * by the group.
                 */
                $label = $matches[1][0];
            }
        }
        // Capitalize first letter
        $label = ucfirst($label);
        $breadcrumb_parts[$temp_url] = $label;
        // Remove the last part of the URL :
        // "https://localhost/site1" => "https://localhost"
        $temp_url = substr($temp_url, 0, strrpos($temp_url, "/"));
    }

    return array_reverse($breadcrumb_parts);
}


/*
 * Parses the option epfl:custom_breadcrumb to return an array that allows to easily construct the HTML
 * for the breadcrumb in get_breadcrumb() function.
 *
 * The option must follow the format [label|url]>[label|url]>[label|url].
 *
 * The returned array is of the form ["https://localhost" => "locahost", "https://localhost/site1" => "site1"]
 * for the URL https://localhost/site1
 */
function construct_breadcrumb_from_option($option) {
    $breadcrumb_parts = Array();
    // Constructs an array mapping URLs to names. For example, on the site https://localhost/site1 :
    // ["https://localhost/site1" => "site1", "https://localhost" => "locahost"]

    $parts = explode('>', $option);
    foreach($parts as $part) {
        $url_label = explode('|', $part);
        $label = str_replace('[', '', $url_label[0]);
        $url = str_replace(']', '', $url_label[1]);
        // if the url does not start with 'http', add 'https://' to prevent the browser to handle it as
        // a relative url.
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }
        $breadcrumb_parts[$url] = $label;
    }

    return $breadcrumb_parts;
}


/**
 * temp breadcrumb function
 */

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
       
    // On a sub-site like https://localhost/site1/site2, on the page https://localhost/site1/site2/page
    // the default breadcrumb looks like "Homepage > page" because the instance of wordpress for site2
    // considers https://localhost/site1/site2 to be the Homepage.
    //
    // So the following code parses the URL and creates a base breadcrumb that looks like :
    // "localhost > site1 > site2".
    // Then this base breadcrumb is used as a prefix replacing "Homepage" in the original breadcrumb.
    //
    // The value is cached during one hour to avoid parsing the url on every page load.
    //
    // The transient API is used because the basic Cache Object does not persist data if no persistent
    // cache plugin is used. The transient API uses the Cache Object if such a plugin is setup, otherwise
    // it stores the value in the database as an option.
    if (false === ($base_breadcrumb = get_transient('base_breadcrumb'))) {
        $breadcrumb_parts = Array();
        $breadcrumb_option = get_option('epfl:custom_breadcrumb');
        // Check any string of the form [label|url]>[label|url]>...>[label|url]
        $breadcrumb_option_format = "/(^\[[^\|\[\]]+\|[^\|\[\]]+\]){1}(>(\[[^\|\[\]]+\|[^\|\[\]]+\]){1})*$/";
        $matched = preg_match($breadcrumb_option_format, $breadcrumb_option);

        if ($breadcrumb_option && $matched === 1) {
            $breadcrumb_parts = construct_breadcrumb_from_option($breadcrumb_option);
        } else {
            $breadcrumb_parts = construct_breadcrumb_from_url();
        }
        $base_breadcrumb = '';
        foreach($breadcrumb_parts as $url => $label){
            if ($base_breadcrumb == '') {
                $base_breadcrumb .= '<li class="item-home"><a class="bread-link bread-home" href="' . $url . '" title="' . $label . '">' . $label . '</a></li>';
            } else {
                $base_breadcrumb .= '<li class="item-parent"><a class="bread-parent" href="' . $url . '" title="' . $label . '">' . $label . '</a></li>';
            }
        }
        set_transient('base_breadcrumb', $base_breadcrumb, 30);
    }

    echo $base_breadcrumb;
       
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

/**
 * Enqueue scripts and styles.
 */
function epfl_scripts() {
	wp_enqueue_style( 'epfl-style', get_stylesheet_uri() );
	
	wp_enqueue_style('font-awesome', get_template_directory_uri() .'/assets/css/font-awesome.min.css');
	wp_enqueue_style('grid', get_template_directory_uri() .'/assets/css/stylisticss.grid.css');
	
	wp_enqueue_style('compiled-styles', get_template_directory_uri() .'/assets/css/theme.min.css');

	wp_enqueue_script( 'modernizr', get_stylesheet_directory_uri() . '/assets/js/libs/modernizr.js', array(), null , false );
	wp_enqueue_script( 'epfl-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), '20151215', true );
	wp_enqueue_script( 'pryv-scripts', get_template_directory_uri() . '/assets/js/main.js', array( 'jquery' ), null , true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'epfl_scripts' );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}

