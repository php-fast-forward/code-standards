#!/usr/bin/env python3
"""Native runtime probes against an already built synthetic PHAR, in owned fixtures."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

if len(sys.argv) < 3:
    raise SystemExit('Usage: verify-runtime.py PASSED_TRANSPORT_DIRECTORY NEW_FIXTURE_DIRECTORY [PHP ...]')
transport = Path(sys.argv[1]).resolve()
root = Path(sys.argv[2]).resolve()
receipt = json.loads((transport / 'result.json').read_text())
phar = transport / 'package/builds/code-standards.phar'
while os.getloadavg()[0] >= 14:
    time.sleep(30)
assert hashlib.sha256(phar.read_bytes()).hexdigest() == receipt['archive_files']['builds/code-standards.phar']
root.mkdir(exist_ok=False)
(root / 'temporary files').mkdir(mode=0o700)
(root / 'empty-ini').mkdir()
env = {k: v for k, v in os.environ.items() if not k.startswith(('COMPOSER', 'GIT_'))}
env.update(XDEBUG_MODE='off', TMPDIR=str(root / 'temporary files'), PHP_INI_SCAN_DIR=str(root / 'empty-ini'), GIT_CONFIG_GLOBAL='/dev/null',
           GIT_CONFIG_SYSTEM='/dev/null', GIT_CONFIG_NOSYSTEM='1', GIT_TERMINAL_PROMPT='0')
results = []


def run(args, cwd, expected=0, override=None):
    while os.getloadavg()[0] >= 14:
        time.sleep(30)
    result = subprocess.run([str(x) for x in args], cwd=cwd, env=override or env,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=180)
    index = len(results)
    (root / f'{index:02}.stdout').write_bytes(result.stdout)
    (root / f'{index:02}.stderr').write_bytes(result.stderr)
    results.append({'argv': [str(x) for x in args], 'cwd': str(cwd), 'exit': result.returncode})
    (root / 'receipts.json').write_text(json.dumps(results, indent=2) + '\n')
    if expected is not None and result.returncode != expected:
        raise RuntimeError(f'Expected {expected}, got {result.returncode}: {args}; log {index:02}')
    return result


versions = []
for index, php in enumerate(sys.argv[3:] or [shutil.which('php')]):
    project = root / f'consumer {index}'
    project.mkdir()
    versions.append(json.loads(run([php, '-r', 'echo json_encode([PHP_VERSION, get_loaded_extensions()]);'], project).stdout))
    source = project / 'source.php'
    source.write_text('<?php function values($value) { if ($value === 42) { return array(1, 2); } return array(); }\n')
    policy = project / 'policy.php'
    policy.write_text(r'''<?php return \Rector\Config\RectorConfig::configure()->withPhpVersion(80300)
->withoutParallel()->withCache(__DIR__.'/.cache')->withPaths([__DIR__.'/source.php'])
->withRules([\Rector\Php54\Rector\Array_\LongArrayToShortArrayRector::class]);''')
    (project / 'composer.json').write_text('{"name":"fixture/consumer","description":"Untouched native fixture","license":"MIT"}\n')
    (project / 'composer.lock').write_text('{"fixture":"untouched"}\n')
    run(['git', 'init', '-q'], project)
    run(['git', 'add', 'source.php'], project)
    before = {path: path.read_bytes() for path in [source, policy, project / 'composer.json', project / 'composer.lock']}
    check = run([php, phar, 'check', '--all'], project, expected=2)
    assert b'begin diff' in check.stdout and b'source.php' in check.stdout
    rector = run([php, phar, 'refactor', '--config=policy.php'], project, expected=2)
    assert b'LongArrayToShortArrayRector' in rector.stdout and b'[1, 2]' in rector.stdout
    normalization = run([php, phar, 'normalize-composer'], project, expected=1)
    assert b'composer.lock is not fresh' in normalization.stdout + normalization.stderr
    (project / 'vendor').mkdir()
    (project / 'vendor/autoload.php').write_text('<?php throw new RuntimeException("Unexpected consumer autoload");')
    run([php, phar, 'magic-numbers', '--all'], project, expected=1)
    xml = run([php, phar, 'magic-numbers', '--all', '--format=xml'], project, expected=1)
    document = ET.fromstring(xml.stdout)
    assert int(document.attrib['errorCount']) > 0
    assert any(element.attrib.get('path', '').endswith('/source.php') for element in document.iter('file'))
    assert b'42' in xml.stdout
    assert all(path.read_bytes() == content for path, content in before.items())
    minimal = run([php, '-n', phar, 'list', '--raw'], project, expected=None)
    results[-1]['classification'] = 'php-n passed' if minimal.returncode == 0 else 'php-n missing runtime capability'
    assert not list((root / 'temporary files').glob('code-standards-phar-*'))

# A corrupted payload must fail and leave no extracted installation.
corrupt = root / 'corrupt.phar'
data = bytearray(phar.read_bytes())
data[len(data) // 2] ^= 1
corrupt.write_bytes(data)
corruption = run([sys.argv[3] if len(sys.argv) > 3 else shutil.which('php'), corrupt, 'list'], root, expected=None)
assert corruption.returncode != 0
assert not list((root / 'temporary files').glob('code-standards-phar-*'))
(root / 'result.json').write_text(json.dumps({'status': 'passed', 'versions_and_modules': versions,
    'payload_identity': receipt['payload_identity'], 'commands': results,
    'limits': ['PHP 8.3 not provided by this harness', 'Concurrent extraction not tested here', 'php-n is a probe, not proof of all PHP builds']}, indent=2) + '\n')
print(root / 'result.json')
