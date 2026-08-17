<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// Start from a clean slate: the functional tests compile real containers into the
// system temp directory and a stale one would silently invalidate the assertions.
$buildDir = sys_get_temp_dir().'/jul6art-push-bundle-tests';

if (is_dir($buildDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }

    @rmdir($buildDir);
}
