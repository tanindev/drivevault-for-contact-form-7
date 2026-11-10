import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';

/**
 * System Status & Environment Diagnostics Card.
 * Matches exact SaaS UI design from DriveVault Status & Quota tab.
 */
export const SystemStatusCard = () => {
	const [ status, setStatus ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: 'drivevault-cf7/v1/status' } )
			.then( ( res ) => setStatus( res ) )
			.catch( () => {} )
			.finally( () => setIsLoading( false ) );
	}, [] );

	if ( isLoading ) {
		return (
			<div className="drivevault-card">
				<div
					className="drivevault-card-body"
					style={ {
						padding: 30,
						textAlign: 'center',
						color: '#64748b',
					} }
				>
					{ __(
						'Loading system diagnostics...',
						'drivevault-for-contact-form-7'
					) }
				</div>
			</div>
		);
	}

	if ( ! status ) return null;

	return (
		<div className="drivevault-card">
			<div className="drivevault-card-header">
				<div>
					<h3>
						<Icon name="dns" size={ 20 } color="#1a73e8" />
						{ __(
							'System Environment & Diagnostics',
							'drivevault-for-contact-form-7'
						) }
					</h3>
					<p>
						{ __(
							'Server configuration and API compatibility check.',
							'drivevault-for-contact-form-7'
						) }
					</p>
				</div>
			</div>

			<div className="drivevault-card-body" style={ { padding: 0 } }>
				<table className="drivevault-table">
					<tbody>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'Plugin Version',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>
								<span className="drivevault-badge drivevault-badge--info">
									v{ status.plugin_version }
								</span>
							</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'PHP Version',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>
								{ status.php_version }{ ' ' }
								{ parseFloat( status.php_version ) >= 7.4 ? (
									<span className="drivevault-badge drivevault-badge--success">
										OK
									</span>
								) : (
									<span className="drivevault-badge drivevault-badge--danger">
										{ __(
											'Upgrade Required',
											'drivevault-for-contact-form-7'
										) }
									</span>
								) }
							</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'WordPress Version',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>{ status.wp_version }</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'Contact Form 7',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>
								{ status.cf7_active ? (
									<span className="drivevault-badge drivevault-badge--success">
										{ status.cf7_version
											? `v${ status.cf7_version } Active`
											: 'Active' }
									</span>
								) : (
									<span className="drivevault-badge drivevault-badge--danger">
										{ __(
											'Not Detected',
											'drivevault-for-contact-form-7'
										) }
									</span>
								) }
							</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'cURL Extension',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>
								{ status.curl_available ? (
									<span className="drivevault-badge drivevault-badge--success">
										Enabled
									</span>
								) : (
									<span className="drivevault-badge drivevault-badge--danger">
										Disabled
									</span>
								) }
							</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'OpenSSL Encryption',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>
								{ status.openssl_available ? (
									<span className="drivevault-badge drivevault-badge--success">
										AES-256 Ready
									</span>
								) : (
									<span className="drivevault-badge drivevault-badge--danger">
										Missing
									</span>
								) }
							</td>
						</tr>
						<tr>
							<td style={ { fontWeight: 600, color: '#0f172a' } }>
								{ __(
									'Max Upload Size',
									'drivevault-for-contact-form-7'
								) }
							</td>
							<td>{ status.upload_max_filesize || 'N/A' }</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	);
};

export default SystemStatusCard;
