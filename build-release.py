"""Build and verify a clean installation ZIP from an explicit source allowlist."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib, re

root=Path(__file__).resolve().parent
version=re.search(r"'version'\s*=>\s*'([^']+)'",(root/'config/version.php').read_text(encoding='utf-8-sig')).group(1)
assert re.fullmatch(r'\d+\.\d+\.\d+',version)
files={}
for name in ['.htaccess','index.php','README.md','CHANGELOG.md','extension-catalog.json','public/.htaccess','public/index.php','config/.htaccess','database/.htaccess']:
    files[name]=(root/name).read_bytes()
for directory in ['app','database/migrations','public/assets','public/themes']:
    for path in (root/directory).rglob('*'):
        if path.is_file() and not path.is_symlink():
            relative=path.relative_to(root).as_posix()
            if path.name.startswith('.') and path.name!='.htaccess':continue
            if re.search(r'\.(?:bak|old|orig|save|log|zip|sql)$',path.name,re.I):continue
            files[relative]=path.read_bytes()
for name in ['version','general','theme','url','debug','extensions','update']:
    files['config/'+name+'.php']=(root/'config'/f'{name}.php').read_bytes()
for directory in ['storage','storage/cache','storage/logs','storage/backups','public/uploads','extensions']:
    files[directory+'/.gitkeep']=b''
files['extensions/.htaccess']=b'Require all denied\n'
files['storage/.htaccess']=b'Require all denied\n'
output=root/'dist';output.mkdir(exist_ok=True)
archive=output/f'nexapress-{version}.zip';prefix=f'nexapress-{version}/'
with ZipFile(archive,'w',ZIP_DEFLATED,compresslevel=9) as package:
    for name,data in sorted(files.items()):package.writestr(prefix+name,data)
with ZipFile(archive) as package:
    assert package.testzip() is None
    assert len(package.namelist())==len(files)
    assert all(package.read(prefix+name)==data for name,data in files.items())
    assert not any('/tests/' in name or name.endswith('/config/config.php') for name in package.namelist())
print(f'{archive.name}: {len(files)} files, SHA-256 {hashlib.sha256(archive.read_bytes()).hexdigest()}')
