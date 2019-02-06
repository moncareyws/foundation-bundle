var gulp          = require('gulp');
var $             = require('gulp-load-plugins')();
var autoprefixer  = require('autoprefixer');

var sassPaths = [
  'bundles/foundation/node_modules/foundation-sites/scss',
  'bundles/foundation/node_modules/motion-ui/src'
];

function sass() {
  return gulp.src(["../../scss/*.scss","../../scss/*.sass"])
    .pipe($.sass({
      includePaths: sassPaths,
      // outputStyle: 'compressed' // if css compressed **file size**
    })
      .on('error', $.sass.logError))
    .pipe($.postcss([
      autoprefixer({ browsers: ['last 2 versions', 'ie >= 9'] })
    ]))
    .pipe(gulp.dest('../../css'));
};

function watch() {
  gulp.watch(["../../scss/*.scss","../../scss/*.sass"], sass);
}

gulp.task('sass', sass);
gulp.task('default', gulp.series('sass', watch));
