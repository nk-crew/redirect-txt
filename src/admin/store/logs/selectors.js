import { createSelector } from '@wordpress/data';

export const getLogs = createSelector(
	(state, redirectLogs, notFoundLogs) => {
		if (state?.logs && (redirectLogs || notFoundLogs)) {
			return state.logs.filter((log) => {
				if (log.status === 404) {
					return notFoundLogs;
				}

				return redirectLogs;
			});
		}

		return [];
	},
	(state) => [state?.logs]
);
