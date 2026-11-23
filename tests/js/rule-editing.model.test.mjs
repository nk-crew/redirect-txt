import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
	ruleEditingReducer,
	visibleRuleIssues,
} from '../../src/admin/page-rules/rule-editing.mjs';

const first = { line: 0, code: 'invalid_rule' };
const second = { line: 1, code: 'absolute_source' };

test('an inspection response leaves the rule being typed unmarked', () => {
	const editedLine = ruleEditingReducer(null, { type: 'input', line: 1 });

	assert.deepEqual(visibleRuleIssues([first, second], editedLine), [first]);
	assert.deepEqual(
		visibleRuleIssues(
			[first, second],
			ruleEditingReducer(editedLine, { type: 'move', line: 1 })
		),
		[first]
	);
});

test('moving to another rule or leaving the editor restores diagnostics', () => {
	const editedLine = ruleEditingReducer(null, { type: 'input', line: 1 });

	for (const exit of [{ type: 'move', line: 0 }, { type: 'blur' }]) {
		assert.deepEqual(
			visibleRuleIssues(
				[first, second],
				ruleEditingReducer(editedLine, exit)
			),
			[first, second]
		);
	}
});

test('editing and inspection responses agree through four events', () => {
	const steps = [
		{ type: 'input', line: 0 },
		{ type: 'input', line: 1 },
		{ type: 'move', line: 0 },
		{ type: 'move', line: 1 },
		{ type: 'blur' },
		{ type: 'response', issues: [first, second] },
		{ type: 'response', issues: [second] },
	];

	function visit(path, editedLine, report) {
		const latestInput = path.findLastIndex(
			(event) => event.type === 'input'
		);
		const line = path[latestInput]?.line;
		const stillEditing =
			latestInput !== -1 &&
			path
				.slice(latestInput + 1)
				.every(
					(event) =>
						event.type !== 'blur' &&
						(event.type !== 'move' || event.line === line)
				);
		const expected = stillEditing
			? report.filter((issue) => issue.line !== line)
			: report;

		assert.deepEqual(
			visibleRuleIssues(report, editedLine),
			expected,
			JSON.stringify(path)
		);

		if (path.length < 4) {
			for (const step of steps) {
				if (step.type === 'response') {
					visit([...path, step], editedLine, step.issues);
				} else {
					visit(
						[...path, step],
						ruleEditingReducer(editedLine, step),
						report
					);
				}
			}
		}
	}

	visit([], null, []);
});
