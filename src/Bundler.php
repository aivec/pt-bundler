<?php

namespace Aivec\PtBundler;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Used to create ZIP archives for plugin/theme folders
 *
 * Excludes all files and folders in the current working directory unless explicitly included.
 */
class Bundler extends BaseBundler
{
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
     * Handles copying files and folders into the staging folder before creating
     * the ZIP archive
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    protected function copyTargetsToStagingFolder() {
        $filesystem = new Filesystem();
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
    }
}
