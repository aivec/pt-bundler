<?php

namespace Aivec\PtBundler;

use Symfony\Component\Filesystem\Filesystem;
use Exception;
use Throwable;
use ZipArchive;

/**
 * Used to create ZIP archives for plugin/theme folders
 */
class Bundler
{
    /**
     * Name of filename which contains the name of the ZIP archive file with
     * a version string appended to it
     *
     * @var string
     */
    public static $with_version_appended_fname = 'BUNDLE_FNAME_VERSION_APPENDED';

    /**
     * Name of filename which contains the name of the ZIP archive file without
     * a version string appended to it
     *
     * @var string
     */
    public static $without_version_appended_fname = 'BUNDLE_FNAME_NO_VERSION';

    /**
     * Plugin/theme name
     *
     * Becomes name of the ZIP archive file
     *
     * @var string
     */
    public $ptname;

    /**
     * Base directory where paths should be resolved from.
     *
     * Defaults to `getcwd()`
     *
     * @var string
     */
    public $basedir;

    /**
     * List of folders to include in the archive file
     *
     * @var string[]
     */
    public $foldersToInclude = [];

    /**
     * List of files to include in the archive file
     *
     * @var string[]
     */
    public $filesToInclude = [];

    /**
     * List of files/folders to delete before build step
     *
     * @var string[]
     */
    public $targetsToCleanBeforeBuild = [];

    /**
     * List of files/folders to delete after build step
     *
     * @var string[]
     */
    public $targetsToCleanAfterBuild = [];

    /**
     * List of files/folders to delete from the archive folder
     *
     * @var string[]
     */
    public $archiveTargetsToClean = [];

    /**
     * Build step callback
     *
     * @var callable
     */
    public $build;

    /**
     * Cleanup step callback
     *
     * @var callable
     */
    public $cleanup;

    /**
     * ZIP archive files output dir
     *
     * @var mixed
     */
    public $outdir;

    /**
     * Initializes `Bundler` with the plugin/theme name
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $ptname Plugin or theme name. Becomes name of the ZIP archive file
     * @param string $outdir Where to place the ZIP archive files. Default: 'bundled'
     * @return void
     */
    public function __construct(string $ptname, string $outdir = 'bundled') {
        $this->ptname = $ptname;
        $this->basedir = getcwd();
        if (trim($outdir) === '.') {
            $outdir = './';
        }
        $this->outdir = rtrim(trim($outdir), '/') . '/';
    }

    /**
     * Specifies list of folders to include
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $dirs
     * @return Bundler
     */
    public function setFoldersToInclude(array $dirs): Bundler {
        $this->foldersToInclude = $dirs;
        return $this;
    }

    /**
     * Specifies list of files to include
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $files
     * @return Bundler
     */
    public function setFilesToInclude(array $files): Bundler {
        $this->filesToInclude = $files;
        return $this;
    }

    /**
     * Specifies list of files/folders to delete before build step. Accepts glob patterns.
     *
     * WARNING: **This can delete files/folders from your project (git index). Use with care.**
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $targets
     * @return Bundler
     */
    public function setTargetsToCleanBeforeBuild(array $targets): Bundler {
        $this->targetsToCleanBeforeBuild = $targets;
        return $this;
    }

    /**
     * Specifies list of files/folders to delete after build step. Accepts glob patterns.
     *
     * WARNING: **This can delete files/folders from your project (git index). Use with care.**
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $targets
     * @return Bundler
     */
    public function setTargetsToCleanAfterBuild(array $targets): Bundler {
        $this->targetsToCleanAfterBuild = $targets;
        return $this;
    }

    /**
     * Specifies list of files/folders to delete from the archive folder before it is zipped. Accepts glob patterns.
     *
     * This function does not affect project files. It runs on the archive folder after all required files/folders
     * have been copied into it.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $targets
     * @return Bundler
     */
    public function setArchiveTargetsToClean(array $targets): Bundler {
        $this->archiveTargetsToClean = $targets;
        return $this;
    }

    /**
     * Takes a callable to execute for the build step
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param callable $build
     * @return Bundler
     */
    public function setBuildCallback(callable $build): Bundler {
        $this->build = $build;
        return $this;
    }

    /**
     * Takes a callable to execute after build and clean up finishes
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param callable $cleanup
     * @return Bundler
     */
    public function setCleanupCallback(callable $cleanup): Bundler {
        $this->cleanup = $cleanup;
        return $this;
    }

    /**
     * Extends `glob` to support `**` for recursive subdir matching
     *
     * @see https://gist.github.com/funkjedi/3feee27d873ae2297b8e2370a7082aad
     * @param string $pattern
     * @param int    $flags
     * @return array
     */
    public function globstar(string $pattern, $flags = 0): array {
        if (substr($pattern, 0, 2) === '**') {
            $pattern = './' . $pattern;
        }
        if (stripos($pattern, '**') === false) {
            $files = glob($pattern, $flags);
        } else {
            $position = stripos($pattern, '**');
            $rootPattern = substr($pattern, 0, $position - 1);
            $restPattern = substr($pattern, $position + 2);
            $patterns = [$rootPattern . $restPattern];
            $rootPattern .= '/*';
            while ($dirs = glob($rootPattern, GLOB_ONLYDIR)) {
                $rootPattern .= '/*';
                foreach ($dirs as $dir) {
                    $patterns[] = $dir . $restPattern;
                }
            }
            $files = [];
            foreach ($patterns as $pat) {
                $files = array_merge($files, $this->globstar($pat, $flags));
            }
        }
        $files = array_unique($files);
        sort($files);
        return $files;
    }

    /**
     * Returns absolute path given a path assumed to be relative to `$this->basedir`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $path
     * @return string
     */
    public function resolveRelativePath(string $path): string {
        $path = ltrim($path, './');
        $path = ltrim($path, '/');
        $path = $this->basedir . '/' . $path;
        return $path;
    }

    /**
     * Add files and sub-directories in a folder to zip file.
     *
     * @param string     $folder
     * @param ZipArchive $zipFile
     * @param int        $exclusiveLength Number of text to be exclusived from the file path.
     * @return void
     */
    private static function folderToZip(string $folder, ZipArchive &$zipFile, int $exclusiveLength): void {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    /**
     * Zip a folder (include itself).
     * Usage:
     *   HZip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
     *
     * @param string $sourcePath Path of directory to be zip.
     * @param string $outZipPath Path of output zip file.
     * @return void
     */
    private static function zipDir(string $sourcePath, string $outZipPath): void {
        $pathInfo = pathInfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        $z = new ZipArchive();
        $z->open($outZipPath, ZIPARCHIVE::CREATE);
        $z->addEmptyDir($dirName);
        self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
        $z->close();
    }

    /**
     * Creates ZIP archive file of the plugin/theme with the git version tag number appended
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     * @throws Throwable Thrown for internal execution errors.
     */
    public function createZipArchive(): void {
        exec('git describe', $output);
        $version = '1.0.0';
        if (isset($output)) {
            $version = isset($output[0]) ? $output[0] : $version;
        }
        if (strtolower((string)substr($version, 0, 1)) === 'v') {
            $version = (string)substr($version, 1);
        }

        $filesystem = new Filesystem();

        try {
            foreach ($this->targetsToCleanBeforeBuild as $target) {
                foreach ($this->globstar($this->resolveRelativePath($target)) as $realpath) {
                    if ($filesystem->exists($realpath)) {
                        $filesystem->remove($realpath);
                    }
                }
            }

            if (is_callable($this->build)) {
                call_user_func($this->build);
            }

            foreach ($this->targetsToCleanAfterBuild as $target) {
                foreach ($this->globstar($this->resolveRelativePath($target)) as $realpath) {
                    if ($filesystem->exists($realpath)) {
                        $filesystem->remove($realpath);
                    }
                }
            }

            if (is_callable($this->cleanup)) {
                call_user_func($this->cleanup);
            }

            $filesystem->mkdir($this->ptname, 0755);
            foreach ($this->foldersToInclude as $dir) {
                $dir = ltrim($dir, './');
                $dir = ltrim($dir, '/');
                $filesystem->mirror($this->resolveRelativePath($dir), $this->ptname . '/' . $dir);
            }
            foreach ($this->filesToInclude as $file) {
                $file = ltrim($file, './');
                $file = ltrim($file, '/');
                $filesystem->copy($this->resolveRelativePath($file), $this->ptname . '/' . $file);
            }

            foreach ($this->archiveTargetsToClean as $target) {
                $target = ltrim($target, './');
                $target = ltrim($target, '/');
                $target = $this->ptname . '/' . $target;
                foreach ($this->globstar($this->resolveRelativePath($target)) as $realpath) {
                    if ($filesystem->exists($realpath)) {
                        $filesystem->remove($realpath);
                    }
                }
            }

            // add version number to entry file if applicable (only for plugins)
            $entryfile = "./{$this->ptname}/{$this->ptname}.php";
            if (is_file($entryfile)) {
                $file = file_get_contents($entryfile);
                if ($file !== false) {
                    $file = str_replace('%%VERSION%%', $version, $file);
                    file_put_contents($entryfile, $file);
                }
            }

            if (!$filesystem->exists($this->outdir)) {
                $filesystem->mkdir($this->outdir, 0755);
            }

            $wversionzip = "{$this->ptname}.{$version}.zip";
            $woversionzip = "{$this->ptname}.zip";

            $wversionfname = self::$with_version_appended_fname;
            $woversionfname = self::$without_version_appended_fname;

            file_put_contents("{$this->outdir}{$wversionfname}", $wversionzip);
            file_put_contents("{$this->outdir}{$woversionfname}", $woversionzip);

            self::zipDir("./{$this->ptname}", "{$this->outdir}{$wversionzip}");
            self::zipDir("./{$this->ptname}", "{$this->outdir}{$woversionzip}");

            $filesystem->remove($this->ptname);
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }
    }
}
