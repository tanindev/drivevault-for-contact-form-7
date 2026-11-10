import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../common/Icon';

export const FeedEditor = ( {
	formId,
	isConnected = false,
	onNavigate,
	onBack,
	onSaved,
	createToast,
} ) => {
	const [ forms, setForms ] = useState( [] );
	const [ selectedFormId, setSelectedFormId ] = useState( formId || '' );
	const [ fields, setFields ] = useState( [] );
	const [ metaFields, setMetaFields ] = useState( [] );

	const [ sheets, setSheets ] = useState( [] );
	const [ selectedSheetId, setSelectedSheetId ] = useState( '' );
	const [ sheetTabs, setSheetTabs ] = useState( [ 'Sheet1' ] );
	const [ selectedTab, setSelectedTab ] = useState( 'Sheet1' );

	const [ folders, setFolders ] = useState( [] );
	const [ selectedFolderId, setSelectedFolderId ] = useState( '' );

	const [ columnMap, setColumnMap ] = useState( {} );
	const [ includeMeta, setIncludeMeta ] = useState( true );
	const [ uploadFilesToDrive, setUploadFilesToDrive ] = useState( true );
	const [ deleteLocalFiles, setDeleteLocalFiles ] = useState( false );
	const [ isActive, setIsActive ] = useState( true );

	const [ isSaving, setIsSaving ] = useState( false );
	const [ isTesting, setIsTesting ] = useState( false );
	const [ newSheetTitle, setNewSheetTitle ] = useState( '' );
	const [ isCreatingSheet, setIsCreatingSheet ] = useState( false );

	// 1. Load available CF7 forms
	useEffect( () => {
		apiFetch( { path: 'drivevault-cf7/v1/forms' } )
			.then( ( res ) => {
				setForms( res || [] );
				if ( ! selectedFormId && res && res.length ) {
					setSelectedFormId( res[ 0 ].id );
				}
			} )
			.catch( () => {} );
	}, [] );

	// 2. Load Google Spreadsheets and Drive Folders only if connected
	useEffect( () => {
		if ( ! isConnected ) return;

		apiFetch( { path: 'drivevault-cf7/v1/google/sheets' } )
			.then( ( res ) => setSheets( res || [] ) )
			.catch( () => {} );

		apiFetch( { path: 'drivevault-cf7/v1/google/drive/folders' } )
			.then( ( res ) => setFolders( res || [] ) )
			.catch( () => {} );
	}, [ isConnected ] );

	// 3. Load fields and existing mapping whenever selected form changes
	useEffect( () => {
		if ( ! selectedFormId ) return;

		// Fetch fields
		apiFetch( {
			path: `drivevault-cf7/v1/forms/${ selectedFormId }/fields`,
		} )
			.then( ( res ) => {
				if ( res && res.fields ) {
					setFields( res.fields );
					setMetaFields( res.meta || [] );

					// Default initial column map if not yet configured
					const defaultMap = {};
					res.fields.forEach( ( f ) => {
						defaultMap[ f.name ] = f.label || f.name;
					} );
					if ( res.meta ) {
						res.meta.forEach( ( m ) => {
							defaultMap[ `_meta_${ m.key }` ] = m.label;
						} );
					}
					setColumnMap( ( prev ) =>
						Object.keys( prev ).length ? prev : defaultMap
					);
				}
			} )
			.catch( () => {} );

		// Fetch existing mapping
		apiFetch( { path: `drivevault-cf7/v1/mappings/${ selectedFormId }` } )
			.then( ( res ) => {
				if ( res && res.id ) {
					setSelectedSheetId( res.sheet_id || '' );
					setSelectedTab( res.tab_name || 'Sheet1' );
					setSelectedFolderId( res.drive_folder_id || '' );
					if (
						res.column_map &&
						Object.keys( res.column_map ).length
					) {
						setColumnMap( res.column_map );
					}
					setIncludeMeta( !! res.include_meta );
					setUploadFilesToDrive( !! res.upload_files_to_drive );
					setDeleteLocalFiles( !! res.delete_local_files );
					setIsActive( !! res.is_active );
				}
			} )
			.catch( () => {} );
	}, [ selectedFormId ] );

	// 4. Load tabs when sheet changes
	useEffect( () => {
		if ( ! selectedSheetId ) return;
		apiFetch( {
			path: `drivevault-cf7/v1/google/sheets/tabs?sheet_id=${ selectedSheetId }`,
		} )
			.then( ( res ) => {
				if ( res && res.tabs && res.tabs.length ) {
					setSheetTabs( res.tabs.map( ( t ) => t.title ) );
				}
			} )
			.catch( () => {} );
	}, [ selectedSheetId ] );

	const handleColumnChange = ( key, val ) => {
		setColumnMap( ( prev ) => ( {
			...prev,
			[ key ]: val,
		} ) );
	};

	const handleCreateNewSheet = async () => {
		if ( ! newSheetTitle.trim() ) {
			createToast(
				__(
					'Please enter a title for the new spreadsheet.',
					'drivevault-for-contact-form-7'
				),
				'error'
			);
			return;
		}

		setIsCreatingSheet( true );
		try {
			const created = await apiFetch( {
				path: 'drivevault-cf7/v1/google/sheets',
				method: 'POST',
				data: { title: newSheetTitle.trim() },
			} );
			if ( created && created.id ) {
				setSheets( ( prev ) => [ created, ...prev ] );
				setSelectedSheetId( created.id );
				setSelectedTab( 'Sheet1' );
				setNewSheetTitle( '' );
				createToast(
					__(
						'Google Sheet created successfully!',
						'drivevault-for-contact-form-7'
					),
					'success'
				);
			}
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to create spreadsheet.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsCreatingSheet( false );
		}
	};

	const handleSendTestRow = async () => {
		if ( ! selectedSheetId ) {
			createToast(
				__(
					'Please select a Google Sheet first.',
					'drivevault-for-contact-form-7'
				),
				'error'
			);
			return;
		}

		setIsTesting( true );
		try {
			await apiFetch( {
				path: 'drivevault-cf7/v1/mappings/test',
				method: 'POST',
				data: {
					sheet_id: selectedSheetId,
					tab_name: selectedTab,
					column_map: columnMap,
				},
			} );
			createToast(
				__(
					'Test row written to Google Sheet successfully!',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to send test row.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsTesting( false );
		}
	};

	const handleSave = async () => {
		if ( ! selectedFormId ) {
			createToast(
				__(
					'Please select a Contact Form.',
					'drivevault-for-contact-form-7'
				),
				'error'
			);
			return;
		}
		if ( ! selectedSheetId ) {
			createToast(
				__(
					'Please select or create a Google Sheet.',
					'drivevault-for-contact-form-7'
				),
				'error'
			);
			return;
		}

		setIsSaving( true );
		try {
			const sheetObj = sheets.find( ( s ) => s.id === selectedSheetId );
			const folderObj = folders.find(
				( f ) => f.id === selectedFolderId
			);

			await apiFetch( {
				path: 'drivevault-cf7/v1/mappings',
				method: 'POST',
				data: {
					form_id: selectedFormId,
					sheet_id: selectedSheetId,
					sheet_name: sheetObj ? sheetObj.name : 'Google Sheet',
					tab_name: selectedTab,
					drive_folder_id: selectedFolderId,
					drive_folder_name: folderObj ? folderObj.name : '',
					column_map: columnMap,
					include_meta: includeMeta,
					upload_files_to_drive: uploadFilesToDrive,
					delete_local_files: deleteLocalFiles,
					is_active: isActive,
					auto_create_headers: true,
				},
			} );

			createToast(
				__(
					'Feed mapping saved and headers configured!',
					'drivevault-for-contact-form-7'
				),
				'success'
			);
			if ( onSaved ) onSaved();
		} catch ( err ) {
			createToast(
				err.message ||
					__(
						'Failed to save mapping.',
						'drivevault-for-contact-form-7'
					),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<div className="drivevault-feed-editor">
			{ /* Top Bar with Back Button & Direct Save Actions */ }
			<div
				style={ {
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'space-between',
					marginBottom: '20px',
					flexWrap: 'wrap',
					gap: '12px',
				} }
			>
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						gap: '12px',
					} }
				>
					<button
						type="button"
						className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
						onClick={ onBack }
					>
						<Icon name="arrow_back" size={ 16 } />
						{ __(
							'Back to Feeds',
							'drivevault-for-contact-form-7'
						) }
					</button>
					<div>
						<h2
							style={ {
								margin: 0,
								fontSize: '1.25rem',
								fontWeight: 700,
								color: '#0f172a',
								lineHeight: 1.25,
							} }
						>
							{ __(
								'Configure Form Feed & Field Mapping',
								'drivevault-for-contact-form-7'
							) }
						</h2>
					</div>
				</div>

				<div style={ { display: 'flex', gap: '10px' } }>
					<button
						type="button"
						className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
						onClick={ onBack }
					>
						{ __( 'Cancel', 'drivevault-for-contact-form-7' ) }
					</button>
					<button
						type="button"
						className="drivevault-btn drivevault-btn--primary drivevault-btn--sm"
						onClick={ handleSave }
						disabled={ isSaving }
					>
						<Icon name="save" size={ 16 } />
						{ isSaving
							? __( 'Saving...', 'drivevault-for-contact-form-7' )
							: __(
									'Save Feed Mapping',
									'drivevault-for-contact-form-7'
							  ) }
					</button>
				</div>
			</div>

			{ /* Google Account Warning Banner if Not Connected */ }
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
								<h3>
									{ __(
										'Google Account Not Connected',
										'drivevault-for-contact-form-7'
									) }
								</h3>
								<p className="drivevault-auth-desc">
									{ __(
										'Connect your Google Account to browse spreadsheets and drive folders automatically.',
										'drivevault-for-contact-form-7'
									) }
								</p>
							</div>
						</div>
						{ onNavigate && (
							<button
								type="button"
								className="drivevault-btn drivevault-btn--primary drivevault-btn--sm"
								onClick={ () => onNavigate( 'google' ) }
							>
								<Icon name="link" size={ 16 } />
								{ __(
									'Connect Google Account',
									'drivevault-for-contact-form-7'
								) }
							</button>
						) }
					</div>
				</div>
			) }

			{ /* Card 1: Feed Destinations */ }
			<div className="drivevault-card">
				<div className="drivevault-card-header">
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
						} }
					>
						<div
							style={ {
								width: 38,
								height: 38,
								borderRadius: 8,
								background: '#e8f0fe',
								color: '#1a73e8',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								flexShrink: 0,
							} }
						>
							<Icon name="tune" size={ 20 } color="#1a73e8" />
						</div>
						<div>
							<h3 style={ { margin: 0 } }>
								{ __(
									'Feed Destinations (Google Workspace)',
									'drivevault-for-contact-form-7'
								) }
							</h3>
							<p style={ { margin: '2px 0 0 0' } }>
								{ __(
									'Connect your Contact Form 7 form to a target Google Sheet and optional Drive uploads folder.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>
					</div>
				</div>

				<div className="drivevault-card-body">
					<div className="drivevault-form-grid-2">
						{ /* Contact Form 7 Form Selection */ }
						<div className="drivevault-form-group">
							<label>
								{ __(
									'Contact Form 7 Form',
									'drivevault-for-contact-form-7'
								) }
							</label>
							<select
								className="drivevault-select"
								style={ { width: '100%' } }
								value={ selectedFormId }
								onChange={ ( e ) =>
									setSelectedFormId( e.target.value )
								}
							>
								<option value="">
									{ __(
										'-- Select a Contact Form --',
										'drivevault-for-contact-form-7'
									) }
								</option>
								{ forms.map( ( f ) => (
									<option key={ f.id } value={ f.id }>
										{ f.title } (#{ f.id })
									</option>
								) ) }
							</select>
							<p className="drivevault-field-help">
								{ __(
									'Select the Contact Form 7 form whose submissions will be captured.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>

						{ /* Google Spreadsheet Selection & Creation */ }
						<div className="drivevault-form-group">
							<label>
								{ __(
									'Target Google Spreadsheet',
									'drivevault-for-contact-form-7'
								) }
							</label>
							<select
								className="drivevault-select"
								style={ { width: '100%' } }
								value={ selectedSheetId }
								onChange={ ( e ) =>
									setSelectedSheetId( e.target.value )
								}
							>
								<option value="">
									{ __(
										'-- Select Google Spreadsheet --',
										'drivevault-for-contact-form-7'
									) }
								</option>
								{ sheets.map( ( s ) => (
									<option key={ s.id } value={ s.id }>
										{ s.name }
									</option>
								) ) }
							</select>
							<div
								style={ {
									display: 'flex',
									gap: '8px',
									marginTop: '8px',
								} }
							>
								<input
									type="text"
									className="drivevault-input"
									placeholder={ __(
										'Or enter title to create new sheet...',
										'drivevault-for-contact-form-7'
									) }
									value={ newSheetTitle }
									onChange={ ( e ) =>
										setNewSheetTitle( e.target.value )
									}
									style={ { flex: 1, height: '36px' } }
								/>
								<button
									type="button"
									className="drivevault-btn drivevault-btn--secondary drivevault-btn--sm"
									onClick={ handleCreateNewSheet }
									disabled={ isCreatingSheet }
								>
									<Icon name="add" size={ 16 } />
									{ isCreatingSheet
										? __(
												'Creating...',
												'drivevault-for-contact-form-7'
										  )
										: __(
												'Create Sheet',
												'drivevault-for-contact-form-7'
										  ) }
								</button>
							</div>
						</div>

						{ /* Worksheet Tab Name */ }
						<div className="drivevault-form-group">
							<label>
								{ __(
									'Worksheet Tab Name',
									'drivevault-for-contact-form-7'
								) }
							</label>
							<select
								className="drivevault-select"
								style={ { width: '100%' } }
								value={ selectedTab }
								onChange={ ( e ) =>
									setSelectedTab( e.target.value )
								}
							>
								{ sheetTabs.map( ( tab ) => (
									<option key={ tab } value={ tab }>
										{ tab }
									</option>
								) ) }
							</select>
							<p className="drivevault-field-help">
								{ __(
									'The specific sheet tab to append new submission rows to.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>

						{ /* Google Drive Uploads Folder */ }
						<div className="drivevault-form-group">
							<label>
								{ __(
									'Google Drive File Uploads Folder (Optional)',
									'drivevault-for-contact-form-7'
								) }
							</label>
							<select
								className="drivevault-select"
								style={ { width: '100%' } }
								value={ selectedFolderId }
								onChange={ ( e ) =>
									setSelectedFolderId( e.target.value )
								}
							>
								<option value="">
									{ __(
										'Auto-create form folder in Google Drive root',
										'drivevault-for-contact-form-7'
									) }
								</option>
								{ folders.map( ( fold ) => (
									<option key={ fold.id } value={ fold.id }>
										{ fold.name }
									</option>
								) ) }
							</select>
							<p className="drivevault-field-help">
								{ __(
									'File attachments will be uploaded to this Drive folder and direct cloud links added to the sheet.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>
					</div>
				</div>
			</div>

			{ /* Card 2: Visual Field-to-Column Mapper */ }
			<div className="drivevault-card">
				<div className="drivevault-card-header">
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
						} }
					>
						<div
							style={ {
								width: 38,
								height: 38,
								borderRadius: 8,
								background: '#e8f0fe',
								color: '#1a73e8',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								flexShrink: 0,
							} }
						>
							<Icon
								name="table_chart"
								size={ 20 }
								color="#1a73e8"
							/>
						</div>
						<div>
							<h3 style={ { margin: 0 } }>
								{ __(
									'Visual Field-to-Column Mapper',
									'drivevault-for-contact-form-7'
								) }
							</h3>
							<p style={ { margin: '2px 0 0 0' } }>
								{ __(
									'Map each Contact Form 7 tag to a column header in your Google Sheet.',
									'drivevault-for-contact-form-7'
								) }
							</p>
						</div>
					</div>
				</div>

				<div className="drivevault-card-body">
					{ /* Form Input Fields */ }
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '8px',
							marginBottom: '12px',
						} }
					>
						<h4
							style={ {
								margin: 0,
								fontSize: '0.875rem',
								fontWeight: 600,
								color: '#334155',
							} }
						>
							{ __(
								'Form Input Fields',
								'drivevault-for-contact-form-7'
							) }
						</h4>
						<span className="drivevault-badge drivevault-badge--info">
							{ fields.length }
						</span>
					</div>

					{ fields.length === 0 && (
						<div
							style={ {
								padding: '16px',
								background: '#f8fafc',
								borderRadius: '8px',
								textAlign: 'center',
								color: '#64748b',
								fontSize: '0.84375rem',
								marginBottom: '16px',
							} }
						>
							{ __(
								'No custom tags detected for this form.',
								'drivevault-for-contact-form-7'
							) }
						</div>
					) }

					{ fields.map( ( field ) => (
						<div
							key={ field.name }
							className="drivevault-mapping-row"
						>
							<div className="drivevault-mapping-source">
								<span className="drivevault-tag-pill">
									[{ field.name }]
								</span>
								{ field.is_file && (
									<span className="drivevault-file-badge">
										<Icon name="attach_file" size={ 12 } />
										{ __(
											'File Attachment',
											'drivevault-for-contact-form-7'
										) }
									</span>
								) }
								{ field.label && field.label !== field.name && (
									<span className="drivevault-source-label">
										{ field.label }
									</span>
								) }
							</div>
							<div className="drivevault-mapping-arrow">
								<Icon
									name="arrow_forward"
									size={ 16 }
									color="#94a3b8"
								/>
							</div>
							<div className="drivevault-mapping-target">
								<input
									type="text"
									className="drivevault-input"
									placeholder={ __(
										'Google Sheet Column Header',
										'drivevault-for-contact-form-7'
									) }
									value={ columnMap[ field.name ] || '' }
									onChange={ ( e ) =>
										handleColumnChange(
											field.name,
											e.target.value
										)
									}
								/>
							</div>
						</div>
					) ) }

					{ /* Built-in Meta Fields */ }
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '8px',
							margin: '24px 0 12px 0',
						} }
					>
						<h4
							style={ {
								margin: 0,
								fontSize: '0.875rem',
								fontWeight: 600,
								color: '#334155',
							} }
						>
							{ __(
								'Built-in Meta Fields',
								'drivevault-for-contact-form-7'
							) }
						</h4>
						<span className="drivevault-badge drivevault-badge--neutral">
							{ metaFields.length }
						</span>
					</div>

					{ metaFields.map( ( meta ) => (
						<div
							key={ meta.key }
							className="drivevault-mapping-row"
						>
							<div className="drivevault-mapping-source">
								<span className="drivevault-tag-pill is-meta">
									{ '{' }
									{ meta.key }
									{ '}' }
								</span>
								<span className="drivevault-source-label">
									{ meta.label }
								</span>
							</div>
							<div className="drivevault-mapping-arrow">
								<Icon
									name="arrow_forward"
									size={ 16 }
									color="#94a3b8"
								/>
							</div>
							<div className="drivevault-mapping-target">
								<input
									type="text"
									className="drivevault-input"
									placeholder={ __(
										'Google Sheet Column Header',
										'drivevault-for-contact-form-7'
									) }
									value={
										columnMap[ `_meta_${ meta.key }` ] || ''
									}
									onChange={ ( e ) =>
										handleColumnChange(
											`_meta_${ meta.key }`,
											e.target.value
										)
									}
								/>
							</div>
						</div>
					) ) }

					{ /* Automation & Sync Options Box */ }
					<div className="drivevault-feed-options-box">
						<h4
							style={ {
								margin: '0 0 14px 0',
								fontSize: '0.875rem',
								fontWeight: 600,
								color: '#1e293b',
							} }
						>
							{ __(
								'Sync & File Automation Settings',
								'drivevault-for-contact-form-7'
							) }
						</h4>
						<div
							style={ {
								display: 'flex',
								flexDirection: 'column',
								gap: '12px',
							} }
						>
							<label className="drivevault-checkbox-row">
								<input
									type="checkbox"
									checked={ uploadFilesToDrive }
									onChange={ ( e ) =>
										setUploadFilesToDrive(
											e.target.checked
										)
									}
								/>
								<div>
									<strong>
										{ __(
											'Upload file attachments to Google Drive',
											'drivevault-for-contact-form-7'
										) }
									</strong>
									<p>
										{ __(
											'Direct Google Drive shareable URLs will be recorded in the spreadsheet column.',
											'drivevault-for-contact-form-7'
										) }
									</p>
								</div>
							</label>

							<label className="drivevault-checkbox-row">
								<input
									type="checkbox"
									checked={ deleteLocalFiles }
									onChange={ ( e ) =>
										setDeleteLocalFiles( e.target.checked )
									}
								/>
								<div>
									<strong>
										{ __(
											'Delete local file from WordPress server after Drive upload',
											'drivevault-for-contact-form-7'
										) }
									</strong>
									<p>
										{ __(
											'Frees hosting disk space by safely removing temporary upload attachments after cloud storage.',
											'drivevault-for-contact-form-7'
										) }
									</p>
								</div>
							</label>

							<label className="drivevault-checkbox-row">
								<input
									type="checkbox"
									checked={ isActive }
									onChange={ ( e ) =>
										setIsActive( e.target.checked )
									}
								/>
								<div>
									<strong>
										{ __(
											'Enable this sync feed',
											'drivevault-for-contact-form-7'
										) }
									</strong>
									<p>
										{ __(
											'When enabled, new Contact Form 7 submissions will immediately sync to this Google Sheet.',
											'drivevault-for-contact-form-7'
										) }
									</p>
								</div>
							</label>
						</div>
					</div>
				</div>

				{ /* Card Footer Actions */ }
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'space-between',
						padding: '18px 24px',
						background: '#f8fafc',
						borderTop: '1px solid #e2e8f0',
						flexWrap: 'wrap',
						gap: '12px',
					} }
				>
					<button
						type="button"
						className="drivevault-btn drivevault-btn--secondary"
						onClick={ handleSendTestRow }
						disabled={ isTesting || ! selectedSheetId }
					>
						<Icon name="playlist_add" size={ 18 } />
						{ isTesting
							? __(
									'Sending Test Row...',
									'drivevault-for-contact-form-7'
							  )
							: __(
									'Send Test Row to Sheet',
									'drivevault-for-contact-form-7'
							  ) }
					</button>

					<div style={ { display: 'flex', gap: '10px' } }>
						<button
							type="button"
							className="drivevault-btn drivevault-btn--secondary"
							onClick={ onBack }
						>
							{ __( 'Cancel', 'drivevault-for-contact-form-7' ) }
						</button>
						<button
							type="button"
							className="drivevault-btn drivevault-btn--primary"
							onClick={ handleSave }
							disabled={ isSaving }
						>
							<Icon name="save" size={ 16 } />
							{ isSaving
								? __(
										'Saving Feed...',
										'drivevault-for-contact-form-7'
								  )
								: __(
										'Save Feed Mapping',
										'drivevault-for-contact-form-7'
								  ) }
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default FeedEditor;
