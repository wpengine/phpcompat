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
		}
	});

	grunt.loadNpmTasks('grunt-wp-readme-to-markdown');

	grunt.registerTask('default', []);
	grunt.registerTask('readme', ['wp_readme_to_markdown']);
};

// Add build status image to GitHub readme.
function addBuildStatus(readme) {
	var buildImage = '<a href="https://circleci.com/gh/wpengine/phpcompat/tree/master"><img src="https://circleci.com/gh/wpengine/phpcompat/tree/master.svg?style=shield"></a>';

	return readme.replace(/# PHP Compatibility Checker #/, '# PHP Compatibility Checker ' + buildImage);
}
