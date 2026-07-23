# Implementation Roadmap

This roadmap converts the agreed [Business Rules](../business-rules/README.md) into reviewable, commit-sized implementation tasks.

## Delivery Strategy

Correctness is built from the database outward:

```text
Schema and invariants
    -> canonical movements and balance projections
    -> orders and reservations
    -> physical fulfillment
    -> provider reliability
    -> authenticated operational UI
    -> hardening and submission
```

Frontend polish and optional features must not start before the core correctness gates pass.

## Phase Index

| Phase | Outcome | Required |
| --- | --- | --- |
| [1. Foundation and Schema](phase-01-foundation-and-schema.md) | Domain vocabulary, complete schema, models, factories, and demo data | Yes |
| [2. Inventory Ledger](phase-02-inventory-ledger.md) | Idempotent receipts, adjustments, transfers, movements, and projections | Yes |
| [3. Orders and Reservations](phase-03-orders-and-reservations.md) | Partial allocation, release, expiration, and automatic backorder processing | Yes |
| [4. Fulfillment](phase-04-fulfillment.md) | Pick, return, pack, unpack, shipment preparation, and progress | Yes |
| [5. Shipping Reliability](phase-05-shipping-reliability.md) | Provider abstraction, jobs, timeouts, signed callbacks, and duplicate safety | Yes |
| [6. Interfaces and Demo](phase-06-interfaces-and-demo.md) | Admin authentication, server-rendered operational UI, and demo controls | Yes |
| [7. Hardening and Submission](phase-07-hardening-and-submission.md) | Scenario proof, final test matrix, docs, evidence, and video | Yes |

## Commit Rules

Each numbered task is intended to become one commit.

1. Use the proposed commit subject or a similarly specific imperative subject.
2. Keep schema, model relationships, factory support, and focused tests for one concern together.
3. Keep action code and its business-rule tests in the same commit.
4. Do not mix UI polish with domain behavior.
5. Do not introduce a dependency without explicit approval.
6. Generate Laravel files with `php artisan make:* --no-interaction`.
7. Run the narrowest relevant tests while working.
8. Run `vendor/bin/pint --dirty --format agent` after modifying PHP.
9. Run `php artisan test --compact` at every phase gate.
10. Update business rules and the decision register in the same commit as any approved rule change.
11. Avoid WIP commits on the submission branch.

## Definition of a Completed Commit

A task is complete only when:

- The stated behavior works.
- Failure and boundary cases have tests.
- No unrelated files are changed.
- Migrations roll forward and backward where safe.
- PHP formatting passes.
- The commit can be explained and demonstrated independently.

## Critical Path

The minimum acceptable submission follows every required task in Phases 1–7, excluding tasks explicitly marked optional or stretch.

Core backend completion occurs after Phase 5. The system should already prove:

- Concurrent reservation safety.
- Partial allocation and recovery.
- Ledger/projection atomicity.
- Pick and pack conservation.
- Shipment retry and timeout safety.
- Duplicate and out-of-order callback safety.

Only then should significant UI work begin.

## Optional and Stretch Work

Optional work is deliberately isolated:

- Automatic cross-warehouse allocation.
- Ledger-to-projection reconciliation command.
- Additional presentation UI.
- General versioned JSON API.

Do not begin it unless:

1. All required tests pass.
2. Required documentation is current.
3. The remaining delivery time has been reviewed.

## Working Checklist

- [ ] Phase 1 gate passed
- [ ] Phase 2 gate passed
- [ ] Phase 3 gate passed
- [ ] Phase 4 gate passed
- [ ] Phase 5 gate passed
- [ ] Phase 6 gate passed
- [ ] Phase 7 gate passed
- [ ] Required screenshot captured
- [ ] Video recorded and linked
- [ ] AI usage accurately documented
