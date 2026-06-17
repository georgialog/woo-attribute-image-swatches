<?php
/**
 * Plugin Name: Image Swatches for Woocommerce
 * Description: Replace WooCommerce product attribute dropdowns with clickable image swatches
 * Version: 1.0.0
 * Author: Georgia Log
 * License: GPL-2.0+
 * Author URI: https://geocreates.me
 * Text Domain: wc-image-swatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access
}

// Prevent direct access to this file
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WC_IMAGE_SWATCHES_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_IMAGE_SWATCHES_URL', plugin_dir_url( __FILE__ ) );

class WC_Image_Swatches {
	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	public function init() {
		// Admin hooks for attribute term meta
		add_action( 'product_cat_add_form_fields', array( $this, 'add_attribute_swatch_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_attribute_swatch_field' ) );
		add_action( 'created_product_cat', array( $this, 'save_attribute_swatch_image' ) );
		add_action( 'edited_product_cat', array( $this, 'save_attribute_swatch_image' ) );

		// Dynamic hooks for all product attributes
		add_action( 'admin_init', array( $this, 'register_attribute_hooks' ) );

		// Frontend hooks
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_swatch_options' ), 10, 4 );

		// Admin page
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register hooks for all product attributes dynamically
	 */
	public function register_attribute_hooks() {
		$attributes = wc_get_attribute_taxonomies();
		foreach ( $attributes as $attribute ) {
			$tax = 'pa_' . $attribute->attribute_name;
			add_action( $tax . '_add_form_fields', array( $this, 'add_attribute_swatch_field_for_tax' ) );
			add_action( $tax . '_edit_form_fields', array( $this, 'edit_attribute_swatch_field_for_tax' ) );
			add_action( 'created_' . $tax, array( $this, 'save_attribute_swatch_image_for_tax' ) );
			add_action( 'edited_' . $tax, array( $this, 'save_attribute_swatch_image_for_tax' ) );
		}
	}

	/**
	 * Add swatch image field to attribute term add form
	 */
	public function add_attribute_swatch_field_for_tax() {
		// Verify user capability
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		wp_nonce_field( 'wc_image_swatches_nonce', 'wc_image_swatches_nonce_field' );
		?>
		<div class="form-field">
			<label for="swatch_image"><?php esc_html_e( 'Swatch Image', 'wc-image-swatches' ); ?></label>
			<div id="swatch_image_preview" style="margin: 10px 0;">
				<img id="swatch_image_img" src="" style="max-width: 100px; display: none;" />
			</div>
			<button type="button" class="button swatch_image_button"><?php esc_html_e( 'Upload Image', 'wc-image-swatches' ); ?></button>
			<input type="hidden" id="swatch_image" name="swatch_image" value="" />
			<p class="description"><?php esc_html_e( 'Upload an image for this attribute term swatch', 'wc-image-swatches' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit swatch image field on attribute term edit form
	 */
	public function edit_attribute_swatch_field_for_tax( $term ) {
		// Verify user capability
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		wp_nonce_field( 'wc_image_swatches_nonce', 'wc_image_swatches_nonce_field' );

		$swatch_id = intval( get_term_meta( $term->term_id, 'swatch_image', true ) );
		$swatch_url = $swatch_id ? wp_get_attachment_image_url( $swatch_id, 'thumbnail' ) : '';
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="swatch_image"><?php esc_html_e( 'Swatch Image', 'wc-image-swatches' ); ?></label>
			</th>
			<td>
				<div id="swatch_image_preview" style="margin: 10px 0;">
					<?php if ( $swatch_url ) : ?>
						<img id="swatch_image_img" src="<?php echo esc_url( $swatch_url ); ?>" style="max-width: 100px;" alt="<?php esc_attr_e( 'Swatch preview', 'wc-image-swatches' ); ?>" />
					<?php else : ?>
						<img id="swatch_image_img" src="" style="max-width: 100px; display: none;" alt="<?php esc_attr_e( 'Swatch preview', 'wc-image-swatches' ); ?>" />
					<?php endif; ?>
				</div>
				<button type="button" class="button swatch_image_button"><?php esc_html_e( 'Upload Image', 'wc-image-swatches' ); ?></button>
				<button type="button" class="button swatch_image_remove" <?php echo $swatch_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'wc-image-swatches' ); ?></button>
				<input type="hidden" id="swatch_image" name="swatch_image" value="<?php echo esc_attr( $swatch_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Upload an image for this attribute term swatch', 'wc-image-swatches' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save swatch image on term creation/edit
	 */
	public function save_attribute_swatch_image_for_tax( $term_id ) {
		// Verify nonce
		if ( ! isset( $_POST['wc_image_swatches_nonce_field'] ) || 
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_image_swatches_nonce_field'] ) ), 'wc_image_swatches_nonce' ) ) {
			return;
		}

		// Verify user capability
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		// Verify and sanitize term ID
		$term_id = absint( $term_id );

		if ( isset( $_POST['swatch_image'] ) ) {
			$swatch_image = absint( sanitize_text_field( wp_unslash( $_POST['swatch_image'] ) ) );
			
			if ( $swatch_image > 0 ) {
				// Verify the attachment exists and is an image
				if ( wp_attachment_is_image( $swatch_image ) ) {
					update_term_meta( $term_id, 'swatch_image', $swatch_image );
				}
			} else {
				delete_term_meta( $term_id, 'swatch_image' );
			}
		}
	}

	/**
	 * Fallback for product categories (not used but kept for compatibility)
	 */
	public function add_attribute_swatch_field() {}
	public function edit_attribute_swatch_field() {}
	public function save_attribute_swatch_image() {}

	/**
	 * Get enabled swatch attributes from settings
	 */
	public function get_enabled_swatch_attributes() {
		$enabled = get_option( 'wc_image_swatches_attributes', array() );
		
		if ( ! is_array( $enabled ) ) {
			return array();
		}

		// Sanitize each attribute key
		$enabled = array_map( 'sanitize_text_field', $enabled );
		
		return $enabled;
	}

	/**
	 * Render image swatches instead of dropdowns
	 */
	public function render_swatch_options( $html, $args, $product, $attribute ) {
		// Validate inputs
		if ( ! is_array( $args ) || ! isset( $args['options'], $args['name'] ) ) {
			return $html;
		}

		$enabled_attributes = $this->get_enabled_swatch_attributes();

		// Check if this attribute should use swatches
		$attribute = sanitize_text_field( $attribute );
		$attribute_key = 'pa_' . $attribute;
		
		if ( ! in_array( $attribute_key, $enabled_attributes, true ) ) {
			return $html;
		}

		$options = array_map( 'sanitize_text_field', (array) $args['options'] );
		$attribute_name = sanitize_text_field( $args['name'] );

		if ( empty( $options ) ) {
			return $html;
		}

		$html = '<div class="wc-image-swatches-container" data-attribute="' . esc_attr( $attribute_name ) . '">';

		foreach ( $options as $option ) {
			// Sanitize option value
			$option = sanitize_text_field( $option );

			// Get term object
			$term = get_term_by( 'slug', $option, $attribute_key );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			// Get swatch image - ensure it's an integer
			$swatch_id = intval( get_term_meta( $term->term_id, 'swatch_image', true ) );
			$swatch_url = $swatch_id > 0 ? wp_get_attachment_image_url( $swatch_id, 'thumbnail' ) : '';

			// Check if this variation is available
			$is_available = false;
			$variations = $product->get_available_variations();

			foreach ( $variations as $variation ) {
				$attr_value = isset( $variation['attributes'][ $attribute_name ] ) ? sanitize_text_field( $variation['attributes'][ $attribute_name ] ) : '';
				if ( $attr_value === $option && ! empty( $variation['is_in_stock'] ) ) {
					$is_available = true;
					break;
				}
			}

			$class = 'wc-swatch';
			if ( ! $is_available ) {
				$class .= ' wc-swatch--unavailable';
			}

			$html .= '<label class="' . esc_attr( $class ) . '" title="' . esc_attr( $term->name ) . '">';

			if ( $swatch_url ) {
				$html .= '<img src="' . esc_url( $swatch_url ) . '" alt="' . esc_attr( $term->name ) . '" />';
			} else {
				$html .= '<span class="wc-swatch__fallback">' . esc_html( $term->name ) . '</span>';
			}

			$html .= '<input type="radio" name="' . esc_attr( $attribute_name ) . '" value="' . esc_attr( $option ) . '" ' . ( ! $is_available ? 'disabled' : '' ) . ' />';
			$html .= '</label>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Add settings page
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			'Image Swatches',
			'Image Swatches',
			'manage_woocommerce',
			'wc-image-swatches',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'wc-image-swatches',
			'wc_image_swatches_attributes',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_attributes_setting' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize attributes setting
	 */
	public function sanitize_attributes_setting( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		// Sanitize each attribute key
		$sanitized = array_map( 'sanitize_text_field', $value );

		// Validate each attribute exists
		$attributes = wc_get_attribute_taxonomies();
		$valid_attributes = array();

		foreach ( $attributes as $attribute ) {
			$attr_key = 'pa_' . $attribute->attribute_name;
			if ( in_array( $attr_key, $sanitized, true ) ) {
				$valid_attributes[] = $attr_key;
			}
		}

		return $valid_attributes;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wc-image-swatches' ) );
		}

		// Get all product attributes
		$attributes = wc_get_attribute_taxonomies();
		$enabled = $this->get_enabled_swatch_attributes();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooCommerce Image Swatches Settings', 'wc-image-swatches' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wc-image-swatches' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Swatches For', 'wc-image-swatches' ); ?></th>
						<td>
							<?php if ( ! empty( $attributes ) ) : ?>
								<?php foreach ( $attributes as $attribute ) : ?>
									<?php $attr_key = 'pa_' . sanitize_text_field( $attribute->attribute_name ); ?>
									<label>
										<input type="checkbox" name="wc_image_swatches_attributes[]" value="<?php echo esc_attr( $attr_key ); ?>" <?php checked( in_array( $attr_key, $enabled, true ) ); ?> />
										<?php echo esc_html( $attribute->attribute_label ); ?>
									</label><br />
								<?php endforeach; ?>
							<?php else : ?>
								<p><?php esc_html_e( 'No product attributes found. Create attributes in WooCommerce first.', 'wc-image-swatches' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Instructions', 'wc-image-swatches' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Select which attributes should display as image swatches above', 'wc-image-swatches' ); ?></li>
				<li><?php esc_html_e( 'Go to Products > Attributes in WordPress admin', 'wc-image-swatches' ); ?></li>
				<li><?php esc_html_e( 'Edit each attribute and add a swatch image to each term', 'wc-image-swatches' ); ?></li>
				<li><?php esc_html_e( 'View a product page - selected attributes will display as swatches', 'wc-image-swatches' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( is_product() ) {
			wp_enqueue_style( 'wc-image-swatches', WC_IMAGE_SWATCHES_URL . 'assets/frontend.css' );
			wp_enqueue_script( 'wc-image-swatches', WC_IMAGE_SWATCHES_URL . 'assets/frontend.js', array( 'jquery' ), '1.0.0', true );
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only on taxonomy screens
		if ( strpos( $hook, 'edit-tags.php' ) !== false ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wc-image-swatches-admin', WC_IMAGE_SWATCHES_URL . 'assets/admin.css' );
			wp_enqueue_script( 'wc-image-swatches-admin', WC_IMAGE_SWATCHES_URL . 'assets/admin.js', array( 'jquery' ), '1.0.0', true );
		}
	}
}

// Initialize plugin
WC_Image_Swatches::get_instance();
