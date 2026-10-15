import assert from 'node:assert/strict';
import { test } from 'node:test';
import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { planRelease, readApi } from './release.mjs';

function fixture(branch = 'master') {
  const repository = 'example/library';
  const sha = 'a'.repeat(40);

  return {
    event: { repository: { full_name: repository, default_branch: branch }, ref: `refs/heads/${branch}` },
    sha, currentSha: sha,
    pr: {
      number: 12, html_url: 'https://github.com/example/library/pull/12',
      user: { login: 'github-actions[bot]' },
      head: { ref: `changeset-release/${branch}`, repo: { full_name: repository } },
      base: { ref: branch, repo: { full_name: repository } },
      merge_commit_sha: sha, merged_at: '2026-01-01T00:00:00Z', merged_by: { login: 'maintainer' },
    },
    manifest: { name: '@example/library', version: '2.0.1', private: true },
    lock: { name: '@example/library', version: '2.0.1', packages: { '': { name: '@example/library', version: '2.0.1' } } },
    previousVersion: '2.0.0', pendingNotes: [],
    changelog: '# @example/library\n\n## 2.0.1\n\n### Patch Changes\n\n- Fix a reported error.\n\n## 2.0.0\n\nPrevious notes.\n',
    changes: ['M\tpackage.json', 'M\tpackage-lock.json', 'A\tCHANGELOG.md', 'D\t.changeset/fix.md'],
    tags: [{ name: '2.0.0', sha: 'b'.repeat(40) }],
  };
}

for (const branch of ['master', 'main']) {
  test(`prepare the merged version and record the actual merger on ${branch}`, () => {
    const plan = planRelease(fixture(branch));
    assert.equal(plan.tag, 'v2.0.1');
    assert.equal(plan.tagExists, false);
    assert.match(plan.notes, /Fix a reported error/);
    assert.match(plan.notes, /merged by @maintainer/);
    assert.doesNotMatch(plan.notes, /Previous notes/);
  });
}

test('ordinary merges, stale runs and other branches never publish', () => {
  assert.equal(planRelease({ ...fixture(), pr: null }), null);
  assert.equal(planRelease({ ...fixture(), currentSha: 'c'.repeat(40) }), null);
  const input = fixture();
  input.event.ref = 'refs/heads/feature';
  assert.equal(planRelease(input), null);
});

const invalid = {
  'a human-created version branch': input => { input.pr.user.login = 'contributor'; },
  'a fork version PR': input => { input.pr.head.repo.full_name = 'fork/library'; },
  'a different target repository': input => { input.pr.base.repo.full_name = 'fork/library'; },
  'a different target branch': input => { input.pr.base.ref = 'other'; },
  'a different version branch': input => { input.pr.head.ref = 'feature'; },
  'a different merge SHA': input => { input.pr.merge_commit_sha = 'c'.repeat(40); },
  'an unmerged version PR': input => { input.pr.merged_at = null; },
  'a missing merger': input => { input.pr.merged_by = null; },
  'a public npm package': input => { input.manifest.private = false; },
  'a prerelease': input => { input.manifest.version = '2.0.1-beta.1'; },
  'an unchanged version': input => { input.previousVersion = '2.0.1'; },
  'a downgraded version': input => { input.previousVersion = '2.1.0'; },
  'an inconsistent lockfile': input => { input.lock.version = '2.0.0'; },
  'an inconsistent root lockfile': input => { input.lock.packages[''].version = '2.0.0'; },
  'another npm package': input => { input.lock.name = '@other/library'; },
  'unconsumed release notes': input => { input.pendingNotes.push('pending.md'); },
  'unrelated source changes': input => { input.changes.push('M\tsrc/ErrorHandler.php'); },
  'modified release notes': input => { input.changes.push('M\t.changeset/fix.md'); },
  'removed maintenance instructions': input => { input.changes.push('D\t.changeset/README.md'); },
  'a missing changelog entry': input => { input.changelog = '## 2.0.0\nOld version.'; },
  'an empty changelog entry': input => { input.changelog = '## 2.0.1\n\n'; },
  'a conflicting tag': input => { input.tags.push({ name: 'v2.0.1', sha: 'c'.repeat(40) }); },
  'an existing version alias': input => { input.tags.push({ name: '2.0.1', sha: input.sha }); },
  'an older version than the latest tag': input => { input.tags.push({ name: 'v2.1.0', sha: 'c'.repeat(40) }); },
};

for (const [name, mutate] of Object.entries(invalid)) {
  test(`reject ${name}`, () => {
    const input = fixture();
    mutate(input);
    assert.throws(() => planRelease(input));
  });
}

test('retry a matching immutable tag without creating another tag', () => {
  const input = fixture();
  input.tags.push({ name: 'v2.0.1', sha: input.sha });
  assert.equal(planRelease(input).tagExists, true);
});

test('compare version components numerically', () => {
  const input = fixture();
  input.previousVersion = '2.0.9';
  input.manifest.version = input.lock.version = input.lock.packages[''].version = '2.0.10';
  input.changelog = '## 2.0.10\n\nA later patch.\n';
  assert.equal(planRelease(input).version, '2.0.10');
});

test('only an optional 404 means absent; authentication and server failures propagate', async () => {
  const response = status => async () => ({ status, ok: status === 200, json: async () => ({ id: 1 }) });
  assert.equal(await readApi('fixture', { optional: true, fetcher: response(404) }), null);
  assert.deepEqual(await readApi('fixture', { fetcher: response(200) }), { id: 1 });
  for (const status of [401, 403, 404, 429, 500]) {
    await assert.rejects(readApi('fixture', { fetcher: response(status) }));
    if (status !== 404) await assert.rejects(readApi('fixture', { optional: true, fetcher: response(status) }));
  }
});

function inRepository(callback) {
  const directory = mkdtempSync(join(tmpdir(), 'error-handler-release-'));
  const run = (command, args, extraEnv = {}) => spawnSync(command, args, {
    cwd: directory, encoding: 'utf8', timeout: 30_000,
    env: { ...process.env, GIT_CONFIG_GLOBAL: '/dev/null', GIT_CONFIG_NOSYSTEM: '1', ...extraEnv },
  });
  const git = (...args) => {
    const result = run('git', args);
    assert.equal(result.status, 0, result.stderr);
    return result.stdout.trim();
  };

  try {
    mkdirSync(join(directory, '.changeset'));
    cpSync('.changeset/config.json', join(directory, '.changeset/config.json'));
    cpSync('package.json', join(directory, 'package.json'));
    symlinkSync(resolve('node_modules'), join(directory, 'node_modules'), 'dir');
    writeFileSync(join(directory, '.gitignore'), 'node_modules/\n');
    git('init', '-b', 'master');
    git('config', 'user.name', 'Release fixture');
    git('config', 'user.email', 'release@example.invalid');
    const bare = join(directory, 'origin.git');
    git('init', '--bare', bare);
    git('remote', 'add', 'origin', bare);
    writeFileSync(join(directory, '.gitignore'), 'node_modules/\norigin.git/\n');
    callback({ directory, run, git });
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
}

test('an old base-branch note cannot mask a missing PR note in the local command', () => {
  inRepository(({ directory, run, git }) => {
    const note = '---\n"@coisa/error-handler": patch\n---\nA compatible fix.\n';
    writeFileSync(join(directory, '.changeset/base.md'), note);
    writeFileSync(join(directory, 'composer.json'), '{}\n');
    git('add', '.');
    git('commit', '-m', 'Base with an unconsumed release note');
    git('update-ref', 'refs/remotes/origin/master', 'HEAD');
    writeFileSync(join(directory, 'composer.json'), '{"description":"A new PR change"}\n');
    git('add', 'composer.json');
    git('commit', '-m', 'Change a runtime file without a new note');
    const bareStatus = run('node', [resolve('node_modules/@changesets/cli/bin.js'), 'status']);
    assert.equal(bareStatus.status, 0, bareStatus.stderr);
    const missing = run('npm', ['run', 'changeset:status']);
    assert.equal(missing.status, 1, missing.stdout + missing.stderr);
    assert.match(missing.stdout + missing.stderr, /no changesets/i);
    writeFileSync(join(directory, '.changeset/pr.md'), note);
    git('add', '.changeset/pr.md');
    git('commit', '-m', 'Document this PR');
    const ownNote = run('npm', ['run', 'changeset:status']);
    assert.equal(ownNote.status, 0, ownNote.stdout + ownNote.stderr);
  });
});

test('the official CLI creates one private-package tag, emits action metadata and skips an existing tag', () => {
  inRepository(({ directory, run, git }) => {
    const manifest = JSON.parse(readFileSync(join(directory, 'package.json'), 'utf8'));
    manifest.version = '2.0.1';
    writeFileSync(join(directory, 'package.json'), JSON.stringify(manifest));
    git('add', '.');
    git('commit', '-m', 'Prepared version');
    const output = join(directory, 'changesets-output.ndjson');
    const env = { CHANGESETS_OUTPUT: output, npm_config_registry: 'http://127.0.0.1:1' };
    const args = [resolve('node_modules/@changesets/cli/bin.js'), 'publish'];
    const published = run('node', args, env);
    assert.equal(published.status, 0, published.stdout + published.stderr);
    assert.equal(git('tag', '--list'), 'v2.0.1');
    assert.equal(git('rev-parse', 'v2.0.1^{commit}'), git('rev-parse', 'HEAD'));
    assert.deepEqual(readFileSync(output, 'utf8').trim().split('\n').map(JSON.parse), [
      { type: 'git-tag', tag: 'v2.0.1', packageName: '@coisa/error-handler' },
    ]);
    rmSync(output);
    const retry = run('node', args, env);
    assert.equal(retry.status, 0, retry.stdout + retry.stderr);
    assert.equal(git('tag', '--list'), 'v2.0.1');
    assert.ok(!existsSync(output) || readFileSync(output, 'utf8').trim() === '');
    assert.equal(git('ls-remote', '--tags', 'origin'), '', 'The local rehearsal must never push');
  });
});
