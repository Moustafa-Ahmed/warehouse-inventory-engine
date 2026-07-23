# Interfaces and Scope

## 1. Required Entry Points

Every required entry point calls shared application actions:

```text
Blade web controllers --+
Artisan commands -------+--> Application Actions --> Domain Rules --> MySQL
Queued jobs ------------+
Provider webhook -------+
```

Web controllers, commands, jobs, and the webhook translate input and output only. Blade views present prepared data. None of them implements inventory rules.

A general JSON API is not required by the challenge or by the operational UI. If one is added later, it becomes another adapter over the same application actions.

## 2. Operational UI

The required UI is server-rendered Blade with Bootstrap. It uses ordinary HTTP form submissions and does not depend on AJAX.

Required request flow:

```text
GET page -> render Blade form with operation key
POST form -> authenticate -> authorize -> validate -> call application action
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
2. Dashboard for partial allocations, expiring reservations, pending or uncertain shipments, failed attempts, pending provider events, and recent movements.
3. Product catalog with per-warehouse stock and outstanding demand.
4. Warehouse catalog with current stock.
5. Inventory balance details with receipt, adjustment, transfer, reservation, and movement forms or links.
6. Inventory reporting with aggregate available, reserved, picked, packed, on-hand, and shipped quantities.
7. Open-reservation report with product, warehouse, order, age, expiration, and status filters.
8. Orders-that-consumed-inventory report, where consumption means quantity moved from packed stock to external/shipped after confirmed carrier handoff.
9. Order list and detail with allocation, fulfillment, and delivery progress.
10. Reservation detail and timeline with only valid reserve, confirm, release, pick, return, pack, and unpack actions.
11. Backorder work queue with allocate-now action.
12. Shipment list and detail with items, attempts, events, and submission controls.
13. Provider-event inbox.
14. Local demonstration controls for deterministic provider outcomes.

Additional presentation-oriented screens may be added only after the core rules and tests pass.

## 3. Required HTTP Boundaries

The application has two different HTTP concerns.

### Authenticated Operational Web Routes

- Return HTML or redirects rather than requiring JSON.
- Use named routes, session authentication, authorization, CSRF, and Form Requests.
- Keep controllers thin and delegate every mutation to an application action.
- May return a Blade fragment or JSON only as an optional progressive enhancement; that enhancement is not a general API contract.

### Shipping-Provider Webhook

```text
POST /webhooks/shipping-provider
```

The webhook is a machine-to-machine integration endpoint, not a general inventory API. It is outside session authentication because it uses HMAC, timestamp replay protection, provider/event identity, input validation, rate limiting, and durable event persistence.

## 4. Artisan Commands

Core commands:

```text
shipments:process-pending
inventory:allocate-backorders
reservations:expire
provider-events:process-pending
```

Demonstration commands:

```text
demo:concurrent-reservation
demo:inventory-scenarios
```

Optional:

```text
inventory:reconcile
```

Commands call shared actions and do not contain a second implementation of the domain rules.

## 5. Queued Jobs

Planned jobs:

- Allocate an outstanding order item after stock receipt.
- Submit a shipment through the provider.
- Process a persisted provider event.
- Generate delayed or duplicate callbacks in the mock provider.

Every job is idempotent and may execute more than once.

## 6. Scheduler

Core schedules:

- Process pending shipments.
- Expire temporary reservations.
- Process pending provider events.
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
- Mock provider success, failure, timeout, delay, and duplicate callbacks.
- Provider-event inbox and HMAC validation.
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
- Call the existing application actions; do not build API-specific domain behavior.
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
