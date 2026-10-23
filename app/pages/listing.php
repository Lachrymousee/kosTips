<?php
$q=trim(param('q'));$cat=param('cat');$order=param('order','new');$view=param('view','mine');
$file=$page==='collection'?'koleksi.php':'index.php';
$title=$page==='home'?'Diskusi anak kos':($view==='saved'?'Diskusi tersimpan':'Thread saya');
$threads=array_values(array_filter($ctx['threads'],function($t)use($ctx,$q,$cat,$page,$view){
    if($page==='collection'&&($view==='saved'?!in_array($t['id'],$ctx['saved'],true):!own($t,$ctx)))return false;
    return ($cat===''||$t['category']===$cat)&&($q===''||mb_stripos($t['title'],$q)!==false);
}));
usort($threads,$order==='replies'?fn($a,$b)=>count($b['replies'])<=>count($a['replies']):fn($a,$b)=>strcmp($b['createdAt'],$a['createdAt']));
$pages=max(1,(int)ceil(count($threads)/5));$number=min($pages,max(1,(int)param('page','1')));
$params=['q'=>$q,'cat'=>$cat,'order'=>$order];if($page==='collection')$params['view']=$view;
?>
<div class="page-heading"><div><p class="eyebrow"><?=$page==='home'?'JELAJAHI KOSTIPS':'KOLEKSIMU'?></p><h1><?=h($title)?></h1><p><?=$page==='home'?'Dari urusan dapur sampai merawat kamar, obrolkan di sini.':'Temukan kembali diskusi yang kamu buat atau simpan.'?></p></div></div>
<form class="search-form" action="<?=h($file)?>" method="get"><?=icon('search')?><input type="search" name="q" aria-label="Cari judul diskusi" value="<?=h($q)?>" maxlength="150" placeholder="Cari tips, pertanyaan, atau topik diskusi..."><?php if($page==='collection'):?><input type="hidden" name="view" value="<?=h($view)?>"><?php endif;?><input type="hidden" name="cat" value="<?=h($cat)?>"><input type="hidden" name="order" value="<?=h($order)?>"><button class="button primary">Cari</button></form>
<nav class="filter-chips" aria-label="Kategori"><?php foreach(['Semua',...ForumService::CATEGORIES] as $c):$value=$c==='Semua'?'':$c;?><a class="chip <?=$cat===$value?'active':''?>" href="<?=h(url($file,array_replace($params,['cat'=>$value])))?>"><?=h($c)?></a><?php endforeach;?></nav>
<div class="listing-toolbar"><span><?=count($threads)?> diskusi<?=$q!==''?' untuk “'.h($q).'”':''?></span><form action="<?=h($file)?>" method="get" class="sort-form"><?php foreach($params as $key=>$value)if($key!=='order'):?><input type="hidden" name="<?=h($key)?>" value="<?=h($value)?>"><?php endif;?><label for="order">Urutkan</label><select name="order" id="order"><option value="new" <?=$order!=='replies'?'selected':''?>>Terbaru</option><option value="replies" <?=$order==='replies'?'selected':''?>>Balasan terbanyak</option></select><button class="button small">Terapkan</button></form></div>
<section aria-label="Daftar diskusi"><?php foreach(array_slice($threads,($number-1)*5,5) as $t)card($t,$ctx);if(!$threads)empty_state('Belum ada diskusi','Coba kata pencarian lain atau mulai thread baru.');?></section>
<?php pagination($number,$pages,$file,$params);?>
