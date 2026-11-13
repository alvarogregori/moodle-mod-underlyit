'use strict';

module.exports = function(grunt) {
    const banner = `/*!\n` +
        ` * Underly.it activity plugin.\n` +
        ` *\n` +
        ` * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>\n` +
        ` * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later\n` +
        ` */\n`;

    grunt.initConfig({
        uglify: {
            options: {
                banner,
                compress: true,
                mangle: true,
                output: {
                    comments: false,
                    ascii_only: false
                }
            },
            amd: {
                files: [{
                    expand: true,
                    cwd: 'amd/src',
                    src: ['*.js'],
                    dest: 'amd/build',
                    ext: '.min.js'
                }]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.registerTask('amd', ['uglify:amd']);
    grunt.registerTask('default', ['amd']);
};