<?php

require_once 'vendor/autoload.php';

use App\Http\Controllers\Payment\CheckoutController;
use App\Services\MercadoPagoService;
use App\Models\PlanConfig;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing MercadoPagoService...\n";
    $service = new MercadoPagoService();
    echo "✓ MercadoPagoService created\n";
    
    echo "Testing CheckoutController...\n";
    $controller = new CheckoutController($service);
    echo "✓ CheckoutController created\n";
    
    echo "Testing PlanConfig model...\n";
    $plans = PlanConfig::active()->ordered()->get();
    echo "✓ Found " . $plans->count() . " plans\n";
    
    echo "Testing getFrontendConfig...\n";
    $config = $service->getFrontendConfig();
    echo "✓ Config: " . json_encode($config) . "\n";
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
