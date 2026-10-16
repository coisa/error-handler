import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { test } from 'node:test';

// Execute the actual default-branch scripts against real temporary files and
// minimal API-shaped fixtures. No token, network request or live comment is used.
const workflow = fs.readFileSync(new URL('../../.github/workflows/coverage-comment.yml', import.meta.url), 'utf8');
const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;
const require = createRequire(import.meta.url);

function scriptFor(name) {
  const step = workflow.split(`      - name: ${name}\n`)[1]?.split('\n      - name: ')[0];
  assert.ok(step, `Missing publisher step: ${name}`);
  const script = step.split('          script: |\n')[1];
  assert.ok(script, `Missing inline script: ${name}`);
  return new AsyncFunction('github', 'context', 'core', 'require', 'process',
    script.split('\n').map(line => line.slice(12)).join('\n'));
}

const discover = scriptFor('Find the current PR and triggering report');
const prepare = scriptFor('Validate Markdown and recheck the current head');

function fixture(t, { conclusion = 'success', fork = false } = {}) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'coverage-publisher-'));
  t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
  const reportRoot = path.join(directory, 'download');
  fs.mkdirSync(reportRoot);
  const reportFile = path.join(reportRoot, 'code-coverage-results.md');
  fs.writeFileSync(reportFile, '### PHPUnit coverage\n\nLine coverage is **4.71%**.\n');
  const repository = { id: 98326226, full_name: 'coisa/error-handler' };
  const source = fork ? { id: 1234, full_name: 'contributor/error-handler' } : repository;
  const run = {
    id: 38834098323,
    workflow_id: 385530000,
    run_attempt: 1,
    name: 'PHP CI',
    path: '.github/workflows/ci.yml',
    event: 'pull_request',
    status: 'completed',
    conclusion,
    head_sha: '27ff64ec6395646d630b3d451f1fdd48f1515305',
    repository: structuredClone(repository),
    head_repository: structuredClone(source),
  };
  const pr = {
    number: 14,
    state: 'open',
    head: { sha: run.head_sha, repo: structuredClone(source) },
    base: { repo: structuredClone(repository) },
  };
  const artifact = {
    id: 12018771920,
    name: 'coverage-pr-comment',
    size_in_bytes: 200,
    expired: false,
    workflow_run: {
      id: run.id,
      head_sha: run.head_sha,
      repository_id: repository.id,
      head_repository_id: source.id,
    },
  };
  const context = { repo: { owner: 'coisa', repo: 'error-handler' }, payload: { repository, workflow_run: run } };
  const state = {
    run,
    currentRun: structuredClone(run),
    pr,
    candidates: [pr],
    artifacts: [artifact],
    output: {},
    messages: [],
    reportRoot,
    reportFile,
    commentFile: path.join(directory, 'comment.md'),
  };
  const github = {
    rest: {
      actions: {
        getWorkflowRun: async args => {
          assert.equal(args.run_id, run.id);
          return { data: state.currentRun };
        },
        listWorkflowRunArtifacts: Symbol('artifacts'),
      },
      repos: { listPullRequestsAssociatedWithCommit: Symbol('pulls') },
      pulls: {
        get: async args => {
          assert.equal(args.pull_number, pr.number);
          return { data: state.pr };
        },
      },
    },
    paginate: async (method, args) => {
      if (method === github.rest.actions.listWorkflowRunArtifacts) {
        assert.equal(args.run_id, run.id);
        return state.artifacts;
      }
      assert.equal(method, github.rest.repos.listPullRequestsAssociatedWithCommit);
      assert.equal(args.commit_sha, run.head_sha);
      return state.candidates;
    },
  };
  const core = {
    setOutput: (key, value) => { state.output[key] = value; },
    info: message => state.messages.push(message),
  };
  const process = {
    env: { PR_NUMBER: String(pr.number), REPORT_ROOT: reportRoot, COMMENT_PATH: state.commentFile },
  };
  state.discover = () => discover(github, context, core, require, process);
  state.prepare = () => prepare(github, context, core, require, process);
  return state;
}

for (const options of [{}, { conclusion: 'failure' }, { fork: true }]) {
  test(`current PR exports bounded Markdown: ${JSON.stringify(options)}`, async t => {
    const state = fixture(t, options);
    await state.discover();
    assert.deepEqual(state.output, { number: 14, artifact: 12018771920 });
    state.output = {};
    await state.prepare();
    assert.deepEqual(state.output, { number: 14 });
    const body = fs.readFileSync(state.commentFile, 'utf8');
    assert.ok(body.includes(`Tested commit: \`${state.run.head_sha}\``));
    assert.ok(body.includes(`/actions/runs/${state.run.id}/attempts/1`));
    assert.ok(body.includes(`**${state.run.conclusion}**`));
  });
}

for (const [name, mutate] of [
  ['closed PR', state => { state.pr.state = 'closed'; }],
  ['stale SHA', state => { state.pr.head.sha = 'a'.repeat(40); }],
  ['wrong base repository', state => { state.pr.base.repo.full_name = 'other/repository'; }],
  ['wrong head repository', state => { state.pr.head.repo.id = 999; }],
  ['ambiguous PRs', state => { state.candidates.push(structuredClone(state.pr)); }],
  ['missing Markdown artifact', state => { state.artifacts = []; }],
  ['superseded attempt', state => { state.currentRun.run_attempt = 2; }],
]) {
  test(`discovery skips ${name}`, async t => {
    const state = fixture(t);
    mutate(state);
    await state.discover();
    assert.deepEqual(state.output, {});
    assert.ok(state.messages.length > 0);
  });
}

for (const [name, mutate] of [
  ['wrong workflow path', state => { state.run.path = '.github/workflows/unrelated.yml'; }],
  ['wrong repository', state => { state.run.repository.id = 999; }],
  ['wrong run SHA', state => { state.currentRun.head_sha = 'a'.repeat(40); }],
  ['invalid run ID', state => { state.run.id = -1; }],
  ['duplicate artifacts', state => { state.artifacts.push(structuredClone(state.artifacts[0])); }],
  ['expired artifact', state => { state.artifacts[0].expired = true; }],
  ['different artifact run', state => { state.artifacts[0].workflow_run.id = 999; }],
  ['different artifact SHA', state => { state.artifacts[0].workflow_run.head_sha = 'a'.repeat(40); }],
  ['different artifact head repository', state => { state.artifacts[0].workflow_run.head_repository_id = 999; }],
  ['oversized archive', state => { state.artifacts[0].size_in_bytes = 1000001; }],
  ['malformed archive size', state => { delete state.artifacts[0].size_in_bytes; }],
]) {
  test(`discovery rejects ${name}`, async t => {
    const state = fixture(t);
    mutate(state);
    await assert.rejects(state.discover);
    assert.deepEqual(state.output, {});
  });
}

for (const [name, mutate] of [
  ['head moved after discovery', state => { state.pr.head.sha = 'a'.repeat(40); }],
  ['PR closed after discovery', state => { state.pr.state = 'closed'; }],
  ['attempt changed after discovery', state => { state.currentRun.run_attempt = 2; }],
]) {
  test(`publication skips ${name}`, async t => {
    const state = fixture(t);
    await state.discover();
    state.output = {};
    mutate(state);
    await state.prepare();
    assert.deepEqual(state.output, {});
    assert.equal(fs.existsSync(state.commentFile), false);
  });
}
for (const [name, mutate] of [
  ['empty file', state => fs.writeFileSync(state.reportFile, '')],
  ['oversized file', state => fs.writeFileSync(state.reportFile, 'a'.repeat(60001))],
  ['directory', state => { fs.unlinkSync(state.reportFile); fs.mkdirSync(state.reportFile); }],
  ['symbolic link', state => {
    const target = path.join(path.dirname(state.reportRoot), 'target.md');
    fs.renameSync(state.reportFile, target);
    fs.symlinkSync(target, state.reportFile);
  }],
  ['invalid UTF-8', state => fs.writeFileSync(state.reportFile, Buffer.from([0xff]))],
  ['NUL content', state => fs.writeFileSync(state.reportFile, 'coverage\0data')],
  ['missing file', state => fs.unlinkSync(state.reportFile)],
]) {
  test(`publication rejects ${name}`, async t => {
    const state = fixture(t);
    mutate(state);
    await assert.rejects(state.prepare);
    assert.deepEqual(state.output, {});
    assert.equal(fs.existsSync(state.commentFile), false);
  });
}
