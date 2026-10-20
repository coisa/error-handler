---
category: changed
type: patch
---

Install the Changelog agent skill through the development-only `llm/skills`
Composer plugin and the committed skill-source configuration. Keep generated
agent instructions out of Git and Composer distributions instead of maintaining
a versioned copy of the upstream skill.
Also synchronize the Container and Config skills from their installed Composer
packages.
