#!/usr/bin/env python3
"""Proves Composer VCS preserves an explicit dist pointing to a ready ZIP."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys

if len(sys.argv) != 3:
    raise SystemExit('Usage: verify-vcs-dist.py PASSED_TRANSPORT_DIRECTORY NEW_FIXTURE_DIRECTORY')
transport = Path(sys.argv[1]).resolve()
root = Path(sys.argv[2]).resolve()
assert json.loads((transport / 'result.json').read_text())['status'] == 'passed'
if os.getloadavg()[0] >= 14:
    raise SystemExit('Pending: load must be below 14.')
root.mkdir(exist_ok=False)
env = {k: v for k, v in os.environ.items() if not k.startswith(('COMPOSER', 'GIT_'))}
env.update(COMPOSER_HOME=str(root / 'composer-home'), COMPOSER_CACHE_DIR=str(root / 'composer-cache'),
           XDEBUG_MODE='off', GIT_CONFIG_GLOBAL='/dev/null', GIT_CONFIG_SYSTEM='/dev/null',
           GIT_CONFIG_NOSYSTEM='1', GIT_TERMINAL_PROMPT='0', COMPOSER_NO_INTERACTION='1')
receipts = []


def run(args, cwd, expected=0, override=None):
    if os.getloadavg()[0] >= 14:
        raise SystemExit('Pending: load increased; no new command started.')
    result = subprocess.run([str(x) for x in args], cwd=cwd, env=override or env,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=180)
    index = len(receipts)
    (root / f'{index:02}.log').write_bytes(result.stdout)
    receipts.append({'argv': [str(x) for x in args], 'exit': result.returncode, 'cwd': str(cwd)})
    (root / 'receipts.json').write_text(json.dumps(receipts, indent=2) + '\n')
    if result.returncode != expected:
        raise RuntimeError(f'Expected {expected}, got {result.returncode}; see {index:02}.log')
    return result.stdout.decode()


vcs = root / 'repository'
vcs.mkdir()
for name in ['src', 'config', 'resources', 'bin', 'composer.json', 'composer.lock', 'LICENSE']:
    origin = transport / 'package' / name
    shutil.copytree(origin, vcs / name) if origin.is_dir() else shutil.copy2(origin, vcs / name)
manifest = json.loads((vcs / 'composer.json').read_text())
manifest['dist'] = {'type': 'zip', 'url': str(transport / 'package/.build/git.zip')}
(vcs / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')
run(['git', 'init', '-q', '-b', 'main'], vcs)
run(['git', 'add', '.'], vcs)
run(['git', '-c', 'user.name=VCS Fixture', '-c', 'user.email=fixture@example.invalid',
     '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'Source-only dist metadata fixture'], vcs)
assert not list(vcs.rglob('*.phar'))
for mode in ['dist', 'global', 'source']:
    home = root / mode
    home.mkdir()
    (home / 'composer.json').write_text(json.dumps({'name': 'fixture/consumer',
        'repositories': [{'type': 'vcs', 'url': str(vcs)}, {'packagist.org': False}]}))
    local_env = dict(env, COMPOSER_HOME=str(home)) if mode == 'global' else env
    prefix = ['composer', 'global'] if mode == 'global' else ['composer']
    run([*prefix, 'require', '--prefer-source' if mode == 'source' else '--prefer-dist',
         '--no-plugins', '--no-scripts', 'fast-forward/code-standards:dev-main'], home, override=local_env)
    lock = json.loads((home / 'composer.lock').read_text())
    assert len(lock['packages']) == 1
    assert lock['packages'][0]['dist']['url'] == manifest['dist']['url']
    installed = home / 'vendor/fast-forward/code-standards'
    assert (installed / 'builds/code-standards.phar').is_file() == (mode != 'source')
    run(['php', home / 'vendor/bin/code-standards', 'list', '--raw'], home,
        expected=255 if mode == 'source' else 0, override=local_env)
(root / 'result.json').write_text(json.dumps({'status': 'passed',
    'source_has_phar': False, 'manifest_dist': manifest['dist'],
    'local_and_global_prefer_dist': 'ready PHAR, single solver package',
    'prefer_source': 'no PHAR; cannot run without contributor setup or artifact acquisition',
    'scope': 'Real Composer VCS behavior, not a published Packagist version or GitHub release'}, indent=2) + '\n')
print(root / 'result.json')
