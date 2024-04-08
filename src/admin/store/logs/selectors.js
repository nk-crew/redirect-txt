export function getLogs(state, redirectLogs, notFoundLogs) {
	if (state?.logs && (redirectLogs || notFoundLogs)) {
		return state.logs.filter((log) => {
			if (log.status === 404) {
				return notFoundLogs;
			}

			return redirectLogs;
		});
	}

	return [];
}
