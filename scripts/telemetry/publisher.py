#!/usr/bin/env python3
"""Publish reviewed-size incident patches as draft PRs; never merge or deploy."""
import base64
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import uuid

from runner import Config, REPOSITORY, Runner, RunnerError, SHA, protected_path

API = 'https://api.github.com/repos/murilloimparavel/caldas-gestao'
TOKEN_FILE = Path('/opt/caldas-telemetry/github-token')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Publisher:
    def __init__(self, runner, token_file=TOKEN_FILE):
        self.runner = runner
        self.token_file = token_file

    def token(self):
        descriptor = os.open(self.token_file, os.O_RDONLY | os.O_NOFOLLOW)
        try:
            info = os.fstat(descriptor)
            if not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o077 or not 20 <= info.st_size <= 4096:
                raise RunnerError('GitHub credential must be a root-only regular file')
            token = os.read(descriptor, 4097).decode('ascii').strip()
            if not re.fullmatch(r'[A-Za-z0-9_]{20,4096}', token):
                raise RunnerError('Invalid GitHub credential format')
            return token
        finally:
            os.close(descriptor)

    def api(self, method, path, payload=None, *, missing_ok=False):
        if not path.startswith('/') or '\n' in path or '?' in path and not path.startswith('/pulls?'):
            raise RunnerError('Invalid GitHub API path')
        data = None if payload is None else json.dumps(payload).encode()
        request = urllib.request.Request(API + path, data=data, method=method, headers={'Authorization': 'Bearer ' + self.token(), 'Accept': 'application/vnd.github+json', 'Content-Type': 'application/json', 'X-GitHub-Api-Version': '2022-11-28', 'User-Agent': 'caldas-incident-publisher'})
        try:
            with urllib.request.build_opener(NoRedirect()).open(request, timeout=20) as response:
                content = response.read(1024 * 1024 + 1)
                if len(content) > 1024 * 1024:
                    raise RunnerError('GitHub response exceeded limit')
                return json.loads(content)
        except urllib.error.HTTPError as error:
            if missing_ok and error.code == 404:
                return None
            raise RunnerError('GitHub request failed with HTTP ' + str(error.code)) from None
        except (urllib.error.URLError, TimeoutError, ValueError):
            raise RunnerError('GitHub request unavailable') from None

    def publish(self, incident_id, release, patch, expected_hash):
        incident_id = str(uuid.UUID(incident_id))
        if not SHA.fullmatch(release) or hashlib.sha256(patch).hexdigest() != expected_hash or not patch or len(patch) > 2 * 1024 * 1024:
            raise RunnerError('Invalid incident release or patch')
        branch = 'codex/telemetry/' + incident_id
        pulls = self.api('GET', '/pulls?state=all&head=murilloimparavel:' + branch + '&base=production&per_page=100')
        if not isinstance(pulls, list) or len(pulls) > 1:
            raise RunnerError('Unable to uniquely identify existing pull request')
        if pulls and pulls[0].get('state') == 'closed':
            url = pulls[0].get('html_url')
            if not isinstance(url, str) or not re.fullmatch(r'https://github\.com/murilloimparavel/caldas-gestao/pull/[1-9][0-9]*', url):
                raise RunnerError('Invalid pull request response')
            return url
        existing = self.api('GET', '/git/ref/heads/' + branch, missing_ok=True)
        with tempfile.TemporaryDirectory(prefix='publish-', dir=self.runner.state) as directory:
            repo = Path(directory) / 'repo'
            self.runner.run(['git', 'init', '--quiet', str(repo)], timeout=30)
            command = ['git', '-c', 'core.hooksPath=/dev/null', '-c', 'core.fsmonitor=false', '-C', str(repo)]
            self.runner.run(command + ['fetch', '--no-tags', '--depth=1', REPOSITORY, release], timeout=120)
            if self.runner.run(command + ['rev-parse', 'FETCH_HEAD']).stdout.strip() != release:
                raise RunnerError('Fetched release differs from the incident')
            self.runner.run(command + ['checkout', '--detach', release])
            patchfile = Path(directory) / 'change.patch'
            patchfile.write_bytes(patch)
            self.runner.run(command + ['apply', '--check', '--', str(patchfile)])
            self.runner.run(command + ['apply', '--', str(patchfile)])
            self.runner.run(command + ['add', '-A', '--', '.'])
            names = [x for x in self.runner.run(command + ['diff', '--cached', '--name-only', '-z']).stdout.split('\0') if x]
            if not names or len(names) > 100 or any(protected_path(name) for name in names):
                raise RunnerError('Patch includes protected or excessive files')
            self.runner.run(command + ['diff', '--cached', '--check'])
            remote_commit = self.api('GET', '/git/commits/' + release)
            tree = []
            for name in names:
                if not re.fullmatch(r'[A-Za-z0-9_./ -]{1,240}', name):
                    raise RunnerError('Unsupported file path')
                entry = self.runner.run(command + ['ls-files', '--stage', '--', name]).stdout.strip()
                if not entry:
                    tree.append({'path': name, 'mode': '100644', 'type': 'blob', 'sha': None})
                    continue
                mode = entry.split(' ', 1)[0]
                if mode not in {'100644', '100755'}:
                    raise RunnerError('Unsupported file mode')
                source = repo / name
                descriptor = os.open(source, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
                try:
                    info = os.fstat(descriptor)
                    if not stat.S_ISREG(info.st_mode) or info.st_size > 1024 * 1024:
                        raise RunnerError('Unsupported file size or type')
                    data = os.read(descriptor, info.st_size + 1)
                finally:
                    os.close(descriptor)
                if re.search(rb'(?:sk-[A-Za-z0-9_-]{16,}|eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)', data):
                    raise RunnerError('Credential-like data withheld')
                blob = self.api('POST', '/git/blobs', {'content': base64.b64encode(data).decode(), 'encoding': 'base64'})
                tree.append({'path': name, 'mode': mode, 'type': 'blob', 'sha': blob['sha']})
            new_tree = self.api('POST', '/git/trees', {'base_tree': remote_commit['tree']['sha'], 'tree': tree})
            if existing is not None:
                existing_sha = existing.get('object', {}).get('sha')
                if not isinstance(existing_sha, str) or not SHA.fullmatch(existing_sha):
                    raise RunnerError('Existing branch has invalid commit')
                existing_commit = self.api('GET', '/git/commits/' + existing_sha)
                if existing_commit.get('tree', {}).get('sha') != new_tree['sha'] or [item.get('sha') for item in existing_commit.get('parents', [])] != [release]:
                    raise RunnerError('Existing incident branch differs; refusing to modify it')
            else:
                commit = self.api('POST', '/git/commits', {'message': 'fix: prepare incident ' + incident_id, 'tree': new_tree['sha'], 'parents': [release]})
                self.api('POST', '/git/refs', {'ref': 'refs/heads/' + branch, 'sha': commit['sha']})
        if pulls:
            pr = pulls[0]
        else:
            pr = self.api('POST', '/pulls', {'title': 'Proposta para incidente ' + incident_id, 'head': branch, 'base': 'production', 'draft': True, 'body': 'Proposta automática para revisão humana.\n\nIncidente: ' + incident_id + '\nVersão observada: ' + release + '\n\nA correção exige revisão e validação antes de publicação.'})
        url = pr.get('html_url') if isinstance(pr, dict) else None
        if not isinstance(url, str) or not re.fullmatch(r'https://github\.com/murilloimparavel/caldas-gestao/pull/[1-9][0-9]*', url):
            raise RunnerError('Invalid pull request response')
        return url

    def once(self):
        incidents = self.runner.state / 'incidents'
        if not incidents.is_dir():
            return
        for attempt in sorted(incidents.iterdir()):
            if not attempt.is_dir() or attempt.is_symlink() or not (attempt / 'result.json').is_file() or (attempt / 'published.json').exists():
                continue
            metadata = json.loads((attempt / 'result.json').read_text())
            if metadata.get('status') != 'prepared_for_review':
                continue
            retry = attempt / 'publish-retry.json'
            previous = json.loads(retry.read_text()) if retry.exists() else {'attempts': 0, 'retry_after': 0}
            if previous['attempts'] >= 3 or time.time() < previous['retry_after']:
                continue
            try:
                self.token()
            except (RunnerError, OSError, UnicodeError):
                print('Incident publisher credential unavailable; patch retained for review.', flush=True)
                return
            self.runner.atomic_json(retry, {'attempts': previous['attempts'] + 1, 'retry_after': time.time() + 900})
            try:
                patch = (attempt / 'change.patch').read_bytes()
                url = self.publish(metadata['incident_id'], metadata['incident_release'], patch, metadata['patch_sha256'])
                self.runner.atomic_json(attempt / 'published.json', {'pull_request': url})
                print('Incident draft PR created or already exists.', flush=True)
            except (RunnerError, OSError, ValueError, KeyError, subprocess.TimeoutExpired):
                print('Incident publication failed; patch retained for review.', flush=True)
            return


def main():
    runner = Runner(Config())
    with (runner.state / 'publisher.lock').open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return 0
        Publisher(runner).once()
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
