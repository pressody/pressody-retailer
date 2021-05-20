/* global wp:false */

(function( window, $, _, Backbone, wp, undefined ) {
	'use strict';

	var $tabs = $( '.nav-tab-wrapper .nav-tab' ),
		$panels = $( '.pixelgradelt_retailer-tab-panel' ),
		updateTabs;

	updateTabs = function() {
		var hash = window.location.hash;

		$tabs.removeClass( 'nav-tab-active' ).filter( '[href="' + hash + '"]' ).addClass( 'nav-tab-active' );
		$panels.removeClass( 'is-active' ).filter( hash ).addClass( 'is-active' );

		if ( $tabs.filter( '.nav-tab-active' ).length < 1 ) {
			var href = $tabs.eq( 0 ).addClass( 'nav-tab-active' ).attr( 'href' );
			$panels.removeClass( 'is-active' ).filter( href ).addClass( 'is-active' );
		}
	};

	updateTabs();
	$( window ).on( 'hashchange', updateTabs );

	// Scroll back to the top when a tab panel is reloaded or submitted.
	setTimeout(function() {
		if ( location.hash ) {
			window.scrollTo( 0, 1 );
		}
	}, 1 );

	// Handle the checkbox for toggling plugins.
	$( document ).on( 'change', '.pixelgradelt_retailer-status', function() {
		var $checkbox = $( this ),
			$spinner = $( this ).siblings( '.spinner' ).addClass( 'is-active' );

		wp.ajax.post( 'pixelgradelt_retailer_toggle_plugin', {
			plugin_file: $checkbox.val(),
			status: $checkbox.prop( 'checked' ),
			_wpnonce: $checkbox.siblings( '.pixelgradelt_retailer-status-nonce' ).val()
		}).done(function() {
			setTimeout( function() {
				$spinner.removeClass( 'is-active' );
			}, 300);
		}).fail(function() {
			$spinner.removeClass( 'is-active' );
		});
	});

	function toggleDropdown( $dropdown, isOpen ) {
		return $dropdown
			.toggleClass( 'is-open', isOpen )
			.attr( 'aria-expanded', isOpen );
	}

	$( document )
		.on( 'click.pixelgradelt_retailer', '.pixelgradelt_retailer-dropdown-toggle', function( e ) {
			var $group = $( e.target ).closest( '.pixelgradelt_retailer-dropdown-group' ),
				isOpen = $group.hasClass( 'is-open' );

			e.preventDefault();

			toggleDropdown( $group, ! isOpen );
		})
		.on( 'click.pixelgradelt_retailer', function( e ) {
			var $button = $( e.target ).closest( 'button' ),
				$group = $( e.target ).closest( '.pixelgradelt_retailer-dropdown-group' );

			if ( ! $button.hasClass( 'pixelgradelt_retailer-dropdown-toggle' ) ) {
				toggleDropdown( $( '.pixelgradelt_retailer-dropdown-group' ), false );
			} else {
				toggleDropdown( $( '.pixelgradelt_retailer-dropdown-group' ).not( $group ), false );
			}
		});

})( this, jQuery, _, Backbone, wp );
