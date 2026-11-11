'use strict';

const fs = require('node:fs');
const path = require('node:path');
const {createHash} = require('node:crypto');

const SHA = /^[0-9a-f]{40}$/;
const DIGEST = /^[0-9a-f]{64}$/;
const isSha = value => typeof value === 'string' && value.length === 40 && SHA.test(value);
const TARGET = 'builds/code-standards';
const MARKER = '[phar-ci]';
const candidateFields = ['prNumber', 'headSha', 'branch', 'artifactId', 'runId', 'runAttempt'];
const positiveInteger = value => Number.isSafeInteger(value) && value > 0;
const sameRepository = (repository, name) => typeof repository?.full_name === 'string'
  && repository.full_name.toLowerCase() === name.toLowerCase();

function skip(core, reason) {
  core?.info?.(`Skipping compiled executable update: ${reason}`);
  return {eligible: false, reason};
}

function validCandidate(candidate) {
  return candidate && isSha(candidate.headSha) && typeof candidate.branch === 'string'
    && candidate.branch.length > 0 && !candidate.branch.startsWith('refs/')
    && ['prNumber', 'artifactId', 'runId', 'runAttempt'].every(field => positiveInteger(candidate[field]));
}

function currentPullRequest(pr, candidate, repository, repositoryId, baseBranch) {
  return pr?.state === 'open' && pr.merged !== true && pr.number === candidate.prNumber
    && sameRepository(pr.head?.repo, repository) && pr.head.repo.id === repositoryId
    && sameRepository(pr.base?.repo, repository) && pr.base.repo.id === repositoryId
    && pr.base.ref === baseBranch
    && pr.head.sha === candidate.headSha && pr.head.ref === candidate.branch;
}

/** Derives every ref from GitHub's run/PR APIs, never from the downloaded artifact. */
async function inspect({github, context, core}) {
  const event = context.payload?.workflow_run;
  const repository = `${context.repo.owner}/${context.repo.repo}`;
  if (context.eventName !== 'workflow_run' || !positiveInteger(event?.id)
    || !positiveInteger(event?.run_attempt)) return skip(core, 'not a completed workflow-run event');
  const {data: run} = await github.rest.actions.getWorkflowRun({...context.repo, run_id: event.id});
  const fields = ['id', 'run_attempt', 'name', 'path', 'event', 'status', 'conclusion', 'head_sha', 'head_branch'];
  if (fields.some(field => run[field] !== event[field]) || run.name !== 'Repository: Quality'
    || run.path !== '.github/workflows/repository-ci.yml' || run.event !== 'pull_request'
    || run.status !== 'completed' || !['success', 'failure'].includes(run.conclusion)
    || !isSha(run.head_sha) || typeof run.head_branch !== 'string' || !run.head_branch
    || run.head_branch === context.payload.repository?.default_branch) return skip(core, 'ineligible or superseded quality run');
  if (!sameRepository(context.payload.repository, repository) || !sameRepository(run.repository, repository)
    || !sameRepository(run.head_repository, repository) || !sameRepository(event.repository, repository)
    || !sameRepository(event.head_repository, repository) || !positiveInteger(run.repository.id)
    || run.repository.id !== context.payload.repository.id || run.head_repository.id !== run.repository.id
    || event.repository.id !== run.repository.id || event.head_repository.id !== run.repository.id) return skip(core, 'foreign repository or fork');
  if (!Array.isArray(run.pull_requests) || run.pull_requests.length !== 1
    || !positiveInteger(run.pull_requests[0].number)) return skip(core, 'no unique associated pull request');
  const candidate = {prNumber: run.pull_requests[0].number, headSha: run.head_sha,
    branch: run.head_branch, runId: run.id, runAttempt: run.run_attempt};
  const {data: pr} = await github.rest.pulls.get({...context.repo, pull_number: candidate.prNumber});
  if (!currentPullRequest(pr, candidate, repository, run.repository.id, context.payload.repository.default_branch)) return skip(core, 'pull request closed, foreign or no longer at source head');
  const {data: head} = await github.rest.git.getCommit({...context.repo, commit_sha: candidate.headSha});
  if (head.sha !== candidate.headSha || typeof head.message !== 'string') throw new Error('Malformed source commit response.');
  if (head.message.includes(MARKER)) return skip(core, 'automation-marked source commit');
  const name = `code-standards-update-${candidate.headSha}-${candidate.runAttempt}`;
  const matches = [];
  for (let page = 1; ; page++) {
    const {data} = await github.rest.actions.listWorkflowRunArtifacts({...context.repo, run_id: run.id, per_page: 100, page});
    if (!Array.isArray(data.artifacts)) throw new Error('Malformed artifact response.');
    matches.push(...data.artifacts.filter(artifact => artifact.name === name && artifact.expired === false));
    if (matches.length > 1) throw new Error('Ambiguous compiled executable artifacts.');
    if (data.artifacts.length < 100) break;
  }
  if (matches.length === 0) return skip(core, 'no current-attempt update artifact');
  if (!positiveInteger(matches[0].id)) throw new Error('Invalid artifact identity.');
  if (!positiveInteger(matches[0].size_in_bytes) || matches[0].size_in_bytes > 101 * 1024 * 1024) {
    throw new Error('Invalid or oversized compressed artifact.');
  }
  candidate.artifactId = matches[0].id;
  if (!validCandidate(candidate)) throw new Error('Invalid candidate identity.');
  return {eligible: true, candidate};
}

function readRegularFile(directory, name, maximum) {
  const filename = path.join(directory, name);
  const before = fs.lstatSync(filename);
  if (!before.isFile() || before.isSymbolicLink() || before.size > maximum || before.size === 0) {
    throw new Error(`Invalid regular file or size: ${name}`);
  }
  const descriptor = fs.openSync(filename, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW | fs.constants.O_NONBLOCK);
  try {
    const opened = fs.fstatSync(descriptor);
    if (!opened.isFile() || opened.size > maximum || opened.size !== before.size || opened.ino !== before.ino) {
      throw new Error(`Artifact file changed while opening: ${name}`);
    }
    const bytes = fs.readFileSync(descriptor);
    if (bytes.length !== opened.size) throw new Error(`Artifact file changed while reading: ${name}`);
    return bytes;
  } finally {
    fs.closeSync(descriptor);
  }
}

/** Reads and hashes bounded data only; it never loads or executes PHP/PHAR code. */
function validateCandidate({directory, candidate}) {
  if (!validCandidate(candidate)) throw new Error('Invalid candidate identity.');
  const stat = fs.lstatSync(directory);
  if (!stat.isDirectory() || stat.isSymbolicLink()) throw new Error('Artifact directory must be a real directory.');
  if (JSON.stringify(fs.readdirSync(directory).sort()) !== JSON.stringify(['build-receipt.json', 'code-standards'])) {
    throw new Error('Artifact must contain exactly the executable and build receipt.');
  }
  const receipt = JSON.parse(readRegularFile(directory, 'build-receipt.json', 16 * 1024).toString('utf8'));
  const keys = ['run_attempt', 'run_id', 'sha256', 'source_sha', 'version'];
  if (!receipt || Array.isArray(receipt) || JSON.stringify(Object.keys(receipt).sort()) !== JSON.stringify(keys)
    || receipt.version !== 1 || !isSha(receipt.source_sha) || typeof receipt.sha256 !== 'string'
    || receipt.sha256.length !== 64 || !DIGEST.test(receipt.sha256)
    || receipt.source_sha !== candidate.headSha || receipt.run_id !== candidate.runId
    || receipt.run_attempt !== candidate.runAttempt) throw new Error('Invalid build receipt identity.');
  const bytes = readRegularFile(directory, 'code-standards', 100 * 1024 * 1024);
  const sha256 = createHash('sha256').update(bytes).digest('hex');
  if (sha256 !== receipt.sha256) throw new Error('Executable digest differs from the build receipt.');
  const blobSha = createHash('sha1').update(`blob ${bytes.length}\0`).update(bytes).digest('hex');
  return {bytes, sha256, blobSha};
}

async function executableEntry(reader, context, commitSha) {
  const {data: commit} = await reader.rest.git.getCommit({...context.repo, commit_sha: commitSha});
  if (commit.sha !== commitSha || !isSha(commit.tree?.sha)) throw new Error('Invalid commit tree identity.');
  const {data: tree} = await reader.rest.git.getTree({...context.repo, tree_sha: commit.tree.sha, recursive: '1'});
  if (tree.truncated !== false || !Array.isArray(tree.tree)) throw new Error('Incomplete commit tree.');
  const entries = tree.tree.filter(entry => entry.path === TARGET);
  if (entries.length !== 1 || entries[0].type !== 'blob' || entries[0].mode !== '100755'
    || !isSha(entries[0].sha)) throw new Error('Tracked executable must already be a regular 100755 Git blob.');
  return {...entries[0], treeSha: commit.tree.sha};
}

async function assertCurrent(reader, context, candidate) {
  const repository = `${context.repo.owner}/${context.repo.repo}`;
  const {data: pr} = await reader.rest.pulls.get({...context.repo, pull_number: candidate.prNumber});
  if (!currentPullRequest(pr, candidate, repository, context.payload.repository.id, context.payload.repository.default_branch)) throw new Error('Pull request changed before commit.');
  const {data: ref} = await reader.rest.git.getRef({...context.repo, ref: `heads/${candidate.branch}`});
  if (ref.ref !== `refs/heads/${candidate.branch}` || ref.object?.type !== 'commit'
    || ref.object.sha !== candidate.headSha) throw new Error('Branch changed before commit.');
}

/** The scoped writer performs one server-side expected-head mutation, without executing candidate bytes. */
async function commit({reader, writer, context, core, candidate, directory}) {
  if (!validCandidate(candidate)) throw new Error('Invalid candidate identity.');
  const fresh = await inspect({github: reader, context, core});
  if (!fresh.eligible || candidateFields.some(field => fresh.candidate[field] !== candidate[field])) {
    throw new Error('Candidate is no longer eligible.');
  }
  const payload = validateCandidate({directory, candidate});
  const entry = await executableEntry(reader, context, candidate.headSha);
  const {data: existing} = await reader.rest.repos.getContent({...context.repo, path: TARGET, ref: candidate.headSha});
  if (Array.isArray(existing) || existing.type !== 'file' || existing.sha !== entry.sha) throw new Error('Executable content/tree identity mismatch.');
  await assertCurrent(reader, context, candidate);
  if (existing.sha === payload.blobSha) return {status: 'noop', headSha: candidate.headSha};
  const {data: repository} = await reader.rest.repos.get({...context.repo});
  if (!sameRepository(repository, `${context.repo.owner}/${context.repo.repo}`)
    || repository.id !== context.payload.repository.id || typeof repository.node_id !== 'string'
    || !repository.node_id || repository.default_branch !== context.payload.repository.default_branch) {
    throw new Error('Repository node identity or default branch mismatch.');
  }
  const {data: createdBlob} = await writer.rest.git.createBlob({...context.repo,
    content: payload.bytes.toString('base64'), encoding: 'base64'});
  if (createdBlob.sha !== payload.blobSha) throw new Error('Created blob identity differs from executable bytes.');
  const {data: createdTree} = await writer.rest.git.createTree({...context.repo, base_tree: entry.treeSha,
    tree: [{path: TARGET, mode: '100755', type: 'blob', sha: createdBlob.sha}]});
  if (!isSha(createdTree.sha)) throw new Error('Invalid created tree identity.');
  const {data: createdCommit} = await writer.rest.git.createCommit({...context.repo,
    message: 'build: refresh compiled executable [phar-ci]', tree: createdTree.sha, parents: [candidate.headSha]});
  const commitSha = createdCommit.sha;
  if (!isSha(commitSha)) throw new Error('Commit creation returned no valid commit identity.');
  await assertCurrent(reader, context, candidate);
  // Unlike force:false alone, beforeOid atomically rejects both advances and rewinds.
  await writer.graphql(`mutation AttachCompiledExecutable($input: UpdateRefsInput!) {
    updateRefs(input: $input) { clientMutationId }
  }`, {input: {repositoryId: repository.node_id,
    refUpdates: [{name: `refs/heads/${candidate.branch}`, beforeOid: candidate.headSha,
      afterOid: commitSha, force: false}]}});
  try {
    const updated = await executableEntry(reader, context, commitSha);
    if (updated.sha !== payload.blobSha) throw new Error('Committed executable bytes differ.');
  } catch (error) {
    throw new Error(`Commit ${commitSha} was created but executable validation failed: ${error.message}`, {cause: error});
  }
  return {status: 'committed', commitSha};
}

module.exports = {inspect, validateCandidate, commit};
