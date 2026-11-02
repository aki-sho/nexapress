"""Exercise unmodified release sources through the real admin core-update route."""
import argparse
import hashlib
import http.cookiejar
import http.server
import io
import json
import os
from pathlib import Path
import re
import shutil
import socket
import ssl
import subprocess
import tarfile
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

SOURCE = Path(__file__).resolve().parents[1]


def command(args, **kwargs):
    if args[0] == 'php':
        args = [args[0], '-d', 'display_errors=0', '-d', 'display_startup_errors=0', *args[1:]]
    result = subprocess.run(args, capture_output=True, text=True, encoding='utf-8', errors='replace', **kwargs)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result.stdout.strip()


def free_port():
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
            response = self.opener.open(request, timeout=90)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode('utf-8')
        assert 'Fatal error:' not in body and 'Warning:' not in body, body[-1500:]
        return response.status, body, response.geturl()

    def token(self):
        status, body, _ = self.request('/admin/updates')
        assert status == 200
        match = re.search(r'name="_token"\s+value="([a-f0-9]+)"', body)
        assert match, body[-1500:]
        return match[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', type=Path, required=True)
    parser.add_argument('--from-ref', action='append', default=[])
    parser.add_argument('--reproduce-package', type=Path)
    parser.add_argument('--public', action='store_true', help='Use actual public GitHub API and download URLs.')
    parser.add_argument('--ca-file', type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    if 'NEXAPRESS_TEST_PASSWORD' not in os.environ:
        raise RuntimeError('NEXAPRESS_TEST_PASSWORD must point to a disposable MariaDB/MySQL server.')
    package = args.package.resolve()
    with zipfile.ZipFile(package) as archive:
        manifest = json.loads(archive.read('update-manifest.json'))
        assert archive.testzip() is None
    target = manifest['version']
    assert package.name == f'nexapress-update-{target}.zip'
    work = Path(tempfile.mkdtemp(prefix='nexapress-updater-'))
    transport = {'package': package, 'version': target, 'bad_digest': False}
    requested = []
    checks = 0
    results = []
    tls = None
    cert = args.ca_file

    def check(condition, label):
        nonlocal checks
        assert condition, label
        checks += 1
        print('PASS:', label, flush=True)

    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self, *unused):
            pass

        def do_GET(self):
            requested.append(self.path)
            selected = transport['package']
            version = transport['version']
            name = f'nexapress-update-{version}.zip'
            url = f'https://github.com/aki-sho/nexapress/releases/download/v{version}/{name}'
            if self.path == '/repos/aki-sho/nexapress/releases/latest':
                payload = json.dumps({'tag_name': 'v' + version, 'name': 'Test release', 'assets': [{
                    'name': name, 'browser_download_url': url, 'size': selected.stat().st_size,
                    'digest': 'sha256:' + ('0' * 64 if transport['bad_digest'] else hashlib.sha256(selected.read_bytes()).hexdigest()),
                }]}).encode()
                content_type = 'application/json'
            elif self.path == urllib.parse.urlsplit(url).path:
                payload = selected.read_bytes()
                content_type = 'application/octet-stream'
            else:
                self.send_error(404)
                return
            self.send_response(200)
            self.send_header('Content-Type', content_type)
            self.send_header('Content-Length', str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

    try:
        if not args.public:
            cert, key = work / 'cert.pem', work / 'key.pem'
            openssl = shutil.which('openssl') or r'D:\xampp\apache\bin\openssl.exe'
            command([openssl, 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
                     '-subj', '/CN=github.com', '-addext', 'subjectAltName=DNS:github.com,DNS:api.github.com',
                     '-keyout', str(key), '-out', str(cert)])
            tls = http.server.ThreadingHTTPServer(('127.0.0.1', free_port()), Handler)
            context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
            context.load_cert_chain(cert, key)
            tls.socket = context.wrap_socket(tls.socket, server_side=True)
            threading.Thread(target=tls.serve_forever, daemon=True).start()
            transport_php = work / 'transport.php'
            transport_php.write_text('''<?php
namespace app\\Core;
function curl_setopt_array($handle, array $options): bool {
    $options[CURLOPT_CONNECT_TO] = ['github.com:443:127.0.0.1:PORT','api.github.com:443:127.0.0.1:PORT'];
    $options[CURLOPT_CAINFO] = __DIR__ . '/cert.pem';
    $options[CURLOPT_PROXY] = '';
    return \\curl_setopt_array($handle, $options);
}
'''.replace('PORT', str(tls.server_port)), encoding='utf-8')
        router = work / 'router.php'
        router.write_text('<?php\n' + ("require __DIR__ . '/transport.php';\n" if not args.public else '') +
            "$_SERVER['SCRIPT_NAME'] = '/index.php'; require getcwd() . '/index.php';\n", encoding='utf-8')
        refs = args.from_ref or ['v2.2.0', 'v2.3.0', 'v2.4.0', 'v2.4.1']
        for index, ref in enumerate(refs):
            fixture = work / ('site-' + str(index))
            fixture.mkdir()
            archive = subprocess.run(['git', 'archive', ref], cwd=SOURCE, capture_output=True, check=True).stdout
            with tarfile.open(fileobj=io.BytesIO(archive)) as source_archive:
                for member in source_archive.getmembers():
                    assert member.isfile() or member.isdir()
                    assert (fixture / member.name).resolve().is_relative_to(fixture.resolve())
                source_archive.extractall(fixture)
            for directory in ['storage/cache', 'storage/logs', 'storage/backups', 'storage/extensions/retained-extension', 'extensions/retained-extension', 'public/uploads', 'public/themes/retained-theme']:
                (fixture / directory).mkdir(parents=True, exist_ok=True)
            (fixture / 'extensions/retained-extension/manifest.json').write_text(json.dumps({
                'id': 'retained-extension', 'name': 'Retained extension', 'version': '1.0.0', 'bootstrap': 'bootstrap.php'}), encoding='utf-8')
            (fixture / 'extensions/retained-extension/bootstrap.php').write_text('<?php // Retained extension\n', encoding='utf-8')
            (fixture / 'storage/extensions/retained-extension/settings.json').write_text('{"retained":true}', encoding='utf-8')
            (fixture / 'public/uploads/retained.txt').write_text('Retained upload', encoding='utf-8')
            (fixture / 'public/themes/retained-theme/theme.php').write_text('<?php // Retained theme\n', encoding='utf-8')
            helper = SOURCE / 'tests/updater-fixture.php'
            before = json.loads(command(['php', str(helper), str(fixture), 'setup']))
            version = before.pop('version')
            protected = ['config/config.php', 'config/general.php', 'config/url.php', 'config/theme.php', 'config/debug.php',
                         'extensions/retained-extension/bootstrap.php', 'storage/extensions/retained-extension/settings.json',
                         'public/uploads/retained.txt', 'public/themes/retained-theme/theme.php']
            digests = {name: hashlib.sha256((fixture / name).read_bytes()).hexdigest() for name in protected}
            server = None
            log = (work / f'php-{index}.log').open('w', encoding='utf-8')
            try:
                port = free_port()
                sessions = work / f'sessions-{index}'
                sessions.mkdir()
                php = ['php', '-d', 'display_errors=0', '-d', 'display_startup_errors=0',
                       '-d', 'session.save_path=' + sessions.as_posix(), '-d', 'opcache.enable=0']
                if cert:
                    php += ['-d', 'curl.cainfo=' + cert.as_posix()]
                server = subprocess.Popen(php + ['-S', f'127.0.0.1:{port}', str(router)], cwd=fixture,
                    stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
                guest, admin = Client(f'http://127.0.0.1:{port}'), Client(f'http://127.0.0.1:{port}')
                transport.update(package=package, version=target, bad_digest=False)
                for _ in range(50):
                    try:
                        if guest.request('/admin/login')[0] == 200:
                            break
                    except (ConnectionError, urllib.error.URLError):
                        time.sleep(.1)
                check(admin.request('/admin/login', {'username': 'admin', 'password': 'Test-Password-2026'})[2].endswith('/admin'), ref + ': original admin login')
                check('/admin/login' in guest.request('/admin/updates')[2], ref + ': guest cannot access updater')
                token = admin.token()
                check(admin.request('/admin/updates/install', {'_token': 'bad'})[0] == 419, ref + ': update CSRF enforcement')
                if args.reproduce_package and not args.public and version != '2.4.1':
                    transport.update(package=args.reproduce_package.resolve(), version='2.4.1')
                    body = admin.request('/admin/updates/install', {'_token': token})[1]
                    check('更新に失敗しました。' in body, ref + ': reproduce published 2.4.1 failure')
                    failure_log = (fixture / 'storage/logs/update.log').read_text(encoding='utf-8')
                    check('更新対象外のファイルが含まれています：config/.htaccess' in failure_log, ref + ': exact rejected file confirmed')
                    state = json.loads(command(['php', str(helper), str(fixture), 'state']))
                    check(state.pop('version') == version and state == before, ref + ': rejected 2.4.1 preserves original DB and version')
                    check(not list((fixture / 'storage/backups').glob('update-*.zip')), ref + ': rejection occurs before backup/apply')
                    transport.update(package=package, version=target)
                body = admin.request('/admin/updates/install', {'_token': token})[1]
                (work / f'update-result-{index}.html').write_text(body, encoding='utf-8')
                check('更新が完了しました' in body and '更新に失敗しました' not in body, ref + ': real admin update completes')
                state = json.loads(command(['php', str(helper), str(fixture), 'state']))
                check(state.pop('version') == target, ref + ': installed version matches release')
                check(state == before, ref + ': posts, pages, users and enabled extensions preserved')
                check(all(hashlib.sha256((fixture / name).read_bytes()).hexdigest() == value for name, value in digests.items()), ref + ': settings, extensions, theme and upload preserved')
                backups = list((fixture / 'storage/backups').glob('update-*.zip'))
                check(bool(backups), ref + ': file/DB backup retained')
                with zipfile.ZipFile(backups[-1]) as backup:
                    check(backup.testzip() is None and 'database.sql' in backup.namelist() and 'backup-manifest.json' in backup.namelist(), ref + ': backup ZIP contains DB and restore manifest')
                check(not (fixture / 'storage/maintenance.lock').exists(), ref + ': maintenance lock cleared')
                check('Retained page content' in guest.request('/page/retained-page')[1], ref + ': public page works after update')
                check('NexaPressは最新です' in admin.request('/admin/updates')[1], ref + ': admin update page reloads as current')
                if not args.public:
                    token = admin.token()
                    next_version = target.rsplit('.', 1)[0] + '.' + str(int(target.rsplit('.', 1)[1]) + 1)
                    transport.update(version=next_version, bad_digest=True)
                    body = admin.request('/admin/updates/install', {'_token': token})[1]
                    check('更新に失敗しました。処理：更新ZIPの取得。' in body, ref + ': failed step is displayed without raw exception details')
                    state = json.loads(command(['php', str(helper), str(fixture), 'state']))
                    check(state.pop('version') == target and state == before, ref + ': invalid checksum preserves installed site')
                    transport.update(version=target, bad_digest=False)
                restored = json.loads(command(['php', str(helper), str(fixture), 'restore', str(backups[-1])]))
                check(restored.pop('version') == version and restored == before, ref + ': backup restores original release and complete retained DB')
                check('Retained page content' in guest.request('/page/retained-page')[1], ref + ': public page works after backup restore')
                log.flush()
                errors = [line for line in (work / f'php-{index}.log').read_text(encoding='utf-8').splitlines()
                          if any(marker in line for marker in ['PHP Warning:', 'PHP Fatal error:', 'PHP Parse error:']) and 'Module ' not in line]
                check(not errors, ref + ': no PHP runtime errors ' + repr(errors))
                results.append({'from_ref': ref, 'from_version': version, 'to_version': target, 'status': 'PASS'})
            finally:
                if server:
                    server.terminate()
                    server.wait(timeout=10)
                log.close()
                command(['php', str(helper), str(fixture), 'cleanup'])
        if not args.public:
            check('/repos/aki-sho/nexapress/releases/latest' in requested, 'production UpdateChecker fetched release metadata over verified TLS')
            check(any(path.endswith(package.name) for path in requested), 'production UpdatePackage downloaded tested ZIP over verified TLS')
        report = {'status': 'PASS', 'checks': checks, 'package': package.name,
                  'sha256': hashlib.sha256(package.read_bytes()).hexdigest(), 'public_github': args.public,
                  'cases': results, 'evidence': str(work)}
        if args.output:
            args.output.parent.mkdir(parents=True, exist_ok=True)
            args.output.write_text(json.dumps(report, indent=2), encoding='utf-8')
        print(f'Updater release checks: {checks} passed; evidence: {work}', flush=True)
    finally:
        if tls:
            tls.shutdown()
            tls.server_close()


if __name__ == '__main__':
    main()
