export function getRules(state) {
	return state?.rules || '';
}

export function getUpdating(state) {
	return state?.updating || false;
}

export function getError(state) {
	return state?.error || false;
}
