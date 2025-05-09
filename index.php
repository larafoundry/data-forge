<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

$request = new Illuminate\Http\Request();
$request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|email',
]);
// Default 404
http_response_code(404);
echo 'Not Found';
