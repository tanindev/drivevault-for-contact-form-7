import React, { useEffect } from 'react';

export const Modal = ( { title, children, onClose, footer } ) => {
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', handleKeyDown );
		return () => window.removeEventListener( 'keydown', handleKeyDown );
	}, [ onClose ] );

	return (
		<div className="drivevault-modal-overlay" onClick={ onClose }>
			<div
				className="drivevault-modal"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<div className="drivevault-modal-header">
					<h3>{ title }</h3>
					<button
						type="button"
						className="drivevault-modal-close-btn"
						onClick={ onClose }
					>
						&times;
					</button>
				</div>
				<div className="drivevault-modal-body">{ children }</div>
				{ footer && (
					<div
						className="drivevault-modal-footer"
						style={ {
							padding: '14px 22px',
							borderTop: '1px solid #e2e8f0',
							background: '#f8fafc',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: 10,
						} }
					>
						{ footer }
					</div>
				) }
			</div>
		</div>
	);
};

export default Modal;
