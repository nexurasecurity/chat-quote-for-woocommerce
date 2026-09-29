/* global Chart, cqfwAnalyticsData */
( function () {
	'use strict';

	function initChart() {
		var canvas = document.getElementById( 'cqfw-clicks-chart' );
		if ( ! canvas || typeof Chart === 'undefined' || typeof cqfwAnalyticsData === 'undefined' ) {
			return;
		}

		var ctx = canvas.getContext( '2d' );

		// Gradient for the line fill
		var gradient = ctx.createLinearGradient( 0, 0, 0, 400 );
		gradient.addColorStop( 0, 'rgba(37, 211, 102, 0.4)' );
		gradient.addColorStop( 1, 'rgba(37, 211, 102, 0.0)' );

		var labels = cqfwAnalyticsData.chartLabels || [];
		var data = cqfwAnalyticsData.chartData || [];

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [ {
					label: 'WhatsApp Clicks',
					data: data,
					borderColor: '#25d366',
					backgroundColor: gradient,
					borderWidth: 3,
					pointBackgroundColor: '#ffffff',
					pointBorderColor: '#075e54',
					pointBorderWidth: 2,
					pointRadius: 4,
					pointHoverRadius: 6,
					fill: true,
					tension: 0.4 // Smooth curves
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: '#1e293b',
						titleColor: '#ffffff',
						bodyColor: '#ffffff',
						titleFont: { size: 13, family: '-apple-system, sans-serif' },
						bodyFont: { size: 14, weight: 'bold', family: '-apple-system, sans-serif' },
						padding: 12,
						displayColors: false,
						callbacks: {
							label: function(context) {
								return context.parsed.y + ' clicks';
							}
						}
					}
				},
				// Add fallback for Chart.js 2.x in case WooCommerce overrides window.Chart
				tooltips: {
					backgroundColor: '#1e293b',
					titleFontColor: '#ffffff',
					bodyFontColor: '#ffffff',
					titleFontSize: 13,
					bodyFontSize: 14,
					xPadding: 12,
					yPadding: 12,
					displayColors: false,
					callbacks: {
						label: function(tooltipItem, data) {
							return tooltipItem.yLabel + ' clicks';
						}
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							font: { family: '-apple-system, sans-serif', color: '#64748b' }
						},
						grid: {
							color: '#f1f5f9',
							drawBorder: false
						}
					},
					x: {
						grid: {
							display: false,
							drawBorder: false
						},
						ticks: {
							font: { family: '-apple-system, sans-serif', color: '#64748b' }
						}
					}
				},
				interaction: {
					intersect: false,
					mode: 'index',
				},
			}
		} );
	}

	function animateCounters() {
		var counters = document.querySelectorAll( '.cqfw-stat-value' );
		counters.forEach( function( counter ) {
			var target = parseInt( counter.getAttribute( 'data-target' ), 10 ) || 0;
			var duration = 1500;
			var stepTime = Math.abs( Math.floor( duration / ( target || 1 ) ) );
			// Ensure it's not too fast or too slow
			if ( stepTime < 10 ) stepTime = 10;
			if ( stepTime > 50 ) stepTime = 50;
			
			var current = 0;
			var steps = Math.ceil( duration / stepTime );
			var increment = Math.ceil( target / steps );

			if ( target === 0 ) {
				counter.textContent = '0';
				return;
			}

			var timer = setInterval( function() {
				current += increment;
				if ( current >= target ) {
					counter.textContent = target.toLocaleString();
					clearInterval( timer );
				} else {
					counter.textContent = current.toLocaleString();
				}
			}, stepTime );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			initChart();
			animateCounters();
		} );
	} else {
		initChart();
		animateCounters();
	}
}() );
