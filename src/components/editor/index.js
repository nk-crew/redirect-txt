import './style.scss';

import { forwardRef } from '@wordpress/element';
import clsx from 'clsx';
import CodeEditor from '@uiw/react-textarea-code-editor';

export default forwardRef(function Editor(props, ref) {
	const { className, ...restProps } = props;

	return (
		<CodeEditor
			ref={ref}
			data-color-mode="light"
			className={clsx('redirect-txt-editor', className)}
			{...restProps}
		/>
	);
});
