import apiFetch from '@wordpress/api-fetch';
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import Button from '../../components/button';
import Editor from '../../components/editor';
import Input from '../../components/input';

const emptyIssues = [];
const messages = {
	missing_separator: () =>
		__(
			'This rule needs a space after the colon that separates the source and target.',
			'redirect-txt'
		),
	invalid_rule: () =>
		__(
			'Add a source and a target separated by a colon and a space.',
			'redirect-txt'
		),
	unsupported_status: () =>
		__(
			'Use a supported HTTP status for this group of rules.',
			'redirect-txt'
		),
	invalid_regex: () =>
		__(
			'This regular expression is invalid. Edit it before saving.',
			'redirect-txt'
		),
	absolute_source: () =>
		__(
			'Use the path from this site URL as the rule source.',
			'redirect-txt'
		),
	outside_site_source: () =>
		__(
			'This URL cannot be used as a rule source. Enter a path on this site.',
			'redirect-txt'
		),
};

async function inspectDraft(data, signal) {
	const { response } = await apiFetch({
		path: '/redirect-txt/v1/inspect_draft',
		method: 'POST',
		data,
		signal,
	});

	return response;
}

function checkError(error) {
	return (
		(error?.code && error.message) ||
		__('Could not check the draft. Try again.', 'redirect-txt')
	);
}

function useRuleDiagnostics(rules) {
	const [result, setResult] = useState(null);
	const [attempt, setAttempt] = useState(0);

	useEffect(() => {
		if (rules === '') {
			return;
		}

		const request = new AbortController();
		const timer = setTimeout(async () => {
			try {
				const { diagnostics } = await inspectDraft(
					{ rules },
					request.signal
				);

				if (!request.signal.aborted) {
					setResult({ rules, diagnostics });
				}
			} catch (error) {
				if (!request.signal.aborted) {
					setResult({ rules, error: checkError(error) });
				}
			}
		}, 400);

		return () => {
			clearTimeout(timer);
			request.abort();
		};
	}, [rules, attempt]);

	const current = result?.rules === rules ? result : null;

	return {
		issues: current?.diagnostics || emptyIssues,
		error: current?.error,
		retry() {
			setResult(null);
			setAttempt((count) => count + 1);
		},
	};
}

function issueAtY(rows, y) {
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

function preserveLineBreaks(previous, value) {
	if (!previous.includes('\r')) {
		return value;
	}

	const breaks = previous.match(/\r\n|\n|\r/g);
	const lines = value.split('\n');

	return breaks.length === lines.length - 1
		? lines.map((line, index) => line + (breaks[index] ?? '')).join('')
		: value;
}

function useRuleHighlights(frame, issues, matchedLine) {
	const issueRows = useRef([]);

	useLayoutEffect(() => {
		const preview = frame.current.querySelector('.w-tc-editor-preview');
		let marked = [];

		function clear() {
			marked.forEach((row) =>
				row.removeAttribute('data-redirect-txt-mark')
			);
			marked = [];
			issueRows.current = [];
		}

		function mark() {
			clear();

			const rows = preview.querySelectorAll('code > .code-line');
			const matched = rows[matchedLine];

			if (matched) {
				matched.setAttribute('data-redirect-txt-mark', 'match');
				marked.push(matched);
			}

			for (const issue of issues) {
				const node = rows[issue.line];

				if (node) {
					node.setAttribute('data-redirect-txt-mark', 'issue');
					marked.push(node);
					issueRows.current.push({ issue, node });
				}
			}
		}

		if (issues.length || matchedLine !== undefined) {
			// UIW replaces the highlighted markup when its textarea value changes.
			const observer =
				new preview.ownerDocument.defaultView.MutationObserver(mark);
			observer.observe(preview, { childList: true });
			mark();

			return () => {
				observer.disconnect();
				clear();
			};
		}
	}, [frame, issues, matchedLine]);

	return issueRows;
}

function TestResult({ result }) {
	if (!result) {
		return null;
	}

	if (result.pending) {
		return __('Testing address…', 'redirect-txt');
	}

	if (result.error) {
		return result.error;
	}

	switch (result.outcome) {
		case 'matched':
			return (
				<div className="redirect-txt-draft-match">
					<strong>{__('Matched rule', 'redirect-txt')}</strong>
					<code>{result.rule}</code>
					<dl>
						<dt>{__('HTTP status', 'redirect-txt')}</dt>
						<dd>{result.status}</dd>
						{result.location !== null && (
							<>
								<dt>{__('Destination', 'redirect-txt')}</dt>
								<dd>
									<code>{result.location}</code>
								</dd>
							</>
						)}
					</dl>
				</div>
			);
		case 'protected':
			return __('This path is protected from redirects.', 'redirect-txt');
		case 'post_resolution':
			return __(
				'No path rule matched. WordPress must resolve the post before a post ID rule can match.',
				'redirect-txt'
			);
		case 'invalid_address':
			return __(
				'Enter a URL on this site or a path starting with /. Paths are relative to the site homepage.',
				'redirect-txt'
			);
		case 'too_many_rules':
			return sprintf(
				/* translators: %d: Maximum number of path rules a draft address test can evaluate. */
				__(
					'Address testing supports up to %d path rules.',
					'redirect-txt'
				),
				result.limit
			);
		default:
			return __('No rule matched this address.', 'redirect-txt');
	}
}

export default function DraftEditor({ rules, onChange }) {
	const [address, setAddress] = useState('');
	const [result, setResult] = useState(null);
	const [openIssue, setOpenIssue] = useState(null);
	const [hover, setHover] = useState(null);
	const testRequest = useRef(null);
	const textarea = useRef(null);
	const frame = useRef(null);
	const trigger = useRef(null);
	const details = useRef(null);
	const hovered = useRef(null);
	const { issues, error, retry } = useRuleDiagnostics(rules);
	const issueRows = useRuleHighlights(frame, issues, result?.line);
	const selected = issues.includes(openIssue) ? openIssue : null;
	const hoveredIssue =
		!selected && issues.includes(hover?.issue) ? hover : null;

	function clearTest() {
		testRequest.current?.abort();
		setResult(null);
	}

	useEffect(() => () => testRequest.current?.abort(), []);

	useEffect(() => {
		if (!hoveredIssue && !selected) {
			return;
		}

		const ownerDocument = frame.current.ownerDocument;

		function dismiss(event) {
			if (event.key !== 'Escape') {
				return;
			}

			if (
				details.current?.contains(
					details.current.ownerDocument.activeElement
				)
			) {
				trigger.current?.focus();
			}

			setHover(null);
			setOpenIssue(null);
		}

		ownerDocument.addEventListener('keydown', dismiss);
		return () => ownerDocument.removeEventListener('keydown', dismiss);
	}, [hoveredIssue, selected]);

	function changeRules(nextRules) {
		clearTest();
		setOpenIssue(null);
		setHover(null);
		hovered.current = null;
		onChange(nextRules);
	}

	async function testAddress(event) {
		event.preventDefault();

		testRequest.current?.abort();
		const request = new AbortController();
		testRequest.current = request;
		setResult({ pending: true });

		try {
			const response = await inspectDraft(
				{ rules, address },
				request.signal
			);

			if (!request.signal.aborted) {
				setResult(response.test);
			}
		} catch (testError) {
			if (!request.signal.aborted) {
				setResult({ error: checkError(testError) });
			}
		}
	}

	function hoverIssue(event) {
		const row = issueAtY(issueRows.current, event.clientY);

		if (row === hovered.current) {
			return;
		}

		hovered.current = row;
		setHover(
			row
				? {
						issue: row.issue,
						top:
							row.node.getBoundingClientRect().bottom -
							frame.current.getBoundingClientRect().top,
					}
				: null
		);
	}

	function applyCorrection() {
		const parts = rules.split(/(\r\n|\n|\r)/);
		const index = selected.line * 2;
		const start = parts
			.slice(0, index)
			.join('')
			.replace(/\r\n|\r/g, '\n').length;
		const end = start + parts[index].length;
		const field = textarea.current;

		field.focus({ preventScroll: true });
		field.setSelectionRange(start, end);

		// insertText keeps the correction in the browser's native undo history.
		if (
			!field.ownerDocument.execCommand(
				'insertText',
				false,
				selected.replacement
			)
		) {
			field.setRangeText(selected.replacement, start, end, 'end');
		}

		parts[index] = selected.replacement;
		changeRules(parts.join(''));
	}

	return (
		<>
			<div
				ref={frame}
				className="redirect-txt-draft-frame"
				onMouseMove={hoverIssue}
				onMouseLeave={() => {
					hovered.current = null;
					setHover(null);
				}}
			>
				<Editor
					ref={textarea}
					value={rules.replace(/\r\n|\r/g, '\n')}
					language="yaml"
					aria-label={__('Redirect rules', 'redirect-txt')}
					aria-describedby="redirect-txt-rules-help"
					placeholder={__('Enter rules…', 'redirect-txt')}
					onChange={(event) =>
						changeRules(
							preserveLineBreaks(rules, event.target.value)
						)
					}
					onKeyDown={(event) =>
						event.key === 'Tab' ? false : undefined
					}
					padding={15}
				/>
				{hoveredIssue && (
					<div
						aria-hidden="true"
						className="redirect-txt-draft-hover"
						style={{ top: hoveredIssue.top }}
					>
						{messages[hoveredIssue.issue.code]()}
					</div>
				)}
			</div>
			{issues.length > 0 && (
				<div>
					<button
						className="button"
						type="button"
						ref={trigger}
						aria-expanded={!!selected}
						aria-controls="redirect-txt-rule-issue"
						onClick={() =>
							setOpenIssue(selected ? null : issues[0])
						}
					>
						{__('Review rule issues', 'redirect-txt')}
					</button>
					<div
						ref={details}
						id="redirect-txt-rule-issue"
						className="redirect-txt-draft-detail"
						hidden={!selected}
					>
						{selected && (
							<>
								<code>
									{rules.split(/\r\n|\n|\r/)[selected.line]}
								</code>
								<p role="status">{messages[selected.code]()}</p>
								<div className="redirect-txt-draft-actions">
									{selected.replacement !== undefined && (
										<button
											className="button"
											type="button"
											onClick={applyCorrection}
										>
											{selected.code === 'absolute_source'
												? __(
														'Use site path',
														'redirect-txt'
													)
												: __(
														'Insert a space',
														'redirect-txt'
													)}
										</button>
									)}
									{issues.length > 1 && (
										<button
											className="button"
											type="button"
											onClick={() =>
												setOpenIssue(
													issues[
														(issues.indexOf(
															selected
														) +
															1) %
															issues.length
													]
												)
											}
										>
											{__('Next issue', 'redirect-txt')}
										</button>
									)}
								</div>
							</>
						)}
					</div>
				</div>
			)}
			{error && (
				<div className="redirect-txt-draft-actions">
					<span role="status">{error}</span>
					<button
						className="button-link"
						type="button"
						onClick={retry}
					>
						{__('Retry rule check', 'redirect-txt')}
					</button>
				</div>
			)}
			<div
				className="redirect-txt-admin-rules-card-description"
				id="redirect-txt-rules-help"
			>
				{__(
					'Add one rule per line. Separate the source and target with a colon and a space.',
					'redirect-txt'
				)}{' '}
				{__(
					'To redirect an address to the site homepage, use',
					'redirect-txt'
				)}{' '}
				<code>/account/: /</code>.
			</div>
			<form className="redirect-txt-draft-test" onSubmit={testAddress}>
				<label htmlFor="redirect-txt-test-address">
					{__('Site URL or path', 'redirect-txt')}
				</label>
				<div className="redirect-txt-draft-test-field">
					<Input
						id="redirect-txt-test-address"
						value={address}
						placeholder="/account/"
						autoCapitalize="none"
						spellCheck={false}
						onChange={(event) => {
							clearTest();
							setAddress(event.target.value);
						}}
					/>
					<Button
						type="submit"
						disabled={!!result?.pending || !address.trim()}
					>
						{__('Test address', 'redirect-txt')}
					</Button>
				</div>
				<div role="status">
					<TestResult result={result} />
				</div>
			</form>
		</>
	);
}
