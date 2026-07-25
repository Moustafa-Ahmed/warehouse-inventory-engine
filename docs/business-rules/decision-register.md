# Decision Register

This register records the decisions made during the design workshop. “Accepted” means the rule is part of the implementation scope. “Deferred,” “Optional,” and “Provisional” items must not be represented as complete decisions.

## Inventory and Allocation

| ID | Status | Decision |
| --- | --- | --- |
| D1 | Accepted | Inventory movements are the canonical immutable history. Current balances are synchronous projections updated atomically with every movement and locked pessimistically for inventory decisions. |
| D2 | Accepted | Reservations immediately create hard holds. The same available quantity cannot be promised to another order. |
| D3 | Accepted | Reservation requests allocate as much as is currently available. The operation result explicitly reports requested, allocated, and outstanding quantities, and the UI displays all three. Outstanding quantities are retried after stock receipt and by a scheduled safety-net command, using FIFO order-item priority. |
| D4 | Deferred stretch goal | The schema supports multiple warehouse reservations for one order item. Initial allocation uses an explicitly selected warehouse. Automatic cross-warehouse splitting may be added only if time permits. |
| D5 | Accepted | Temporary reservations may expire. Confirmed sales-order reservations do not expire automatically. Expiration releases only eligible reserved stock through normal ledger and history operations. |
| D6 | Accepted | Warehouse transfers may move only available stock. Reserved, picked, and packed stock remains at its warehouse. Reservation reallocation is a separate future operation. |

## Order and Fulfillment

| ID | Status | Decision |
| --- | --- | --- |
| D7 | Accepted | Order edits apply only the quantity difference. Increases create additional demand. Decreases release eligible reserved quantities and cannot reduce below terminal or physically progressed quantities. |
| D8 | Accepted | Only reserved quantities can be cancelled immediately. Picked stock requires an explicit return-to-stock action; packed stock requires unpacking and then return-to-stock. Shipped stock cannot be cancelled. |
| D9 | Accepted | Only packed stock may enter a shipment. Partial shipments and multiple shipments per order are allowed. Every shipment belongs to one warehouse. |
| D10 | Accepted | Warehouse stock is deducted when the provider confirms carrier handoff/shipment, not when the shipment is created, submitted, or delivered. |
| D11 | Accepted | A pre-shipment failure leaves stock packed. A post-shipment delivery failure does not return stock to inventory. A complete returns/RMA workflow is outside core scope and documented as future work. |
| D12 | Accepted | Allocation, fulfillment, and delivery progress are tracked separately. An item is fulfilled when `shipped + cancelled = ordered`; an order completes only when all items meet that condition. |

## Reliability

| ID | Status | Decision |
| --- | --- | --- |
| D13 | Accepted | Mutating operations use central idempotency records with an operation type, key, request hash, status, and original result. Same key and payload returns the original result; same key with a different payload is rejected. |
| D14 | Accepted | A provider timeout means the outcome is unknown. No stock changes occur; retry or reconciliation reuses the same provider request key. |
| D15 | Accepted | A permanent provider failure leaves inventory packed. A new provider attempt or explicit warehouse reversal is required. |
| D16 | Accepted | Provider callbacks are persisted before processing. Duplicate events are acknowledged without repeated effects. Out-of-order future events remain pending until prerequisites exist; stale events are harmless no-ops. |
| D17 | Accepted | Persisted pending states, after-commit dispatch, and scheduled sweepers provide recovery. A generic transactional outbox is a future improvement rather than core scope. |
| D18 | Accepted | Inventory movements use an append-only double-entry form: source warehouse/bucket, destination warehouse/bucket, quantity, operation, and business reference. |

## Interfaces, Security, and Delivery

| ID | Status | Decision |
| --- | --- | --- |
| D19 | Accepted, extensible | Build a server-rendered Bootstrap/Blade operational UI using authenticated forms, plus Artisan commands, queued jobs, scheduler entry points, and the required signed provider webhook. JavaScript/jQuery is limited to progressive enhancement and is not required for correctness. A general versioned JSON API is optional future work and is not required by the UI or challenge. Every entry point uses shared application services. |
| D20 | Accepted | Use one authenticated administrator role with access to every operational feature. For challenge scope, that administrator performs the warehouse-operator actions; separate operational roles are future work. All operational web mutations require authentication and authorization. |
| D21 | Accepted | Shipping-provider webhooks use HMAC verification over a timestamp and raw request body, with replay-window and event-ID checks. |
| D22 | Optional | Add a read-only ledger-to-projection reconciliation command only after core work is complete. It reports drift and never silently corrects balances. |
| D23 | Provisional | Demonstrate six focused live failure scenarios and use the small smoke/critical test suite plus explanation or manual evidence for other relevant behavior. Final video contents may change after the demo is complete. |
| D24 | Provisional | Accept the documented core, stretch, and out-of-scope boundaries for now. Implement submission-critical work first. Supporting work remains planned but may be simplified only after an explicit time review, with the change recorded as a limitation. |
| D25 | Accepted | Use a small risk-based Pest suite. Smoke tests cover application wiring, required pages, commands, and representative happy paths. Focused MySQL integration and concurrency tests cover inventory correctness, idempotency, rollback, reservations, shipment deduction, retries, and duplicate callbacks. Unit tests are limited to important pure calculations or state/outcome mapping; exhaustive per-class or per-branch coverage is not required. |
| D26 | Accepted | Only a valid persisted `shipment.confirmed` webhook may mark a local shipment shipped and deduct packed inventory. Provider submission responses and administrator controls cannot do so directly. |
| D27 | Accepted | Persist the mock provider's external shipments and outbound events separately from local provider attempts and the received-event inbox. |
| D28 | Accepted | Mock-provider outcomes use configurable weighted random selection by default, with a per-shipment forced override for deterministic tests and demonstrations. |
| D29 | Accepted | In local demonstration, the mock provider sends callbacks as actual signed HTTP requests to the configured webhook URL. Automated tests fake outbound HTTP while testing the real receiving route independently. |
| D30 | Accepted | Local/testing controls can send shipment confirmation, send delivery confirmation, replay the last webhook, and deliberately send an out-of-order delivery event. These controls operate only through mock-provider outbound events. |
| D31 | Accepted | The timeout demonstration represents provider acceptance followed by a lost response. The external identity and future callback already exist while the local submission remains uncertain and inventory remains packed. |
| D32 | Accepted | Uncertain submissions support provider status lookup by the stable request key as well as idempotent resubmission. Reconciliation never bypasses the confirmation webhook. |
| D33 | Accepted | An exact duplicate callback reuses the same external event ID and raw body. Repeated HTTP attempts and application processing must remain harmless. |
| D34 | Accepted | Use a Laravel application-service layer under domain-oriented `app/Services` namespaces. Controllers, commands, jobs, and webhooks use constructor injection and call focused service methods. Services own use-case orchestration and transaction boundaries, depend on interfaces at external boundaries, and must not become generic base classes or oversized catch-all services. |
| D35 | Accepted sequencing choice | Create and finalize the root README, `docs/ARCHITECTURE.md`, `docs/AI_USAGE.md`, and video outline after the working demo is complete in Phase 6. Their required content remains submission-critical in Phase 7; the decision register preserves interim ownership and trade-off notes until then. |

## Deferred Review Points

These items are intentionally unresolved:

1. Whether automatic cross-warehouse allocation will be implemented after the core system is complete.
2. Whether the optional reconciliation command fits the remaining schedule.
3. Which exact scenarios fit the final 15–20 minute video.
4. Whether presentation-focused UI additions are worthwhile after all correctness tests pass.
5. Whether a general versioned JSON API is worthwhile after every required deliverable passes.

## Decision Ownership

These business decisions were selected by the repository owner after reviewing alternatives and trade-offs. AI assistance was used to enumerate options, expose consequences, organize the resulting rules, and prepare the implementation plan. This distinction must be reflected in `docs/AI_USAGE.md`.
