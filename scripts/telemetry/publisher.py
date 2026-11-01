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

from runner import Config, REPOSITORY, Runner, RunnerError, SHA, protected_path, sanitize_incident

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
        if not path.startswith('/') or '\n' in path or '?' in path and not path.startswith(('/pulls?', '/issues?')):
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

    def ensure_issue(self, incident, attempt):
        """Create or recover exactly one issue before the diagnostic agent runs."""
        incident = sanitize_incident(incident)
        incident_id = incident['id']
        attempt = Path(attempt)
        if attempt.is_symlink() or not attempt.is_dir() or attempt.parent.resolve() != (self.runner.state / 'incidents').resolve():
            raise RunnerError('Invalid incident attempt directory')
        issue_file = attempt / 'issue.json'
        title = 'Incidente de telemetria ' + incident_id
        issue_pattern = r'https://github\.com/murilloimparavel/caldas-gestao/issues/([1-9][0-9]*)'

        def validated_issue(issue):
            if not isinstance(issue, dict) or issue.get('title') != title or 'pull_request' in issue:
                raise RunnerError('Invalid incident issue response')
            if issue.get('state') == 'closed':
                raise RunnerError('Incident issue is closed; manual review required')
            number, url = issue.get('number'), issue.get('html_url')
            match = re.fullmatch(issue_pattern, url) if isinstance(url, str) else None
            if type(number) is not int or number < 1 or not match or int(match.group(1)) != number:
                raise RunnerError('Invalid incident issue identity')
            return {'number': number, 'url': url}

        # This lock spans the remote lookup and creation even when worker and publisher overlap.
        with (self.runner.state / ('issue-' + incident_id + '.lock')).open('a') as lock:
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                raise RunnerError('Incident issue creation already in progress') from None
            if issue_file.exists() or issue_file.is_symlink():
                if issue_file.is_symlink() or issue_file.stat().st_size > 4096:
                    raise RunnerError('Invalid saved issue')
                saved = json.loads(issue_file.read_text())
                number = saved.get('number') if isinstance(saved, dict) else None
                url = saved.get('url') if isinstance(saved, dict) else None
                match = re.fullmatch(issue_pattern, url) if isinstance(url, str) else None
                if type(number) is not int or number < 1 or not match or int(match.group(1)) != number:
                    raise RunnerError('Invalid saved issue identity')
                remote = validated_issue(self.api('GET', '/issues/' + str(number)))
                if remote != {'number': number, 'url': url}:
                    raise RunnerError('Saved issue identity differs from GitHub')
                return remote
            matches = []
            for page in range(1, 11):
                issues = self.api('GET', f'/issues?state=all&per_page=100&page={page}')
                if not isinstance(issues, list):
                    raise RunnerError('Invalid GitHub issue list')
                matches.extend(issue for issue in issues if isinstance(issue, dict) and issue.get('title') == title and 'pull_request' not in issue)
                if len(issues) < 100:
                    break
            else:
                raise RunnerError('Issue lookup exceeded page limit')
            if len(matches) > 1:
                raise RunnerError('Duplicate incident issues need manual review')
            if matches:
                result = validated_issue(matches[0])
            else:
                frames = []
                for frame in incident['frames']:
                    file = frame.get('file', '')
                    line = frame.get('line')
                    if (re.fullmatch(r'(?:app|bootstrap|routes|resources|config)/[A-Za-z0-9_./-]+\.php', file)
                        or re.fullmatch(r'/build/assets/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{8,}\.js', file)) and type(line) is int:
                        frames.append(f'- `{file}:{line}`')
                source = '\n'.join(frames[:8]) or '- Nenhum quadro de código permitido foi registrado.'
                # All values come from the collector allowlist; the exception message and user data are excluded.
                body = ('Incidente capturado automaticamente. A causa e o impacto requerem confirmação humana.\n\n'
                        '### Evidências sanitizadas\n'
                        f'- Identificador: `{incident_id}`\n'
                        f'- Versão observada: `{incident["release"]}`\n'
                        f'- Origem: `{incident["kind"]}`\n'
                        f'- Categoria: `{incident["category"]}`\n'
                        f'- Tipo ou código: `{incident["exception_class"] or "unknown"}`\n'
                        f'- Rota de origem: `{incident["route_name"] or "unknown"}`\n'
                        f'- Ocorrências registradas: {incident["occurrences"]}\n\n'
                        '### Localização observada\n' + source + '\n\n'
                        '### Impacto e critérios de aceite\n'
                        '- Confirmar se o fluxo é afetado e identificar a causa com evidências.\n'
                        '- Preparar a menor proposta verificável ou registrar que não há evidências suficientes.\n'
                        '- Validar o comportamento e submeter o PR a revisão humana antes de qualquer publicação.\n')
                result = validated_issue(self.api('POST', '/issues', {'title': title, 'body': body}))
            self.runner.atomic_json(issue_file, result)
            return result

    def publish(self, incident_id, release, patch, expected_hash, issue_number):
        incident_id = str(uuid.UUID(incident_id))
        if not SHA.fullmatch(release) or hashlib.sha256(patch).hexdigest() != expected_hash or not patch or len(patch) > 2 * 1024 * 1024:
            raise RunnerError('Invalid incident release or patch')
        if type(issue_number) is not int or issue_number < 1:
            raise RunnerError('A valid issue is required before publishing')
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
            pr = self.api('POST', '/pulls', {'title': 'Proposta para incidente ' + incident_id, 'head': branch, 'base': 'production', 'draft': True, 'body': 'Proposta automática para revisão humana.\n\nRelacionada à issue #' + str(issue_number) + '\nIncidente: ' + incident_id + '\nVersão observada: ' + release + '\n\nA correção exige revisão e validação antes de publicação.'})
        url = pr.get('html_url') if isinstance(pr, dict) else None
        if not isinstance(url, str) or not re.fullmatch(r'https://github\.com/murilloimparavel/caldas-gestao/pull/[1-9][0-9]*', url):
            raise RunnerError('Invalid pull request response')
        return url

    def once(self):
        incidents = self.runner.state / 'incidents'
        if not incidents.is_dir():
            return
        for attempt in sorted(incidents.iterdir()):
            if not attempt.is_dir() or attempt.is_symlink() or not (attempt / 'result.json').is_file() or not (attempt / 'issue.json').is_file() or (attempt / 'published.json').exists():
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
                incident = json.loads((attempt / 'incident.json').read_text())
                if incident.get('id') != metadata.get('incident_id') or incident.get('release') != metadata.get('incident_release'):
                    raise RunnerError('Incident and result do not match')
                issue = self.ensure_issue(incident, attempt)
                patch = (attempt / 'change.patch').read_bytes()
                url = self.publish(metadata['incident_id'], metadata['incident_release'], patch, metadata['patch_sha256'], issue['number'])
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
