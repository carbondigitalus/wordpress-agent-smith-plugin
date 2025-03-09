// Core Modules
const fs = require('fs');
const path = require('path');

// NPM Modules
const gulp = require('gulp');
const iconv = require('gulp-iconv-lite');

// Variables
const pathPackageFile = path.resolve(__dirname, 'package.json');
const pathPluginFile = path.resolve(__dirname, 'src/php/agent-smith.php');

// Delay function (takes time in milliseconds)
function delayNextTask(time) {
    return new Promise((resolve) => setTimeout(resolve, time));
}

function getPluginVersion() {
    // Read package.json
    const packageJson = JSON.parse(fs.readFileSync(pathPackageFile, 'utf8'));
    const version = packageJson.version;

    return version;
}

// Gulp task to convert all PHP files encoding from any encoding (like us-ascii) to utf-8
gulp.task('convert-php-encoding', function () {
    return gulp
        .src('./agent-smith/**/*.php') // Include all PHP files inside the 'agent-smith' directory and subdirectories
        .pipe(iconv({ from: 'us-ascii', to: 'utf-8' })) // Convert encoding to utf-8
        .pipe(gulp.dest('./agent-smith')); // Save the converted files back to the same directory
});

// Copy /dist/assets/ folder to the plugin folder (retain `assets` folder)
gulp.task('copy-assets-from-dist-to-plugin-folder', function () {
    return gulp.src('dist/assets/**/*').pipe(gulp.dest('agent-smith/assets'));
});

// Copy /src/assets/img/ folder to the plugin folder inside /assets
// gulp.task('copy-img-to-plugin-assets', function () {
//     return gulp.src('src/assets/img/**/*').pipe(gulp.dest('agent-smith/assets/img'));
// });

// Copy /src/php/ folder to the plugin folder
gulp.task('copy-php-to-plugin-folder', function () {
    return gulp.src('src/php/**/*').pipe(gulp.dest('agent-smith'));
});

// Copy /src/assets/*.xsl files to the plugin folder inside /assets
gulp.task('copy-xsl-to-plugin-assets', function () {
    return gulp.src('src/assets/*.xsl').pipe(gulp.dest('agent-smith/assets'));
});

// Ensure the package directories agent-smith exist
gulp.task('create-plugin-folders', function (done) {
    const coreDir = path.resolve(__dirname, 'agent-smith');
    const pluginDir = path.join(coreDir, 'agent-smith');

    // Check if agent-smith exists, if not create it
    if (!fs.existsSync(coreDir)) {
        fs.mkdirSync(coreDir, { recursive: true });
        console.log(`Created directory: ${coreDir}`);
    }

    // Check if agent-smith directory inside agent-smith exists, if not create it
    if (!fs.existsSync(pluginDir)) {
        fs.mkdirSync(pluginDir, { recursive: true });
        console.log(`Created directory: ${pluginDir}`);
    }

    done();
});

// Gulp task to introduce a delay
gulp.task('delay-next-task', async function (done) {
    const delayTime = 1000; // Delay time in milliseconds (1 second)
    console.log(`Delaying for ${delayTime / 1000} seconds...`);
    await delayNextTask(delayTime);
    done();
});

// Recursively remove empty folders from the agent-smith directory
gulp.task('delete-empty-folders', function (done) {
    const distDir = path.resolve(__dirname, 'agent-smith');

    function removeEmptyDirs(dir) {
        // Check if the directory exists before proceeding
        if (!fs.existsSync(dir)) {
            console.log(`Directory ${dir} does not exist, skipping...`);
            return false;
        }

        const entries = fs.readdirSync(dir, { withFileTypes: true });
        let isEmpty = true;

        entries.forEach((entry) => {
            const entryPath = path.join(dir, entry.name);

            if (entry.isDirectory()) {
                // Recursively remove subdirectories
                if (removeEmptyDirs(entryPath)) {
                    fs.rmdirSync(entryPath);
                    console.log(`Removed empty folder: ${entryPath}`);
                } else {
                    isEmpty = false;
                }
            } else {
                isEmpty = false;
            }
        });

        return isEmpty; // Return true if the directory is empty, otherwise false
    }

    removeEmptyDirs(distDir);
    done();
});

// Clean up directories, excluding zip files
gulp.task('delete-plugin-build-folders', function (done) {
    const distDir = path.resolve(__dirname, 'dist');
    const pluginDir = path.resolve(__dirname, 'agent-smith');

    // Function to clean directories but exclude .zip and critical files like index.php
    function cleanDirectoryExcludingZip(directory) {
        if (fs.existsSync(directory)) {
            fs.readdirSync(directory).forEach((file) => {
                const filePath = path.join(directory, file);
                const stat = fs.statSync(filePath);

                if (stat.isDirectory()) {
                    fs.rmSync(filePath, { recursive: true, force: true }); // Remove the directory itself
                } else if (!file.endsWith('.zip') && file !== 'index.php') {
                    // Exclude index.php
                    console.log(`Deleting file: ${filePath}`);
                    fs.unlinkSync(filePath); // Remove the file
                } else {
                    console.log(`Skipping: ${filePath}`);
                }
            });

            // After cleaning out all files, remove the root directory itself
            fs.rmSync(directory, { recursive: true, force: true }); // Updated to use fs.rmSync instead of fs.rmdirSync
            console.log(`Removed directory: ${directory}`);
        }
    }

    cleanDirectoryExcludingZip(pluginDir);
    cleanDirectoryExcludingZip(distDir);

    done();
});

// Gulp task to sync the version from package.json to the PHP file
gulp.task('update-plugin-version', function (done) {
    // Read package.json
    const version = getPluginVersion();

    // Read PHP file content
    const phpFileContent = fs.readFileSync(pathPluginFile, 'utf8');

    // Replace the version in the PHP file's comment header
    const updatedPhpFileContent = phpFileContent.replace(/Version:\s*\d+\.\d+\.\d+/, `Version:           ${version}`);

    // Write updated content back to the PHP file
    fs.writeFileSync(pathPluginFile, updatedPhpFileContent);

    console.log(`PHP file updated to version ${version}`);
    done();
});

// Gulp task to dynamically load and zip the entire agent-smith folder
gulp.task('zip-plugin-core', async function () {
    // Read package.json
    const version = getPluginVersion();

    const zip = (await import('gulp-zip')).default;
    const pluginDir = 'agent-smith';
    const outputName = `agent-smith-${version}.zip`;

    return gulp
        .src(`${pluginDir}/**/*`, { base: '.' }) // Include the folder itself and all its contents
        .pipe(zip(outputName)) // Create the zip
        .pipe(gulp.dest('./')); // Save it to the root directory
});

// Build task (run all steps in sequence)
gulp.task(
    'build-plugin',
    gulp.series(
        'create-plugin-folders',
        'copy-php-to-plugin-folder',
        'copy-assets-from-dist-to-plugin-folder',
        // 'copy-img-to-plugin-assets', ACTIVATE WHEN IMAGES ARE ADDED TO PLUGIN
        'copy-xsl-to-plugin-assets',
        'convert-php-encoding',
        'delete-empty-folders',
        'zip-plugin-core',
        function (done) {
            console.log('Plugin packaged successfully!');
            done();
        }
    )
);

// Project clean task - plugin version
gulp.task(
    'cleanup-plugin',
    gulp.series('delete-plugin-build-folders', function (done) {
        console.log('Project cleaned successfully!');
        done();
    })
);
