// WordPress 6.2–6.5 lack react-jsx-runtime. Remove this adapter when the minimum reaches 6.6.
import { createElement, Fragment } from '@wordpress/element';

export { Fragment };

export function jsx(type, props, key) {
	return createElement(
		type,
		key === undefined
			? props
			: { ...props, key: props.key === undefined ? key : props.key }
	);
}

export { jsx as jsxs };
