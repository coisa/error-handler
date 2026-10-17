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

namespace CoiSA\ErrorHandler\Test\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Exercises the actual distribution gate with disposable, independently malformed archives.
 *
 * @internal
 *
 * @coversNothing
 */
final class ConsumerSkillArchiveTest extends TestCase
{
    /** @var string an exclusively owned temporary archive directory */
    private string $temporaryDirectory;

    /** @var string the selected temporary tar path */
    private string $archivePath;

    /**
     * Give each archive case an independent location outside application state.
     */
    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/coisa-skill-archive-' . bin2hex(random_bytes(12));

        if (!mkdir($this->temporaryDirectory, 0700)) {
            throw new \RuntimeException('Could not create the isolated archive fixture.');
        }

        $this->archivePath = $this->temporaryDirectory . '/package.tar';
    }

    /**
     * Remove only the archive and directory created by this test.
     */
    protected function tearDown(): void
    {
        if (is_file($this->archivePath) && !unlink($this->archivePath)) {
            throw new \RuntimeException('Could not remove the isolated archive fixture.');
        }

        if (is_dir($this->temporaryDirectory) && !rmdir($this->temporaryDirectory)) {
            throw new \RuntimeException('Could not remove the isolated fixture directory.');
        }
    }

    /**
     * All licensed consumer resources are admitted without broadening other resource paths.
     */
    public function testCompleteCanonicalSkillPassesTheProductionGate(): void
    {
        $this->createArchive();
        $this->expectOutputRegex('/^\d+ distribution files verified against source; no development assets\R$/');

        $this->verifyArchive();
    }

    /**
     * Omitting a nested example must fail even when runtime code remains complete.
     */
    public function testMissingBundledExampleIsRejected(): void
    {
        $path = 'resources/skills/coisa-error-handler/examples/configured.php';
        $this->createArchive($path);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required runtime or consumer metadata is missing: ' . $path);

        $this->verifyArchive();
    }

    /**
     * Packaging cannot silently substitute different consumer instructions.
     */
    public function testChangedConsumerBytesAreRejected(): void
    {
        $path = 'resources/skills/coisa-error-handler/SKILL.md';
        $archive = $this->createArchive();
        $archive->addFromString($path, 'Changed consumer instructions');
        unset($archive);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Distribution bytes differ from the selected source: ' . $path);

        $this->verifyArchive();
    }

    /**
     * Admitting the exact skill prefix must not admit arbitrary resource files.
     */
    public function testOtherResourceDirectoriesRemainForbidden(): void
    {
        $path = 'resources/private-note.txt';
        $archive = $this->createArchive();
        $archive->addFromString($path, 'Synthetic development note');
        unset($archive);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected development asset in distribution: ' . $path);

        $this->verifyArchive();
    }

    /**
     * Build a real tar from required source files, with an optional intentional omission.
     *
     * @param null|string $omittedPath one package-relative member to leave out
     *
     * @return \PharData the created archive for a case-specific mutation
     */
    private function createArchive(?string $omittedPath = null): \PharData
    {
        $root = \dirname(__DIR__, 2);
        $paths = ['composer.json', 'LICENSE', 'README.md', 'CHANGELOG.md'];

        foreach (['src', 'resources/skills/coisa-error-handler'] as $directory) {
            $sourceFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory));

            /** @var \SplFileInfo $source a fixture input from the selected source tree */
            foreach ($sourceFiles as $source) {
                if ($source->isFile()) {
                    $paths[] = substr($source->getPathname(), \strlen($root) + 1);
                }
            }
        }

        $archive = new \PharData($this->archivePath);

        foreach ($paths as $path) {
            if ($path !== $omittedPath) {
                $archive->addFile($root . '/' . $path, $path);
            }
        }

        return $archive;
    }

    /**
     * Run the existing readonly verifier in method scope without installing callbacks.
     */
    private function verifyArchive(): void
    {
        $argv = [__FILE__, $this->archivePath];

        require \dirname(__DIR__) . '/Production/archive.php';
    }
}
