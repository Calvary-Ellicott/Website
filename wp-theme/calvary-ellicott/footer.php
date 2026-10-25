</main>

<footer class="site-footer">
	<?php ce_brand( 'footer-brand' ); ?>
	<div>
		<strong><?php echo ce_service_line_html(); ?></strong>
		<p><?php echo ce_address_html( ', ' ); ?></p>
		<?php $email = ce_opt( 'contact_email' ); $phone = ce_opt( 'phone' ); ?>
		<?php if ( is_email( $email ) || $phone ) : ?>
			<p>
				<?php if ( is_email( $email ) ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?>
				<?php if ( is_email( $email ) && $phone ) : ?> · <?php endif; ?>
				<?php if ( $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo ce_opt_span( 'phone' ); ?></a><?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
	<nav aria-label="<?php esc_attr_e( 'Footer navigation', 'calvary-ellicott' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'items_wrap'     => '<ul>%3$s</ul>',
				'depth'          => 1,
				'fallback_cb'    => 'ce_nav_fallback',
			)
		);
		?>
	</nav>
	<p class="copyright">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
