/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PageRules from '../page-rules';
import PageLogs from '../page-logs';

export default {
	rules: {
		label: __('Rules', 'redirect-txt'),
		block: PageRules,
	},
	logs: {
		label: __('Logs', 'redirect-txt'),
		block: PageLogs,
	},
};
