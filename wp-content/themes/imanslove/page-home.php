<?php get_header(); ?>

    <div class="container">
    	<section id="home" class="home">
	    	<div class="home-bkgd"></div>
	    	<div class="home-overlay"></div>
	    	<img src="<?php header_image(); ?>" alt="Iman's Love, LLC" class="home-logo">
	    </section>
	    <section id="quote">
	    	<div class="content">
	    		<?php dynamic_sidebar('pull-quote'); ?>
	    	</div>
	    </section>
	    <section id="about" class="section-about">
	    	<div class="content">
	    		<?php if( has_post_thumbnail() ): ?>
				
					<img src="<?php the_post_thumbnail_url('full'); ?>" alt="<?php the_title(); ?>" class="about-pic">

				<?php endif; ?>
			    <div class="about-content">
			    	<h1><span>Iman's</span> Love</h1>
					<?php
						
						if ( have_posts() ):

							while ( have_posts() ) : the_post();
								the_content();
							endwhile;

						endif;

					?>
			    </div>
			</div>
		</section>
	    <section id="services" class="section-services">
	    	<div class="content">
	    		<div class="services services-babysitting">
	    			<?php dynamic_sidebar('babysitting-services'); ?>
	    			<?php dynamic_sidebar('babysitting-pricing'); ?>
	    		</div>
	    		<div class="services services-basic">
	    			<?php dynamic_sidebar('basic-services'); ?>
	    			<?php dynamic_sidebar('basic-pricing'); ?>
	    		</div>
	    		<div class="services services-premium">
	    			<?php dynamic_sidebar('premium-services'); ?>
	    			<?php dynamic_sidebar('premium-pricing'); ?>
	    		</div>
		    </div>
	    </section>
	    <section id="book" class="section-book">
	    	<h2>Booking</h2>
	    	<div class="content">
	    		<?php echo do_shortcode('[ameliacatalog]'); ?>
	    	</div>
	    	<div class="section-book-contracts">
		    	<?php wp_nav_menu(array('theme_location' => 'contracts')); ?>
		    </div>
	    </section>
	    <section id="photos">
	    	<div class="content">
	    		<?php dynamic_sidebar('photos'); ?>
	    	</div>
	    </section>
	    <section id="testimonials" class="section-test">
	    	<div class="test-overlay"></div>
	    	<div class="content">
	    		<h2>Testimonials</h2>
	    		<?php dynamic_sidebar('testimonials'); ?>
	    	</div>
	    </section>
	    <section id="contact" class="section-contact">
	    	<div class="content-header">
	    		<h2>Contact Us</h2>
	    		<?php dynamic_sidebar('contact'); ?>
	    	</div>
	    	<div class="content contact-content">
	    		<?php echo do_shortcode('[wpforms id="28" title="false" description="false"]'); ?>
	    	</div>
	    </section>
	    <footer class="footer">
			<div class="footer-container">
				<div class="footer-content">
					<?php dynamic_sidebar('footer-contact'); ?>
					<p>
						<strong>© <?php echo date('Y'); ?> by Iman's Love LLC. Designed by <a href="https://www.thesocialagencyllc.com/" target="_blank">The Social Agency LLC</a>.</strong>
					</p>
					<?php wp_nav_menu(array('theme_location' => 'social')); ?>
				</div>
			</div>
		</footer><!-- .site-footer -->
    </div>

<?php get_footer(); ?>