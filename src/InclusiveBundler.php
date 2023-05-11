<?php

namespace Aivec\PtBundler;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Used to create ZIP archives for plugin/theme folders
 *
 * Includes all files and folders in the current working directory unless explicitly excluded.
 */
class InclusiveBundler extends BaseBundler
{
    /**
     * List of folders and files to exclude from the final ZIP archive
     *
     * @var string[]
     */
    public $targetsToExclude = [];

    /**
     * Specifies list of folders and files to exclude
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string[] $dirs
     * @return InclusiveBundler
     */
    public function setTargetsToExclude(array $dirs): InclusiveBundler {
        $this->targetsToExclude = $dirs;
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
        $filesystem->mirror($this->basedir, $this->ptname);
        foreach ($this->targetsToExclude as $target) {
            $target = ltrim($target, './');
            $target = ltrim($target, '/');
            $target = $this->ptname . '/' . $target;
            foreach ($this->globstar($target) as $realpath) {
                if ($filesystem->exists($realpath)) {
                    $filesystem->remove($realpath);
                }
            }
        }
    }
}
