var phpPaths = [
	'wpengine-phpcompat.php',
	'load-files.php',
	'src/*.php',
];

module.exports = function(grunt) {

	grunt.initConfig({
		wp_readme_to_markdown: {
			options: {
				screenshot_url: 'assets/{screenshot}.png',
				post_convert: addBuildStatus,
			},
			your_target: {
				files: {
					'readme.md': 'readme.txt'
				},
			},
		},
		phplint: {
			plugin: phpPaths
		},
		phpcs: {
			plugin: {
				src: phpPaths
			},
			options: {
				bin: 'vendor/bin/phpcs',
				standard: 'src/ruleset-wordpress.xml'
			}
		}
	});

	grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
	grunt.loadNpmTasks('grunt-phplint');
	grunt.loadNpmTasks('grunt-phpcs');

	grunt.registerTask('default', ['phplint', 'phpcs']);

	grunt.registerTask('readme', ['wp_readme_to_markdown']);
};

// Add build status image to GitHub readme.
function addBuildStatus(readme) {
	var buildImage = '<a href="https://travis-ci.org/wpengine/phpcompat"><img src="https://travis-ci.org/wpengine/phpcompat.svg?branch=master"></a>';

	return readme.replace(/# PHP Compatibility Checker #/, '# PHP Compatibility Checker ' + buildImage);
}
