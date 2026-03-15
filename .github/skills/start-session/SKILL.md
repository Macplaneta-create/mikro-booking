---
name: start-session
description: "Use at the beginning of a coding session to quickly recover context: what is done, what is verified, what is next, and what to work on now. Trigger phrases: start session, resume work, what were we doing, recover context, continue previous work."
---

# Start Session

Use this skill at the beginning of a session when context may be missing or stale.

## Goal

Rebuild working context in 3-5 minutes and produce a clear mini-plan for the current session.

## Session startup order

Read these files in order:

1. `STATUS.md`
2. `NEXT_SESSION.md`
3. `ROADMAP.md`
4. `README.md` (only if needed for entry points)

Then check repository state:

5. `git status`
6. changed files (if any) to detect unfinished local work

## Interpretation rules

- Treat `STATUS.md` as source of truth for current state.
- Treat `NEXT_SESSION.md` as short execution queue for this session.
- Treat `ROADMAP.md` as medium-term context only.
- Do not assume code is verified unless explicitly marked as verified.

## Required output

Produce exactly these sections:

1. `Recovered Context`:
   - what is implemented,
   - what is implemented but unverified,
   - known risks/blockers.
2. `This Session Priority`:
   - top 1-3 tasks for now, based on `NEXT_SESSION.md` and `STATUS.md`.
3. `First Action Now`:
   - one concrete first step to execute immediately.

## Optional cleanup at session start

If you detect contradictions between `STATUS.md` and `NEXT_SESSION.md`, do a minimal factual update before coding.

## Red flags

Stop and resolve context first if any of these are true:

- `STATUS.md` says feature is planned but `NEXT_SESSION.md` treats it as done,
- `NEXT_SESSION.md` contains long completed history instead of near-term tasks,
- local uncommitted changes exist and are not reflected in session plan.