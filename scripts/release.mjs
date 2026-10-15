import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const name = '@coisa/error-handler';
const versionPattern = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;
const shaPattern = /^[a-f0-9]{40}$/;

/** Permit publication only for the exact merged bot version PR and its notes. */
export function planRelease({ manifest, previous, changelog, sha, repository, prs }) {
  if (manifest.name !== name || manifest.private !== true || !versionPattern.test(manifest.version)) {
    throw new Error('Expected a private tooling manifest with a stable version.');
  }
  if (!previous || previous.version === manifest.version) return null;
  if (previous.name !== name || !versionPattern.test(previous.version)) throw new Error('Invalid baseline.');
  const current = manifest.version.split('.').map(Number);
  const old = previous.version.split('.').map(Number);
  const difference = current.findIndex((value, index) => value !== old[index]);
  if (current[difference] < old[difference]) throw new Error('Release versions must increase.');
  if (!shaPattern.test(sha)) throw new Error('Expected an immutable merge commit.');
  const matches = prs.filter(pr => pr.merged_at && pr.merge_commit_sha === sha &&
    pr.base?.ref === 'master' && pr.base?.repo?.full_name === repository &&
    pr.head?.ref === 'changeset-release/master' && pr.head?.repo?.full_name === repository &&
    pr.user?.login === 'github-actions[bot]');
  if (matches.length !== 1) throw new Error('Expected one merged Changesets bot version PR.');
  const lines = changelog.split(/\r?\n/);
  const starts = lines.flatMap((line, index) => line === '## ' + manifest.version ? [index] : []);
  if (starts.length !== 1) throw new Error('Expected exactly one changelog section.');
  const end = lines.findIndex((line, index) => index > starts[0] && /^## /.test(line));
  const notes = lines.slice(starts[0] + 1, end < 0 ? undefined : end).join('\n').trim();
  if (!notes) throw new Error('Release notes must not be empty.');
  return { tag: manifest.version, sha, notes, repository };
}

/** Reuse matching tags/releases; never move a tag or overwrite an existing release. */
export function publishRelease(plan, api) {
  const root = 'repos/' + plan.repository;
  const optional = path => {
    try { return api(path); } catch (error) {
      if (error.status === 404) return null;
      throw error;
    }
  };
  const ref = optional(root + '/git/ref/tags/' + plan.tag);
  let object = ref?.object;
  const seen = new Set();
  while (object?.type === 'tag') {
    if (!shaPattern.test(object.sha) || seen.has(object.sha)) throw new Error('Invalid annotated tag chain.');
    seen.add(object.sha);
    object = api(root + '/git/tags/' + object.sha).object;
  }
  if (ref && (object?.type !== 'commit' || object.sha !== plan.sha)) throw new Error('Tag points elsewhere.');
  if (!ref) api(root + '/git/refs', { ref: 'refs/tags/' + plan.tag, sha: plan.sha });
  const release = optional(root + '/releases/tags/' + plan.tag);
  if (release) {
    if (release.draft || release.tag_name !== plan.tag) throw new Error('Incompatible existing release.');
    return;
  }
  api(root + '/releases', { tag_name: plan.tag, target_commitish: plan.sha, name: plan.tag,
    body: plan.notes, draft: false, prerelease: false });
}

/** Pass arguments and JSON through stdin without shell interpolation or credential output. */
function api(path, body) {
  try {
    return JSON.parse(execFileSync('gh', ['api', path, ...(body ? ['--method', 'POST', '--input', '-'] : [])],
      { encoding: 'utf8', input: body ? JSON.stringify(body) : undefined, stdio: ['pipe', 'pipe', 'pipe'] }));
  } catch (error) {
    if (/HTTP 404/.test(String(error.stderr))) error.status = 404;
    throw error;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const { GITHUB_EVENT_NAME: event, GITHUB_REF: ref, GITHUB_SHA: sha,
    GITHUB_REPOSITORY: repository, RELEASE_BEFORE: before } = process.env;
  if (event !== 'push' || ref !== 'refs/heads/master' || repository !== 'coisa/error-handler' ||
      !shaPattern.test(sha ?? '') || !shaPattern.test(before ?? '')) throw new Error('Expected a verified master push.');
  const manifest = JSON.parse(readFileSync('package.json', 'utf8'));
  const exists = !/^0{40}$/.test(before) &&
    execFileSync('git', ['ls-tree', '--name-only', before, '--', 'package.json'], { encoding: 'utf8' }).trim();
  const previous = exists ? JSON.parse(execFileSync('git', ['show', before + ':package.json'], { encoding: 'utf8' })) : null;
  const plan = planRelease({ manifest, previous, sha, repository, changelog: readFileSync('CHANGELOG.md', 'utf8'),
    prs: previous && previous.version !== manifest.version
      ? api('repos/' + repository + '/commits/' + sha + '/pulls?per_page=100')
        .map(pr => api('repos/' + repository + '/pulls/' + pr.number)) : [] });
  if (plan) publishRelease(plan, api);
  console.log(plan ? 'GitHub release ' + plan.tag + ' is present at ' + sha : 'No version change to publish.');
}
