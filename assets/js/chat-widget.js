/* global cqfwChat */
( function () {
	'use strict';

	var config = window.cqfwChat || {};
	var settings = config.settings || {};
	var trackAction = config.ajaxActionTrack || 'cqfw_log_click';
	var chatAction = config.ajaxActionChat || 'cqfw_send_chat_message';
	var historyAction = config.ajaxActionHistory || 'cqfw_get_chat_history';
	var pollAction = config.ajaxActionPoll || 'cqfw_poll_messages';

	var renderedMessageIds = [];
	var lastMessageId = 0;
	var pollInterval = null;

	/* ---------- Helpers ---------- */

	function postFormData( action, formData ) {
		formData.append( 'action', action );
		formData.append( 'nonce', config.nonce || '' );
		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} ).then( function ( r ) { return r.json(); } );
	}

	function postAjax( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce || '' );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function formatTime( dateStr ) {
		if ( ! dateStr ) {
			return '';
		}
		var d = new Date( dateStr.replace( ' ', 'T' ) );
		if ( isNaN( d.getTime() ) ) {
			var now = new Date();
			return now.getHours() + ':' + String( now.getMinutes() ).padStart( 2, '0' );
		}
		return d.getHours() + ':' + String( d.getMinutes() ).padStart( 2, '0' );
	}

	function nowTime() {
		var now = new Date();
		return now.getHours() + ':' + String( now.getMinutes() ).padStart( 2, '0' );
	}

	function getWidgetRoot() { return document.getElementById( 'cqfw-widget-root' ); }
	function getPanel( root ) { return root ? root.querySelector( '.cqfw-chat-panel' ) : null; }
	function getMessagesArea( panel ) { return panel ? panel.querySelector( '.cqfw-chat-panel__messages' ) : null; }
	function getForm( panel ) { return panel ? panel.querySelector( '.cqfw-chat-form' ) : null; }
	function getOptions( panel ) { return panel ? panel.querySelector( '.cqfw-chat-panel__options' ) : null; }
	function getTypingIndicator() { return document.querySelector( '.cqfw-typing-indicator' ); }

	/* ---------- Lightbox Modal & Zoom Preview ---------- */
	var currentZoom = 1;

	function initLightbox() {
		var existing = document.getElementById( "cqfw-image-lightbox" );
		if ( existing ) {
			return existing;
		}
		var lb = document.createElement( "div" );
		lb.id = "cqfw-image-lightbox";
		lb.className = "cqfw-lightbox-modal";
		lb.hidden = true;
		lb.innerHTML = '<div class="cqfw-lightbox-backdrop"></div>' +
			'<div class="cqfw-lightbox-dialog">' +
				'<div class="cqfw-lightbox-header">' +
					'<span class="cqfw-lightbox-title">Image Preview</span>' +
					'<div class="cqfw-lightbox-actions">' +
						'<button type="button" class="cqfw-lb-btn cqfw-lb-zoom-in" title="Zoom In (+)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>' +
						'<button type="button" class="cqfw-lb-btn cqfw-lb-zoom-out" title="Zoom Out (-)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>' +
						'<button type="button" class="cqfw-lb-btn cqfw-lb-reset" title="Reset Zoom">100%</button>' +
						'<a href="#" target="_blank" download class="cqfw-lb-btn cqfw-lb-download" title="Download Image"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></a>' +
						'<button type="button" class="cqfw-lb-btn cqfw-lb-close" title="Close (Esc)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>' +
					'</div>' +
				'</div>' +
				'<div class="cqfw-lightbox-body">' +
					'<img src="" class="cqfw-lightbox-image" alt="Image preview" />' +
				'</div>' +
			'</div>';
		document.body.appendChild( lb );

		var img = lb.querySelector( ".cqfw-lightbox-image" );
		var resetBtn = lb.querySelector( ".cqfw-lb-reset" );

		function updateZoom() {
			img.style.transform = "scale(" + currentZoom + ")";
			if ( resetBtn ) { resetBtn.textContent = Math.round( currentZoom * 100 ) + "%"; }
		}

		lb.querySelector( ".cqfw-lb-zoom-in" ).addEventListener( "click", function ( e ) {
			e.stopPropagation();
			if ( currentZoom < 3 ) { currentZoom += 0.25; updateZoom(); }
		} );

		lb.querySelector( ".cqfw-lb-zoom-out" ).addEventListener( "click", function ( e ) {
			e.stopPropagation();
			if ( currentZoom > 0.5 ) { currentZoom -= 0.25; updateZoom(); }
		} );

		resetBtn.addEventListener( "click", function ( e ) {
			e.stopPropagation();
			currentZoom = 1;
			updateZoom();
		} );

		function closeLb() {
			lb.hidden = true;
			currentZoom = 1;
			updateZoom();
			img.src = "";
		}

		lb.querySelector( ".cqfw-lb-close" ).addEventListener( "click", closeLb );
		lb.querySelector( ".cqfw-lightbox-backdrop" ).addEventListener( "click", closeLb );

		document.addEventListener( "keydown", function ( e ) {
			if ( e.key === "Escape" && ! lb.hidden ) {
				closeLb();
			}
		} );

		return lb;
	}

	function openLightbox( imgSrc, title ) {
		var lb = initLightbox();
		var img = lb.querySelector( ".cqfw-lightbox-image" );
		var titleEl = lb.querySelector( ".cqfw-lightbox-title" );
		var dl = lb.querySelector( ".cqfw-lb-download" );
		currentZoom = 1;
		img.style.transform = "scale(1)";
		lb.querySelector( ".cqfw-lb-reset" ).textContent = "100%";
		img.src = imgSrc;
		if ( titleEl ) { titleEl.textContent = title || "Image Preview"; }
		if ( dl ) { dl.href = imgSrc; }
		lb.hidden = false;
	}

	// Lightbox click delegation
	document.addEventListener( "click", function ( e ) {
		var trigger = e.target.closest( ".cqfw-lightbox-trigger" );
		if ( trigger ) {
			e.preventDefault();
			var imgSrc = trigger.getAttribute( "data-img" ) || trigger.getAttribute( "href" );
			var title  = trigger.getAttribute( "data-title" ) || "Image Preview";
			if ( imgSrc ) {
				openLightbox( imgSrc, title );
			}
		}
	} );

	/* ---------- Bubble Rendering ---------- */

	function escapeHtml( string ) {
		var div = document.createElement( 'div' );
		div.textContent = string || '';
		return div.innerHTML;
	}

		function renderBubbleContent( bubble, text, timeSpan ) {
		var lines = ( text || "" ).split( "\n" );
		var html = "";
		var hasAttachment = false;

		lines.forEach( function ( line ) {
			// Matches attachments: [optional emoji] filename: url OR direct URL
			var attMatch = line.match( /^(?:[\uD83D\uDCCE\uD83D\uDCF7\uD83D\uDCCE\u2709\uD83D\uDCCE\uD83D\uDCCF\uD83D\uDCF8📎📷📄]|📎|Ã°Å¸“Å½|\?\?|\[Attachment\])?\s*([^:\n]+):\s*(https?:\/\/[^\s]+)/i );
			if ( ! attMatch ) {
				var urlMatch = line.trim().match( /^(https?:\/\/[^\s]+)$/i );
				if ( urlMatch ) {
					var rawUrl = urlMatch[1];
					var rawName = rawUrl.split( "/" ).pop().split( "?" )[0] || "Attachment";
					attMatch = [ line, rawName, rawUrl ];
				}
			}

			if ( attMatch ) {
				hasAttachment = true;
				var fileName = attMatch[1].trim();
				var fileUrl  = attMatch[2].trim();
				if ( /\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i.test( fileUrl ) ) {
					html += '<div class="cqfw-bubble-attachment is-image">' +
						'<a href="' + encodeURI( fileUrl ) + '" class="cqfw-lightbox-trigger" data-img="' + encodeURI( fileUrl ) + '" data-title="' + escapeHtml( fileName ) + '" title="Click to zoom">' +
							'<img src="' + encodeURI( fileUrl ) + '" alt="' + escapeHtml( fileName ) + '" class="cqfw-zoomable-img" />' +
						'</a>' +
					'</div>';
				} else {
					html += '<div class="cqfw-bubble-attachment is-file">' +
						'<a href="' + encodeURI( fileUrl ) + '" target="_blank" rel="noopener" download style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(0,0,0,0.06);border-radius:6px;text-decoration:none;color:inherit;font-size:13px;margin:6px 0;font-weight:500;">' +
							'📎 <strong>' + escapeHtml( fileName ) + '</strong>' +
						'</a>' +
					'</div>';
				}
			} else if ( line.trim() ) {
				html += ( html ? "<br>" : "" ) + escapeHtml( line );
			}
		} );

		if ( ! hasAttachment ) {
			bubble.textContent = text || "";
			if ( timeSpan ) { bubble.appendChild( timeSpan ); }
		} else {
			bubble.innerHTML = html;
			if ( timeSpan ) { bubble.appendChild( timeSpan ); }
		}
	}

	function appendBubble( messagesArea, text, className, id, time ) {
		if ( id ) {
			var msgId = parseInt( id, 10 );
			if ( msgId > lastMessageId ) { lastMessageId = msgId; }
			if ( renderedMessageIds.indexOf( msgId ) !== -1 ) { return; }
			renderedMessageIds.push( msgId );
		}

		var bubble = document.createElement( 'div' );
		bubble.className = 'cqfw-bubble ' + className;

		// Add timestamp
		var timeStr = time ? formatTime( time ) : nowTime();
		if ( className !== 'is-context' && className !== 'is-error' ) {
			var timeSpan = document.createElement( 'span' );
			timeSpan.className = 'cqfw-bubble-time';
			timeSpan.textContent = timeStr;
			renderBubbleContent( bubble, text, timeSpan );
		} else {
			bubble.textContent = text;
		}

		messagesArea.appendChild( bubble );
		messagesArea.scrollTop = messagesArea.scrollHeight;
	}

	/* ---------- Product Context Card ---------- */

	function showProductCard( messagesArea ) {
		var product = config.product || {};
		if ( ! messagesArea || ! product.product_name ) { return; }

		// Don't show if already rendered
		if ( messagesArea.querySelector( '.cqfw-product-card' ) ) { return; }

		var card = document.createElement( 'div' );
		card.className = 'cqfw-product-card';

		var imgHtml = '';
		if ( product.product_image ) {
			imgHtml = '<img class="cqfw-product-card__img" src="' + product.product_image + '" alt="" />';
		} else {
			imgHtml = '<div class="cqfw-product-card__img" style="display:flex;align-items:center;justify-content:center;color:#90a4ae;font-size:22px;">📦</div>';
		}

		var priceHtml = product.product_price ? '<div class="cqfw-product-card__price">' + product.product_price + '</div>' : '';

		card.innerHTML = imgHtml +
			'<div class="cqfw-product-card__info">' +
			'<div class="cqfw-product-card__name">' + product.product_name + '</div>' +
			priceHtml +
			'</div>';

		messagesArea.appendChild( card );
		messagesArea.scrollTop = messagesArea.scrollHeight;
	}

	/* ---------- Typing Indicator ---------- */

	function showTyping() {
		var el = getTypingIndicator();
		if ( el ) {
			el.classList.add( 'is-visible' );
			el.setAttribute( 'aria-hidden', 'false' );
		}
	}

	function hideTyping() {
		var el = getTypingIndicator();
		if ( el ) {
			el.classList.remove( 'is-visible' );
			el.setAttribute( 'aria-hidden', 'true' );
		}
	}

	/* ---------- Panel Open/Close ---------- */

	function openPanel( root ) {
		var panel = getPanel( root );
		if ( ! panel ) { return; }
		root.classList.add( 'is-open' );
		panel.setAttribute( 'aria-hidden', 'false' );
		var toggleBtn = root.querySelector( '.cqfw-floating-button' );
		if ( toggleBtn ) { toggleBtn.setAttribute( 'aria-expanded', 'true' ); }
		// Match desktop overlay feel: prevent background scroll while open (esp. mobile).
		if ( document.body && ! document.body.classList.contains( 'cqfw-chat-open' ) ) {
			document.body.classList.add( 'cqfw-chat-open' );
			document.body.style.overflow = 'hidden';
		}
	}

	function closePanel( root ) {
		var panel = getPanel( root );
		if ( ! panel ) { return; }
		root.classList.remove( 'is-open' );
		panel.setAttribute( 'aria-hidden', 'true' );
		var toggleBtn = root.querySelector( '.cqfw-floating-button' );
		if ( toggleBtn ) { toggleBtn.setAttribute( 'aria-expanded', 'false' ); }
		if ( document.body && document.body.classList.contains( 'cqfw-chat-open' ) ) {
			document.body.classList.remove( 'cqfw-chat-open' );
			document.body.style.overflow = '';
		}
	}

	/* ---------- Auto-resize Textarea ---------- */

	function autoResize( textarea ) {
		if ( ! textarea ) { return; }
		textarea.style.height = 'auto';
		textarea.style.height = Math.min( textarea.scrollHeight, 100 ) + 'px';
	}

	/* ---------- Hide Name/Phone Fields ---------- */

	function hideNamePhoneFields( form ) {
		if ( ! form ) { return; }
		var nameField = form.querySelector( 'input[name="name"]' );
		var phoneField = form.querySelector( 'input[name="phone"]' );
		var inputsContainer = form.querySelector( '.cqfw-form-inputs' );

		if ( inputsContainer ) { inputsContainer.style.display = 'none'; }
		if ( nameField ) { nameField.required = false; }
		if ( phoneField ) { phoneField.required = false; }

		// Add reset link
		var existingReset = form.querySelector( '.cqfw-chat-reset' );
		if ( ! existingReset ) {
			var resetLink = document.createElement( 'button' );
			resetLink.type = 'button';
			resetLink.className = 'cqfw-chat-reset';
			resetLink.textContent = '\u21bb Start new chat';
			resetLink.addEventListener( 'click', function () {
				localStorage.removeItem( 'cqfw_session_id' );
				localStorage.removeItem( 'cqfw_visitor_name' );
				localStorage.removeItem( 'cqfw_visitor_phone' );
				renderedMessageIds = [];
				lastMessageId = 0;
				if ( pollInterval ) {
					clearInterval( pollInterval );
					pollInterval = null;
				}
				var messagesArea = getMessagesArea( getPanel( getWidgetRoot() ) );
				if ( messagesArea ) { messagesArea.innerHTML = ''; }
				if ( inputsContainer ) { inputsContainer.style.display = ''; }
				if ( nameField ) { nameField.value = ''; }
				if ( phoneField ) { phoneField.value = ''; }
				resetLink.remove();

				// Re-show welcome
				if ( messagesArea ) {
					var welcomeMsg = config.welcomeMessage || 'Hi there! \ud83d\udc4b How can we help you today?';
					appendBubble( messagesArea, welcomeMsg, 'is-bot' );
					showProductCard( messagesArea );
				}
			} );
			form.insertBefore( resetLink, form.firstChild );
		}
	}

	/* ---------- Init Widget ---------- */

	function initWidget() {
		var root = getWidgetRoot();
		if ( ! root ) { return; }

		setupVisibilityAndTriggers( root );

		var panel = getPanel( root );
		var messagesArea = getMessagesArea( panel );
		var form = getForm( panel );
		var options = getOptions( panel );
		var openButton = root.querySelector( '.cqfw-floating-button' );
		var closeButton = root.querySelector( '.cqfw-chat-close' );
		var continueButton = root.querySelector( '.cqfw-option--continue' );
		var whatsappButton = root.querySelector( '.cqfw-option--whatsapp' );
		var messageField = form ? form.querySelector( 'textarea[name="message"]' ) : null;
		var whatsappUrl = '';
		var redirectTimer = null;
		var activeAgentPhone = '';

		setupExtras( root, panel );

		var agentButtons = root.querySelectorAll( '.cqfw-agent-item' );
		if ( agentButtons.length ) {
			activeAgentPhone = agentButtons[0].getAttribute( 'data-phone' ) || '';
			agentButtons.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					agentButtons.forEach( function ( b ) {
						b.classList.remove( 'is-active' );
						b.style.borderColor = '#cbd5e1';
						b.style.background = '#fff';
						b.style.color = '#334155';
					} );
					btn.classList.add( 'is-active' );
					btn.style.borderColor = '#128c7e';
					btn.style.background = '#f0fdf4';
					btn.style.color = '#128c7e';
					activeAgentPhone = btn.getAttribute( 'data-phone' ) || '';
				} );
			} );
		}

		var sessionId = localStorage.getItem( 'cqfw_session_id' ) || '';
		var visitorName = localStorage.getItem( 'cqfw_visitor_name' ) || '';
		var visitorPhone = localStorage.getItem( 'cqfw_visitor_phone' ) || '';

		// Auto-resize textarea
		if ( messageField ) {
			messageField.addEventListener( 'input', function () { autoResize( this ); } );

			// Enter to send, Shift+Enter for newline
			messageField.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' && ! e.shiftKey ) {
					e.preventDefault();
					if ( form ) {
						form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
					}
				}
			} );
		}

		var welcomeMsg = config.welcomeMessage || 'Hi there! \ud83d\udc4b How can we help you today?';

		function showWelcome() {
			if ( ! messagesArea || messagesArea.querySelector( '.cqfw-bubble' ) ) { return; }
			appendBubble( messagesArea, welcomeMsg, 'is-bot' );
		}

		function showProductContext() {
			if ( ! messagesArea ) { return; }
			showProductCard( messagesArea );
		}

		function resetConversation() {
			if ( form ) { form.hidden = false; }
			if ( options ) { options.hidden = true; }
			if ( redirectTimer ) {
				clearTimeout( redirectTimer );
				redirectTimer = null;
			}
		}

		function pollNow() {
			var currentSessionId = localStorage.getItem( 'cqfw_session_id' );
			if ( ! currentSessionId ) { return; }
			postAjax( pollAction, {
				session_id: currentSessionId,
				last_id: lastMessageId
			} ).then( function ( response ) {
				if ( response && response.success && response.data && response.data.messages && response.data.messages.length ) {
					response.data.messages.forEach( function ( msg ) {
						var type = msg.sender === 'admin' ? 'is-bot' : 'is-user';
						var time = msg.created_at_gmt ? (msg.created_at_gmt.replace(' ', 'T') + 'Z') : msg.created_at;
						appendBubble( messagesArea, msg.message, type, msg.id, time );
					} );
				}
			} );
		}

		function startPolling() {
			if ( pollInterval ) { clearInterval( pollInterval ); }
			pollNow();
			pollInterval = setInterval( pollNow, 1500 );
		}

		// Load session history on startup if exists
		if ( sessionId ) {
			if ( form ) {
				var nameField = form.querySelector( 'input[name="name"]' );
				var phoneField = form.querySelector( 'input[name="phone"]' );
				if ( nameField ) { nameField.value = visitorName; }
				if ( phoneField ) { phoneField.value = visitorPhone; }
				hideNamePhoneFields( form );
			}
			postAjax( historyAction, { session_id: sessionId } )
				.then( function ( response ) {
					if ( response && response.success && response.data && response.data.messages ) {
						messagesArea.innerHTML = '';
						renderedMessageIds = [];
						lastMessageId = 0;
						showProductContext();
						response.data.messages.forEach( function ( msg ) {
							var type = msg.sender === 'admin' ? 'is-bot' : 'is-user';
							var time = msg.created_at_gmt ? (msg.created_at_gmt.replace(' ', 'T') + 'Z') : msg.created_at;
							appendBubble( messagesArea, msg.message, type, msg.id, time );
						} );
						if ( response.data.messages.length === 0 ) {
							showWelcome();
						}
						startPolling();
					} else {
						showWelcome();
						showProductContext();
					}
				} )
				.catch( function () {
					showWelcome();
					showProductContext();
				} );
		}

		var multiAgentView = root.querySelector( '.cqfw-multi-agent-view' );
		var webchatView = root.querySelector( '.cqfw-webchat-view' );
		// Switch between multi-agent cards view and webchat view
		root.querySelectorAll( '.cqfw-switch-to-webchat' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				if ( e ) e.preventDefault();
				if ( multiAgentView && webchatView ) {
					multiAgentView.style.display = 'none';
					webchatView.style.display = 'flex';
					showWelcome();
					showProductContext();
				}
			} );
		} );

		var backToAgents = root.querySelector( '.cqfw-back-to-agents' );
		if ( backToAgents && multiAgentView && webchatView ) {
			backToAgents.addEventListener( 'click', function () {
				webchatView.style.display = 'none';
				multiAgentView.style.display = 'flex';
			} );
		}

		// Agent Cards click: track and open WhatsApp directly with selected agent
		root.querySelectorAll( '.cqfw-agent-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( card.classList.contains( 'is-offline' ) || card.getAttribute( 'data-online' ) === '0' ) {
					// Offline agent cannot be clicked
					return;
				}
				var phone = card.getAttribute( 'data-phone' ) || '';
				var cleanPhone = phone.replace( /\D/g, '' );
				if ( ! cleanPhone && settings.whatsapp_number ) {
					cleanPhone = String( settings.whatsapp_number ).replace( /\D/g, '' );
				}
				var message = config.defaultMessage || 'Hello!';
				var targetUrl = 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent( message );

				var productId = root.getAttribute( 'data-product-id' ) || '';
				postAjax( trackAction, { product_id: productId, page_type: 'multi_agent_whatsapp' } )
					.finally( function () {
						window.open( targetUrl, '_blank' );
					} );
			} );
		} );

		if ( openButton ) {
			openButton.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( root.classList.contains( 'is-open' ) ) {
					closePanel( root );
					return;
				}
				openPanel( root );
				if ( ! multiAgentView ) {
					if ( ! localStorage.getItem( 'cqfw_session_id' ) ) {
						showWelcome();
						showProductContext();
					} else {
						showProductContext();
						startPolling();
					}
				}
			} );
		}

		// Close panel on outside click / tap (ignore the open toggle itself).
		document.addEventListener( 'click', function ( e ) {
			if ( ! root.classList.contains( 'is-open' ) ) {
				return;
			}
			var panel = getPanel( root );
			var t = e.target;
			if ( openButton && ( openButton === t || openButton.contains( t ) ) ) {
				return;
			}
			if ( panel && ( panel === t || panel.contains( t ) ) ) {
				return;
			}
			// Backdrop tap on mobile (::before overlay covers root)
			closePanel( root );
		} );

		// Close panel on Escape key
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && root.classList.contains( 'is-open' ) ) {
				closePanel( root );
			}
		} );

		// Close panel on any close button click
		root.querySelectorAll( '.cqfw-chat-close' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				closePanel( root );
				resetConversation();
			} );
		} );

		if ( continueButton ) {
			continueButton.addEventListener( 'click', function () {
				resetConversation();
				if ( messagesArea ) {
					appendBubble( messagesArea, 'Great, keep chatting here whenever you like! \ud83d\ude0a', 'is-bot' );
				}
				if ( messageField ) { messageField.focus(); }
			} );
		}

		if ( whatsappButton ) {
			whatsappButton.addEventListener( 'click', function () {
				var targetUrl = whatsappUrl;
				if ( activeAgentPhone && targetUrl ) {
					var rawPhone = activeAgentPhone.replace( /\D/g, '' );
					targetUrl = targetUrl.replace( /wa\.me\/[0-9]*/, 'wa.me/' + rawPhone );
				}
				var productId = root.getAttribute( 'data-product-id' ) || '';
				postAjax( trackAction, { product_id: productId, page_type: 'chat_panel_whatsapp' } )
					.finally( function () {
						if ( targetUrl ) { window.location.href = targetUrl; }
					} );
			} );
		}

		/* ---------- PRO Rich Chat Input & Emojis ---------- */
		var attachBtn       = form ? form.querySelector( '.cqfw-chat-attach-btn' ) : null;
		var attachMenu      = form ? form.querySelector( '.cqfw-attach-menu' ) : null;
		var attachFileInput = form ? form.querySelector( '.cqfw-attach-file' ) : null;
		var attachScreenBtn = form ? form.querySelector( '.cqfw-attach-screenshot' ) : null;
		var hiddenFileInput = form ? form.querySelector( '.cqfw-hidden-file-input' ) : null;
		var attachPreview   = form ? form.querySelector( '.cqfw-attachment-preview' ) : null;
		var attachNameSpan  = form ? form.querySelector( '.cqfw-attachment-name' ) : null;
		var attachRemoveBtn = form ? form.querySelector( '.cqfw-attachment-remove' ) : null;
		var inputWrapper    = form ? form.querySelector( '.cqfw-message-input-wrapper' ) : null;

		var emojiBtn        = form ? form.querySelector( '.cqfw-chat-emoji-btn' ) : null;
		var emojiPicker     = form ? form.querySelector( '.cqfw-emoji-picker' ) : null;

		// Toggle Attachment Menu
		if ( attachBtn && attachMenu ) {
			attachBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				if ( emojiPicker ) { emojiPicker.hidden = true; }
				attachMenu.hidden = ! attachMenu.hidden;
			} );
		}

		// Trigger file upload from Send a file
		if ( attachFileInput && hiddenFileInput ) {
			attachFileInput.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				attachMenu.hidden = true;
				hiddenFileInput.accept = 'image/*,.pdf,.doc,.docx,.txt,.zip';
				hiddenFileInput.click();
			} );
		}

		// Trigger screenshot upload from Add screenshot
		if ( attachScreenBtn && hiddenFileInput ) {
			attachScreenBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				attachMenu.hidden = true;
				hiddenFileInput.accept = 'image/*';
				hiddenFileInput.click();
			} );
		}

		// Handle file selection with security and size validation
		if ( hiddenFileInput ) {
			hiddenFileInput.addEventListener( 'change', function () {
				if ( hiddenFileInput.files && hiddenFileInput.files[0] ) {
					var file = hiddenFileInput.files[0];
					var fileName = file.name;
					var ext = fileName.split( '.' ).pop().toLowerCase();
					var disallowed = [ 'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'cgi', 'pl', 'py' ];

					if ( disallowed.indexOf( ext ) !== -1 ) {
						alert( 'Disallowed file type for security. PHP and script files cannot be uploaded.' );
						hiddenFileInput.value = '';
						return;
					}

					// Max 5MB limit
					if ( file.size > 5 * 1024 * 1024 ) {
						alert( 'File size exceeds the 5MB limit. Please select a smaller file.' );
						hiddenFileInput.value = '';
						return;
					}

					if ( attachNameSpan ) { attachNameSpan.textContent = file.name; }
					if ( attachPreview ) { attachPreview.style.display = 'block'; }
					if ( inputWrapper ) { inputWrapper.classList.add( 'has-file' ); }
				}
			} );
		}

		// Remove attachment
		if ( attachRemoveBtn && hiddenFileInput ) {
			attachRemoveBtn.addEventListener( 'click', function () {
				hiddenFileInput.value = '';
				if ( attachPreview ) { attachPreview.style.display = 'none'; }
				if ( inputWrapper ) { inputWrapper.classList.remove( 'has-file' ); }
			} );
		}

		// Toggle Emoji Picker
		if ( emojiBtn && emojiPicker ) {
			emojiBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				if ( attachMenu ) { attachMenu.hidden = true; }
				emojiPicker.hidden = ! emojiPicker.hidden;
			} );
		}

		// Insert Emoji into Message Field
		if ( emojiPicker && messageField ) {
			emojiPicker.querySelectorAll( '.cqfw-emoji-item' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var emoji = btn.getAttribute( 'data-emoji' ) || btn.textContent.trim();
					var start = messageField.selectionStart || messageField.value.length;
					var end = messageField.selectionEnd || messageField.value.length;
					var val = messageField.value;
					messageField.value = val.substring( 0, start ) + emoji + val.substring( end );
					messageField.focus();
					messageField.setSelectionRange( start + emoji.length, start + emoji.length );
					if ( inputWrapper ) { inputWrapper.classList.add( 'has-content' ); }
					emojiPicker.hidden = true;
				} );
			} );
		}

		// Textarea input event for send button highlight
		if ( messageField && inputWrapper ) {
			messageField.addEventListener( 'input', function () {
				if ( messageField.value.trim().length > 0 ) {
					inputWrapper.classList.add( 'has-content' );
				} else {
					inputWrapper.classList.remove( 'has-content' );
				}
			} );
		}

		// Close attachment menu & emoji picker when clicking outside
		document.addEventListener( 'click', function ( e ) {
			if ( attachMenu && ! attachMenu.hidden && ! attachMenu.contains( e.target ) && e.target !== attachBtn ) {
				attachMenu.hidden = true;
			}
			if ( emojiPicker && ! emojiPicker.hidden && ! emojiPicker.contains( e.target ) && e.target !== emojiBtn ) {
				emojiPicker.hidden = true;
			}
		} );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var nameField = form.querySelector( 'input[name="name"]' );
				var phoneField = form.querySelector( 'input[name="phone"]' );
				var msgField = form.querySelector( 'textarea[name="message"]' );
				var submitButton = form.querySelector( '.cqfw-chat-send' );

				var currentSessionId = localStorage.getItem( 'cqfw_session_id' ) || '';

				if ( ! currentSessionId && settings.require_phone_number && ! phoneField.value.trim() ) {
					appendBubble( messagesArea, 'Phone number is required.', 'is-error' );
					return;
				}

				var hasFile = hiddenFileInput && hiddenFileInput.files && hiddenFileInput.files[0];
				var userText = msgField ? msgField.value.trim() : '';

				if ( ! userText && ! hasFile ) { return; }

				submitButton.disabled = true;
				submitButton.classList.add( 'is-loading' );

				var requestPromise;

				if ( hasFile ) {
					var formData = new FormData();
					formData.append( 'session_id', currentSessionId );
					formData.append( 'name', nameField ? nameField.value : '' );
					formData.append( 'phone', phoneField ? phoneField.value : '' );
					formData.append( 'message', userText );
					formData.append( 'product_id', ( config.product && config.product.product_id ) ? config.product.product_id : ( root.dataset.productId || '' ) );
					formData.append( 'page_url', root.dataset.pageUrl || window.location.href );
					formData.append( 'attachment', hiddenFileInput.files[0] );
					requestPromise = postFormData( chatAction, formData );
				} else {
					var postData = {
						session_id: currentSessionId,
						name: nameField ? nameField.value : '',
						phone: phoneField ? phoneField.value : '',
						message: userText,
						product_id: ( config.product && config.product.product_id ) ? config.product.product_id : ( root.dataset.productId || '' ),
						page_url: root.dataset.pageUrl || window.location.href
					};
					requestPromise = postAjax( chatAction, postData );
				}

				requestPromise
					.then( function ( response ) {
						if ( response && response.success ) {
							var responseData = response.data || {};

							if ( responseData.session_id ) {
								localStorage.setItem( 'cqfw_session_id', responseData.session_id );
								localStorage.setItem( 'cqfw_visitor_name', responseData.visitor_name || '' );
								localStorage.setItem( 'cqfw_visitor_phone', responseData.visitor_phone || '' );
								hideNamePhoneFields( form );
							}

							var newMsgId = responseData.message_id ? parseInt( responseData.message_id, 10 ) : null;

							var displayMsg = ( responseData && responseData.saved_message ) ? responseData.saved_message : userText;
							if ( ! displayMsg && hasFile && hiddenFileInput.files[0] ) {
								displayMsg = '📎 ' + hiddenFileInput.files[0].name;
							}
							appendBubble( messagesArea, displayMsg, 'is-user', newMsgId );

							whatsappUrl = responseData.whatsapp_url || '';

							// Clear input & attachment
							if ( msgField ) {
								msgField.value = '';
								autoResize( msgField );
							}
							if ( hiddenFileInput ) {
								hiddenFileInput.value = '';
							}
							if ( attachPreview ) {
								attachPreview.style.display = 'none';
							}
							if ( inputWrapper ) {
								inputWrapper.classList.remove( 'has-file' );
							}

							// Start instant polling right away without fake bot bubble delay
							startPolling();
						} else {
							appendBubble( messagesArea, ( response && response.data && response.data.message ) ? response.data.message : 'Something went wrong.', 'is-error' );
						}
					} )
					.catch( function () {
						appendBubble( messagesArea, 'Network error. Please try again.', 'is-error' );
					} )
					.finally( function () {
						submitButton.disabled = false;
						submitButton.classList.remove( 'is-loading' );
					} );
			} );
		}
	}

	/* ---------- WooCommerce Button Tracking ---------- */

	/**
	 * Pair Add to Cart + WhatsApp Buy into one row on shop cards (matches Style 3 admin preview).
	 * Fallback for themes that still print their own ATC beside our pair.
	 */
	function wrapShopLoopActions() {
		document.querySelectorAll( '.cqfw-shop-loop-actions.cqfw-cta-has-pair' ).forEach( function ( pair ) {
			var card = pair.closest( 'li.product, .wc-block-product, .wc-block-grid__product, .wp-block-post, .product' );
			if ( ! card ) {
				return;
			}
			card.classList.add( 'cqfw-has-cta-pair' );

			// Hide leftover theme ATC buttons outside our pair.
			card.querySelectorAll( 'a.button, button.button, .wp-block-button, .wc-block-components-product-button' ).forEach( function ( btn ) {
				if ( pair.contains( btn ) || btn.classList.contains( 'cqfw-cta-atc-btn' ) || btn.classList.contains( 'cqfw-cta-wa-btn' ) ) {
					return;
				}
				if ( btn.closest( '.cqfw-shop-loop-actions' ) ) {
					return;
				}
				btn.style.setProperty( 'display', 'none', 'important' );
			} );
		} );
	}

	function trackAndOpen( button ) {
		var url = button.getAttribute( 'data-whatsapp-url' ) || button.getAttribute( 'href' );
		if ( ! url ) { return; }
		var productId = button.getAttribute( 'data-product-id' ) || '';
		var pageType = button.getAttribute( 'data-page-type' ) || 'page';
		fireExternalTracking( {
			product_id: productId,
			page_type: pageType,
			page_url: window.location.href
		} );
		postAjax( trackAction, { product_id: productId, page_type: pageType } )
			.finally( function () { window.location.href = url; } );
	}

	function fireExternalTracking( data ) {
		var ctrl = config.controls || {};
		var adv = {};
		if ( ctrl.trackingAdvanced ) {
			adv = {
				page_url: data.page_url || window.location.href,
				product_id: data.product_id || '',
				page_type: data.page_type || '',
				title: document.title
			};
		}
		if ( ctrl.gaEnabled ) {
			var gaName = ctrl.gaEventName || 'cqfw_whatsapp_click';
			try {
				if ( typeof window.gtag === 'function' ) {
					window.gtag( 'event', gaName, adv );
				} else if ( window.dataLayer && Array.isArray( window.dataLayer ) ) {
					var pushObj = { event: gaName };
					Object.keys( adv ).forEach( function ( k ) { pushObj[ k ] = adv[ k ]; } );
					window.dataLayer.push( pushObj );
				}
			} catch ( e ) { /* ignore */ }
		}
		if ( ctrl.fbEnabled && typeof window.fbq === 'function' ) {
			try {
				window.fbq( 'track', ctrl.fbEvent || 'Contact', adv );
			} catch ( e2 ) { /* ignore */ }
		}
		if ( ctrl.googleAdsEnabled && ctrl.googleAdsSendTo && typeof window.gtag === 'function' ) {
			try {
				window.gtag( 'event', 'conversion', { send_to: ctrl.googleAdsSendTo } );
			} catch ( e3 ) { /* ignore */ }
		}
		if ( ctrl.webhookEnabled ) {
			postAjax( config.ajaxActionWebhook || 'cqfw_webhook_ping', {
				product_id: data.product_id || '',
				page_type: data.page_type || '',
				page_url: data.page_url || window.location.href
			} ).catch( function () { /* ignore */ } );
		}
	}

	function isMobileViewport() {
		return window.matchMedia && window.matchMedia( '(max-width: 782px)' ).matches;
	}

	function revealWidget( root ) {
		if ( ! root || root.classList.contains( 'cqfw-is-ready' ) ) {
			return;
		}
		root.classList.remove( 'cqfw-is-hidden' );
		root.classList.add( 'cqfw-is-ready' );
	}

	function setupVisibilityAndTriggers( root ) {
		var ctrl = config.controls || {};
		var desktopOk = ctrl.showDesktop !== false;
		var mobileOk = ctrl.showMobile !== false;
		var mobile = isMobileViewport();

		if ( ( mobile && ! mobileOk ) || ( ! mobile && ! desktopOk ) ) {
			root.style.display = 'none';
			return;
		}

		var timeDelay = parseInt( ctrl.timeDelay || root.getAttribute( 'data-time-delay' ) || '0', 10 ) || 0;
		var scrollPct = parseInt( ctrl.scrollDelay || root.getAttribute( 'data-scroll-delay' ) || '0', 10 ) || 0;
		var clickSel = ( ctrl.clickTrigger || root.getAttribute( 'data-click-trigger' ) || '' ).trim();
		var viewSel = ( ctrl.viewportTrigger || root.getAttribute( 'data-viewport-trigger' ) || '' ).trim();
		var revealed = root.classList.contains( 'cqfw-is-ready' );

		function doReveal() {
			if ( revealed && root.classList.contains( 'cqfw-is-ready' ) ) {
				return;
			}
			revealed = true;
			revealWidget( root );
		}

		var needsWait = timeDelay > 0 || scrollPct > 0 || !! viewSel;
		if ( ! needsWait ) {
			doReveal();
		}

		if ( timeDelay > 0 ) {
			setTimeout( doReveal, timeDelay * 1000 );
		}

		if ( scrollPct > 0 ) {
			var onScroll = function () {
				var doc = document.documentElement;
				var max = ( doc.scrollHeight - window.innerHeight ) || 1;
				var pct = ( window.scrollY / max ) * 100;
				if ( pct >= scrollPct ) {
					doReveal();
					window.removeEventListener( 'scroll', onScroll );
				}
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		if ( viewSel ) {
			try {
				var target = document.querySelector( viewSel );
				if ( target && typeof IntersectionObserver !== 'undefined' ) {
					var io = new IntersectionObserver( function ( entries ) {
						entries.forEach( function ( entry ) {
							if ( entry.isIntersecting ) {
								doReveal();
								io.disconnect();
							}
						} );
					}, { threshold: 0.15 } );
					io.observe( target );
				} else if ( ! needsWait ) {
					doReveal();
				}
			} catch ( err ) {
				doReveal();
			}
		}

		if ( clickSel ) {
			document.addEventListener( 'click', function ( e ) {
				var hit = e.target.closest( clickSel );
				if ( ! hit ) { return; }
				e.preventDefault();
				doReveal();
				var btn = root.querySelector( '.cqfw-floating-button' );
				if ( btn ) { btn.click(); }
			} );
		}
	}

	function setupExtras( root, panel ) {
		var extras = root.querySelector( '.cqfw-widget-extras' );
		if ( ! extras || ! panel ) { return; }
		extras.hidden = false;
		panel.appendChild( extras );

		var shareBtn = extras.querySelector( '.cqfw-share-page' );
		if ( shareBtn ) {
			shareBtn.addEventListener( 'click', function () {
				var tpl = shareBtn.getAttribute( 'data-share-tpl' ) || 'Check this out: {url}';
				var msg = tpl.replace( '{url}', window.location.href );
				var wa = 'https://wa.me/?text=' + encodeURIComponent( msg );
				fireExternalTracking( { page_type: 'share', page_url: window.location.href } );
				window.open( wa, '_blank', 'noopener' );
			} );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.cqfw-wa-button' );
		if ( button ) {
			event.preventDefault();
			trackAndOpen( button );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			wrapShopLoopActions();
			initWidget();
		} );
	} else {
		wrapShopLoopActions();
		initWidget();
	}
}() );
