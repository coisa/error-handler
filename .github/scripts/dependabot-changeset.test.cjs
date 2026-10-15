const assert = require('node:assert/strict');
const { test } = require('node:test');
const maintainNote = require('./dependabot-changeset.cjs');
const { isEligible, releaseNote, validateNotes } = maintainNote;

const repository = { id: 123, default_branch: 'master' };
const oldHead = 'a'.repeat(40);
const newHead = 'b'.repeat(40);
const notePath = '.changeset/dependabot-42.md';

function fixture(files = [{ filename: 'composer.json', status: 'modified' }]) {
  const pr = {
    number: 42,
    state: 'open',
    user: { login: 'dependabot[bot]', id: 49699333 },
    head: { repo: { id: 123 }, ref: 'dependabot/composer/psr-log-3', sha: oldHead },
    base: { repo: { id: 123 }, ref: 'master' },
    changed_files: files.length,
  };
  const run = { event: 'pull_request', head_repository: { id: 123 }, head_sha: oldHead, pull_requests: [{ number: 42 }] };
  const context = { repo: { owner: 'coisa', repo: 'error-handler' }, payload: { repository, workflow_run: run } };
  const writes = [];
  const outputs = {};
  let content = '---\n"@coisa/error-handler": patch\n---\n\nUpdate dependencies.\n';
  const github = {
    rest: {
      pulls: {
        get: async () => ({ data: structuredClone(pr) }),
        listFiles: async () => {},
      },
      repos: {
        listPullRequestsAssociatedWithCommit: async () => {},
        getContent: async () => ({ data: { type: 'file', size: Buffer.byteLength(content), encoding: 'base64', content: Buffer.from(content).toString('base64') } }),
      },
      git: {
        getCommit: async () => ({ data: { tree: { sha: 'base-tree' } } }),
        createTree: async (input) => { writes.push(input); return { data: { sha: 'note-tree' } }; },
        createCommit: async (input) => { writes.push(input); return { data: { sha: newHead } }; },
        updateRef: async (input) => { writes.push(input); pr.head.sha = input.sha; },
      },
    },
    paginate: async (route) => route === github.rest.pulls.listFiles ? files : [{ number: 42 }],
  };
  const core = { info: () => {}, setOutput: (name, value) => { outputs[name] = value; } };
  return { pr, run, context, github, core, writes, outputs, files, setContent: (value) => { content = value; } };
}

test('requires the real bot, same repository, default base, bot branch and exact current SHA', () => {
  const { pr, run } = fixture();
  assert.equal(isEligible(pr, run, repository), true);
  for (const mutate of [
    (p) => { p.state = 'closed'; },
    (p) => { p.user.id = 1; },
    (p) => { p.user.login = 'dependabot'; },
    (p) => { p.head.repo.id = 456; },
    (p) => { p.head.repo = null; },
    (p) => { p.base.repo.id = 456; },
    (p) => { p.base.ref = 'other'; },
    (p) => { p.head.ref = 'feature'; },
    (p) => { p.head.sha = newHead; },
  ]) {
    const changed = structuredClone(pr);
    mutate(changed);
    assert.equal(isEligible(changed, run, repository), false);
  }
});

test('creates one patch note without requiring composer.lock', () => {
  assert.equal(releaseNote([{ filename: 'composer.json' }], '@coisa/error-handler', notePath),
    '---\n"@coisa/error-handler": patch\n---\n\nUpdate Composer dependencies.\n');
});

test('summarizes dependency areas without copying arbitrary PR text', () => {
  const files = ['package-lock.json', '.github/workflows/ci.yml', 'composer.lock'].map((filename) => ({ filename }));
  const content = releaseNote(files, '@coisa/error-handler', notePath);
  assert.match(content, /Composer dependencies and GitHub Actions used by repository maintenance and development-only Changesets dependencies/);
  assert.equal(content.trim().split('\n\n').length, 2);
});

test('preserves maintainer classification and rejects unexpected or renamed files', () => {
  assert.equal(releaseNote([{ filename: '.changeset/manual.md' }], '@coisa/error-handler', notePath), null);
  for (const file of [
    { filename: 'src/ErrorHandler.php' },
    { filename: '.changeset/config.json' },
    { filename: 'composer.json', status: 'renamed', previous_filename: 'other.json' },
    { filename: notePath, status: 'removed' },
  ]) assert.throws(() => releaseNote([file], '@coisa/error-handler', notePath));
});

test('writes only the deterministic note and fast-forwards the expected parent', async () => {
  const f = fixture();
  await maintainNote(f);
  assert.deepEqual(f.writes[0].tree.map(({ path }) => path), [notePath]);
  assert.equal(f.writes[0].base_tree, 'base-tree');
  assert.deepEqual(f.writes[1].parents, [oldHead]);
  assert.match(f.writes[1].message, /\[dependabot skip\]/);
  assert.match(f.writes[1].message, /Signed-off-by: github-actions\[bot\] <41898282\+github-actions\[bot\]@users\.noreply\.github\.com>/);
  assert.deepEqual(f.writes[1].author, { name: 'github-actions[bot]', email: '41898282+github-actions[bot]@users.noreply.github.com' });
  assert.deepEqual(f.writes[2], { owner: 'coisa', repo: 'error-handler', ref: `heads/${f.pr.head.ref}`, sha: newHead, force: false });
  assert.equal(f.outputs.head, newHead);
  assert.equal(f.outputs['note-paths'], JSON.stringify([notePath]));
});

test('repeated runs and later bot updates preserve the note without duplicate commits', async () => {
  const f = fixture([{ filename: 'composer.json' }, { filename: notePath }]);
  await maintainNote(f);
  await maintainNote(f);
  assert.equal(f.writes.length, 0);
  assert.equal(f.outputs.head, oldHead);
  assert.equal(f.outputs['note-paths'], JSON.stringify([notePath]));
});

test('recreates the same note after a Dependabot rebase drops it', async () => {
  const f = fixture();
  await maintainNote(f);
  f.pr.head.sha = oldHead;
  await maintainNote(f);
  assert.equal(f.writes.length, 6);
  assert.equal(f.writes[3].tree[0].path, notePath);
});

test('preserves a manually supplied major note instead of creating a patch', async () => {
  const f = fixture([{ filename: 'composer.json' }, { filename: '.changeset/manual.md' }]);
  await maintainNote(f);
  assert.equal(f.writes.length, 0);
  assert.equal(f.outputs['note-paths'], JSON.stringify(['.changeset/manual.md']));
});

test('ignores non-PR runs, fork runs, stale heads and closed PRs', async () => {
  for (const mutate of [
    (f) => { f.run.event = 'workflow_dispatch'; },
    (f) => { f.run.head_repository.id = 456; },
    (f) => { f.pr.head.sha = newHead; },
    (f) => { f.pr.state = 'closed'; },
  ]) {
    const f = fixture();
    mutate(f);
    await maintainNote(f);
    assert.equal(f.writes.length, 0);
    assert.deepEqual(f.outputs, {});
  }
});

test('does not update a branch that advances while its note commit is prepared', async () => {
  const f = fixture();
  f.github.rest.git.createCommit = async () => {
    f.pr.head.sha = 'c'.repeat(40);
    return { data: { sha: newHead } };
  };
  await maintainNote(f);
  assert.equal(f.writes.length, 1);
  assert.deepEqual(f.outputs, {});
});

test('falls back to commit-associated PR metadata when workflow_run omits PRs', async () => {
  const f = fixture();
  f.run.pull_requests = [];
  await maintainNote(f);
  assert.equal(f.outputs.head, newHead);
});

test('rejects unrelated files and oversized updates before any writes', async () => {
  const unrelated = fixture([{ filename: 'src/ErrorHandler.php' }]);
  await assert.rejects(() => maintainNote(unrelated), /unexpected/);
  assert.equal(unrelated.writes.length, 0);
  const oversized = fixture();
  oversized.pr.changed_files = 101;
  await assert.rejects(() => maintainNote(oversized), /100 files/);
  assert.equal(oversized.writes.length, 0);
});

test('never forces a ref update or reports success when its fast-forward is rejected', async () => {
  const f = fixture();
  f.github.rest.git.updateRef = async ({ force }) => {
    assert.equal(force, false);
    throw new Error('Not a fast forward');
  };
  await assert.rejects(() => maintainNote(f), /fast forward/);
  assert.deepEqual(f.outputs, {});
});

test('uses the actual Changesets parser for API note data and rejects invalid releases', async () => {
  const f = fixture();
  const validate = () => validateNotes({ ...f, head: oldHead, paths: [notePath] });
  await validate();
  f.setContent('---\n"@coisa/error-handler": major\n---\n\nA reviewed breaking change.\n');
  await validate();
  for (const content of [
    'not a Changeset',
    '---\n"@other/package": patch\n---\n\nWrong package.\n',
    '---\n"@coisa/error-handler": invalid\n---\n\nInvalid bump.\n',
    '---\n"@coisa/error-handler": patch\n---\n\n',
    '---\n{}\n---\n\nNo release.\n',
  ]) {
    f.setContent(content);
    await assert.rejects(validate);
  }
});

test('bounds API note paths and sizes rather than treating arbitrary files as notes', async () => {
  const f = fixture();
  await assert.rejects(() => validateNotes({ ...f, head: oldHead, paths: ['package.json'] }), /path/);
  await assert.rejects(() => validateNotes({ ...f, head: oldHead, paths: [] }), /1–100/);
  f.setContent('x'.repeat(65537));
  await assert.rejects(() => validateNotes({ ...f, head: oldHead, paths: [notePath] }), /64 KiB/);
});
