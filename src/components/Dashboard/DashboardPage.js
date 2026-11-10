import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';

/**
 * Dashboard & KPI Stats Overview.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const DashboardPage = ( { onNavigate, isConnected, createToast } ) => {
	const [ stats, setStats ] = useState( {
		total_syncs: 0,
		successful: 0,
		failed: 0,
		success_rate: 100,
		active_forms: 0,
	} );
	const [ recentLogs, setRecentLogs ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		const loadDashboardData = async () => {
			try {
				const [ statsRes, logsRes ] = await Promise.all( [
					apiFetch( { path: 'drivevault-cf7/v1/logs/stats' } ),
					apiFetch( { path: 'drivevault-cf7/v1/logs?per_page=5' } ),
				] );
				if ( statsRes ) setStats( statsRes );
				if ( logsRes && logsRes.items ) setRecentLogs( logsRes.items );
			} catch ( err ) {
				// handled silently
			} finally {
				setIsLoading( false );
			}
		};
		loadDashboardData();
	}, [] );

	return (
		<div className="drivevault-dashboard-page">
			{ /* Warning Banner if Not Connected */ }
			{ ! isConnected && (
				<div
					className="drivevault-ui-card drivevault-auth-card"
					style={ { marginBottom: 24 } }
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
								<Icon
									name="warning"
									size={ 28 }
									color="#b06000"
								/>
							</div>
							<div className="drivevault-auth-text">
								<h3>
									{ __(
										'Google Account Not Connected',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p className="drivevault-auth-desc">
									{ __(
										'Connect your Google account via OAuth 2.0 to begin syncing Contact Form 7 submissions with Google Sheets and Google Drive.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
						<div className="drivevault-auth-actions">
							<button
								type="button"
								className="drivevault-btn drivevault-btn--primary"
								onClick={ () => onNavigate( 'google' ) }
							>
								<Icon name="link" size={ 16 } />
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

			{ /* 4-Column KPI Stats Cards */ }
			<div className="drivevault-stat-grid">
				<div className="drivevault-stat-card">
					<div className="drivevault-stat-icon is-blue">
						<Icon name="sync_alt" size={ 22 } />
					</div>
					<div className="drivevault-stat-content">
						<span className="drivevault-stat-value">
							{ stats.total_syncs }
						</span>
						<span className="drivevault-stat-label">
							{ __(
								'Total Submissions Synced',
								'drivevault-for-contact-form-7'
							) }
						</span>
					</div>
				</div>

				<div className="drivevault-stat-card">
					<div className="drivevault-stat-icon is-green">
						<Icon name="check_circle" size={ 22 } />
					</div>
					<div className="drivevault-stat-content">
						<span className="drivevault-stat-value">
							{ stats.success_rate }%
						</span>
						<span className="drivevault-stat-label">
							{ __(
								'Sync Success Rate',
								'drivevault-for-contact-form-7'
							) }
						</span>
					</div>
				</div>

				<div className="drivevault-stat-card">
					<div className="drivevault-stat-icon is-purple">
						<Icon name="dynamic_feed" size={ 22 } />
					</div>
					<div className="drivevault-stat-content">
						<span className="drivevault-stat-value">
							{ stats.active_forms }
						</span>
						<span className="drivevault-stat-label">
							{ __(
								'Active Form Feeds',
								'drivevault-for-contact-form-7'
							) }
						</span>
					</div>
				</div>

				<div className="drivevault-stat-card">
					<div className="drivevault-stat-icon is-orange">
						<Icon name="cloud_queue" size={ 22 } />
					</div>
					<div className="drivevault-stat-content">
						<span className="drivevault-stat-value">
							{ isConnected
								? __(
										'Active',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'Inactive',
										'drivevault-for-contact-form-7'
								  ) }
						</span>
						<span className="drivevault-stat-label">
							{ __(
								'Google API Connection',
								'drivevault-for-contact-form-7'
							) }
						</span>
					</div>
				</div>
			</div>

			{ /* Recent Submission Activity Card */ }
			<div className="drivevault-card" style={ { marginTop: 24 } }>
				<div className="drivevault-card-header">
					<div>
						<h3>
							<Icon name="history" size={ 20 } color="#1a73e8" />
							{ __(
								'Recent Submission Activity',
								'drivevault-for-contact-form-7'
							) }
						</h3>
						<p>
							{ __(
								'Latest form submissions and real-time Google Sheets dispatch results.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>
					<button
						type="button"
						className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
						onClick={ () => onNavigate( 'logs' ) }
					>
						<span>
							{ __(
								'View All Logs',
								'drivevault-for-contact-form-7'
							) }
						</span>
						<Icon name="arrow_forward" size={ 14 } />
					</button>
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
								'Loading activity data...',
								'drivevault-for-contact-form-7'
							) }
						</div>
					) : recentLogs.length === 0 ? (
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
								<Icon name="dynamic_feed" size={ 24 } />
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
									'No form submissions recorded yet.',
									'drivevault-for-contact-form-7'
								) }
							</h4>
							<p
								style={ {
									margin: '0 0 18px 0',
									fontSize: 13,
									color: '#64748b',
								} }
							>
								{ __(
									'Submissions from mapped Contact Form 7 forms will automatically stream here and write to your Google Sheets.',
									'drivevault-for-contact-form-7'
								) }
							</p>
							<button
								type="button"
								className="drivevault-btn drivevault-btn--primary drivevault-btn--sm"
								onClick={ () => onNavigate( 'feeds' ) }
							>
								<Icon name="add" size={ 16 } />
								<span>
									{ __(
										'Configure a Form Feed',
										'drivevault-for-contact-form-7'
									) }
								</span>
							</button>
						</div>
					) : (
						<div className="drivevault-table-wrapper">
							<table className="drivevault-table">
								<thead>
									<tr>
										<th>
											{ __(
												'Form / Date',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Spreadsheet & Sheet',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Attachments',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Status',
												'drivevault-for-contact-form-7'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ recentLogs.map( ( item ) => (
										<tr key={ item.id }>
											<td>
												<strong
													style={ {
														color: '#0f172a',
													} }
												>
													{ item.form_title ||
														sprintf(
															__(
																'Form #%d',
																'drivevault-for-contact-form-7'
															),
															item.form_id
														) }
												</strong>
												<div
													style={ {
														fontSize: 12,
														color: '#94a3b8',
													} }
												>
													{ item.created_at }
												</div>
											</td>
											<td>
												{ item.spreadsheet_title ||
													item.sheet_name ||
													'—' }
												{ item.tab_name && (
													<span
														style={ {
															color: '#64748b',
															fontSize: 12,
														} }
													>
														{ ' ' }
														({ item.tab_name })
													</span>
												) }
											</td>
											<td>
												{ ( item.drive_files &&
													item.drive_files.length >
														0 ) ||
												( item.uploaded_files &&
													item.uploaded_files.length >
														0 ) ? (
													<span className="drivevault-badge drivevault-badge--info">
														{
															(
																item.drive_files ||
																item.uploaded_files
															).length
														}{ ' ' }
														{ __(
															'files uploaded',
															'drivevault-for-contact-form-7'
														) }
													</span>
												) : (
													<span
														style={ {
															color: '#94a3b8',
															fontSize: 12,
														} }
													>
														—
													</span>
												) }
											</td>
											<td>
												{ ( () => {
													const isSuccess =
														item.result ===
															'success' ||
														item.status ===
															'success';
													return (
														<span
															className={ `drivevault-badge ${
																isSuccess
																	? 'drivevault-badge--success'
																	: 'drivevault-badge--danger'
															}` }
														>
															{ isSuccess
																? __(
																		'Success',
																		'drivevault-for-contact-form-7'
																  )
																: __(
																		'Failed',
																		'drivevault-for-contact-form-7'
																  ) }
														</span>
													);
												} )() }
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

export default DashboardPage;
