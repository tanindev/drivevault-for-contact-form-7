import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';
import FeedEditor from './FeedEditor';

/**
 * Feeds Management Page.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const FeedsPage = ( { createToast, isConnected, onNavigate } ) => {
	const [ forms, setForms ] = useState( [] );
	const [ editingFormId, setEditingFormId ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	const loadForms = async () => {
		setIsLoading( true );
		try {
			const res = await apiFetch( { path: 'drivevault-cf7/v1/forms' } );
			setForms( res || [] );
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to load forms.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsLoading( false );
		}
	};

	useEffect( () => {
		loadForms();
	}, [] );

	const handleToggleStatus = async ( formId ) => {
		try {
			const mapping = await apiFetch( {
				path: `drivevault-cf7/v1/mappings/${ formId }`,
			} );
			if ( mapping && mapping.id ) {
				const toggled = await apiFetch( {
					path: `drivevault-cf7/v1/mappings/${ mapping.id }/toggle`,
					method: 'POST',
				} );
				if ( toggled && toggled.success ) {
					createToast( toggled.message, 'success' );
					loadForms();
				}
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to toggle status.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		}
	};

	if ( editingFormId ) {
		return (
			<FeedEditor
				formId={ editingFormId }
				isConnected={ isConnected }
				onNavigate={ onNavigate }
				onBack={ () => {
					setEditingFormId( null );
					loadForms();
				} }
				onSaved={ () => {
					setEditingFormId( null );
					loadForms();
				} }
				createToast={ createToast }
			/>
		);
	}

	return (
		<div className="drivevault-feeds-page">
			{ /* Warning Banner if Not Connected */ }
			{ ! isConnected && (
				<div
					className="drivevault-ui-card drivevault-auth-card"
					style={ { marginBottom: 20 } }
				>
					<div className="drivevault-auth-banner">
						<div className="drivevault-auth-info">
							<div
								className="drivevault-auth-logo"
								style={ {
									background: '#fef7e0',
									color: '#b06000',
								} }
							>
								<Icon name="info" size={ 26 } color="#b06000" />
							</div>
							<div className="drivevault-auth-text">
								<h3 style={ { fontSize: 14 } }>
									{ __(
										'Google Account Required',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p className="drivevault-auth-desc">
									{ __(
										'Connect your Google Account first to allow Contact Form 7 to create spreadsheets and stream submissions in real time.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
						<div className="drivevault-auth-actions">
							<button
								type="button"
								className="drivevault-btn drivevault-btn--primary drivevault-btn--sm"
								onClick={ () => onNavigate( 'google' ) }
							>
								<span>
									{ __(
										'Connect Google Account',
										'drivevault-for-contact-form-7'
									) }
								</span>
							</button>
						</div>
					</div>
				</div>
			) }

			{ /* Feeds Table Card */ }
			<div className="drivevault-card">
				<div className="drivevault-card-header">
					<div>
						<h3>
							<Icon
								name="dynamic_feed"
								size={ 20 }
								color="#1a73e8"
							/>
							{ __(
								'Contact Form 7 Feeds & Mappings',
								'drivevault-for-contact-form-7'
							) }
						</h3>
						<p>
							{ __(
								'Connect each Contact Form 7 form to a dedicated Google Sheet and Google Drive folder.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<div style={ { display: 'flex', gap: 10 } }>
						<button
							type="button"
							className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
							style={ { height: 36, minHeight: 36 } }
							onClick={ loadForms }
							title={ __(
								'Refresh forms list',
								'drivevault-for-contact-form-7'
							) }
						>
							<Icon name="refresh" size={ 14 } />
							<span>
								{ __(
									'Refresh',
									'drivevault-for-contact-form-7'
								) }
							</span>
						</button>
					</div>
				</div>

				<div className="drivevault-card-body" style={ { padding: 0 } }>
					{ isLoading ? (
						<div
							style={ {
								padding: 40,
								textAlign: 'center',
								color: '#64748b',
							} }
						>
							{ __(
								'Loading forms...',
								'drivevault-for-contact-form-7'
							) }
						</div>
					) : forms.length === 0 ? (
						<div
							style={ {
								textAlign: 'center',
								padding: '50px 20px',
								background: '#f8fafc',
							} }
						>
							<div
								style={ {
									width: 52,
									height: 52,
									borderRadius: '50%',
									background: '#e2e8f0',
									display: 'inline-flex',
									alignItems: 'center',
									justifyContent: 'center',
									color: '#64748b',
									marginBottom: 14,
								} }
							>
								<Icon name="assignment" size={ 26 } />
							</div>
							<h4
								style={ {
									margin: '0 0 6px 0',
									fontSize: 15,
									fontWeight: 600,
									color: '#1e293b',
								} }
							>
								{ __(
									'No Contact Form 7 forms found.',
									'drivevault-for-contact-form-7'
								) }
							</h4>
							<p
								style={ {
									margin: '0 0 16px 0',
									fontSize: 13,
									color: '#64748b',
								} }
							>
								{ __(
									'Create a form in Contact Form 7 first, then come back here to map it to Google Sheets and Drive.',
									'drivevault-for-contact-form-7'
								) }
							</p>
							<a
								href="admin.php?page=wpcf7-new"
								className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
							>
								<Icon name="add" size={ 14 } />
								<span>
									{ __(
										'Create Form in Contact Form 7',
										'drivevault-for-contact-form-7'
									) }
								</span>
							</a>
						</div>
					) : (
						<div className="drivevault-table-wrapper">
							<table className="drivevault-table">
								<thead>
									<tr>
										<th>
											{ __(
												'Form Title / ID',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Target Google Sheet',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Target Drive Folder',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Sync Status',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Last Submitted',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th style={ { textAlign: 'right' } }>
											{ __(
												'Actions',
												'drivevault-for-contact-form-7'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ forms.map( ( form ) => (
										<tr key={ form.id }>
											<td>
												<div
													style={ {
														fontWeight: 600,
														color: '#0f172a',
													} }
												>
													{ form.title }
												</div>
												<div
													style={ {
														fontSize: 12,
														color: '#94a3b8',
													} }
												>
													ID: #{ form.id }
												</div>
											</td>
											<td>
												{ form.sheet_name ? (
													<div>
														<div
															style={ {
																fontWeight: 500,
																color: '#1e293b',
															} }
														>
															{ form.sheet_name }
														</div>
														<div
															style={ {
																fontSize: 12,
																color: '#64748b',
															} }
														>
															{ __(
																'Worksheet:',
																'drivevault-for-contact-form-7'
															) }{ ' ' }
															{ form.tab_name ||
																'Sheet1' }
														</div>
													</div>
												) : (
													<span
														style={ {
															color: '#94a3b8',
															fontStyle: 'italic',
															fontSize: 13,
														} }
													>
														{ __(
															'Not configured',
															'drivevault-for-contact-form-7'
														) }
													</span>
												) }
											</td>
											<td>
												{ form.drive_folder_name ? (
													<span
														style={ {
															color: '#334155',
															fontWeight: 500,
														} }
													>
														{
															form.drive_folder_name
														}
													</span>
												) : (
													<span
														style={ {
															color: '#94a3b8',
															fontSize: 12,
														} }
													>
														{ __(
															'Auto / Root Folder',
															'drivevault-for-contact-form-7'
														) }
													</span>
												) }
											</td>
											<td>
												{ form.has_mapping ? (
													<button
														type="button"
														className={ `drivevault-badge ${
															form.is_active
																? 'drivevault-badge--success'
																: 'drivevault-badge--warning'
														}` }
														style={ {
															cursor: 'pointer',
															border: 'none',
														} }
														onClick={ () =>
															handleToggleStatus(
																form.id
															)
														}
														title={ __(
															'Click to toggle active sync state',
															'drivevault-for-contact-form-7'
														) }
													>
														{ form.is_active
															? __(
																	'Active',
																	'drivevault-for-contact-form-7'
															  )
															: __(
																	'Paused',
																	'drivevault-for-contact-form-7'
															  ) }
													</button>
												) : (
													<span className="drivevault-badge drivevault-badge--neutral">
														{ __(
															'Unmapped',
															'drivevault-for-contact-form-7'
														) }
													</span>
												) }
											</td>
											<td>
												<span
													style={ {
														fontSize: 13,
														color: form.last_submitted_at
															? '#334155'
															: '#94a3b8',
													} }
												>
													{ form.last_submitted_at ||
														'—' }
												</span>
											</td>
											<td
												style={ { textAlign: 'right' } }
											>
												<button
													type="button"
													className={ `drivevault-btn drivevault-btn--sm ${
														isConnected
															? 'drivevault-btn--primary'
															: 'drivevault-btn--secondary'
													}` }
													disabled={ ! isConnected }
													onClick={ () => {
														if ( isConnected ) {
															setEditingFormId(
																form.id
															);
														}
													} }
													title={
														! isConnected
															? __(
																	'Connect your Google Account first to configure feeds.',
																	'drivevault-for-contact-form-7'
															  )
															: ''
													}
													style={
														! isConnected
															? {
																	opacity: 0.55,
																	cursor: 'not-allowed',
																	height: 34,
																	minHeight: 34,
															  }
															: {
																	height: 34,
																	minHeight: 34,
															  }
													}
												>
													<Icon
														name={
															isConnected
																? 'tune'
																: 'lock'
														}
														size={ 14 }
													/>
													<span>
														{ form.has_mapping
															? __(
																	'Edit Mapping',
																	'drivevault-for-contact-form-7'
															  )
															: __(
																	'Configure Feed',
																	'drivevault-for-contact-form-7'
															  ) }
													</span>
												</button>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default FeedsPage;
