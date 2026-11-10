import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import Header from './components/common/Header';
import Tabs from './components/common/Tabs';
import Icon from './components/common/Icon';
import ToastContainer from './components/common/ToastContainer';

import DashboardPage from './components/Dashboard/DashboardPage';
import FeedsPage from './components/Feeds/FeedsPage';
import GoogleAccountPage from './components/Google/GoogleAccountPage';
import LogsPage from './components/Logs/LogsPage';
import SettingsPage from './components/Settings/SettingsPage';

import './scss/icons.scss';
import './scss/admin.scss';

const TABS = [
	{
		key: 'dashboard',
		title: __( 'Dashboard', 'drivevault-for-contact-form-7' ),
		icon: <Icon name="dashboard" size={ 17 } />,
	},
	{
		key: 'feeds',
		title: __( 'Form Feeds', 'drivevault-for-contact-form-7' ),
		icon: <Icon name="dynamic_feed" size={ 17 } />,
	},
	{
		key: 'google',
		title: __( 'Google Account', 'drivevault-for-contact-form-7' ),
		icon: <Icon name="cloud_queue" size={ 17 } />,
	},
	{
		key: 'logs',
		title: __( 'Submission Logs', 'drivevault-for-contact-form-7' ),
		icon: <Icon name="history" size={ 17 } />,
	},
	{
		key: 'settings',
		title: __( 'Settings', 'drivevault-for-contact-form-7' ),
		icon: <Icon name="settings" size={ 17 } />,
	},
];

const VALID_TABS = [ 'dashboard', 'feeds', 'google', 'logs', 'settings' ];

const getInitialTab = () => {
	if ( typeof window === 'undefined' ) return 'dashboard';
	const urlParams = new URLSearchParams( window.location.search );
	const tab = urlParams.get( 'tab' );
	if ( tab && VALID_TABS.includes( tab ) ) return tab;
	const hash = window.location.hash.replace( '#', '' );
	if ( hash && VALID_TABS.includes( hash ) ) return hash;
	return 'dashboard';
};

export const App = () => {
	const initialData = window.drivevaultCf7Data || {};
	const [ activeTab, setActiveTab ] = useState( getInitialTab );

	const [ isConnected, setIsConnected ] = useState(
		!! initialData.isConnected
	);
	const [ account, setAccount ] = useState( null );
	const [ toasts, setToasts ] = useState( [] );

	const createToast = ( message, type = 'info' ) => {
		const id = Date.now() + Math.random();
		setToasts( ( prev ) => [ ...prev, { id, message, type } ] );
		setTimeout( () => {
			setToasts( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
		}, 4000 );
	};

	const loadAccountStatus = async () => {
		try {
			const res = await apiFetch( {
				path: 'drivevault-cf7/v1/auth/status',
			} );
			if ( res ) {
				setIsConnected( !! res.isConnected );
				setAccount( res.account );
			}
		} catch ( err ) {
			// handled silently
		}
	};

	useEffect( () => {
		loadAccountStatus();

		if ( initialData.authNotice && initialData.authNotice.msg ) {
			createToast(
				initialData.authNotice.msg,
				initialData.authNotice.type || 'info'
			);
		}
	}, [] );

	const handleTabChange = ( tabKey ) => {
		if ( ! VALID_TABS.includes( tabKey ) || tabKey === activeTab ) return;
		setActiveTab( tabKey );
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', tabKey );
		window.history.pushState( { tab: tabKey }, '', url.toString() );
	};

	// Popstate history listener
	useEffect( () => {
		const onPopState = () => {
			setActiveTab( getInitialTab() );
		};
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [] );

	const handleDisconnect = async () => {
		if (
			! window.confirm(
				__(
					'Are you sure you want to disconnect your Google Account?',
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
			setIsConnected( false );
			setAccount( null );
			createToast(
				__(
					'Google Account disconnected successfully.',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
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
		<div className="drivevault-app">
			<ToastContainer toasts={ toasts } />

			{ /* Top Header Bar */ }
			<Header
				account={ account }
				isConnected={ isConnected }
				onDisconnect={ handleDisconnect }
				onNavigate={ handleTabChange }
			/>

			{ /* Centered Container */ }
			<div className="drivevault-container">
				<div className="drivevault-body-card">
					{ /* Capsule Navigation Tabs Bar */ }
					<div className="drivevault-tabs-header-bar">
						<Tabs
							tabs={ TABS }
							active={ activeTab }
							onChange={ handleTabChange }
						/>
					</div>

					{ /* Tab Content Wrapper */ }
					<div className="drivevault-tab-content-wrapper">
						{ activeTab === 'dashboard' && (
							<DashboardPage
								onNavigate={ handleTabChange }
								isConnected={ isConnected }
								createToast={ createToast }
							/>
						) }
						{ activeTab === 'feeds' && (
							<FeedsPage
								createToast={ createToast }
								isConnected={ isConnected }
								onNavigate={ handleTabChange }
							/>
						) }
						{ activeTab === 'google' && (
							<GoogleAccountPage
								account={ account }
								isConnected={ isConnected }
								createToast={ createToast }
								onAccountUpdated={ loadAccountStatus }
							/>
						) }
						{ activeTab === 'logs' && (
							<LogsPage createToast={ createToast } />
						) }
						{ activeTab === 'settings' && (
							<SettingsPage createToast={ createToast } />
						) }
					</div>
				</div>
			</div>
		</div>
	);
};

export default App;
