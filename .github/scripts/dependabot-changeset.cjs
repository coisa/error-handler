const { readFileSync } = require('node:fs');

const BOT_ID = 49699333;
const COMMIT_AUTHOR = {
  name: 'github-actions[bot]',
  email: '41898282+github-actions[bot]@users.noreply.github.com',
};

function isEligible(pr, run, repository) {
  return pr.state === 'open'
    && pr.user.id === BOT_ID
    && pr.user.login === 'dependabot[bot]'
    && pr.head.repo?.id === repository.id
    && pr.base.repo?.id === repository.id
    && pr.base.ref === repository.default_branch
    && pr.head.ref.startsWith('dependabot/')
    && pr.head.sha === run.head_sha;
}

function releaseNote(files, packageName, notePath) {
  const areas = new Set();

  for (const { filename, status, previous_filename: previous } of files) {
    if (status === 'renamed' || previous) {
      throw new Error('Review renamed files manually before adding a release note.');
    }

    if (filename === notePath && status !== 'removed') continue;
    if (/^\.changeset\/[^/]+\.md$/.test(filename) && filename !== '.changeset/README.md') {
      if (status === 'removed') throw new Error('Review removed release notes manually.');
      return null; // A maintainer already supplied the release classification.
    }
    if (['composer.json', 'composer.lock'].includes(filename)) {
      areas.add('Composer dependencies');
    } else if (['package.json', 'package-lock.json'].includes(filename)) {
      areas.add('development-only Changesets dependencies');
    } else if (/^\.github\/workflows\/[^/]+\.ya?ml$/.test(filename)) {
      areas.add('GitHub Actions used by repository maintenance');
    } else {
      throw new Error(`Review unexpected dependency-update file manually: ${filename}`);
    }
  }

  if (!areas.size) throw new Error('No dependency changes were found.');

  return `---\n${JSON.stringify(packageName)}: patch\n---\n\nUpdate ${[...areas].sort().join(' and ')}.\n`;
}

async function maintainNote({ github, context, core }) {
  const { repository, workflow_run: run } = context.payload;
  if (run?.event !== 'pull_request' || run.head_repository?.id !== repository.id) return;

  const repo = context.repo;
  const associated = run.pull_requests?.length ? run.pull_requests : await github.paginate(
    github.rest.repos.listPullRequestsAssociatedWithCommit,
    { ...repo, commit_sha: run.head_sha, per_page: 100 },
  );

  for (const { number: pull_number } of associated) {
    const { data: pr } = await github.rest.pulls.get({ ...repo, pull_number });
    if (!isEligible(pr, run, repository)) continue;
    if (pr.changed_files > 100) throw new Error('Review updates with more than 100 files manually.');

    const files = await github.paginate(github.rest.pulls.listFiles, {
      ...repo, pull_number, per_page: 100,
    });
    const notePath = `.changeset/dependabot-${pull_number}.md`;
    const packageName = JSON.parse(readFileSync('package.json', 'utf8')).name;
    const content = releaseNote(files, packageName, notePath);
    let head = pr.head.sha;

    if (content && !files.some(({ filename }) => filename === notePath)) {
      const { data: base } = await github.rest.git.getCommit({ ...repo, commit_sha: head });
      const { data: tree } = await github.rest.git.createTree({
        ...repo,
        base_tree: base.tree.sha,
        tree: [{ path: notePath, mode: '100644', type: 'blob', content }],
      });
      const { data: commit } = await github.rest.git.createCommit({
        ...repo,
        message: `chore(release): add dependency update Changeset [dependabot skip]\n\nSigned-off-by: ${COMMIT_AUTHOR.name} <${COMMIT_AUTHOR.email}>`,
        author: COMMIT_AUTHOR,
        tree: tree.sha,
        parents: [head],
      });

      // Non-forced fast-forward rejects a concurrently updated dependency branch.
      const { data: current } = await github.rest.pulls.get({ ...repo, pull_number });
      if (!isEligible(current, run, repository)) {
        core.info(`PR #${pull_number} advanced; its next run will add the note.`);
        continue;
      }
      await github.rest.git.updateRef({
        ...repo, ref: `heads/${pr.head.ref}`, sha: commit.sha, force: false,
      });
      head = commit.sha;
      core.info(`Added ${notePath} to PR #${pull_number} at ${head}.`);
    } else {
      core.info(`Preserved the existing release note for PR #${pull_number}.`);
    }

    // GITHUB_TOKEN commits do not trigger pull_request CI. A separate trusted
    // job validates only note data, followed by an exact-SHA check publisher.
    const { data: current } = await github.rest.pulls.get({ ...repo, pull_number });
    if (current.state !== 'open' || current.head.sha !== head) continue;
    const notes = files.filter(({ filename, status }) => status !== 'removed'
      && /^\.changeset\/[^/]+\.md$/.test(filename)
      && filename !== '.changeset/README.md').map(({ filename }) => filename);
    core.setOutput('head', head);
    core.setOutput('pull-number', pull_number);
    core.setOutput('note-paths', JSON.stringify(notes.length ? notes : [notePath]));
    return;
  }
}

async function validateNotes({ github, context, head, paths }) {
  const { default: parse } = await import('@changesets/parse');
  const packageName = JSON.parse(readFileSync('package.json', 'utf8')).name;
  let matchingRelease = false;

  if (!paths.length || paths.length > 100) throw new Error('Expected 1–100 release notes.');

  for (const path of paths) {
    if (!/^\.changeset\/[^/]+\.md$/.test(path) || path === '.changeset/README.md') {
      throw new Error('Unexpected release-note path.');
    }
    const { data: file } = await github.rest.repos.getContent({ ...context.repo, path, ref: head });
    if (file.type !== 'file' || file.size > 65536 || file.encoding !== 'base64') {
      throw new Error('Expected a regular release-note file of at most 64 KiB.');
    }
    const note = parse(Buffer.from(file.content, 'base64').toString('utf8'));
    if (!note.summary.trim()) throw new Error('Release notes need a summary.');
    for (const release of note.releases) {
      if (release.name !== packageName || !['patch', 'minor', 'major'].includes(release.type)) {
        throw new Error('Review unexpected release packages or bump types.');
      }
      matchingRelease = true;
    }
  }

  if (!matchingRelease) throw new Error('No release note targets the library package.');
}

module.exports = maintainNote;
module.exports.isEligible = isEligible;
module.exports.releaseNote = releaseNote;
module.exports.validateNotes = validateNotes;
