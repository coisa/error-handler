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

use CoiSA\PhpCsFixer\HeaderExtractor;
use CoiSA\PhpCsFixer\PhpCsFixer;

$paths = [
    __FILE__,
    __DIR__,
];

$header = HeaderExtractor::extractFromFile(__FILE__);

return PhpCsFixer::create($paths, $header, [
    // PER3 sections 4/4.4 recommend concise empty bodies (SHOULD); keep multiline bodies
    // to converge with complete PSR12 checking, including braced namespaces.
    // https://github.com/php-fig/per-coding-style/blob/3.0.0/spec.md#44-methods-and-functions
    'single_line_empty_body' => false,
    'braces_position' => [
        'allow_single_line_anonymous_functions' => false,
        'allow_single_line_empty_anonymous_classes' => false,
    ],
]);
