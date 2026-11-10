import React, { useRef, useState, useEffect, useCallback } from 'react';

/**
 * Modern Segmented Capsule Tabs with animated sliding indicator.
 * Matches exact SaaS UI design from DriveVault ecosystem.
 */
export const Tabs = ( { tabs, active, onChange, className = '' } ) => {
	const tabsRef = useRef( [] );
	const wrapperRef = useRef( null );
	const [ indicator, setIndicator ] = useState( { width: 0, left: 0 } );
	const [ isReady, setIsReady ] = useState( false );

	const updateIndicator = useCallback( () => {
		const activeIndex = tabs.findIndex( ( t ) => t.key === active );
		const activeTab = tabsRef.current[ activeIndex ];
		const wrapper = wrapperRef.current;

		if ( ! activeTab || ! wrapper ) return;

		const wrapperRect = wrapper.getBoundingClientRect();
		const tabRect = activeTab.getBoundingClientRect();

		setIndicator( {
			width: tabRect.width,
			left: tabRect.left - wrapperRect.left,
		} );
		setIsReady( true );
	}, [ active, tabs ] );

	useEffect( () => {
		const raf = requestAnimationFrame( updateIndicator );
		return () => cancelAnimationFrame( raf );
	}, [ updateIndicator ] );

	useEffect( () => {
		const wrapper = wrapperRef.current;
		if ( ! wrapper ) return;

		const observer = new ResizeObserver( () => {
			requestAnimationFrame( updateIndicator );
		} );
		observer.observe( wrapper );

		return () => observer.disconnect();
	}, [ updateIndicator ] );

	return (
		<div className={ `drivevault-modern-tabs ${ className }` }>
			<div
				ref={ wrapperRef }
				className="drivevault-modern-tabs__wrapper"
				role="tablist"
			>
				<div
					className="drivevault-modern-tabs__indicator"
					style={ {
						width: `${ indicator.width }px`,
						transform: `translateX(${ indicator.left }px)`,
						opacity: isReady ? 1 : 0,
					} }
				/>

				{ tabs.map( ( tab, idx ) => {
					const isActive = active === tab.key;
					return (
						<button
							key={ tab.key }
							ref={ ( el ) => {
								tabsRef.current[ idx ] = el;
							} }
							type="button"
							role="tab"
							aria-selected={ isActive }
							tabIndex={ isActive ? 0 : -1 }
							className={ `drivevault-modern-tabs__item ${
								isActive ? 'is-active' : ''
							}` }
							onClick={ () => onChange( tab.key ) }
						>
							{ tab.icon && (
								<span className="drivevault-modern-tabs__icon">
									{ typeof tab.icon === 'string' ? (
										<span className="drivevault-icon">
											{ tab.icon }
										</span>
									) : (
										tab.icon
									) }
								</span>
							) }
							<span className="drivevault-modern-tabs__title">
								{ tab.title }
							</span>
						</button>
					);
				} ) }
			</div>
		</div>
	);
};

export default Tabs;
