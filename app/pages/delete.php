<?php
$type=$data['type']??param('type');$id=$data['targetId']??param('id');
if(!in_array($type,['thread','reply','attachment'],true))throw new HttpError(422,'Jenis konten tidak valid.');
[$thread,$target]=find_target($ctx,$type,$id);
if(!$ctx['me']||(!own($target,$ctx)&&!$ctx['admin']))throw new HttpError(403,'Kamu tidak berhak menghapus konten ini.');
$name=['thread'=>'thread','reply'=>'balasan','attachment'=>'lampiran'][$type];
?>
<div class="page-heading"><div><p class="eyebrow">KONFIRMASI</p><h1>Hapus <?=h($name)?>?</h1><p>Periksa konten berikut sebelum melanjutkan.</p></div></div>
<div class="confirm-panel"><p class="delete-snapshot"><?=h($target['title']??$target['body']??$target['name'])?></p><p>Pada thread <strong><?=h($thread['title'])?></strong>.</p>
<?php if($type==='thread'):?><p>Seluruh balasan dan lampiran pada thread ini juga akan dihapus.</p><?php endif;?><p>Tindakan ini tidak dapat dibatalkan.</p>
<form action="aksi.php" method="post"><?=csrf_input()?><input type="hidden" name="action" value="<?=h($type)?>.delete"><input type="hidden" name="id" value="<?=h($id)?>">
<?php if(isset($data['error'])):?><div class="form-error" role="alert"><?=h($data['error'])?></div><?php endif;?>
<?php if($ctx['admin']):?><div class="field"><label for="reason">Alasan moderasi</label><textarea id="reason" name="reason" minlength="5" maxlength="500" required placeholder="Jelaskan alasan penghapusan"><?=h($data['reason']??'')?></textarea><div class="field-help">5–500 karakter. Alasan dan akun admin akan dicatat dalam log.</div></div><?php endif;?>
<div class="editor-actions"><a class="button" href="<?=h(thread_url($thread['id']))?>">Batal</a><button class="button danger">Hapus <?=h($name)?></button></div></form></div>
