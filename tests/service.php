<?php
declare(strict_types=1);
// Isolated, in-memory SQLite. Never connects to config/local.php or the real DB.
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sqlite.sql'));
$root=sys_get_temp_dir().'/kostips-service-'.bin2hex(random_bytes(8));
$store=new UploadStore($root);$service=new ForumService($db,$store);$passed=0;
function check(bool $condition,string $label): void {global $passed;if(!$condition)throw new RuntimeException('FAIL: '.$label);$passed++;echo "PASS $label\n";}
function denied(int $status,callable $fn,string $label): void {try{$fn();}catch(HttpError $e){check($e->status===$status,$label);return;}throw new RuntimeException('FAIL: '.$label.' was allowed');}
function remove_tree(string $path): void {foreach(scandir($path)?:[] as $f){if($f==='.'||$f==='..')continue;$p="$path/$f";is_dir($p)?remove_tree($p):unlink($p);}rmdir($path);}
try {
 foreach([['a','A','member'],['b','B','member'],['admin','Admin','admin']] as [$id,$name,$role])query($db,'INSERT INTO users(id,name,initials,color,role) VALUES(?,?,?,?,?)',[$id,$name,$name,'#ffffff',$role]);
 $admin=query($db,'SELECT * FROM users WHERE id=?',['admin'])->fetch();$member=query($db,'SELECT * FROM users WHERE id=?',['a'])->fetch();
 $input=['title'=>'../../Tips hemat: makan / kos','body'=>'Isi diskusi','category'=>'Memasak'];
 $t=$service->threadSave('a',$input,[])['id'];$folder=query($db,'SELECT upload_dir FROM threads WHERE id=?',[$t])->fetchColumn();
 check(is_dir($root.'/uploads/'.$folder)&&!str_contains($folder,'..')&&!str_contains($folder,'/'),'folder uses sanitized title plus ID');
 $t2=$service->threadSave('a',$input,[])['id'];check($t!==$t2,'same title has separate thread identity');
 $service->threadSave('a',$input+['id'=>$t],[]);check(query($db,'SELECT upload_dir FROM threads WHERE id=?',[$t])->fetchColumn()===$folder,'folder remains stable on edit');
 denied(403,fn()=>$service->threadSave('b',$input+['id'=>$t],[]),'other member cannot edit thread');
 denied(403,fn()=>$service->threadSave('admin',$input+['id'=>$t],[]),'admin cannot edit someone else’s thread');
 denied(403,fn()=>$service->threadDelete('b',$t),'other member cannot delete thread');
 $r=$service->replyCreate('b',$t,'Balasan B')['id'];
 denied(403,fn()=>$service->replyUpdate('a',$r,'Edit A'),'thread owner cannot edit another reply');
 denied(403,fn()=>$service->replyDelete('a',$r),'thread owner cannot delete another reply');
 denied(403,fn()=>$service->replyUpdate('admin',$r,'Edit admin'),'admin cannot edit another reply');
 $service->replyUpdate('b',$r,'Perubahan B');check(query($db,'SELECT body FROM replies WHERE id=?',[$r])->fetchColumn()==='Perubahan B','owner edits reply');
 denied(422,fn()=>$service->replyCreate('b',$t,''),'empty reply rejected');
 denied(422,fn()=>$service->replyCreate('b',$t,str_repeat('a',2001)),'overlong reply rejected');
 denied(404,fn()=>$service->replyCreate('b','missing','Isi'),'reply to missing thread rejected');
 denied(422,fn()=>$service->replyDelete('admin',$r,''),'moderation requires reason');
 check((bool)query($db,'SELECT id FROM replies WHERE id=?',[$r])->fetch(),'failed moderation keeps reply');
 $db->exec("CREATE TRIGGER block_log BEFORE INSERT ON moderation_logs BEGIN SELECT RAISE(ABORT, 'simulated log failure'); END");
 try{$service->replyDelete('admin',$r,'Melanggar aturan');throw new RuntimeException('Expected DB exception');}catch(PDOException $e){}
 check((bool)query($db,'SELECT id FROM replies WHERE id=?',[$r])->fetch(),'log failure rolls back deletion');$db->exec('DROP TRIGGER block_log');
 $service->replyDelete('admin',$r,'Melanggar aturan diskusi');$logs=$service->logs($admin);
 check(count($logs)===1&&$logs[0]['targetId']===$r&&$logs[0]['actorId']==='admin','moderation log stores actor and deleted target');
 denied(403,fn()=>$service->logs($member),'member cannot read moderation logs');
 check($service->state($member)['logs']===[],'member state excludes moderation logs');
 $own=$service->replyCreate('a',$t,'Balasan A')['id'];$service->replyDelete('a',$own);check(count($service->logs($admin))===1,'ordinary self deletion is not admin moderation');
 $payload="<img src=x onerror=alert(1)> '); DROP TABLE users; --";
 $rid=$service->replyCreate('a',$t,$payload)['id'];check(query($db,'SELECT body FROM replies WHERE id=?',[$rid])->fetchColumn()===$payload,'payload remains literal text');
 check((int)query($db,'SELECT COUNT(*) FROM users')->fetchColumn()===3,'SQL payload does not modify schema');
 // Simulated persisted file exercises cascade cleanup, not the upload validator.
 $path=$folder.'/'.str_repeat('a',32).'.pdf';file_put_contents($store->filePath($path),'%PDF-1.4 test');
 query($db,'INSERT INTO attachments(id,thread_id,user_id,original_name,mime_type,size_bytes,relative_path,created_at) VALUES(?,?,?,?,?,?,?,?)',['file1',$t,'a','test.pdf','application/pdf',13,$path,timestamp()]);
 $service->bookmarkToggle('b',$t);
 $service->threadDelete('admin',$t,'Konten melanggar aturan');
 foreach(['replies','attachments','bookmarks'] as $table)check((int)query($db,"SELECT COUNT(*) FROM $table WHERE thread_id=?",[$t])->fetchColumn()===0,"thread deletion cascades $table");
 check(!file_exists($root.'/uploads/'.$folder),'thread deletion removes physical files and folder');
 check(count($service->logs($admin))===2,'audit logs survive thread cascade');
 query($db,'INSERT INTO file_cleanup(id,relative_path,kind,created_at) VALUES(?,?,?,?)',['missingjob','missing/'.str_repeat('b',32).'.pdf','file',timestamp()]);
 check($store->cleanup($db)===1,'cleanup retry tolerates already missing directory');
 $db->exec('DELETE FROM write_limits');for($i=0;$i<30;$i++)$service->bookmarkToggle('a',$t2);
 denied(429,fn()=>$service->bookmarkToggle('a',$t2),'write rate limit enforced');
 $db->exec('DELETE FROM write_limits');
 // Even an existing admin ID no longer has powers after a DB role change.
 query($db,"UPDATE users SET role='member' WHERE id='admin'");
 denied(403,fn()=>$service->threadDelete('admin',$t2,'Former admin'),'mutation reloads role from database');
 echo "$passed service checks passed.\n";
} finally {remove_tree($root);}
