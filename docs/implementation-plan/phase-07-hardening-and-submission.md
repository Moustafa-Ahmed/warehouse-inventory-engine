# Phase 7 — Hardening and Submission

## Objective

After the Phase 6 demo works, audit the important risks, create the required submission documents from the implemented system, run clean-state verification, record the required walkthrough, and deliver an accessible GitHub or GitLab repository.

## Commit P7.1 — `test: complete the risk-based smoke and critical suite`

**Priority:** Submission-critical

Scope:

- After the Phase 6 demo works, classify each [Acceptance Scenario](../business-rules/acceptance-scenarios.md) as focused automated, smoke, walkthrough, or manual evidence.
- Ensure every critical risk in the [Testing Strategy](testing-strategy.md) has a focused Pest test.
- Ensure primary pages, required commands, migration/seeding, and one representative form flow are in the smoke suite.
- Assert movements, transitions, operations, and projections in critical tests—not only return values or HTTP responses.
- Use small datasets only where important state or provider outcomes share setup.
- Do not add tests merely to cover every DTO, enum, class, branch, validation rule, or acceptance scenario.

Done when:

- Every critical risk links to at least one focused automated test.
- Secondary scenarios identify their smoke, walkthrough, or manual evidence.
- The suite remains small enough to understand and explain during review.

## Commit P7.2 — `test: repeat the highest-risk concurrent and duplicate processing`

**Priority:** Submission-critical

Scope:

- Repeat the final-unit reservation test.
- Repeat duplicate reservation operation, shipment job, and callback processing.
- Exercise opposite-direction transfer lock ordering.
- Verify query plans/index usage for critical lookup paths where practical.
- Review queue timeout, `retry_after`, tries, and backoff configuration together.

Done when:

- Removing an essential lock, unique constraint, or idempotency guard causes a focused critical test failure.
- No tested retry or race produces unexplained quantity drift.
- Queue settings cannot cause a timed-out job to execute concurrently by configuration mistake.

## Commit P7.3 — `docs: finalize project setup and operating guide`

**Priority:** Submission-critical

Scope:

- Replace the Laravel placeholder with the final root `README.md`.
- Verify prerequisites, MySQL setup, environment variables, installation, migration, seeding, workers, scheduler, frontend build, commands, and test instructions.
- Document demo administrator setup, callback URL, HMAC secret, random outcome weights, non-synchronous queue connection, queue worker, and mock-provider controls.
- Document assumptions, priority decisions, deferred supporting work, and known limitations.
- Remove every placeholder and stale command.

Done when:

- A reviewer can run the project from a clean clone.
- Every documented command is copyable and accurate.

## Commit P7.4 — `docs: finalize system architecture`

**Priority:** Submission-critical

Scope:

- Create `docs/ARCHITECTURE.md` against the implemented code.
- Describe domain model, schema, lifecycle, movements, projections, locking, idempotency, progress calculation, application services, readonly DTO boundaries, jobs, persistent mock-provider boundary, outbound HTTP delivery, inbound event processing, reconciliation, security, scaling, and trade-offs.
- Explain the design patterns and SOLID principles actually used.
- Link the business rules, ERD, and implementation evidence.
- Update diagrams to match implemented behavior.
- Clearly label deferred and omitted features.

Done when:

- Architecture documentation describes actual code rather than the original plan.
- A reviewer can trace each important guarantee to its schema/service/test evidence.

## Commit P7.5 — `docs: finalize AI usage and engineering ownership`

**Priority:** Submission-critical

Scope:

- Create `docs/AI_USAGE.md`.
- Record how AI assisted with alternatives, business-rule workshops, documentation, implementation, and review.
- Summarize important prompt/workflow categories.
- Distinguish generated, manually modified, rejected, and personally decided work.
- Name important AI suggestions that were rejected and why.
- Explain differentiators, trade-offs, and areas the repository owner can modify live.

Done when:

- Disclosure is specific enough to support the technical review.
- The owner can explain every referenced decision and implementation.

## Commit P7.6 — `chore: verify clean submission quality`

**Priority:** Submission-critical

Scope:

- Run the complete intentionally small MySQL smoke and critical suites.
- Run Pint.
- Run the frontend production build.
- Run dependency security audit.
- Check migrations and reference/demo seeding from a fresh database.
- Verify queue and scheduler instructions and required commands.
- Review logs and browser output for current errors.
- Review the final diff for secrets, debug code, dead links, stale docs, and accidental demo-only production exposure.

Done when:

- Every required deliverable exists.
- Tests, migration/seed, and build pass from a clean state.
- No secret, local-only path, or unexplained debug behavior remains.

## Commit P7.7 — `docs: add final test evidence and walkthrough`

**Priority:** Submission-critical

Scope:

- Capture and add the required passing-test screenshot after P7.6 succeeds.
- Create and finalize the video outline against the working demo before recording.
- Record a target 18–19 minute walkthrough using this timed outline:
  - **0:00–2:00 — Introduction:** identity, relevant ERP/inventory/warehouse/logistics/POS/CRUD experience, and why this architecture was chosen.
  - **2:00–7:00 — Architecture:** domain model, schema, reservation lifecycle, ledger/projections, concurrency, security, decisions, patterns, and SOLID.
  - **7:00–13:00 — Failure scenarios:** demonstrate at least five; provisionally show final-unit concurrency, repeated reservation, partial recovery, timeout-after-provider-acceptance followed by an actual HTTP confirmation, duplicate callback, and rollback.
  - **13:00–15:00 — Testing:** what was tested, why the focused tests matter, risks protected, and how the few unit tests validate pure business rules.
  - **15:00–17:00 — AI and ownership:** where AI helped, rejected suggestions, independently selected decisions, and differences from a typical generated solution.
  - **17:00–18:30 — Production future:** improvements, scaling, remaining risks, and support for millions of inventory transactions.
- Explain why inventory remains correct after each demonstrated failure.
- Identify focused test, smoke, or manual evidence for other relevant behavior.
- Upload the video and add the final link.

Done when:

- The video lasts 15–20 minutes and covers every required section.
- Evidence files and links work from the repository.
- The walkthrough matches the final implementation and limitations.

## Commit P7.8 — `chore: finalize repository handoff`

**Priority:** Submission-critical

Scope:

- Create the GitHub or GitLab repository with the intended visibility and reviewer access.
- Add the canonical repository and video URLs to the submission documentation.
- Verify the intended submission branch contains the complete explainable history.
- Push the final branch.
- Verify links and access while signed out or through the intended reviewer account.
- Perform one final clean-clone setup/read-through check.

Done when:

- A reviewer can access the repository and video without requesting missing permissions.
- The clean clone follows the README successfully.
- The submitted branch and URLs are unambiguous.

## Recommended Live Demonstration

Provisionally demonstrate:

1. Concurrent reservation of the final unit.
2. Repeating the same reservation operation.
3. Partial reservation completed after stock receipt.
4. Provider timeout after acceptance, stable-key reconciliation, and late signed HTTP confirmation.
5. Exact duplicate provider callback using the same external event ID and body.
6. Injected transaction failure and rollback.

Briefly show or explain smoke/focused evidence for worker retry, cancellation, partial shipment, transfer, out-of-order events, and permanent provider failure.

## Optional Commit P7.O1 — `feat: reconcile ledger and balance projections`

**Priority:** Optional/stretch

Begin only after P7.8 and only if remaining time is explicitly approved.

Scope:

- Implement read-only movement replay.
- Implement `inventory:reconcile`.
- Report missing balances, mismatched buckets, and malformed movement chains.
- Exit unsuccessfully on drift.
- Never auto-correct.
- Add clean, drift, and command-exit tests.

Done when:

- Projection integrity can be audited without mutating data.

## Final Submission Gate

- [ ] Source code complete
- [ ] Migrations complete
- [ ] Factories and reference/demo seeders complete
- [ ] Small risk-based suite passes on MySQL
- [ ] Required Artisan command and queued jobs work
- [ ] Mock provider covers every required outcome
- [ ] Mock-provider callback URL, worker, scheduler, and deterministic controls work from documented setup
- [ ] Root README finalized
- [ ] `docs/ARCHITECTURE.md` finalized
- [ ] `docs/AI_USAGE.md` finalized
- [ ] Passing-test screenshot committed
- [ ] 15–20 minute video uploaded and linked
- [ ] Assumptions, priorities, and limitations documented
- [ ] Optional work clearly labeled
- [ ] GitHub/GitLab repository pushed and reviewer access verified
- [ ] Clean-clone setup verified
- [ ] Full diff reviewed and explainable
