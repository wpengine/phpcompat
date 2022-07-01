var gulp = require('gulp');
var wpPot = require('gulp-wp-pot');

function pot () {
  return gulp.src('plugin/*.php')
    .pipe(wpPot({
      domain: 'wpe-php-compat',
      package: 'PHP Compatibility Checker'
    }))
    .pipe(gulp.dest('plugin/languages/wpe-php-compat.pot'));
}

exports.pot = pot;
exports.default = gulp.parallel(pot);
