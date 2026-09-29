/* global cqfwQuoteData, jQuery */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		var $form = $( '#cqfw-quote-form' );
		var $wrapper = $( '#cqfw-quote-form-container' );
		var $success = $( '#cqfw-quote-success' );
		var currentStep = 1;

		if ( ! $form.length ) return;

		function updateProgress( step ) {
			$( '.cqfw-progress-step' ).each( function () {
				var st = parseInt( $( this ).data( 'step' ), 10 );
				if ( st < step ) {
					$( this ).removeClass( 'is-active' ).addClass( 'is-completed' );
				} else if ( st === step ) {
					$( this ).removeClass( 'is-completed' ).addClass( 'is-active' );
				} else {
					$( this ).removeClass( 'is-active is-completed' );
				}
			} );
		}

		function showStep( step ) {
			$( '.cqfw-form-step' ).removeClass( 'is-active' );
			$( '.cqfw-form-step[data-step="' + step + '"]' ).addClass( 'is-active' );
			updateProgress( step );
			currentStep = step;

			if ( step === 3 ) {
				populateSummary();
			}
		}

		function validateStep( step ) {
			var isValid = true;
			var $currentStep = $( '.cqfw-form-step[data-step="' + step + '"]' );

			$currentStep.find( 'input[required], textarea[required], select[required]' ).each( function () {
				var $input = $( this );
				var val = $input.val() ? $input.val().trim() : '';
				var type = $input.attr( 'type' );
				var $err = $input.siblings( '.cqfw-error-msg' );
				if ( ! $err.length ) {
					$err = $input.closest( '.cqfw-form-group' ).find( '.cqfw-error-msg' );
				}

				if ( type === 'file' ) {
					if ( ! this.files || ! this.files.length ) {
						isValid = false;
						$input.addClass( 'is-invalid' );
						$err.text( 'Please choose a file.' );
					} else {
						$input.removeClass( 'is-invalid' );
						$err.text( '' );
					}
					return;
				}

				if ( val === '' ) {
					isValid = false;
					$input.addClass( 'is-invalid' );
					$err.text( 'This field is required.' );
				} else if ( type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( val ) ) {
					isValid = false;
					$input.addClass( 'is-invalid' );
					$err.text( 'Please enter a valid email address.' );
				} else {
					$input.removeClass( 'is-invalid' );
					$err.text( '' );
				}
			} );

			return isValid;
		}

		function populateSummary() {
			$( '#cqfw-sum-name' ).text( $( '#cqfw_q_name' ).val() );
			$( '#cqfw-sum-email' ).text( $( '#cqfw_q_email' ).val() );
			$( '#cqfw-sum-phone' ).text( $( '#cqfw_q_phone' ).val() );
			$( '#cqfw-sum-qty' ).text( $( '#cqfw_q_qty' ).val() );
			$( '#cqfw-sum-msg' ).text( $( '#cqfw_q_message' ).val() );

			var $customContainer = $( '#cqfw-sum-custom-fields' );
			$customContainer.empty();

			$( '.cqfw-form-step[data-step="2"] .cqfw-pro-field' ).each( function () {
				var $field = $( this );
				var label = $field.find( 'label' ).clone().children().remove().end().text().trim();
				var $fileInput = $field.find( 'input[type="file"]' );
				if ( $fileInput.length && $fileInput[0].files && $fileInput[0].files.length ) {
					var fileName = $fileInput[0].files[0].name;
					$customContainer.append( '<p><strong>' + label + ':</strong> 📎 ' + fileName + '</p>' );
				} else {
					var $txtInput = $field.find( 'input:not([type="file"]), textarea, select' );
					if ( $txtInput.length && $txtInput.val() ) {
						$customContainer.append( '<p><strong>' + label + ':</strong> ' + $txtInput.val().trim() + '</p>' );
					}
				}
			} );
		}

		// Navigation Handlers
		$( '.cqfw-btn-next' ).on( 'click', function () {
			var nextStep = parseInt( $( this ).data( 'next' ), 10 );
			if ( validateStep( currentStep ) ) {
				showStep( nextStep );
			}
		} );

		$( '.cqfw-btn-prev' ).on( 'click', function () {
			var prevStep = parseInt( $( this ).data( 'prev' ), 10 );
			showStep( prevStep );
		} );

		// Clear validation on input
		$form.find( 'input, textarea, select' ).on( 'input change', function () {
			$( this ).removeClass( 'is-invalid' );
			var $err = $( this ).siblings( '.cqfw-error-msg' );
			if ( ! $err.length ) {
				$err = $( this ).closest( '.cqfw-form-group' ).find( '.cqfw-error-msg' );
			}
			$err.text( '' );
		} );

		// Form Submission
		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			if ( ! validateStep( 1 ) || ! validateStep( 2 ) ) {
				return;
			}

			var $btn = $( '#cqfw-submit-quote-btn' );
			var $btnText = $btn.find( '.cqfw-btn-text' );
			var $spinner = $btn.find( '.cqfw-spinner' );

			$btn.prop( 'disabled', true );
			$btnText.hide();
			$spinner.show();

			var formData = new FormData( $form[0] );
			formData.append( 'action', 'cqfw_submit_quote' );
			formData.append( 'nonce', cqfwQuoteData.nonce );

			$.ajax( {
				url: cqfwQuoteData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function ( response ) {
					if ( response.success ) {
						$form.hide();
						$( '.cqfw-form-progress' ).hide();
						$success.show();
					} else {
						alert( ( response.data && response.data.message ) ? response.data.message : 'An error occurred. Please try again.' );
						$btn.prop( 'disabled', false );
						$btnText.show();
						$spinner.hide();
					}
				},
				error: function () {
					alert( 'Network error. Please try again.' );
					$btn.prop( 'disabled', false );
					$btnText.show();
					$spinner.hide();
				}
			} );
		} );

	} );
}( jQuery ) );
