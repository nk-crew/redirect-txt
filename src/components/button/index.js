import './style.scss';

import clsx from 'clsx';

export default function Button(props) {
	const { className, ...restProps } = props;

	return (
		<button
			className={clsx('redirect-txt-button', className)}
			{...restProps}
		/>
	);
}
