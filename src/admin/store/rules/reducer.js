const { rules } = window.redirectTxtAdminData;

function reducer(
	state = {
		rules,
		updating: false,
		error: '',
	},
	action = {}
) {
	switch (action.type) {
		case 'UPDATE_RULES_PENDING':
			return {
				...state,
				updating: true,
			};
		case 'UPDATE_RULES_SUCCESS':
			return {
				...state,
				updating: false,
				rules: action.rules,
			};
		case 'UPDATE_RULES_ERROR':
			return {
				...state,
				updating: false,
				error: action.error || '',
			};
	}

	return state;
}

export default reducer;
