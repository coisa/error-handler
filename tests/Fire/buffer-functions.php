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

namespace CoiSA\ErrorHandler\Http\Message;

/**
 * Injects one failed or non-progressing native cleanup result in an isolated child.
 *
 * Native output callbacks cannot safely create buffers while closing a buffer.
 * This namespace seam exercises the defensive stop without invoking that fatal path.
 *
 * @return bool the selected synthetic cleanup result, without closing a buffer
 */
function ob_end_clean(): bool
{
    static $calls = 0;
    $GLOBALS['bufferFixtureCleanupCalls'] = ++$calls;

    return 'no-progress' === ($GLOBALS['bufferFixtureMode'] ?? null);
}
