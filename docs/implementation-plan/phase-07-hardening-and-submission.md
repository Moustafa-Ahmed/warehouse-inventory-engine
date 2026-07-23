# Phase 7 — Hardening and Submission

## Objective

Prove the system under the challenge’s failure scenarios, complete the required documentation and evidence, and prepare a concise technical walkthrough.

## Commit P7.1 — `test: cover the complete inventory risk matrix`

Scope:

- Map every [Acceptance Scenario](../business-rules/acceptance-scenarios.md) to automated tests.
- Add missing unit, feature, web-interface, webhook, job, command, and MySQL concurrency coverage.
- Assert movements, transitions, operations, and projections—not only HTTP responses.
- Add datasets for lifecycle and invalid-transition combinations.

Done when:

- Every acceptance scenario links to at least one test.
- Tests demonstrate which business risk they protect.

## Commit P7.2 — `test: harden concurrent and repeated processing`

Scope:

- Repeat final-unit reservation tests.
- Exercise duplicate operations, jobs, callbacks, and sweepers.
- Exercise transfer lock ordering.
- Exercise confirmation-versus-cancellation and expiration-versus-confirmation races.
- Verify query plans/index usage for critical lookup paths where practical.

Done when:

- Removing a lock, unique constraint, or idempotency guard causes a meaningful test failure.
- No race produces unexplained quantity drift.

## Optional Commit P7.O1 — `feat: reconcile ledger and balance projections`

Begin only after all required work passes.

Scope:

- Implement read-only movement replay.
- Implement `inventory:reconcile`.
- Report missing balances, mismatched buckets, and malformed movement chains.
- Exit unsuccessfully on drift.
- Never auto-correct.
- Add clean, drift, and command-exit tests.

Done when:

- Projection integrity can be audited without mutating data.

## Commit P7.3 — `docs: write project setup and operating guide`

Scope:

- Replace the default root `README.md`.
- Document prerequisites, MySQL setup, environment variables, installation, migration, seeding, workers, scheduler, frontend build, commands, and tests.
- Document demo administrator setup and provider configuration.
- Add assumptions and known limitations.

Done when:

- A reviewer can run the project from a clean clone.
- Every required command is copyable and accurate.

## Commit P7.4 — `docs: document final system architecture`

Scope:

- Create `docs/ARCHITECTURE.md`.
- Describe domain model, schema, lifecycle, movements, projections, locking, idempotency, jobs, provider boundary, security, scaling, trade-offs, and future improvements.
- Link the business rules, ERD, and implementation evidence.
- Update diagrams to match implemented behavior.

Done when:

- Architecture documentation describes the actual code, not the original plan.
- Deferred and omitted features are honest.

## Commit P7.5 — `docs: disclose AI usage and engineering ownership`

Scope:

- Create `docs/AI_USAGE.md`.
- Record how AI assisted with alternatives, business-rule workshops, documentation, implementation, and review.
- Summarize important prompt/workflow categories.
- Distinguish generated, manually modified, rejected, and personally decided work.
- Explain differentiators and trade-offs.

Done when:

- Disclosure is specific enough to support the technical review.
- The owner can explain every referenced decision and implementation.

## Commit P7.6 — `docs: add test evidence and walkthrough plan`

Scope:

- Capture and add the required passing-test screenshot.
- Add a video walkthrough outline timed to 15–20 minutes.
- Select six live scenarios provisionally and list automated evidence for the remainder.
- Add the final video URL after upload.

Done when:

- Evidence files and links work from the repository.
- The walkthrough covers architecture, failures, tests, AI usage, decisions, and future improvements.

## Commit P7.7 — `chore: finalize submission quality checks`

Scope:

- Run the full MySQL test suite.
- Run Pint.
- Run the frontend production build.
- Run dependency security audit.
- Check migrations from a fresh database.
- Verify queue and scheduler instructions.
- Review logs and browser output for current errors.
- Review the final diff for secrets, debug code, dead links, and stale documentation.

Done when:

- Every required deliverable exists.
- Tests and build pass from a clean state.
- No secret, local path, or demo-only production exposure remains.

## Recommended Live Demonstration

Provisionally demonstrate:

1. Concurrent reservation of the final unit.
2. Repeating the same reservation operation.
3. Partial reservation completed after stock receipt.
4. Provider timeout followed by late confirmation.
5. Duplicate provider callback.
6. Injected transaction failure and rollback.

Show automated evidence for worker retry, cancellation, partial shipment, transfer, out-of-order events, and permanent provider failure.

## Final Submission Gate

- [ ] Source code complete
- [ ] Migrations complete
- [ ] Factories and seeders complete
- [ ] Tests pass on MySQL
- [ ] Required Artisan command and queued jobs work
- [ ] Mock provider covers every required outcome
- [ ] Root README complete
- [ ] `docs/ARCHITECTURE.md` complete
- [ ] `docs/AI_USAGE.md` complete
- [ ] Passing-test screenshot committed
- [ ] Video link committed
- [ ] Assumptions and limitations documented
- [ ] Optional work clearly labeled
- [ ] Full diff reviewed and explainable
