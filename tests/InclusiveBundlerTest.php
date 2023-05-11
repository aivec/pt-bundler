<?php

declare(strict_types=1);

use Aivec\PtBundler\InclusiveBundler;
use PHPUnit\Framework\TestCase;

final class InclusiveBundlerTest extends TestCase
{
    public function testAllFilesAndFoldersIncludedByDefault(): void {
        $bundler = new InclusiveBundler('test_plugin', 'bundled', __DIR__ . '/test_plugin');
        $bundler->setTargetsToExclude([
            'deleteme',
            'src/src_file.vue',
            'dist/**/*.min.*',
        ]);
        $bundler->prepareStagingFolder($bundler->getPtVersion());
        $this->assertFileDoesNotExist($bundler->ptname . '/deleteme');
        $this->assertFileDoesNotExist($bundler->ptname . '/src/src_file.vue');
        $this->assertFileDoesNotExist($bundler->ptname . '/dist/assets/css_asset.min.css');
        $this->assertFileDoesNotExist($bundler->ptname . '/dist/assets/js_asset.min.js');
        $bundler->deleteStagingFolder();
    }
}
