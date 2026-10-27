<?php
$items=[];foreach($ctx['threads'] as $t)foreach($t['replies'] as $r)if(own($r,$ctx))$items[]=['reply'=>$r,'thread'=>$t];
usort($items,fn($a,$b)=>strcmp($b['reply']['createdAt'],$a['reply']['createdAt']));
$pages = max(1, (int) ceil(count($items) / 10));
$number = min($pages, max(1, (int) param('page', '1')));
?>
<div class="page-heading"><div><p class="eyebrow">KOLEKSIMU</p><h1>Balasan saya</h1><p>Ikuti kembali percakapan yang pernah kamu tanggapi.</p></div></div>
<?php foreach(array_slice($items,($number-1)*10,10) as $item):$t=$item['thread'];$r=$item['reply'];?><article class="personal-reply"><a href="<?=h(thread_url($t['id']))?>#reply-<?=h($r['id'])?>"><?=icon('message')?><?=h($t['title'])?></a><p><?=h($r['body'])?></p><small><?=h(date_id($r['createdAt']))?></small><div class="reply-tools"><a class="text-action" href="<?=h(url('thread.php',['id'=>$t['id'],'edit_reply'=>$r['id']]))?>#reply-<?=h($r['id'])?>"><?=icon('edit')?>Edit</a><a class="text-action" href="<?=h(url('hapus.php',['type'=>'reply','id'=>$r['id']]))?>"><?=icon('trash')?>Hapus</a></div></article><?php endforeach;if(!$items)empty_state('Belum ada balasan','Bagikan pengalamanmu dalam salah satu diskusi.');pagination($number,$pages,'balasan.php');?>
