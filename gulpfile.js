const path = require('path');

const gulp = require('gulp');
const changeFileContent = require('gulp-change-file-content');
const yargs = require('yargs/yargs');
const parseVersionString = require('parse-version-string').default;

const pkg = require('./package.json');

const currentVersion = pkg.version;
const currentVersionData = parseVersionString(pkg.version);
const bumpFiles = {
	// Version in the package file.
	'./package.json': /("version": ")[0-9][0-9.a-zA-Z-]*(",$)/gm,
	// Version in the readme file.
	'./readme.txt': /(^\* Stable tag: )[0-9][0-9.a-zA-Z-]*($)/gm,
	'./redirect-txt.php': [
		// Version in the file header.
		/(\* Version:\s\s+)[0-9][0-9.a-zA-Z-]*($)/gm,

		// Version in the constant definition.
		/(define\( 'REDIRECT_TXT_VERSION', ').*(' \);$)/gm,
	],
};

// Bump current version number.
//  gulp bump --type major       : bumps 1.0.0
//  gulp bump --type minor       : bumps 0.1.0
//  gulp bump --type patch       : bumps 0.0.2
//  gulp bump --type prerelease  : bumps 0.0.1-alpha.2
gulp.task('bump', (done) => {
	let newVersion = '';

	const { argv } = yargs(process.argv.slice(2));
	const { major, minor, preReleaseType, preReleaseIncrement } =
		currentVersionData;
	let { patch } = currentVersionData;

	switch (argv.type) {
		case 'major':
			newVersion = `${(major || 0) + 1}.0.0`;
			break;
		case 'minor':
			newVersion = `${major || 0}.${(minor || 0) + 1}.0`;
			break;
		case 'prerelease':
			if (!preReleaseIncrement) {
				patch = (patch || 0) + 1;
			}

			newVersion = `${major || 0}.${minor || 0}.${patch || 0}-${
				preReleaseType || 'alpha'
			}.${(preReleaseIncrement || 0) + 1}`;
			break;

		// patch
		default:
			newVersion = `${major || 0}.${minor || 0}.${(patch || 0) + 1}`;
			break;
	}

	Object.keys(bumpFiles).forEach((src) => {
		gulp.src(src)
			.pipe(
				changeFileContent((content) => {
					if (!Array.isArray(bumpFiles[src])) {
						bumpFiles[src] = [bumpFiles[src]];
					}

					bumpFiles[src].forEach((reg) => {
						content = content.replace(reg, `$1${newVersion}$2`);
					});

					return content;
				})
			)
			.pipe(
				gulp.dest((file) => {
					return path.dirname(file.path);
				})
			);
	});

	// eslint-disable-next-line no-console
	console.log(
		`Bumped version from %cv${currentVersion}%c to %cv${newVersion}`,
		'color: #f08d49',
		'',
		'color: #7ec699'
	);

	done();
});
