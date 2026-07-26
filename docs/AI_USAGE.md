# AI Usage and Engineering Ownership

## Purpose

AI tools were used extensively during this challenge. This document explains the workflow honestly: AI accelerated alternatives, documentation, implementation, tests, and review, while the repository owner selected the business behavior, challenged abstractions and naming, controlled scope, and accepted each commit-sized result.

The final repository should not be interpreted as a one-prompt generated application. It was built through an iterative design and implementation conversation with repeated review, correction, testing, and simplification.

## How AI Was Used

### Requirements and business-rule workshop

AI first read `challenge.txt` and compared five ERD approaches. It recommended the pessimistic-locking approach as the best balance between correctness, explainability, and interview scope.

The requirements were then converted into explicit decision questions covering:

- Ledger and balance ownership.
- Full versus partial reservation.
- Temporary versus confirmed reservations.
- Order edits and cancellation boundaries.
- Picking, packing, shipping, and delivery semantics.
- Idempotency and transaction rollback.
- Provider timeouts, failures, retries, duplicate callbacks, and reconciliation.
- UI, commands, jobs, webhook, security, tests, and optional work.

The owner answered those questions and corrected unclear assumptions before implementation. The accepted results are recorded in the [Decision Register](business-rules/decision-register.md).

### Planning and sequencing

AI organized the agreed rules into phase gates and commit-sized roadmap tasks. The owner repeatedly reviewed sequencing and changed it when necessary, particularly by:

- Moving the mock-provider boundary and factories earlier.
- Requiring the persistent mock-provider behavior to be complete enough for manual shipment and webhook controls.
- Deferring final README, architecture, AI disclosure, and video work until the demo existed.
- Replacing broad unit-test coverage with a small risk-based smoke and critical suite.
- Requiring tests after every task and one commit per roadmap task.

### Implementation

For the work performed in this challenge session, AI wrote most code and documentation changes through repository tools. This included migrations, models, factories, enums, readonly DTOs, application services, jobs, commands, Form Requests, controllers, Blade views, tests, seeders, and final documentation.

Each implementation task followed the same general loop:

1. Read the current roadmap task and relevant business rules.
2. Inspect existing conventions and version-specific Laravel documentation.
3. Implement one bounded change.
4. Format changed PHP with Pint.
5. Run the narrowest relevant MySQL Pest test, and run broader suites at phase gates.
6. Review the diff and commit the task independently after owner approval.

AI also evaluated externally supplied review findings against current code. Findings were not applied automatically: stale or incorrect claims were skipped, while valid issues were fixed minimally and tested.

### Testing and hardening

AI drafted the Pest tests, but their scope was owner-directed. The suite intentionally emphasizes:

- Concurrent reservation of the final unit.
- Deterministic lock ordering.
- Duplicate operation and changed-payload behavior.
- Transaction rollback.
- Quantity conservation across fulfillment.
- Provider acceptance versus confirmed carrier handoff.
- Timeout-after-acceptance and stable-key reconciliation.
- HMAC verification.
- Duplicate, conflicting, delayed, and out-of-order callbacks.
- Queue timeout/retry configuration and critical query plans.

Simple DTOs, enum declarations, relationship accessors, and every individual validation branch do not have standalone tests. The [Testing Evidence](testing-evidence.md) document shows the risk-to-test mapping.

## Main Prompt and Workflow Categories

The exact transcript is not required to understand the workflow. The recurring prompt categories were:

- “Read the challenge and compare these architecture approaches.”
- “Ask me for each business decision; I want to decide the behavior personally.”
- “Turn the decisions into readable business rules and commit-sized implementation phases.”
- “Review the plan for missing work, bad sequencing, and overengineering.”
- “Implement the next roadmap task, test it, and commit it separately.”
- “Verify these review findings against current code; fix only valid issues.”
- “Use clearer Laravel-community naming and remove unnecessary abstractions.”
- “Keep the test suite small: smoke coverage plus focused tests for important risks.”
- “Check the implementation against the challenge and document only what is actually built.”

These workflows used AI as a design partner, implementer, reviewer, and documentation assistant rather than as the source of product authority.

## What Was Generated, Modified, and Personally Designed

### AI-first drafts

The initial text or code for most challenge-specific repository changes was AI drafted, including:

- The ERD comparison and pessimistic-locking presentation.
- The organized business-rule handbook and implementation roadmap.
- Most Laravel implementation files.
- The focused Pest suite and evidence matrix.
- The final README and architecture documentation.

“AI-first draft” does not mean “accepted unchanged.” Many drafts went through owner feedback, focused review findings, refactoring, and test-backed correction.

### Owner-authored or owner-controlled input

The owner:

- Updated the supplied challenge document with the complete requirements and deliverables.
- Selected the business decisions in the decision workshop.
- Set the scope, implementation order, Laravel service-pattern requirement, and testing philosophy.
- Inspected generated code and challenged unclear names, duplicate state, and speculative abstractions.
- Approved or rejected proposed changes before the next task.
- Committed early planning work and then requested explicit task-level commits.

The owner’s most important direct design choices include:

- Pessimistic row locking with an append-only movement ledger and synchronous projections.
- Partial reservation with requested, allocated, and still-outstanding quantities shown explicitly.
- Warehouse-scoped core allocation, with automatic cross-warehouse splitting deferred.
- Separate allocation, fulfillment, shipment, and delivery progress.
- Deducting packed stock only on a valid persisted carrier-handoff callback.
- Treating provider timeout as an `unknown` outcome and reconciling with the same request key.
- HMAC-authenticated callbacks with persistent receipt identity and exact-body duplicate handling.
- A server-rendered Blade UI rather than a general JSON API.
- One administrator role for challenge scope.
- Laravel application services and native readonly DTOs.
- A small risk-based Pest suite instead of unit tests for every class.

### Modifications made through owner review

The owner did not merely approve features. They pushed the implementation toward simpler and more conventional Laravel naming and behavior. Examples include:

- Removing an unnecessary provider-scenario selector interface and class.
- Flattening the provider contract namespace and removing repeated `ShippingShipping...` naming.
- Merging identical provider result DTO concepts into one `Result`.
- Preserving the full SHA-256 digest in simulated external shipment IDs.
- Using `unknown`, not `uncertain`, for a provider outcome that cannot yet be determined.
- Rejecting unexplained shipment/delivery status duplication and generic “fulfillment progress” terminology.
- Rejecting a `tests/Unit/Domain` structure and a `ValueObjects` folder that did not match the Laravel-oriented design; business-area DTO folders were used instead.
- Removing lifecycle counters from mass assignment so only services may change them.
- Requiring mock-provider shipment controls, real signed HTTP callbacks, replay, retries, reconciliation, and out-of-order behavior—not just an in-memory fake.
- Keeping reservation transition history after reviewing a suggestion to delete it, because movements alone do not retain reservation before/after state, kind, status, source, and reason.
- Deferring the optional ledger reconciliation command and general API until submission-critical work is complete.

## Rejected or Simplified AI Suggestions

Several ideas were deliberately rejected or reduced:

| Suggestion | Decision and reason |
| --- | --- |
| Full event sourcing | Rejected. It increases implementation and explanation cost without improving the core challenge guarantee. |
| Optimistic locking as the primary approach | Rejected. Correct retry behavior is possible, but pessimistic locking is easier to demonstrate for final-unit allocation. |
| Two-phase reservation as a separate architecture | Rejected. Temporary and confirmed reservation kinds cover the required behavior without another reservation system. |
| Generic repositories and base services | Rejected. Eloquent plus focused application services is clearer for this Laravel application. |
| Interface for deterministic mock scenario selection | Removed. Passing or persisting a scenario is sufficient. |
| General JSON API for the operational UI | Deferred. Blade forms call the same application services directly; the only required machine boundary is the provider webhook. |
| Transactional outbox | Deferred. Durable pending records plus scheduled recovery satisfy challenge scope. |
| Unit tests for every DTO, enum, model, and branch | Rejected. Tests are concentrated on concurrency, idempotency, rollback, quantity conservation, and provider failure modes. |
| Persisted status for every kind of progress | Rejected. Quantity projections and a pure calculator avoid redundant state with ambiguous meanings. |
| Automatic cross-warehouse split allocation | Deferred. The schema allows multiple reservations, but the core workflow remains explicitly warehouse scoped. |

## What Differentiates This Solution

The differentiator is not the number of layers. It is the separation of failure domains while preserving one clear inventory correctness boundary:

- The movement ledger and balance projection commit together.
- Every inventory decision rereads locked MySQL rows.
- A browser retry, duplicate job, and duplicate callback each have a durable idempotency mechanism appropriate to that boundary.
- Provider acceptance is explicitly separated from physical carrier handoff.
- A timeout-after-acceptance creates a real external identity and future callback while local inventory remains packed.
- Reconciliation cannot “repair” inventory by changing shipment state directly; it requests redelivery of the existing signed callback.
- The mock provider persists its own external-side shipments and callback attempts, then uses actual HTTP in the local demonstration.
- Out-of-order callbacks remain durable until their prerequisite exists.
- The test suite is small enough to explain but attacks the highest-risk concurrency and duplicate-processing behavior repeatedly.

This is intentionally more complete than a CRUD-only inventory demo while remaining smaller than a speculative production ERP architecture.

## Intentional Trade-offs and Future Improvements

The central trade-off is pessimistic locking: it gives a simple correctness proof but serializes writers for hot product/warehouse balances. Short transactions and deterministic lock ordering control that cost. If measurements showed extreme hot-SKU contention, per-key command serialization or a different partitioning strategy could be introduced later.

Other intentional limits are:

- MySQL is the single inventory correctness authority.
- Reports read operational tables directly; read replicas or reporting projections are future scaling work.
- Durable sweepers are used instead of a general outbox.
- The provider contract has only one mock adapter.
- There is no automatic ledger-to-balance reconciliation yet.
- Returns, costing, lots, serials, bins, multi-tenancy, and multiple roles are outside scope.

The architecture discussion and production evolution path are documented in [System Architecture](ARCHITECTURE.md).

## Engineering Ownership and Review Readiness

The owner is responsible for the submitted design and should be able to explain or modify:

- Why balance buckets are mutually exclusive and why delivered is cumulative within shipped.
- Why shipment composition and provider acceptance do not deduct inventory.
- The row-lock and transaction order for reservation, transfer, and shipment confirmation.
- How operation request hashes distinguish replay from conflict.
- Why provider submission keys and provider webhook event IDs solve different idempotency problems.
- How an `unknown` submission is reconciled without bypassing the callback.
- Why a duplicate raw body is accepted but an identity collision with changed bytes is rejected.
- Why reservation transitions are audit history rather than event sourcing.
- How the persistent mock provider schedules, signs, retries, replays, and delivers callbacks.
- Which features and tests were deliberately omitted.

Useful live-change areas are the pure order progress calculator, mock-provider scenario mapping, a focused service validation rule, one Form Request rule, or a small report filter. These areas demonstrate understanding without requiring unsafe changes to the concurrency model during a review.

The repository history preserves the implementation as independently reviewable tasks. AI accelerated construction, but the owner’s repeated decisions, objections, simplifications, and acceptance of each task define the engineering ownership.
