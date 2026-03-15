# Documentation Workflow

Ten dokument opisuje prosty proces utrzymania dokumentacji tak, aby kolejna sesja zawsze miała jasny obraz stanu projektu.

## Role dokumentów

- `STATUS.md`: główne źródło prawdy o stanie między sesjami.
- `NEXT_SESSION.md`: najbliższe 1-3 działania.
- `ROADMAP.md`: plan średnioterminowy i priorytety produktu.
- `QA_CHECKLIST.md`: manualna regresja.
- `RELEASE_CHECKLIST.md`: gotowość release i wdrożenia.
- `docs/archive/`: snapshoty historyczne i wersyjne.

## Zasady statusów

Nie traktuj samego kodu jako potwierdzenia działania.

- `Zweryfikowane`: potwierdzone testami lub ręcznie.
- `Zaimplementowane, do potwierdzenia`: kod jest, ale brak pełnego potwierdzenia.
- `W trakcie`: aktywnie rozwijane, niegotowe.
- `Planowane`: backlog.
- `Do dopracowania / naprawy`: znane ryzyka, regresje, braki.

## Kiedy aktualizować

Po każdej większej sesji kodowania:

1. Zaktualizuj `STATUS.md`.
2. Zaktualizuj `NEXT_SESSION.md` (maksymalnie 1-3 pozycje).
3. Zaktualizuj `ROADMAP.md` tylko gdy zmieniły się priorytety średnioterminowe.
4. Jeśli powstał dokument wersyjny, przenieś go do `docs/archive/`.

## Szybka checklista końca sesji (2 min)

- [ ] Czy `STATUS.md` rozróżnia rzeczy zweryfikowane od tylko zaimplementowanych?
- [ ] Czy `NEXT_SESSION.md` zawiera tylko najbliższe kroki?
- [ ] Czy release/QA nie są pomieszane z roadmapą?
- [ ] Czy snapshoty wersyjne nie zalegają w root?

## Automatyzacja w repo

- Skill startowy: `start-session` (plik `.github/skills/start-session/SKILL.md`).
- Prompt slash: `/Update Project Docs` (plik `.github/prompts/update-docs.prompt.md`).
- Skill workflow: `update-project-docs` (plik `.github/skills/update-project-docs/SKILL.md`).
- Hook przypominający przy zakończeniu sesji: `.github/hooks/doc-reminder.json`.