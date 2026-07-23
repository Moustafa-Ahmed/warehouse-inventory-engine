# Phase 1 — Foundation and Schema

## Objective

Establish the domain vocabulary and complete relational foundation without implementing business operations prematurely.

## Commit P1.1 — `docs: record agreed business rules and implementation roadmap`

Scope:

- Add the documentation index.
- Add the business-rules handbook.
- Add the decision register and acceptance scenarios.
- Add this commit-sized roadmap.

Done when:

- All documents are linked from `docs/README.md`.
- Accepted, deferred, optional, and provisional decisions are distinguishable.
- No application behavior changes.

## Commit P1.2 — `feat: add inventory domain enums and value objects`

Scope:

- Add backed enums for stable domain vocabulary: inventory buckets, operation types/statuses, reservation kinds/statuses, allocation progress, fulfillment progress, shipment status, attempt outcome, and provider-event status.
- Add small immutable command/result data objects where they prevent ambiguous arrays.
- Add domain exceptions for invalid transitions, insufficient source quantity, idempotency conflicts, and ineligible operations.
- Add unit tests for enum values, transition rules, and quantity result objects.

Done when:

- Status vocabulary is centralized and type safe.
- No controller, job, or action uses ad-hoc status strings.
- Domain exceptions expose structured context.

## Commit P1.3 — `feat: add product and warehouse catalogs`

Scope:

- Generate Product and Warehouse models, migrations, factories, and seeders.
- Add unique SKU and warehouse code constraints.
- Add active/inactive state.
- Define guarded mass-assignment fields, casts, relationships, and useful factory states.
- Add migration and model tests.

Done when:

- Products and warehouses cannot be duplicated by business key.
- Historical entities can be deactivated without destructive deletion.

## Commit P1.4 — `feat: add idempotent operation records`

Scope:

- Add the central operation table and model.
- Store operation type, unique idempotency key, request hash, status, result reference/payload, failure context, and completion time.
- Add appropriate unique and lookup indexes.
- Add factory states for pending, completed, and failed operations.
- Add database-constraint tests.

Done when:

- One operation key cannot represent two persisted operations.
- Request hashes and original outcomes have durable storage.

## Commit P1.5 — `feat: add inventory balances and movement ledger schema`

Scope:

- Add one inventory balance per product/warehouse.
- Add exclusive available, reserved, picked, and packed projection columns.
- Add non-negative database checks and unique product/warehouse constraint.
- Add append-only movement schema with source/destination warehouse and bucket, positive quantity, operation reference, business reference, actor, and metadata.
- Add models, relationships, casts, factories, and schema tests.

Done when:

- Duplicate balance rows are impossible.
- Negative persisted buckets are rejected.
- Movement rows can describe receipt, reservation, transfer, fulfillment, and shipment.

## Commit P1.6 — `feat: add order and order item schema`

Scope:

- Add orders and order items.
- Store ordered and cancelled quantities plus allocation, fulfillment, and delivery progress projections.
- Add unique order numbers, foreign keys, indexes, models, relationships, casts, and factories.
- Add database constraints for positive ordered quantities and valid terminal totals.

Done when:

- One order can contain multiple products.
- Order items can later own multiple reservations and shipment items.

## Commit P1.7 — `feat: add reservations and transition history schema`

Scope:

- Add reservations belonging to order items and warehouses.
- Store temporary/confirmed kind, requested quantity, current stage projections, released quantity, status, and nullable expiration.
- Add append-only reservation transition history with operation, actor/source, reason, and before/after quantities.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- Multiple reservations can belong to one order item.
- The schema supports future multi-warehouse allocation.
- Expiring reservations are efficiently queryable.

## Commit P1.8 — `feat: add shipment and provider event schema`

Scope:

- Add shipments and shipment items.
- Add provider submission attempts with stable provider request keys and outcomes.
- Add provider-event inbox with unique provider/external-event identity, raw payload, occurrence time, processing status, and error context.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- One order supports multiple warehouse-specific shipments.
- Provider attempts are distinct from shipment business state.
- Duplicate external event IDs are rejected by the database.

## Commit P1.9 — `feat: seed coherent warehouse demo data`

Scope:

- Add factory states for important business situations.
- Seed one administrator placeholder, products, warehouses, balances, orders, reservations, shipments, and history suitable for development.
- Ensure seed data uses valid domain relationships and never bypasses constraints.
- Add a smoke test for `migrate:fresh --seed`.

Done when:

- A fresh MySQL database can migrate and seed successfully.
- Demo data includes full, partial, pending, and failed examples.

## Phase Gate

- [ ] Fresh migrations succeed on MySQL.
- [ ] Rollback works for reversible migrations.
- [ ] Factories produce valid isolated and related models.
- [ ] Seed data loads without disabling constraints.
- [ ] Schema includes all challenge-required tables.
- [ ] All focused and full tests pass.
- [ ] Pint passes on changed PHP.
