import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';
import Modal from '../common/Modal';

/**
 * Submission & Activity Logs Tab.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const LogsPage = ( { createToast } ) => {
	const [ logs, setLogs ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ resultFilter, setResultFilter ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ activePayloadLog, setActivePayloadLog ] = useState( null );
	const [ isRetryingId, setIsRetryingId ] = useState( null );

	const loadLogs = async () => {
		setIsLoading( true );
		try {
			const query = new URLSearchParams( {
				page,
				per_page: 20,
				result: resultFilter,
				search,
			} ).toString();

			const res = await apiFetch( {
				path: `drivevault-cf7/v1/logs?${ query }`,
			} );
			if ( res ) {
				setLogs( res.items || [] );
				setTotal( res.total || 0 );
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to fetch logs.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsLoading( false );
		}
	};

	useEffect( () => {
		loadLogs();
	}, [ page, resultFilter ] );

	const handleSearchSubmit = ( e ) => {
		e.preventDefault();
		setPage( 1 );
		loadLogs();
	};

	const handleRetry = async ( logId ) => {
		setIsRetryingId( logId );
		try {
			const res = await apiFetch( {
				path: `drivevault-cf7/v1/logs/${ logId }/retry`,
				method: 'POST',
			} );
			if ( res && res.success ) {
				createToast( res.message, 'success' );
				loadLogs();
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__( 'Retry failed.', 'drivevault-for-contact-form-7' ),
				'error'
			);
		} finally {
			setIsRetryingId( null );
		}
	};

	const handleDelete = async ( logId ) => {
		try {
			await apiFetch( {
				path: `drivevault-cf7/v1/logs/${ logId }`,
				method: 'DELETE',
			} );
			createToast(
				__( 'Log entry deleted.', 'drivevault-for-contact-form-7' ),
				'success'
			);
			loadLogs();
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Could not delete log.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		}
	};

	const handleClearAll = async () => {
		if (
			! window.confirm(
				__(
					'Are you sure you want to clear all submission logs?',
					'drivevault-for-contact-form-7'
				)
			)
		) {
			return;
		}

		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/logs',
				method: 'DELETE',
			} );
			createToast(
				__(
					'All submission logs cleared.',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
			loadLogs();
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to clear logs.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		}
	};

	return (
		<div className="drivevault-logs-page">
			<div className="drivevault-card">
				<div className="drivevault-card-header">
					<div>
						<h3>
							<Icon name="history" size={ 20 } color="#1a73e8" />
							{ __(
								'Submission & Sync Activity Logs',
								'drivevault-for-contact-form-7'
							) }
						</h3>
						<p>
							{ __(
								'Audit trail of all Contact Form 7 submissions, Google Sheets sync, and Drive uploads.',
								'drivevault-for-contact-form-7'
							) }
						</p>
					</div>

					<div
						style={ {
							display: 'flex',
							gap: 10,
							alignItems: 'center',
						} }
					>
						<select
							className="drivevault-select"
							style={ { width: 140, height: 36, minHeight: 36 } }
							value={ resultFilter }
							onChange={ ( e ) => {
								setResultFilter( e.target.value );
								setPage( 1 );
							} }
						>
							<option value="">
								{ __(
									'All Statuses',
									'drivevault-for-contact-form-7'
								) }
							</option>
							<option value="success">
								{ __(
									'Success Only',
									'drivevault-for-contact-form-7'
								) }
							</option>
							<option value="error">
								{ __(
									'Errors Only',
									'drivevault-for-contact-form-7'
								) }
							</option>
						</select>

						<button
							type="button"
							className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
							style={ { height: 36, minHeight: 36 } }
							onClick={ loadLogs }
							title={ __(
								'Refresh logs',
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

						<button
							type="button"
							className="drivevault-btn drivevault-btn--danger-outline drivevault-btn--sm"
							style={ { height: 36, minHeight: 36 } }
							onClick={ handleClearAll }
							title={ __(
								'Clear all logs',
								'drivevault-for-contact-form-7'
							) }
						>
							<Icon name="delete_sweep" size={ 14 } />
							<span>
								{ __(
									'Clear All',
									'drivevault-for-contact-form-7'
								) }
							</span>
						</button>
					</div>
				</div>

				<div className="drivevault-card-body" style={ { padding: 0 } }>
					{ /* Filter & Search Bar */ }
					<div
						style={ {
							padding: '14px 20px',
							borderBottom: '1px solid #f1f5f9',
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							gap: 12,
						} }
					>
						<form
							onSubmit={ handleSearchSubmit }
							style={ {
								display: 'flex',
								gap: 8,
								flex: 1,
								maxWidth: 380,
							} }
						>
							<input
								type="text"
								className="drivevault-input"
								placeholder={ __(
									'Search by form, sheet, or error...',
									'drivevault-for-contact-form-7'
								) }
								value={ search }
								onChange={ ( e ) =>
									setSearch( e.target.value )
								}
								style={ { height: 36, minHeight: 36 } }
							/>
							<button
								type="submit"
								className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
								style={ { height: 36, minHeight: 36 } }
							>
								<Icon name="search" size={ 14 } />
								<span>
									{ __(
										'Search',
										'drivevault-for-contact-form-7'
									) }
								</span>
							</button>
						</form>

						<div style={ { fontSize: 13, color: '#64748b' } }>
							{ sprintf(
								/* translators: %1$d: total number of log entries */
								__(
									'Showing %1$d total entries',
									'drivevault-for-contact-form-7'
								),
								total
							) }
						</div>
					</div>

					{ isLoading ? (
						<div
							style={ {
								padding: 40,
								textAlign: 'center',
								color: '#64748b',
							} }
						>
							{ __(
								'Loading logs...',
								'drivevault-for-contact-form-7'
							) }
						</div>
					) : logs.length === 0 ? (
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
								<Icon name="history" size={ 26 } />
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
									'No matching submission logs found.',
									'drivevault-for-contact-form-7'
								) }
							</h4>
							<p
								style={ {
									margin: 0,
									fontSize: 13,
									color: '#64748b',
								} }
							>
								{ __(
									'Form submission events and synchronization status will appear here.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>
					) : (
						<div className="drivevault-table-wrapper">
							<table className="drivevault-table">
								<thead>
									<tr>
										<th>
											{ __(
												'Status',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Form Title / ID',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Submission Info',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Duration',
												'drivevault-for-contact-form-7'
											) }
										</th>
										<th>
											{ __(
												'Date & Time',
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
									{ logs.map( ( log ) => (
										<tr key={ log.id }>
											<td>
												<span
													className={ `drivevault-badge ${
														log.result === 'success'
															? 'drivevault-badge--success'
															: 'drivevault-badge--danger'
													}` }
												>
													{ log.result === 'success'
														? __(
																'Synced',
																'drivevault-for-contact-form-7'
														  )
														: __(
																'Failed',
																'drivevault-for-contact-form-7'
														  ) }
												</span>
											</td>
											<td>
												<div
													style={ {
														fontWeight: 600,
														color: '#0f172a',
													} }
												>
													{ log.form_title ||
														sprintf(
															__(
																'Form #%d',
																'drivevault-for-contact-form-7'
															),
															log.form_id
														) }
												</div>
												<div
													style={ {
														fontSize: 12,
														color: '#94a3b8',
													} }
												>
													ID: #{ log.form_id }
												</div>
											</td>
											<td>
												<div
													style={ {
														fontSize: 13,
														color: '#334155',
														fontWeight: 500,
													} }
												>
													{ log.spreadsheet_title ||
														log.sheet_name ||
														log.sheet_id ||
														'—' }
													{ log.tab_name && (
														<span
															style={ {
																color: '#64748b',
																fontSize: 12,
																fontWeight: 400,
															} }
														>
															{ ' ' }
															({ log.tab_name })
														</span>
													) }
												</div>
												{ log.error_message && (
													<div
														style={ {
															fontSize: 12,
															color: '#d93025',
															marginTop: 2,
														} }
													>
														{ log.error_message }
													</div>
												) }
											</td>
											<td>
												<span
													style={ {
														fontSize: 13,
														color: '#64748b',
													} }
												>
													{ ( () => {
														const dur =
															log.duration_ms ||
															log.execution_time_ms ||
															0;
														return dur > 0
															? `${ (
																	dur / 1000
															  ).toFixed( 2 ) }s`
															: '—';
													} )() }
												</span>
											</td>
											<td>
												<span
													style={ {
														fontSize: 13,
														color: '#334155',
													} }
												>
													{ log.created_at }
												</span>
											</td>
											<td
												style={ { textAlign: 'right' } }
											>
												<div
													style={ {
														display: 'inline-flex',
														gap: 6,
													} }
												>
													<button
														type="button"
														className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
														onClick={ () =>
															setActivePayloadLog(
																log
															)
														}
														title={ __(
															'View submission data',
															'drivevault-for-contact-form-7'
														) }
													>
														<Icon
															name="visibility"
															size={ 14 }
														/>
														<span>
															{ __(
																'View Payload',
																'drivevault-for-contact-form-7'
															) }
														</span>
													</button>

													{ log.result ===
														'error' && (
														<button
															type="button"
															className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
															onClick={ () =>
																handleRetry(
																	log.id
																)
															}
															disabled={
																isRetryingId ===
																log.id
															}
															title={ __(
																'Retry Google sync',
																'drivevault-for-contact-form-7'
															) }
														>
															<Icon
																name="replay"
																size={ 14 }
															/>
															<span>
																{ isRetryingId ===
																log.id
																	? __(
																			'Retrying...',
																			'drivevault-for-contact-form-7'
																	  )
																	: __(
																			'Retry',
																			'drivevault-for-contact-form-7'
																	  ) }
															</span>
														</button>
													) }

													<button
														type="button"
														className="drivevault-btn drivevault-btn--danger-outline drivevault-btn--sm"
														onClick={ () =>
															handleDelete(
																log.id
															)
														}
														title={ __(
															'Delete log',
															'drivevault-for-contact-form-7'
														) }
														style={ {
															padding: '6px 8px',
														} }
													>
														<Icon
															name="delete"
															size={ 14 }
														/>
													</button>
												</div>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</div>
			</div>

			{ /* Payload Viewer Modal */ }
			{ activePayloadLog && (
				<Modal
					title={ sprintf(
						/* translators: %d: log entry ID */
						__(
							'Submission Payload #%d',
							'drivevault-for-contact-form-7'
						),
						activePayloadLog.id
					) }
					onClose={ () => setActivePayloadLog( null ) }
				>
					{ ( () => {
						const payloadData =
							activePayloadLog.mapped_data &&
							Object.keys( activePayloadLog.mapped_data ).length >
								0
								? activePayloadLog.mapped_data
								: activePayloadLog.form_data &&
								  Object.keys( activePayloadLog.form_data )
										.length > 0
								? activePayloadLog.form_data
								: activePayloadLog.payload || {};

						const driveFiles =
							activePayloadLog.drive_files ||
							activePayloadLog.uploaded_files ||
							[];

						return (
							<div>
								{ /* Summary Meta Bar */ }
								<div
									style={ {
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'space-between',
										padding: '10px 14px',
										background: '#f8fafc',
										border: '1px solid #e2e8f0',
										borderRadius: 8,
										marginBottom: 14,
										fontSize: 12,
										color: '#64748b',
										flexWrap: 'wrap',
										gap: 8,
									} }
								>
									<div>
										<strong>
											{ __(
												'Form:',
												'drivevault-for-contact-form-7'
											) }
										</strong>{ ' ' }
										<span style={ { color: '#0f172a' } }>
											{ activePayloadLog.form_title }
										</span>
									</div>
									<div>
										<strong>
											{ __(
												'Target Sheet:',
												'drivevault-for-contact-form-7'
											) }
										</strong>{ ' ' }
										<span style={ { color: '#0f172a' } }>
											{ activePayloadLog.spreadsheet_title ||
												activePayloadLog.sheet_name ||
												'—' }
											{ activePayloadLog.tab_name
												? ` (${ activePayloadLog.tab_name })`
												: '' }
										</span>
									</div>
									<div>
										<strong>
											{ __(
												'Duration:',
												'drivevault-for-contact-form-7'
											) }
										</strong>{ ' ' }
										<span style={ { color: '#0f172a' } }>
											{ ( activePayloadLog.duration_ms ||
												activePayloadLog.execution_time_ms ||
												0 ) > 0
												? `${ (
														( activePayloadLog.duration_ms ||
															activePayloadLog.execution_time_ms ) /
														1000
												  ).toFixed( 2 ) }s`
												: '—' }
										</span>
									</div>
								</div>

								{ /* Key-Value Grid of Mapped Submission Fields */ }
								{ Object.keys( payloadData ).length > 0 ? (
									<div
										style={ {
											border: '1px solid #e2e8f0',
											borderRadius: 8,
											overflow: 'hidden',
											marginBottom: 14,
										} }
									>
										<table
											style={ {
												width: '100%',
												borderCollapse: 'collapse',
												fontSize: 13,
											} }
										>
											<tbody>
												{ Object.entries(
													payloadData
												).map(
													( [ key, val ], idx ) => (
														<tr
															key={ key }
															style={ {
																background:
																	idx % 2 ===
																	0
																		? '#ffffff'
																		: '#f8fafc',
																borderBottom:
																	'1px solid #f1f5f9',
															} }
														>
															<td
																style={ {
																	padding:
																		'8px 14px',
																	fontWeight: 600,
																	color: '#334155',
																	width: '35%',
																	verticalAlign:
																		'top',
																} }
															>
																{ key }
															</td>
															<td
																style={ {
																	padding:
																		'8px 14px',
																	color: '#0f172a',
																	wordBreak:
																		'break-word',
																} }
															>
																{ typeof val ===
																'object' ? (
																	JSON.stringify(
																		val
																	)
																) : typeof val ===
																		'string' &&
																  ( val.startsWith(
																		'http://'
																  ) ||
																		val.startsWith(
																			'https://'
																		) ) ? (
																	<a
																		href={
																			val
																		}
																		target="_blank"
																		rel="noreferrer"
																		style={ {
																			color: '#1a73e8',
																			textDecoration:
																				'none',
																			fontWeight: 500,
																			display:
																				'inline-flex',
																			alignItems:
																				'center',
																			gap: 4,
																		} }
																	>
																		<span>
																			{
																				val
																			}
																		</span>
																		<span>
																			↗
																		</span>
																	</a>
																) : typeof val ===
																		'string' &&
																  val.length ===
																		64 &&
																  /^[a-f0-9]{64}$/i.test(
																		val
																  ) ? (
																	<div>
																		<code
																			style={ {
																				fontSize: 11,
																				background:
																					'#f1f5f9',
																				padding:
																					'2px 6px',
																				borderRadius: 4,
																				color: '#475569',
																			} }
																		>
																			{
																				val
																			}
																		</code>
																		<div
																			style={ {
																				fontSize: 11,
																				color: '#d97706',
																				marginTop: 4,
																				display:
																					'flex',
																				alignItems:
																					'center',
																				gap: 4,
																			} }
																		>
																			<span>
																				⚠️
																			</span>
																			<span>
																				{ __(
																					'CF7 temporary file checksum. Files uploaded prior to Drive integration cannot be re-downloaded.',
																					'drivevault-for-contact-form-7'
																				) }
																			</span>
																		</div>
																	</div>
																) : (
																	String(
																		val ||
																			'—'
																	)
																) }
															</td>
														</tr>
													)
												) }
											</tbody>
										</table>
									</div>
								) : null }

								{ /* Collapsible Raw JSON Payload */ }
								<details style={ { marginBottom: 12 } }>
									<summary
										style={ {
											fontSize: 12,
											color: '#64748b',
											cursor: 'pointer',
											marginBottom: 6,
											fontWeight: 500,
										} }
									>
										{ __(
											'View Raw JSON Payload',
											'drivevault-for-contact-form-7'
										) }
									</summary>
									<div
										style={ {
											background: '#0f172a',
											color: '#e2e8f0',
											fontFamily: 'monospace',
											fontSize: 12,
											padding: '14px 18px',
											borderRadius: 8,
											maxHeight: 220,
											overflowY: 'auto',
											lineHeight: 1.6,
										} }
									>
										<pre
											style={ {
												margin: 0,
												whiteSpace: 'pre-wrap',
												wordBreak: 'break-all',
											} }
										>
											{ JSON.stringify(
												payloadData,
												null,
												2
											) }
										</pre>
									</div>
								</details>

								{ /* Uploaded Drive Files */ }
								{ driveFiles && driveFiles.length > 0 && (
									<div style={ { marginTop: 14 } }>
										<h5
											style={ {
												margin: '0 0 8px 0',
												fontSize: 13,
												fontWeight: 600,
												color: '#0f172a',
											} }
										>
											{ __(
												'Uploaded Google Drive Files:',
												'drivevault-for-contact-form-7'
											) }
										</h5>
										<ul
											style={ {
												margin: 0,
												paddingLeft: 20,
												fontSize: 13,
												color: '#334155',
											} }
										>
											{ driveFiles.map( ( f, idx ) => {
												const fileUrl =
													f.webViewLink ||
													f.drive_view_url ||
													f.url ||
													'';
												const fileName =
													f.name ||
													f.file_name ||
													__(
														'Uploaded File',
														'drivevault-for-contact-form-7'
													);
												return (
													<li key={ idx }>
														{ fileUrl ? (
															<a
																href={ fileUrl }
																target="_blank"
																rel="noreferrer"
																style={ {
																	color: '#1a73e8',
																	textDecoration:
																		'none',
																	fontWeight: 500,
																} }
															>
																{ fileName } ↗
															</a>
														) : (
															<span>
																{ fileName }
															</span>
														) }
													</li>
												);
											} ) }
										</ul>
									</div>
								) }
							</div>
						);
					} )() }
				</Modal>
			) }
		</div>
	);
};

export default LogsPage;
