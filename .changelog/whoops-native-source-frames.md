---
category: fixed
type: patch
---

Require optional Whoops `^2.18.7` for development HTML, adopting its upstream fix
for native STDIN and `php -r` source inspection. Reject older Whoops versions
through Composer without making the optional package mandatory. Preserve original exception
frames, one original report, legitimate application warnings and the existing
Whoops renderer response policy. Align installation guidance and the bundled
consumer skill, with bounded native source regressions alongside the renderer's
unit tests.
