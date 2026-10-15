import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { appendFileSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const stableVersion = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;

function compareVersions(left, right) {
  const a = left.split('.').map(BigInt);
  const b = right.split('.').map(BigInt);

  for (let index = 0; index < a.length; index++) {
    if (a[index] !== b[index]) return a[index] > b[index] ? 1 : -1;
  }

  return 0;
}

/** Validate a merged version PR before allowing the official Changesets publisher. */
export function planRelease({ event, sha, currentSha, pr, manifest, lock, previousVersion, pendingNotes, changelog, changes, tags }) {
  const repository = event.repository.full_name;
  const branch = event.repository.default_branch;

  if (currentSha !== sha || event.ref !== `refs/heads/${branch}` || !pr) return null;

  assert.equal(pr.user.login, 'github-actions[bot]', 'Version PR must be created by GitHub Actions');
  assert.equal(pr.head.ref, `changeset-release/${branch}`, 'Unexpected version branch');
  assert.equal(pr.head.repo.full_name, repository, 'Version PR must belong to this repository');
  assert.equal(pr.base.repo.full_name, repository, 'Unexpected base repository');
  assert.equal(pr.base.ref, branch, 'Unexpected base branch');
  assert.equal(pr.merge_commit_sha, sha, 'Only the merged version PR commit can be released');
  assert.ok(pr.merged_at && pr.merged_by?.login, 'Version PR must have an identified merger');
  assert.equal(manifest.private, true, 'The npm package must remain private');
  assert.match(manifest.version, stableVersion, 'Only stable versions are supported by this workflow');
  assert.match(previousVersion, stableVersion, 'Missing previous stable version');
  assert.ok(compareVersions(manifest.version, previousVersion) > 0, 'Version must increase');
  assert.equal(lock.version, manifest.version, 'Lockfile version differs');
  assert.equal(lock.packages[''].version, manifest.version, 'Root lockfile version differs');
  assert.equal(lock.name, manifest.name, 'Lockfile package differs');
  assert.equal(lock.packages[''].name, manifest.name, 'Root lockfile package differs');
  assert.deepEqual(pendingNotes, [], 'Version PR must consume all pending notes');
  assert.ok(changes.length > 0, 'Empty version PR');

  for (const change of changes) {
    assert.match(change, /^(?:[AM]\t(?:package\.json|package-lock\.json|CHANGELOG\.md)|D\t\.changeset\/(?!README\.md$)[^/]+\.md)$/, 'Version PR contains unrelated changes');
  }

  const entry = changelog.split(/^## /m).slice(1).find(section => section.split('\n')[0].trim() === manifest.version);
  assert.ok(entry?.slice(manifest.version.length).trim(), 'Missing changelog entry for this version');
  const tag = `v${manifest.version}`;

  for (const existing of tags) {
    const version = existing.name.replace(/^v/, '');
    if (!stableVersion.test(version)) continue;
    assert.ok(compareVersions(manifest.version, version) >= 0, 'A newer version is already tagged');
    if (version === manifest.version) {
      assert.equal(existing.name, tag, 'This version already has a tag without the v prefix');
      assert.equal(existing.sha, sha, 'Existing tag points to a different commit');
    }
  }

  return {
    tag,
    version: manifest.version,
    tagExists: tags.some(existing => existing.name === tag),
    notes: `${entry.slice(manifest.version.length).trim()}\n\nReleased from [#${pr.number}](${pr.html_url}); merged by @${pr.merged_by.login}.\n`,
  };
}

/** Treat only an explicitly permitted HTTP 404 as an absent release. */
export async function readApi(path, { optional = false, fetcher = fetch } = {}) {
  const response = await fetcher(`${process.env.GITHUB_API_URL || 'https://api.github.com'}/${path}`, {
    headers: {
      Authorization: `Bearer ${process.env.GH_TOKEN}`,
      Accept: 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28',
    },
    signal: AbortSignal.timeout(15_000),
  });

  if (optional && response.status === 404) return null;
  assert.ok(response.ok, `GitHub API request failed (${response.status}): ${path}`);

  return response.json();
}

const git = (...args) => execFileSync('git', args, { encoding: 'utf8', timeout: 15_000 }).trim();
const json = path => JSON.parse(readFileSync(path, 'utf8'));

async function verifyTag(repository, tag, sha) {
  let object = (await readApi(`repos/${repository}/git/ref/tags/${encodeURIComponent(tag)}`)).object;

  while (object.type === 'tag') {
    object = (await readApi(`repos/${repository}/git/tags/${object.sha}`)).object;
  }

  assert.equal(object.type, 'commit', 'Tag must resolve to a commit');
  assert.equal(object.sha, sha, 'Published tag points to a different commit');
}

async function main() {
  assert.equal(process.env.GITHUB_EVENT_NAME, 'push', 'Publication requires a default-branch push');
  const event = json(process.env.GITHUB_EVENT_PATH);
  const repository = process.env.GITHUB_REPOSITORY;
  const sha = process.env.GITHUB_SHA;
  assert.equal(event.repository.full_name, repository, 'Unexpected event repository');
  const manifest = json('package.json');
  const tag = `v${manifest.version}`;
  const mode = process.argv[2];

  if (mode === 'verify-tag' || mode === 'verify') {
    await verifyTag(repository, tag, sha);
    if (mode === 'verify') {
      const release = await readApi(`repos/${repository}/releases/tags/${encodeURIComponent(tag)}`);
      assert.equal(release.draft, false, 'Release is still a draft');
      assert.equal(release.prerelease, false, 'Unexpected prerelease');
      assert.equal(release.tag_name, tag, 'Unexpected release tag');
    }
    return;
  }

  assert.equal(mode, 'prepare', 'Expected prepare, verify-tag or verify');
  const output = value => appendFileSync(process.env.GITHUB_OUTPUT, `${value}\n`);
  const current = await readApi(`repos/${repository}/git/ref/heads/${encodeURIComponent(event.repository.default_branch)}`);
  if (current.object.sha !== sha) return output('eligible=false');
  const associated = await readApi(`repos/${repository}/commits/${sha}/pulls?per_page=100`);
  const candidates = associated.filter(pr => pr.head.ref === `changeset-release/${event.repository.default_branch}` && pr.merge_commit_sha === sha);
  assert.ok(candidates.length <= 1, 'Ambiguous version PR');
  if (!candidates.length) return output('eligible=false');
  const pr = await readApi(`repos/${repository}/pulls/${candidates[0].number}`);
  const tags = git('tag', '--list').split('\n').filter(name => stableVersion.test(name.replace(/^v/, ''))).map(name => ({ name, sha: git('rev-parse', `${name}^{commit}`) }));
  const plan = planRelease({
    event, sha, currentSha: current.object.sha, pr, manifest,
    lock: json('package-lock.json'),
    previousVersion: JSON.parse(git('show', `${event.before}:package.json`)).version,
    pendingNotes: readdirSync('.changeset').filter(name => name.endsWith('.md') && name !== 'README.md'),
    changelog: readFileSync('CHANGELOG.md', 'utf8'),
    changes: git('diff', '--name-status', '--no-renames', event.before, sha).split('\n'),
    tags,
  });
  if (!plan) return output('eligible=false');
  const release = await readApi(`repos/${repository}/releases/tags/${encodeURIComponent(plan.tag)}`, { optional: true });
  assert.ok(!release || plan.tagExists, 'Release exists without its expected tag');
  if (release) {
    assert.equal(release.draft, false, 'Existing release is a draft');
    assert.equal(release.prerelease, false, 'Existing release is a prerelease');
  }
  writeFileSync(join(process.env.RUNNER_TEMP, 'release-notes.md'), plan.notes);
  output(`eligible=true\ntag=${plan.tag}\nversion=${plan.version}\ntag-exists=${plan.tagExists}\nrelease-exists=${Boolean(release)}`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(error => {
    console.error(error.message);
    process.exitCode = 1;
  });
}
