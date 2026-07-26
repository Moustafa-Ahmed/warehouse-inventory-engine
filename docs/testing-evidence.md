# Testing Evidence

## Purpose

This matrix connects the agreed acceptance behavior to the intentionally small Pest suite and the final walkthrough. It is a traceability guide, not a coverage-percentage claim. Database-backed tests use MySQL; outbound provider HTTP is faked while the receiving webhook route is tested as a real HTTP boundary.

Evidence classifications:

- **Critical:** focused integration, concurrency, rollback, job, or webhook test protecting a high-risk invariant.
- **Smoke:** broad wiring, page, command, seeding, or representative form evidence.
- **Walkthrough:** deterministic live demonstration prepared by the demo commands.
- **Manual:** concise inspection where another automated test would not protect a distinct risk.

## Critical-Risk Traceability

| Testing-strategy risk | Primary focused evidence |
| --- | --- |
| 1. Two requests cannot reserve the final unit | `Critical/Reservations/ReservationConcurrencyTest.php` — final-unit test using independent MySQL connections |
| 2. Partial reservation reports requested, allocated, and outstanding | `Critical/Reservations/ReservationServiceTest.php` — partial allocation result plus movement, transition, projection, and operation assertions |
| 3. Identical replay is harmless and changed payload conflicts | `Critical/Operations/OperationServiceTest.php`, `Critical/Inventory/InventoryServiceTest.php`, `Critical/Web/InventoryReceiptWebWorkflowTest.php` |
| 4. Injected failure rolls back all related writes | `Critical/Reservations/ReservationServiceTest.php`, `Critical/Inventory/InventoryMovementServiceTest.php`, `Critical/Shipping/ShipmentConfirmationTest.php` |
| 5. Release returns only reserved stock to available | `Critical/Reservations/ReservationServiceTest.php` |
| 6. Receipt recovers partial allocation without over-allocation | `Critical/Reservations/BackorderAllocationTest.php` |
| 7. Transfer consumes only available inventory | `Critical/Inventory/InventoryServiceTest.php` |
| 8. Shipment confirmation deducts packed stock exactly once | `Critical/Shipping/ShipmentConfirmationTest.php` |
| 9. Timeout keeps stock packed until late confirmation | `Critical/Shipping/ProviderSubmissionReconciliationTest.php`, `Critical/Shipping/ShipmentSubmissionOutcomeTest.php` |
| 10. Duplicate job creates no second shipment effect | `Critical/Shipping/PendingShipmentProcessingTest.php`, `Critical/Shipping/ShipmentConfirmationTest.php` |
| 11. Valid HMAC callback is accepted once; invalid signature is rejected | `Critical/Shipping/ShippingProviderWebhookTest.php` |
| 12. Duplicate and out-of-order callbacks cannot skip prerequisites or deduct twice | `Critical/Shipping/OutOfOrderProviderWebhookTest.php`, `Critical/Shipping/ShippingProviderWebhookTest.php`, `Critical/Shipping/ShipmentConfirmationTest.php` |
| 13. Provider acceptance alone cannot ship inventory | `Critical/Shipping/ShipmentSubmissionOutcomeTest.php`, `Critical/Shipping/ShipmentCompositionTest.php` |
| 14. Reconciliation cannot bypass callback processing or duplicate external identity | `Critical/Shipping/ProviderSubmissionReconciliationTest.php` |
| 15. HTTP retry preserves webhook identity and at-most-once effect | `Critical/Shipping/MockProviderWebhookDeliveryTest.php`, `Critical/Shipping/ShippingProviderWebhookTest.php` |

## Acceptance-Scenario Classification

| # | Scenario | Classification | Evidence |
| ---: | --- | --- | --- |
| 1 | Concurrent final-unit reservation | Critical + walkthrough | `Critical/Reservations/ReservationConcurrencyTest.php`; `demo:concurrent-reservation` |
| 2 | Partial reservation | Critical + walkthrough | `Critical/Reservations/ReservationServiceTest.php`; `demo:inventory-scenarios` |
| 3 | Automatic backorder allocation | Critical | `Critical/Reservations/BackorderAllocationTest.php` |
| 4 | Duplicate reservation command | Critical + walkthrough | `Critical/Reservations/ReservationServiceTest.php`; repeat the same operation key in the walkthrough |
| 5 | Idempotency conflict | Critical | `Critical/Operations/OperationServiceTest.php`, `Critical/Web/InventoryReceiptWebWorkflowTest.php` |
| 6 | Transaction rollback | Critical + walkthrough | `Critical/Reservations/ReservationServiceTest.php`, `Critical/Inventory/InventoryMovementServiceTest.php` |
| 7 | Order quantity reduction | Critical | `Critical/Orders/OrderEditServiceTest.php` |
| 8 | Pick, pack, and reversal | Critical | `Critical/Fulfillment/FulfillmentLifecycleTest.php`, `FulfillmentUnpackingTest.php`, `FulfillmentReturnTest.php` |
| 9 | Available-stock transfer | Critical | `Critical/Inventory/InventoryServiceTest.php` |
| 10 | Transfer exceeds availability | Critical | `Critical/Inventory/InventoryServiceTest.php` |
| 11 | Partial shipment | Critical + walkthrough | `Critical/Shipping/ShipmentCompositionTest.php`, `ShipmentConfirmationTest.php`; shipment UI |
| 12 | Provider timeout | Critical + walkthrough | `Critical/Shipping/ProviderSubmissionReconciliationTest.php`; deterministic timeout demo shipment |
| 13 | Permanent provider failure | Critical + walkthrough | `Critical/Shipping/ShipmentSubmissionOutcomeTest.php`; deterministic failed demo shipment |
| 14 | Duplicate shipment callback | Critical + walkthrough | `Critical/Shipping/ShippingProviderWebhookTest.php`, `ShipmentConfirmationTest.php`; replay control |
| 15 | Out-of-order delivery callback | Critical + walkthrough | `Critical/Shipping/OutOfOrderProviderWebhookTest.php`; persisted pending demo receipt |
| 16 | Reservation expiration | Critical | `Critical/Reservations/ReservationExpirationTest.php` |
| 17 | Worker retry | Critical + walkthrough explanation | `Critical/Shipping/PendingShipmentProcessingTest.php`, `MockProviderWebhookDeliveryTest.php` |
| 18 | HMAC verification | Critical | `Critical/Shipping/ShippingProviderWebhookTest.php` |
| 19 | Partial result in operational UI | Smoke | `Smoke/OrderReservationWebWorkflowTest.php` |
| 20 | Duplicate browser submission | Critical web workflow | `Critical/Web/InventoryReceiptWebWorkflowTest.php` |
| 21 | Core operation without JavaScript | Smoke + manual | `Smoke/OperationalLayoutTest.php`, server-rendered forms, and walkthrough with normal form posts |
| 22 | Inventory reporting | Smoke/integration | `Smoke/OperationalReportsTest.php` |
| 23 | Open commitments and consumed inventory | Smoke/integration | `Smoke/OperationalReportsTest.php` |
| 24 | Accepted submission is not yet shipped | Critical | `Critical/Shipping/ShipmentSubmissionOutcomeTest.php` |
| 25 | Manual mock confirmation | Critical boundary + walkthrough | `Critical/Shipping/MockProviderControlsTest.php`, `MockProviderWebhookDeliveryTest.php`; local HTTP callback walkthrough |
| 26 | Outbound transport retry | Critical | `Critical/Shipping/MockProviderWebhookDeliveryTest.php` |
| 27 | Reconciliation does not bypass webhook | Critical + walkthrough | `Critical/Shipping/ProviderSubmissionReconciliationTest.php` |

## Broad Smoke Evidence

- `Smoke/ApplicationBootTest.php` verifies MySQL wiring, required tables, provider binding, related factories, guest protection, and login rendering.
- `Smoke/ReferenceDataSeederTest.php` verifies repeatable reference seeding without inventing inventory history.
- `Smoke/ShippingRecoveryScheduleTest.php` verifies all recovery commands are registered, scheduled with overlap protection, and safe with no eligible work.
- Operational page smoke tests cover inventory, orders, reservations, fulfillment, shipments, provider webhook receipts, reports, catalog administration, dashboard, authentication shell, and deterministic demo setup.
- `Smoke/DemoScenarioCommandTest.php` verifies demo setup, ledger reconciliation, scoped reset, idempotent rerun, command registration, and production-environment rejection.

## Deliberate Non-Coverage

Simple readonly DTOs, enum declarations, casts, Eloquent relationship accessors, framework validation branches, and individual Blade fragments do not receive standalone tests. Their behavior is exercised through the higher-value workflows above. Frontend production build, clean-clone setup, real local callback delivery, and reviewer access are final verification or walkthrough evidence rather than repeated PHP tests.
