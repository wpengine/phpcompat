var gulp = require('gulp');
var gulpMinify = require('gulp-minify');
var wpPot = require('gulp-wp-pot');

function minify () {

  return gulp.src(['plugin/scripts/*.js'])
    .pipe(gulpMinify({
      ignoreFiles: ['*-min.js']
    }))
    .pipe(gulp.dest('plugin/scripts'));

}

function pot () {
  return gulp.src('plugin/*.php')
    .pipe(wpPot({
      domain: 'wpe-php-compat',
      package: 'PHP Compatibility Checker'
    }))
    .pipe(gulp.dest('plugin/languages/wpe-php-compat.pot'));
}

exports.minify = minify;
exports.pot = pot;
exports.default = gulp.parallel(pot, minify);
