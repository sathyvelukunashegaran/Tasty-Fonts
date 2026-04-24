# Agent Workspace

This directory holds lightweight collaboration files for AI agents working in this repository. It is repository-only context and is excluded from release archives.

## File Map

- [`../AGENTS.md`](../AGENTS.md) — primary repo instructions for agents.
- [`../ARCHITECTURE.md`](../ARCHITECTURE.md) — repo-local architecture orientation.
- [`../DESIGN.md`](../DESIGN.md) — admin UI design-system rules.
- [`../wiki/Architecture.md`](../wiki/Architecture.md) — full published architecture reference.
- [`lessons.md`](lessons.md) — concise pitfalls and lessons worth checking before larger changes.
- [`tasks/README.md`](tasks/README.md) — optional format for persistent task notes.

## How To Use This Directory

- Read `AGENTS.md` first.
- Check `lessons.md` before larger changes, repeated problem areas, or work touching known hotspots.
- Create `tasks/todo-(name).md` only when the work is long-running or multi-step enough that persistent notes will help.
- Keep deep architecture in `ARCHITECTURE.md` or `wiki/Architecture.md`, not in task notes.
- Keep visual rules in `DESIGN.md`, not in ad hoc CSS comments or task notes.

## Maintenance

- Add new lessons only when they are likely to prevent repeated mistakes.
- Remove stale task notes after the work is merged or no longer relevant.
- Keep this index in sync when new shared agent files are added.
