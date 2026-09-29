/**
 * Chat Quote - Chat Styles Admin JS
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// Initialize Color Pickers
		$('.cqfw-color-field').wpColorPicker();

		var isPro = window.cqfwStylesConfig ? window.cqfwStylesConfig.isPro : false;

		// Card Selection
		$('.cqfw-style-card').on('click', function (e) {
			var $card = $(this);
			// Shop button style cards are handled in admin-settings.js
			if ($card.data('shop-style-id')) {
				return;
			}
			var styleId = $card.data('style-id');
			var isProStyle = $card.data('is-pro') == '1';
			var title = $card.data('style-title');
			var desc = $card.data('style-desc');

			

			// Activate Card
			$('.cqfw-style-card').removeClass('is-active');
			$card.addClass('is-active');
			$('#cqfw_active_style_input').val(styleId).trigger('change');
			$(document).trigger('cqfw:style-changed', [styleId]);

			// Update Section 2 Header
			if (title) {
				$('#cqfw-customize-header-title').text(title);
			}
			if (desc) {
				$('#cqfw-customize-header-desc').text(desc);
			}

			// Scroll into view if click action was "Customize"
			if ($(e.target).hasClass('cqfw-style-btn') || $(e.target).closest('.cqfw-style-btn').length) {
				$('html, body').animate({
					scrollTop: $('#cqfw-customize-section').offset().top - 80
				}, 400);
			}

			// Custom Button Image / Avatar: ONLY open when Custom (style_99) is clicked
			if (styleId === 'style_99') {
				$('#cqfw_custom_image_row').slideDown(200).css({
					background: '#f0fdf4',
					padding: '12px',
					borderRadius: '8px',
					border: '1.5px dashed #22c55e'
				});
			} else {
				$('#cqfw_custom_image_row').slideUp(200);
			}

			// Handle Agent Avatars row for Style 10, 11, 12
			if (styleId === 'style_10' || styleId === 'style_12') {
				$('#cqfw_agent_avatars_row').slideDown(250);
				$('#cqfw_agent_uploader_1, #cqfw_agent_uploader_2, #cqfw_agent_uploader_3').show();
				$('#cqfw_agent_label_1').text('Agent 1');
				if (!$('#cqfw_agent_1_avatar').val()) {
					$('#cqfw_agent_1_preview').attr('src', (window.cqfwStylesConfig && window.cqfwStylesConfig.urls) ? window.cqfwStylesConfig.urls.agent1 : '');
				}
			} else if (styleId === 'style_11') {
				$('#cqfw_agent_avatars_row').slideDown(250);
				$('#cqfw_agent_uploader_1').show();
				$('#cqfw_agent_uploader_2, #cqfw_agent_uploader_3').hide();
				$('#cqfw_agent_label_1').text('Sales Support Agent');
				if (!$('#cqfw_agent_1_avatar').val()) {
					$('#cqfw_agent_1_preview').attr('src', (window.cqfwStylesConfig && window.cqfwStylesConfig.urls) ? window.cqfwStylesConfig.urls.agentSales : '');
				}
			} else {
				$('#cqfw_agent_avatars_row').slideUp(200);
			}
		});

		// Popup Style Card Selection (Visual Cards Grid)
		$('.cqfw-popup-style-card').on('click', function (e) {
			var $card = $(this);
			var popupStyleId = $card.data('popup-style-id');
			var isProStyle = $card.data('is-pro') == '1';

			

			// Activate Card
			$('.cqfw-popup-style-card').removeClass('is-active');
			$('.cqfw-popup-style-card').find('.cqfw-style-btn.customize').text('Select Style');
			$card.addClass('is-active');
			$card.find('.cqfw-style-btn.customize').text('✓ Selected');
			$('#cqfw_popup_style_input').val(popupStyleId);
		});

		// Add Icon checkbox toggle
		$('#cqfw_add_icon').on('change', function () {
			if ($(this).is(':checked')) {
				$('#cqfw_icon_color_row, #cqfw_icon_size_row').slideDown(200);
			} else {
				$('#cqfw_icon_color_row, #cqfw_icon_size_row').slideUp(200);
			}
		}).trigger('change');

		// Custom Image Media Uploader (Style 99)
		var mediaFrame;
		$('#cqfw_upload_img_btn').on('click', function (e) {
			e.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: (window.cqfwStylesConfig && window.cqfwStylesConfig.i18n) ? window.cqfwStylesConfig.i18n.mediaTitle : 'Choose Button Image',
				button: {
					text: (window.cqfwStylesConfig && window.cqfwStylesConfig.i18n) ? window.cqfwStylesConfig.i18n.mediaButton : 'Use This Image'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				$('#cqfw_custom_image').val(attachment.url);
				$('#cqfw_custom_image_preview').attr('src', attachment.url);
				$('#cqfw_remove_img_btn').show();
			});

			mediaFrame.open();
		});

		$('#cqfw_remove_img_btn').on('click', function (e) {
			e.preventDefault();
			$('#cqfw_custom_image').val('');
			$('#cqfw_custom_image_preview').attr('src', '');
			$(this).hide();
		});

		// Agent Avatar Media Uploader
		var agentMediaFrames = {};
		function openAgentMediaFrame(slot) {
			slot = parseInt(slot, 10) || 1;

			if (agentMediaFrames[slot]) {
				agentMediaFrames[slot].open();
				return;
			}

			agentMediaFrames[slot] = wp.media({
				title: (window.cqfwStylesConfig && window.cqfwStylesConfig.i18n && window.cqfwStylesConfig.i18n.agentMediaTitle) 
					? window.cqfwStylesConfig.i18n.agentMediaTitle + ' (#' + slot + ')' 
					: 'Choose Agent Avatar Photo',
				button: {
					text: (window.cqfwStylesConfig && window.cqfwStylesConfig.i18n && window.cqfwStylesConfig.i18n.agentMediaButton) 
						? window.cqfwStylesConfig.i18n.agentMediaButton 
						: 'Set Agent Avatar'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			agentMediaFrames[slot].on('select', function () {
				var attachment = agentMediaFrames[slot].state().get('selection').first().toJSON();
				var imgUrl = attachment.url;

				// Update hidden form input
				$('#cqfw_agent_' + slot + '_avatar').val(imgUrl);

				// Update Section 2 preview
				$('#cqfw_agent_' + slot + '_preview').attr('src', imgUrl);
				$('.cqfw-btn-agent-reset[data-agent-slot="' + slot + '"]').show();

				// Update preview cards immediately
				$('.cqfw-agent-slot-' + slot + '-img').attr('src', imgUrl);

				// Visual feedback highlight
				$('#cqfw_agent_' + slot + '_preview').css({
					transform: 'scale(1.15)',
					transition: 'transform 0.25s ease'
				});
				setTimeout(function () {
					$('#cqfw_agent_' + slot + '_preview').css('transform', 'scale(1)');
				}, 300);
			});

			agentMediaFrames[slot].open();
		}

		// Click on "Change" button or avatar preview in Section 2
		$(document).on('click', '.cqfw-btn-agent-upload, .cqfw-agent-edit-overlay, .cqfw-agent-avatar-preview-wrap', function (e) {
			e.preventDefault();
			var slot = $(this).closest('.cqfw-agent-uploader-item').find('.cqfw-btn-agent-upload').data('agent-slot') || $(this).data('agent-slot') || 1;
			openAgentMediaFrame(slot);
		});

		// Click directly on an agent avatar inside the preview cards
		$(document).on('click', '.cqfw-clickable-avatar', function (e) {
			e.stopPropagation();
			var slot = $(this).data('agent-slot') || 1;
			var $card = $(this).closest('.cqfw-style-card');
			if (!$card.hasClass('is-active')) {
				$card.trigger('click');
			}
			openAgentMediaFrame(slot);
		});

		// Reset Agent Avatar to default
		$(document).on('click', '.cqfw-btn-agent-reset', function (e) {
			e.preventDefault();
			var slot = $(this).data('agent-slot') || 1;
			var activeStyle = $('#cqfw_active_style_input').val();
			var defaultUrl = '';

			if (slot === 1 && activeStyle === 'style_11') {
				defaultUrl = (window.cqfwStylesConfig && window.cqfwStylesConfig.urls) ? window.cqfwStylesConfig.urls.agentSales : '';
			} else {
				defaultUrl = (window.cqfwStylesConfig && window.cqfwStylesConfig.urls) ? window.cqfwStylesConfig.urls['agent' + slot] : '';
			}

			$('#cqfw_agent_' + slot + '_avatar').val('');
			$('#cqfw_agent_' + slot + '_preview').attr('src', defaultUrl);
			$('.cqfw-agent-slot-' + slot + '-img').attr('src', defaultUrl);
			$(this).hide();
		});

		
	});
})(jQuery);
