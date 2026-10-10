<?php
// Script auxiliar de diagnóstico (solo entorno de demostración).
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$o = App\Models\Order::find(81);
echo '=== ORDEN 81 tras intento del Job ===' . PHP_EOL;
echo 'estado: ' . $o->estado . PHP_EOL;
echo 'auditoria (datos_sincronizacion):' . PHP_EOL;
echo json_encode($o->datos_sincronizacion, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo 'jobs restantes en cola: ' . DB::table('jobs')->count() . PHP_EOL;
echo 'failed_jobs: ' . DB::table('failed_jobs')->count() . PHP_EOL;

