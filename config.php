<?php
// Default XAMPP lokal. Tidak perlu mengubah file ini jika MySQL masih memakai pengaturan bawaan.
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: __DIR__;
$config = [
    'environment' => 'development',
    'demo_auth' => true,
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kostips_php',
        'username' => 'root',
        'password' => '',
    ],
    // XAMPP standar: C:/xampp/kostips-php-data. Dibuat otomatis, di luar htdocs.
    'storage_path' => dirname($documentRoot) . '/kostips-php-data',
];
// Opsional untuk komputer yang memakai port/password berbeda. File lokal tidak masuk Git.
if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (!is_array($local)) throw new RuntimeException('Konfigurasi lokal harus mengembalikan array.');
    $config = array_replace_recursive($config, $local);
}
return $config;
