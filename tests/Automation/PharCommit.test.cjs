'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {createHash} = require('node:crypto');
const {inspect, validateCandidate, commit} = require('../../.github/PharCommit/commit.cjs');

const HEAD = 'a'.repeat(40);
const NEW = 'c'.repeat(40);
const TARGET = 'dist/code-standards';
const blob = bytes => createHash('sha1').update(`blob ${bytes.length}\0`).update(bytes).digest('hex');

function fixture(t) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'standards-phar-commit-'));
  t.after(() => fs.rmSync(directory, {recursive: true, force: true}));
  const bytes = Buffer.from('<?php throw new RuntimeException("never execute downloaded bytes");');
  fs.writeFileSync(path.join(directory, 'code-standards'), bytes);
  const receipt = {version: 1, source_sha: HEAD, run_id: 7, run_attempt: 2,
    sha256: createHash('sha256').update(bytes).digest('hex')};
  const writeReceipt = value => fs.writeFileSync(path.join(directory, 'build-receipt.json'), JSON.stringify(value));
  writeReceipt(receipt);
  const repository = {id: 1, full_name: 'fixture/repo', default_branch: 'main'};
  const run = {id: 7, run_attempt: 2, name: 'Repository: Quality', path: '.github/workflows/repository-ci.yml',
    event: 'pull_request', status: 'completed', conclusion: 'failure', head_sha: HEAD, head_branch: 'codex/fix',
    repository, head_repository: repository, pull_requests: [{number: 97}]};
  const context = {eventName: 'workflow_run', repo: {owner: 'fixture', repo: 'repo'},
    payload: {repository: structuredClone(repository), workflow_run: structuredClone(run)}};
  const candidate = {prNumber: 97, headSha: HEAD, branch: 'codex/fix', artifactId: 9, runId: 7, runAttempt: 2};
  const state = {run, pr: {number: 97, state: 'open', merged: false,
    head: {sha: HEAD, ref: 'codex/fix', repo: repository}, base: {ref: 'main', repo: repository}},
    message: 'fix: reviewed source change', ref: HEAD, oldBlob: blob(Buffer.from('old executable')),
    oldMode: '100755', newMode: '100755', newBlob: blob(bytes), truncated: false,
    artifacts: [[{id: 9, name: `code-standards-update-${HEAD}-2`, expired: false, size_in_bytes: 256}]],
    repository: {...repository, node_id: 'R_verified_repository'}, objects: [], mutations: [], committed: false};
  const reader = {rest: {
    actions: {
      getWorkflowRun: async args => {assert.equal(args.run_id, 7); return {data: structuredClone(state.run)};},
      listWorkflowRunArtifacts: async args => {
        assert.deepEqual({...args, page: 1}, {...context.repo, run_id: 7, per_page: 100, page: 1});
        return {data: {artifacts: structuredClone(state.artifacts[args.page - 1] || [])}};
      }
    },
    pulls: {get: async args => {assert.equal(args.pull_number, 97); return {data: structuredClone(state.pr)};}},
    git: {
      getCommit: async args => {
        assert.ok([HEAD, NEW].includes(args.commit_sha));
        return {data: {sha: args.commit_sha, message: state.message,
          tree: {sha: args.commit_sha === HEAD ? 'b'.repeat(40) : 'd'.repeat(40)}}};
      },
      getTree: async args => {
        assert.equal(args.recursive, '1');
        const old = args.tree_sha === 'b'.repeat(40);
        return {data: {truncated: state.truncated, tree: [{path: TARGET, type: 'blob',
          mode: old ? state.oldMode : state.newMode, sha: old ? state.oldBlob : state.newBlob}]}};
      },
      getRef: async args => {
        assert.equal(args.ref, 'heads/codex/fix');
        return {data: {ref: 'refs/heads/codex/fix', object: {type: 'commit', sha: state.ref}}};
      }
    },
    repos: {get: async args => {assert.deepEqual(args, context.repo); return {data: structuredClone(state.repository)};}, getContent: async args => {
      assert.deepEqual(args, {...context.repo, path: TARGET, ref: HEAD});
      state.onGetContent?.();
      return {data: {type: 'file', sha: state.oldBlob}};
    }}
  }};
  const writer = {rest: {git: {
    createBlob: async args => {
      state.objects.push({method: 'createBlob', ...structuredClone(args)});
      assert.equal(args.encoding, 'base64');
      return {data: {sha: blob(Buffer.from(args.content, 'base64'))}};
    },
    createTree: async args => {
      state.objects.push({method: 'createTree', ...structuredClone(args)});
      return {data: {sha: 'd'.repeat(40)}};
    },
    createCommit: async args => {
      state.objects.push({method: 'createCommit', ...structuredClone(args)});
      state.onCreateCommit?.();
      return {data: {sha: NEW}};
    }
  }}, graphql: async (query, variables) => {
    assert.match(query, /updateRefs\(input: \$input\)/);
    assert.doesNotMatch(query, /createCommitOnBranch|deleteRef/);
    state.mutations.push(structuredClone(variables.input));
    if (state.race) state.ref = state.race;
    if (state.ref !== variables.input.refUpdates[0].beforeOid) throw new Error('EXPECTED_HEAD_MISMATCH');
    state.committed = true;
    state.ref = NEW;
    return {updateRefs: {clientMutationId: null}};
  }};
  return {directory, bytes, receipt, writeReceipt, context, candidate, state, reader, writer,
    inspect: () => inspect({github: reader, context}),
    validate: () => validateCandidate({directory, candidate}),
    commit: () => commit({reader, writer, context, candidate, directory})};
}

test('inspection derives attempt-bound candidate from API identities', async t => {
  const f = fixture(t);
  assert.deepEqual(await f.inspect(), {eligible: true, candidate: f.candidate});
  f.state.run.conclusion = f.context.payload.workflow_run.conclusion = 'success';
  assert.equal((await f.inspect()).eligible, true);
});

test('wrong run identity, event, status, conclusion and source are skipped', async t => {
  for (const [field, value] of [['name', 'Untrusted'], ['path', '.github/workflows/other.yml'],
    ['event', 'push'], ['status', 'in_progress'], ['conclusion', 'cancelled'],
    ['conclusion', 'timed_out'], ['head_sha', 'invalid'], ['head_sha', `${HEAD}\n`], ['head_branch', 'main']]) {
    const f = fixture(t);
    f.state.run[field] = f.context.payload.workflow_run[field] = value;
    assert.equal((await f.inspect()).eligible, false, field);
  }
  const stale = fixture(t);
  stale.state.run.run_attempt = 3;
  assert.equal((await stale.inspect()).eligible, false);
});

test('foreign run/head repositories, fork PRs, moved heads and closed PRs are skipped', async t => {
  const changes = [
    f => {f.state.run.head_repository = {id: 2, full_name: 'fork/repo'};},
    f => {f.state.run.repository = {id: 2, full_name: 'other/repo'};},
    f => {f.state.pr.head.repo = {id: 2, full_name: 'fork/repo'};},
    f => {f.state.pr.base.repo = {id: 2, full_name: 'other/repo'};},
    f => {f.state.pr.base.ref = 'maintenance';},
    f => {f.state.pr.head.sha = NEW;}, f => {f.state.pr.head.ref = 'other';},
    f => {f.state.pr.state = 'closed';}, f => {f.state.pr.merged = true;},
    f => {f.state.run.pull_requests = [];}, f => {f.state.run.pull_requests.push({number: 98});}
  ];
  for (const change of changes) {
    const f = fixture(t);
    change(f);
    assert.equal((await f.inspect()).eligible, false);
    assert.equal(f.state.mutations.length, 0);
  }
});

test('automation-marked commits prevent a build-update loop', async t => {
  const f = fixture(t);
  f.state.message = 'build: refresh compiled executable [phar-ci]';
  assert.equal((await f.inspect()).eligible, false);
});

test('missing, expired and previous-attempt artifacts are skipped; duplicates reject', async t => {
  for (const artifacts of [[], [{id: 9, name: `code-standards-update-${HEAD}-2`, expired: true}],
    [{id: 9, name: `code-standards-update-${HEAD}-1`, expired: false}]]) {
    const f = fixture(t);
    f.state.artifacts = [artifacts];
    assert.equal((await f.inspect()).eligible, false);
  }
  const duplicate = fixture(t);
  duplicate.state.artifacts[0].push({...duplicate.state.artifacts[0][0], id: 10});
  await assert.rejects(duplicate.inspect(), /Ambiguous/);
});

test('artifact selection paginates without hiding a second matching artifact', async t => {
  const f = fixture(t);
  const unrelated = Array.from({length: 100}, (_, i) => ({id: i + 20, name: `other-${i}`, expired: false}));
  f.state.artifacts = [unrelated, f.state.artifacts[0]];
  assert.equal((await f.inspect()).candidate.artifactId, 9);
  f.state.artifacts[0][0] = {...f.state.artifacts[1][0], id: 10};
  await assert.rejects(f.inspect(), /Ambiguous/);
});

test('compressed artifact size is bounded before downloading', async t => {
  for (const size of [undefined, 0, -1, '256', 101 * 1024 * 1024 + 1]) {
    const f = fixture(t);
    f.state.artifacts[0][0].size_in_bytes = size;
    await assert.rejects(f.inspect(), /compressed artifact/);
  }
});

test('validator accepts bounded matching data without executing it', t => {
  const f = fixture(t);
  const payload = f.validate();
  assert.deepEqual(payload.bytes, f.bytes);
  assert.equal(payload.sha256, f.receipt.sha256);
  assert.equal(payload.blobSha, blob(f.bytes));
  assert.deepEqual(fs.readdirSync(f.directory).sort(), ['build-receipt.json', 'code-standards']);
});

test('receipt identity, schema, digest and raw JSON failures reject', t => {
  const receipts = [null, [], {...fixture(t).receipt, version: '1'}, {...fixture(t).receipt, source_sha: NEW},
    {...fixture(t).receipt, run_id: 8}, {...fixture(t).receipt, run_attempt: 1},
    {...fixture(t).receipt, sha256: '0'.repeat(64)}, {...fixture(t).receipt, sha256: 'z'.repeat(64)},
    {...fixture(t).receipt, sha256: `${fixture(t).receipt.sha256}\n`},
    {...fixture(t).receipt, branch: 'main'}];
  for (const receipt of receipts) {
    const f = fixture(t);
    f.writeReceipt(receipt);
    assert.throws(f.validate);
  }
  const malformed = fixture(t);
  fs.writeFileSync(path.join(malformed.directory, 'build-receipt.json'), '{');
  assert.throws(malformed.validate);
});

test('extra entries, directories and symlinks cannot be artifact payloads', t => {
  const extra = fixture(t);
  fs.writeFileSync(path.join(extra.directory, 'untrusted.cjs'), 'throw new Error("never load");');
  assert.throws(extra.validate, /exactly/);
  for (const filename of ['code-standards', 'build-receipt.json']) {
    const f = fixture(t);
    fs.unlinkSync(path.join(f.directory, filename));
    fs.symlinkSync(path.join(f.directory, filename === 'code-standards' ? 'build-receipt.json' : 'code-standards'), path.join(f.directory, filename));
    assert.throws(f.validate, /regular file/);
  }
  const directory = fixture(t);
  fs.unlinkSync(path.join(directory.directory, 'code-standards'));
  fs.mkdirSync(path.join(directory.directory, 'code-standards'));
  assert.throws(directory.validate, /regular file/);
  const linkedDirectory = fixture(t);
  const link = `${linkedDirectory.directory}-link`;
  fs.symlinkSync(linkedDirectory.directory, link);
  t.after(() => fs.unlinkSync(link));
  assert.throws(() => validateCandidate({directory: link, candidate: linkedDirectory.candidate}), /real directory/);
});

test('oversized executable and receipt are rejected before reading payload bytes', t => {
  for (const [filename, size] of [['code-standards', 100 * 1024 * 1024 + 1], ['build-receipt.json', 16 * 1024 + 1]]) {
    const f = fixture(t);
    fs.truncateSync(path.join(f.directory, filename), size);
    assert.throws(f.validate, /size/);
  }
});

test('commit changes only the fixed existing executable with atomic expected-head semantics', async t => {
  const f = fixture(t);
  assert.deepEqual(await f.commit(), {status: 'committed', commitSha: NEW});
  assert.deepEqual(f.state.objects, [
    {method: 'createBlob', ...f.context.repo, content: f.bytes.toString('base64'), encoding: 'base64'},
    {method: 'createTree', ...f.context.repo, base_tree: 'b'.repeat(40),
      tree: [{path: TARGET, mode: '100755', type: 'blob', sha: blob(f.bytes)}]},
    {method: 'createCommit', ...f.context.repo, message: 'build: refresh compiled executable [phar-ci]',
      tree: 'd'.repeat(40), parents: [HEAD]}
  ]);
  assert.deepEqual(f.state.mutations, [{repositoryId: 'R_verified_repository',
    refUpdates: [{name: 'refs/heads/codex/fix', beforeOid: HEAD, afterOid: NEW, force: false}]}]);
  assert.equal(f.state.ref, NEW);
});

test('identical Git blob bytes return noop without invoking the writer', async t => {
  const f = fixture(t);
  f.state.oldBlob = blob(f.bytes);
  assert.deepEqual(await f.commit(), {status: 'noop', headSha: HEAD});
  assert.equal(f.state.mutations.length, 0);
  assert.equal(f.state.objects.length, 0);
});

test('commit revalidates the PR, artifact identity and exact branch ref before writing', async t => {
  for (const change of [f => {f.state.pr.state = 'closed';}, f => {f.state.pr.head.sha = NEW;},
    f => {f.state.ref = NEW;}, f => {f.candidate.branch = 'main';},
    f => {f.candidate.artifactId = 10;}, f => {f.state.onGetContent = () => {f.state.ref = NEW;};}]) {
    const f = fixture(t);
    change(f);
    await assert.rejects(f.commit());
    assert.equal(f.state.mutations.length, 0);
  }
});

test('a changed or malformed payload never reaches the scoped writer', async t => {
  const f = fixture(t);
  fs.appendFileSync(path.join(f.directory, 'code-standards'), 'changed');
  await assert.rejects(f.commit(), /digest/);
  assert.equal(f.state.mutations.length, 0);
});

test('the existing target must be an executable regular Git file in a complete tree', async t => {
  for (const change of [f => {f.state.oldMode = '100644';}, f => {f.state.oldMode = '120000';},
    f => {f.state.truncated = true;}]) {
    const f = fixture(t);
    change(f);
    await assert.rejects(f.commit());
    assert.equal(f.state.mutations.length, 0);
  }
});

test('server CAS conflicts reject both competing forward updates and branch rewinds', async t => {
  for (const race of ['e'.repeat(40), 'f'.repeat(40)]) {
    const f = fixture(t);
    f.state.race = race;
    await assert.rejects(f.commit(), /EXPECTED_HEAD_MISMATCH/);
    assert.equal(f.state.committed, false);
    assert.equal(f.state.ref, race);
    assert.equal(f.state.mutations.length, 1);
    assert.equal(f.state.objects.length, 3);
  }
});

test('repository node identity comes only from the verified repository API response', async t => {
  for (const change of [f => {f.state.repository.id = 2;},
    f => {f.state.repository.full_name = 'other/repo';}, f => {f.state.repository.node_id = '';},
    f => {f.state.repository.default_branch = 'codex/fix';}]) {
    const f = fixture(t);
    change(f);
    await assert.rejects(f.commit(), /Repository node identity/);
    assert.equal(f.state.objects.length, 0);
    assert.equal(f.state.mutations.length, 0);
  }
  const f = fixture(t);
  f.context.payload.repository.node_id = 'R_untrusted_event_value';
  await f.commit();
  assert.equal(f.state.mutations[0].repositoryId, 'R_verified_repository');
});

test('PR/ref revalidation after object creation leaves orphan objects without attaching a commit', async t => {
  for (const change of [f => {f.state.pr.state = 'closed';}, f => {f.state.ref = 'e'.repeat(40);},
    f => {f.state.ref = 'f'.repeat(40);}]) {
    const f = fixture(t);
    f.state.onCreateCommit = () => change(f);
    await assert.rejects(f.commit(), /changed before commit/);
    assert.equal(f.state.objects.length, 3);
    assert.equal(f.state.mutations.length, 0);
    assert.equal(f.state.committed, false);
  }
});

test('post-mutation mode and content are read back instead of assumed preserved', async t => {
  for (const change of [f => {f.state.newMode = '100644';}, f => {f.state.newBlob = '0'.repeat(40);}]) {
    const f = fixture(t);
    change(f);
    await assert.rejects(f.commit(), /created but executable validation failed/);
    assert.equal(f.state.committed, true);
    assert.equal(f.state.mutations.length, 1);
  }
});
