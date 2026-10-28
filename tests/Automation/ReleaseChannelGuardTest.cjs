const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const workflow = fs.readFileSync(path.join(__dirname, '../../.github/workflows/repository-release-channels.yml'), 'utf8');
const body = workflow.split('          script: |\n')[1].split('      - name:')[0]
  .trimEnd().split('\n').map(line => line.slice(12)).join('\n');
const run = new (Object.getPrototypeOf(async function(){}).constructor)('github', 'context', 'core', body);
const sha = 'a'.repeat(40);

async function select(tag, names, overrides = {}) {
  const outputs = {};
  const release = {id: 1, tag_name: tag, draft: false, prerelease: false, published_at: '2026-10-28T00:00:00Z', ...overrides};
  const tags = names.map(name => ({name, commit: {sha}}));
  const context = {repo: {owner: 'fixture', repo: 'repo'}, sha, payload: {release: {id: 1, tag_name: tag}}};
  const listTags = Symbol('listTags');
  const github = {
    rest: {repos: {
      getRelease: async args => {assert.equal(args.release_id, 1); return {data: release};}, listTags
    }},
    paginate: async (method, args) => {assert.equal(method, listTags); assert.equal(args.per_page, 100); return tags;}
  };
  process.env.FF_RELEASE_TAG = tag;
  process.env.FF_RELEASE_SHA = sha;
  process.env.FF_RELEASE_ID = '1';
  await run(github, context, {setOutput: (name, value) => {outputs[name] = value;}});
  return outputs;
}

test('editing older releases never selects a major or latest update', async () => {
  for (const [tag, tags] of [
    ['v0.4.1', ['v0.4.1', 'v0.4.2', 'v1.0.0']],
    ['v1.0.0', ['v0.4.2', 'v1.0.0', 'v1.0.1']]
  ]) assert.deepEqual(await select(tag, tags), {promote: false, latest: false});
});

test('a later 0.x after 1.x selects only its major', async () => {
  assert.deepEqual(await select('v0.4.3', ['v0.4.2', 'v0.4.3', 'v1.0.0']), {promote: true, latest: false});
});

test('0.x to 1.x selects v1 and the default latest tag', async () => {
  assert.deepEqual(await select('v1.0.0', ['v0.4.2', 'v1.0.0']), {promote: true, latest: true});
});

test('version ordering is numeric and tolerates components beyond safe integers', async () => {
  assert.deepEqual(await select('v0.10.0', ['v0.9.0', 'v0.10.0']), {promote: true, latest: true});
  assert.deepEqual(await select('v0.9007199254740992.0', ['v0.9007199254740992.0', 'v0.9007199254740993.0']), {promote: false, latest: false});
});

test('moving major/latest names are not concrete stable versions', async () => {
  assert.deepEqual(await select('v0.4.2', ['v0', 'v1', 'latest', 'v0.4.2']), {promote: true, latest: true});
});

test('unpublished, non-stable or mismatched live releases fail closed', async () => {
  for (const override of [{draft: true}, {prerelease: true}, {published_at: null}, {tag_name: 'v0.4.3'}]) {
    await assert.rejects(select('v0.4.2', ['v0.4.2'], override));
  }
  await assert.rejects(select('v0.4.2', ['v0.4.1']));
  for (const name of ['v0', '0.4.2', 'v0.04.2', 'v0.4.2-rc1', 'v0.4.2+build', 'v0.4.2\n', '$(exit 0)']) {
    await assert.rejects(select(name, [name]));
  }
});
