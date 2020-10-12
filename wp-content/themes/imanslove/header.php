<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
		<title><?php bloginfo('name'); wp_title('|'); ?></title>
		<meta name="description" content="<?php bloginfo('description'); ?>">
		<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,200;0,400;0,500;0,700;1,100;1,600;1,700&display=swap" rel="stylesheet">
		<?php wp_head();?>
	</head>
	<body id="page-top" <?php body_class( $home_classes ); ?>>
		<header id="header" class="header">
			<div class="header-container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" id="mobileNavBtn"><span class="icon-bar top-bar"></span><span class="icon-bar middle-bar"></span><span class="icon-bar bottom-bar"></span></button>
					<?php wp_nav_menu(array('theme_location' => 'social')); ?>
				</div>
				<nav id="mainNav" class="navbar">
					<div id="navbarResponsive" class="scroll-menu">
				        <ul class="navbar-nav">
				            <li class="nav-item"><a href="#home" class="nav-link js-scroll-trigger">Home</a></li>
				            <li class="nav-item"><a href="#about" class="nav-link js-scroll-trigger">About</a></li>
				            <li class="nav-item"><a href="#services" class="nav-link js-scroll-trigger">Services</a></li>
				            <li class="nav-item"><a href="#book" class="nav-link js-scroll-trigger">Book Online</a></li>
				            <li class="nav-item"><a href="#photos" class="nav-link js-scroll-trigger">Photos</a></li>
				            <li class="nav-item"><a href="#testimonials" class="nav-link js-scroll-trigger">Testimonials</a></li>
				            <li class="nav-item"><a href="#contact" class="nav-link js-scroll-trigger">Contact</a></li>
				        </ul>
				    </div>
				</nav><!-- main navigation -->
			</div>
		</header>
				
			
		