import apiFetch from '@wordpress/api-fetch';
import {
	useEffect,
	useLayoutEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Editor from '../../components/editor';
import { ruleEditingReducer, visibleRuleIssues } from './rule-editing.mjs';

const editorPadding = 15;
const noIssues = [];
const messages = {
	invalid_rule: () => __('Use the format /old/: /new/.', 'redirect-txt'),
	unsupported_status: () =>
		__('Use a supported HTTP status.', 'redirect-txt'),
	invalid_regex: () =>
		__('Fix this regular expression before saving.', 'redirect-txt'),
	absolute_source: () =>
		__('Use a path on this site as the source.', 'redirect-txt'),
};

function useRuleIssues(rules) {
	const [result, setResult] = useState(null);

	useEffect(() => {
		if (!rules) {
			return;
		}

		const request = new AbortController();
		const timer = setTimeout(async () => {
			try {
				const { response } = await apiFetch({
					path: '/redirect-txt/v1/inspect_rules',
					method: 'POST',
					data: { rules },
					signal: request.signal,
				});

				if (!request.signal.aborted) {
					setResult({ rules, issues: response });
				}
			} catch {
				// Rule inspection is independent of saving.
			}
		}, 400);

		return () => {
			clearTimeout(timer);
			request.abort();
		};
	}, [rules]);

	return result?.rules === rules ? result.issues : noIssues;
}

function useMarkedRows(frame, issues) {
	const rows = useRef([]);

	useLayoutEffect(() => {
		const preview = frame.current.querySelector('.w-tc-editor-preview');

		function clear() {
			for (const { node } of rows.current) {
				node.removeAttribute('data-redirect-txt-issue');
			}
			rows.current = [];
		}

		function mark() {
			clear();
			const lines = preview.querySelectorAll('code > .code-line');

			for (const issue of issues) {
				const node = lines[issue.line];
				if (node) {
					node.setAttribute('data-redirect-txt-issue', issue.code);
					rows.current.push({ issue, node });
				}
			}
		}

		if (!issues.length) {
			return;
		}

		// UIW replaces the preview nodes when it highlights the edited text.
		const observer = new preview.ownerDocument.defaultView.MutationObserver(
			mark
		);
		observer.observe(preview, { childList: true });
		mark();

		return () => {
			observer.disconnect();
			clear();
		};
	}, [frame, issues]);

	return rows;
}

function rowAtY(rows, y) {
	let first = 0;
	let last = rows.length - 1;

	while (first <= last) {
		const middle = Math.floor((first + last) / 2);
		const row = rows[middle];
		const { top, bottom } = row.node.getBoundingClientRect();

		if (y < top) {
			last = middle - 1;
		} else if (y >= bottom) {
			first = middle + 1;
		} else {
			return row;
		}
	}

	return null;
}

function lineAtCaret(field) {
	return field.value.slice(0, field.selectionStart).split('\n').length - 1;
}

export default function RuleEditor({ rules, onChange }) {
	const frame = useRef(null);
	const tooltip = useRef(null);
	const [active, setActive] = useState(null);
	const [editedLine, updateEditedLine] = useReducer(ruleEditingReducer, null);
	const inspectedIssues = useRuleIssues(rules);
	const issues = useMemo(
		() => visibleRuleIssues(inspectedIssues, editedLine),
		[inspectedIssues, editedLine]
	);
	const rows = useMarkedRows(frame, issues);
	const visible = issues.includes(active?.row.issue) ? active : null;
	const message = visible ? messages[visible.row.issue.code]() : '';

	function show(row, fromCaret = false) {
		setActive((current) => {
			if (!row) {
				return null;
			}
			if (current?.row === row && current.fromCaret === fromCaret) {
				return current;
			}
			return { row, fromCaret };
		});
	}

	function showCaretIssue(event) {
		const field = event.currentTarget;
		if (field.selectionStart !== field.selectionEnd) {
			show(null);
			return;
		}

		const line = lineAtCaret(field);
		updateEditedLine({ type: 'move', line });
		show(
			rows.current.find((row) => row.issue.line === line),
			true
		);
	}

	useLayoutEffect(() => {
		if (!visible) {
			return;
		}

		const bounds = frame.current.getBoundingClientRect();
		const row = visible.row.node.getBoundingClientRect();
		const panel = tooltip.current;
		const textRange = document.createRange();
		textRange.selectNodeContents(visible.row.node);

		const text = textRange.getBoundingClientRect();
		const rtl = window.getComputedStyle(frame.current).direction === 'rtl';
		const textEnd = rtl
			? bounds.right - text.left
			: text.right - bounds.left;
		const inset = editorPadding + 1;
		const inlineStart = textEnd + 8;
		const height = panel.offsetHeight;

		let start = inset;
		let top = bounds.bottom - inset + 4;

		if (
			inlineStart + panel.offsetWidth <= bounds.width - inset &&
			height <= row.height
		) {
			start = inlineStart;
			top = row.top + (row.height - height) / 2;
		} else {
			const above = bounds.top + inset - height - 4;
			const toolbar = document.getElementById('wpadminbar');
			const topEdge = Math.max(
				0,
				toolbar?.getBoundingClientRect().bottom ?? 0
			);

			if (above >= topEdge && row.top - bounds.top < bounds.height / 2) {
				top = above;
			}
		}

		panel.style.insetInlineStart = start + 'px';
		panel.style.top = top - bounds.top + 'px';
	}, [visible]);

	useEffect(() => {
		if (!visible) {
			return;
		}

		function hide() {
			setActive(null);
		}

		function onEscape(event) {
			if (event.key === 'Escape') {
				hide();
			}
		}

		document.addEventListener('keydown', onEscape);
		window.addEventListener('scroll', hide, true);
		window.addEventListener('resize', hide);

		return () => {
			document.removeEventListener('keydown', onEscape);
			window.removeEventListener('scroll', hide, true);
			window.removeEventListener('resize', hide);
		};
	}, [visible]);

	return (
		<div
			ref={frame}
			className="redirect-txt-rule-editor"
			style={{ '--redirect-txt-rules-padding': editorPadding + 'px' }}
			onMouseMove={(event) => {
				if (tooltip.current?.contains(event.target) || event.buttons) {
					return;
				}
				show(rowAtY(rows.current, event.clientY));
			}}
			onMouseLeave={() => show(null)}
		>
			<Editor
				value={rules.replace(/\r\n|\r/g, '\n')}
				language="yaml"
				aria-label={__('Redirect rules', 'redirect-txt')}
				aria-describedby="redirect-txt-rules-help"
				placeholder={__('Enter rules…', 'redirect-txt')}
				onChange={(event) => {
					updateEditedLine({
						type: 'input',
						line: lineAtCaret(event.currentTarget),
					});
					show(null);
					onChange(event.target.value);
				}}
				onMouseDown={() => show(null)}
				onClick={showCaretIssue}
				onSelect={(event) => {
					const field = event.currentTarget;
					if (field.selectionStart === field.selectionEnd) {
						updateEditedLine({
							type: 'move',
							line: lineAtCaret(field),
						});
					}
				}}
				onBlur={() => {
					updateEditedLine({ type: 'blur' });
					show(null);
				}}
				onKeyUp={(event) => {
					if (/^(?:Arrow|Home|End|Page)/.test(event.key)) {
						showCaretIssue(event);
					}
				}}
				padding={editorPadding}
			/>
			{visible && (
				<div
					ref={tooltip}
					className="redirect-txt-rule-tooltip"
					aria-hidden="true"
				>
					{message}
				</div>
			)}
			<div className="screen-reader-text" role="status">
				{visible?.fromCaret ? message : ''}
			</div>
		</div>
	);
}
