import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';
import SystemStatusCard from './SystemStatusCard';

/**
 * Settings & Preferences Page.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const SettingsPage = ( { createToast } ) => {
	const [ settings, setSettings ] = useState( {
		sync_trigger: 'mail_sent',
		delete_local_uploads: false,
		anonymize_ip: false,
		enable_error_notifications: true,
		error_notification_email: '',
		log_retention_days: 60,
		delete_data_on_uninstall: false,
	} );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'drivevault-cf7/v1/settings' } )
			.then( ( res ) => {
				if ( res ) setSettings( res );
			} )
			.catch( () => {} );
	}, [] );

	const handleChange = ( key, value ) => {
		setSettings( ( prev ) => ( {
			...prev,
			[ key ]: value,
		} ) );
	};

	const handleSave = async ( e ) => {
		if ( e ) e.preventDefault();
		setIsSaving( true );
		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/settings',
				method: 'POST',
				data: settings,
			} );
			createToast(
				__(
					'Settings updated successfully.',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to save settings.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<div className="drivevault-settings-page">
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)',
					gap: 24,
					alignItems: 'start',
				} }
			>
				<form onSubmit={ handleSave }>
					{ /* Sync & Ingestion Preferences */ }
					<div className="drivevault-card">
						<div className="drivevault-card-header">
							<div>
								<h3>
									<Icon
										name="tune"
										size={ 20 }
										color="#1a73e8"
									/>
									{ __(
										'Sync & Automation Preferences',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p>
									{ __(
										'Customize form trigger hooks, local storage purging, and privacy options.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>

						<div className="drivevault-card-body">
							{ /* Trigger Hook Segmented Selection */ }
							<div className="drivevault-form-group">
								<label>
									{ __(
										'Google Sheets Sync Trigger Action',
										'drivevault-for-contact-form-7'
									) }
								</label>
								<div
									style={ {
										display: 'grid',
										gridTemplateColumns: '1fr 1fr',
										gap: 12,
										marginTop: 6,
										marginBottom: 8,
									} }
								>
									<div
										onClick={ () =>
											handleChange(
												'sync_trigger',
												'mail_sent'
											)
										}
										style={ {
											padding: '14px 16px',
											borderRadius: 8,
											border: `2px solid ${
												settings.sync_trigger ===
												'mail_sent'
													? '#1a73e8'
													: '#e2e8f0'
											}`,
											background:
												settings.sync_trigger ===
												'mail_sent'
													? '#eff6ff'
													: '#ffffff',
											cursor: 'pointer',
										} }
									>
										<div
											style={ {
												fontWeight: 600,
												fontSize: 13,
												color: '#0f172a',
												marginBottom: 2,
											} }
										>
											{ __(
												'On Email Sent (Recommended)',
												'drivevault-for-contact-form-7'
											) }
										</div>
										<div
											style={ {
												fontSize: 12,
												color: '#64748b',
											} }
										>
											<code>wpcf7_mail_sent</code> — sync
											only after CF7 mail is successfully
											dispatched.
										</div>
									</div>

									<div
										onClick={ () =>
											handleChange(
												'sync_trigger',
												'submit'
											)
										}
										style={ {
											padding: '14px 16px',
											borderRadius: 8,
											border: `2px solid ${
												settings.sync_trigger ===
												'submit'
													? '#1a73e8'
													: '#e2e8f0'
											}`,
											background:
												settings.sync_trigger ===
												'submit'
													? '#eff6ff'
													: '#ffffff',
											cursor: 'pointer',
										} }
									>
										<div
											style={ {
												fontWeight: 600,
												fontSize: 13,
												color: '#0f172a',
												marginBottom: 2,
											} }
										>
											{ __(
												'On Submission Validation',
												'drivevault-for-contact-form-7'
											) }
										</div>
										<div
											style={ {
												fontSize: 12,
												color: '#64748b',
											} }
										>
											<code>wpcf7_submit</code> —
											immediate sync upon form validation,
											even if mail fails.
										</div>
									</div>
								</div>
								<p className="drivevault-field-help">
									{ __(
										'Select when submissions stream into Google Sheets.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>

							{ /* Delete Local Uploads */ }
							<div
								className="drivevault-form-group"
								style={ { marginTop: 20 } }
							>
								<label
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: 10,
										cursor: 'pointer',
										fontWeight: 600,
									} }
								>
									<input
										type="checkbox"
										checked={
											settings.delete_local_uploads
										}
										onChange={ ( e ) =>
											handleChange(
												'delete_local_uploads',
												e.target.checked
											)
										}
										style={ { width: 18, height: 18 } }
									/>
									<span>
										{ __(
											'Purge Local Uploads after Google Drive Upload',
											'drivevault-for-contact-form-7'
										) }
									</span>
								</label>
								<p
									className="drivevault-field-help"
									style={ { marginLeft: 28 } }
								>
									{ __(
										'Automatically removes files from wp-content/uploads/wpcf7_uploads/ once securely uploaded to Google Drive to keep server hosting storage lean.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>

							{ /* Anonymize IP */ }
							<div
								className="drivevault-form-group"
								style={ { marginTop: 16 } }
							>
								<label
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: 10,
										cursor: 'pointer',
										fontWeight: 600,
									} }
								>
									<input
										type="checkbox"
										checked={ settings.anonymize_ip }
										onChange={ ( e ) =>
											handleChange(
												'anonymize_ip',
												e.target.checked
											)
										}
										style={ { width: 18, height: 18 } }
									/>
									<span>
										{ __(
											'Anonymize Submitter IP Addresses (GDPR Compliance)',
											'drivevault-for-contact-form-7'
										) }
									</span>
								</label>
								<p
									className="drivevault-field-help"
									style={ { marginLeft: 28 } }
								>
									{ __(
										'Masks the last octet of submitter IP addresses before recording them into activity logs.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
					</div>

					{ /* Error Notifications Card */ }
					<div
						className="drivevault-card"
						style={ { marginTop: 24 } }
					>
						<div className="drivevault-card-header">
							<div>
								<h3>
									<Icon
										name="notifications"
										size={ 20 }
										color="#1a73e8"
									/>
									{ __(
										'Error Alerts & Notifications',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p>
									{ __(
										'Receive instant alerts if Google Sheets or Drive API sync encounters an error.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>

						<div className="drivevault-card-body">
							<div className="drivevault-form-group">
								<label
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: 10,
										cursor: 'pointer',
										fontWeight: 600,
									} }
								>
									<input
										type="checkbox"
										checked={
											settings.enable_error_notifications
										}
										onChange={ ( e ) =>
											handleChange(
												'enable_error_notifications',
												e.target.checked
											)
										}
										style={ { width: 18, height: 18 } }
									/>
									<span>
										{ __(
											'Send Email Notification on Sync Failures',
											'drivevault-for-contact-form-7'
										) }
									</span>
								</label>
							</div>

							{ settings.enable_error_notifications && (
								<div
									className="drivevault-form-group"
									style={ { marginTop: 14 } }
								>
									<label>
										{ __(
											'Notification Email Address',
											'drivevault-for-contact-form-7'
										) }
									</label>
									<input
										type="email"
										className="drivevault-input"
										placeholder="admin@example.com"
										value={
											settings.error_notification_email ||
											''
										}
										onChange={ ( e ) =>
											handleChange(
												'error_notification_email',
												e.target.value
											)
										}
									/>
									<p className="drivevault-field-help">
										{ __(
											'Comma-separated recipient email addresses for alert dispatches.',
											'drivevault-for-contact-form-7'
										) }
									</p>
								</div>
							) }
						</div>
					</div>

					{ /* Data Retention & Uninstall */ }
					<div
						className="drivevault-card"
						style={ { marginTop: 24 } }
					>
						<div className="drivevault-card-header">
							<div>
								<h3>
									<Icon
										name="delete_forever"
										size={ 20 }
										color="#d93025"
									/>
									{ __(
										'Data Management & Retention',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p>
									{ __(
										'Configure log expiration and uninstall cleanup behavior.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>

						<div className="drivevault-card-body">
							<div className="drivevault-form-group">
								<label>
									{ __(
										'Submission Logs Retention Window (Days)',
										'drivevault-for-contact-form-7'
									) }
								</label>
								<input
									type="number"
									min="7"
									max="365"
									className="drivevault-input"
									value={ settings.log_retention_days }
									onChange={ ( e ) =>
										handleChange(
											'log_retention_days',
											parseInt( e.target.value, 10 ) || 30
										)
									}
									style={ { maxWidth: 160 } }
								/>
								<p className="drivevault-field-help">
									{ __(
										'Logs older than this threshold are purged daily automatically by WordPress cron.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>

							<div
								className="drivevault-form-group"
								style={ { marginTop: 18 } }
							>
								<label
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: 10,
										cursor: 'pointer',
										fontWeight: 600,
										color: '#d93025',
									} }
								>
									<input
										type="checkbox"
										checked={
											settings.delete_data_on_uninstall
										}
										onChange={ ( e ) =>
											handleChange(
												'delete_data_on_uninstall',
												e.target.checked
											)
										}
										style={ { width: 18, height: 18 } }
									/>
									<span>
										{ __(
											'Complete Data Wipe on Plugin Deletion',
											'drivevault-for-contact-form-7'
										) }
									</span>
								</label>
								<p
									className="drivevault-field-help"
									style={ { marginLeft: 28 } }
								>
									{ __(
										'If enabled, all database tables, feed mappings, and OAuth tokens are permanently deleted when you delete the plugin from WordPress.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
					</div>

					<div style={ { marginTop: 24 } }>
						<button
							type="submit"
							className="drivevault-btn drivevault-btn--primary"
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
											'Save Settings',
											'drivevault-for-contact-form-7'
									  ) }
							</span>
						</button>
					</div>
				</form>

				{ /* Right Column: System Status Card */ }
				<div>
					<SystemStatusCard />
				</div>
			</div>
		</div>
	);
};

export default SettingsPage;
