<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/bootstrap.php';

function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function icon(string $name): string {
    static $icons;
    $icons ??= require __DIR__ . '/icons.php';
    return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($icons[$name] ?? $icons['message']).'</svg>';
}
function url(string $page, array $params=[]): string {
    $params=array_filter($params,fn($v)=>$v!=='' && $v!==null);
    return $page.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'');
}
function param(string $name,string $default=''): string {
    $v=$_GET[$name]??$default;
    if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||strlen($v)>2000)throw new HttpError(422,'Parameter halaman tidak valid.');
    return $v;
}
function date_id(string $value,bool $withTime=false): string {
    $d=(new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $months=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return $d->format('j').' '.$months[(int)$d->format('n')-1].' '.$d->format('Y').($withTime?' · '.$d->format('H:i').' WIB':'');
}
function avatar(array $u): string {
    $color=preg_match('/\A#[0-9a-f]{6}\z/i',$u['color']??'')?$u['color']:'#edf0ec';
    return '<span class="avatar" style="background:'.h($color).'">'.h($u['initials']).'</span>';
}
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']??'').'">'; }
function thread_url(string $id): string {return url('thread.php',['id'=>$id]);}
function own(array $object,array $ctx): bool {return isset($ctx['me']['id'])&&$object['author']['id']===$ctx['me']['id'];}
function find_thread(array $ctx,string $id): array {
    valid_id($id);
    foreach($ctx['threads'] as $thread)if($thread['id']===$id)return $thread;
    throw new HttpError(404,'Thread tidak ditemukan.');
}
function find_target(array $ctx,string $type,string $id): array {
    valid_id($id);
    foreach($ctx['threads'] as $thread){
        if($type==='thread'&&$thread['id']===$id)return [$thread,$thread];
        foreach($type==='reply'?$thread['replies']:($type==='attachment'?$thread['attachments']:[]) as $item){
            if($item['id']===$id){if($type==='attachment')$item['author']=$thread['author'];return [$thread,$item];}
        }
    }
    throw new HttpError(404,'Konten tidak ditemukan.');
}
function web_context(): array {
    start_session();
    $service=new ForumService(db(),new UploadStore());
    $actor=current_user();
    return $service->state($actor)+['service'=>$service,'actor'=>$actor,'demoMode'=>demo_allowed()];
}
function public_headers(): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Referrer-Policy: same-origin');
}
function render_page(string $page,array $ctx,array $data=[]): void {
    $views=['home'=>'listing','collection'=>'listing','thread'=>'thread','editor'=>'editor','replies'=>'replies','admin'=>'admin','delete'=>'delete','about'=>'about','error'=>'error'];
    $titles=['home'=>'Diskusi anak kos','collection'=>'Koleksimu','thread'=>'Diskusi','editor'=>'Tulis thread','replies'=>'Balasan saya','admin'=>'Panel admin','delete'=>'Konfirmasi penghapusan','about'=>'Tentang kosTips','error'=>'Permintaan belum berhasil'];
    $pageTitle=$data['title']??$titles[$page];
    $flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
    require __DIR__.'/layout-top.php';
    require __DIR__.'/pages/'.$views[$page].'.php';
    require __DIR__.'/layout-bottom.php';
}
function page_error(Throwable $error,?array $ctx=null): void {
    $status=$error instanceof HttpError?$error->status:500;
    if(!($error instanceof HttpError))error_log('kosTips: '.get_class($error).': '.$error->getMessage());
    http_response_code($status);public_headers();
    $ctx??=['me'=>null,'admin'=>false,'demoMode'=>false,'threads'=>[],'saved'=>[],'logs'=>[]];
    render_page('error',$ctx,['status'=>$status,'message'=>$error instanceof HttpError?$error->getMessage():'Aplikasi belum dapat mengakses database atau folder penyimpanan. Periksa langkah setup di README.']);
}
function run_page(string $page): void {
    $ctx=null;ob_start();
    try {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new HttpError(405,'Halaman ini dibuka dengan GET.');
        $ctx=web_context();
        if($page==='admin')require_admin(require_user());
        if(in_array($page,['collection','replies','editor','delete'],true))require_user();
        if($ctx['admin']&&in_array($page,['collection','replies'],true))redirect_to('admin.php');
        public_headers();render_page($page,$ctx);ob_end_flush();
    } catch(Throwable $e){ob_end_clean();page_error($e,$ctx);}
}
function redirect_to(string $path): never {header('Location: '.$path,true,303);exit;}
function safe_return($value,string $fallback='index.php'): string {
    if(!is_string($value)||strlen($value)>2000||preg_match('/[\x00-\x20\x7f\\\\]/',$value))return $fallback;
    $parts=parse_url($value);
    if(!$parts||isset($parts['host'])||isset($parts['scheme'])||!in_array($parts['path']??'',['index.php','thread.php','koleksi.php','balasan.php','admin.php'],true))return $fallback;
    return $value;
}
function request_return(): string {
    return safe_return(basename($_SERVER['SCRIPT_NAME']??'index.php').(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''));
}
function badge(string $category): string {return '<span class="category-badge">'.h($category).'</span>';}
function empty_state(string $title,string $text): void {echo '<div class="empty-state"><h2>'.h($title).'</h2><p>'.h($text).'</p><a class="button secondary" href="index.php">Jelajahi diskusi</a></div>';}
function pagination(int $page,int $pages,string $file,array $params=[]): void {
    if($pages<2)return;
    echo '<nav class="pagination-row" aria-label="Halaman"><span>Halaman '.$page.' dari '.$pages.'</span><div class="pagination">';
    // Keep navigation bounded even for large page counts.
    $numbers=array_unique(array_merge([1],range(max(1,$page-2),min($pages,$page+2)),[$pages]));$prev=0;
    foreach($numbers as $n){if($prev&&$n>$prev+1)echo '<span>…</span>';echo '<a class="'.($n===$page?'active':'').'" '.($n===$page?'aria-current="page"':'').' href="'.h(url($file,$params+['page'=>$n])).'">'.$n.'</a>';$prev=$n;}
    echo '</div></nav>';
}
function save_form(array $thread,array $ctx): void {
    if(!$ctx['me'])return;
    $saved=in_array($thread['id'],$ctx['saved'],true);
    echo '<form action="aksi.php" method="post" class="inline-form">'.csrf_input().'<input type="hidden" name="action" value="bookmark.toggle"><input type="hidden" name="id" value="'.h($thread['id']).'"><input type="hidden" name="return_to" value="'.h(request_return()).'"><button class="text-action '.($saved?'saved':'').'" type="submit" aria-label="'.($saved?'Batal simpan':'Simpan').' '.h($thread['title']).'">'.icon('bookmark').($saved?'Tersimpan':'Simpan').'</button></form>';
}
function card(array $thread,array $ctx): void {
    $image=null;foreach($thread['attachments'] as $file)if(str_starts_with($file['type'],'image/')){$image=$file;break;}
    ?><article class="thread-card">
    <div class="thread-meta"><div class="author-info"><?=avatar($thread['author'])?><span class="author-text"><?=h($thread['author']['name'])?><?=own($thread,$ctx)?'<span class="owner-tag">Kamu</span>':''?><time datetime="<?=h($thread['createdAt'])?>"><?=h(date_id($thread['createdAt']))?></time></span></div><?=badge($thread['category'])?></div>
    <div class="thread-card-body <?=$image?'has-image':''?>"><div class="thread-summary"><h2 class="thread-title"><a href="<?=h(thread_url($thread['id']))?>"><?=h($thread['title'])?></a></h2><p class="thread-excerpt"><?=h(mb_strimwidth($thread['body'],0,240,'…'))?></p></div>
    <?php if($image):?><a href="<?=h(thread_url($thread['id']))?>" class="thread-preview"><img src="<?=h(url('download.php',['id'=>$image['id'],'inline'=>1]))?>" alt="<?=h($image['name'])?>" loading="lazy"></a><?php endif;?></div>
    <div class="thread-actions"><a class="text-action" href="<?=h(thread_url($thread['id']))?>#replies"><?=icon('message')?><?=count($thread['replies'])?> balasan</a><a class="text-action" href="<?=h(thread_url($thread['id']))?>#bagikan"><?=icon('share')?>Bagikan</a><?php if($thread['attachments']):?><span class="attachment-count"><?=icon('paperclip')?><?=count($thread['attachments'])?> lampiran</span><?php endif;?><span class="save-action"><?php save_form($thread,$ctx);?></span></div></article><?php
}
function full_thread_link(string $id): string {
    $host=$_SERVER['HTTP_HOST']??'localhost';
    if(!preg_match('/\A(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]+)?\z/',$host))$host='localhost';
    $folder=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php')),'/');
    return ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$host.$folder.'/'.thread_url($id);
}
