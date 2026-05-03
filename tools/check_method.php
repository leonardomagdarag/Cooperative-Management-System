<?php
require __DIR__ . '/../vendor/autoload.php';
$u = new App\Models\User;
$r = new ReflectionMethod($u, 'checkPermissionTo');
echo $r->getDeclaringClass()->getName() . "\n";
echo "Has own method: " . ($r->getDeclaringClass()->getName() === 'App\\Models\\User' ? 'yes' : 'no') . "\n";
