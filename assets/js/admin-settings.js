( function () {
	'use strict';

	function initQuickNav() {
		var navLinks = document.querySelectorAll( '.cqfw-jump-link' );
		var sections = document.querySelectorAll( '.cqfw-section-card' );

		if ( ! navLinks.length ) {
			return;
		}

		function scrollToTarget( targetId ) {
			if ( ! targetId ) return;
			var targetEl = document.getElementById( targetId );
			if ( targetEl ) {
				targetEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				navLinks.forEach( function ( l ) {
					var t = l.getAttribute( 'data-target' ) || ( l.getAttribute( 'href' ) || '' ).replace( '#', '' );
					if ( t === targetId ) {
						l.classList.add( 'nav-tab-active' );
					} else {
						l.classList.remove( 'nav-tab-active' );
					}
				} );
				if ( window.history && window.history.pushState ) {
					window.history.pushState( null, null, '#' + targetId );
				}
			}
		}

		// Initial hash jump on page load
		var hash = window.location.hash;
		if ( hash ) {
			var rawId = hash.replace( '#', '' );
			var targetEl = document.getElementById( rawId );
			if ( targetEl ) {
				setTimeout( function () {
					scrollToTarget( rawId );
				}, 150 );
			}
		}

		// Nav link click events
		navLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var href = link.getAttribute( 'href' );
				var targetId = link.getAttribute( 'data-target' ) || ( href ? href.replace( '#', '' ) : '' );
				if ( targetId ) {
					e.preventDefault();
					scrollToTarget( targetId );
				}
			} );
		} );

		// Switch buttons (e.g. "Scroll to Chat Styles" button)
		document.querySelectorAll( '.cqfw-switch-tab-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var targetId = btn.getAttribute( 'data-target' );
				scrollToTarget( targetId );
			} );
		} );

		// ScrollSpy to update active link during scroll
		if ( 'IntersectionObserver' in window && sections.length ) {
			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						var id = entry.target.getAttribute( 'id' );
						navLinks.forEach( function ( l ) {
							var t = l.getAttribute( 'data-target' ) || ( l.getAttribute( 'href' ) || '' ).replace( '#', '' );
							if ( t === id ) {
								l.classList.add( 'nav-tab-active' );
							} else {
								l.classList.remove( 'nav-tab-active' );
							}
						} );
					}
				} );
			}, { rootMargin: '-15% 0px -65% 0px' } );

			sections.forEach( function ( sec ) {
				observer.observe( sec );
			} );
		}
	}

	function initColorPickers() {
		if ( window.jQuery && jQuery.fn && jQuery.fn.wpColorPicker ) {
			jQuery( '.cqfw-color-field' ).wpColorPicker();
		}
	}

	function initImageFields() {
		var fields = document.querySelectorAll( '.cqfw-image-field' );

		if ( ! fields.length || ! window.wp || ! wp.media ) {
			return;
		}

		fields.forEach( function ( field ) {
			var uploadButton = field.querySelector( '.cqfw-upload-image' );
			var removeButton = field.querySelector( '.cqfw-remove-image' );
			var urlField = field.querySelector( '.cqfw-image-url' );
			var preview = field.querySelector( '.cqfw-image-preview' );
			var previewImg = preview ? preview.querySelector( 'img' ) : null;

			if ( ! uploadButton || ! removeButton || ! urlField ) {
				return;
			}

			uploadButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				var frame = wp.media( {
					title: 'Select Button Icon',
					button: {
						text: 'Use This Image'
					},
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					urlField.value = attachment.url || '';

					if ( preview && previewImg ) {
						previewImg.src = attachment.url || '';
						preview.style.display = attachment.url ? '' : 'none';
					}
				} );

				frame.open();
			} );

			removeButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				urlField.value = '';

				if ( preview && previewImg ) {
					previewImg.src = '';
					preview.style.display = 'none';
				}
			} );
		} );
	}

	function initShopButtonStyles() {
		var picker = document.getElementById( 'cqfw-shop-style-picker' );
		if ( ! picker ) {
			return;
		}

		var shopToggle = document.getElementById( 'enable_shop_button' );
		var styleInput = document.getElementById( 'cqfw_shop_button_style_input' );
		var customPanel = document.getElementById( 'cqfw-shop-custom-options' );
		var isPro = window.cqfwStylesConfig ? !!window.cqfwStylesConfig.isPro : false;
		var upgradeUrl = window.cqfwStylesConfig && window.cqfwStylesConfig.upgradeUrl
			? window.cqfwStylesConfig.upgradeUrl
			: 'https://wpchatquote.com/pro';

		function syncPickerVisibility() {
			if ( ! shopToggle ) {
				picker.style.display = '';
				return;
			}
			picker.style.display = shopToggle.checked ? '' : 'none';
		}

		function syncCustomPanel( styleId ) {
			if ( ! customPanel ) {
				return;
			}
			customPanel.style.display = ( styleId === 'custom' && isPro ) ? '' : 'none';
		}

		if ( shopToggle ) {
			shopToggle.addEventListener( 'change', syncPickerVisibility );
			syncPickerVisibility();
		}

		picker.querySelectorAll( '.cqfw-shop-style-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();

				var styleId = card.getAttribute( 'data-shop-style-id' );
				var isProStyle = card.getAttribute( 'data-is-pro' ) === '1';

				if ( isProStyle && ! isPro ) {
					if ( window.confirm( ( window.cqfwStylesConfig && window.cqfwStylesConfig.i18n && window.cqfwStylesConfig.i18n.proRequired )
						? window.cqfwStylesConfig.i18n.proRequired + '\n\n' + window.cqfwStylesConfig.i18n.proModalDesc
						: 'This custom shop button style is available in PRO.' ) ) {
						window.open( upgradeUrl, '_blank' );
					}
					return;
				}

				picker.querySelectorAll( '.cqfw-shop-style-card' ).forEach( function ( c ) {
					c.classList.remove( 'is-active' );
				} );
				card.classList.add( 'is-active' );

				if ( styleInput ) {
					styleInput.value = styleId;
				}
				syncCustomPanel( styleId );
			} );
		} );

		if ( styleInput ) {
			syncCustomPanel( styleInput.value );
		}
	}

	function digitsOnly( value ) {
		return String( value || '' ).replace( /\D+/g, '' );
	}

	function syncPhoneField( wrap ) {
		if ( ! wrap ) {
			return;
		}
		var dial = wrap.querySelector( '[data-cqfw-phone-dial]' );
		var local = wrap.querySelector( '[data-cqfw-phone-local]' );
		var full = wrap.querySelector( '[data-cqfw-phone-full]' );
		if ( ! dial || ! local || ! full ) {
			return;
		}
		var dialDigits = digitsOnly( dial.value );
		var national = digitsOnly( local.value ).replace( /^0+/, '' );
		local.value = national;
		full.value = national ? ( dialDigits + national ) : '';
	}

	function initPhoneFields() {
		document.querySelectorAll( '[data-cqfw-phone]' ).forEach( function ( wrap ) {
			var dial = wrap.querySelector( '[data-cqfw-phone-dial]' );
			var local = wrap.querySelector( '[data-cqfw-phone-local]' );
			if ( ! dial || ! local ) {
				return;
			}
			function onChange() {
				syncPhoneField( wrap );
			}
			dial.addEventListener( 'change', onChange );
			local.addEventListener( 'input', onChange );
			local.addEventListener( 'blur', onChange );
			syncPhoneField( wrap );
		} );

		document.querySelectorAll( '.cqfw-panel__form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				form.querySelectorAll( '[data-cqfw-phone]' ).forEach( syncPhoneField );
			} );
		} );
	}

	function initToasts() {
		var toasts = document.querySelectorAll( '[data-cqfw-toast]' );
		if ( ! toasts.length ) {
			return;
		}
		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		toasts.forEach( function ( toast, index ) {
			if ( toast.parentNode !== document.body ) {
				document.body.appendChild( toast );
			}
			toast.classList.add( 'is-floating' );
			toast.style.top = ( 48 + ( index * 72 ) ) + 'px';
			var hold = reduceMotion ? 4200 : 3800;
			setTimeout( function () {
				toast.classList.add( 'is-leaving' );
				setTimeout( function () {
					if ( toast.parentNode ) {
						toast.parentNode.removeChild( toast );
					}
				}, reduceMotion ? 0 : 280 );
			}, hold );
		} );
	}

	function initChecklistProgress() {
		var guide = document.querySelector( '.cqfw-easy-guide[data-cqfw-progress]' );
		if ( ! guide ) {
			return;
		}
		var fill = guide.querySelector( '.cqfw-easy-guide__bar-fill' );
		if ( ! fill ) {
			return;
		}
		// Re-trigger fill animation after paint (CSS var already set inline).
		fill.style.animation = 'none';
		// Force reflow then restore.
		void fill.offsetWidth;
		fill.style.animation = '';
	}

	function runAll() {
		try { initQuickNav(); } catch ( e ) { console.error( 'CQFW nav error:', e ); }
		try { initColorPickers(); } catch ( e ) { console.error( 'CQFW color error:', e ); }
		try { initImageFields(); } catch ( e ) { console.error( 'CQFW image error:', e ); }
		try { initProUI(); } catch ( e ) { console.error( 'CQFW pro UI error:', e ); }
		try { initShopButtonStyles(); } catch ( e ) { console.error( 'CQFW shop styles error:', e ); }
		try { initUpsellDismiss(); } catch ( e ) { console.error( 'CQFW upsell error:', e ); }
		try { initAdminNoticeRail(); } catch ( e ) { console.error( 'CQFW notices error:', e ); }
		try { initLivePreview(); } catch ( e ) { console.error( 'CQFW live preview error:', e ); }
		try { initStickySaveFeedback(); } catch ( e ) { console.error( 'CQFW save feedback error:', e ); }
		try { initPhoneFields(); } catch ( e ) { console.error( 'CQFW phone fields error:', e ); }
		try { initToasts(); } catch ( e ) { console.error( 'CQFW toast error:', e ); }
		try { initChecklistProgress(); } catch ( e ) { console.error( 'CQFW checklist error:', e ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', runAll );
	} else {
		runAll();
	}

	function initProUI() {

		// Document delegation for Add Row (+ Add Field, + Add Agent)
		document.addEventListener( 'click', function ( e ) {
			var addBtn = e.target.closest( '.cqfw-add-pro-row' );
			if ( addBtn ) {
				e.preventDefault();
				var builder = addBtn.closest( '.cqfw-pro-ui-builder' );
				if ( ! builder ) return;
				var key = builder.getAttribute( 'data-key' );
				var rowsContainer = builder.querySelector( '.cqfw-pro-rows' );
				if ( ! rowsContainer ) return;

				var newRow = document.createElement( 'div' );
				newRow.className = 'cqfw-pro-row';
				newRow.style.cssText = 'display:flex;gap:10px;align-items:center;margin-bottom:10px;background:#f8fafc;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;flex-wrap:wrap;';

				if ( key === 'cqfw_custom_form_fields' ) {
					newRow.innerHTML =
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Field ID</label><input type="text" data-col="id" placeholder="company_name" style="width:130px;height:34px;" /></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Label</label><input type="text" data-col="label" placeholder="Company Name" style="width:140px;height:34px;" /></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Type</label><select data-col="type" style="width:100px;height:34px;"><option value="text">text</option><option value="email">email</option><option value="tel">tel</option><option value="number">number</option><option value="url">url</option><option value="file">file</option></select></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Placeholder</label><input type="text" data-col="placeholder" placeholder="Your Company" style="width:140px;height:34px;" /></div>' +
						'<div style="padding-top:16px;"><label style="cursor:pointer;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;"><input type="checkbox" data-col="required" /> Required</label></div>' +
						'<div style="padding-top:16px;"><button type="button" class="button cqfw-remove-pro-row" style="color:#dc2626;border-color:#fca5a5;background:#fef2f2;">&times; Remove</button></div>';
				} else if ( key === 'cqfw_whatsapp_agents' ) {
					newRow.innerHTML =
						'<div class="cqfw-agent-avatar-col" style="display:flex;flex-direction:column;gap:3px;">' +
							'<label style="display:block;font-size:11px;font-weight:600;color:#64748b;">Avatar Photo</label>' +
							'<div style="display:flex;align-items:center;gap:6px;">' +
								'<div class="cqfw-agent-avatar-preview" style="width:42px;height:42px;border-radius:50%;overflow:hidden;background:#e2e8f0;border:1px solid #cbd5e1;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
									'<img src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none;" />' +
									'<span class="cqfw-avatar-placeholder" style="font-size:20px;line-height:1;color:#94a3b8;">👤</span>' +
								'</div>' +
								'<input type="hidden" data-col="avatar" value="" />' +
								'<button type="button" class="button button-small cqfw-upload-agent-avatar" style="font-size:11px;height:28px;line-height:26px;">Upload</button>' +
								'<button type="button" class="button button-small cqfw-remove-agent-avatar" title="Remove photo" style="font-size:12px;height:28px;line-height:24px;padding:0 8px;color:#ef4444;border-color:#fca5a5;background:#fef2f2;display:none;">&times;</button>' +
							'</div>' +
						'</div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Agent Name</label><input type="text" data-col="name" placeholder="e.g. Deo" style="width:130px;height:34px;" /></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">WhatsApp Number</label><input type="text" data-col="phone" placeholder="+8801XXXXXXXXX" style="width:150px;height:34px;" /></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Department / Subtitle</label><input type="text" data-col="department" placeholder="e.g. Tech Support" style="width:140px;height:34px;" /></div>' +
						'<div><label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">Working Hours (Online)</label><div style="display:flex;align-items:center;gap:4px;"><input type="time" data-col="start_time" value="" title="Start Time (leave blank for 24/7)" style="width:105px;height:34px;padding:2px 6px;" /><span style="color:#94a3b8;font-size:12px;">-</span><input type="time" data-col="end_time" value="" title="End Time (leave blank for 24/7)" style="width:105px;height:34px;padding:2px 6px;" /></div></div>' +
						'<div style="padding-top:16px;"><button type="button" class="button cqfw-remove-pro-row" style="color:#dc2626;border-color:#fca5a5;background:#fef2f2;">&times; Remove</button></div>';
				}

				rowsContainer.appendChild( newRow );
				var firstInput = newRow.querySelector( 'input[type="text"]' );
				if ( firstInput ) firstInput.focus();
				syncJson( builder );
				return;
			}

			// Agent Avatar Upload button delegation
			var uploadAvatarBtn = e.target.closest( '.cqfw-upload-agent-avatar' );
			if ( uploadAvatarBtn ) {
				e.preventDefault();
				var row = uploadAvatarBtn.closest( '.cqfw-pro-row' );
				var builder = uploadAvatarBtn.closest( '.cqfw-pro-ui-builder' );
				if ( ! row || ! window.wp || ! wp.media ) return;

				var frame = wp.media( {
					title: 'Select Agent Avatar Photo',
					button: { text: 'Use Avatar Image' },
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var url = attachment.url || '';
					var avatarInput = row.querySelector( 'input[data-col="avatar"]' );
					var avatarImg = row.querySelector( '.cqfw-agent-avatar-preview img' );
					var placeholder = row.querySelector( '.cqfw-avatar-placeholder' );
					var removeAvatarBtn = row.querySelector( '.cqfw-remove-agent-avatar' );

					if ( avatarInput ) avatarInput.value = url;
					if ( avatarImg ) {
						avatarImg.src = url;
						avatarImg.style.display = url ? '' : 'none';
					}
					if ( placeholder ) {
						placeholder.style.display = url ? 'none' : '';
					}
					if ( removeAvatarBtn ) {
						removeAvatarBtn.style.display = url ? '' : 'none';
					}

					if ( builder ) syncJson( builder );
				} );

				frame.open();
				return;
			}

			// Agent Avatar Remove button delegation
			var removeAvatarBtn = e.target.closest( '.cqfw-remove-agent-avatar' );
			if ( removeAvatarBtn ) {
				e.preventDefault();
				var row = removeAvatarBtn.closest( '.cqfw-pro-row' );
				var builder = removeAvatarBtn.closest( '.cqfw-pro-ui-builder' );
				if ( ! row ) return;

				var avatarInput = row.querySelector( 'input[data-col="avatar"]' );
				var avatarImg = row.querySelector( '.cqfw-agent-avatar-preview img' );
				var placeholder = row.querySelector( '.cqfw-avatar-placeholder' );

				if ( avatarInput ) avatarInput.value = '';
				if ( avatarImg ) {
					avatarImg.src = '';
					avatarImg.style.display = 'none';
				}
				if ( placeholder ) {
					placeholder.style.display = '';
				}
				removeAvatarBtn.style.display = 'none';

				if ( builder ) syncJson( builder );
				return;
			}

			// Document delegation for Remove Row
			var removeBtn = e.target.closest( '.cqfw-remove-pro-row' );
			if ( removeBtn ) {
				e.preventDefault();
				var row = removeBtn.closest( '.cqfw-pro-row' );
				var b = removeBtn.closest( '.cqfw-pro-ui-builder' );
				if ( row ) row.remove();
				if ( b ) syncJson( b );
				return;
			}
		} );

		// Business Hours: auto-mark Open when user edits or clicks time input
		document.addEventListener( 'input', function ( e ) {
			handleInputEvent( e );
		} );
		document.addEventListener( 'change', function ( e ) {
			handleInputEvent( e );
		} );

		function handleInputEvent( e ) {
			var target = e.target;
			var builder = target.closest( '.cqfw-pro-ui-builder' );
			if ( ! builder ) return;

			if ( builder.classList.contains( 'cqfw-biz-hours' ) ) {
				// If user changed Open/Closed checkbox
				if ( target.dataset.bizCol === 'active' || target.getAttribute( 'data-biz-col' ) === 'active' ) {
					var row = target.closest( '.cqfw-biz-row' );
					if ( row ) {
						row.style.background = target.checked ? '#f0fdf4' : '#fafafa';
						var statusSpan = row.querySelector( '.cqfw-biz-status-label span' );
						if ( statusSpan ) {
							statusSpan.textContent = target.checked ? 'Open' : 'Closed';
							statusSpan.style.color = target.checked ? '#16a34a' : '#64748b';
						}
					}
				}

				// If user modified time, auto-check the active checkbox if it was unchecked
				if ( target.type === 'time' ) {
					var r = target.closest( '.cqfw-biz-row' );
					if ( r ) {
						var activeChk = r.querySelector( '[data-biz-col="active"]' );
						if ( activeChk && ! activeChk.checked ) {
							activeChk.checked = true;
							r.style.background = '#f0fdf4';
							var s = r.querySelector( '.cqfw-biz-status-label span' );
							if ( s ) {
								s.textContent = 'Open';
								s.style.color = '#16a34a';
							}
						}
					}
				}

				syncBizHours( builder );
			} else {
				syncJson( builder );
			}
		}

		// Initial sync across all builders
		document.querySelectorAll( '.cqfw-pro-ui-builder' ).forEach( function ( builder ) {
			if ( builder.classList.contains( 'cqfw-biz-hours' ) ) {
				syncBizHours( builder );
			} else {
				syncJson( builder );
			}
		} );

		// Sync before form submit
		var settingsForms = document.querySelectorAll( 'form' );
		settingsForms.forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				document.querySelectorAll( '.cqfw-pro-ui-builder' ).forEach( function ( builder ) {
					if ( builder.classList.contains( 'cqfw-biz-hours' ) ) {
						syncBizHours( builder );
					} else {
						syncJson( builder );
					}
				} );
			} );
		} );
	}

	function syncJson( builder ) {
		var key = builder.getAttribute( 'data-key' );
		var textarea = document.getElementById( key );
		if ( ! textarea ) return;

		var rows = builder.querySelectorAll( '.cqfw-pro-row' );
		var data = [];
		rows.forEach( function ( row ) {
			var obj = {};
			var hasAnyValue = false;
			row.querySelectorAll( '[data-col]' ).forEach( function ( input ) {
				var col = input.getAttribute( 'data-col' );
				if ( input.type === 'checkbox' ) {
					obj[ col ] = input.checked;
				} else {
					var val = ( input.value || '' ).trim();
					obj[ col ] = val;
					if ( val !== '' ) hasAnyValue = true;
				}
			} );
			if ( hasAnyValue ) {
				data.push( obj );
			}
		} );
		textarea.value = JSON.stringify( data );
	}

	function syncBizHours( builder ) {
		var key = builder.getAttribute( 'data-key' );
		var textarea = document.getElementById( key );
		if ( ! textarea ) return;

		var enabledChk = builder.querySelector( '#cqfw_biz_enabled' );
		var data = { enabled: enabledChk ? enabledChk.checked : false };

		builder.querySelectorAll( '.cqfw-biz-row' ).forEach( function ( row ) {
			var day = row.getAttribute( 'data-day' );
			var activeChk  = row.querySelector( '[data-biz-col="active"]' );
			var startInput = row.querySelector( '[data-biz-col="start"]' );
			var endInput   = row.querySelector( '[data-biz-col="end"]' );
			data[ day ] = {
				active : activeChk  ? activeChk.checked  : false,
				start  : startInput ? startInput.value   : '09:00',
				end    : endInput   ? endInput.value     : '18:00',
			};
		} );
		textarea.value = JSON.stringify( data );
	}

	function initAdminNoticeRail() {
		var rail = document.getElementById( 'cqfw-admin-notices' );
		var app = document.querySelector( '.cqfw-app.wrap' );
		if ( ! rail || ! app ) {
			return;
		}

		function shouldKeepInline( el ) {
			if ( ! el || ! el.classList ) {
				return true;
			}
			// Our own success toasts / intentionally inline notices stay put.
			if ( el.classList.contains( 'cqfw-toast' ) || el.classList.contains( 'inline' ) || el.classList.contains( 'cqfw-go-pro-banner' ) ) {
				return true;
			}
			if ( el.closest( '.cqfw-panel' ) || el.closest( '.cqfw-inbox-wrap' ) || el.closest( '.cqfw-analytics-wrap' ) ) {
				return true;
			}
			return false;
		}

		function relocate() {
			var nodes = app.querySelectorAll( '.notice, .update-nag, .fs-notice' );
			nodes.forEach( function ( n ) {
				if ( rail.contains( n ) || shouldKeepInline( n ) ) {
					return;
				}
				// Skip if already outside shell but not in rail (sibling after screen title).
				if ( n.parentNode === app && n.previousElementSibling && n.previousElementSibling.classList.contains( 'cqfw-app__screen-title' ) ) {
					rail.appendChild( n );
					return;
				}
				if ( n.closest( '.cqfw-app__shell' ) || n.closest( '.cqfw-app__top' ) || n.parentNode === app ) {
					rail.appendChild( n );
				}
			} );
		}

		relocate();
		// WP common.js moves notices after first h1 on ready — run again shortly after.
		setTimeout( relocate, 50 );
		setTimeout( relocate, 250 );

		if ( typeof MutationObserver === 'function' ) {
			var obs = new MutationObserver( function () {
				relocate();
			} );
			obs.observe( app, { childList: true, subtree: true } );
		}
	}

	function initUpsellDismiss() {
		document.querySelectorAll( '.cqfw-go-pro-banner' ).forEach( function ( banner ) {
			var btn = banner.querySelector( '.notice-dismiss' );
			if ( ! btn ) {
				return;
			}
			btn.addEventListener( 'click', function () {
				var context = banner.getAttribute( 'data-cqfw-dismiss' ) || 'settings';
				var cfg = window.cqfwStylesConfig || {};
				if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
					return;
				}
				var body = new URLSearchParams();
				body.append( 'action', 'cqfw_dismiss_upsell' );
				body.append( 'nonce', cfg.nonce );
				body.append( 'context', context );
				fetch( cfg.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				} ).catch( function () { /* ignore */ } );
			} );
		} );
	}

	/**
	 * Chat Bubble live preview — CQFW native (no third-party markup/JS).
	 */
	function initLivePreview() {
		var root = document.getElementById( 'cqfw-live-preview' );
		if ( ! root ) {
			return;
		}

		var stage = root.querySelector( '[data-cqfw-stage]' );
		var frame = root.querySelector( '[data-cqfw-frame]' );
		var panel = root.querySelector( '[data-cqfw-lp-panel]' );
		var fab = root.querySelector( '[data-cqfw-lp-fab]' );
		var titleEl = root.querySelector( '[data-cqfw-lp-title]' );
		var labelEl = root.querySelector( '[data-cqfw-lp-label]' );
		var offEl = root.querySelector( '[data-cqfw-lp-off]' );
		var deviceBtns = root.querySelectorAll( '[data-cqfw-device]' );

		function val( id, fallback ) {
			var el = document.getElementById( id );
			if ( ! el ) {
				return fallback;
			}
			if ( el.type === 'checkbox' ) {
				return el.checked;
			}
			return ( el.value || '' ).trim() || fallback;
		}

		function sync() {
			if ( ! frame || ! fab ) {
				return;
			}
			var title = val( 'chat_popup_title', 'Chat with us' );
			var label = val( 'floating_button_text', 'Chat with us' );
			var styleId = val( 'cqfw_active_style_input', 'style_1' ) || 'style_1';
			var styleSlug = 'cqfw-lp--' + String( styleId ).replace( /_/g, '-' );
			var labelStyles = { style_1: 1, style_4: 1, style_8: 1 };
			var iconOnlyStyles = { style_2: 1, style_3: 1, style_3_extend: 1, style_7: 1, style_7_extend: 1 };
			var showLabel = !! labelStyles[ styleId ];
			var iconOnly = !! iconOnlyStyles[ styleId ];
			var customBg = val( 'cqfw_background_color', '' );
			var customFg = val( 'cqfw_text_color', '' );
			var customIcon = val( 'cqfw_icon_color', '' );
			var bg = customBg || ( styleId === 'style_1' ? '#ffffff' : '#25d366' );
			var fg = customFg || ( styleId === 'style_1' ? '#1e293b' : '#ffffff' );
			var iconC = customIcon || ( styleId === 'style_1' ? '#25d366' : '#ffffff' );
			var h = val( 'cqfw_pos_h_side', 'right' );
			var v = val( 'cqfw_pos_v_side', 'bottom' );
			var floatingOn = document.getElementById( 'enable_floating_button' );
			var chatOn = document.getElementById( 'enable_chat_widget' );
			var enabled = ( ! floatingOn || floatingOn.checked ) && ( ! chatOn || chatOn.checked );
			var badge = root.querySelector( '[data-cqfw-lp-badge]' );

			if ( titleEl ) {
				titleEl.textContent = title;
			}
			if ( labelEl ) {
				labelEl.textContent = label;
				if ( showLabel ) {
					labelEl.removeAttribute( 'hidden' );
				} else {
					labelEl.setAttribute( 'hidden', 'hidden' );
				}
			}
			if ( badge ) {
				if ( styleId === 'style_3_extend' ) {
					badge.removeAttribute( 'hidden' );
				} else {
					badge.setAttribute( 'hidden', 'hidden' );
				}
			}

			fab.className = 'cqfw-lp-fab ' + styleSlug + ( iconOnly ? ' is-icon-only' : '' );
			frame.style.setProperty( '--cqfw-lp-bg', bg );
			frame.style.setProperty( '--cqfw-lp-fg', fg );
			frame.style.setProperty( '--cqfw-lp-icon', iconC );
			frame.setAttribute( 'data-h', h === 'left' ? 'left' : 'right' );
			frame.setAttribute( 'data-v', v === 'top' ? 'top' : 'bottom' );
			frame.setAttribute( 'data-style', styleId );
			frame.classList.toggle( 'is-off', ! enabled );
			if ( offEl ) {
				if ( enabled ) {
					offEl.setAttribute( 'hidden', 'hidden' );
				} else {
					offEl.removeAttribute( 'hidden' );
					if ( panel ) {
						panel.classList.remove( 'is-open' );
						panel.setAttribute( 'aria-hidden', 'true' );
					}
					fab.setAttribute( 'aria-expanded', 'false' );
				}
			}
		}

		deviceBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var device = btn.getAttribute( 'data-cqfw-device' ) || 'desktop';
				deviceBtns.forEach( function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
				} );
				if ( stage ) {
					stage.classList.toggle( 'is-mobile', device === 'mobile' );
					stage.classList.toggle( 'is-desktop', device !== 'mobile' );
				}
			} );
		} );

		if ( fab && panel ) {
			fab.addEventListener( 'click', function () {
				if ( frame && frame.classList.contains( 'is-off' ) ) {
					return;
				}
				var open = ! panel.classList.contains( 'is-open' );
				panel.classList.toggle( 'is-open', open );
				panel.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
				fab.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

		var watchIds = [
			'chat_popup_title',
			'floating_button_text',
			'enable_floating_button',
			'enable_chat_widget',
			'cqfw_background_color',
			'cqfw_text_color',
			'cqfw_icon_color',
			'cqfw_pos_h_side',
			'cqfw_pos_v_side',
			'cqfw_active_style_input'
		];
		watchIds.forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( ! el ) {
				return;
			}
			el.addEventListener( 'input', sync );
			el.addEventListener( 'change', sync );
		} );

		// wpColorPicker change events (jQuery).
		if ( window.jQuery ) {
			jQuery( document ).on( 'irischange change', '#cqfw_background_color, #cqfw_text_color, #cqfw_icon_color', sync );
			jQuery( document ).on( 'cqfw:style-changed', sync );
		}

		// Style card selection may also change hidden inputs / colors later — re-sync cheaply.
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.cqfw-style-card, .cqfw-popup-style-card' ) ) {
				setTimeout( sync, 30 );
			}
		} );

		sync();
		// Open once so visitors see the panel animation immediately.
		setTimeout( function () {
			if ( fab && panel && frame && ! frame.classList.contains( 'is-off' ) ) {
				panel.classList.add( 'is-open' );
				panel.setAttribute( 'aria-hidden', 'false' );
				fab.setAttribute( 'aria-expanded', 'true' );
			}
		}, 350 );
	}

	function initStickySaveFeedback() {
		document.querySelectorAll( '.cqfw-panel__form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var btn = form.querySelector( '.cqfw-btn-save' );
				if ( ! btn ) {
					return;
				}
				btn.classList.add( 'is-saving' );
				var original = btn.textContent;
				btn.setAttribute( 'data-cqfw-original-label', original );
				btn.textContent = ( window.cqfwStylesConfig && cqfwStylesConfig.i18n && cqfwStylesConfig.i18n.saving ) ? cqfwStylesConfig.i18n.saving : 'Saving…';
			} );
		} );
	}

}() );
