#!/usr/bin/env python3
"""Independent typed health reporting; never invokes Codex, claims incidents or reads auth contents."""
from __future__ import annotations

import argparse
import fcntl
import importlib.util
import ipaddress
import json
import os
from pathlib import Path
import re
import shutil
import socket
import stat
import subprocess
import sys
import time

QUEUE_STALE_SECONDS = 3600
CHECKS = ('image', 'network', 'auth_file', 'proxy', 'paused', 'freshness', 'queue', 'disk')


def check(key: str, status: str, code: str, value: int | None = None) -> dict:
    result = {'key': key, 'status': status, 'code': code}
    if value is not None:
        result['value'] = max(0, min(int(value), 10**15))
    return result


class HealthReporter:
    def __init__(self, runner):
        self.runner = runner
        self.web = None
        self.queue = None

    def command(self, argv: list[str], timeout: int = 10) -> str:
        return self.runner.run(argv, timeout=timeout).stdout

    def image(self) -> dict:
        self.command(['docker', 'image', 'inspect', self.runner.config.image])
        return check('image', 'ok', 'ready')

    def network(self) -> dict:
        info = json.loads(self.command(['docker', 'network', 'inspect', self.runner.config.network]))
        valid = isinstance(info, list) and len(info) == 1 and isinstance(info[0], dict) and info[0].get('Internal') is True
        return check('network', 'ok' if valid else 'error', 'ready' if valid else 'unavailable')

    def auth_file(self) -> dict:
        info = json.loads(self.command(['docker', 'volume', 'inspect', self.runner.config.auth_volume]))
        if not isinstance(info, list) or len(info) != 1 or not isinstance(info[0], dict) or info[0].get('Name') != self.runner.config.auth_volume:
            return check('auth_file', 'error', 'unavailable')
        mountpoint = info[0].get('Mountpoint')
        if not isinstance(mountpoint, str) or not Path(mountpoint).is_absolute():
            return check('auth_file', 'error', 'unavailable')
        # stat only: no token bytes, expiry claims or credential payload ever leave this host.
        metadata = (Path(mountpoint) / 'auth.json').lstat()
        valid = stat.S_ISREG(metadata.st_mode) and metadata.st_size > 0 and metadata.st_size <= 1024 * 1024 and metadata.st_uid == 10001 and metadata.st_mode & 0o077 == 0
        return check('auth_file', 'ok' if valid else 'error', 'ready' if valid else 'unavailable')

    def proxy(self) -> dict:
        ids = self.command(['docker', 'ps', '--no-trunc', '--filter', 'label=com.docker.compose.project=caldas-telemetry', '--filter', 'label=com.docker.compose.service=telemetry-egress', '--format', '{{.ID}}']).splitlines()
        if len(ids) != 1 or not re.fullmatch(r'[0-9a-f]{64}', ids[0]):
            return check('proxy', 'missing', 'missing')
        inspected = json.loads(self.command(['docker', 'inspect', ids[0]]))
        if not isinstance(inspected, list) or len(inspected) != 1 or not isinstance(inspected[0], dict):
            return check('proxy', 'error', 'unavailable')
        info = inspected[0]
        labels = info.get('Config', {}).get('Labels', {})
        if info.get('Id') != ids[0] or labels.get('com.docker.compose.project') != 'caldas-telemetry' or labels.get('com.docker.compose.service') != 'telemetry-egress' or info.get('State', {}).get('Running') is not True:
            return check('proxy', 'error', 'unavailable')
        address = info.get('NetworkSettings', {}).get('Networks', {}).get(self.runner.config.network, {}).get('IPAddress')
        ip = ipaddress.ip_address(address)
        if not ip.is_private or ip.is_loopback or ip.is_link_local:
            return check('proxy', 'error', 'unavailable')
        # Fixed HTTPS CONNECT checks proxy + upstream DNS/egress without model/auth calls.
        with socket.create_connection((str(ip), 3128), timeout=3) as connection:
            connection.settimeout(3)
            connection.sendall(b'CONNECT api.openai.com:443 HTTP/1.1\r\nHost: api.openai.com:443\r\n\r\n')
            response = connection.recv(512).split(b'\r\n', 1)[0]
        valid = bool(re.fullmatch(rb'HTTP/1\.[01] 200(?: .*)?', response))
        return check('proxy', 'ok' if valid else 'error', 'ready' if valid else 'unavailable')

    def paused(self) -> dict:
        if self.runner.paused():
            return check('paused', 'paused', 'paused', 1)
        prerequisites = self.runner.prerequisite_state()
        pipeline = self.runner.pipeline_state()
        if prerequisites['failures'] >= 3 or pipeline['failures'] >= 3:
            return check('paused', 'paused', 'paused', 1)
        if prerequisites['retry_after'] > time.time():
            return check('paused', 'error', 'unavailable', prerequisites['failures'])
        if not self.runner.budget():
            return check('paused', 'paused', 'quota', 0)
        return check('paused', 'ok', 'ready', 0)

    def freshness(self) -> dict:
        now = int(time.time())
        path = self.runner.state / 'worker-heartbeat.json'
        if path.is_symlink() or not path.is_file() or path.stat().st_size > 1024:
            return check('freshness', 'unknown', 'not_checked')
        document = json.loads(path.read_text())
        stamp = document.get('at') if isinstance(document, dict) else None
        if type(stamp) is not int or stamp <= 0 or stamp > now + 60:
            return check('freshness', 'error', 'unknown')
        age = max(0, now - stamp)
        if age <= 180:
            return check('freshness', 'ok', 'ready', age)
        for job in self.runner.incident_jobs():
            started = int(job['Config']['Labels']['io.caldas.telemetry.started'])
            if 0 <= now - started < 1200:
                return check('freshness', 'ok', 'running', now - started)
        return check('freshness', 'stale', 'stale', age)

    def web_container(self) -> str:
        if self.web is None:
            self.web, _release = self.runner.web_container()
        return self.web

    def queue_check(self) -> dict:
        raw = json.loads(self.runner.app(self.web_container(), ['telemetry:queue-health', '--json']))
        fields = ('pending', 'running', 'oldest_pending_seconds')
        if not isinstance(raw, dict) or set(raw) != set(fields) or any(type(raw[field]) is not int or not 0 <= raw[field] <= 10**15 for field in fields):
            return check('queue', 'error', 'unknown')
        self.queue = raw
        if raw['pending'] > 0 and raw['oldest_pending_seconds'] >= QUEUE_STALE_SECONDS:
            return check('queue', 'stale', 'stale', raw['pending'])
        code = 'pending' if raw['pending'] else 'running' if raw['running'] else 'ready'
        return check('queue', 'ok', code, raw['pending'])

    def disk(self) -> dict:
        free = shutil.disk_usage(self.runner.state).free
        return check('disk', 'ok' if free >= 2 * 1024**3 else 'error', 'ready' if free >= 2 * 1024**3 else 'low_space', free)

    def snapshot(self) -> dict:
        values = []
        for key in CHECKS:
            method = self.queue_check if key == 'queue' else getattr(self, key)
            try:
                values.append(method())
            except FileNotFoundError:
                values.append(check(key, 'missing', 'missing'))
            except (RuntimeError, subprocess.TimeoutExpired, OSError, ValueError, TypeError, KeyError, AttributeError):
                values.append(check(key, 'error', 'unavailable'))
        paused = next(value for value in values if value['key'] == 'paused')['status'] == 'paused'
        overall = 'paused' if paused else 'healthy' if all(value['status'] == 'ok' for value in values) else 'degraded'
        return {'version': 1, 'overall': overall, 'checks': values}

    def report(self) -> bool:
        payload = self.snapshot()
        path = self.runner.state / 'health.json'
        try:
            previous = json.loads(path.read_text()) if not path.is_symlink() and path.is_file() and path.stat().st_size <= 8192 else None
        except (OSError, ValueError):
            previous = None
        published = False
        try:
            self.runner.run(['docker', 'exec', '-i', self.web_container(), 'php', 'artisan', 'telemetry:report-health', '--json'], input=json.dumps(payload, ensure_ascii=True), timeout=15)
            published = True
        except (RuntimeError, subprocess.TimeoutExpired, OSError, ValueError):
            pass
        record = {'observed_at': int(time.time()), 'published': published, 'payload': payload}
        self.runner.atomic_json(path, record)
        # Journal only fixed statuses; never raw command output, registry references or auth data.
        before = previous.get('payload') if isinstance(previous, dict) else None
        def signature(value):
            if not isinstance(value, dict) or not isinstance(value.get('checks'), list):
                return None
            return value.get('overall'), [(item.get('key'), item.get('status'), item.get('code')) for item in value['checks'] if isinstance(item, dict)]
        if signature(before) != signature(payload) or not published:
            print('Telemetry health: ' + payload['overall'] + '; catalog=' + ('reported' if published else 'unavailable') + '; checks=' + ','.join(value['key'] + ':' + value['status'] for value in payload['checks']), flush=True)
        return published


def load_runner():
    spec = importlib.util.spec_from_file_location('caldas_health_runner', Path(__file__).with_name('runner.py'))
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--state-dir', type=Path, default=Path('/var/lib/caldas-telemetry'))
    args = parser.parse_args()
    module = load_runner()
    runner = module.Runner(module.Config(state_dir=args.state_dir))
    runner.deadline = time.monotonic() + 75
    with (runner.state / 'health.lock').open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return 0
        try:
            return 0 if HealthReporter(runner).report() else 1
        except (RuntimeError, subprocess.TimeoutExpired, OSError, ValueError):
            print('Telemetry health: unavailable; local report failed.', flush=True)
            return 1


if __name__ == '__main__':
    raise SystemExit(main())
