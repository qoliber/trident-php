<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

// Simple test application for Trident PHP library testing

header('Content-Type: application/json');
header('X-Cache-Tags: page.home,global.header');
header('Cache-Control: public, max-age=3600');

$response = [
    'status' => 'ok',
    'message' => 'Trident PHP Library Test Application',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'headers' => getallheaders(),
    ],
];

echo json_encode($response, JSON_PRETTY_PRINT);
