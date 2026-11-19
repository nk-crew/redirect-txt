const wordpress = require('@wordpress/eslint-plugin');
const wpPackagesRegExp = '^@wordpress/(?!(icons|interface|style-engine))';

module.exports = [
	{ ignores: ['**/.*', '**/build/**', '**/node_modules/**', '**/vendor/**'] },
	{ files: ['**/*.jsx', '**/*.ts', '**/*.tsx'] },
	...wordpress.configs.recommended,
	{
		settings: {
			'import/internal-regex': wpPackagesRegExp,
		},
		rules: {
			'import/no-unresolved': ['error', { ignore: [wpPackagesRegExp] }],
		},
	},
];
