<?php
$id=$data['editId']??param('id');$t=$id!==''?find_thread($ctx,$id):null;
if($t&&!own($t,$ctx))throw new HttpError(403,'Kamu hanya boleh mengedit thread milikmu sendiri.');
$old=$data['old']??[];
$value=fn($key,$fallback='')=>is_string($old[$key]??null)?$old[$key]:($t[$key]??$fallback);
$keep=$old['retain_ids']??($t?array_column($t['attachments'],'id'):[]);if(!is_array($keep))$keep=[];
?>
<nav class="breadcrumb"><a href="index.php">Jelajahi</a><?=icon('chevron-right')?><span><?=$t?'Edit thread':'Buat thread'?></span></nav><div class="page-heading"><div><p class="eyebrow">CERITA DARI KOS</p><h1><?=$t?'Perbarui thread':'Mulai diskusi baru'?></h1><p>Ceritakan pertanyaan atau pengalamanmu dengan jelas.</p></div></div>
<form class="editor-form" action="aksi.php" method="post" enctype="multipart/form-data">
<?=csrf_input()?><input type="hidden" name="action" value="thread.save"><?php if($t):?><input type="hidden" name="id" value="<?=h($t['id'])?>"><?php endif;?>
<?php if(isset($data['error'])):?><div class="form-error" role="alert"><?=h($data['error'])?> Jika memilih file baru, silakan pilih kembali.</div><?php endif;?>
<div class="field"><label for="thread-title">Judul thread <span>Wajib</span></label><input id="thread-title" name="title" value="<?=h($value('title'))?>" placeholder="Contoh: Menu praktis apa yang bisa dimasak di kos?" maxlength="150" required><div class="field-help">Maksimal 150 karakter.</div></div>
<div class="field"><label for="thread-category">Kategori <span>Wajib</span></label><select id="thread-category" name="category" required><option value="">Pilih kategori yang sesuai</option><?php foreach(ForumService::CATEGORIES as $cat):?><option value="<?=h($cat)?>" <?=$value('category')===$cat?'selected':''?>><?=h($cat)?></option><?php endforeach;?></select></div>
<div class="field"><label for="thread-body">Isi diskusi <span>Wajib</span></label><textarea id="thread-body" name="body" maxlength="5000" required placeholder="Apa yang ingin kamu tanyakan atau bagikan?"><?=h($value('body'))?></textarea><div class="field-help">Teks biasa, maksimal 5.000 karakter.</div></div>
<?php if($t&&$t['attachments']):?><fieldset class="existing-files"><legend>Lampiran yang sudah tersimpan</legend><p>Centang file yang ingin dipertahankan. File tanpa centang dihapus setelah perubahan disimpan.</p><?php foreach($t['attachments'] as $f):?><label><input type="checkbox" name="retain_ids[]" value="<?=h($f['id'])?>" <?=in_array($f['id'],$keep,true)?'checked':''?>> <span><?=h($f['name'])?></span></label><?php endforeach;?></fieldset><?php endif;?>
<div class="field"><label for="files">Lampiran baru <span>Opsional</span></label><input class="native-file" id="files" name="files[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf"><div class="field-help">JPG, PNG, atau PDF. Maksimal 2 MB/file dan total 3 lampiran/thread. Gambar tampil setelah thread disimpan.</div></div>
<div class="editor-actions"><a class="button" href="<?=$t?h(thread_url($t['id'])):'index.php'?>">Batal</a><button class="button primary"><?=icon('check')?><?=$t?'Simpan perubahan':'Terbitkan thread'?></button></div></form>
