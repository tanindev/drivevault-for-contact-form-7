import React from 'react';
import { render, createRoot } from '@wordpress/element';
import App from './app';
import './scss/admin.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'drivevault-cf7-app' );
	if ( ! container ) {
		return;
	}

	if ( createRoot ) {
		createRoot( container ).render( <App /> );
	} else if ( render ) {
		render( <App />, container );
	}
} );
