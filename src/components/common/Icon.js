import React from 'react';

/**
 * DriveVault Icon Component with ligature support and SVG fallback.
 */
export const Icon = ( { name, className = '', size, color, style = {} } ) => {
	const iconSize = size
		? typeof size === 'number'
			? `${ size }px`
			: size
		: '1.25rem';
	const customStyles = {
		...style,
		...( size ? { fontSize: iconSize } : {} ),
		...( color ? { color } : {} ),
	};

	if ( name === 'notifications' || name === 'bell' ) {
		const svgPixelSize =
			typeof size === 'number' ? size : parseInt( size, 10 ) || 20;
		return (
			<svg
				className={ `drivevault-icon-svg ${ className }` }
				width={ svgPixelSize }
				height={ svgPixelSize }
				viewBox="0 0 24 24"
				fill={ color || 'currentColor' }
				style={ {
					verticalAlign: 'middle',
					display: 'inline-block',
					...style,
				} }
				aria-hidden="true"
			>
				<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z" />
			</svg>
		);
	}

	return (
		<span
			className={ `drivevault-icon ${ className }` }
			style={ customStyles }
			aria-hidden="true"
		>
			{ name }
		</span>
	);
};

export default Icon;
