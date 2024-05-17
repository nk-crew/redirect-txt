import './style.scss';

import clsx from 'clsx';
import CodeEditor from '@uiw/react-textarea-code-editor';

export default function Editor(props) {
	const { className, ...restProps } = props;

	return (
		<CodeEditor
			data-color-mode="light"
			className={clsx('redirect-txt-editor', className)}
			{...restProps}
		/>
	);
}
