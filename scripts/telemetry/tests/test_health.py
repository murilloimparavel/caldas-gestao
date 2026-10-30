import importlib.util
import json
from pathlib import Path
import socket
import stat
import subprocess
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch

SPEC = importlib.util.spec_from_file_location('telemetry_health', Path(__file__).parents[1] / 'health.py')
health = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = health
SPEC.loader.exec_module(health)


class HealthContracts(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.runner = Mock()
        self.runner.state = Path(self.directory.name)
        self.runner.config = SimpleNamespace(image='caldas-telemetry-runner:0.165.0', network='caldas-telemetry-internal', auth_volume='caldas-telemetry-codex-auth')
        self.runner.atomic_json = lambda path, value: path.write_text(json.dumps(value))
        self.runner.web_container.return_value = ('trusted-web', 'a' * 40)
        self.runner.paused.return_value = False
        self.runner.prerequisite_state.return_value = {'failures': 0, 'retry_after': 0}
        self.runner.pipeline_state.return_value = {'failures': 0}
        self.runner.budget.return_value = True
        self.reporter = health.HealthReporter(self.runner)

    def healthy_checks(self):
        for key in health.CHECKS:
            setattr(self.reporter, 'queue_check' if key == 'queue' else key, Mock(return_value=health.check(key, 'ok', 'ready')))

    def test_image_is_checked_without_codex_recovery_or_authentication(self):
        self.runner.run.return_value = subprocess.CompletedProcess([], 0, '[]', '')
        self.assertEqual(self.reporter.image(), health.check('image', 'ok', 'ready'))
        self.runner.run.assert_called_once_with(['docker', 'image', 'inspect', self.runner.config.image], timeout=10)
        self.runner.ensure_image.assert_not_called()

    def test_missing_image_is_reported_even_when_no_runner_image_can_execute(self):
        self.healthy_checks()
        self.reporter.image.side_effect = RuntimeError('image unavailable; arbitrary secret should never be captured')
        snapshot = self.reporter.snapshot()
        self.assertEqual(snapshot['overall'], 'degraded')
        self.assertEqual(snapshot['checks'][0], health.check('image', 'error', 'unavailable'))
        self.assertNotIn('secret', json.dumps(snapshot))
        self.assertEqual([value['key'] for value in snapshot['checks']], list(health.CHECKS))

    def test_auth_check_reads_metadata_only_and_rejects_unsafe_or_large_files(self):
        self.reporter.command = Mock(return_value=json.dumps([{'Name': self.runner.config.auth_volume, 'Mountpoint': '/var/lib/docker/volumes/test/_data'}]))
        valid = {'st_mode': stat.S_IFREG | 0o600, 'st_size': 1234, 'st_uid': 10001}
        with patch.object(health.Path, 'lstat', return_value=SimpleNamespace(**valid)), patch.object(health.Path, 'read_text', side_effect=AssertionError('credential contents must never be read')):
            self.assertEqual(self.reporter.auth_file(), health.check('auth_file', 'ok', 'ready'))
        for change in [{'st_mode': stat.S_IFLNK | 0o600}, {'st_mode': stat.S_IFREG | 0o644}, {'st_size': 0}, {'st_size': 1024 * 1024 + 1}, {'st_uid': 0}]:
            with self.subTest(change=change), patch.object(health.Path, 'lstat', return_value=SimpleNamespace(**{**valid, **change})):
                self.assertEqual(self.reporter.auth_file()['status'], 'error')

    def test_network_must_remain_internal(self):
        for internal, expected in [(True, 'ok'), (False, 'error')]:
            with self.subTest(internal=internal):
                self.reporter.command = Mock(return_value=json.dumps([{'Internal': internal}]))
                self.assertEqual(self.reporter.network()['status'], expected)

    def test_proxy_probe_uses_only_a_fixed_unauthenticated_connect_target(self):
        proxy_id = 'a' * 64
        metadata = {'Id': proxy_id, 'Config': {'Labels': {'com.docker.compose.project': 'caldas-telemetry', 'com.docker.compose.service': 'telemetry-egress'}}, 'State': {'Running': True}, 'NetworkSettings': {'Networks': {self.runner.config.network: {'IPAddress': '172.30.0.2'}}}}
        self.reporter.command = Mock(side_effect=[proxy_id + '\n', json.dumps([metadata])])
        connection = Mock()
        connection.recv.return_value = b'HTTP/1.1 200 Connection established\r\n'
        context = Mock()
        context.__enter__ = Mock(return_value=connection)
        context.__exit__ = Mock(return_value=False)
        with patch.object(health.socket, 'create_connection', return_value=context) as connect:
            self.assertEqual(self.reporter.proxy()['status'], 'ok')
        connect.assert_called_once_with(('172.30.0.2', 3128), timeout=3)
        connection.sendall.assert_called_once_with(b'CONNECT api.openai.com:443 HTTP/1.1\r\nHost: api.openai.com:443\r\n\r\n')
        self.assertNotIn('Authorization', str(connection.sendall.call_args))

    def test_backoff_and_quota_cannot_look_like_a_healthy_worker(self):
        self.assertEqual(self.reporter.paused(), health.check('paused', 'ok', 'ready', 0))
        self.runner.prerequisite_state.return_value = {'failures': 1, 'retry_after': 1700000900}
        with patch.object(health.time, 'time', return_value=1700000000):
            self.assertEqual(self.reporter.paused(), health.check('paused', 'error', 'unavailable', 1))
        self.runner.prerequisite_state.return_value = {'failures': 0, 'retry_after': 0}
        self.runner.budget.return_value = False
        self.assertEqual(self.reporter.paused(), health.check('paused', 'paused', 'quota', 0))
        self.runner.pipeline_state.return_value = {'failures': 3}
        self.assertEqual(self.reporter.paused(), health.check('paused', 'paused', 'paused', 1))

    def test_pause_does_not_prevent_independent_reporting(self):
        self.healthy_checks()
        self.reporter.paused.return_value = health.check('paused', 'paused', 'paused', 1)
        self.runner.run.return_value = subprocess.CompletedProcess([], 0, '{}', '')
        self.assertTrue(self.reporter.report())
        record = json.loads((self.runner.state / 'health.json').read_text())
        self.assertEqual(record['payload']['overall'], 'paused')
        command = self.runner.run.call_args.args[0]
        self.assertEqual(command, ['docker', 'exec', '-i', 'trusted-web', 'php', 'artisan', 'telemetry:report-health', '--json'])
        payload = json.loads(self.runner.run.call_args.kwargs['input'])
        self.assertEqual(set(payload), {'version', 'overall', 'checks'})
        self.assertNotIn('codex', command)
        self.assertNotIn('claim', ' '.join(command))

    def test_catalog_failure_still_persists_typed_local_health_without_error_contents(self):
        self.healthy_checks()
        self.runner.run.side_effect = RuntimeError('private secret headers')
        self.assertFalse(self.reporter.report())
        record = json.loads((self.runner.state / 'health.json').read_text())
        self.assertFalse(record['published'])
        self.assertNotIn('private', json.dumps(record))

    def test_freshness_distinguishes_recent_poll_active_job_and_stale_worker(self):
        stamp = self.runner.state / 'worker-heartbeat.json'
        stamp.write_text(json.dumps({'at': 1700000000}))
        with patch.object(health.time, 'time', return_value=1700000180):
            self.assertEqual(self.reporter.freshness()['status'], 'ok')
        self.runner.incident_jobs.return_value = []
        with patch.object(health.time, 'time', return_value=1700000181):
            self.assertEqual(self.reporter.freshness()['status'], 'stale')
        self.runner.incident_jobs.return_value = [{'Config': {'Labels': {'io.caldas.telemetry.started': '1700000100'}}}]
        with patch.object(health.time, 'time', return_value=1700000500):
            self.assertEqual(self.reporter.freshness()['code'], 'running')
        stamp.write_text('x' * 1025)
        self.assertEqual(self.reporter.freshness()['status'], 'unknown')

    def test_queue_accepts_only_bounded_integer_metadata(self):
        self.runner.app.return_value = json.dumps({'pending': 2, 'running': 1, 'oldest_pending_seconds': 120})
        self.assertEqual(self.reporter.queue_check(), health.check('queue', 'ok', 'pending', 2))
        self.runner.app.assert_called_with('trusted-web', ['telemetry:queue-health', '--json'])
        for value in [{'pending': True, 'running': 0, 'oldest_pending_seconds': 0}, {'pending': -1, 'running': 0, 'oldest_pending_seconds': 0}, {'pending': 0, 'running': 0, 'oldest_pending_seconds': 0, 'token': 'bad'}]:
            self.runner.app.return_value = json.dumps(value)
            self.assertEqual(self.reporter.queue_check()['status'], 'error')

    def test_eligible_backlog_becomes_stale_at_one_hour_only_when_pending(self):
        cases = [
            ({'pending': 2, 'running': 0, 'oldest_pending_seconds': 3599}, health.check('queue', 'ok', 'pending', 2)),
            ({'pending': 2, 'running': 0, 'oldest_pending_seconds': 3600}, health.check('queue', 'stale', 'stale', 2)),
            ({'pending': 0, 'running': 0, 'oldest_pending_seconds': 7200}, health.check('queue', 'ok', 'ready', 0)),
            ({'pending': 0, 'running': 1, 'oldest_pending_seconds': 7200}, health.check('queue', 'ok', 'running', 0)),
        ]
        for values, expected in cases:
            with self.subTest(values=values):
                self.runner.app.return_value = json.dumps(values)
                self.assertEqual(self.reporter.queue_check(), expected)
        self.healthy_checks()
        self.reporter.queue_check.return_value = health.check('queue', 'stale', 'stale', 2)
        self.assertEqual(self.reporter.snapshot()['overall'], 'degraded')

    def test_corrupt_previous_health_does_not_block_the_next_snapshot(self):
        self.healthy_checks()
        (self.runner.state / 'health.json').write_text('{invalid')
        self.runner.run.return_value = subprocess.CompletedProcess([], 0, '{}', '')
        self.assertTrue(self.reporter.report())
        self.assertTrue(json.loads((self.runner.state / 'health.json').read_text())['published'])


if __name__ == '__main__':
    unittest.main()
