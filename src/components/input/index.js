import './style.scss';

import clsx from 'clsx';

export default function Input(props) {
	const { className, error, ...restProps } = props;

	return (
		<div
			className={clsx(
				'redirect-txt-input',
				error && 'redirect-txt-input-error',
				className
			)}
		>
			<input {...restProps} />
			{error && (
				<div className="redirect-txt-input-error-text">{error}</div>
			)}
		</div>
	);
}
