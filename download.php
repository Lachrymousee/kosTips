<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
try {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD'],true)) throw new HttpError(405,'Metode tidak didukung.');
    $id=valid_id($_GET['id'] ?? null);
    // Current product policy: visible forum threads and their attachments are public.
    // Joining threads ensures a deleted thread can never expose a stale file.
    $file=query(db(),'SELECT a.* FROM attachments a JOIN threads t ON t.id = a.thread_id WHERE a.id = ?',[$id])->fetch();
    if(!$file) throw new HttpError(404,'Lampiran tidak ditemukan.');
    $path=(new UploadStore())->filePath($file['relative_path']);
    if(!is_file($path)) throw new HttpError(404,'File tidak tersedia.');
    $inline=($_GET['inline'] ?? '')==='1' && in_array($file['mime_type'],['image/jpeg','image/png'],true);
    header('Content-Type: '.$file['mime_type']);
    header('Content-Length: '.filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Cache-Control: no-store');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="download.'.pathinfo($path,PATHINFO_EXTENSION).'"; filename*=UTF-8\'\''.rawurlencode($file['original_name']));
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='HEAD') readfile($path);
} catch(HttpError $e) { json_response(['error'=>$e->getMessage()],$e->status); }
catch(Throwable $e) { error_log('kosTips download: '.$e->getMessage()); json_response(['error'=>'File belum dapat diakses.'],500); }
