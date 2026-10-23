<?php
require_admin(require_user());$tab=param('tab','threads');
$labels=['threads'=>'Thread','replies'=>'Balasan','files'=>'Lampiran','logs'=>'Log moderasi'];if(!isset($labels[$tab]))throw new HttpError(404,'Tab admin tidak ditemukan.');
$items=[];
if($tab==='threads')foreach($ctx['threads'] as $t)$items[]=['target'=>$t,'thread'=>$t];
elseif($tab==='logs')$items=$ctx['logs'];
else foreach($ctx['threads'] as $t)foreach($tab==='replies'?$t['replies']:$t['attachments'] as $item)$items[]=['target'=>$item,'thread'=>$t];
$pages=max(1,(int)ceil(count($items)/10));$number=min($pages,max(1,(int)param('page','1')));
$pending=(int)query(db(),'SELECT COUNT(*) FROM file_cleanup')->fetchColumn();
?>
<div class="page-heading"><div><p class="eyebrow">PENGELOLAAN</p><h1>Panel admin</h1><p>Tinjau thread, balasan, dan lampiran agar diskusi tetap nyaman.</p></div></div>
<div class="moderation-notice">Penghapusan oleh admin memerlukan alasan. Tulisan pengguna lain tidak dapat diedit. Log menampilkan hingga 200 tindakan terbaru.</div>
<?php if($pending):?><form class="cleanup-notice" action="aksi.php" method="post"><?=csrf_input()?><input type="hidden" name="action" value="cleanup"><span><?=h($pending)?> pekerjaan pembersihan file masih tertunda.</span><button class="button small">Coba bersihkan lagi</button></form><?php endif;?>
<nav class="moderation-tabs" aria-label="Jenis moderasi"><?php foreach($labels as $key=>$label):?><a class="<?=$key===$tab?'active':''?>" href="<?=h(url('admin.php',['tab'=>$key]))?>"><?=h($label)?></a><?php endforeach;?></nav>
<?php if(!$items):empty_state('Belum ada konten','Konten atau tindakan yang sesuai akan tampil di sini.');endif;?>
<?php if($tab==='logs'):?><ol class="mod-log"><?php foreach(array_slice($items,($number-1)*10,10) as $log):?><li><strong><?=h($log['action'])?></strong><p><?=h($log['title'])?></p><p><?=h(date_id($log['at'],true))?> · <?=h($log['actorName'])?></p><p><strong>Alasan:</strong> <?=h($log['reason'])?></p><small><?=h($log['targetType'])?> · <?=h($log['targetId'])?><br>Pemilik: <?=h($log['targetOwnerId'])?> · Thread: <?=h($log['threadId'])?></small></li><?php endforeach;?></ol>
<?php else:foreach(array_slice($items,($number-1)*10,10) as $row):$t=$row['thread'];$item=$row['target'];$type=['threads'=>'thread','replies'=>'reply','files'=>'attachment'][$tab];?>
<div class="moderation-row"><div><?php if($tab==='threads'):?><strong><a href="<?=h(thread_url($t['id']))?>"><?=h($t['title'])?></a></strong><small><?=h($t['author']['name'])?> · <?=h($t['category'])?> · <?=count($t['replies'])?> balasan</small>
<?php elseif($tab==='replies'):?><strong><?=h($item['author']['name'])?></strong><p><?=h($item['body'])?></p><small>Pada <a href="<?=h(thread_url($t['id']))?>"><?=h($t['title'])?></a></small>
<?php else:?><strong><?=h($item['name'])?></strong><small><?=h($t['author']['name'])?> · <a href="<?=h(thread_url($t['id']))?>"><?=h($t['title'])?></a></small><?php endif;?></div>
<div class="moderation-file-actions"><?php if($tab==='files'):?><a class="text-action" href="<?=h(url('download.php',['id'=>$item['id']]))?>"><?=icon('download')?>Unduh</a><?php endif;?><a class="text-action" href="<?=h(url('hapus.php',['type'=>$type,'id'=>$item['id']]))?>"><?=icon('trash')?>Hapus</a></div></div>
<?php endforeach;endif;pagination($number,$pages,'admin.php',['tab'=>$tab]);?>
