---
category: fixed
type: patch
---

Negotiate the built-in HTML renderer using its actual UTF-8 media type. Honor
charset-specific Accept preferences and exclusions instead of choosing HTML when
its charset is explicitly forbidden or falling back when UTF-8 HTML is accepted.
