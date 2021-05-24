<?php

declare(strict_types=1);

use Aivec\PtBundler\Bundler;
use PHPUnit\Framework\TestCase;

final class GlobPatternTest extends TestCase
{
    public function testFileExtensionGlob(): void {
        $bundler = new Bundler('test');
        $this->assertSame(2, count($bundler->globstar('tests/test_plugin/dist/assets/*.css')));
        $this->assertSame(2, count($bundler->globstar('tests/test_plugin/dist/*.map')));
        $doubleglob = $bundler->globstar('tests/test_plugin/dist/assets/*.min.*');
        $this->assertSame(2, count($doubleglob));
        $this->assertSame('tests/test_plugin/dist/assets/css_asset.min.css', $doubleglob[0]);
        $this->assertSame('tests/test_plugin/dist/assets/js_asset.min.js', $doubleglob[1]);
    }

    public function testSubDirRecursiveFileExtensionGlob(): void {
        $bundler = new Bundler('test');
        $this->assertSame(2, count($bundler->globstar('tests/test_plugin/**/*.vue')));
    }

    public function testGlobDirsReturnDirs(): void {
        $bundler = new Bundler('test');
        $dirs = $bundler->globstar('tests/test_plugin/**/deleteme');
        $this->assertSame(3, count($dirs));
        $this->assertSame('tests/test_plugin/deleteme', $dirs[0]);
        $this->assertSame('tests/test_plugin/src/child/grandchild/deleteme', $dirs[1]);
        $this->assertSame('tests/test_plugin/src/deleteme', $dirs[2]);

        $tripleglob = $bundler->globstar('tests/test_plugin/**/*.min.*');
        $this->assertSame(2, count($tripleglob));
        $this->assertSame('tests/test_plugin/dist/assets/css_asset.min.css', $tripleglob[0]);
        $this->assertSame('tests/test_plugin/dist/assets/js_asset.min.js', $tripleglob[1]);
    }

    public function testNoGlob(): void {
        $bundler = new Bundler('test');
        $files = $bundler->globstar('tests/test_plugin/test_plugin.php');
        $this->assertSame(1, count($files));
        $this->assertSame('tests/test_plugin/test_plugin.php', $files[0]);
    }

    public function testLeadingCharGlob(): void {
        $bundler = new Bundler('test');
        $this->assertSame(2, count($bundler->globstar('**/*.vue')));
    }

    public function testLeadingCharGlobForTargetsIsResolved(): void {
        $bundler = new Bundler('test');
        $bundler->setTargetsToCleanBeforeBuild(['**/*.vue']);

        $paths = [];
        foreach ($bundler->targetsToCleanBeforeBuild as $target) {
            foreach ($bundler->globstar($bundler->resolveRelativePath($target)) as $realpath) {
                $paths[] = $realpath;
            }
        }

        $this->assertSame(2, count($paths));
        $this->assertSame(getcwd() . '/tests/test_plugin/src/child/grandchild/some_file.vue', $paths[0]);
        $this->assertSame(getcwd() . '/tests/test_plugin/src/src_file.vue', $paths[1]);
    }
}
