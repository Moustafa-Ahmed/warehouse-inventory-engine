# Implementation Roadmap

This roadmap converts the agreed [Business Rules](../business-rules/README.md) into reviewable, dependency-ordered implementation tasks.

Testing follows the [Risk-Based Testing Strategy](testing-strategy.md): smoke tests cover wiring and ordinary interfaces, while focused automated tests are reserved for important inventory, concurrency, idempotency, shipment, and provider behavior.

## Delivery Strategy

Correctness is built from the database outward while external boundaries are de-risked early. Required submission documents are produced from the working Phase 6 demo during Phase 7:

```text
Project/test foundation
    -> service/DTO conventions + provider contract/deterministic fake
    -> schema, persistent mock-provider state, and reference data
    -> canonical movements and balance projections
    -> orders, progress, reservations, release, and backorders
    -> physical fulfillment and shipment preparation
    -> provider integration, jobs, callbacks, and recovery
    -> operational query services and minimal Blade UI
    -> risk audit, evidence, video, and repository delivery
```

No task may call a class, job, application service, query service, or document that is planned only in a later task.

## Priority Levels

Each task has one of these priorities:

- **Submission-critical:** required to prove the challenge’s inventory, concurrency, shipment, provider, documentation, or delivery requirements.
- **Supporting:** part of the agreed solution and useful for the walkthrough, but may be simplified after an explicit time review once every submission-critical task passes.
- **Optional/stretch:** begins only after all required deliverables, evidence, and walkthrough preparation pass.

Supporting work is not silently dropped. Any reduction must be recorded in the decision register and final known limitations.

Supporting tasks encountered before Phase 5 may be parked while the submission-critical path continues. Record them in the working checklist, then explicitly implement, simplify, or defer them at the post-Phase-5 time review.

## Phase Index

| Phase | Outcome | Priority |
| --- | --- | --- |
| [1. Foundation and Schema](phase-01-foundation-and-schema.md) | MySQL test harness, service/DTO conventions, early provider boundary, warehouse/mock-provider schema, factories, and reference seed data | Submission-critical |
| [2. Inventory Ledger](phase-02-inventory-ledger.md) | Idempotent receipts, adjustments, transfers, movements, projections, and concurrency proof | Submission-critical |
| [3. Orders and Reservations](phase-03-orders-and-reservations.md) | Shared progress calculation, partial allocation, release, edits, expiration, and backorder recovery | Submission-critical |
| [4. Fulfillment](phase-04-fulfillment.md) | Pick, return, pack, unpack, shipment preparation, and conservation | Submission-critical |
| [5. Shipping Reliability](phase-05-shipping-reliability.md) | Persistent mock-provider behavior, actual HTTP callbacks, thin jobs/commands, reconciliation, and duplicate safety | Submission-critical |
| [6. Interfaces and Demo](phase-06-interfaces-and-demo.md) | Minimal authenticated Blade workflows, operational reports, scenario data, and demo controls | Mixed |
| [7. Hardening and Submission](phase-07-hardening-and-submission.md) | Risk-based proof, finalized docs, evidence, video, and repository handoff | Submission-critical |

## Commit Rules

Each numbered task is intended to become one independently reviewable commit.

1. Use the proposed commit subject or a similarly specific imperative subject.
2. Keep schema, model relationships, and factory support for one concern together.
3. Keep important service behavior and its focused risk test in the same commit.
4. Do not mix presentation polish with domain behavior.
5. Do not introduce a dependency without explicit approval.
6. Generate Laravel files with `php artisan make:* --no-interaction`.
7. Run the narrowest relevant tests while working.
8. Run `vendor/bin/pint --dirty --format agent` after modifying PHP.
9. Run `php artisan test --compact` at every phase gate.
10. Update the business rules and decision register with any approved rule change.
11. Preserve implementation decisions and ownership notes in the decision register; after the Phase 6 demo, Phase 7 creates and finalizes `README.md`, `docs/ARCHITECTURE.md`, `docs/AI_USAGE.md`, and the video outline from the implemented system.
12. Avoid WIP commits on the submission branch.
13. Introduce each DTO, enum, exception, calculator, value object, or state machine with its first real consumer and only after its semantics are defined.
14. Use native `final readonly` DTOs under `app/DTOs/{Area}` when typed service inputs or results would otherwise be ambiguous arrays; do not add standalone tests for simple DTOs.

## Definition of a Completed Commit

A task is complete only when:

- The stated behavior works.
- Important correctness or reliability risks have a focused automated test.
- Ordinary CRUD, wiring, and interface changes are added to the smoke suite or verified manually.
- No unrelated files are changed.
- Migrations roll forward and backward where safe.
- PHP formatting passes.
- Business-rule and decision-register changes are included when assumptions or decisions change; final submission documents are produced after the demo in Phase 7.
- The commit can be explained and demonstrated independently.

The plan does not require a unit test for every class or branch, and it does not target a coverage percentage.

## Critical Path and Safe Time Review

The first safe time review happens after Phase 5. At that point the backend must already prove:

- Concurrent final-unit reservation safety.
- Partial allocation and recovery.
- Ledger/projection atomicity.
- Reservation release.
- Pick/pack quantity conservation.
- Shipment command and queued-job processing.
- Provider success, permanent failure, timeout, delayed confirmation, and duplicate callback behavior.
- Actual signed HTTP callback delivery from the mock provider, including transport retry.
- Stable-key status lookup and reconciliation of provider submissions with unknown outcomes without bypassing the webhook.
- Duplicate and out-of-order callback safety.

Phase 6 then builds the minimum UI and reports needed to operate and explain those capabilities. Presentation-only dashboard polish and broad catalog convenience are supporting work.

## Optional and Stretch Work

Optional work is deliberately isolated:

- Automatic cross-warehouse allocation.
- Ledger-to-projection reconciliation command.
- Additional presentation UI.
- General versioned JSON API.

Do not begin it unless:

1. All submission-critical tests pass.
2. Required business-rule and planning documentation is current.
3. Evidence and the video outline exist.
4. The remaining delivery time has been reviewed.

## Working Checklist

- [x] Phase 1 gate passed
- [x] Phase 2 gate passed
- [x] Phase 3 gate passed
- [ ] Phase 4 gate passed
- [ ] Phase 5 gate passed
- [ ] Safe time review completed
- [ ] Phase 6 gate passed
- [ ] Phase 7 gate passed
- [ ] Required screenshot captured
- [ ] Video recorded and linked
- [ ] AI usage accurately documented
- [ ] GitHub/GitLab repository URL verified
