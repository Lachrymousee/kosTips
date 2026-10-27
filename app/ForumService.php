<?php
declare(strict_types=1);

final class ForumService
{
    public const CATEGORIES = ['Memasak', 'Kebersihan', 'Perawatan Barang', 'Hemat Pengeluaran'];
    private PDO $db;
    private UploadStore $files;

    public function __construct(PDO $db, UploadStore $files) { $this->db = $db; $this->files = $files; }

    private function lockSuffix(): string { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }
    private function thread(string $id, bool $lock = false): array
    {
        $row = query($this->db, 'SELECT * FROM threads WHERE id = ?' . ($lock ? $this->lockSuffix() : ''), [valid_id($id)])->fetch();
        if (!$row) throw new HttpError(404, 'Thread tidak ditemukan.');
        return $row;
    }
    private function owned(array $row, array $actor): void
    {
        if ($row['user_id'] !== $actor['id']) throw new HttpError(403, 'Kamu hanya boleh mengubah konten milikmu sendiri.');
    }
    private function deletable(array $row, array $actor): void
    {
        if ($row['user_id'] !== $actor['id'] && $actor['role'] !== 'admin') throw new HttpError(403, 'Kamu tidak berhak menghapus konten ini.');
    }
    private function transaction(string $actorId, callable $work)
    {
        $this->db->beginTransaction();
        try {
            // Lock the actor first, then the thread: consistent ordering and per-user quota serialization.
            $actor = query($this->db, 'SELECT * FROM users WHERE id = ?' . $this->lockSuffix(), [$actorId])->fetch();
            if (!$actor) throw new HttpError(401, 'Session pengguna tidak valid.');
            $rate = query($this->db, 'SELECT * FROM write_limits WHERE user_id = ?', [$actorId])->fetch();
            $now = time();
            if (!$rate) query($this->db, 'INSERT INTO write_limits(user_id,window_started,request_count) VALUES(?,?,?)', [$actorId,$now,1]);
            elseif ($now - (int)$rate['window_started'] >= 60) query($this->db, 'UPDATE write_limits SET window_started = ?, request_count = 1 WHERE user_id = ?', [$now,$actorId]);
            else {
                if ((int)$rate['request_count'] >= 30) throw new HttpError(429, 'Terlalu banyak perubahan. Coba lagi sebentar.');
                query($this->db, 'UPDATE write_limits SET request_count = request_count + 1 WHERE user_id = ?', [$actorId]);
            }
            $result = $work($actor);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
    private function logDeletion(array $actor, string $type, array $target, string $threadId, $reason): void
    {
        if ($actor['role'] !== 'admin') return;
        $reason = input_text($reason, 'Alasan moderasi', 500);
        if (mb_strlen($reason) < 5) throw new HttpError(422, 'Alasan moderasi minimal 5 karakter.');
        $snapshot = $target['title'] ?? $target['body'] ?? $target['original_name'];
        query($this->db, 'INSERT INTO moderation_logs(id,actor_id,actor_name,action,target_type,target_id,thread_id,target_owner_id,title_snapshot,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
            new_id('l'), $actor['id'], $actor['name'], 'delete_' . $type, $type, $target['id'],
            $threadId, $target['user_id'], mb_substr($snapshot,0,500), $reason, timestamp(),
        ]);
    }
    private function queueCleanup(string $path, string $kind = 'file'): void
    {
        query($this->db, 'INSERT INTO file_cleanup(id,relative_path,kind,created_at) VALUES(?,?,?,?)', [new_id('g'),$path,$kind,timestamp()]);
    }
    public function replyCreate(string $actorId, string $threadId, $body): array
    {
        $body = input_text($body, 'Balasan', 2000);
        return $this->transaction($actorId, function(array $actor) use ($threadId,$body): array {
            $this->thread($threadId, true);
            $id = new_id('r');
            query($this->db, 'INSERT INTO replies(id,thread_id,user_id,body,created_at) VALUES(?,?,?,?,?)', [$id,$threadId,$actor['id'],$body,timestamp()]);
            return ['id' => $id];
        });
    }
    private function reply(string $id, bool $lock = false): array
    {
        $reply = query($this->db, 'SELECT * FROM replies WHERE id = ?' . ($lock ? $this->lockSuffix() : ''), [valid_id($id)])->fetch();
        if (!$reply) throw new HttpError(404, 'Balasan tidak ditemukan.');
        return $reply;
    }
    public function replyUpdate(string $actorId, string $id, $body): array
    {
        $body = input_text($body, 'Balasan', 2000);
        return $this->transaction($actorId, function(array $actor) use ($id,$body): array {
            $reply = $this->reply($id); $this->thread($reply['thread_id'], true);
            $reply = $this->reply($id,true); $this->owned($reply,$actor);
            query($this->db, 'UPDATE replies SET body = ?, updated_at = ? WHERE id = ?', [$body,timestamp(),$id]);
            return ['id'=>$id];
        });
    }
    public function replyDelete(string $actorId, string $id, $reason = ''): array
    {
        return $this->transaction($actorId, function(array $actor) use ($id,$reason): array {
            $reply = $this->reply($id); $this->thread($reply['thread_id'], true);
            $reply = $this->reply($id,true); $this->deletable($reply,$actor);
            $this->logDeletion($actor,'reply',$reply,$reply['thread_id'],$reason);
            query($this->db, 'DELETE FROM replies WHERE id = ?', [$id]);
            return ['deleted'=>true];
        });
    }
    public function threadSave(string $actorId, array $input, array $uploads): array
    {
        $title = input_text($input['title'] ?? null,'Judul',150);
        $body = input_text($input['body'] ?? null,'Isi diskusi',5000);
        $category = $input['category'] ?? null;
        if (!in_array($category,self::CATEGORIES,true)) throw new HttpError(422,'Kategori tidak valid.');
        $id = isset($input['id']) && $input['id'] !== '' ? valid_id($input['id']) : null;
        $retain = $input['retain_ids'] ?? [];
        if (is_string($retain)) {
            try { $retain = json_decode($retain,true,16,JSON_THROW_ON_ERROR); }
            catch (JsonException $e) { throw new HttpError(422,'Daftar lampiran tidak valid.'); }
        }
        if (!is_array($retain) || count($retain)>UploadStore::MAX_FILES) throw new HttpError(422,'Daftar lampiran tidak valid.');
        $retain = array_values(array_unique(array_map('valid_id',$retain)));
        $staged = [];
        try {
            $result = $this->transaction($actorId, function(array $actor) use ($id,$title,$body,$category,$retain,$uploads,&$staged): array {
                $old = $id ? $this->thread($id,true) : null;
                if ($old) $this->owned($old,$actor);
                $attachments = $old ? query($this->db,'SELECT * FROM attachments WHERE thread_id = ?',[$id])->fetchAll() : [];
                foreach ($retain as $keep) if (!in_array($keep,array_column($attachments,'id'),true)) throw new HttpError(403,'Lampiran bukan milik thread ini.');
                $staged = $this->files->stage($uploads);
                if (count($retain)+count($staged)>UploadStore::MAX_FILES) throw new HttpError(422,'Maksimal 3 lampiran per thread.');
                $removed = array_values(array_filter($attachments,fn($f)=>!in_array($f['id'],$retain,true)));
                $used = (int)query($this->db,'SELECT COALESCE(SUM(size_bytes),0) FROM attachments WHERE user_id = ?',[$actor['id']])->fetchColumn();
                if ($used-array_sum(array_column($removed,'size_bytes'))+array_sum(array_column($staged,'size'))>UploadStore::USER_QUOTA_BYTES) throw new HttpError(422,'Kuota lampiran per pengguna adalah 20 MB.');
                $newId = $id ?? new_id('t');
                // A thread keeps its initial folder name even if its title is later edited.
                $folder = $old ? $old['upload_dir'] : folder_name($newId,$title);
                if ($old) query($this->db,'UPDATE threads SET title = ?, body = ?, category = ?, updated_at = ? WHERE id = ?',[$title,$body,$category,timestamp(),$newId]);
                else query($this->db,'INSERT INTO threads(id,user_id,category,title,body,upload_dir,created_at) VALUES(?,?,?,?,?,?,?)',[$newId,$actor['id'],$category,$title,$body,$folder,timestamp()]);
                foreach ($removed as $file) {
                    $this->queueCleanup($file['relative_path']);
                    query($this->db,'DELETE FROM attachments WHERE id = ?',[$file['id']]);
                }
                $this->files->promote($staged,$folder);
                foreach ($staged as $file) query($this->db,'INSERT INTO attachments(id,thread_id,user_id,original_name,mime_type,size_bytes,relative_path,created_at) VALUES(?,?,?,?,?,?,?,?)',[
                    $file['id'],$newId,$actor['id'],$file['name'],$file['type'],$file['size'],$folder.'/'.$file['stored_name'],timestamp(),
                ]);
                return ['id'=>$newId];
            });
        } catch (Throwable $e) { $this->files->discard($staged); throw $e; }
        $this->files->cleanup($this->db);
        return $result;
    }
    public function threadDelete(string $actorId, string $id, $reason = ''): array
    {
        $result = $this->transaction($actorId, function(array $actor) use ($id,$reason): array {
            $thread = $this->thread($id,true); $this->deletable($thread,$actor);
            $this->logDeletion($actor,'thread',$thread,$id,$reason);
            foreach (query($this->db,'SELECT relative_path FROM attachments WHERE thread_id = ?',[$id])->fetchAll() as $file) $this->queueCleanup($file['relative_path']);
            $this->queueCleanup($thread['upload_dir'],'directory');
            query($this->db,'DELETE FROM threads WHERE id = ?',[$id]);
            return ['deleted'=>true];
        });
        $this->files->cleanup($this->db);
        return $result;
    }
    public function attachmentDelete(string $actorId, string $id, $reason = ''): array
    {
        $result = $this->transaction($actorId, function(array $actor) use ($id,$reason): array {
            $file = query($this->db,'SELECT * FROM attachments WHERE id = ?',[valid_id($id)])->fetch();
            if (!$file) throw new HttpError(404,'Lampiran tidak ditemukan.');
            $this->thread($file['thread_id'],true);
            $file = query($this->db,'SELECT * FROM attachments WHERE id = ?' . $this->lockSuffix(),[$id])->fetch();
            if (!$file) throw new HttpError(404,'Lampiran tidak ditemukan.');
            $this->deletable($file,$actor);
            $this->logDeletion($actor,'attachment',$file,$file['thread_id'],$reason);
            $this->queueCleanup($file['relative_path']);
            query($this->db,'DELETE FROM attachments WHERE id = ?',[$id]);
            return ['deleted'=>true];
        });
        $this->files->cleanup($this->db);
        return $result;
    }
    public function bookmarkToggle(string $actorId,string $id): array
    {
        return $this->transaction($actorId,function(array $actor) use($id): array {
            $this->thread($id,true);
            $exists=query($this->db,'SELECT thread_id FROM bookmarks WHERE user_id = ? AND thread_id = ?',[$actor['id'],$id])->fetch();
            if($exists) query($this->db,'DELETE FROM bookmarks WHERE user_id = ? AND thread_id = ?',[$actor['id'],$id]);
            else query($this->db,'INSERT INTO bookmarks(user_id,thread_id) VALUES(?,?)',[$actor['id'],$id]);
            return ['saved'=>!$exists];
        });
    }
    public function logs(array $actor): array
    {
        require_admin($actor);
        $labels=['delete_reply'=>'Menghapus balasan','delete_thread'=>'Menghapus thread','delete_attachment'=>'Menghapus lampiran'];
        return array_map(fn($row)=>[
            'id'=>$row['id'],'actorId'=>$row['actor_id'],'actorName'=>$row['actor_name'],
            'action'=>$labels[$row['action']] ?? $row['action'],'targetType'=>$row['target_type'],
            'targetId'=>$row['target_id'],'threadId'=>$row['thread_id'],'targetOwnerId'=>$row['target_owner_id'],
            'title'=>$row['title_snapshot'],'reason'=>$row['reason'],'at'=>iso_date($row['created_at']),
        ],query($this->db,'SELECT * FROM moderation_logs ORDER BY created_at DESC, id DESC LIMIT 200')->fetchAll());
    }
    public function state(?array $actor): array
    {
        $users=[];
        foreach(query($this->db,'SELECT * FROM users')->fetchAll() as $user) $users[$user['id']]=user_public($user);
        $threads=[];
        foreach(query($this->db,'SELECT * FROM threads ORDER BY created_at DESC')->fetchAll() as $row) $threads[$row['id']]=[
            'id'=>$row['id'],'category'=>$row['category'],'title'=>$row['title'],'body'=>$row['body'],
            'author'=>$users[$row['user_id']],'createdAt'=>iso_date($row['created_at']),'edited'=>$row['updated_at']!==null,'replies'=>[],'attachments'=>[],
        ];
        foreach(query($this->db,'SELECT * FROM replies ORDER BY created_at,id')->fetchAll() as $row) {
            if(!isset($threads[$row['thread_id']])) continue;
            $threads[$row['thread_id']]['replies'][]=['id'=>$row['id'],'body'=>$row['body'],'author'=>$users[$row['user_id']],
                'createdAt'=>iso_date($row['created_at']),'edited'=>$row['updated_at']!==null];
        }
        foreach(query($this->db,'SELECT * FROM attachments ORDER BY created_at,id')->fetchAll() as $row) {
            if(!isset($threads[$row['thread_id']])) continue;
            $threads[$row['thread_id']]['attachments'][]=['id'=>$row['id'],'name'=>$row['original_name'],'type'=>$row['mime_type'],'size'=>(int)$row['size_bytes']];
        }
        $admin=$actor && $actor['role']==='admin';
        return ['threads'=>array_values($threads),'saved'=>$actor?query($this->db,'SELECT thread_id FROM bookmarks WHERE user_id = ?',[$actor['id']])->fetchAll(PDO::FETCH_COLUMN):[],
            'me'=>$actor?user_public($actor):null,'admin'=>(bool)$admin,'logs'=>$admin?$this->logs($actor):[]];
    }
}
