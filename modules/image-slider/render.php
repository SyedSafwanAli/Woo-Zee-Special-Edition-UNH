<?php
/**
 * [wzp_image_slider] — Simple image slider.
 *
 * Two ways to use it:
 *
 *   1. ADMIN (recommended) — configure slides in WP-Admin → Woo Zee → Image
 *      Slider (each slide can have a desktop image, an optional mobile image,
 *      and an optional link), then place:
 *          [wzp_image_slider]
 *
 *   2. INLINE — pass images directly (no per-slide links / mobile images):
 *          [wzp_image_slider images="12,45,78"]
 *          [wzp_image_slider images="https://site.com/a.jpg, .../b.jpg"]
 *
 * OPTIONAL ATTRIBUTES (override the saved settings):
 *   height   Slider height in px (default 500). Use "auto" to keep image ratio.
 *   fit      cover | contain  (default cover)
 *   autoplay yes | no         (default yes)
 *   delay    autoplay delay in ms (default 4000)
 *   loop     yes | no         (default yes)
 *   arrows   yes | no         (default yes)
 *   dots     yes | no         (default yes)
 *   radius   corner radius in px (default 0)
 *
 * @package WooZeePlugin
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wzp_render_image_slider' ) ) :

/**
 * Build and return the image-slider HTML string.
 *
 * @param array $atts Shortcode attributes.
 * @return string     Escaped HTML + inline Swiper init; empty string if no images.
 */
function wzp_render_image_slider( $atts ) {

	// ── Saved settings from the admin "Image Slider" tab ───────────────────────
	$opts = wp_parse_args(
		(array) get_option( 'wzp_image_slider_options', array() ),
		array(
			'slides'   => array(),
			'images'   => array(), // legacy: flat list of desktop ids
			'height'   => '500',
			'fit'      => 'cover',
			'autoplay' => 'yes',
			'delay'    => '4000',
			'loop'     => 'yes',
			'arrows'   => 'yes',
			'dots'     => 'yes',
			'radius'   => '0',
		)
	);

	$atts = shortcode_atts(
		array(
			'images'   => '',           // inline-only; empty means "use admin slides"
			'height'   => $opts['height'],
			'fit'      => $opts['fit'],
			'autoplay' => $opts['autoplay'],
			'delay'    => $opts['delay'],
			'loop'     => $opts['loop'],
			'arrows'   => $opts['arrows'],
			'dots'     => $opts['dots'],
			'radius'   => $opts['radius'],
		),
		$atts,
		'wzp_image_slider'
	);

	// ── Build the list of slides ───────────────────────────────────────────────
	// Each slide: [ 'url', 'mobile', 'alt', 'link' ].
	$slides = array();

	$inline = trim( (string) $atts['images'] );

	if ( '' !== $inline ) {
		// INLINE mode — comma-separated IDs or URLs (no links / mobile images).
		foreach ( array_filter( array_map( 'trim', explode( ',', $inline ) ) ) as $item ) {
			if ( is_numeric( $item ) ) {
				$url = wp_get_attachment_image_url( absint( $item ), 'full' );
				if ( $url ) {
					$slides[] = array(
						'url'    => $url,
						'mobile' => '',
						'alt'    => (string) get_post_meta( absint( $item ), '_wp_attachment_image_alt', true ),
						'link'   => '',
					);
				}
			} elseif ( filter_var( $item, FILTER_VALIDATE_URL ) ) {
				$slides[] = array( 'url' => esc_url_raw( $item ), 'mobile' => '', 'alt' => '', 'link' => '' );
			}
		}
	} else {
		// ADMIN mode — rich slides with optional mobile image + link.
		$saved = is_array( $opts['slides'] ) ? $opts['slides'] : array();

		// Backward compatibility: migrate a legacy flat "images" list on the fly.
		if ( empty( $saved ) && ! empty( $opts['images'] ) && is_array( $opts['images'] ) ) {
			foreach ( $opts['images'] as $id ) {
				$saved[] = array( 'image_id' => absint( $id ), 'mobile_id' => 0, 'link' => '' );
			}
		}

		foreach ( $saved as $slide ) {
			if ( ! is_array( $slide ) ) { continue; }
			$image_id  = absint( $slide['image_id']  ?? 0 );
			$mobile_id = absint( $slide['mobile_id'] ?? 0 );
			if ( ! $image_id ) { continue; }

			$url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( ! $url ) { continue; }

			$slides[] = array(
				'url'    => $url,
				'mobile' => $mobile_id ? ( wp_get_attachment_image_url( $mobile_id, 'full' ) ?: '' ) : '',
				'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
				'link'   => esc_url_raw( $slide['link'] ?? '' ),
			);
		}
	}

	if ( empty( $slides ) ) {
		return '';
	}

	// ── Normalise options ──────────────────────────────────────────────────────
	$height   = ( 'auto' === strtolower( trim( $atts['height'] ) ) ) ? 'auto' : absint( $atts['height'] ) . 'px';
	$fit      = ( 'contain' === strtolower( $atts['fit'] ) ) ? 'contain' : 'cover';
	$autoplay = in_array( strtolower( $atts['autoplay'] ), array( 'yes', 'true', '1' ), true );
	$delay    = max( 1000, absint( $atts['delay'] ) );
	$loop     = in_array( strtolower( $atts['loop'] ), array( 'yes', 'true', '1' ), true );
	$arrows   = in_array( strtolower( $atts['arrows'] ), array( 'yes', 'true', '1' ), true );
	$dots     = in_array( strtolower( $atts['dots'] ), array( 'yes', 'true', '1' ), true );
	$radius   = absint( $atts['radius'] );

	// Unique id so multiple sliders can live on one page.
	$uid = 'wzp-img-slider-' . wp_unique_id();

	// ── Render ─────────────────────────────────────────────────────────────────
	ob_start();
	?>
	<div class="wzp-module" data-wzp-module="image-slider">
		<div class="wzp-img-slider swiper" id="<?php echo esc_attr( $uid ); ?>"
		     style="--wzp-is-height:<?php echo esc_attr( $height ); ?>;--wzp-is-fit:<?php echo esc_attr( $fit ); ?>;--wzp-is-radius:<?php echo esc_attr( $radius ); ?>px;"
		     aria-label="<?php esc_attr_e( 'Image slider', 'woo-zee-plugin' ); ?>">

			<div class="swiper-wrapper">
				<?php foreach ( $slides as $index => $slide ) : ?>
					<?php
					$slide_label = esc_attr( sprintf(
						/* translators: 1: current slide, 2: total slides */
						__( 'Slide %1$d of %2$d', 'woo-zee-plugin' ),
						$index + 1,
						count( $slides )
					) );

					// Inner media markup (picture with optional mobile source).
					ob_start();
					?>
					<?php if ( $slide['mobile'] ) : ?>
						<picture>
							<source media="(max-width: 768px)" srcset="<?php echo esc_url( $slide['mobile'] ); ?>">
							<img class="wzp-img-slider__img"
							     src="<?php echo esc_url( $slide['url'] ); ?>"
							     alt="<?php echo esc_attr( $slide['alt'] ); ?>"
							     <?php echo 0 === $index ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
							     decoding="async">
						</picture>
					<?php else : ?>
						<img class="wzp-img-slider__img"
						     src="<?php echo esc_url( $slide['url'] ); ?>"
						     alt="<?php echo esc_attr( $slide['alt'] ); ?>"
						     <?php echo 0 === $index ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
						     decoding="async">
					<?php endif; ?>
					<?php
					$media = ob_get_clean();
					?>
					<div class="swiper-slide wzp-img-slider__slide" role="group" aria-label="<?php echo $slide_label; ?>">
						<?php if ( $slide['link'] ) : ?>
							<a class="wzp-img-slider__link" href="<?php echo esc_url( $slide['link'] ); ?>">
								<?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</a>
						<?php else : ?>
							<?php echo $media; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $dots ) : ?>
				<div class="swiper-pagination"></div>
			<?php endif; ?>

			<?php if ( $arrows ) : ?>
				<div class="swiper-button-prev"></div>
				<div class="swiper-button-next"></div>
			<?php endif; ?>

		</div>
	</div>

	<style>
	#<?php echo esc_attr( $uid ); ?>{width:100%;overflow:hidden;border-radius:var(--wzp-is-radius);}
	#<?php echo esc_attr( $uid ); ?> .wzp-img-slider__slide,
	#<?php echo esc_attr( $uid ); ?> .wzp-img-slider__link,
	#<?php echo esc_attr( $uid ); ?> picture{display:block;height:var(--wzp-is-height);}
	#<?php echo esc_attr( $uid ); ?> .wzp-img-slider__img{width:100%;height:var(--wzp-is-height);object-fit:var(--wzp-is-fit);display:block;}
	#<?php echo esc_attr( $uid ); ?> .swiper-button-prev,
	#<?php echo esc_attr( $uid ); ?> .swiper-button-next{color:#fff;}
	#<?php echo esc_attr( $uid ); ?> .swiper-pagination-bullet-active{background:#fff;}
	</style>

	<script>
	( function () {
		function wzpInit_<?php echo esc_js( str_replace( '-', '_', $uid ) ); ?>() {
			if ( typeof Swiper === 'undefined' ) { return; }
			new Swiper( '#<?php echo esc_js( $uid ); ?>', {
				loop       : <?php echo $loop ? 'true' : 'false'; ?>,
				<?php if ( $autoplay ) : ?>
				autoplay   : { delay: <?php echo (int) $delay; ?>, disableOnInteraction: false },
				<?php endif; ?>
				<?php if ( $dots ) : ?>
				pagination : { el: '#<?php echo esc_js( $uid ); ?> .swiper-pagination', clickable: true },
				<?php endif; ?>
				<?php if ( $arrows ) : ?>
				navigation : {
					prevEl : '#<?php echo esc_js( $uid ); ?> .swiper-button-prev',
					nextEl : '#<?php echo esc_js( $uid ); ?> .swiper-button-next'
				},
				<?php endif; ?>
				a11y       : {
					prevSlideMessage : '<?php echo esc_js( __( 'Previous slide', 'woo-zee-plugin' ) ); ?>',
					nextSlideMessage : '<?php echo esc_js( __( 'Next slide', 'woo-zee-plugin' ) ); ?>'
				}
			} );
		}
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', wzpInit_<?php echo esc_js( str_replace( '-', '_', $uid ) ); ?> );
		} else {
			wzpInit_<?php echo esc_js( str_replace( '-', '_', $uid ) ); ?>();
		}
	} )();
	</script>
	<?php

	return ob_get_clean();
}

endif; // function_exists

// ── Entry point (called by WZP_Shortcodes::dispatch) ─────────────────────────
echo wzp_render_image_slider( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput
