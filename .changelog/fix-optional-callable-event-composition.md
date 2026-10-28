---
category: fixed
type: patch
---

Skip structural autowiring failure for an unconfigured optional callable reporter so the default major aggregate still delivers PSR-14 events. Preserve configured reporter order and explicit factory failures, and document the container's remaining concrete fallback boundary.
