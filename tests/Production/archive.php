<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

if (!isset($argv[1]) || !is_file($argv[1])) {
    throw new InvalidArgumentException('Usage: php tests/Production/archive.php /absolute/package.tar');
}

$root = dirname(__DIR__, 2);
$archivePath = realpath($argv[1]);

if (false === $archivePath) {
    throw new RuntimeException('Unable to resolve the archive path.');
}

$archive = new PharData($archivePath);
$prefix = 'phar://' . $archivePath . '/';
$files = [];

/** @var PharFileInfo $file An archive member supplied by the Phar iterator. */
foreach (new RecursiveIteratorIterator($archive) as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = substr($file->getPathname(), strlen($prefix));

    // Runtime code, useful guides and the canonical skill are distribution assets.
    $allowed = in_array($path, ['composer.json', 'LICENSE', 'README.md', 'CHANGELOG.md'], true)
        ||   str_starts_with($path, 'src/')
        ||   str_starts_with($path, 'docs/')
        ||   str_starts_with($path, 'resources/skills/coisa-error-handler/');

    if (!$allowed) {
        throw new RuntimeException('Unexpected development asset in distribution: ' . $path);
    }

    if (!is_file($root . '/' . $path) || file_get_contents($root . '/' . $path) !== $file->getContent()) {
        throw new RuntimeException('Distribution bytes differ from the selected source: ' . $path);
    }

    $files[$path] = true;
}

$required = ['composer.json', 'LICENSE', 'README.md', 'CHANGELOG.md'];

foreach (['src', 'resources/skills/coisa-error-handler'] as $directory) {
    /** @var SplFileInfo $source A required runtime or consumer skill file from the selected source. */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory)) as $source) {
        if ($source->isFile()) {
            $required[] = substr($source->getPathname(), strlen($root) + 1);
        }
    }
}

foreach ($required as $path) {
    if (!isset($files[$path])) {
        throw new RuntimeException('Required runtime or consumer metadata is missing: ' . $path);
    }
}

echo count($files) . ' distribution files verified against source; no development assets' . PHP_EOL;
