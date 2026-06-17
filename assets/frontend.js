jQuery(document).ready(function($) {
	'use strict';

	// Handle swatch clicks
	$(document).on('click', '.wc-swatch', function() {
		var $swatch = $(this);
		var $radio = $swatch.find('input[type="radio"]');

		// Don't allow selection of disabled options
		if ($radio.is(':disabled')) {
			return false;
		}

		// Validate radio element exists
		if ( ! $radio.length ) {
			return false;
		}

		// Check the radio button
		$radio.prop('checked', true);

		// Get and validate attribute name
		var attributeName = $radio.attr('name');
		if ( ! attributeName || attributeName.indexOf('attribute_') !== 0 ) {
			return false;
		}

		// Update visual state
		updateSwatchStates( attributeName );

		// Get and validate the value
		var selectValue = $radio.val();
		if ( ! selectValue ) {
			return false;
		}

		// Find and trigger the corresponding select element
		var $select = $('select[name="' + attributeName.replace(/"/g, '') + '"]');
		if ( $select.length ) {
			$select.val( selectValue ).trigger('change');
		}

		// Also trigger on the form
		var $form = $('form.variations_form');
		if ( $form.length ) {
			$form.trigger('woocommerce_variation_select_change');
		}

		return false;
	});

	// Update visual state of swatches
	function updateSwatchStates( attributeName ) {
		// Sanitize attribute name
		attributeName = attributeName.replace(/[^a-zA-Z0-9_\-]/g, '');

		var $container = $('[data-attribute="' + attributeName + '"]');
		if ( ! $container.length ) {
			return;
		}

		var $selected = $container.find('input[type="radio"]:checked').closest('.wc-swatch');

		// Remove selected class from all swatches in this container
		$container.find('.wc-swatch').removeClass('wc-swatch--selected');

		// Add selected class to the checked swatch
		if ( $selected.length ) {
			$selected.addClass('wc-swatch--selected');
		}
	}

	// Sync with WooCommerce variation selection
	$(document).on('change', 'select[name^="attribute_"]', function() {
		var selectName = $(this).attr('name');
		if ( ! selectName ) {
			return;
		}

		// Sanitize select name
		selectName = selectName.replace(/[^a-zA-Z0-9_\-]/g, '');

		var selectValue = $(this).val();
		if ( ! selectValue ) {
			return;
		}

		// Find corresponding swatch container
		var $container = $('[data-attribute="' + selectName + '"]');
		if ( ! $container.length ) {
			return;
		}

		// Update radio selection
		$container.find('input[type="radio"]').prop('checked', false);

		var $targetRadio = $container.find('input[type="radio"][value="' + selectValue.replace(/"/g, '') + '"]');
		if ( $targetRadio.length ) {
			$targetRadio.prop('checked', true);
		}

		// Update visual state
		updateSwatchStates( selectName );
	});

	// Initialize on page load
	$(document).on('woocommerce_variation_select_change', function() {
		updateAvailabilityStates();
	});

	// Update availability states based on current variation state
	function updateAvailabilityStates() {
		var $form = $('form.variations_form');
		if ( ! $form.length ) {
			return;
		}

		var $swatches = $form.find('.wc-swatch');

		$swatches.each(function() {
			var $swatch = $(this);
			var $radio = $swatch.find('input[type="radio"]');
			var isAvailable = !$radio.is(':disabled');

			if (isAvailable) {
				$swatch.removeClass('wc-swatch--unavailable');
			} else {
				$swatch.addClass('wc-swatch--unavailable');
			}
		});
	}

	// Handle when WooCommerce resets attributes
	$(document).on('click', '.reset_variations', function() {
		$('.wc-swatch').removeClass('wc-swatch--selected');
		$('[data-attribute]').find('input[type="radio"]').prop('checked', false);
	});

	// Initialize states on load
	if ($('.wc-image-swatches-container').length) {
		$('.wc-image-swatches-container').each(function() {
			var attrName = $(this).data('attribute');
			if ( attrName ) {
				updateSwatchStates( attrName );
			}
		});
	}
});
