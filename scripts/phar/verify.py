#!/usr/bin/env python3
"""Disposable transport experiment; never modifies a consumer or the source checkout."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import zipfile

source = Path(__file__).resolve().parents[2]
if len(sys.argv) not in [3, 4] or (len(sys.argv) == 4 and sys.argv[3] != '--gzip'):
    raise SystemExit('Usage: verify.py INSTALLED_NO_DEV_RUNTIME NEW_EXPERIMENT_DIRECTORY [--gzip]')
runtime = Path(sys.argv[1]).resolve()
experiment = Path(sys.argv[2]).resolve()
if os.getloadavg()[0] >= 14:
    raise SystemExit('Pending: host one-minute load must be below 14 before this experiment.')
experiment.mkdir(exist_ok=False)
logs = experiment / 'logs'
logs.mkdir()
env = {key: value for key, value in os.environ.items() if not key.startswith(('COMPOSER', 'GIT_'))}
env.update(COMPOSER_HOME=str(experiment / 'composer-home'),
           COMPOSER_CACHE_DIR=str(experiment / 'composer-cache'), COMPOSER_NO_INTERACTION='1', XDEBUG_MODE='off', GIT_CONFIG_GLOBAL='/dev/null',
           GIT_CONFIG_SYSTEM='/dev/null', GIT_CONFIG_NOSYSTEM='1', GIT_TERMINAL_PROMPT='0')
(experiment / 'temporary files').mkdir(mode=0o700)
env['TMPDIR'] = str(experiment / 'temporary files')
receipts = []


def run(args, cwd, expected=0, environment=None):
    if os.getloadavg()[0] >= 14:
        raise SystemExit('Pending: load increased above 14; resume in a fresh experiment after recovery.')
    index = len(receipts)
    result = subprocess.run([str(x) for x in args], cwd=cwd, env=environment or env,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=300)
    (logs / f'{index:02}.log').write_bytes(result.stdout)
    receipts.append({'argv': [str(x) for x in args], 'cwd': str(cwd), 'exit': result.returncode})
    (experiment / 'receipts.json').write_text(json.dumps(receipts, indent=2) + '\n')
    if expected is not None and result.returncode != expected:
        raise RuntimeError(f'Unexpected exit {result.returncode}: {args}; see {logs}/{index:02}.log')
    return result.stdout.decode()


# A PHAR built from an older checkout cannot qualify the candidate's current resources/source.
for name in ['src', 'config', 'resources', 'bin', 'composer.json', 'composer.lock', 'LICENSE']:
    original = source / name
    expected = {str(path.relative_to(source)): path.read_bytes() for path in original.rglob('*') if path.is_file()} if original.is_dir() else {name: original.read_bytes()}
    staged = runtime / name
    actual = {str(path.relative_to(runtime)): path.read_bytes() for path in staged.rglob('*') if path.is_file()} if staged.is_dir() else {name: staged.read_bytes()}
    assert expected == actual, f'Stale runtime input: {name}'


package = experiment / 'package'
package.mkdir()
for path in ['src', 'config', 'resources', 'docs', 'bin', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', '.gitattributes']:
    origin = source / path
    target = package / path
    shutil.copytree(origin, target) if origin.is_dir() else shutil.copy2(origin, target)
(package / 'builds').mkdir()
run(['php', '-d', 'phar.readonly=0', source / 'scripts/phar/build.php', runtime,
     package / 'builds/code-standards.phar', *(['--gzip'] if len(sys.argv) == 4 else [])], experiment)
payload_identity = json.loads(run(['php', '-r', '$archive = new Phar($argv[1]); echo $archive["distribution-manifest.json"]->getContent();', package / 'builds/code-standards.phar'], experiment))
manifest = json.loads((package / 'composer.json').read_text())
installed = json.loads((runtime / 'vendor/composer/installed.json').read_text())
extensions = {name: '*' for item in installed['packages']
              for name in item.get('require', {}) if name.startswith('ext-')}
manifest['require'] = {'php': '^8.3', 'ext-phar': '*', **dict(sorted(extensions.items()))}
if payload_identity['compression'] == 'GZ':
    manifest['require']['ext-zlib'] = '*'
manifest.pop('autoload', None)
manifest.pop('autoload-dev', None)
manifest.pop('scripts', None)
(package / 'composer.json').write_text(json.dumps(manifest, indent=4) + '\n')
(package / 'bin/code-standards').write_text(
    '#!/usr/bin/env php\n<?php\nrequire __DIR__ . "/../builds/code-standards.phar";\n')
with (package / '.gitattributes').open('a') as stream:
    stream.write('\n/builds/*.phar binary\n/src/ export-ignore\n/config/ export-ignore\n/bin/ecs-worker export-ignore\n')
run(['git', 'init', '-q', '-b', 'main'], package)
run(['git', 'add', '.'], package)
run(['git', '-c', 'user.name=Distribution Fixture', '-c', 'user.email=fixture@example.invalid',
     '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'Synthetic PHAR transport fixture'], package)
commit = run(['git', 'rev-parse', 'HEAD'], package).strip()
(package / '.build').mkdir()
run(['git', 'archive', '--format=zip', '--output=.build/git.zip', 'HEAD'], package)
run(['composer', 'archive', '--format=zip', '--dir=.build', '--file=composer',
     '--no-plugins', '--no-scripts'], package)


def contents(path):
    with zipfile.ZipFile(path) as archive:
        return {name: hashlib.sha256(archive.read(name)).hexdigest()
                for name in archive.namelist() if not name.endswith('/')}


git_archive = contents(package / '.build/git.zip')
assert git_archive == contents(package / '.build/composer.zip')
for resource in (source / 'resources').rglob('*'):
    if resource.is_file():
        name = str(resource.relative_to(source))
        assert git_archive[name] == hashlib.sha256(resource.read_bytes()).hexdigest(), name
assert 'builds/code-standards.phar' in git_archive
assert not any(name.startswith(('src/', 'tests/', 'config/')) for name in git_archive)
metadata = {**manifest, 'version': 'dev-poc',
            'dist': {'type': 'zip', 'url': str(package / '.build/git.zip')},
            'source': {'type': 'git', 'url': str(package), 'reference': commit}}
repositories = [{'type': 'package', 'package': metadata}, {'packagist.org': False}]
for mode in ['dist', 'source', 'global']:
    home = experiment / mode
    home.mkdir()
    (home / 'composer.json').write_text(json.dumps({'name': 'fixture/consumer', 'repositories': repositories}))
    local_env = dict(env, COMPOSER_HOME=str(home)) if mode == 'global' else env
    prefix = ['composer', 'global'] if mode == 'global' else ['composer']
    run([*prefix, 'require', '--prefer-source' if mode == 'source' else '--prefer-dist',
         '--no-scripts', '--no-plugins', 'fast-forward/code-standards:dev-poc'], home, environment=local_env)
    lock = json.loads((home / 'composer.lock').read_text())
    assert [item['name'] for item in lock['packages']] == ['fast-forward/code-standards']
    root = home / 'vendor/fast-forward/code-standards'
    assert (root / 'src').exists() == (mode == 'source')
    run(['php', home / 'vendor/bin/code-standards', 'list', '--raw'], home, environment=local_env)
    # No tracked PHP files: native Git selection must still work from the consumer cwd.
    run(['git', 'init', '-q'], home)
    run(['php', home / 'vendor/bin/code-standards', 'check', '--all'], home, environment=local_env)
    fixture = home / 'source.php'
    fixture.write_text('<?php\n\nfunction add($a,$b){return $a+$b;}\n')
    config = home / 'ecs.php'
    config.write_text(r'''<?php
if (getcwd() !== __DIR__) { throw new \RuntimeException("Changed consumer cwd"); }
$runtime = dirname((new \ReflectionClass(\FastForward\CodeStandards\Config\ECSConfig::class))->getFileName(), 3);
$identity = json_decode(file_get_contents($runtime . '/distribution-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
file_put_contents(getenv('PHAR_PROBE'), json_encode(['runtime' => $runtime, 'mode' => fileperms($runtime) & 0777, 'identity' => $identity]));
return \FastForward\CodeStandards\Config\ECSConfig::configure();
''')
    local_env = dict(local_env, PHAR_PROBE=str(home / 'runtime-probe.json'))
    run(['git', 'add', 'source.php', 'ecs.php'], home)
    before = {path: path.read_bytes() for path in [fixture, config, home / 'composer.json', home / 'composer.lock']}
    run(['php', home / 'vendor/bin/code-standards', 'check', '--all', '--config=ecs.php'], home,
        expected=2, environment=local_env)
    assert all(path.read_bytes() == content for path, content in before.items())
    probe = json.loads((home / 'runtime-probe.json').read_text())
    assert probe['mode'] == 0o700
    assert not Path(probe['runtime']).exists(), 'Runtime was not cleaned up'
    assert probe['identity']['root']['name'] == 'fast-forward/code-standards'
    assert probe['identity'] == payload_identity
    (home / 'vendor/autoload.php').write_text('<?php throw new RuntimeException("Consumer autoload executed");')
    run(['php', home / 'vendor/bin/code-standards', 'list', '--raw'], home, environment=local_env)
    assert not list((experiment / 'temporary files').glob('code-standards-phar-*'))

(experiment / 'result.json').write_text(json.dumps({'status': 'passed', 'fixture_commit': commit,
    'runtime_require': manifest['require'], 'payload_identity': payload_identity, 'archive_files': git_archive,
    'sizes': {'phar': (package / 'builds/code-standards.phar').stat().st_size,
              'git_zip': (package / '.build/git.zip').stat().st_size,
              'composer_zip': (package / '.build/composer.zip').stat().st_size,
              'unpacked_runtime': sum(path.stat().st_size for path in runtime.rglob('*') if path.is_file()),
              'outer_resources': sum(path.stat().st_size for path in (source / 'resources').rglob('*') if path.is_file())},
    'limitation': 'Transport only; PHP 8.3, executable configs, real ECS/Rector/MND and extension minimum remain separate gates.'}, indent=2) + '\n')
print(experiment / 'result.json')
