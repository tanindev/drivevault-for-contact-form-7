import React from 'react';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import ConnectedAccountsDropdown from './ConnectedAccountsDropdown';

/**
 * Top Header Component.
 * Exactly matches DriveVault Backup & Migration design system.
 */
export const Header = ( {
	account,
	isConnected,
	onDisconnect,
	onNavigate,
} ) => {
	return (
		<header className="drivevault-header">
			<div className="drivevault-header-left">
				<div className="drivevault-logo-icon">
					<Icon name="cloud_queue" size={ 28 } color="#1a73e8" />
				</div>
				<div className="drivevault-header-titles">
					<h1>
						{ __(
							'DriveVault for Contact Form 7',
							'drivevault-for-contact-form-7'
						) }
					</h1>
					<p className="drivevault-subtitle">
						{ __(
							'Automated Google Sheets sync & Google Drive file uploads for Contact Form 7.',
							'drivevault-for-contact-form-7'
						) }
					</p>
				</div>
			</div>

			<div className="drivevault-header-right">
				<ConnectedAccountsDropdown
					account={ account }
					isConnected={ isConnected }
					onNavigate={ onNavigate }
					onDisconnect={ onDisconnect }
				/>
			</div>
		</header>
	);
};

export default Header;
