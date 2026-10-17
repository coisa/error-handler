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

namespace CoiSA\ErrorHandler\Test\Documentation;

/**
 * Read a documentation input without silently accepting an unavailable file.
 *
 * @param string $path the selected repository file
 *
 * @return string the complete source bytes
 *
 * @throws \RuntimeException when the input cannot be read
 */
$readFile = static function (string $path): string {
    $contents = file_get_contents($path);
    if (false === $contents) {
        throw new \RuntimeException('Cannot read site input: ' . $path);
    }

    return $contents;
};

$root = \dirname(__DIR__, 2);
$document = new \DOMDocument();
if (!$document->loadHTML($readFile($root . '/docs/index.html'), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
    throw new \RuntimeException('Cannot parse the static documentation page.');
}

if (
    null === $document->documentElement || 'en' !== $document->documentElement->getAttribute('lang')
    || 1 !== $document->getElementsByTagName('h1')->length
    || 1 !== $document->getElementsByTagName('main')->length
) {
    throw new \RuntimeException('The page needs English language, one main landmark and one primary heading.');
}

$ids = [];
foreach ($document->getElementsByTagName('*') as $element) {
    $id = $element->getAttribute('id');
    if ('' !== $id) {
        if (isset($ids[$id])) {
            throw new \RuntimeException('Duplicate site anchor: ' . $id);
        }
        $ids[$id] = true;
    }
}

$links = 0;
foreach ($document->getElementsByTagName('a') as $link) {
    $href = $link->getAttribute('href');
    if (str_starts_with($href, '#')) {
        if (!isset($ids[substr($href, 1)])) {
            throw new \RuntimeException('Missing site anchor: ' . $href);
        }
    } elseif (str_starts_with($href, 'https://')) {
        $prefix = 'https://github.com/coisa/error-handler/blob/master/';
        if (str_starts_with($href, $prefix) && !is_file($root . '/' . substr($href, \strlen($prefix)))) {
            throw new \RuntimeException('Missing repository link target: ' . $href);
        }
    } else {
        throw new \RuntimeException('Expected a page anchor or explicit HTTPS source link: ' . $href);
    }
    ++$links;
}

$snippets = 0;
foreach ($document->getElementsByTagName('pre') as $pre) {
    $codes = $pre->getElementsByTagName('code');
    $code = $codes->item(0);
    if (1 !== $codes->length || !$code instanceof \DOMElement) {
        throw new \RuntimeException('Every source excerpt needs exactly one code element.');
    }

    $source = $code->getAttribute('data-example') ?: $code->getAttribute('data-source');
    if ('' === $source) {
        // Commands have no source excerpt; executable PHP recipes always name their source.
        if (1 !== preg_match('~^(git |cd |composer |php |vendor/bin/phpunit )~', trim($code->textContent))) {
            throw new \RuntimeException('An executable source block must name its example.');
        }

        continue;
    }
    if (1 !== preg_match('~^examples/[a-z-]+\.php$~', $source)) {
        throw new \RuntimeException('Invalid executable example path: ' . $source);
    }

    $snippet = $code->textContent;
    $contents = $readFile($root . '/' . $source);
    if ($code->hasAttribute('data-example')) {
        // Licensing headers are retained in source and omitted only from displayed recipes.
        $contents = preg_replace('~/\*\*.*?\*/\s*\n~s', '', $contents, 1);
        if (null === $contents || trim($snippet) !== trim($contents)) {
            throw new \RuntimeException('Displayed complete recipe differs from source: ' . $source);
        }
        $cookbook = $readFile($root . '/docs/examples.md');
        if (!str_contains($cookbook, trim($snippet))) {
            throw new \RuntimeException('The cookbook is missing the complete recipe: ' . $source);
        }
    } elseif ('' === trim($snippet) || !str_contains($contents, $snippet)) {
        throw new \RuntimeException('Displayed source differs from its executable example: ' . $source);
    }
    ++$snippets;
}

if (0 === $snippets || !is_file($root . '/docs/.nojekyll')) {
    throw new \RuntimeException('The static site needs checked source excerpts and its .nojekyll marker.');
}

$cookbook = $readFile($root . '/docs/examples.md');
$recipeCount = preg_match_all(
    '~<!-- source: (examples/[a-z-]+\.php) -->\R```php\R(.*?\R)```~s',
    $cookbook,
    $recipeMatches,
    PREG_SET_ORDER,
);
if (false === $recipeCount || 0 === $recipeCount || $recipeCount !== preg_match_all('~^```php\R~m', $cookbook)) {
    throw new \RuntimeException('Every cookbook PHP block must name its complete executable recipe source.');
}

$recipes = [];
foreach ($recipeMatches as $recipe) {
    $source = $recipe[1];
    if (isset($recipes[$source])) {
        throw new \RuntimeException('Duplicate cookbook recipe: ' . $source);
    }

    // Remove the fixed license preamble and retain its following separator verbatim.
    $display = preg_replace(
        '~\A(<\?php)\R\R/\*\*\R \* This file is part of coisa/error-handler\..*?\*/~s',
        '$1',
        $readFile($root . '/' . $source),
        1,
        $headers,
    );
    if (null === $display || 1 !== $headers || $display !== $recipe[2]) {
        throw new \RuntimeException('Complete cookbook recipe differs from source: ' . $source);
    }
    $recipes[$source] = true;
}

$exampleFiles = glob($root . '/examples/*.php');
if (false === $exampleFiles) {
    throw new \RuntimeException('Cannot list executable recipe sources.');
}
$expectedRecipes = [];
foreach ($exampleFiles as $exampleFile) {
    $filename = basename($exampleFile);
    if (\in_array($filename, ['bootstrap.php', 'check.php', 'verify.php'], true)) {
        continue;
    }
    $expectedRecipes['examples/' . $filename] = true;
    if (!isset($recipes['examples/' . $filename])) {
        throw new \RuntimeException('The cookbook is missing a complete recipe: ' . $filename);
    }
}
$unexpectedRecipes = array_diff_key($recipes, $expectedRecipes);
if ([] !== $unexpectedRecipes) {
    throw new \RuntimeException('Unexpected cookbook recipes: ' . implode(', ', array_keys($unexpectedRecipes)));
}

printf(
    'Static major documentation passed: %d source excerpts, %d links, '
    . "%d unique anchors and %d complete recipes.\n",
    $snippets,
    $links,
    \count($ids),
    $recipeCount,
);
