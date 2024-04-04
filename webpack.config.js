/**
 * External Dependencies
 */
const { resolve } = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const newConfig = {
	...defaultConfig,
	...{
		entry: {
			admin: resolve(process.cwd(), 'src/admin', 'index.js'),
		},
	},

	// Display minimum info in terminal.
	stats: 'minimal',
};

module.exports = newConfig;
