import test from 'node:test';
import assert from 'node:assert/strict';
import { planRelease, publishRelease } from '../../scripts/release.mjs';

const sha = 'a'.repeat(40);
const repository = 'coisa/error-handler';
const pr = { merged_at: '2026-10-15T00:00:00Z', merge_commit_sha: sha,
  base: { ref: 'master', repo: { full_name: repository } },
  head: { ref: 'changeset-release/master', repo: { full_name: repository } },
  user: { login: 'github-actions[bot]' } };
const input = { manifest: { name: '@coisa/error-handler', private: true, version: '3.0.0' },
  previous: { name: '@coisa/error-handler', private: true, version: '2.0.0' },
  changelog: '# Changelog\n## 3.0.0\n\n### Major Changes\n\nNew lifecycle.\n\n## 2.0.0\nOld notes.',
  sha, repository, prs: [pr] };
const plan = planRelease(input);
test('baseline and unchanged versions do not publish', () => {
  assert.equal(planRelease({ ...input, previous: null }), null);
  assert.equal(planRelease({ ...input, previous: input.manifest }), null);
});
test('extracts only the exact release notes', () => {
  assert.equal(plan.tag, '3.0.0');
  assert.equal(plan.notes, '### Major Changes\n\nNew lifecycle.');
});
for (const [label, changes] of [
  ['ordinary feature PR', { prs: [{ ...pr, head: { ...pr.head, ref: 'feature' } }] }],
  ['unmerged PR', { prs: [{ ...pr, merged_at: null }] }],
  ['fork', { prs: [{ ...pr, head: { ...pr.head, repo: { full_name: 'other/fork' } } }] }],
  ['different merge SHA', { prs: [{ ...pr, merge_commit_sha: 'b'.repeat(40) }] }],
  ['non-bot author', { prs: [{ ...pr, user: { login: 'someone' } }] }],
  ['duplicate version PR', { prs: [pr, pr] }],
  ['missing notes', { changelog: '# Changelog\n## 2.0.0\nOld' }],
  ['duplicate notes', { changelog: '## 3.0.0\nA\n## 3.0.0\nB' }],
  ['empty notes', { changelog: '## 3.0.0\n\n## 2.0.0\nOld' }],
  ['downgrade', { manifest: { ...input.manifest, version: '1.0.0' } }],
  ['public npm package', { manifest: { ...input.manifest, private: false } }],
]) test('rejects ' + label, () => assert.throws(() => planRelease({ ...input, ...changes })));
test('creates an immutable tag and GitHub release', () => {
  const writes = [];
  publishRelease(plan, (path, body) => {
    if (!body) throw Object.assign(new Error('Missing'), { status: 404 });
    writes.push([path, body]); return {};
  });
  assert.deepEqual(writes.map(([path]) => path.split('/').at(-1)), ['refs', 'releases']);
  assert.equal(writes[0][1].sha, sha);
  assert.equal(writes[1][1].body, plan.notes);
});
test('matching existing release is idempotent', () => {
  publishRelease(plan, (path, body) => {
    assert.equal(body, undefined);
    return path.includes('/git/ref/') ? { object: { type: 'commit', sha } } : { tag_name: '3.0.0', draft: false };
  });
});
test('refuses conflicting tags', () => {
  assert.throws(() => publishRelease(plan, () => ({ object: { type: 'commit', sha: 'b'.repeat(40) } })), /elsewhere/);
});
test('API errors do not masquerade as absent tags', () => {
  assert.throws(() => publishRelease(plan, () => { throw Object.assign(new Error('Denied'), { status: 403 }); }), /Denied/);
});
