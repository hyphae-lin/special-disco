/**
 * Registers the Hexxsy Stripe gateway with WooCommerce's block checkout.
 * Payment happens on Stripe's page, so this only shows a label and note.
 */
( function () {
	var settings = window.wc.wcSettings.getSetting( 'hexxsy_stripe_data', {} );
	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities.decodeEntities;
	var label = decode( settings.title || 'Card' );

	var Content = function () {
		return el( 'div', null, decode( settings.description || '' ) );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'hexxsy_stripe',
		label: el( 'span', null, label ),
		ariaLabel: label,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: { features: settings.supports || [ 'products' ] },
	} );
} )();
