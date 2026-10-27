<?php
declare(strict_types=1);

final class UploadStore
{
    public const MAX_FILE_BYTES = 2097152;
    public const MAX_FILES = 3;
    public const USER_QUOTA_BYTES = 20971520;
    private string $root;

    public function __construct(?string $path = null)
    {
        $path = $path ?? config()['storage_path'];
        if (!is_dir($path) && !mkdir($path, 0750, true)) throw new RuntimeException('Cannot create storage');
        $root = realpath($path);
        if ($root === false) throw new RuntimeException('Invalid storage');
        $public = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
        if (PHP_SAPI !== 'cli' && $public && $this->within($root, $public)) {
            throw new HttpError(503, 'Folder penyimpanan harus di luar folder publik. Atur storage_path pada konfigurasi ke folder di luar htdocs.');
        }
        $this->root = $root;
        foreach (['uploads', 'staging'] as $dir) {
            if (!is_dir($root . '/' . $dir) && !mkdir($root . '/' . $dir, 0750)) throw new RuntimeException('Cannot create storage directory');
        }
    }

    private function within(string $path, string $parent): bool
    {
        $path = str_replace('\\', '/', $path); $parent = rtrim(str_replace('\\', '/', $parent), '/');
        if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $parent = strtolower($parent); }
        return $path === $parent || str_starts_with($path, $parent . '/');
    }

    public function directory(string $folder): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]{1,160}\z/', $folder)) throw new RuntimeException('Invalid storage directory');
        $path = $this->root . '/uploads/' . $folder;
        if (is_link($path)) throw new RuntimeException('Symlink directory is not permitted');
        if (!is_dir($path) && !mkdir($path, 0750)) throw new RuntimeException('Cannot create thread directory');
        return $path;
    }

    public function filePath(string $relative): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]{1,160}\/[a-f0-9]{32}\.(?:jpg|png|pdf)\z/', $relative)) throw new RuntimeException('Invalid stored path');
        $path = $this->root . '/uploads/' . $relative;
        $parent = realpath(dirname($path));
        if (is_link(dirname($path)) || is_link($path)) throw new HttpError(404, 'File tidak tersedia.');
        // Missing directories are safe to treat as missing files during cleanup.
        if ($parent && !$this->within($parent, $this->root . '/uploads')) throw new HttpError(404, 'File tidak tersedia.');
        return $path;
    }

    /** Validates actual uploaded bytes, then moves them to private staging. */
    public function stage(array $files): array
    {
        if ($files === []) return [];
        foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
            if (!isset($files[$key]) || !is_array($files[$key])) throw new HttpError(422, 'Format lampiran tidak valid.');
        }
        if (count($files['name']) > self::MAX_FILES) throw new HttpError(422, 'Maksimal 3 lampiran per thread.');
        $staged = [];
        try {
            foreach ($files['name'] as $i => $name) {
                $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK) throw new HttpError(422, 'Upload gagal atau file melebihi batas server.');
                $tmp = $files['tmp_name'][$i] ?? '';
                if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new HttpError(422, 'Sumber upload tidak valid.');
                $size = filesize($tmp);
                if (!$size || $size > self::MAX_FILE_BYTES) throw new HttpError(422, 'Ukuran file harus 1 byte sampai 2 MB.');
                $name = input_text($name, 'Nama file', 200);
                $name = basename(str_replace('\\', '/', $name));
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                if (!isset($types[$extension]) || $mime !== $types[$extension]) throw new HttpError(422, 'Isi dan ekstensi file harus sesuai JPG, PNG, atau PDF.');
                if ($mime === 'application/pdf') {
                    $handle = fopen($tmp, 'rb'); $signature = fread($handle, 5); fclose($handle);
                    if ($signature !== '%PDF-') throw new HttpError(422, 'Signature PDF tidak valid.');
                } else {
                    $image = @getimagesize($tmp);
                    $type = $mime === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
                    if (!$image || $image[2] !== $type || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > 40000000) {
                        throw new HttpError(422, 'Gambar tidak valid atau dimensinya terlalu besar.');
                    }
                }
                $stored = bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
                $stagePath = $this->root . '/staging/' . bin2hex(random_bytes(24));
                if (!move_uploaded_file($tmp, $stagePath)) throw new RuntimeException('Could not stage upload');
                chmod($stagePath, 0640);
                $staged[] = ['id' => new_id('a'), 'name' => $name, 'type' => $mime, 'size' => $size, 'stored_name' => $stored, 'stage_path' => $stagePath];
            }
            return $staged;
        } catch (Throwable $e) {
            $this->discard($staged);
            throw $e;
        }
    }

    public function promote(array &$files, string $folder): void
    {
        $directory = $this->directory($folder);
        foreach ($files as &$file) {
            $path = $directory . '/' . $file['stored_name'];
            if (!rename($file['stage_path'], $path)) throw new RuntimeException('Could not persist upload');
            $file['promoted_path'] = $path;
        }
    }

    public function discard(array $files): void
    {
        foreach ($files as $file) foreach (['stage_path', 'promoted_path'] as $key) {
            if (isset($file[$key]) && is_file($file[$key])) @unlink($file[$key]);
        }
    }

    /** Process deletion outbox after commit; jobs survive partial filesystem failure. */
    public function cleanup(PDO $db): int
    {
        $pending = query($db, 'SELECT * FROM file_cleanup ORDER BY kind DESC, id ASC')->fetchAll();
        $count = 0;
        foreach ($pending as $job) {
            try {
                if ($job['kind'] === 'file') {
                    if (query($db, 'SELECT id FROM attachments WHERE relative_path = ?', [$job['relative_path']])->fetch()) continue;
                    $path = $this->filePath($job['relative_path']);
                    $removed = !is_file($path) || unlink($path);
                } else {
                    $folder = $job['relative_path'];
                    if (!preg_match('/\A[a-zA-Z0-9_-]{1,160}\z/', $folder)) continue;
                    if (query($db, 'SELECT id FROM threads WHERE upload_dir = ?', [$folder])->fetch()) continue;
                    $path = $this->root . '/uploads/' . $folder;
                    $removed = !file_exists($path) || (!is_link($path) && @rmdir($path));
                }
                if ($removed) { query($db, 'DELETE FROM file_cleanup WHERE id = ?', [$job['id']]); $count++; }
            } catch (Throwable $e) { error_log('kosTips file cleanup pending: ' . $job['id']); }
        }
        return $count;
    }
}
