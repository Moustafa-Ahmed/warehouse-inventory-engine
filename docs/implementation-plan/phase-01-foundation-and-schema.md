# Phase 1 — Foundation, Early Boundaries, and Schema

## Objective

Establish the test environment, living submission documents, domain vocabulary, provider boundary, and relational foundation before implementing state-changing business operations.

## Commit P1.1 — `docs: record agreed business rules and implementation roadmap`

**Priority:** Submission-critical

Scope:

- Add the documentation index.
- Add the business-rules handbook.
- Add the decision register and acceptance scenarios.
- Add this dependency-ordered roadmap.

Done when:

- All planning documents are linked from `docs/README.md`.
- Accepted, deferred, optional, and provisional decisions are distinguishable.
- No application behavior changes.

## Commit P1.2 — `docs: establish living submission documents`

**Priority:** Submission-critical

Scope:

- Replace the Laravel placeholder in the root `README.md` with an honest setup/status skeleton.
- Create `docs/ARCHITECTURE.md` with headings for the required architecture topics and links to the business rules.
- Create `docs/AI_USAGE.md` with the AI-assisted work, personally selected decisions, and rejected alternatives known so far.
- Add explicit “living document” markers so incomplete sections are not presented as finished.
- Add a short video-outline skeleton matching the challenge’s required sections and timings.

Done when:

- AI usage is recorded from the beginning rather than reconstructed at the end.
- Later commits can update setup and architecture beside the code they change.
- Required document paths already exist and clearly show their current status.

## Commit P1.3 — `test: establish the risk-based Pest harness`

**Priority:** Submission-critical

Scope:

- Remove the example Pest tests and placeholder helper.
- Organize tests into focused `Unit`, `Feature/Smoke`, and `Feature/Critical` areas.
- Configure database-backed smoke and critical tests to use an isolated MySQL test database without committed credentials.
- Enable database reset behavior appropriate to each suite.
- Add an initial application-boot smoke test.
- Document the narrow smoke, critical, and full-suite commands from the [Testing Strategy](testing-strategy.md).

Done when:

- The initial smoke test passes against the documented test environment.
- Later commits have a clear place for smoke versus critical tests.
- No test depends on the development database or flaky shared state.

## Commit P1.4 — `feat: add inventory domain enums and value objects`

**Priority:** Submission-critical

Scope:

- Add backed enums for inventory buckets, operation types/statuses, reservation kinds/statuses, allocation progress, fulfillment progress, shipment status, attempt outcome, and provider-event status.
- Add small immutable command/result data objects where they prevent ambiguous arrays.
- Add domain exceptions for invalid transitions, insufficient source quantity, idempotency conflicts, and ineligible operations.
- Add focused unit tests only for important transition rules and quantity calculations; do not test enum declarations or simple data containers.

Done when:

- Status vocabulary is centralized and type safe.
- No controller, job, or action will need ad-hoc status strings.
- Domain exceptions expose structured context.

## Commit P1.5 — `feat: define the shipping provider boundary and deterministic fake`

**Priority:** Submission-critical

Scope:

- Add the `ShippingProvider` contract.
- Add provider request/result objects and explicit success, permanent-failure, timeout/uncertain, and delayed-confirmation outcomes.
- Add an injectable outcome selector.
- Implement an in-memory deterministic fake that can return each outcome without database, queue, or HTTP dependencies.
- Allow a deterministic scenario description to request later or duplicate callback behavior without delivering callbacks yet.
- Bind the contract to the fake for local/testing environments.
- Add one focused outcome-mapping dataset.

Done when:

- Provider-facing code can depend on a stable interface from the start.
- Every required outcome can be selected deterministically.
- Random selection, callback delivery, HMAC, and persistence remain intentionally deferred until the shipment/event infrastructure exists.

## Commit P1.6 — `feat: add product and warehouse catalogs`

**Priority:** Submission-critical

Scope:

- Generate Product and Warehouse models, migrations, factories, and seeders.
- Add unique SKU and warehouse code constraints.
- Add active/inactive state.
- Define mass-assignment fields, casts, relationships, and useful factory states.
- Add the catalogs to the migration/seed smoke test; do not create standalone tests for ordinary model relationships or casts.

Done when:

- Products and warehouses cannot be duplicated by business key.
- Historical entities can be deactivated without destructive deletion.

## Commit P1.7 — `feat: add idempotent operation records`

**Priority:** Submission-critical

Scope:

- Add the central operation table and model.
- Store operation type, unique idempotency key, request hash, status, result reference/payload, failure context, and completion time.
- Add appropriate unique and lookup indexes.
- Add factory states for pending, completed, and failed operations.
- Add one focused MySQL test proving operation-key uniqueness and durable original-result storage.

Done when:

- One operation key cannot represent two persisted operations.
- Request hashes and original outcomes have durable storage.

## Commit P1.8 — `feat: add inventory balances and movement ledger schema`

**Priority:** Submission-critical

Scope:

- Add one inventory balance per product/warehouse.
- Add exclusive available, reserved, picked, and packed projection columns.
- Add non-negative database checks and unique product/warehouse constraint.
- Add append-only movement schema with source/destination warehouse and bucket, positive quantity, operation reference, business reference, actor, and metadata.
- Add models, relationships, casts, and factories.
- Rely on the migration smoke test and movement integration tests rather than standalone schema tests for every column.

Done when:

- Duplicate balance rows are impossible.
- Negative persisted buckets are rejected.
- Movement rows can describe receipt, reservation, transfer, fulfillment, and shipment.

## Commit P1.9 — `feat: add order and order item schema`

**Priority:** Submission-critical

Scope:

- Add orders and order items.
- Store ordered and cancelled quantities plus allocation, fulfillment, and delivery progress projections.
- Add unique order numbers, foreign keys, indexes, models, relationships, casts, and factories.
- Add database constraints for positive ordered quantities and valid terminal totals.

Done when:

- One order can contain multiple products.
- Order items can later own multiple reservations and shipment items.

## Commit P1.10 — `feat: add reservations and transition history schema`

**Priority:** Submission-critical

Scope:

- Add reservations belonging to order items and warehouses.
- Store temporary/confirmed kind, requested quantity, current stage projections, released quantity, status, and nullable expiration.
- Add append-only reservation transition history with operation, actor/source, reason, and before/after quantities.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- Multiple reservations can belong to one order item.
- The schema supports future multi-warehouse allocation.
- Expiring reservations are efficiently queryable.

## Commit P1.11 — `feat: add shipment attempt and provider event schema`

**Priority:** Submission-critical

Scope:

- Add shipments and shipment items.
- Add provider submission attempts with stable provider request keys and outcomes.
- Add provider-event inbox with unique provider/external-event identity, raw payload, occurrence time, processing status, and safe error context.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- One order supports multiple warehouse-specific shipments.
- Provider attempts are distinct from shipment business state.
- Duplicate external event IDs are rejected by the database.

## Commit P1.12 — `feat: seed reference warehouse data`

**Priority:** Submission-critical

Scope:

- Seed products, warehouses, and zero-valued inventory balance reference data.
- Keep factories for important isolated states used by focused tests.
- Do not directly seed non-zero balances, movements, orders, reservations, shipments, attempts, events, or histories.
- Reserve coherent end-to-end scenario data for Phase 6, after the application actions exist.
- Add a `migrate:fresh --seed` smoke test.

Done when:

- A fresh MySQL database migrates and seeds successfully.
- Every non-zero future inventory quantity must enter through the movement applicator.
- Reference seed data cannot drift from the canonical ledger.

## Phase Gate

- [ ] Living README, architecture, AI-usage, and video-outline documents exist and are current
- [ ] Smoke and critical test harnesses use the documented MySQL environment
- [ ] Provider contract and deterministic fake outcomes pass focused tests
- [ ] Fresh migrations succeed on MySQL
- [ ] Rollback works for reversible migrations
- [ ] Factories produce valid isolated and related models
- [ ] Reference seed data loads without bypassing inventory actions
- [ ] Schema includes all challenge-required tables
- [ ] The smoke suite and applicable focused tests pass
- [ ] Pint passes on changed PHP
