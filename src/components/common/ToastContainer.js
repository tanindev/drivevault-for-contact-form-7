import React from 'react';
import Icon from './Icon';

export const ToastContainer = ( { toasts = [] } ) => {
	if ( ! toasts.length ) return null;

	return (
		<div className="drivevault-toast-container">
			{ toasts.map( ( toast ) => (
				<div
					key={ toast.id }
					className={ `drivevault-toast is-${
						toast.type || 'info'
					}` }
				>
					<Icon
						name={
							toast.type === 'success'
								? 'check_circle'
								: toast.type === 'error'
								? 'error'
								: 'info'
						}
						size={ 18 }
					/>
					<span>{ toast.message }</span>
				</div>
			) ) }
		</div>
	);
};

export default ToastContainer;
