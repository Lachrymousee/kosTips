<?php
// Opsional: salin sebagai config.local.php HANYA jika pengaturan MySQL berbeda.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kostips_php',
        'username' => 'root',
        'password' => '',
    ],
    // Jika perlu memindahkan file, gunakan folder di luar htdocs:
    // 'storage_path' => 'D:/kostips-php-data',
];
