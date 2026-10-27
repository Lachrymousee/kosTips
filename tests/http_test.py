"""Isolated PHP form tests. Python 3 + PHP with pdo_sqlite; no browser JS.
Creates a temporary copy and DB. Does not touch the configured MySQL database.
Run: python tests/http_test.py --php php
"""
from pathlib import Path
from html.parser import HTMLParser
import argparse,base64,http.cookiejar,json,re,shutil,socket,sqlite3,subprocess,tempfile,time,urllib.request,urllib.error,urllib.parse,uuid
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j9FAAAAAASUVORK5CYII=')
PDF=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None
class HTML(HTMLParser):
    def __init__(self,text):
        super().__init__(convert_charrefs=True);self.tags=[];self.forms=[];self.active=None;self.feed(text)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs);self.tags.append((tag,attrs))
        if tag=='form': self.active={'attrs':attrs,'inputs':{}};self.forms.append(self.active)
        if self.active is not None and tag=='input' and 'name' in attrs:self.active['inputs'][attrs['name']]=attrs.get('value','')
    def handle_endtag(self,tag):
        if tag=='form':self.active=None
class Client:
    def __init__(self,base):
        self.base=base;self.csrf='';self.jar=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar),NoRedirect())
    def request(self,path='index.php',body=None,headers=None,method=None):
        req=urllib.request.Request(self.base+'/'+path,data=body,headers=headers or {},method=method)
        try:r=self.http.open(req,timeout=30)
        except urllib.error.HTTPError as e:r=e
        return r.status,r.read(),r.headers
    def get(self,path='index.php',headers=None):
        status,raw,head=self.request(path,headers=headers);text=raw.decode('utf8');page=HTML(text)
        tokens=[a['value'] for tag,a in page.tags if tag=='input' and a.get('name')=='csrf'];
        if tokens:self.csrf=tokens[0]
        return status,text,head,page
    def post(self,action,values=None,token=None,headers=None):
        data={'action':action,'csrf':self.csrf if token is None else token};data.update(values or {})
        return self.request('aksi.php',urllib.parse.urlencode(data,doseq=True).encode(),{'Content-Type':'application/x-www-form-urlencoded'}|(headers or {}))
    def switch(self,user):
        status,raw,head=self.post('session.demo',{'account':user});assert status==303,(status,raw)
        return self.get(head['Location'])
    def upload(self,values,files):
        boundary='kostips'+uuid.uuid4().hex;chunks=[]
        for k,v in ({'action':'thread.save','csrf':self.csrf}|values).items():
            for item in (v if isinstance(v,list) else [v]):chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{item}\r\n'.encode())
        for name,raw,mime in files:chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+raw+b'\r\n')
        chunks.append(f'--{boundary}--\r\n'.encode());return self.request('aksi.php',b''.join(chunks),{'Content-Type':'multipart/form-data; boundary='+boundary})

def setup(source,parent):
    root=parent/'htdocs/kostips-php';root.parent.mkdir(parents=True)
    shutil.copytree(source,root,ignore=shutil.ignore_patterns('config.local.php','__pycache__','.git'))
    local="<?php return ['database'=>['dsn'=>'sqlite:'.dirname(__DIR__,2).'/kostips-test.sqlite']];\n"
    (root/'config.local.php').write_text(local)
    db=sqlite3.connect(parent/'kostips-test.sqlite');db.executescript((root/'database/schema.sqlite.sql').read_text())
    seed=json.loads((root/'database/seed.json').read_text())
    for u in seed['users']:db.execute('INSERT INTO users VALUES(?,?,?,?,?)',(u['id'],u['name'],u['initials'],u['color'],'member'))
    db.execute('INSERT INTO users VALUES(?,?,?,?,?)',('admin','Admin kosTips','AD','#eaf4e4','admin'))
    for t in seed['threads']:
        folder=t['id']+'-'+re.sub('[^a-zA-Z0-9]+','-',t['title']).lower()[:80].strip('-')
        db.execute('INSERT INTO threads VALUES(?,?,?,?,?,?,?,NULL)',(t['id'],t['author']['id'],t['category'],t['title'],t['body'],folder,t['createdAt'].replace('T',' ')[:19]))
        for r in t['replies']:db.execute('INSERT INTO replies VALUES(?,?,?,?,?,NULL)',(r['id'],t['id'],r['author']['id'],r['body'],r['createdAt'].replace('T',' ')[:19]))
    db.commit();db.close();return root

def run_checks(root,base):
    passed=0
    def check(ok,label):
        nonlocal passed
        if not ok:raise AssertionError(label)
        passed+=1;print('PASS',label,flush=True)
    a,b,admin=Client(base),Client(base),Client(base)
    status,text,headers,page=a.get();b.get();b.switch('rani');admin.get();admin.switch('admin')
    check(status==200 and text.count('<article class="thread-card">')==5,'server renders five thread cards per page')
    check("script-src 'none'" in headers['Content-Security-Policy'],'CSP explicitly disables JavaScript')
    check(not any(t=='script' or any(k.startswith('on') for k in attrs) for t,attrs in page.tags),'HTML has no script or inline event handlers')
    check(all(f['inputs'].get('csrf') for f in page.forms if f['attrs'].get('method')=='post'),'native POST forms carry CSRF tokens')
    cookie=next(iter(a.jar));check(cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite')=='Lax','session cookie protection remains enabled')
    check(b.get('admin.php')[0]==403,'member denied admin page directly')
    check(a.request('aksi.php')[0]==405,'GET action cannot mutate')
    check(a.post('reply.create',{'thread_id':'t1','body':'test'},token='')[0]==403,'missing CSRF denied')
    check(a.post('reply.create',{'thread_id':'t1','body':'test'},token='wrong')[0]==403,'wrong CSRF denied')
    check(a.post('reply.create',{'thread_id':'t1','body':'test'},headers={'Sec-Fetch-Site':'cross-site'})[0]==403,'cross-site write denied')
    values={'title':'../../Uji PHP Tanpa JS','category':'Memasak','body':'Isi diskusi dari form biasa'}
    status,raw,head=a.upload(values,[('gambar.png',PNG,'image/png'),('catatan.pdf',PDF,'application/pdf')]);check(status==303,'multipart create uses redirect after successful POST')
    target=head['Location'];tid=urllib.parse.parse_qs(urllib.parse.urlparse(target).query)['id'][0]
    status,text,head,page=a.get(target);check(status==200 and 'Uji PHP Tanpa JS' in text,'redirected detail contains new title')
    check('download.php?id=' in text and 'inline=1' in text,'server renders image preview URLs')
    db=sqlite3.connect(root.parents[1]/'kostips-test.sqlite')
    rows=db.execute('SELECT id,relative_path,mime_type FROM attachments WHERE thread_id=?',(tid,)).fetchall();folder=db.execute('SELECT upload_dir FROM threads WHERE id=?',(tid,)).fetchone()[0]
    storage=root.parents[1]/'kostips-php-data/uploads'
    check(len(rows)==2 and all((storage/row[1]).is_file() for row in rows),'real uploaded bytes stored outside htdocs')
    check(folder.startswith(tid+'-') and 'uji-php-tanpa-js' in folder and '/' not in folder,'upload directory contains sanitized thread title and ID')
    for aid,path,mime in rows:
        status,raw,head=a.request('download.php?id='+aid+'&inline=1')
        check(status==200 and raw==(PNG if mime=='image/png' else PDF),'download matches '+mime)
        check(head['Content-Disposition'].startswith('inline' if mime=='image/png' else 'attachment') and head['X-Content-Type-Options']=='nosniff','safe serving headers for '+mime)
    check(a.request('download.php?id=../../config.php')[0]==422,'download traversal rejected')
    payload='<img src=x onerror=alert(1)>'
    status,raw,head=a.post('reply.create',{'thread_id':tid,'body':payload,'user_id':'admin','role':'admin'});check(status==303,'plain form creates reply')
    rid=head['Location'].split('#reply-')[1];row=db.execute('SELECT user_id,body FROM replies WHERE id=?',(rid,)).fetchone();check(row==('me',payload),'server ignores forged role/user and stores literal text')
    text=a.get(target)[1];check('&lt;img src=x onerror=alert(1)&gt;' in text and '<img src=x' not in text,'PHP escapes reply HTML')
    check(b.post('reply.update',{'id':rid,'body':'stolen'})[0]==403,'other member cannot edit reply through direct POST')
    check(admin.post('reply.update',{'id':rid,'body':'edited'})[0]==403,'admin cannot edit another author’s reply')
    check(b.get('hapus.php?type=reply&id='+rid)[0]==403,'other member cannot open delete confirmation')
    check(b.post('reply.delete',{'id':rid,'role':'admin'})[0]==403,'forged admin cannot delete reply')
    check(a.post('reply.update',{'id':rid,'body':'Perubahan pemilik'})[0]==303,'owner updates reply')
    check('Perubahan pemilik' in a.get(target)[1],'updated reply rendered by PHP')
    status,text,head,page=admin.get('hapus.php?type=reply&id='+rid);check(status==200 and any(t=='textarea' and attrs.get('name')=='reason' for t,attrs in page.tags),'admin confirmation requires reason')
    check(admin.post('reply.delete',{'id':rid,'reason':''})[0]==422,'empty admin reason rejected server-side')
    check(admin.post('reply.delete',{'id':rid,'reason':'Uji moderasi <script>alert(1)</script>'})[0]==303,'admin deletion succeeds with reason')
    text=admin.get('admin.php?tab=logs')[1];check('Uji moderasi &lt;script&gt;' in text and rid in text,'log persists and escapes moderation reason')
    check(b.get('admin.php?tab=logs')[0]==403,'member cannot read moderation log HTML')
    for name,raw,mime in [('fake.png',b'<?php echo 1; ?>','image/png'),('x.php',PNG,'image/png'),('empty.pdf',b'','application/pdf'),('big.pdf',PDF+b'x'*2097152,'application/pdf')]:
        check(a.upload(values,[(name,raw,mime)])[0]==422,'invalid upload rejected: '+name)
    check(a.upload(values,[('x.png',PNG,'image/png')]*4)[0]==422,'four attachments rejected')
    check(a.upload(values|{'body':''},[])[0]==422,'thread validation returns HTML error')
    status,raw,head=a.upload(values|{'body':''},[]);check(b'../../Uji PHP Tanpa JS' in raw and b'name="title"' in raw,'failed editor preserves submitted title')
    check(b.upload(values|{'id':tid},[])[0]==403,'other member cannot replace thread or attachments')
    retained=[row[0] for row in rows]
    check(a.upload(values|{'id':tid,'title':'Judul diganti','retain_ids[]':retained},[])[0]==303,'owner edit retains selected files')
    check(db.execute('SELECT upload_dir FROM threads WHERE id=?',(tid,)).fetchone()[0]==folder,'folder stays stable after title edit')
    check(a.post('bookmark.toggle',{'id':tid,'return_to':'https://untrusted.example'})[2]['Location']==target,'bookmark redirect rejects external URL')
    check('Judul diganti' in a.get('koleksi.php?view=saved')[1],'saved collection renders from database')
    check('Judul diganti' in a.get('index.php?q=Judul+diganti')[1],'server search finds thread')
    check('Judul diganti' not in a.get('index.php?q=tidakada987')[1],'server search filters unrelated thread')
    aid=rows[0][0];check(b.post('attachment.delete',{'id':aid})[0]==403,'member cannot remove another attachment')
    check(admin.post('attachment.delete',{'id':aid,'reason':'Lampiran uji dihapus'})[0]==303,'individual attachment moderation works')
    check(not (storage/rows[0][1]).exists() and a.request('download.php?id='+aid)[0]==404,'moderated file removed from disk and download route')
    a.post('reply.create',{'thread_id':tid,'body':'Balasan untuk cascade'});b.post('bookmark.toggle',{'id':tid})
    check(admin.post('thread.delete',{'id':tid,'reason':'Membersihkan thread pengujian'})[0]==303,'admin can delete complete thread')
    check(not (storage/folder).exists() and all(db.execute('SELECT COUNT(*) FROM '+table+' WHERE thread_id=?',(tid,)).fetchone()[0]==0 for table in ['replies','attachments','bookmarks']),'cascade clears rows and physical folder')
    check(db.execute('SELECT COUNT(*) FROM moderation_logs WHERE thread_id=?',(tid,)).fetchone()[0]==3,'moderation logs survive cascade')
    check(a.get(target)[0]==404,'deleted thread returns 404')
    status,text,head,page=admin.get('admin.php');sidebar=text.split('<aside class="sidebar"')[1].split('</aside>')[0]
    check('koleksi.php' not in sidebar and 'balasan.php' not in sidebar,'admin navigation excludes member collections')
    status,text,head,page=Client(base).get(headers={'Host':'untrusted.example'});check('Akun uji lokal' not in text and 'name="account"' not in text,'nonlocal Host has no demo switch')
    config=root/'config.local.php';original=config.read_text();config.write_text(original.replace("return [","return ['environment'=>'production',"))
    check(admin.get('admin.php')[0]==401,'production invalidates existing demo admin session')
    # Get a CSRF token from a controlled, public account-free page is not necessary: session token remains valid.
    check(admin.post('session.demo',{'account':'admin'})[0]==404,'production blocks switching to demo admin')
    check(admin.post('reply.create',{'thread_id':'t1','body':'test'})[0]==401,'production guest cannot submit reply')
    config.write_text(original);db.close();print(str(passed)+' PHP/HTML integration checks passed.',flush=True)


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--php',default='php');args=parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='kostips-classic-') as temp:
        parent=Path(temp);root=setup(Path(__file__).resolve().parents[1],parent)
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        with open(parent/'server.log','w+') as log:
            server=subprocess.Popen([args.php,'-S',f'localhost:{port}','-t',str(parent/'htdocs')],stdout=log,stderr=log)
            try:
                for _ in range(50):
                    try:urllib.request.urlopen(f'http://localhost:{port}/kostips-php/index.php',timeout=1);break
                    except urllib.error.URLError:time.sleep(.1)
                run_checks(root,f'http://localhost:{port}/kostips-php')
            except Exception:log.seek(0);print(log.read());raise
            finally:server.terminate();server.wait(timeout=10)
if __name__=='__main__':main()
