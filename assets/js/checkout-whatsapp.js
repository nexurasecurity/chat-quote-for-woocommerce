/**
 * Checkout → WhatsApp (Pro)
 * Toggle: when ON, Place order opens WhatsApp with customer + cart info.
 */
(function () {
	'use strict';

	var cfg = window.cqfwCheckoutWA;
	if (!cfg || !cfg.waBase) {
		return;
	}

	var i18n = cfg.i18n || {};
	var originalPlaceLabel = '';
	var storageKey = 'cqfw_checkout_wa_pref';

	function $(sel, root) {
		return (root || document).querySelector(sel);
	}

	function getStoredPref() {
		try {
			var v = window.sessionStorage.getItem(storageKey);
			if (v === '1') {
				return true;
			}
			if (v === '0') {
				return false;
			}
		} catch (e) {
			/* ignore */
		}
		return null;
	}

	function setStoredPref(on) {
		try {
			window.sessionStorage.setItem(storageKey, on ? '1' : '0');
		} catch (e) {
			/* ignore */
		}
	}

	function applyDefaultState(toggle) {
		if (!toggle) {
			return;
		}
		var stored = getStoredPref();
		var on = stored === null ? !!cfg.defaultOn : stored;
		toggle.checked = on;
		toggle.setAttribute('aria-checked', on ? 'true' : 'false');
	}

	function val(sel) {
		var el = $(sel);
		return el && typeof el.value === 'string' ? el.value.trim() : '';
	}

	function field() {
		var shipDiff = $('#ship-to-different-address-checkbox');
		var useShip = shipDiff && shipDiff.checked;

		var first = val('#billing_first_name') || val('#billing-first_name') || val('input[name="billing_first_name"]');
		var last = val('#billing_last_name') || val('#billing-last_name') || val('input[name="billing_last_name"]');
		var name = (first + ' ' + last).trim() || val('#billing_company') || '';

		var phone = val('#billing_phone') || val('#billing-phone') || val('input[name="billing_phone"]');
		var email = val('#billing_email') || val('#email') || val('input[name="billing_email"]');

		function addr(prefix) {
			var parts = [
				val('#' + prefix + '_address_1') || val('#' + prefix + '-address_1') || val('input[name="' + prefix + '_address_1"]'),
				val('#' + prefix + '_address_2') || val('#' + prefix + '-address_2') || val('input[name="' + prefix + '_address_2"]'),
				val('#' + prefix + '_city') || val('#' + prefix + '-city') || val('input[name="' + prefix + '_city"]'),
				val('#' + prefix + '_state') || val('#' + prefix + '-state') || val('select[name="' + prefix + '_state"]') || val('input[name="' + prefix + '_state"]'),
				val('#' + prefix + '_postcode') || val('#' + prefix + '-postcode') || val('input[name="' + prefix + '_postcode"]'),
				val('#' + prefix + '_country') || val('#' + prefix + '-country') || val('select[name="' + prefix + '_country"]') || val('input[name="' + prefix + '_country"]')
			].filter(Boolean);
			return parts.join(', ');
		}

		var billingAddr = addr('billing');
		var shippingAddr = useShip ? addr('shipping') : billingAddr;
		var notes = val('#order_comments') || val('#order-notes') || val('textarea[name="order_comments"]');

		// Block checkout often uses different IDs — try common WC Blocks fields.
		if (!first) {
			first = val('#billing-first_name') || val('input[id*="billing-first"]');
			last = val('#billing-last_name') || val('input[id*="billing-last"]');
			name = (first + ' ' + last).trim() || name;
		}
		if (!phone) {
			phone = val('input[id*="phone"]') || phone;
		}
		if (!email) {
			email = val('input[type="email"]') || email;
		}

		return {
			name: name,
			phone: phone,
			email: email,
			billing: billingAddr,
			shipping: shippingAddr,
			notes: notes
		};
	}

	function buildMessage(data) {
		var lines = [];
		lines.push('*' + (i18n.heading || 'New checkout order via WhatsApp') + '*');
		lines.push('');
		lines.push('*' + (i18n.customer || 'Customer') + '*');
		if (data.name) {
			lines.push('Name: ' + data.name);
		}
		if (data.phone) {
			lines.push('Phone: ' + data.phone);
		}
		if (data.email) {
			lines.push('Email: ' + data.email);
		}
		if (data.billing) {
			lines.push('Address: ' + data.billing);
		}
		if (data.shipping && data.shipping !== data.billing) {
			lines.push((i18n.shipping || 'Shipping') + ': ' + data.shipping);
		}
		lines.push('');
		lines.push('*' + (i18n.items || 'Items') + '*');

		(cfg.cartLines || []).forEach(function (item, idx) {
			var row = (idx + 1) + '. ' + item.name + ' × ' + item.qty + ' — ' + item.price;
			if (item.sku) {
				row += ' (SKU: ' + item.sku + ')';
			}
			lines.push(row);
		});

		lines.push('');
		lines.push('*' + (i18n.totals || 'Totals') + '*');
		var t = cfg.totals || {};
		if (t.subtotal) {
			lines.push((i18n.subtotal || 'Subtotal') + ': ' + t.subtotal);
		}
		if (t.shipping) {
			lines.push((i18n.shippingCost || 'Shipping') + ': ' + t.shipping);
		}
		if (t.tax) {
			lines.push((i18n.tax || 'Tax') + ': ' + t.tax);
		}
		if (t.total) {
			lines.push((i18n.total || 'Total') + ': ' + t.total);
		}

		if (data.notes) {
			lines.push('');
			lines.push('*' + (i18n.notes || 'Order notes') + '*');
			lines.push(data.notes);
		}

		return lines.join('\n');
	}

	function openWhatsApp(data) {
		var msg = buildMessage(data);
		var url = cfg.waBase + (cfg.waBase.indexOf('?') >= 0 ? '&' : '?') + 'text=' + encodeURIComponent(msg);
		window.open(url, '_blank');
	}

	function isOn() {
		var box = $('#cqfw_place_order_whatsapp');
		return !!(box && box.checked);
	}

	function updatePlaceButton() {
		var toggle = $('#cqfw_place_order_whatsapp');
		if (toggle) {
			toggle.setAttribute('aria-checked', isOn() ? 'true' : 'false');
		}

		var btn =
			$('#place_order') ||
			$('.wc-block-components-checkout-place-order-button') ||
			$('button[type="submit"].wc-block-components-checkout-place-order-button');

		if (!btn) {
			return;
		}

		if (!originalPlaceLabel) {
			originalPlaceLabel = (btn.innerText || btn.value || '').trim();
		}

		if (isOn()) {
			if (btn.tagName === 'INPUT') {
				btn.value = i18n.buttonLabel || 'Place order on WhatsApp';
			} else {
				btn.textContent = i18n.buttonLabel || 'Place order on WhatsApp';
			}
			btn.classList.add('cqfw-place-order-wa');
			btn.setAttribute('aria-label', i18n.buttonLabel || 'Place order on WhatsApp');
		} else {
			if (btn.tagName === 'INPUT') {
				btn.value = originalPlaceLabel || 'Place order';
			} else {
				btn.textContent = originalPlaceLabel || 'Place order';
			}
			btn.classList.remove('cqfw-place-order-wa');
			btn.removeAttribute('aria-label');
		}
	}

	function onPlaceOrder(e) {
		if (!isOn()) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();
		if (typeof e.stopImmediatePropagation === 'function') {
			e.stopImmediatePropagation();
		}

		var data = field();
		if (!data.name) {
			window.alert(i18n.missingName || 'Please enter your name.');
			return false;
		}
		if (!data.phone) {
			window.alert(i18n.missingPhone || 'Please enter your phone number.');
			return false;
		}

		openWhatsApp(data);
		return false;
	}

	function bind() {
		var toggle = $('#cqfw_place_order_whatsapp');
		applyDefaultState(toggle);

		if (toggle) {
			toggle.addEventListener('change', function () {
				setStoredPref(!!toggle.checked);
				updatePlaceButton();
			});
		}

		document.addEventListener(
			'click',
			function (e) {
				var t = e.target;
				if (!t) {
					return;
				}
				var btn =
					t.id === 'place_order' ||
					t.closest('#place_order') ||
					t.closest('.wc-block-components-checkout-place-order-button') ||
					(t.matches && t.matches('button.wc-block-components-checkout-place-order-button'));
				if (!btn) {
					return;
				}
				if (!isOn()) {
					return;
				}
				onPlaceOrder(e);
			},
			true
		);

		// Classic form submit fallback.
		var form = $('form.checkout');
		if (form) {
			form.addEventListener(
				'submit',
				function (e) {
					if (isOn()) {
						onPlaceOrder(e);
					}
				},
				true
			);
		}

		updatePlaceButton();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}

	// Woo fragments / block re-renders.
	document.body.addEventListener('updated_checkout', function () {
		updatePlaceButton();
	});
})();
