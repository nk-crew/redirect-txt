/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export function updateRules(rules) {
	return ({ dispatch }) => {
		dispatch({ type: 'UPDATE_RULES_PENDING' });

		const data = { rules };

		apiFetch({
			path: '/redirect-txt/v1/update_rules',
			method: 'POST',
			data,
		})
			.then((res) => {
				dispatch({
					type: 'UPDATE_RULES_SUCCESS',
					rules,
				});
				return res.response;
			})
			.catch((err) => {
				dispatch({
					type: 'UPDATE_RULES_ERROR',
					error:
						err?.error_code === 'invalid_regex' && err.rule
							? sprintf(
									/* translators: %s: Rule with an invalid regular expression. */
									__(
										'Invalid regular expression in %s',
										'redirect-txt'
									),
									err.rule
								)
							: err?.response ||
								err?.error_code ||
								__(
									'Something went wrong, please, try again…',
									'redirect-txt'
								),
				});
			});
	};
}
