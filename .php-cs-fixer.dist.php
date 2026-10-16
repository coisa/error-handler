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
    'trailing_comma_in_multiline' => [
        'after_heredoc' => true,
        'elements'     => ['arguments', 'array_destructuring', 'arrays'],
    ],
]);
