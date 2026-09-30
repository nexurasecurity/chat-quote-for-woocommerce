/**
 * Product Inquiry Button & Dynamic Form Builder Scripts.
 * Handles frontend modal opening/closing, AJAX submission, and admin form builder interactions.
 */

(function ($) {
	'use strict';

	/* ==========================================================================
	   1. Frontend Modal & Submission
	   ========================================================================== */

	var InquiryFrontend = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Open modal on button click
			$( document ).on( 'click', '.cqfw-inquiry-btn', function ( e ) {
				e.preventDefault();
				self.openModal( $( this ) );
			} );

			// Close modal
			$( document ).on( 'click', '.cqfw-inquiry-close, .cqfw-inquiry-close-btn, .cqfw-inquiry-backdrop', function ( e ) {
				e.preventDefault();
				self.closeModal();
			} );

			// Close on Escape key
			$( document ).on( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && $( '#cqfw-inquiry-modal' ).is( ':visible' ) ) {
					self.closeModal();
				}
			} );

			// AJAX form submit
			$( document ).on( 'submit', '#cqfw-inquiry-form', function ( e ) {
				e.preventDefault();
				self.submitForm( $( this ) );
			} );
		},

		openModal: function ( $btn ) {
			var $modal = $( '#cqfw-inquiry-modal' );
			if ( ! $modal.length ) return;

			var productId   = $btn.data( 'product-id' ) || '';
			var productName = $btn.data( 'product-name' ) || '';
			var productPrice= $btn.data( 'product-price' ) || '';
			var productImage= $btn.data( 'product-image' ) || '';

			// Variable product support: check if on single product page with selected variation
			var $varForm = $( 'form.variations_form' );
			if ( $varForm.length ) {
				var varId = $varForm.find( 'input[name="variation_id"]' ).val();
				if ( varId && varId !== '0' && varId !== '' ) {
					productId = varId;

					// Collect selected variation attributes (e.g. Size: Large, Color: Blue)
					var varAttrs = [];
					$varForm.find( 'select[name^="attribute_"]' ).each( function () {
						var $sel = $( this );
						var val  = $sel.val();
						if ( val ) {
							var label = $sel.closest( 'tr' ).find( 'label' ).text().trim() || $sel.attr( 'name' ).replace( 'attribute_', '' ).replace( 'pa_', '' );
							label = label.replace( /[:*]/g, '' ).trim();
							var optText = $sel.find( 'option:selected' ).text().trim() || val;
							varAttrs.push( label + ': ' + optText );
						}
					} );

					if ( varAttrs.length ) {
						productName += ' (' + varAttrs.join( ', ' ) + ')';
					}

					// Variation price if available
					var $varPrice = $varForm.find( '.woocommerce-variation-price .price, .single_variation .price' );
					if ( $varPrice.length && $.trim( $varPrice.text() ) ) {
						productPrice = $varPrice.html();
					}

					// Variation image if updated by WooCommerce gallery
					var $galleryImg = $( '.woocommerce-product-gallery__image img' ).first();
					if ( $galleryImg.length && $galleryImg.attr( 'src' ) ) {
						productImage = $galleryImg.attr( 'src' );
					}
				}
			}

			// Populate hidden fields
			$modal.find( '#cqfw-inquiry-product-id' ).val( productId );
			$modal.find( '#cqfw-inquiry-product-name' ).val( productName );

			// Populate product preview
			$modal.find( '.cqfw-inquiry-product-name' ).text( productName );
			$modal.find( '.cqfw-inquiry-product-price' ).html( productPrice );

			var $thumb = $modal.find( '.cqfw-inquiry-product-thumb' );
			if ( productImage ) {
				$thumb.attr( 'src', productImage ).show();
			} else {
				$thumb.hide();
			}

			// Reset form state & messages
			var $form = $modal.find( '#cqfw-inquiry-form' );
			var $successBox = $modal.find( '.cqfw-inquiry-success-box' );
			var $msg = $modal.find( '.cqfw-inquiry-response-msg' );

			$form.show();
			$successBox.hide();
			$msg.hide().removeClass( 'is-error is-success' ).empty();

			var $submitBtn = $form.find( '.cqfw-inquiry-submit-btn' );
			$submitBtn.prop( 'disabled', false );
			$submitBtn.find( '.cqfw-btn-spinner' ).hide();
			$submitBtn.find( '.cqfw-btn-text' ).text( cqfwInquiry && cqfwInquiry.i18n ? cqfwInquiry.i18n.sendText || 'Send Inquiry' : 'Send Inquiry' );

			// Show modal
			$modal.fadeIn( 200, function () {
				$modal.attr( 'aria-hidden', 'false' );
				// Focus first input
				var $first = $modal.find( '.cqfw-inquiry-input:visible' ).first();
				if ( $first.length ) {
					$first.focus();
				}
			} );
		},

		closeModal: function () {
			var $modal = $( '#cqfw-inquiry-modal' );
			if ( ! $modal.length ) return;

			$modal.fadeOut( 180, function () {
				$modal.attr( 'aria-hidden', 'true' );
			} );
		},

		submitForm: function ( $form ) {
			var self = this;
			var $modal = $( '#cqfw-inquiry-modal' );
			var $msg = $form.find( '.cqfw-inquiry-response-msg' );
			var $submitBtn = $form.find( '.cqfw-inquiry-submit-btn' );
			var $spinner = $submitBtn.find( '.cqfw-btn-spinner' );
			var $btnText = $submitBtn.find( '.cqfw-btn-text' );

			$msg.hide().removeClass( 'is-error is-success' ).empty();

			// Basic HTML5 validation check
			var valid = true;
			$form.find( '[required]:visible' ).each( function () {
				var val = $.trim( $( this ).val() );
				if ( ! val ) {
					valid = false;
					$( this ).css( 'border-color', '#ef4444' );
				} else {
					$( this ).css( 'border-color', '' );
				}
			} );

			if ( ! valid ) {
				$msg.addClass( 'is-error' ).text( cqfwInquiry.i18n.required || 'Please fill in all required fields.' ).fadeIn();
				return;
			}

			// Show loading
			$submitBtn.prop( 'disabled', true );
			$spinner.show();
			$btnText.text( cqfwInquiry.i18n.submitting || 'Sending inquiry...' );

			var formData = $form.serialize();
			if ( formData.indexOf( 'nonce=' ) === -1 && window.cqfwInquiry && cqfwInquiry.nonce ) {
				formData += '&nonce=' + encodeURIComponent( cqfwInquiry.nonce );
			}

			$.ajax( {
				url: cqfwInquiry.ajaxUrl,
				type: 'POST',
				data: formData,
				dataType: 'json',
				success: function ( res ) {
					$spinner.hide();
					$submitBtn.prop( 'disabled', false );
					$btnText.text( 'Send Inquiry' );

					if ( res && res.success ) {
						// Show success box
						$form.slideUp( 200, function () {
							$modal.find( '.cqfw-inquiry-success-box' ).fadeIn( 200 );
						} );
					} else {
						var err = ( res && res.data && res.data.message ) ? res.data.message : ( cqfwInquiry.i18n.error || 'Failed to send inquiry.' );
						$msg.addClass( 'is-error' ).text( err ).fadeIn();
					}
				},
				error: function ( xhr ) {
					$spinner.hide();
					$submitBtn.prop( 'disabled', false );
					$btnText.text( 'Send Inquiry' );
					var errMsg = cqfwInquiry.i18n.error || 'Failed to send inquiry.';
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						errMsg = xhr.responseJSON.data.message;
					}
					$msg.addClass( 'is-error' ).text( errMsg ).fadeIn();
				}
			} );
		}
	};

	/* ==========================================================================
	   2. Admin Form Builder
	   ========================================================================== */

	var InquiryAdminBuilder = {
		init: function () {
			if ( ! $( '#cqfw-fields-list' ).length ) return;
			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Add Custom Field button
			$( document ).on( 'click', '.cqfw-add-field-btn', function ( e ) {
				e.preventDefault();
				self.promptNewField();
			} );

			// Remove Field
			$( document ).on( 'click', '.cqfw-remove-field-btn', function ( e ) {
				e.preventDefault();
				var $card = $( this ).closest( '.cqfw-field-card' );
				var isCore = $card.find( '.cqfw-f-core' ).val() === '1';
				if ( isCore ) {
					alert( 'Core fields cannot be deleted.' );
					return;
				}

				if ( confirm( ( window.cqfwInquiryAdmin && cqfwInquiryAdmin.i18n.confirmDelete ) || 'Are you sure you want to remove this field?' ) ) {
					$card.fadeOut( 200, function () {
						$( this ).remove();
						self.syncFieldsJson();
					} );
				}
			} );

			// Move Up
			$( document ).on( 'click', '.cqfw-move-up', function ( e ) {
				e.preventDefault();
				var $card = $( this ).closest( '.cqfw-field-card' );
				var $prev = $card.prev( '.cqfw-field-card' );
				if ( $prev.length ) {
					$card.insertBefore( $prev );
					self.syncFieldsJson();
				}
			} );

			// Move Down
			$( document ).on( 'click', '.cqfw-move-down', function ( e ) {
				e.preventDefault();
				var $card = $( this ).closest( '.cqfw-field-card' );
				var $next = $card.next( '.cqfw-field-card' );
				if ( $next.length ) {
					$card.insertAfter( $next );
					self.syncFieldsJson();
				}
			} );

			// Add Dropdown Option
			$( document ).on( 'click', '.cqfw-add-option-btn', function ( e ) {
				e.preventDefault();
				var $box = $( this ).closest( '.cqfw-select-options-box' );
				var $tags = $box.find( '.cqfw-options-tags' );
				var optVal = prompt( ( window.cqfwInquiryAdmin && cqfwInquiryAdmin.i18n.enterOption ) || 'Enter option name:' );
				if ( optVal && $.trim( optVal ) ) {
					var $tag = $(
						'<span class="cqfw-option-tag" style="display:inline-flex;align-items:center;gap:6px;background:#e2e8f0;padding:3px 8px;border-radius:4px;font-size:12px;color:#1e293b;">' +
							'<input type="text" class="cqfw-opt-val" value="' + self.escapeHtml( $.trim( optVal ) ) + '" style="border:none;background:transparent;padding:0;width:120px;height:22px;font-size:12px;" />' +
							'<button type="button" class="cqfw-remove-option" style="border:none;background:none;cursor:pointer;color:#94a3b8;font-weight:bold;padding:0 2px;">&times;</button>' +
						'</span>'
					);
					$tags.append( $tag );
					self.syncFieldsJson();
				}
			} );

			// Remove Dropdown Option
			$( document ).on( 'click', '.cqfw-remove-option', function ( e ) {
				e.preventDefault();
				$( this ).closest( '.cqfw-option-tag' ).remove();
				self.syncFieldsJson();
			} );

			// Type change handler
			$( document ).on( 'change', '.cqfw-f-type', function () {
				var $card = $( this ).closest( '.cqfw-field-card' );
				var newType = $( this ).val();
				var $optsBox = $card.find( '.cqfw-select-options-box' );

				if ( newType === 'select' ) {
					if ( ! $optsBox.length ) {
						var boxHtml =
							'<div class="cqfw-select-options-box" style="margin-top:10px;padding:10px 14px;background:#f8fafc;border-radius:6px;border:1px dashed #cbd5e1;">' +
								'<div style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">' +
									'<span>Dropdown Options (one per tag):</span>' +
									'<button type="button" class="button button-small cqfw-add-option-btn">+ Add Option</button>' +
								'</div>' +
								'<div class="cqfw-options-tags" style="display:flex;flex-wrap:wrap;gap:6px;">' +
									'<span class="cqfw-option-tag" style="display:inline-flex;align-items:center;gap:6px;background:#e2e8f0;padding:3px 8px;border-radius:4px;font-size:12px;color:#1e293b;">' +
										'<input type="text" class="cqfw-opt-val" value="Option 1" style="border:none;background:transparent;padding:0;width:120px;height:22px;font-size:12px;" />' +
										'<button type="button" class="cqfw-remove-option" style="border:none;background:none;cursor:pointer;color:#94a3b8;font-weight:bold;padding:0 2px;">&times;</button>' +
									'</span>' +
								'</div>' +
							'</div>';
						$card.append( boxHtml );
					} else {
						$optsBox.show();
					}
				} else {
					if ( $optsBox.length ) {
						$optsBox.remove();
					}
				}
				self.syncFieldsJson();
			} );

			// Input changes sync JSON
			$( document ).on( 'input change', '.cqfw-f-label, .cqfw-f-placeholder, .cqfw-f-required, .cqfw-f-enabled, .cqfw-opt-val', function () {
				self.syncFieldsJson();
			} );

			// Ensure sync on form submit
			$( document ).on( 'submit', 'form', function () {
				if ( $( '#cqfw-fields-list' ).length ) {
					self.syncFieldsJson();
				}
			} );
		},

		promptNewField: function () {
			var self = this;
			var randId = 'field_custom_' + Math.floor( Math.random() * 10000 );
			var newCardHtml =
				'<div class="cqfw-field-card" data-id="' + randId + '" style="background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:12px 16px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">' +
					'<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
						'<div class="cqfw-field-order-ctrls" style="display:flex;flex-direction:column;gap:2px;">' +
							'<button type="button" class="cqfw-order-btn cqfw-move-up" title="Move up" style="border:1px solid #cbd5e1;background:#f8fafc;padding:0;width:24px;height:18px;line-height:16px;border-radius:3px;cursor:pointer;font-size:10px;">▲</button>' +
							'<button type="button" class="cqfw-order-btn cqfw-move-down" title="Move down" style="border:1px solid #cbd5e1;background:#f8fafc;padding:0;width:24px;height:18px;line-height:16px;border-radius:3px;cursor:pointer;font-size:10px;">▼</button>' +
						'</div>' +
						'<div style="width:105px;">' +
							'<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:2px;">Type</label>' +
							'<select class="cqfw-f-type" style="width:100%;height:32px;font-size:12px;padding:2px 6px;">' +
								'<option value="text">Text</option>' +
								'<option value="email">Email</option>' +
								'<option value="tel">Phone</option>' +
								'<option value="select">Dropdown</option>' +
								'<option value="textarea">Textarea</option>' +
								'<option value="number">Number</option>' +
								'<option value="checkbox">Checkbox</option>' +
							'</select>' +
							'<input type="hidden" class="cqfw-f-id" value="' + randId + '" />' +
							'<input type="hidden" class="cqfw-f-core" value="0" />' +
						'</div>' +
						'<div style="flex:1;min-width:160px;">' +
							'<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:2px;">Label</label>' +
							'<input type="text" class="cqfw-f-label" value="Custom Field" placeholder="Field Label" style="width:100%;height:32px;" />' +
						'</div>' +
						'<div style="flex:1;min-width:160px;">' +
							'<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:2px;">Placeholder</label>' +
							'<input type="text" class="cqfw-f-placeholder" value="" placeholder="Optional Placeholder" style="width:100%;height:32px;" />' +
						'</div>' +
						'<div style="padding-top:16px;">' +
							'<label style="font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;">' +
								'<input type="checkbox" class="cqfw-f-required" /> Required' +
							'</label>' +
						'</div>' +
						'<div style="padding-top:16px;">' +
							'<label style="font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;">' +
								'<input type="checkbox" class="cqfw-f-enabled" checked /> Enabled' +
							'</label>' +
						'</div>' +
						'<div style="padding-top:16px;">' +
							'<button type="button" class="button cqfw-remove-field-btn" style="color:#ef4444;border-color:#fca5a5;background:#fef2f2;height:30px;line-height:28px;padding:0 8px;">&times; Remove</button>' +
						'</div>' +
					'</div>' +
				'</div>';

			var $newCard = $( newCardHtml );
			$( '#cqfw-fields-list' ).append( $newCard );
			$newCard.find( '.cqfw-f-label' ).focus().select();
			self.syncFieldsJson();
		},

		syncFieldsJson: function () {
			var fields = [];
			$( '#cqfw-fields-list .cqfw-field-card' ).each( function () {
				var $card = $( this );
				var id          = $card.find( '.cqfw-f-id' ).val();
				var type        = $card.find( '.cqfw-f-type' ).val();
				var core        = $card.find( '.cqfw-f-core' ).val() === '1';
				var label       = $card.find( '.cqfw-f-label' ).val();
				var placeholder = $card.find( '.cqfw-f-placeholder' ).val();
				var required    = $card.find( '.cqfw-f-required' ).is( ':checked' );
				var enabled     = $card.find( '.cqfw-f-enabled' ).is( ':checked' );

				var item = {
					id: id,
					type: type,
					label: label,
					placeholder: placeholder,
					required: required,
					core: core,
					enabled: enabled
				};

				if ( type === 'select' ) {
					var options = [];
					$card.find( '.cqfw-opt-val' ).each( function () {
						var opt = $.trim( $( this ).val() );
						if ( opt ) {
							options.push( opt );
						}
					} );
					item.options = options;
				}

				fields.push( item );
			} );

			var jsonStr = JSON.stringify( fields );
			$( '#cqfw_inquiry_form_fields' ).val( jsonStr );
		},

		escapeHtml: function ( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		}
	};

	/* ==========================================================================
	   3. Admin Product Targeting & Replace Cart Controls
	   ========================================================================== */

	var InquiryAdminTargeting = {
		currentPage: 1,
		maxPages: 1,
		currentSearch: '',
		searchTimer: null,

		init: function () {
			if ( ! $( '#cqfw-pro-inquiry-display-mode' ).length && ! $( '#cqfw-pro-inquiry-replace-cart' ).length && ! $( '#cqfw-prod-pagination-bar' ).length ) return;

			var $bar = $( '#cqfw-prod-pagination-bar' );
			if ( $bar.length ) {
				var pMax = parseInt( $bar.attr( 'data-max-pages' ), 10 );
				var pCur = parseInt( $bar.attr( 'data-current-page' ), 10 );
				if ( ! isNaN( pMax ) && pMax > 0 ) {
					this.maxPages = pMax;
				}
				if ( ! isNaN( pCur ) && pCur > 0 ) {
					this.currentPage = pCur;
				}
			}

			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			// Toggle target products box based on display mode
			$( document ).on( 'change', '#cqfw-pro-inquiry-display-mode', function () {
				var mode = $( this ).val();
				if ( mode === 'specific' || mode === 'exclude' ) {
					$( '#cqfw-pro-product-selector-box' ).slideDown( 200 );
				} else {
					$( '#cqfw-pro-product-selector-box' ).slideUp( 200 );
				}
			} );

			// Toggle Replace Scope box
			$( document ).on( 'change', '#cqfw-pro-inquiry-replace-cart', function () {
				if ( $( this ).is( ':checked' ) ) {
					$( '#cqfw-replace-scope-box' ).slideDown( 200 );
				} else {
					$( '#cqfw-replace-scope-box' ).slideUp( 200 );
				}
			} );

			// Debounced live AJAX search (300ms)
			$( document ).on( 'input keyup', '#cqfw-product-search-input', function () {
				var q = $.trim( $( this ).val() );
				clearTimeout( self.searchTimer );
				self.searchTimer = setTimeout( function () {
					self.currentSearch = q;
					self.currentPage   = 1;
					self.fetchProducts();
				}, 300 );
			} );

			// Prev Page
			$( document ).on( 'click', '#cqfw-prod-prev-page', function ( e ) {
				e.preventDefault();
				if ( self.currentPage > 1 ) {
					self.currentPage--;
					self.fetchProducts();
				}
			} );

			// Next Page
			$( document ).on( 'click', '#cqfw-prod-next-page', function ( e ) {
				e.preventDefault();
				var $bar = $( '#cqfw-prod-pagination-bar' );
				var pMax = parseInt( $bar.attr( 'data-max-pages' ), 10 );
				if ( ! isNaN( pMax ) && pMax > 0 ) {
					self.maxPages = pMax;
				}

				if ( self.currentPage < self.maxPages ) {
					self.currentPage++;
					self.fetchProducts();
				}
			} );

			// Checkbox change -> sync with persistent hidden inputs
			$( document ).on( 'change', '.cqfw-product-item-chk', function () {
				var pid         = $( this ).val();
				var isChecked   = $( this ).is( ':checked' );
				var $hiddenWrap = $( '#cqfw-selected-products-hidden-inputs' );

				if ( isChecked ) {
					if ( ! $hiddenWrap.find( 'input[value="' + pid + '"]' ).length ) {
						$hiddenWrap.append( '<input type="hidden" name="cqfw_pro_inquiry_selected_products[]" value="' + pid + '">' );
					}
				} else {
					$hiddenWrap.find( 'input[value="' + pid + '"]' ).remove();
				}

				self.updateSelectedCount();
			} );

			// Clear all selections
			$( document ).on( 'click', '#cqfw-select-clear', function ( e ) {
				e.preventDefault();
				$( '#cqfw-selected-products-hidden-inputs' ).empty();
				$( '.cqfw-product-item-chk' ).prop( 'checked', false );
				self.updateSelectedCount();
			} );
		},

		fetchProducts: function () {
			var self = this;

			var ajaxUrl = ( typeof cqfwInquiryAdmin !== 'undefined' && cqfwInquiryAdmin.ajaxUrl )
				? cqfwInquiryAdmin.ajaxUrl
				: ( typeof ajaxurl !== 'undefined' ? ajaxurl : '' );

			var nonce = ( typeof cqfwInquiryAdmin !== 'undefined' && cqfwInquiryAdmin.nonce )
				? cqfwInquiryAdmin.nonce
				: ( $( '#cqfw_pro_nonce' ).val() || '' );

			if ( ! ajaxUrl ) {
				return;
			}

			$( '#cqfw-prod-loading-overlay' ).css( 'display', 'flex' );
			$( '#cqfw-prod-search-spinner' ).show();

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cqfw_inquiry_get_products',
					nonce: nonce,
					page: self.currentPage,
					search: self.currentSearch
				},
				success: function ( res ) {
					$( '#cqfw-prod-loading-overlay' ).hide();
					$( '#cqfw-prod-search-spinner' ).hide();

					if ( res && res.success && res.data ) {
						self.renderProducts( res.data );
					}
				},
				error: function ( xhr, status, err ) {
					$( '#cqfw-prod-loading-overlay' ).hide();
					$( '#cqfw-prod-search-spinner' ).hide();
					console.error( 'CQFW get products error:', err );
				}
			} );
		},

		renderProducts: function ( data ) {
			var self       = this;
			var $checklist = $( '#cqfw-product-checklist' );
			$checklist.empty();

			self.currentPage = parseInt( data.current_page, 10 ) || 1;
			self.maxPages    = parseInt( data.max_pages, 10 ) || 1;

			var $bar = $( '#cqfw-prod-pagination-bar' );
			$bar.attr( 'data-current-page', self.currentPage ).attr( 'data-max-pages', self.maxPages );

			if ( ! data.products || ! data.products.length ) {
				$checklist.html( '<p class="cqfw-no-prods-msg" style="font-size:12px;color:#94a3b8;margin:8px 0;">No products found.</p>' );
			} else {
				var $hiddenWrap = $( '#cqfw-selected-products-hidden-inputs' );
				$.each( data.products, function ( i, prod ) {
					var isChecked = $hiddenWrap.find( 'input[value="' + prod.id + '"]' ).length > 0;
					var skuText   = prod.sku ? ' (' + self.escapeHtml( prod.sku ) + ')' : '';
					var rowHtml   = '<label class="cqfw-product-checkbox-label" style="display:flex;align-items:center;gap:8px;padding:6px 4px;font-size:12px;color:#1e293b;cursor:pointer;border-bottom:1px solid #f8fafc;">' +
						'<input type="checkbox" class="cqfw-product-item-chk" value="' + prod.id + '"' + ( isChecked ? ' checked' : '' ) + ' />' +
						'<span class="cqfw-product-item-title" style="flex:1;">' + self.escapeHtml( prod.title ) + '</span>' +
						'<span style="font-size:10.5px;color:#94a3b8;">#' + prod.id + skuText + '</span>' +
						'</label>';
					$checklist.append( rowHtml );
				} );
			}

			// Update Pagination UI
			var totalCount = typeof data.total !== 'undefined' ? data.total : 0;
			$( '#cqfw-prod-page-info' ).html(
				'Page ' + self.currentPage + ' of ' + self.maxPages +
				' <span style="color:#94a3b8;margin-left:4px;">(' + totalCount + ' products)</span>'
			);

			$( '#cqfw-prod-prev-page' ).prop( 'disabled', self.currentPage <= 1 );
			$( '#cqfw-prod-next-page' ).prop( 'disabled', self.currentPage >= self.maxPages );
		},

		updateSelectedCount: function () {
			var count = $( '#cqfw-selected-products-hidden-inputs input' ).length;
			$( '#cqfw-selected-count' ).text( count + ' product(s) selected' );
		},

		escapeHtml: function ( str ) {
			return $( '<div>' ).text( str || '' ).html();
		}
	};

	/* ==========================================================================
	   4. Admin Inquiry Status Updates (AJAX)
	   ========================================================================== */

	var InquiryAdminStatus = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			$( document ).on( 'change', '.cqfw-quick-status-change', function () {
				var $select   = $( this );
				var inquiryId = $select.data( 'id' );
				var status    = $select.val();
				var nonce     = ( window.cqfwInquiryAdmin && cqfwInquiryAdmin.nonce ) || '';
				var ajaxUrl   = ( window.cqfwInquiryAdmin && cqfwInquiryAdmin.ajaxUrl ) || ( window.ajaxurl || '' );

				if ( ! inquiryId || ! ajaxUrl ) return;

				$select.prop( 'disabled', true ).css( 'opacity', '0.6' );

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'cqfw_update_inquiry_status',
						inquiry_id: inquiryId,
						status: status,
						nonce: nonce
					},
					dataType: 'json',
					success: function ( res ) {
						$select.prop( 'disabled', false ).css( 'opacity', '1' );
						if ( res && res.success && res.data ) {
							// Update badge in list table
							var $badge = $( '#cqfw-status-badge-' + inquiryId );
							if ( $badge.length ) {
								$badge.text( res.data.label )
									  .css( { 'background': res.data.bg, 'color': res.data.color } );
							}
							// Update badge in single view
							var $viewBadge = $( '.cqfw-view-status-badge' );
							if ( $viewBadge.length ) {
								$viewBadge.text( res.data.label )
									      .css( { 'background': res.data.bg, 'color': res.data.color } );
							}
							// Update bottom select in single view if exists
							var $bottomSelect = $( '#cqfw_status_bottom' );
							if ( $bottomSelect.length ) {
								$bottomSelect.val( status );
							}
						} else {
							alert( ( res && res.data && res.data.message ) || 'Failed to update status.' );
						}
					},
					error: function () {
						$select.prop( 'disabled', false ).css( 'opacity', '1' );
						alert( 'Error connecting to server to update status.' );
					}
				} );
			} );
		}
	};

	// Initialize on DOM ready
	$( document ).ready( function () {
		if ( typeof cqfwInquiry !== 'undefined' ) {
			InquiryFrontend.init();
		}
		if ( typeof cqfwInquiryAdmin !== 'undefined' || $( '#cqfw-fields-list' ).length ) {
			InquiryAdminBuilder.init();
		}
		if ( typeof cqfwInquiryAdmin !== 'undefined' || $( '#cqfw-pro-inquiry-display-mode' ).length ) {
			InquiryAdminTargeting.init();
		}
		if ( typeof cqfwInquiryAdmin !== 'undefined' || $( '.cqfw-quick-status-change' ).length ) {
			InquiryAdminStatus.init();
		}

		// Admin manual inquiry modal
		$( document ).on( 'click', '#cqfw-open-add-inquiry-modal', function ( e ) {
			e.preventDefault();
			$( '#cqfw-add-inquiry-modal' ).css( 'display', 'flex' );
		} );
		$( document ).on( 'click', '#cqfw-close-add-inquiry-modal, #cqfw-cancel-add-inquiry-modal', function ( e ) {
			e.preventDefault();
			$( '#cqfw-add-inquiry-modal' ).hide();
		} );
		$( document ).on( 'click', '#cqfw-add-inquiry-modal', function ( e ) {
			if ( $( e.target ).is( '#cqfw-add-inquiry-modal' ) ) {
				$( this ).hide();
			}
		} );
	} );

})( jQuery );
