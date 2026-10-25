"""Integration checks against a disposable MySQL/MariaDB instance (see tests/README.md)."""
import argparse
import functools
import http.cookiejar
import http.server
import json
import os
from pathlib import Path
import re
import shutil
import socket
import ssl
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

SOURCE = Path(__file__).resolve().parents[1]


def port():
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        return listener.getsockname()[1]


class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None):
        request = urllib.request.Request(self.base + path, None if data is None else urllib.parse.urlencode(data).encode())
        try:
            result = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            result = error
        body = result.read().decode('utf-8')
        assert 'Fatal error:' not in body and 'Warning:' not in body, body[:1000]
        return result.status, body, result.geturl()

    def csrf(self, path):
        code, body, _ = self.request(path)
        assert code == 200, (path, code, body[:500])
        match = re.search(r'name="_csrf_token"\s+value="([a-f0-9]+)"', body)
        assert match, ('missing CSRF token', path)
        return match[1]

    def login(self, username, password='Test-Password-2026'):
        return self.request('/admin/login', {'username': username, 'password': password})


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--browser', action='store_true')
    parser.add_argument('--output', type=Path)
    parser.add_argument('--keep', action='store_true')
    parser.add_argument('--from-2.2', action='store_true', dest='from22')
    args = parser.parse_args()
    if 'NEXAPRESS_TEST_PASSWORD' not in os.environ:
        raise RuntimeError('Set NEXAPRESS_TEST_PASSWORD for a disposable MariaDB/MySQL server.')
    work = Path(tempfile.mkdtemp(prefix='nexapress-integration-'))
    fixture = work / 'site'
    fixture.mkdir()
    for directory in ['app', 'config', 'public', 'database']:
        shutil.copytree(SOURCE / directory, fixture / directory, ignore=shutil.ignore_patterns('config.php', 'uploads'))
    shutil.copy2(SOURCE / 'index.php', fixture / 'index.php')
    for directory in ['storage/cache', 'storage/logs', 'storage/backups', 'extensions', 'public/uploads']:
        (fixture / directory).mkdir(parents=True, exist_ok=True)
    server = None
    tls = None
    log = None
    try:
        subprocess.run(['php', str(SOURCE / 'tests/fixture.php'), str(fixture)] + (['2.2'] if args.from22 else []), check=True)
        subprocess.run(['php', str(SOURCE / 'tests/fresh-install.php'), str(fixture)], check=True)
        subprocess.run(['php', str(fixture / 'app/Console/migrate.php')], check=True)
        classic = os.environ.get('NEXAPRESS_TEST_CLASSIC_EDITOR')
        if classic:
            shutil.copytree(classic, fixture / 'extensions/classic-editor')
        nitro = os.environ.get('NEXAPRESS_TEST_NITRO_CACHE')
        if nitro:
            shutil.copytree(nitro, fixture / 'extensions/nx_nitro_cache')
        packages = work / 'packages'
        packages.mkdir()
        manifest = {'id': 'test-extension', 'name': '更新テスト', 'description': '検証用', 'version': '1.0.0',
                    'bootstrap': 'bootstrap.php', 'requires': {'nexapress': '2.3.0', 'php': '8.0'}}
        installed = fixture / 'extensions' / 'original-folder'
        installed.mkdir()
        (installed / 'manifest.json').write_text(json.dumps(manifest), encoding='utf-8')
        (installed / 'bootstrap.php').write_text('<?php /* test extension */', encoding='utf-8')
        settings = fixture / 'storage/extensions/test-extension'
        settings.mkdir(parents=True)
        (settings / 'settings.json').write_text('{"keep":true}', encoding='utf-8')
        tls_port = port()
        cert, key = work / 'cert.pem', work / 'key.pem'
        openssl = shutil.which('openssl') or r'D:\xampp\apache\bin\openssl.exe'
        subprocess.run([openssl, 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj', '/CN=127.0.0.1',
                        '-addext', 'subjectAltName=IP:127.0.0.1', '-keyout', str(key), '-out', str(cert)],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        class QuietHandler(http.server.SimpleHTTPRequestHandler):
            def log_message(self, *args):
                pass
        handler = functools.partial(QuietHandler, directory=str(packages))
        tls = http.server.ThreadingHTTPServer(('127.0.0.1', tls_port), handler)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(cert, key)
        tls.socket = context.wrap_socket(tls.socket, server_side=True)
        threading.Thread(target=tls.serve_forever, daemon=True).start()
        origin = f'https://127.0.0.1:{tls_port}'
        def release(version, invalid_id=False, traversal=False, php='8.0'):
            latest = dict(manifest, version=version, download_url=origin + '/package.zip', requires={'nexapress': '2.3.0', 'php': php})
            (packages / 'manifest.json').write_text(json.dumps(latest), encoding='utf-8')
            payload = dict(latest, id='wrong-id') if invalid_id else latest
            with zipfile.ZipFile(packages / 'package.zip', 'w') as archive:
                archive.writestr('new-package-folder/manifest.json', json.dumps(payload))
                archive.writestr('new-package-folder/bootstrap.php', '<?php /* updated */')
                if traversal:
                    archive.writestr('new-package-folder/../escape.php', '<?php')
            (packages / 'catalog.json').write_text(json.dumps({'extensions': [{'manifest_url': origin + '/manifest.json'}]}), encoding='utf-8')
        release('2.0.0')
        config = {'catalog_url': origin + '/catalog.json', 'cache_ttl': 0, 'timeout': 5, 'download_timeout': 5,
                  'allowed_manifest_hosts': ['127.0.0.1'], 'allowed_image_hosts': ['127.0.0.1'], 'allowed_download_hosts': ['127.0.0.1']}
        php_config = '<?php return ' + '[' + ','.join(repr(k) + ' => ' +
            ('[' + ','.join(repr(item) for item in value) + ']' if isinstance(value, list) else repr(value))
            for k, value in config.items()) + '];'
        (fixture / 'config/extensions.php').write_text(php_config, encoding='utf-8')
        http_port = port()
        base = f'http://127.0.0.1:{http_port}'
        log = open(work / 'php-server.log', 'w', encoding='utf-8')
        sessions = work / 'sessions'
        sessions.mkdir()
        server = subprocess.Popen(['php', '-d', 'curl.cainfo=' + cert.as_posix(), '-d', 'session.save_path=' + sessions.as_posix(),
            '-d', 'session.cache_limiter=',
            '-S', f'127.0.0.1:{http_port}', '-t', str(fixture), str(SOURCE / 'tests/router.php')], cwd=fixture,
            stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
        guest, admin = Client(base), Client(base)
        for _ in range(60):
            try:
                if guest.request('/admin/login')[0] == 200: break
            except (urllib.error.URLError, ConnectionError): time.sleep(.1)
        checks = 0
        def check(condition, label):
            nonlocal checks
            assert condition, label
            checks += 1
            print('PASS:', label, flush=True)
        check(admin.login('admin')[2].endswith('/admin'), 'administrator login')
        if nitro:
            cache_token = admin.csrf('/admin/extensions')
            admin.request('/admin/extensions/enable/nx_nitro_cache', {'_csrf_token': cache_token})
            guest.request('/')
            check(any((fixture / 'storage/cache/nx_nitro_cache').glob('*.html')), 'Nitro Cache is active during integration checks')
        token = admin.csrf('/admin/pages/create')
        page_data = {'_csrf_token': token, 'title': '公開ページ', 'slug': 'welcome', 'content': '<p>初稿の本文</p><script>alert(1)</script>', 'status': 'published'}
        check(admin.request('/admin/pages/store', dict(page_data, _csrf_token='bad'))[0] == 419, 'page CSRF protection')
        code, body, location = admin.request('/admin/pages/store', page_data)
        check(code == 200 and '/admin/pages/edit/' in location, 'page creation through admin UI')
        page_id = int(location.rsplit('/', 1)[1])
        check('<script>alert(1)</script>' not in guest.request('/page/welcome')[1], 'public page HTML sanitization')
        check(admin.request(f'/admin/pages/preview/{page_id}')[0] == 200, 'authenticated page preview')
        check('/admin/login' in guest.request(f'/admin/pages/preview/{page_id}')[2], 'guest cannot preview drafts')
        admin.request(f'/admin/pages/update/{page_id}', dict(page_data, content='<p>変更後の本文</p>'))
        history = admin.request(f'/admin/pages/revisions/{page_id}')[1]
        revision_id = re.search(r'/restore/(\d+)', history)[1]
        admin.request(f'/admin/pages/revisions/{page_id}/restore/{revision_id}', {'_csrf_token': token})
        check('初稿の本文' in guest.request('/page/welcome')[1], 'page revision restoration')
        nav_token = admin.csrf('/admin/navigation')
        for label, kind, ref, url in [('ホーム', 'custom', 0, '/'), ('案内', 'page', page_id, ''), ('ニュース', 'category', 1, '')]:
            admin.request('/admin/navigation/save', {'_csrf_token': nav_token, 'label': label, 'type': kind, 'reference_id': ref, 'url': url})
        nav_body = admin.request('/admin/navigation')[1]
        ids = [int(value) for value in re.findall(r'name="id" value="(\d+)"', nav_body) if value != '0']
        admin.request(f'/admin/navigation/move/{ids[1]}', {'_csrf_token': nav_token, 'direction': 'up'})
        header = guest.request('/')[1].split('</header>')[0]
        check(header.index('>案内</a>') < header.index('>ホーム</a>'), 'menu ordering appears in theme')
        admin.request('/admin/navigation/save', {'_csrf_token': nav_token, 'label': '危険', 'type': 'custom', 'url': 'javascript:alert(1)'})
        check('>危険</a>' not in guest.request('/')[1], 'unsafe custom link rejected')
        general_token = admin.csrf('/admin/settings/general')
        admin.request('/admin/settings/general/update', {'_csrf_token': general_token, 'site_title': 'NexaPress Test', 'timezone': 'Asia/Tokyo', 'homepage_mode': 'page', 'homepage_page_id': page_id})
        check('初稿の本文' in guest.request('/')[1] and '記事一覧' in guest.request('/posts')[1], 'static front page with separate post archive')
        category_body = guest.request('/category/news')[1]
        check('公開記事' in category_body and '/category/news?page=2' in category_body, 'public category pagination')
        search_path = re.search(r'class="public-search" action="([^"]+)"', guest.request('/posts')[1])[1]
        check(search_path.endswith('/post/archive/search') and '旧検索スラッグ' in guest.request('/search')[1], 'existing content URL is preserved alongside search')
        search = guest.request(search_path + '?q=' + urllib.parse.quote('検索対象'))[1]
        check('非公開シークレット' not in search and 'ゴミ箱シークレット' not in search and 'page=2' in search and 'q=' in search, 'public search filtering and pagination')
        check('公開記事' not in guest.request(search_path + '?q=%25')[1], 'public search escapes wildcard input')
        admin.request(f'/admin/pages/delete/{page_id}', {'_csrf_token': token})
        check(guest.request('/page/welcome')[0] == 404 and '>案内</a>' not in guest.request('/')[1], 'trash hides page and navigation entry')
        check('公開記事' in guest.request('/')[1], 'front page gracefully falls back when selected page is trashed')
        admin.request(f'/admin/pages/restore/{page_id}', {'_csrf_token': token})
        check(guest.request('/page/welcome')[0] == 404 and 'プレビュー' in admin.request(f'/admin/pages/preview/{page_id}')[1], 'restored page stays private until published')
        check(admin.request(f'/admin/pages/destroy/{page_id}', {'_csrf_token': token})[0] == 404, 'active page cannot be permanently deleted')
        viewer = Client(base)
        viewer.login('viewer')
        check(viewer.request('/admin/navigation')[0] == 403 and viewer.request('/admin/pages')[0] == 403, 'viewer cannot manage content or menus')
        profile_token = viewer.csrf('/admin/profile')
        body = viewer.request('/admin/profile', {'_csrf_token': profile_token, 'name': '閲覧者', 'email': 'new@example.test', 'role': 'administrator'})[1]
        check('現在のパスワード' in body and '保存しました' not in body, 'sensitive profile changes require current password')
        viewer.request('/admin/profile', {'_csrf_token': profile_token, 'name': '閲覧者', 'email': 'new@example.test',
            'current_password': 'Test-Password-2026', 'password': 'Changed-Password-2026', 'password_confirmation': 'Changed-Password-2026', 'role': 'administrator'})
        check(viewer.request('/admin/navigation')[0] == 403, 'profile input cannot escalate role')
        new_viewer = Client(base)
        check(new_viewer.login('viewer', 'Changed-Password-2026')[2].endswith('/admin'), 'new profile password works')
        extension_token = admin.csrf('/admin/extensions')
        admin.request('/admin/extensions/enable/test-extension', {'_csrf_token': extension_token})
        check(admin.request('/admin/extensions/update/test-extension', {'_csrf_token': 'bad'})[0] == 419, 'extension update CSRF protection')
        result = admin.request('/admin/extensions/update/test-extension', {'_csrf_token': extension_token})[1]
        (work / 'extension-result.html').write_text(result, encoding='utf-8')
        check('を更新しました' in result and json.loads((installed / 'manifest.json').read_text(encoding='utf-8'))['version'] == '2.0.0', 'extension update preserves installed folder')
        check((settings / 'settings.json').read_text() == '{"keep":true}' and '有効' in result, 'extension update preserves settings and enabled state')
        check(any((fixture / 'storage/backups/extensions').iterdir()), 'extension update retains a backup')
        release('3.0.0', php='99.0')
        check('が必要です' in admin.request('/admin/extensions/update/test-extension', {'_csrf_token': extension_token})[1], 'incompatible update is rejected')
        release('3.0.0', invalid_id=True)
        check('一致しません' in admin.request('/admin/extensions/update/test-extension', {'_csrf_token': extension_token})[1], 'mismatched update manifest rejected')
        release('3.0.0', traversal=True)
        admin.request('/admin/extensions/update/test-extension', {'_csrf_token': extension_token})
        check(json.loads((installed / 'manifest.json').read_text(encoding='utf-8'))['version'] == '2.0.0' and not (fixture / 'escape.php').exists(), 'unsafe update leaves old installation intact')
        release('2.0.0')
        if args.browser:
            if classic:
                admin.request('/admin/extensions/enable/classic-editor', {'_csrf_token': extension_token})
            output = args.output or work / 'screenshots'
            output.mkdir(parents=True, exist_ok=True)
            environment = dict(os.environ, NEXAPRESS_TEST_URL=base, NEXAPRESS_TEST_OUTPUT=str(output))
            subprocess.run(['node', str(SOURCE / 'tests/browser.cjs')], check=True, env=environment)
        log.flush()
        application_errors = [line for line in (work / 'php-server.log').read_text(encoding='utf-8').splitlines()
            if any(marker in line for marker in ['PHP Warning:', 'PHP Fatal error:', 'PHP Parse error:']) and 'Module ' not in line]
        check(not application_errors, 'no PHP runtime warnings or errors: ' + repr(application_errors))
        print(f'HTTP checks passed: {checks}', flush=True)
        print('Fixture:', fixture, flush=True)
    finally:
        if server:
            server.terminate()
            server.wait(timeout=10)
        if tls:
            tls.shutdown()
            tls.server_close()
        if log:
            log.close()
        if not args.keep:
            if (fixture / 'test-state.json').exists():
                subprocess.run(['php', str(SOURCE / 'tests/fixture.php'), str(fixture), 'cleanup'], check=True)
            resolved = work.resolve()
            assert resolved.parent == Path(tempfile.gettempdir()).resolve() and resolved.name.startswith('nexapress-integration-')
            shutil.rmtree(resolved)
        else:
            print('Preserved test files:', work, flush=True)


if __name__ == '__main__':
    main()
