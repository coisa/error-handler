<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

// coisa/php-cs-fixer 2.1.0 depends on AbstractRuleSetDescription, removed from
// current PHP CS Fixer. This explicit PSR-12 baseline is not its full CoiSA preset.
return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true])
    ->setFinder(PhpCsFixer\Finder::create()->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
    ])->name('*.php'));
