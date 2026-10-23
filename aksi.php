<?php
declare(strict_types=1);
require __DIR__.'/app/web.php';
$ctx=null;$action='';
try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')throw new HttpError(405,'Tindakan perubahan harus dikirim melalui formulir POST.');
    if((int)($_SERVER['CONTENT_LENGTH']??0)>8*1024*1024)throw new HttpError(413,'Permintaan terlalu besar. Maksimal 3 file, masing-masing 2 MB.');
    $ctx=web_context();require_csrf();
    $action=$_POST['action']??'';if(!is_string($action))throw new HttpError(422,'Tindakan tidak valid.');
    if($action==='session.demo'){
        if(!demo_allowed())throw new HttpError(404,'Fitur tidak tersedia.');
        $account=$_POST['account']??null;
        if(!in_array($account,['me','rani','admin'],true))throw new HttpError(422,'Akun uji tidak valid.');
        if(!query(db(),'SELECT id FROM users WHERE id=?',[$account])->fetch())throw new HttpError(503,'Akun contoh belum tersedia. Impor database/kostips.sql melalui phpMyAdmin.');
        session_regenerate_id(true);$_SESSION['user_id']=$account;$_SESSION['auth_mode']='demo';$_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['flash']='Akun uji berhasil diganti.';redirect_to($account==='admin'?'admin.php':'index.php');
    }
    $actor=require_user();$service=$ctx['service'];$destination='index.php';$message='Perubahan berhasil disimpan.';
    switch($action){
        case 'thread.save':
            $result=$service->threadSave($actor['id'],$_POST,$_FILES['files']??[]);$destination=thread_url($result['id']);$message='Thread berhasil disimpan.';break;
        case 'reply.create':
            $threadId=valid_id($_POST['thread_id']??null);$result=$service->replyCreate($actor['id'],$threadId,$_POST['body']??null);$destination=thread_url($threadId).'#reply-'.$result['id'];$message='Balasan berhasil ditambahkan.';break;
        case 'reply.update':
            $id=valid_id($_POST['id']??null);[$thread]=find_target($ctx,'reply',$id);$service->replyUpdate($actor['id'],$id,$_POST['body']??null);$destination=thread_url($thread['id']).'#reply-'.$id;$message='Balasan berhasil diperbarui.';break;
        case 'thread.delete':case 'reply.delete':case 'attachment.delete':
            $id=valid_id($_POST['id']??null);$type=explode('.',$action)[0];[$thread]=find_target($ctx,$type,$id);$reason=$_POST['reason']??'';
            match($type){'thread'=>$service->threadDelete($actor['id'],$id,$reason),'reply'=>$service->replyDelete($actor['id'],$id,$reason),'attachment'=>$service->attachmentDelete($actor['id'],$id,$reason)};
            $destination=$ctx['admin']?url('admin.php',['tab'=>['thread'=>'threads','reply'=>'replies','attachment'=>'files'][$type]]):($type==='thread'?'koleksi.php':thread_url($thread['id']));$message='Konten berhasil dihapus.';break;
        case 'bookmark.toggle':
            $id=valid_id($_POST['id']??null);$result=$service->bookmarkToggle($actor['id'],$id);$destination=safe_return($_POST['return_to']??null,thread_url($id));$message=$result['saved']?'Thread disimpan ke koleksimu.':'Thread dihapus dari koleksi tersimpan.';break;
        case 'cleanup':
            require_admin($actor);$count=(new UploadStore())->cleanup(db());$destination='admin.php';$message=$count.' pekerjaan pembersihan selesai.';break;
        default:throw new HttpError(404,'Tindakan tidak ditemukan.');
    }
    $_SESSION['flash']=$message;redirect_to($destination);
} catch(Throwable $e){
    // Preserve a thread draft on validation errors; browsers require choosing files again.
    if($ctx&&$e instanceof HttpError&&$e->status===422&&$action==='thread.save'){
        ob_start();try{http_response_code(422);public_headers();render_page('editor',$ctx,['old'=>$_POST,'editId'=>isset($_POST['id'])?valid_id($_POST['id']):'','error'=>$e->getMessage()]);ob_end_flush();}catch(Throwable $renderError){ob_end_clean();page_error($renderError,$ctx);}
    }elseif($ctx&&$e instanceof HttpError&&$e->status===422&&in_array($action,['thread.delete','reply.delete','attachment.delete'],true)){
        ob_start();try{http_response_code(422);public_headers();render_page('delete',$ctx,['type'=>explode('.',$action)[0],'targetId'=>valid_id($_POST['id']??null),'error'=>$e->getMessage(),'reason'=>is_string($_POST['reason']??null)?$_POST['reason']:'']);ob_end_flush();}catch(Throwable $renderError){ob_end_clean();page_error($renderError,$ctx);}
    }else page_error($e,$ctx);
}
