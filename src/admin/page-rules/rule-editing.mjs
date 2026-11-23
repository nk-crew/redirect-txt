export function ruleEditingReducer(editedLine, { type, line }) {
	if (type === 'input') {
		return line;
	}

	if (type === 'move' && line === editedLine) {
		return editedLine;
	}

	return null;
}

export function visibleRuleIssues(issues, editedLine) {
	return editedLine === null
		? issues
		: issues.filter((issue) => issue.line !== editedLine);
}
