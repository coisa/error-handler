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

$url = $argv[1] ?? '';
if ('127.0.0.1' !== parse_url($url, PHP_URL_HOST) || 'http' !== parse_url($url, PHP_URL_SCHEME)) {
    throw new InvalidArgumentException('Fixture requests must use HTTP loopback.');
}
$context = stream_context_create(['http' => [
    'ignore_errors' => true, 'timeout' => 2, 'follow_location' => 0,
    'max_redirects' => 0, 'header' => 'Connection: close',
]]);
$stream = fopen($url, 'r', false, $context);
if (false === $stream) {
    throw new RuntimeException('Fixture request failed.');
}

try {
    $body = stream_get_contents($stream, 65537);
    $metadata = stream_get_meta_data($stream);
} finally {
    fclose($stream);
}
$headers = $metadata['wrapper_data'] ?? [];
if (!is_string($body) || strlen($body) > 65536 || $metadata['timed_out'] || !is_array($headers)) {
    throw new RuntimeException('Fixture response exceeded a bound or had invalid headers.');
}

echo json_encode(['headers' => $headers, 'body' => $body], JSON_THROW_ON_ERROR);
