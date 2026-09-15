<?php

declare(strict_types=1);

// Keep the crash tests independent from PHPUnit and optional integrations.
spl_autoload_register(static function (string $class): void {
    $prefix = 'CoiSA\\ErrorHandler\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

function event(string $name, array $data = []): void
{
    echo json_encode(['event' => $name] + $data, JSON_THROW_ON_ERROR) . "\n";
}
