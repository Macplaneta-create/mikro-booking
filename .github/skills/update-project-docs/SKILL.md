---
name: update-project-docs
description: "Use when updating STATUS.md, NEXT_SESSION.md, ROADMAP.md, QA docs, release docs, or cleaning stale markdown files after coding work. Trigger phrases: update project status, refresh docs, clean markdown files, sync roadmap, prepare next session, update STATUS.md."
---

# Update Project Docs

Use this skill when the repository state changed and the project documentation needs to be synchronized.

## Goal

Keep the active documentation consistent with actual code and recent verification work.

## Source of truth

Read documents in this order when relevant:

1. `STATUS.md`
2. `NEXT_SESSION.md`
3. `ROADMAP.md`
4. `README.md`
5. `QA_CHECKLIST.md`
6. `RELEASE_CHECKLIST.md`

Inspect code only as needed to confirm whether a feature is planned, implemented, or verified.

## Required rules

- Do not mark a feature as verified unless it was actually tested or explicitly confirmed.
- Distinguish implemented code from verified behavior.
- Keep `NEXT_SESSION.md` short and execution-oriented.
- Keep `ROADMAP.md` strategic and medium-term.
- Keep release steps out of roadmap and next-session notes.
- Move version-specific documents to `docs/archive/` instead of leaving them in root.
- Prefer deleting stale duplicate docs over keeping multiple competing sources of truth.

## Update procedure

1. **Jeśli sesja zawierała zmiany PHP — uruchom testy przed dokumentowaniem:**
   - `php vendor/bin/phpunit` — wszystkie testy muszą przejść (zielone)
   - `php -l {zmienione_pliki.php}` — brak błędów składni
   - Jeśli testy nie przechodzą — napraw najpierw, dokumentuj po naprawie.
   - Jeśli sesja dotyczyła tylko CSS/TSX/dokumentacji — pomiń ten krok.
2. Read `STATUS.md`, `NEXT_SESSION.md`, and `ROADMAP.md`.
2. Check recent code or changed files if the current state is unclear.
3. Update `STATUS.md` first.
4. Update `NEXT_SESSION.md` to reflect only the next 1-3 priorities.
5. Update `ROADMAP.md` only if medium-term priorities changed.
6. Update `README.md` only if document entry points or project scope changed.
7. Archive or remove stale version-specific markdown files if they are no longer active.
8. **Commit and push documentation changes to GitHub.**
   - Stage only documentation files: `git add STATUS.md NEXT_SESSION.md ROADMAP.md README.md NEXT_SESSION.md docs/`
   - Use a commit message in the format: `docs: update project status [YYYY-MM-DD]`
   - Push to the current branch: `git push`
   - If the push fails due to upstream changes, pull first with `git pull --rebase`, then push again.
   - Do not commit or push source code changes as part of this step — documentation only.

## Expected output shape

When using this skill, aim for:

- one current source of truth for status,
- one short next-session plan,
- one medium-term roadmap,
- one active QA checklist,
- one active release checklist,
- archived historical snapshots under `docs/archive/`.

## Red flags

Fix documentation if any of these are true:

- one file says a feature is done but another says it is planned,
- a release checklist is being used as a roadmap,
- `NEXT_SESSION.md` contains old completed history,
- root contains versioned release notes that should be archived,
- README points to stale or deleted documents.