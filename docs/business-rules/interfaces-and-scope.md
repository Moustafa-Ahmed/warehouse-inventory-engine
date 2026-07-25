# Interfaces and Scope

## 1. Required Entry Points

Every required entry point calls shared application services:

```text
Blade web controllers --+
Artisan commands -------+--> Application Services --> Domain Rules --> MySQL
Queued jobs ------------+
Provider webhook -------+
```

Web controllers, commands, jobs, and the webhook translate input and output only. Blade views present prepared data. None of them implements inventory rules.

A general JSON API is not required by the challenge or by the operational UI. If one is added later, it becomes another adapter over the same application services.

### Application Service Pattern

Use domain-oriented namespaces under `app/Services`, for example:

```text
app/Services/
├── Inventory/
├── Orders/
├── Reservations/
├── Fulfillment/
└── Shipping/
```

Typed service inputs and results that would otherwise be ambiguous arrays use native PHP `final readonly` DTOs grouped by the same business areas:

```text
app/DTOs/
├── Inventory/
├── Orders/
├── Reservations/
├── Fulfillment/
└── Shipping/
```

Rules:

- Group cohesive operations in focused services such as `InventoryService`, `ReservationService`, `FulfillmentService`, `ShipmentService`, `ShipmentSubmissionService`, and `ProviderWebhookService`.
- Use descriptive typed methods such as `reserve()`, `release()`, `pick()`, `submit()`, and `confirmShipment()` rather than a generic `execute()` API.
- Introduce a DTO with its first consuming service or external contract; do not create speculative request/result classes for later phases.
- Keep DTOs as typed data carriers. Business orchestration, locking, idempotency, and state transitions remain in application services.
- Use native PHP readonly DTOs unless an explicitly approved package solves a demonstrated hydration, transformation, or serialization need.
- Inject services through constructors; do not resolve them with `app()` inside controllers, commands, or jobs.
- Services own business orchestration, pessimistic locking, and transaction boundaries.
- Keep slow provider HTTP work outside inventory transactions even when one service coordinates the overall use case.
- Depend on contracts for shipping providers and other external boundaries.
- Introduce calculators, enums, value objects, exceptions, and state machines only with their first real consumer and after their semantics are defined.
- Do not create `BaseService`, generic CRUD services, or repository wrappers around Eloquent without a demonstrated need.
- Split a service when it mixes unrelated domain concerns or becomes difficult to test and explain.

## 2. Operational UI

The required UI is server-rendered Blade with Bootstrap. It uses ordinary HTTP form submissions and does not depend on AJAX.

Required request flow:

```text
GET page -> render Blade form with operation key
POST form -> authenticate -> authorize -> validate -> call application service
          -> redirect -> display stored result or validation/domain error
```

Rules:

- Every operational route requires the administrator session.
- For challenge scope, the administrator is also the warehouse operator; separate roles are not implemented.
- Every form uses CSRF protection and an appropriate Form Request.
- Every authenticated business-mutation form carries a generated operation key.
- Repeating the same form payload and operation key returns the original result.
- Reusing a key with changed input produces a visible conflict.
- Post/redirect/get prevents refresh from becoming a new business intent.
- Requested, applied, and outstanding quantities are visible wherever partial work is possible.
- Domain and validation failures are presented without exposing internal exceptions or secrets.
- JavaScript/jQuery may enhance filters, confirmations, or local demo controls, but required operations continue to work without it.

Planned screens:

1. Administrator login.
2. Dashboard for partial allocations, expiring reservations, shipments pending handoff, uncertain or failed provider submissions, pending provider webhook receipts, and recent movements.
3. Product catalog with per-warehouse stock and outstanding demand.
4. Warehouse catalog with current stock.
5. Inventory balance details with receipt, adjustment, transfer, reservation, and movement forms or links.
6. Inventory reporting with aggregate available, reserved, picked, packed, on-hand, and shipped quantities.
7. Open-reservation report with product, warehouse, order, age, expiration, and status filters.
8. Orders-that-consumed-inventory report, where consumption means quantity moved from packed stock to external/shipped after confirmed carrier handoff.
9. Order list and detail with allocation, fulfillment, and delivery progress.
10. Reservation detail and timeline with only valid reserve, confirm, release, pick, return, pack, and unpack actions.
11. Backorder work queue with allocate-now action.
12. Shipment list and detail with items, provider submissions, mock-provider webhooks, and submission controls.
13. Provider webhook receipt list and detail.
14. Local mock-provider controls for per-shipment outcome selection, shipment confirmation, delivery confirmation, exact webhook replay, and out-of-order delivery.

The submission-critical UI is the minimum inventory, order/reservation, fulfillment/shipment, provider-webhook, reporting, and demonstration flow. Catalog create/edit convenience and the consolidated dashboard are supporting work: they remain planned, but may be simplified after the explicit post-Phase-5 time review because reference data and direct operational pages already support the core demonstration.

Additional presentation-oriented screens may be added only after the submission-critical rules, tests, documents, evidence plan, and time review pass.

## 3. Required HTTP Boundaries

The application has two different HTTP concerns.

### Authenticated Operational Web Routes

- Return HTML or redirects rather than requiring JSON.
- Use named routes, session authentication, authorization, CSRF, and Form Requests.
- Keep controllers thin and delegate every mutation to an application service.
- May return a Blade fragment or JSON only as an optional progressive enhancement; that enhancement is not a general API contract.

### Shipping-Provider Webhook

```text
POST /webhooks/shipping-provider
```

The webhook is a machine-to-machine integration endpoint, not a general inventory API. It is outside session authentication because it uses HMAC, timestamp replay protection, provider/webhook identity, input validation, rate limiting, and durable webhook-receipt persistence.

## 4. Artisan Commands

Core commands:

```text
shipments:process-pending
inventory:allocate-backorders
reservations:expire
provider-webhooks:process-pending
```

Demonstration commands:

```text
demo:concurrent-reservation
demo:inventory-scenarios
mock-provider:send-webhook
mock-provider:replay-webhook
```

Provider recovery commands:

```text
shipments:reconcile-uncertain
mock-provider:dispatch-pending
```

Optional:

```text
inventory:reconcile
```

Commands call shared services and do not contain a second implementation of the domain rules.

## 5. Queued Jobs

Planned jobs:

- Allocate an outstanding order item after stock receipt.
- Submit a shipment through the provider.
- Reconcile an uncertain shipment by stable provider request key.
- Process a persisted provider webhook receipt.
- Deliver a persisted mock-provider callback over signed HTTP.

Every job is idempotent and may execute more than once.

## 6. Scheduler

Core schedules:

- Process pending shipments.
- Reconcile uncertain shipment submissions.
- Deliver due and retryable mock-provider webhooks.
- Expire temporary reservations.
- Process pending provider webhook receipts.
- Allocate outstanding order items.

Scheduled commands prevent overlap. Production multi-server deployment would also use a shared lock and single-server scheduling.

## 7. Core Scope

- Products and warehouses.
- Current balance projections.
- Canonical double-entry movement ledger.
- Receipts, adjustments, and available-stock transfers.
- Orders and order items.
- Full and partial reservation results.
- Outstanding allocation and automatic retry.
- Temporary reservation expiration.
- Pick, return, pack, and unpack.
- Partial and multiple shipments.
- Persistent mock-provider shipments and mock-provider webhook delivery history.
- Mock provider success, failure, timeout-after-acceptance, delay, duplicate, replay, reconciliation, and out-of-order callbacks.
- Provider webhook receipts and HMAC validation.
- Central idempotency records.
- Artisan commands, queued jobs, scheduler, signed provider webhook, and minimal operational Blade UI.
- Factories, seeders, tests, documentation, evidence, and video.

## 8. Stretch Goal

Automatic allocation of one order item across multiple warehouses:

- The schema must support it from the beginning.
- Core allocation remains explicitly warehouse-scoped.
- The stretch implementation begins only if all core phase gates pass.

## 9. Optional Work

- Read-only ledger-to-projection reconciliation command.
- Additional UI that materially improves the walkthrough.
- General versioned JSON API.

If the optional API is implemented:

- Place it under `/api/v1`.
- Give it an authentication strategy and rate limiting appropriate to its consumers.
- Use Form Requests and Eloquent API Resources.
- Preserve the same operation-key, request-hash, and original-result semantics for mutations.
- Expose inventory, movement, order, reservation, shipment, and operational-report queries only as needed.
- Call the existing application services; do not build API-specific domain behavior.
- Test it as an independent contract.
- Do not rewrite the Blade UI to depend on it.

## 10. Explicitly Outside Scope

- Full returns/RMA, inspection, quarantine, and restocking.
- Serial, batch, lot, bin, FIFO, and FEFO tracking.
- Inventory costing and financial valuation.
- Purchase orders and supplier management.
- Customer payment and pricing.
- Multi-tenancy.
- Real carrier integrations.
- Multi-primary or distributed databases.
- Full asynchronous event-sourcing infrastructure.
- Advanced reporting or a complete ERP interface.
- Public or partner API integrations.

## 11. Future Improvements

Production evolution may include:

- Full returns workflow.
- Cross-warehouse allocation and reservation reallocation.
- Transactional outbox.
- General versioned JSON API for external consumers.
- Automated reconciliation with supervised repair workflows.
- Partitioned or archived movement history.
- Per-SKU command serialization for extreme contention.
- Read replicas for reporting.
- Provider-specific adapters, rate limiting, and circuit breakers.
- Lot, serial, bin, and expiry-date tracking.
