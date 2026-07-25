# Risk-Based Testing Strategy

## Objective

Build the smallest automated suite that convincingly proves the important business guarantees in the challenge.

The project does not require a unit test for every enum, model, relationship, controller, Form Request, view, or validation branch. It does not target a coverage percentage.

## Test Layers

### 1. Smoke Tests

Smoke tests provide broad, inexpensive confidence that the application is wired correctly.

They cover:

- The application boots.
- A fresh MySQL database migrates and seeds.
- Guest users are redirected from protected operational pages.
- An authenticated administrator can load the primary inventory, order, reservation, shipment, event, and report pages.
- Required Artisan commands are registered and can run safely with no eligible work.
- One representative browser-form workflow reaches the shared application service and redirects with a visible result.
- The production frontend build succeeds.

Ordinary catalog CRUD, page rendering, navigation, filters, and presentation behavior rely primarily on this layer rather than dedicated tests for every screen.

### 2. Critical Feature and Integration Tests

These tests exercise shared application services against MySQL and assert persisted business effects, not only return values.

Important methods and boundaries:

- Central operation/idempotency coordinator.
- Double-entry movement applicator and balance projection update.
- Reservation allocation and release service methods.
- Order-item quantity conservation/progress calculator.
- Shipment-confirmation inventory deduction.
- Pending-shipment command and submission job.
- Mock-provider outcome mapping, stable-key identity, and status lookup.
- Mock-provider outbound-event delivery and retry state.
- Provider-event persistence and processing.

Required risks to prove:

1. Two concurrent requests cannot reserve the same final unit.
2. A partial reservation records requested, allocated, and outstanding quantities.
3. Repeating the same operation does not repeat its effect; changing the payload for the same key conflicts.
4. An injected failure rolls back balance, movement, reservation, operation, and history together.
5. Releasing reserved quantity returns it to available without touching picked or packed stock.
6. A stock receipt can recover an outstanding partial allocation without over-allocation.
7. Available-stock transfer cannot consume committed quantity.
8. Shipment confirmation moves packed stock to external/shipped exactly once.
9. A timeout leaves stock packed and a late confirmation can safely resolve it.
10. A duplicate or retried job cannot create another shipment effect.
11. A valid signed callback is processed once; an invalid signature is rejected.
12. Duplicate and out-of-order provider events cannot deduct inventory twice or skip prerequisites.
13. Provider acceptance alone cannot mark a shipment shipped.
14. Status reconciliation cannot bypass the callback or create a second external shipment.
15. Retried outbound HTTP delivery reuses the same event identity and produces at most one business effect.

Use a dataset when several provider outcomes or state transitions share the same setup. Do not create separate repetitive test classes merely to increase test count.

### 3. Focused Unit Tests

Unit tests are reserved for important pure logic that is faster and clearer without Laravel or a database:

- Quantity conservation and progress calculation.
- Shipment/reservation transition decisions if represented as pure state-machine logic.
- Provider outcome mapping.
- Canonical request hashing if it is isolated as a pure value object.

Enum declarations, getters, casts, Eloquent relationships, framework behavior, and simple data containers do not receive standalone unit tests.

### 4. Manual and Walkthrough Evidence

Secondary combinations that do not protect a distinct high-risk invariant may be shown through:

- The smoke suite.
- Seeded demonstration data.
- The recorded walkthrough.
- A concise manual checklist.

Manual evidence must not replace the critical concurrency, idempotency, rollback, shipment-deduction, or duplicate-callback tests.

## Test Organization

```text
tests/
├── Unit/                 # Important pure calculations or mappings, grouped by business area
└── Feature/
    ├── Smoke/            # Application wiring, pages, commands, representative flow
    └── Critical/         # MySQL service, job, webhook, rollback, and concurrency risks
```

Do not create a generic `Unit/Domain` catch-all. When unit tests are justified, group them by the concrete area such as `Unit/Orders` or `Unit/Shipping`. Critical integration tests may likewise use concrete areas such as `Critical/Inventory`, `Critical/Reservations`, and `Critical/Shipping`.

The exact filenames may follow the implemented class names, but the distinction between unit, smoke, and critical risk tests must remain clear. Simple DTOs, enum declarations, and data containers do not receive standalone tests.

## Database and Queue Rules

- Database-backed smoke and critical tests run against MySQL so checks, locking, and transaction behavior match production assumptions.
- Concurrency tests use separate connections or processes.
- Queue fakes may verify dispatch wiring, but repeat execution and retry safety are proven by directly executing the important jobs against persisted state.
- Outbound mock-provider HTTP is faked in automated tests; the receiving webhook route is tested directly as an HTTP boundary.
- Provider scenarios are selected directly and deterministically in tests; the suite must never be flaky.

## Working Commands

During implementation, run the narrowest relevant group:

```text
php artisan test --compact tests/Unit
php artisan test --compact tests/Feature/Smoke
php artisan test --compact tests/Feature/Critical
```

At phase gates and before submission:

```text
php artisan test --compact
```

The final screenshot should show the complete, intentionally small suite passing.
