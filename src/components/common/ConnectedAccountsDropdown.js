import React, { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';

/**
 * Top Header Connected Accounts Status & Dropdown Menu.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const ConnectedAccountsDropdown = ( {
	isConnected,
	account,
	onNavigate,
	onDisconnect,
} ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ imgError, setImgError ] = useState( false );
	const dropdownRef = useRef( null );

	// Close on click outside & Escape key
	useEffect( () => {
		const handleClickOutside = ( e ) => {
			if (
				dropdownRef.current &&
				! dropdownRef.current.contains( e.target )
			) {
				setIsOpen( false );
			}
		};

		const handleKeyDown = ( e ) => {
			if ( e.key === 'Escape' ) {
				setIsOpen( false );
			}
		};

		if ( isOpen ) {
			document.addEventListener( 'mousedown', handleClickOutside );
			document.addEventListener( 'keydown', handleKeyDown );
		}

		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ isOpen ] );

	const userInitial = (
		account?.name?.charAt( 0 ) ||
		account?.email?.charAt( 0 ) ||
		'G'
	).toUpperCase();

	return (
		<div className="drivevault-account-menu-wrapper" ref={ dropdownRef }>
			{ /* Avatar / Connection Trigger Button */ }
			<button
				type="button"
				className={ `drivevault-account-trigger-btn ${
					isOpen ? 'is-active' : ''
				} ${ isConnected ? 'is-connected' : 'is-disconnected' }` }
				onClick={ () => setIsOpen( ! isOpen ) }
				aria-expanded={ isOpen }
				aria-haspopup="true"
				title={ __(
					'Google Cloud Accounts Status',
					'drivevault-for-contact-form-7'
				) }
			>
				<div className="drivevault-avatar-badge-wrap">
					{ account?.picture && ! imgError ? (
						<img
							src={ account.picture }
							alt={ account.name || 'Google' }
							className="drivevault-avatar-img"
							onError={ () => setImgError( true ) }
						/>
					) : (
						<div className="drivevault-avatar-fallback">
							{ isConnected ? (
								userInitial
							) : (
								<Icon
									name="cloud_queue"
									size={ 18 }
									color="#fff"
								/>
							) }
						</div>
					) }
					<span
						className={ `drivevault-avatar-status-dot ${
							isConnected ? 'is-online' : 'is-offline'
						}` }
					/>
				</div>

				<div className="drivevault-account-trigger-meta">
					<span className="drivevault-trigger-label">
						{ isConnected
							? 'GOOGLE ACCOUNT'
							: __(
									'Cloud Accounts',
									'drivevault-for-contact-form-7'
							  ) }
					</span>
					<span className="drivevault-trigger-status">
						{ isConnected
							? __( 'Connected', 'drivevault-for-contact-form-7' )
							: __(
									'Not Connected',
									'drivevault-for-contact-form-7'
							  ) }
					</span>
				</div>

				<Icon
					name={ isOpen ? 'expand_less' : 'expand_more' }
					size={ 18 }
					className="drivevault-trigger-chevron"
				/>
			</button>

			{ /* Floating Dropdown */ }
			{ isOpen && (
				<div className="drivevault-accounts-dropdown">
					{ /* Header */ }
					<div className="drivevault-accounts-dropdown__header">
						<div className="drivevault-dropdown-user-info">
							{ account?.picture && ! imgError ? (
								<img
									src={ account.picture }
									alt=""
									className="drivevault-dropdown-avatar"
								/>
							) : (
								<div className="drivevault-dropdown-avatar-fallback">
									{ userInitial }
								</div>
							) }
							<div>
								<h4 className="drivevault-user-name">
									{ account?.name ||
										( isConnected
											? __(
													'Google Cloud Workspace',
													'drivevault-for-contact-form-7'
											  )
											: __(
													'Google Account',
													'drivevault-for-contact-form-7'
											  ) ) }
								</h4>
								<p className="drivevault-user-email">
									{ account?.email ||
										( isConnected
											? __(
													'OAuth 2.0 Authenticated',
													'drivevault-for-contact-form-7'
											  )
											: __(
													'Not connected yet',
													'drivevault-for-contact-form-7'
											  ) ) }
								</p>
							</div>
						</div>
						<span
							className={ `drivevault-dropdown-count-badge ${
								isConnected ? 'is-connected' : ''
							}` }
						>
							{ isConnected
								? __(
										'Active (2 Services)',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'0 Connected',
										'drivevault-for-contact-form-7'
								  ) }
						</span>
					</div>

					{ /* Services List */ }
					<div className="drivevault-accounts-dropdown__list">
						{ /* Google Sheets */ }
						<div className="drivevault-dropdown-provider-item">
							<div
								className="drivevault-provider-icon-badge"
								style={ {
									background: '#e6f4ea',
									color: '#137333',
								} }
							>
								<Icon name="table_view" size={ 20 } />
							</div>
							<div className="drivevault-provider-info">
								<div className="drivevault-provider-title-row">
									<span className="drivevault-provider-name">
										Google Sheets
									</span>
									<span
										className={ `drivevault-pill ${
											isConnected
												? 'drivevault-pill--success'
												: 'drivevault-pill--neutral'
										}` }
									>
										{ isConnected
											? __(
													'Active & Syncing',
													'drivevault-for-contact-form-7'
											  )
											: __(
													'Ready to Connect',
													'drivevault-for-contact-form-7'
											  ) }
									</span>
								</div>
								<p className="drivevault-provider-desc">
									{ __(
										'Real-time Contact Form 7 submissions row streaming',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>

						{ /* Google Drive */ }
						<div className="drivevault-dropdown-provider-item">
							<div
								className="drivevault-provider-icon-badge"
								style={ {
									background: '#e8f0fe',
									color: '#1a73e8',
								} }
							>
								<Icon name="cloud_queue" size={ 20 } />
							</div>
							<div className="drivevault-provider-info">
								<div className="drivevault-provider-title-row">
									<span className="drivevault-provider-name">
										Google Drive
									</span>
									<span
										className={ `drivevault-pill ${
											isConnected
												? 'drivevault-pill--success'
												: 'drivevault-pill--neutral'
										}` }
									>
										{ isConnected
											? __(
													'Active & Ready',
													'drivevault-for-contact-form-7'
											  )
											: __(
													'Ready to Connect',
													'drivevault-for-contact-form-7'
											  ) }
									</span>
								</div>
								<p className="drivevault-provider-desc">
									{ __(
										'Secure attachment storage and organized folder routes',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
					</div>

					{ /* Dropdown Footer Actions */ }
					<div className="drivevault-accounts-dropdown__footer">
						<button
							type="button"
							className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
							onClick={ () => {
								setIsOpen( false );
								if ( onNavigate ) onNavigate( 'google' );
							} }
						>
							<Icon name="settings" size={ 14 } />
							<span>
								{ isConnected
									? __(
											'Manage Google Credentials',
											'drivevault-for-contact-form-7'
									  )
									: __(
											'Connect Google Account',
											'drivevault-for-contact-form-7'
									  ) }
							</span>
						</button>

						{ isConnected && onDisconnect && (
							<button
								type="button"
								className="drivevault-btn drivevault-btn--danger-outline drivevault-btn--sm"
								onClick={ () => {
									setIsOpen( false );
									onDisconnect();
								} }
							>
								<Icon name="link_off" size={ 14 } />
								<span>
									{ __(
										'Disconnect',
										'drivevault-for-contact-form-7'
									) }
								</span>
							</button>
						) }
					</div>
				</div>
			) }
		</div>
	);
};

export default ConnectedAccountsDropdown;
