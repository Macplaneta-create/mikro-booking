---
name: Update Project Docs
description: "Synchronize STATUS.md, NEXT_SESSION.md, ROADMAP.md and checklists after coding work."
argument-hint: "What changed in code, what was tested, what still needs confirmation"
agent: "agent"
---

Update project documentation after recent coding changes.

Follow this workflow:

1. Read STATUS.md, NEXT_SESSION.md, ROADMAP.md, README.md, QA_CHECKLIST.md, RELEASE_CHECKLIST.md.
2. Compare docs with recent code changes or verified test results.
3. Update STATUS.md first:
   - separate implemented from verified,
   - mark blockers, risks, regressions,
   - update top priorities.
4. Update NEXT_SESSION.md:
   - keep only top 1-3 next actions,
   - remove old completed history.
5. Update ROADMAP.md only if medium-term priorities changed.
6. Keep release and QA details in checklist files, not in roadmap.
7. Move version-specific snapshots to docs/archive/.

Use status language consistently:

- Zweryfikowane
- Zaimplementowane, do potwierdzenia
- W trakcie
- Planowane
- Do dopracowania / naprawy

When done, provide a short summary of exactly which docs were changed and why.