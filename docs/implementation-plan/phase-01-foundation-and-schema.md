# Phase 1 — Foundation, Early Boundaries, and Schema

## Objective

Establish the test environment, application-service and DTO conventions, provider boundary, and relational foundation before implementing state-changing business operations. Types are introduced only with the schema, service, or external contract that first consumes them. Final submission documents are intentionally created after the Phase 6 demo is working.

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

## Commit P1.2 — `test: establish the risk-based Pest harness`

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

## Commit P1.3 — `feat: define the shipping provider boundary and deterministic fake`

**Priority:** Submission-critical

Scope:

- Add the `ShippingProvider` contract.
- Add native `final readonly` provider request, submission-result, and status-result DTOs under `app/DTOs/Shipping`.
- Define only the submission outcomes consumed by this boundary: accepted, permanently failed, and timeout/uncertain.
- Keep callback intent separate from submission outcome. A deterministic scenario description may request immediate, delayed, duplicate, or out-of-order callback behavior without delivering callbacks yet.
- Include provider status lookup by stable request key in the boundary so uncertain submissions can be reconciled without creating a new identity.
- Add an injectable outcome selector.
- Implement an in-memory deterministic fake that can return each outcome without database, queue, or HTTP dependencies.
- Bind the contract to the early in-memory fake for local/testing environments until Phase 5 replaces the local runtime binding with the persistent mock-provider adapter.
- Add one focused outcome-mapping dataset.
- Do not define local shipment, provider-attempt, delivery-progress, outbound-event, or inbox-processing statuses in this boundary commit.

Done when:

- Provider-facing code can depend on a stable interface from the start.
- Every required submission outcome and callback-intent scenario can be selected deterministically without mixing their meanings.
- The small in-memory fake remains useful for isolated contract tests, while random selection, callback delivery, HMAC, and persistence remain intentionally deferred until the shipment/event infrastructure exists.

## Commit P1.4 — `feat: add product and warehouse catalogs`

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

## Commit P1.5 — `feat: add idempotent operation records`

**Priority:** Submission-critical

Scope:

- Add the central operation table and model.
- Store operation type, unique idempotency key, request hash, status, result reference/payload, failure context, and completion time.
- Introduce the operation-status enum here because the model and factory consume its defined pending, completed, and failed values.
- Keep the operation-type column as a string in this schema commit. Do not create an enum containing every future operation; P2.1 introduces the initially consumed operation types and later service commits extend them.
- Add appropriate unique and lookup indexes.
- Add factory states for pending, completed, and failed operations.
- Add one focused MySQL test proving operation-key uniqueness and durable original-result storage.

Done when:

- One operation key cannot represent two persisted operations.
- Request hashes and original outcomes have durable storage.

## Commit P1.6 — `feat: add inventory balances and movement ledger schema`

**Priority:** Submission-critical

Scope:

- Add one inventory balance per product/warehouse.
- Add exclusive available, reserved, picked, and packed projection columns.
- Add non-negative database checks and unique product/warehouse constraint.
- Add append-only movement schema with source/destination warehouse and bucket, positive quantity, operation reference, business reference, actor, and metadata.
- Introduce the movement-endpoint bucket enum with the values actually persisted by the movement schema. Treat external/shipped as a movement classification with a null warehouse, not a fifth mutable warehouse balance bucket.
- Do not put generic transition rules on the bucket enum; application services validate movements with product, warehouse, source, destination, quantity, and operation context.
- Add models, relationships, casts, and factories.
- Rely on the migration smoke test and movement integration tests rather than standalone schema tests for every column.

Done when:

- Duplicate balance rows are impossible.
- Negative persisted buckets are rejected.
- Movement rows can describe receipt, reservation, transfer, fulfillment, and shipment.

## Commit P1.7 — `feat: add order and order item schema`

**Priority:** Submission-critical

Scope:

- Add orders and order items.
- Store ordered, cancelled, reserved, picked, packed, shipped, and delivered quantity projections. Outstanding allocation remains derived from the agreed conservation equation.
- Do not create or persist categorical progress enums in this schema commit. P3.1 defines the formulas and resolves the recorded zero, fully cancelled, and not-yet-shipped meanings before any labels are introduced.
- Add unique order numbers, foreign keys, indexes, models, relationships, casts, and factories.
- Add database constraints for positive ordered quantities and valid terminal totals.

Done when:

- One order can contain multiple products.
- Order items can later own multiple reservations and shipment items.

## Commit P1.8 — `feat: add reservations and transition history schema`

**Priority:** Submission-critical

Scope:

- Add reservations belonging to order items and warehouses.
- Store temporary/confirmed kind, requested quantity, current stage projections, released quantity, status, and nullable expiration.
- Introduce only the reservation kind and status enums consumed by these columns. Do not create a generic reservation state machine before the services have the quantity and expiration context needed to enforce transitions.
- Add append-only reservation transition history with operation, actor/source, reason, and before/after quantities.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- Multiple reservations can belong to one order item.
- The schema supports future multi-warehouse allocation.
- Expiring reservations are efficiently queryable.

## Commit P1.9 — `feat: add shipment and provider reliability schema`

**Priority:** Submission-critical

Scope:

- Add shipments and shipment items.
- Add provider submission attempts with stable provider request keys and outcomes.
- Add mock-provider shipments with unique request keys, external identities, forced/random scenario metadata, provider status, and lifecycle timestamps.
- Add mock-provider outbound events with immutable event identity/body, delivery schedule, status, attempts, and safe response/error context.
- Add provider-event inbox with unique provider/external-event identity, raw payload, occurrence time, processing status, and safe error context.
- Before writing the migrations, define and record the exact persisted values for each table. Keep local shipment business state, local provider-attempt state/outcome, mock-provider shipment state, outbound-delivery state, and received-event processing state in separate enums.
- Do not place delivery progress, provider acceptance, uncertainty, or attempt failure into one catch-all shipment status.
- Add models, relationships, casts, indexes, and factory states.

Done when:

- One order supports multiple warehouse-specific shipments.
- Provider attempts are distinct from shipment business state.
- Mock-provider external state is distinct from local attempts and the received-event inbox.
- Due callbacks and uncertain provider shipments are efficiently discoverable.
- Duplicate external event IDs are rejected by the database.

## Commit P1.10 — `feat: seed reference warehouse data`

**Priority:** Submission-critical

Scope:

- Seed products, warehouses, and zero-valued inventory balance reference data.
- Keep factories for important isolated states used by focused tests.
- Do not directly seed non-zero balances, movements, orders, reservations, shipments, attempts, events, or histories.
- Reserve coherent end-to-end scenario data for Phase 6, after the application services exist.
- Add a `migrate:fresh --seed` smoke test.

Done when:

- A fresh MySQL database migrates and seeds successfully.
- Every non-zero future inventory quantity must enter through the movement applicator.
- Reference seed data cannot drift from the canonical ledger.

## Phase Gate

- [ ] Smoke and critical test harnesses use the documented MySQL environment
- [ ] Provider contract, readonly shipping DTOs, and deterministic fake outcome mapping pass focused tests
- [ ] Fresh migrations succeed on MySQL
- [ ] Rollback works for reversible migrations
- [ ] Factories produce valid isolated and related models
- [ ] Reference seed data loads without bypassing inventory services
- [ ] Schema includes all challenge-required tables
- [ ] Persisted status enums belong to their actual tables and do not mix shipment, attempt, provider, event, or delivery meanings
- [ ] No categorical order-progress status exists before P3.1 defines and the owner approves its meaning
- [ ] The smoke suite and applicable focused tests pass
- [ ] Pint passes on changed PHP
