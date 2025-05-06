<?php

declare(strict_types=1);

use Ws\DataBridge\Helpers\ContainerHelper;
use Ws\DataBridge\User;

require_once __DIR__ . '/vendor/autoload.php';

$product = ContainerHelper::makeInstance(\Ws\DataBridge\Product::class, [
    'name' => 'Product A'
]);

var_dump($product);

return;

$point = ContainerHelper::makeInstance(\Ws\DataBridge\ImmutablePoint::class, [
    'x' => 10,
    'y' => 20
]);

var_dump($point);

$user = ContainerHelper::makeInstance(User::class, [
    'name' => 'Nguyễn Văn A',
    'age' => 3
]);

var_dump($user);

$address = ContainerHelper::makeInstance(\Ws\DataBridge\Address::class, [
    'street' => '123 Main St',
    'city' => 'Hanoi'
]);

var_dump($address);