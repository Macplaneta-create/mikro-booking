# Mikro Booking Copilot Instructions

## Documentation workflow

This repository uses a fixed documentation split. Do not mix these roles.

- `STATUS.md` is the source of truth between sessions.
- `NEXT_SESSION.md` contains only the next short execution plan.
- `ROADMAP.md` contains medium-term product direction and backlog.
- `QA_CHECKLIST.md` contains manual regression steps.
- `RELEASE_CHECKLIST.md` contains release and deployment readiness steps.
- `docs/archive/` contains historical snapshots only.

## When to update documentation

After meaningful work, check whether documentation should change.

Update `STATUS.md` when:
- a feature moved from planned to implemented,
- a feature was actually verified manually or by tests,
- a known issue, risk, regression, or blocker was discovered,
- the next priority changed.

Update `NEXT_SESSION.md` when:
- the top 1-3 next actions changed,
- a session plan was completed or replaced.

Update `ROADMAP.md` only when:
- product priorities changed,
- a roadmap item changed phase or was completed.

Do not add release-history notes to active planning files. Put version snapshots in `docs/archive/`.

## Status language rules

Do not treat code presence as confirmation that something works.

Use these distinctions consistently:
- `Zweryfikowane`: confirmed manually or by tests.
- `Zaimplementowane, do potwierdzenia`: code exists but still needs regression or clean-environment validation.
- `W trakcie`: active work, not ready.
- `Planowane`: backlog only.
- `Do dopracowania / naprawy`: known problems, polish, risks, or regressions.

## Session close-out rule

At the end of a substantial coding session, spend 2 minutes checking whether `STATUS.md` and `NEXT_SESSION.md` need updates.

Prefer small factual edits over long narratives.

## Session start rule

At the beginning of a coding session, first recover context from `STATUS.md` and `NEXT_SESSION.md` before implementing changes.

If context is unclear, run the `start-session` skill and derive top 1-3 tasks for the current session.

## Archive rule

If a document is version-specific, move it to `docs/archive/` instead of keeping it in the repository root.