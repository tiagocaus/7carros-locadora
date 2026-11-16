<?php
/** Local disposable fixtures only. No bearer tokens in process arguments. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';
$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
if (\App\Core\Database::env('DB_HOST') !== 'localhost' || !str_starts_with($input['tenant'] ?? '', 'mcp_test_a_')) exit(1);
$c = new \App\Services\Mcp\Context($input['tenant'], $input['employee'], $input['connection'], $input['client'], [], [], false, []);
$security = new \App\Models\Mcp\SecurityState();
try {
    if ($input['operation'] === 'infraction') $security->infraction($c, 'unknown_tool', bin2hex(random_bytes(16)));
    else $security->deliver($c, 1, 0, bin2hex(random_bytes(16)), static function (): void { usleep(50000); });
    echo 'allowed';
} catch (\App\Services\Mcp\SecurityException) { echo 'blocked'; }
