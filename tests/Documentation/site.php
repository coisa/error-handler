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
    if (0 === strpos($href, '#')) {
        if (!isset($ids[substr($href, 1)])) {
            throw new \RuntimeException('Missing site anchor: ' . $href);
        }
    } elseif (0 === strpos($href, 'https://')) {
        $prefix = 'https://github.com/coisa/error-handler/blob/master/';
        if (0 === strpos($href, $prefix) && !is_file($root . '/' . substr($href, \strlen($prefix)))) {
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

    $source = $code->getAttribute('data-source');
    if (1 !== preg_match('~^examples/[a-z-]+\.php$~', $source)) {
        throw new \RuntimeException('Every source excerpt must name an executable example: ' . $source);
    }

    $snippet = $code->textContent;
    if ('' === trim($snippet) || false === strpos($readFile($root . '/' . $source), $snippet)) {
        throw new \RuntimeException('Displayed source differs from its executable example: ' . $source);
    }
    ++$snippets;
}

if (0 === $snippets || !is_file($root . '/docs/.nojekyll')) {
    throw new \RuntimeException('The static site needs checked source excerpts and its .nojekyll marker.');
}

printf(
    "Static 2.x site passed: %d source excerpts, %d links and %d unique anchors.\n",
    $snippets,
    $links,
    \count($ids),
);
