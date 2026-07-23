# Warehouse Inventory Engine Documentation

This directory is the navigation hub for the challenge documentation. The business rules describe **what the system must do**. The implementation plan describes **how the system will be built and verified**.

## Start Here

1. [Business Rules Handbook](business-rules/README.md)
2. [Decision Register](business-rules/decision-register.md)
3. [Implementation Roadmap](implementation-plan/README.md)
4. [Challenge Requirements](../challenge.txt)

## Business Rules

| Document | Purpose |
| --- | --- |
| [Business Rules Handbook](business-rules/README.md) | Scope, navigation, core flow, and terminology |
| [Decision Register](business-rules/decision-register.md) | Every accepted, deferred, optional, or provisional decision |
| [Inventory and Ledger](business-rules/inventory-and-ledger.md) | Stock buckets, canonical movements, projections, adjustments, and transfers |
| [Orders and Reservations](business-rules/orders-and-reservations.md) | Partial allocation, backorders, edits, releases, and expiration |
| [Fulfillment and Shipping](business-rules/fulfillment-and-shipping.md) | Picking, packing, partial shipments, provider outcomes, and delivery |
| [Reliability and Security](business-rules/reliability-and-security.md) | Locking, idempotency, jobs, webhooks, recovery, and HMAC verification |
| [Interfaces and Scope](business-rules/interfaces-and-scope.md) | Blade UI, commands, jobs, webhook, boundaries, and optional API |
| [Acceptance Scenarios](business-rules/acceptance-scenarios.md) | Observable examples used to validate the implementation |

## Implementation Plan

| Document | Purpose |
| --- | --- |
| [Roadmap](implementation-plan/README.md) | Phase order, delivery gates, and commit rules |
| [Phase 1 — Foundation and Schema](implementation-plan/phase-01-foundation-and-schema.md) | Domain vocabulary, tables, models, constraints, factories, and seed data |
| [Phase 2 — Inventory Ledger](implementation-plan/phase-02-inventory-ledger.md) | Idempotent operations, movements, projections, receipts, adjustments, and transfers |
| [Phase 3 — Orders and Reservations](implementation-plan/phase-03-orders-and-reservations.md) | Orders, partial reservation, expiration, and backorder allocation |
| [Phase 4 — Fulfillment](implementation-plan/phase-04-fulfillment.md) | Pick, return, pack, unpack, shipment preparation, and completion |
| [Phase 5 — Shipping Reliability](implementation-plan/phase-05-shipping-reliability.md) | Provider abstraction, jobs, timeouts, webhooks, callbacks, and scheduling |
| [Phase 6 — Interfaces and Demonstration](implementation-plan/phase-06-interfaces-and-demo.md) | Authentication, Blade UI, web forms, and demonstration tooling |
| [Phase 7 — Hardening and Submission](implementation-plan/phase-07-hardening-and-submission.md) | Test matrix, optional reconciliation, required docs, evidence, and video |

## Design Exploration

- [Five ERD Approaches](erd-approaches/README.md)
- [Selected Approach: Pessimistic Locking](erd-approaches/02-pessimistic-locking.md)
- [Interactive Pessimistic Locking Presentation](erd-approaches/02-pessimistic-locking-presentation.html)

## Required Submission Documents

The following documents will be created or finalized during Phase 7:

- `README.md`
- `docs/ARCHITECTURE.md`
- `docs/AI_USAGE.md`
- Passing-test screenshot
- Video walkthrough link

The business-rules handbook and implementation plan are working design documents. If an agreed rule changes, update the decision register and every affected document in the same commit.
