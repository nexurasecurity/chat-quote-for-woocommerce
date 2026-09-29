/* global cqfwAdminInbox, jQuery */
( function ( $ ) {
	'use strict';

	var config = window.cqfwAdminInbox || {};
	var activeSessionId = '';
	var activePhone = '';
	var activeName = '';
	var lastMessageId = 0;
	var renderedAdminMessageIds = [];
	var threadPollInterval = null;
	var sidebarPollInterval = null;
	var searchTimeout = null;

	// UI Selectors
	var $root = $( '#cqfw-admin-inbox-root' );
	var $threadsList = $( '#cqfw-inbox-threads-list' );
	var $search = $( '#cqfw-inbox-search' );
	var $noChatSelected = $( '#cqfw-inbox-no-chat-selected' );
	var $activeChat = $( '#cqfw-inbox-active-chat' );
	var $bubblesContainer = $( '#cqfw-chat-bubbles-container' );
	var $replyForm = $( '#cqfw-admin-reply-form' );
	var $replyText = $( '#cqfw-reply-text' );
	var $sendBtn = $( '#cqfw-send-reply-btn' );

	var $activeName = $( '#cqfw-active-name' );
	var $activePhone = $( '#cqfw-active-phone' );
	var $activeContext = $( '#cqfw-active-context-link' );
	var $whatsappReplyLink = $( '#cqfw-whatsapp-reply-link' );
	var $deleteThreadBtn = $( '#cqfw-delete-thread-btn' );
	var $mobileBack = $( '#cqfw-mobile-back' );

	function postAjax( action, data ) {
		var postData = $.extend( {
			action: action,
			nonce: config.nonce || ''
		}, data );

		return $.ajax( {
			url: config.ajaxUrl,
			type: 'POST',
			data: postData,
			dataType: 'json'
		} );
	}

	function formatTime( mysqlDateString ) {
		if ( ! mysqlDateString ) {
			return '';
		}
		var t = mysqlDateString.split(/[- :]/);
		if ( t.length < 6 ) {
			return mysqlDateString;
		}
		var date = new Date( t[0], t[1] - 1, t[2], t[3], t[4], t[5] );
		if ( isNaN( date.getTime() ) ) {
			return mysqlDateString;
		}
		var now = new Date();
		var diff = now.getTime() - date.getTime();
		var oneDay = 24 * 60 * 60 * 1000;

		if ( diff < oneDay && now.getDate() === date.getDate() ) {
			return date.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
		} else if ( diff < 2 * oneDay && new Date( now.getTime() - oneDay ).getDate() === date.getDate() ) {
			return 'Yesterday';
		} else {
			return date.toLocaleDateString( [], { month: 'short', day: 'numeric' } );
		}
	}

	function buildWhatsAppUrl( phone, name, messageSnippet ) {
		var number = phone.replace( /\D/g, '' );
		if ( ! number ) {
			return '#';
		}
		var text = "Hello " + name + ",\n\nRegarding your inquiry: \"" + messageSnippet + "\"\n\n";
		return "https://wa.me/" + number + "?text=" + encodeURIComponent( text );
	}

	var avatarColors = ['#00a884','#53bdeb','#e8a239','#ef5350','#7c4dff','#26a69a','#ff7043','#5c6bc0','#66bb6a','#ab47bc'];

	function getAvatarColor( name ) {
		var hash = 0;
		for ( var i = 0; i < name.length; i++ ) {
			hash = name.charCodeAt( i ) + ( ( hash << 5 ) - hash );
		}
		return avatarColors[ Math.abs( hash ) % avatarColors.length ];
	}

	function getInitial( name ) {
		if ( ! name ) { return '?'; }
		return name.charAt( 0 ).toUpperCase();
	}

	function loadConversations( searchVal, isBackground ) {
		var data = {};
		if ( searchVal ) {
			data.search = searchVal;
		}

		if ( ! isBackground ) {
			$threadsList.html( '<div class="cqfw-inbox-loading">Loading conversations...</div>' );
		}

		postAjax( config.getConversations, data ).then( function ( response ) {
			if ( response && response.success && response.data && response.data.conversations ) {
				var conversations = response.data.conversations;
				if ( conversations.length === 0 ) {
					$threadsList.html( '<div class="cqfw-inbox-empty">No conversations yet.</div>' );
					return;
				}

				var html = '';
				conversations.forEach( function ( item ) {
					var activeClass = item.session_id === activeSessionId ? ' is-active' : '';
					var snippet = item.message || '';
					if ( snippet.length > 50 ) {
						snippet = snippet.substring( 0, 47 ) + '...';
					}

					var isUnread = item.status === 'pending' && item.sender === 'visitor';
					var dateClass = isUnread ? ' has-unread' : '';
					var senderPrefix = item.sender === 'admin' ? '<span class="cqfw-snippet-check">✓✓</span> ' : '';
					var avatarBg = getAvatarColor( item.name || 'U' );

					html += '<div class="cqfw-thread-item' + activeClass + '" data-session-id="' + item.session_id + '" data-phone="' + escHtml(item.phone || '') + '" data-name="' + escHtml(item.name || '') + '" data-message="' + encodeURIComponent(item.message || '') + '" data-page-url="' + escHtml(item.page_url || '') + '" data-product-id="' + parseInt(item.product_id || 0, 10) + '" data-product-name="' + escHtml(item.product_name || '') + '" data-product-price="' + escHtml(item.product_price || '') + '" data-product-url="' + escHtml(item.product_url || '') + '" data-product-image="' + escHtml(item.product_image || '') + '">';
					html += '  <div class="cqfw-thread-avatar" style="background:' + avatarBg + '">' + getInitial( item.name ) + '</div>';
					html += '  <div class="cqfw-thread-content">';
					html += '    <div class="cqfw-thread-header">';
					html += '      <h3 class="cqfw-thread-name">' + escHtml( item.name || 'Unknown' ) + '</h3>';
					var timeStr = item.created_at_gmt ? (item.created_at_gmt.replace(' ', 'T') + 'Z') : item.created_at;
					html += '      <span class="cqfw-thread-date' + dateClass + '">' + formatTime( timeStr ) + '</span>';
					html += '    </div>';
					html += '    <p class="cqfw-thread-snippet">' + senderPrefix + escHtml( snippet ) + '</p>';
					html += '  </div>';
					if ( isUnread ) {
						html += '  <span class="cqfw-unread-badge">1</span>';
					}
					html += '</div>';
				} );

				$threadsList.html( html );
			} else if ( ! isBackground ) {
				$threadsList.html( '<div class="cqfw-inbox-empty">Error loading threads.</div>' );
			}
		} );
	}

	function escHtml( string ) {
		var entityMap = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#39;',
			'/': '&#x2F;',
			'`': '&#x60;',
			'=': '&#x3D;'
		};
		return String( string ).replace( /[&<>"'`=\/]/g, function ( s ) {
			return entityMap[ s ];
		} );
	}

		function renderBubbleContent( text ) {
		var lines = ( text || "" ).split( "\n" );
		var html = "";
		var hasAttachment = false;

		lines.forEach( function ( line ) {
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
						'<a href="' + encodeURI( fileUrl ) + '" class="cqfw-lightbox-trigger" data-img="' + encodeURI( fileUrl ) + '" data-title="' + escHtml( fileName ) + '" title="Click to zoom">' +
							'<img src="' + encodeURI( fileUrl ) + '" alt="' + escHtml( fileName ) + '" class="cqfw-zoomable-img" />' +
						'</a>' +
					'</div>';
				} else {
					html += '<div class="cqfw-bubble-attachment is-file">' +
						'<a href="' + encodeURI( fileUrl ) + '" target="_blank" rel="noopener" download style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(0,0,0,0.06);border-radius:6px;text-decoration:none;color:inherit;font-size:13px;margin:6px 0;font-weight:500;">' +
							'📎 <strong>' + escHtml( fileName ) + '</strong>' +
						'</a>' +
					'</div>';
				}
			} else if ( line.trim() ) {
				html += ( html ? "<br>" : "" ) + escHtml( line );
			}
		} );

		return hasAttachment ? html : escHtml( text || "" );
	}

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
	$( document ).on( "click", ".cqfw-lightbox-trigger", function ( e ) {
		e.preventDefault();
		var $this = $( this );
		var imgSrc = $this.attr( "data-img" ) || $this.attr( "href" );
		var title  = $this.attr( "data-title" ) || "Image Preview";
		if ( imgSrc ) {
			openLightbox( imgSrc, title );
		}
	} );

	function appendBubble( text, sender, date, msgId ) {
		if ( msgId ) {
			var id = parseInt( msgId, 10 );
			if ( id > lastMessageId ) {
				lastMessageId = id;
			}
			if ( renderedAdminMessageIds.indexOf( id ) !== -1 ) {
				return;
			}
			renderedAdminMessageIds.push( id );
		}

		var wrapClass = sender === 'admin' ? 'is-admin' : 'is-visitor';
		var timeHtml = date ? '<span class="cqfw-bubble-time">' + formatTime( date ) + '</span>' : '';
		var bubbleHtml = '<div class="cqfw-admin-bubble-wrap ' + wrapClass + '">';
		bubbleHtml += '  <div class="cqfw-admin-bubble">' + renderBubbleContent( text ) + timeHtml + '</div>';
		bubbleHtml += '</div>';

		$bubblesContainer.append( bubbleHtml );
		$bubblesContainer.scrollTop( $bubblesContainer[0].scrollHeight );
	}

	function openConversation( sessionId, phone, name, messageSnippet, pageUrl, productName, productPrice, productUrl, productImage ) {
		activeSessionId = sessionId;
		activePhone = phone;
		activeName = name;
		lastMessageId = 0;
		renderedAdminMessageIds = [];

		// Update sidebar active class
		$threadsList.find( '.cqfw-thread-item' ).removeClass( 'is-active' );
		var $clickedThread = $threadsList.find( '[data-session-id="' + sessionId + '"]' );
		$clickedThread.addClass( 'is-active' );
		$clickedThread.find( '.cqfw-unread-badge' ).remove(); // Immediately clear the badge visually
		$clickedThread.find( '.cqfw-thread-date' ).removeClass( 'has-unread' ); // Clear the green text color

		// Switch panel visibility
		$noChatSelected.hide();
		$activeChat.show();

		// Set header info
		$activeName.text( name );
		$activePhone.text( phone );
		$whatsappReplyLink.attr( 'href', buildWhatsAppUrl( phone, name, messageSnippet ) );

		// Update context link and prepare product card
		var productCardHtml = '';
		if ( productName ) {
			productCardHtml = '<div class="cqfw-admin-product-context" style="display:flex; align-items:center; gap: 12px; margin: 15px 20px 5px; padding: 12px 16px; background: #fff; border: 1px solid var(--cq-border); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
			if ( productImage ) {
				productCardHtml += '<img src="' + escHtml( productImage ) + '" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">';
			}
			productCardHtml += '<div style="flex: 1;">';
			productCardHtml += '<a href="' + escHtml( productUrl || pageUrl ) + '" target="_blank" rel="noopener noreferrer" style="font-weight: 600; font-size: 14px; text-decoration: none; color: var(--cq-text); display: block;">' + escHtml( productName ) + '</a>';
			if ( productPrice ) {
				productCardHtml += '<div style="color: var(--cq-green); font-size: 13px; font-weight: 600; margin-top: 4px;">' + escHtml( productPrice ) + '</div>';
			}
			productCardHtml += '</div>';
			productCardHtml += '<a href="' + escHtml( productUrl || pageUrl ) + '" target="_blank" rel="noopener noreferrer" class="button button-secondary button-small" style="display:flex; align-items:center; gap:4px;">View <span class="dashicons dashicons-external" style="font-size:14px; line-height:1.5;"></span></a>';
			productCardHtml += '</div>';

			$activeContext.html( '<a href="' + escHtml( productUrl || pageUrl ) + '" target="_blank" rel="noopener noreferrer">View Product <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.5;"></span></a>' ).show();
		} else if ( pageUrl ) {
			$activeContext.html( '<a href="' + escHtml( pageUrl ) + '" target="_blank" rel="noopener noreferrer">View Source Page <span class="dashicons dashicons-external" style="font-size: 14px; line-height: 1.5;"></span></a>' ).show();
		} else {
			$activeContext.hide();
		}

		// Mobile slide in
		$root.addClass( 'is-thread-active' );

		$bubblesContainer.html( '<div class="cqfw-inbox-loading">Loading message history...</div>' );

		// Stop previous polling
		if ( threadPollInterval ) {
			clearInterval( threadPollInterval );
		}

		// Fetch history
		postAjax( config.getHistory, { session_id: sessionId, nonce: config.nonceUser } ).then( function ( response ) {
			if ( response && response.success && response.data && response.data.messages ) {
				$bubblesContainer.html( '' );
				if ( productCardHtml ) {
					$bubblesContainer.append( productCardHtml );
				}
				var messages = response.data.messages;
				messages.forEach( function ( msg ) {
					appendBubble( msg.message, msg.sender, msg.created_at, msg.id );
				} );
			} else {
				$bubblesContainer.html( '<div class="cqfw-inbox-empty">Failed to load messages.</div>' );
			}

			// Scroll bottom
			$bubblesContainer.scrollTop( $bubblesContainer[0].scrollHeight );

			// Start active polling
			startThreadPolling();
		} ).fail( function() {
			$bubblesContainer.html( '<div class="cqfw-inbox-empty">Failed to load messages (Network Error/Nonce).</div>' );
		} );
	}

	function pollThreadNow() {
		if ( ! activeSessionId ) {
			return;
		}
		$.ajax( {
			url: config.ajaxUrl,
			type: 'POST',
			data: {
				action: config.pollMessages,
				nonce: config.nonceUser || config.nonce,
				session_id: activeSessionId,
				last_id: lastMessageId
			},
			dataType: 'json'
		} ).then( function ( response ) {
			if ( response && response.success && response.data && response.data.messages ) {
				response.data.messages.forEach( function ( msg ) {
					appendBubble( msg.message, msg.sender, msg.created_at, msg.id );
				} );
			}
		} );
	}

	function startThreadPolling() {
		if ( threadPollInterval ) {
			clearInterval( threadPollInterval );
		}
		pollThreadNow();
		threadPollInterval = setInterval( pollThreadNow, 1500 );
	}

	// Setup DOM triggers
	$threadsList.on( 'click', '.cqfw-thread-item', function () {
		var $this = $( this );
		var sessionId = $this.data( 'session-id' );
		var phone = $this.data( 'phone' );
		var name = $this.data( 'name' );
		var messageSnippet = decodeURIComponent( $this.data( 'message' ) );
		var pageUrl = $this.data( 'page-url' ) || '';
		var productName = $this.data( 'product-name' ) || '';
		var productPrice = $this.data( 'product-price' ) || '';
		var productUrl = $this.data( 'product-url' ) || '';
		var productImage = $this.data( 'product-image' ) || '';

		openConversation( sessionId, phone, name, messageSnippet, pageUrl, productName, productPrice, productUrl, productImage );
	} );

	$mobileBack.on( 'click', function () {
		$root.removeClass( 'is-thread-active' );
		activeSessionId = '';
		if ( threadPollInterval ) {
			clearInterval( threadPollInterval );
			threadPollInterval = null;
		}
		$threadsList.find( '.cqfw-thread-item' ).removeClass( 'is-active' );
		$noChatSelected.show();
		$activeChat.hide();
	} );

	// Admin Attachment & Emoji Handlers
	var $adminAttachBtn     = $( '#cqfw-admin-attach-btn' );
	var $adminAttachMenu    = $( '.cqfw-admin-attach-menu' );
	var $adminAttachFile    = $( '.cqfw-admin-attach-file' );
	var $adminAttachScreen  = $( '.cqfw-admin-attach-screenshot' );
	var $adminHiddenFile    = $( '#cqfw-admin-hidden-file' );
	var $adminAttachPreview = $( '.cqfw-admin-attachment-preview' );
	var $adminAttachName    = $( '.cqfw-admin-attachment-name' );
	var $adminAttachRemove  = $( '.cqfw-admin-attachment-remove' );

	var $adminEmojiBtn      = $( '#cqfw-admin-emoji-btn' );
	var $adminEmojiPicker   = $( '.cqfw-admin-emoji-picker' );

	$adminAttachBtn.on( 'click', function ( e ) {
		e.stopPropagation();
		$adminEmojiPicker.prop( 'hidden', true );
		$adminAttachMenu.prop( 'hidden', ! $adminAttachMenu.prop( 'hidden' ) );
	} );

	$adminAttachFile.on( 'click', function ( e ) {
		e.stopPropagation();
		$adminAttachMenu.prop( 'hidden', true );
		$adminHiddenFile.attr( 'accept', 'image/*,.pdf,.doc,.docx,.txt,.zip' ).trigger( 'click' );
	} );

	$adminAttachScreen.on( 'click', function ( e ) {
		e.stopPropagation();
		$adminAttachMenu.prop( 'hidden', true );
		$adminHiddenFile.attr( 'accept', 'image/*' ).trigger( 'click' );
	} );

	$adminHiddenFile.on( 'change', function () {
		if ( this.files && this.files[0] ) {
			var file = this.files[0];
			var fileName = file.name;
			var ext = fileName.split( '.' ).pop().toLowerCase();
			var disallowed = [ 'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'cgi', 'pl', 'py' ];

			if ( disallowed.indexOf( ext ) !== -1 ) {
				alert( 'Disallowed file type for security. PHP and executable script files cannot be uploaded.' );
				$adminHiddenFile.val( '' );
				return;
			}

			if ( file.size > 5 * 1024 * 1024 ) {
				alert( 'File size exceeds the 5MB limit. Please select a smaller file.' );
				$adminHiddenFile.val( '' );
				return;
			}

			$adminAttachName.text( file.name );
			$adminAttachPreview.show();
		}
	} );

	$adminAttachRemove.on( 'click', function () {
		$adminHiddenFile.val( '' );
		$adminAttachPreview.hide();
	} );

	$adminEmojiBtn.on( 'click', function ( e ) {
		e.stopPropagation();
		$adminAttachMenu.prop( 'hidden', true );
		$adminEmojiPicker.prop( 'hidden', ! $adminEmojiPicker.prop( 'hidden' ) );
	} );

	$( document ).on( 'click', '.cqfw-admin-emoji-item', function ( e ) {
		e.stopPropagation();
		var emoji = $( this ).data( 'emoji' ) || $( this ).text().trim();
		var curVal = $replyText.val();
		$replyText.val( curVal + emoji ).focus();
		$adminEmojiPicker.prop( 'hidden', true );
	} );

	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.cqfw-admin-attach-menu, #cqfw-admin-attach-btn' ).length ) {
			$adminAttachMenu.prop( 'hidden', true );
		}
		if ( ! $( e.target ).closest( '.cqfw-admin-emoji-picker, #cqfw-admin-emoji-btn' ).length ) {
			$adminEmojiPicker.prop( 'hidden', true );
		}
	} );

	// Reply submission
	$replyForm.on( 'submit', function ( e ) {
		e.preventDefault();
		var replyText = $replyText.val().trim();
		var hasAdminFile = $adminHiddenFile[0] && $adminHiddenFile[0].files && $adminHiddenFile[0].files[0];

		if ( ( ! replyText && ! hasAdminFile ) || ! activeSessionId ) {
			return;
		}

		$sendBtn.prop( 'disabled', true ).css( 'opacity', '0.6' );

		var requestPromise;
		if ( hasAdminFile ) {
			var formData = new FormData();
			formData.append( 'action', config.sendReply );
			formData.append( 'nonce', config.nonce || '' );
			formData.append( 'session_id', activeSessionId );
			formData.append( 'message', replyText || ( '📎 ' + $adminHiddenFile[0].files[0].name ) );
			formData.append( 'attachment', $adminHiddenFile[0].files[0] );

			requestPromise = $.ajax( {
				url: config.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json'
			} );
		} else {
			requestPromise = postAjax( config.sendReply, {
				session_id: activeSessionId,
				message: replyText
			} );
		}

		requestPromise.then( function ( response ) {
			if ( response && response.success ) {
				var displayMsg = ( response.data && response.data.saved_message ) ? response.data.saved_message : replyText;
				if ( ! displayMsg && hasAdminFile ) {
					displayMsg = '📎 ' + $adminHiddenFile[0].files[0].name;
				}
				$replyText.val( '' );
				$adminHiddenFile.val( '' );
				$adminAttachPreview.hide();
				var newMsgId = response.data && response.data.message_id ? response.data.message_id : null;
				appendBubble( displayMsg, 'admin', new Date().toISOString().replace('T', ' ').substring(0, 19), newMsgId );
				// Reload sidebar to show latest snippet
				loadConversations( $search.val(), true );
			} else {
				alert( response.data && response.data.message ? response.data.message : 'Error sending reply.' );
			}
		} ).always( function () {
			$sendBtn.prop( 'disabled', false ).css( 'opacity', '1' );
		} );
	} );

	// Press Enter to send, Shift+Enter for new line
	$replyText.on( 'keydown', function ( e ) {
		if ( e.which === 13 && ! e.shiftKey ) {
			e.preventDefault();
			$replyForm.trigger( 'submit' );
		}
	} );

	// Delete thread
	$deleteThreadBtn.on( 'click', function () {
		if ( ! activeSessionId ) {
			return;
		}
		if ( ! confirm( 'Are you sure you want to delete this entire conversation history?' ) ) {
			return;
		}

		postAjax( config.deleteConversation, { session_id: activeSessionId } ).then( function ( response ) {
			if ( response && response.success ) {
				$root.removeClass( 'is-thread-active' );
				activeSessionId = '';
				$noChatSelected.show();
				$activeChat.hide();
				loadConversations( $search.val(), false );
			} else {
				alert( 'Failed to delete conversation.' );
			}
		} );
	} );

	// Search filter
	$search.on( 'input', function () {
		var val = $( this ).val();
		if ( searchTimeout ) {
			clearTimeout( searchTimeout );
		}
		searchTimeout = setTimeout( function () {
			loadConversations( val, false );
		}, 300 );
	} );

	// Full screen inbox — expand for comfortable chatting
	var $inboxWrap = $( '#cqfw-inbox-wrap' );
	var $fsBtns = $( '#cqfw-inbox-fullscreen-btn, #cqfw-chat-fullscreen-btn' );

	function setInboxFullscreen( on ) {
		$inboxWrap.toggleClass( 'is-fullscreen', !! on );
		$( 'body' ).toggleClass( 'cqfw-inbox-is-fullscreen', !! on );
		$fsBtns.attr( 'aria-pressed', on ? 'true' : 'false' );
		$fsBtns.attr(
			'title',
			on ? 'Exit full screen' : 'Open full screen chat'
		);
		$( '#cqfw-inbox-fullscreen-btn .cqfw-inbox-fullscreen-btn__label' ).text(
			on ? 'Exit full screen' : 'Full screen'
		);
	}

	$fsBtns.on( 'click', function ( e ) {
		e.preventDefault();
		setInboxFullscreen( ! $inboxWrap.hasClass( 'is-fullscreen' ) );
	} );

	$( document ).on( 'keydown.cqfwInboxFs', function ( e ) {
		if ( e.key === 'Escape' && $inboxWrap.hasClass( 'is-fullscreen' ) ) {
			setInboxFullscreen( false );
		}
	} );

	// Initialize dashboard
	loadConversations( '', false );

	// Start background sidebar polling every 3 seconds
	sidebarPollInterval = setInterval( function () {
		loadConversations( $search.val(), true );
	}, 2000 );

} )( jQuery );
