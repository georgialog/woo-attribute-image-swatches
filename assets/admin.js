jQuery(document).ready(function($) {
	'use strict';

	// Media uploader for swatch images
	var mediaUploader;

	$(document).on('click', '.swatch_image_button', function(e) {
		e.preventDefault();

		// Check if wp.media is available
		if ( typeof wp === 'undefined' || ! wp.media ) {
			console.error( 'WordPress media library not available' );
			return false;
		}

		// Create a new media uploader instance
		mediaUploader = wp.media({
			title: 'Select Swatch Image',
			button: {
				text: 'Use this image'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		// Handle image selection
		mediaUploader.on('select', function() {
			var attachment = mediaUploader.state().get('selection').first().toJSON();

			// Validate attachment
			if ( ! attachment.id || ! attachment.url ) {
				console.error( 'Invalid attachment' );
				return;
			}

			// Validate it's an image
			if ( ! attachment.type || attachment.type.indexOf( 'image' ) === -1 ) {
				alert( 'Please select an image file' );
				return;
			}

			// Update the hidden input with the attachment ID (ensure integer)
			var attachmentId = parseInt( attachment.id, 10 );
			if ( ! Number.isInteger( attachmentId ) || attachmentId <= 0 ) {
				console.error( 'Invalid attachment ID' );
				return;
			}

			var $hiddenInput = $('#swatch_image');
			$hiddenInput.val( attachmentId );

			// Update the preview image with proper escaping
			var $preview = $('#swatch_image_img');
			$preview.attr( 'src', attachment.url )
				.attr( 'alt', attachment.alt || 'Swatch preview' )
				.show();

			// Show remove button
			$('.swatch_image_remove').show();
		});

		mediaUploader.open();
		return false;
	});

	// Remove swatch image
	$(document).on('click', '.swatch_image_remove', function(e) {
		e.preventDefault();

		$('#swatch_image').val('');
		$('#swatch_image_img').attr('src', '').hide();
		$(this).hide();

		return false;
	});
});
