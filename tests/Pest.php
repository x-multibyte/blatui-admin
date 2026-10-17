<?php

declare(strict_types=1);

use BlatUI\Admin\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Recursively delete a directory and its contents for test cleanups.
 */
function deleteDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($path);
}
