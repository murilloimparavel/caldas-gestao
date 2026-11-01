import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

ROOT = Path(__file__).parents[1]
sys.path.insert(0, str(ROOT))
SPEC = importlib.util.spec_from_file_location('telemetry_publisher', ROOT / 'publisher.py')
module = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(module)
INCIDENT = 'fd5891cb-2a00-4064-b5ba-d8984f635fdd'


class PublisherTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.state = Path(self.temp.name)
        repo = self.state / 'seed'
        subprocess.run(['git', 'init', '-q', str(repo)], check=True)
        subprocess.run(['git', '-C', str(repo), 'config', 'user.email', 'test@example.invalid'], check=True)
        subprocess.run(['git', '-C', str(repo), 'config', 'user.name', 'Test'], check=True)
        (repo / 'app').mkdir()
        (repo / 'app' / 'example.txt').write_text('before\n')
        subprocess.run(['git', '-C', str(repo), 'add', '.'], check=True)
        subprocess.run(['git', '-C', str(repo), 'commit', '-qm', 'base'], check=True)
        self.release = subprocess.check_output(['git', '-C', str(repo), 'rev-parse', 'HEAD'], text=True).strip()
        subprocess.run(['git', 'clone', '-q', '--bare', str(repo), str(self.state / 'repository.git')], check=True)
        (repo / 'app' / 'example.txt').write_text('after\n')
        self.patch = subprocess.check_output(['git', '-C', str(repo), 'diff', '--binary'])
        self.repository_patch = patch.object(module, 'REPOSITORY', str(repo))
        self.repository_patch.start()
        self.addCleanup(self.repository_patch.stop)
        self.runner = module.Runner(module.Config(state_dir=self.state))
        self.publisher = module.Publisher(self.runner)
        self.calls = []
        self.existing = None
        self.pulls = []
        self.existing_tree = 't' * 40
        def api(method, path, payload=None, **kwargs):
            self.calls.append((method, path, payload))
            if path.startswith('/pulls?'): return self.pulls
            if path.startswith('/git/ref/'): return self.existing
            if path.startswith('/git/commits/') and method == 'GET':
                return {'tree': {'sha': self.existing_tree if path.endswith('b' * 40) else 'a' * 40}, 'parents': [{'sha': self.release}]}
            if path == '/git/blobs': return {'sha': 'd' * 40}
            if path == '/git/trees': return {'sha': 't' * 40}
            if path == '/git/commits': return {'sha': 'b' * 40}
            if path == '/git/refs': return {'ref': payload['ref']}
            if path == '/pulls': return {'html_url': 'https://github.com/murilloimparavel/caldas-gestao/pull/12'}
            raise AssertionError((method, path))
        self.publisher.api = Mock(side_effect=api)

    def publish(self):
        return self.publisher.publish(INCIDENT, self.release, self.patch, hashlib.sha256(self.patch).hexdigest())

    def test_creates_one_draft_pr_and_never_targets_production_branch_for_write(self):
        self.assertTrue(self.publish().endswith('/12'))
        ref = next(payload for _, path, payload in self.calls if path == '/git/refs')
        self.assertEqual(ref['ref'], 'refs/heads/codex/telemetry/' + INCIDENT)
        pr = next(payload for _, path, payload in self.calls if path == '/pulls')
        self.assertTrue(pr['draft'])
        self.assertEqual(pr['base'], 'production')
        self.assertNotIn('after', pr['body'])

    def test_existing_branch_and_pr_are_reused_and_not_force_updated(self):
        self.existing = {'object': {'sha': 'b' * 40}}
        self.pulls = [{'state': 'open', 'html_url': 'https://github.com/murilloimparavel/caldas-gestao/pull/12'}]
        self.publish()
        self.assertFalse(any(path in ['/git/refs', '/pulls'] and method == 'POST' for method, path, _ in self.calls))

    def test_existing_branch_with_other_tree_is_rejected(self):
        self.existing = {'object': {'sha': 'b' * 40}}
        self.existing_tree = 'e' * 40
        with self.assertRaises(module.RunnerError): self.publish()
        self.assertFalse(any(path == '/pulls' and method == 'POST' for method, path, _ in self.calls))

    def test_closed_pr_is_not_recreated_even_when_branch_removed(self):
        self.pulls = [{'state': 'closed', 'html_url': 'https://github.com/murilloimparavel/caldas-gestao/pull/12'}]
        self.publish()
        self.assertEqual(len(self.calls), 1)

    def test_rejects_tampered_patch(self):
        with self.assertRaises(module.RunnerError): self.publisher.publish(INCIDENT, self.release, self.patch, '0' * 64)
        self.assertEqual(self.calls, [])

    def test_missing_credential_preserves_prepared_patch_and_does_not_consume_attempt(self):
        attempt = self.state / 'incidents' / 'attempt'
        attempt.mkdir(parents=True)
        (attempt / 'result.json').write_text(json.dumps({'status': 'prepared_for_review'}))
        self.publisher.token = Mock(side_effect=FileNotFoundError())
        self.publisher.once()
        self.assertFalse((attempt / 'publish-retry.json').exists())

    def test_protected_changes_never_publish_refs_or_pull_requests(self):
        repo = self.state / 'seed'
        (repo / 'AGENTS.md').write_text('unauthorized instruction\n')
        subprocess.run(['git', '-C', str(repo), 'add', 'AGENTS.md'], check=True)
        self.patch = subprocess.check_output(['git', '-C', str(repo), 'diff', '--cached', '--binary'])
        with self.assertRaises(module.RunnerError): self.publish()
        self.assertFalse(any(path in ['/git/refs', '/pulls'] and method == 'POST' for method, path, _ in self.calls))

    def test_secret_like_file_content_is_not_uploaded(self):
        repo = self.state / 'seed'
        (repo / 'app' / 'example.txt').write_text('sk-' + 'a' * 30 + '\n')
        self.patch = subprocess.check_output(['git', '-C', str(repo), 'diff', '--binary'])
        with self.assertRaises(module.RunnerError): self.publish()
        self.assertFalse(any(path == '/git/blobs' for _, path, _ in self.calls))

    def test_only_prepared_incidents_are_published(self):
        attempt = self.state / 'incidents' / 'attempt'
        attempt.mkdir(parents=True)
        (attempt / 'result.json').write_text(json.dumps({'status': 'blocked'}))
        self.publisher.token = Mock()
        self.publisher.once()
        self.publisher.token.assert_not_called()


if __name__ == '__main__': unittest.main()
