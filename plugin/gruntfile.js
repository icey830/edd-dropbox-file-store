'use strict';

module.exports = function(grunt) {
    var globalConfig = {
        dest: 'bin/edd-dropbox-file-store',
        pluginFolder: 'edd-dropbox-file-store'
    };

    grunt.initConfig({
        globalConfig: globalConfig,
        pkg: grunt.file.readJSON('package.json'),
        clean: ['bin'],
        compress: {
            plugin: {
                options: {
                    archive: 'bin/edd-dropbox-file-store-<%= pkg.version %>.zip',
                    mode: 'zip'
                },
                files : [
                    { cwd: '<%= globalConfig.dest %>', src: ['**'], dest: '/<%= globalConfig.pluginFolder %>/', expand: true}
                ]
            }
        },
        copy: {
            main: {
                files: [
                    {expand: true, src: ['*.php'], dest: '<%= globalConfig.dest %>', filter: 'isFile'},
                    { expand: true, src: ['includes/**'], dest: '<%= globalConfig.dest %>', filter: 'isFile' },
                    { expand: true, src: ['dropbox-sdk/**'], dest: '<%= globalConfig.dest %>', filter: 'isFile' }
                ]
            }
        },
        mkdir: {
            plugin: {
                create: ['<%= globalConfig.pluginFolder %>']
            }
        },
        replace: {
            version: {
                src: ['<%= globalConfig.dest %>/edd-dropbox-file-store.php'],
                overwrite: true,
                replacements: [{
                    from: '0.0.0',
                    to: "<%= pkg.version %>"
                }]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-mkdir');
    grunt.loadNpmTasks('grunt-text-replace');

    grunt.registerTask('default', ['clean', 'mkdir:plugin', 'copy', 'replace:version', 'compress:plugin']);
};