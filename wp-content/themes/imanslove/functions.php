<?php 
/*
	==================================
	Include scripts
	==================================
*/
function iman_script_enqueue() {
	// css
	wp_enqueue_style('customstyle', get_template_directory_uri() . '/_Assets/css/iman.css', array(), '1.0.0', 'all');
	// jquery and scroll
	wp_enqueue_script( 'jquery', get_template_directory_uri() . '/_Assets/js/jquery.slim.min.js', array(), '1.0.0', true );
    wp_enqueue_script( 'bootsrap', get_template_directory_uri() . '/_Assets/js/bootstrap.bundle.min.js', array(), '1.0.0', true );
    wp_enqueue_script( 'easing', get_template_directory_uri() . '/_Assets/js/jquery.easing.min.js', array(), '1.0.0', true );
    wp_enqueue_script( 'scrolling', get_template_directory_uri() . '/_Assets/js/scrolling-nav.js', array(), '1.0.0', true );
	// js
	wp_enqueue_script('customjs', get_template_directory_uri() . '/_Assets/js/iman.js', array(), '1.0.0', true);

}

add_action('wp_enqueue_scripts', 'iman_script_enqueue');

/*
	==================================
	Activate menus
	==================================
*/
function iman_theme_setup() {

	add_theme_support('menus');

	register_nav_menu('main', 'Main Header Navigation');
	register_nav_menu('social', 'Social Links Menu');
	register_nav_menu('contracts', 'Contract Links Menu');
}

add_action('init', 'iman_theme_setup');

/*
	==================================
	Theme support function
	==================================
*/
add_theme_support('custom-header');
add_theme_support('post-thumbnails');
add_theme_support('post-formats', array('aside','image','video','link'));

/*
	==================================
	Sidebar function
	==================================
*/
function iman_widget_setup() {

	register_sidebar(
		array(
			'name'	=>	'Photos',
			'id'	=>	'photos',
			'class'	=>	'custom',
			'description'	=>	'Photos section images',
			'before_widget'	=>	'<div class="photo-img">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	' title="',
			'after_title'	=>	'">',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Pull Quote',
			'id'	=>	'pull-quote',
			'class'	=>	'custom',
			'description'	=>	'Imans Love pull quote',
			'before_widget'	=>	'<div class="pull-quote">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<span title="',
			'after_title'	=>	'"></span>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Babysitting Services',
			'id'	=>	'babysitting-services',
			'class'	=>	'custom',
			'description'	=>	'Imans Love babysitting services',
			'before_widget'	=>	'<div class="services-info services-info-babysitting">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h2>',
			'after_title'	=>	'</h2>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Babysitting Pricing',
			'id'	=>	'babysitting-pricing',
			'class'	=>	'custom',
			'description'	=>	'Imans Love babysitting pricing',
			'before_widget'	=>	'<div class="services-pricing services-pricing-babysitting">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h3>',
			'after_title'	=>	'</h3>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Basic Services',
			'id'	=>	'basic-services',
			'class'	=>	'custom',
			'description'	=>	'Imans Love basic services',
			'before_widget'	=>	'<div class="services-info services-info-basic">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h2>',
			'after_title'	=>	'</h2>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Basic Pricing',
			'id'	=>	'basic-pricing',
			'class'	=>	'custom',
			'description'	=>	'Imans Love basic pricing',
			'before_widget'	=>	'<div class="services-pricing services-pricing-basic">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h3>',
			'after_title'	=>	'</h3>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Premium Services',
			'id'	=>	'premium-services',
			'class'	=>	'custom',
			'description'	=>	'Imans Love premium services',
			'before_widget'	=>	'<div class="services-info services-info-premium">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h2>',
			'after_title'	=>	'</h2>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Premium Pricing',
			'id'	=>	'premium-pricing',
			'class'	=>	'custom',
			'description'	=>	'Imans Love premium pricing',
			'before_widget'	=>	'<div class="services-pricing services-pricing-premium">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<h3>',
			'after_title'	=>	'</h3>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Testimonials',
			'id'	=>	'testimonial',
			'class'	=>	'custom',
			'description'	=>	'Imans Love testimonials',
			'before_widget'	=>	'<div class="testimonial">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<span title="',
			'after_title'	=>	'"></span>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Contact',
			'id'	=>	'contact',
			'class'	=>	'custom',
			'description'	=>	'Imans Love contact info',
			'before_widget'	=>	'<div class="contact">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<span title="',
			'after_title'	=>	'"></span>',
		)
	);

	register_sidebar(
		array(
			'name'	=>	'Footer Contact',
			'id'	=>	'footer-contact',
			'class'	=>	'custom',
			'description'	=>	'Imans Love footer contact info',
			'before_widget'	=>	'<div class="footer-contact">',
			'after_widget'	=>	'</div>',
			'before_title'	=>	'<span title="',
			'after_title'	=>	'"></span>',
		)
	);

}
add_action('widgets_init','iman_widget_setup');

/*
	==================================
	Include Walker file
	==================================
*/
require get_template_directory() . '/inc/walker.php';

/*
	==================================
	Head function
	==================================
*/
function iman_remove_version() {
	return '';
}
add_filter('the_generator', 'iman_remove_version');


/*
	==================================
	Remove Images sizes Function
	==================================
*/
function prefix_remove_default_images( $sizes ) {
	unset( $sizes['thumbnail']); // 150px
	unset( $sizes['full']); // 150px
	unset( $sizes['small']); // 150px
	unset( $sizes['medium']); // 300px
	unset( $sizes['large']); // 1024px
	unset( $sizes['medium_large']); // 768px

	return $sizes;
}

add_filter( 'intermediate_image_sizes_advanced', 'prefix_remove_default_images' );


/*
	==================================
	HTML image markup Function
	==================================
*/
function html5_insert_image($html, $id, $caption, $title, $align, $url) {
  $url = wp_get_attachment_url($id);
  $html5 = "<figure id='post-$id media-$id' class='align-$align'>";
  $html5 .= "<img src='$url' alt='$title' >";
  $html5 .= "</figure>";
  return $html5;
}
add_filter( 'image_send_to_editor', 'html5_insert_image', 10, 9 );


/*
	=======================================
	Limiting excerpt length to 20 words
	=======================================
*/
function custom_excerpt_length( $length ) {
	return 20;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );
