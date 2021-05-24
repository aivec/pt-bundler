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
     * Plugin/theme name
     *
     * Becomes name of the ZIP archive file
     *
     * @var string
     */
    public $ptname;

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
     * Initializes `Bundler` with the plugin/theme name
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $ptname Plugin or theme name. Becomes name of the ZIP archive file
     * @return void
     */
    public function __construct(string $ptname) {
        $this->ptname = $ptname;
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
     * Specifies list of files/folders to delete before build step
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
     * Specifies list of files/folders to delete after build step
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
                if ($filesystem->exists($target)) {
                    $filesystem->remove($target);
                }
            }

            if (is_callable($this->build)) {
                call_user_func($this->build);
            }

            foreach ($this->targetsToCleanAfterBuild as $target) {
                if ($filesystem->exists($target)) {
                    $filesystem->remove($target);
                }
            }

            if (is_callable($this->cleanup)) {
                call_user_func($this->cleanup);
            }

            $filesystem->mkdir($this->ptname, 0755);
            foreach ($this->foldersToInclude as $dir) {
                $filesystem->mirror($dir, $this->ptname . '/' . $dir);
            }
            foreach ($this->filesToInclude as $file) {
                $filesystem->copy($file, $this->ptname . '/' . $file);
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

            self::zipDir("./{$this->ptname}", "./{$this->ptname}.zip");
            self::zipDir("./{$this->ptname}", "./{$this->ptname}.{$version}.zip");

            $filesystem->remove($this->ptname);
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }
    }
}
