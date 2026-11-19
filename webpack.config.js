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
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'react/jsx-runtime$': resolve(__dirname, 'src/jsx-runtime.js'),
		},
	},
	plugins: defaultConfig.plugins.map((plugin) => {
		if (plugin.constructor.name !== 'DependencyExtractionWebpackPlugin') {
			return plugin;
		}

		return new plugin.constructor({
			...plugin.options,
			requestToExternal(request) {
				if (request === 'react/jsx-runtime') {
					return false;
				}
			},
		});
	}),
};

module.exports = newConfig;
