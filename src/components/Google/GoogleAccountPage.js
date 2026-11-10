import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';
import CopyButton from '../common/CopyButton';

/**
 * Google Account & Cloud API Credentials Page.
 * Matches exact SaaS UI design from DriveVault Cloud Storage tab.
 */
export const GoogleAccountPage = ( {
	account,
	isConnected,
	createToast,
	onAccountUpdated,
} ) => {
	const [ clientId, setClientId ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState( '' );
	const [ redirectUri, setRedirectUri ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isConnecting, setIsConnecting ] = useState( false );
	const [ isTesting, setIsTesting ] = useState( false );
	const [ showGuide, setShowGuide ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'drivevault-cf7/v1/settings' } )
			.then( ( res ) => {
				if ( res ) {
					setClientId( res.client_id || '' );
					setClientSecret( res.client_secret || '' );
				}
			} )
			.catch( () => {} );

		apiFetch( { path: 'drivevault-cf7/v1/auth/status' } )
			.then( ( res ) => {
				if ( res && res.redirectUri ) {
					setRedirectUri( res.redirectUri );
				}
			} )
			.catch( () => {} );
	}, [] );

	const handleSaveCredentials = async () => {
		setIsSaving( true );
		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/settings',
				method: 'POST',
				data: {
					client_id: clientId,
					client_secret: clientSecret,
				},
			} );
			createToast(
				__(
					'Google Cloud credentials saved successfully.',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to save credentials.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	};

	const handleConnect = async () => {
		if ( ! clientId.trim() || ! clientSecret.trim() ) {
			createToast(
				__(
					'Please enter your Google Client ID and Client Secret first.',
					'drivevault-for-contact-form-7'
				),
				'error'
			);
			return;
		}

		setIsConnecting( true );
		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/settings',
				method: 'POST',
				data: {
					client_id: clientId,
					client_secret: clientSecret,
				},
			} );

			const res = await apiFetch( {
				path: 'drivevault-cf7/v1/auth/url',
			} );
			if ( res && res.url ) {
				window.location.href = res.url;
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Could not generate Google authorization link.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
			setIsConnecting( false );
		}
	};

	const handleTest = async () => {
		setIsTesting( true );
		try {
			const res = await apiFetch( {
				path: 'drivevault-cf7/v1/auth/test',
				method: 'POST',
			} );
			if ( res && res.success ) {
				createToast(
					__(
						'Google API connection verified successfully! Google Sheets and Drive are ready.',
						'drivevault-for-contact-form-7'
					),
					'success'
				);
				if ( onAccountUpdated ) onAccountUpdated();
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Connection test failed.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsTesting( false );
		}
	};

	const handleDisconnect = async () => {
		if (
			! window.confirm(
				__(
					'Are you sure you want to disconnect your Google account?',
					'drivevault-for-contact-form-7'
				)
			)
		) {
			return;
		}

		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/auth/disconnect',
				method: 'POST',
			} );
			createToast(
				__(
					'Google account disconnected successfully.',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
			if ( onAccountUpdated ) onAccountUpdated();
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to disconnect.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		}
	};

	return (
		<div className="drivevault-google-page">
			{ /* Provider Cards (Google Sheets & Google Drive) */ }
			<div
				className="drivevault-provider-subtabs-grid"
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
					gap: 14,
					marginBottom: 24,
				} }
			>
				<div
					style={ {
						padding: '16px 20px',
						borderRadius: 10,
						border: '2px solid #1a73e8',
						background: '#ffffff',
						display: 'flex',
						alignItems: 'center',
						gap: 14,
						boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
					} }
				>
					<div
						style={ {
							width: 44,
							height: 44,
							borderRadius: 10,
							background: '#e6f4ea',
							color: '#137333',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							flexShrink: 0,
						} }
					>
						<Icon name="table_view" size={ 24 } />
					</div>
					<div>
						<div
							style={ {
								fontWeight: 600,
								fontSize: 14,
								color: '#0f172a',
							} }
						>
							Google Sheets
						</div>
						<div
							style={ {
								fontSize: 12,
								color: isConnected ? '#137333' : '#64748b',
								fontWeight: 600,
								textTransform: 'uppercase',
								letterSpacing: '0.04em',
								marginTop: 2,
							} }
						>
							{ isConnected
								? __(
										'ACTIVE & SYNCING',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'READY TO CONNECT',
										'drivevault-for-contact-form-7'
								  ) }
						</div>
					</div>
				</div>

				<div
					style={ {
						padding: '16px 20px',
						borderRadius: 10,
						border: '2px solid #1a73e8',
						background: '#ffffff',
						display: 'flex',
						alignItems: 'center',
						gap: 14,
						boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
					} }
				>
					<div
						style={ {
							width: 44,
							height: 44,
							borderRadius: 10,
							background: '#e8f0fe',
							color: '#1a73e8',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							flexShrink: 0,
						} }
					>
						<Icon name="cloud_queue" size={ 24 } />
					</div>
					<div>
						<div
							style={ {
								fontWeight: 600,
								fontSize: 14,
								color: '#0f172a',
							} }
						>
							Google Drive
						</div>
						<div
							style={ {
								fontSize: 12,
								color: isConnected ? '#137333' : '#64748b',
								fontWeight: 600,
								textTransform: 'uppercase',
								letterSpacing: '0.04em',
								marginTop: 2,
							} }
						>
							{ isConnected
								? __(
										'ACTIVE & READY',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'READY TO CONNECT',
										'drivevault-for-contact-form-7'
								  ) }
						</div>
					</div>
				</div>
			</div>

			{ /* Modern Connection Banner Card */ }
			<div
				className={ `drivevault-ui-card drivevault-auth-card ${
					isConnected ? 'is-connected' : ''
				}` }
				style={ { marginBottom: 24 } }
			>
				<div className="drivevault-auth-banner">
					<div className="drivevault-auth-info">
						<div className="drivevault-auth-logo">
							<Icon
								name="cloud_queue"
								size={ 32 }
								color={ isConnected ? '#137333' : '#1a73e8' }
							/>
						</div>
						<div className="drivevault-auth-text">
							<h3>
								{ __(
									'Google Account Connection',
									'drivevault-for-contact-form-7'
								) }
							</h3>
							{ isConnected ? (
								<p className="drivevault-connected-email">
									{ __(
										'Connected Account:',
										'drivevault-for-contact-form-7'
									) }{ ' ' }
									<strong className="drivevault-email-tag">
										{ account?.email ||
											account?.name ||
											__(
												'Active Account',
												'drivevault-for-contact-form-7'
											) }
									</strong>
								</p>
							) : (
								<p className="drivevault-auth-desc">
									{ __(
										'Connect your Google Cloud project to allow automated Contact Form 7 spreadsheet streaming and file uploads storage.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							) }
						</div>
					</div>

					<div
						className="drivevault-auth-actions"
						style={ { display: 'flex', gap: 10 } }
					>
						{ isConnected ? (
							<>
								<button
									type="button"
									className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
									onClick={ handleTest }
									disabled={ isTesting }
								>
									<Icon
										name="check_circle"
										size={ 14 }
										color="#137333"
									/>
									<span>
										{ isTesting
											? __(
													'Testing...',
													'drivevault-for-contact-form-7'
											  )
											: __(
													'Test Connection',
													'drivevault-for-contact-form-7'
											  ) }
									</span>
								</button>
								<button
									type="button"
									className="drivevault-btn drivevault-btn--danger-outline drivevault-btn--sm"
									onClick={ handleDisconnect }
								>
									<Icon name="link_off" size={ 14 } />
									<span>
										{ __(
											'Disconnect',
											'drivevault-for-contact-form-7'
										) }
									</span>
								</button>
							</>
						) : (
							<button
								type="button"
								className="drivevault-btn drivevault-btn--primary"
								onClick={ handleConnect }
								disabled={ isConnecting }
							>
								<Icon name="link" size={ 16 } />
								<span>
									{ isConnecting
										? __(
												'Connecting...',
												'drivevault-for-contact-form-7'
										  )
										: __(
												'Authorize & Connect Google Account',
												'drivevault-for-contact-form-7'
										  ) }
								</span>
							</button>
						) }
					</div>
				</div>
			</div>

			{ /* Google Cloud API Credentials Card */ }
			<div className="drivevault-card">
				<div className="drivevault-card-header">
					<div>
						<h3>
							<Icon name="vpn_key" size={ 20 } color="#1a73e8" />
							{ __(
								'Google Cloud API Credentials',
								'drivevault-for-contact-form-7'
							) }
						</h3>
						<p>
							{ __(
								'Configure OAuth 2.0 Client credentials from your Google Cloud Console project.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<button
						type="button"
						className="drivevault-guide-toggle-btn"
						onClick={ () => setShowGuide( ! showGuide ) }
						style={ {
							background: 'none',
							border: 'none',
							color: '#1a73e8',
							cursor: 'pointer',
							fontSize: 13,
							fontWeight: 500,
							display: 'flex',
							alignItems: 'center',
							gap: 5,
						} }
					>
						<Icon name="help_outline" size={ 16 } />
						<span>
							{ showGuide
								? __(
										'Hide Setup Guide',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'View Setup Guide',
										'drivevault-for-contact-form-7'
								  ) }
						</span>
					</button>
				</div>

				<div className="drivevault-card-body">
					{ showGuide && (
						<div
							style={ {
								background: '#f8fafc',
								border: '1px solid #e2e8f0',
								borderRadius: 8,
								padding: '16px 20px',
								marginBottom: 20,
								fontSize: 13,
								lineHeight: 1.6,
								color: '#334155',
							} }
						>
							<h4
								style={ {
									margin: '0 0 10px 0',
									fontSize: 14,
									fontWeight: 600,
									color: '#0f172a',
								} }
							>
								{ __(
									'How to create your Google Cloud OAuth Credentials:',
									'drivevault-for-contact-form-7'
								) }
							</h4>
							<ol style={ { margin: 0, paddingLeft: 20 } }>
								<li>
									Go to the{ ' ' }
									<a
										href="https://console.cloud.google.com/"
										target="_blank"
										rel="noreferrer"
										style={ {
											color: '#1a73e8',
											fontWeight: 500,
										} }
									>
										Google Cloud Console
									</a>{ ' ' }
									and create a new project.
								</li>
								<li>
									Enable the{ ' ' }
									<strong>Google Sheets API</strong> and{ ' ' }
									<strong>Google Drive API</strong> in{ ' ' }
									<em>APIs & Services &gt; Library</em>.
								</li>
								<li>
									Configure the{ ' ' }
									<strong>OAuth Consent Screen</strong> (User
									type: <em>External</em>). Add scopes:{ ' ' }
									<code>.../auth/spreadsheets</code> and{ ' ' }
									<code>.../auth/drive.file</code>.
								</li>
								<li>
									Go to{ ' ' }
									<em>APIs & Services &gt; Credentials</em>,
									click{ ' ' }
									<strong>
										Create Credentials &gt; OAuth Client ID
									</strong>
									.
								</li>
								<li>
									Select Application type:{ ' ' }
									<strong>Web application</strong>. Paste the{ ' ' }
									<em>Authorized Redirect URI</em> below into
									the redirect URIs field.
								</li>
								<li>
									Copy your generated{ ' ' }
									<strong>Client ID</strong> and{ ' ' }
									<strong>Client Secret</strong> into the
									fields below and click{ ' ' }
									<strong>Save Credentials</strong>.
								</li>
							</ol>
						</div>
					) }

					<div className="drivevault-form-group">
						<label>
							{ __(
								'Google Client ID',
								'drivevault-for-contact-form-7'
							) }
							<span style={ { color: '#d93025' } }>*</span>
						</label>
						<input
							type="text"
							className="drivevault-input"
							placeholder="e.g. 1234567890-abcdef.apps.googleusercontent.com"
							value={ clientId }
							onChange={ ( e ) => setClientId( e.target.value ) }
						/>
						<p className="drivevault-field-help">
							{ __(
								'Your OAuth 2.0 Client ID generated in Google Cloud Console.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<div
						className="drivevault-form-group"
						style={ { marginTop: 18 } }
					>
						<label>
							{ __(
								'Google Client Secret',
								'drivevault-for-contact-form-7'
							) }
							<span style={ { color: '#d93025' } }>*</span>
						</label>
						<input
							type="password"
							className="drivevault-input"
							placeholder="••••••••••••••••••••••••"
							value={ clientSecret }
							onChange={ ( e ) =>
								setClientSecret( e.target.value )
							}
						/>
						<p className="drivevault-field-help">
							{ __(
								'Your OAuth 2.0 Client Secret generated in Google Cloud Console. Stored securely with AES-256 encryption.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<div
						className="drivevault-form-group"
						style={ { marginTop: 18 } }
					>
						<label>
							{ __(
								'Authorized Redirect URI',
								'drivevault-for-contact-form-7'
							) }
						</label>
						<div style={ { display: 'flex', gap: 8 } }>
							<input
								type="text"
								className="drivevault-input"
								readOnly
								value={
									redirectUri ||
									window.location.origin +
										window.location.pathname +
										'?page=drivevault-cf7'
								}
								style={ {
									background: '#f8fafc',
									color: '#334155',
								} }
							/>
							<CopyButton
								text={
									redirectUri ||
									window.location.origin +
										window.location.pathname +
										'?page=drivevault-cf7'
								}
							/>
						</div>
						<p className="drivevault-field-help">
							{ __(
								'Must match the Authorized redirect URI in your Google Cloud Console credentials.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<div style={ { marginTop: 24, display: 'flex', gap: 10 } }>
						<button
							type="button"
							className="drivevault-btn drivevault-btn--primary"
							onClick={ handleSaveCredentials }
							disabled={ isSaving }
						>
							<Icon name="save" size={ 16 } />
							<span>
								{ isSaving
									? __(
											'Saving...',
											'drivevault-for-contact-form-7'
									  )
									: __(
											'Save Credentials',
											'drivevault-for-contact-form-7'
									  ) }
							</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default GoogleAccountPage;
