import './style.scss';

import clsx from 'clsx';

export default function Select(props) {
	const { className, error, ...restProps } = props;

	return (
		<div
			className={clsx(
				'redirect-txt-select',
				error && 'redirect-txt-select-error',
				className
			)}
		>
			<select {...restProps} />
			{error && (
				<div className="redirect-txt-select-error-text">{error}</div>
			)}
		</div>
	);
}
