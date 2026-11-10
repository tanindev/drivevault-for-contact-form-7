import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';

/**
 * Copy to Clipboard Button with animated feedback.
 */
export const CopyButton = ( {
	text,
	label = __( 'Copy URI', 'drivevault-for-contact-form-7' ),
	className = '',
} ) => {
	const [ copied, setCopied ] = useState( false );

	const handleCopy = () => {
		if ( ! text ) return;
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text );
		} else {
			const el = document.createElement( 'textarea' );
			el.value = text;
			document.body.appendChild( el );
			el.select();
			document.execCommand( 'copy' );
			document.body.removeChild( el );
		}
		setCopied( true );
		setTimeout( () => setCopied( false ), 2000 );
	};

	return (
		<button
			type="button"
			className={ `drivevault-btn drivevault-btn--secondary drivevault-btn--copy ${
				copied ? 'is-copied' : ''
			} ${ className }` }
			onClick={ handleCopy }
			title={
				copied
					? __(
							'Copied to clipboard!',
							'drivevault-for-contact-form-7'
					  )
					: __( 'Click to copy', 'drivevault-for-contact-form-7' )
			}
		>
			<Icon name={ copied ? 'check' : 'content_copy' } size={ 16 } />
			<span>
				{ copied
					? __( 'Copied!', 'drivevault-for-contact-form-7' )
					: label }
			</span>
		</button>
	);
};

export default CopyButton;
