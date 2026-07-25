# Warehouse Inventory Engine

## Project Context

This is a job-interview challenge implementing a warehouse inventory reservation engine. Optimize for correctness, clarity, and an implementation that can be explained during review. Do not add speculative production architecture.

## Sources of Truth

Read the relevant source documents before implementing:

1. `challenge.txt` — mandatory requirements and deliverables.
2. `docs/README.md` — documentation index and reading order.
3. `docs/business-rules/decision-register.md` — decisions made by the repository owner.
4. `docs/business-rules/*.md` — detailed business behavior and acceptance scenarios.
5. `docs/implementation-plan/*.md` — sequencing, commit boundaries, phase gates, and testing strategy.

If documents conflict, do not guess. Identify the conflict and ask the owner before changing business behavior. When a decision changes, update the decision register, affected rules, plan, and tests together.

## Architecture

- Follow the application-service pattern recorded in D34 and `docs/business-rules/interfaces-and-scope.md`.
- Keep controllers, Form Requests, commands, jobs, scheduler callbacks, and webhooks thin.
- Application services own business orchestration, transaction boundaries, locking, and idempotency.
- Use constructor injection and contracts at external boundaries.
- Do not create generic base services, unnecessary repository wrappers, or speculative abstractions.
- Introduce enums, value objects, exceptions, and state machines with their first real consumer, after their semantics are defined.
- A general JSON API is not required. The operational UI is server-rendered Blade; the provider webhook is the required machine-to-machine boundary.

## Working Rules

- Inspect `git status` and the current roadmap task before editing. Preserve unrelated and uncommitted work.
- Read only the documents relevant to the task, but never implement a workflow without reading its business rules.
- Follow the numbered roadmap tasks as independently reviewable, commit-sized changes.
- Do not call code planned for a later task.
- Use Laravel Boost tools for application information, documentation, database inspection, routes, URLs, and logs.
- Use `php artisan make:* --no-interaction` for Laravel-generated files.
- Do not add dependencies or new top-level architecture without approval.
- Push back when a requested or planned type is ambiguous, duplicates another source of truth, or has no real consumer yet.
- Do not invent business rules, statuses, progress meanings, or provider behavior.
- Do not create a commit unless the user asks.

## Verification

Follow `docs/implementation-plan/testing-strategy.md`.

- Keep the Pest suite small and risk-based.
- Use MySQL for database-backed behavior.
- Run the narrowest relevant test while working and the full suite at phase gates.
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP.
- Documentation-only changes require content, link, and generated-output verification rather than a new automated test.
