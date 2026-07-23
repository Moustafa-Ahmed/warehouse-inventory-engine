# Phase 5 — Shipping Reliability

## Objective

Implement the required provider abstraction, queued processing, failure classification, HMAC callbacks, duplicate handling, and eventual recovery.

## Commit P5.1 — `feat: define shipping provider contract and outcomes`

Scope:

- Add provider interface and request/result data objects.
- Represent accepted, permanent failure, timeout/uncertain, and delayed confirmation outcomes explicitly.
- Add provider-specific exceptions only at the adapter boundary.
- Bind the contract through the service container.
- Add unit tests for outcome mapping.

Done when:

- Shipment jobs depend on an interface rather than the mock implementation.
- Timeout cannot be mistaken for permanent failure.

## Commit P5.2 — `feat: simulate shipping provider failure modes`

Scope:

- Implement the fake provider with configurable random outcomes.
- Support deterministic forced outcomes in tests and local demonstration mode.
- Preserve stable provider request-key behavior.
- Simulate delayed confirmations and duplicate delivery callbacks.
- Add tests proving deterministic and random modes respect the contract.

Done when:

- Every challenge-required provider outcome is reproducible.
- Tests do not depend on randomness.

## Commit P5.3 — `feat: submit pending shipments through queued jobs`

Scope:

- Implement `SubmitShipmentJob`.
- Implement `shipments:process-pending`.
- Record provider attempts before and after calls.
- Use explicit connection and request timeouts.
- Use bounded retry/backoff only for transient outcomes.
- Keep provider calls outside inventory-locking transactions.
- Add successful, duplicate-job, and permanent-failure tests.

Done when:

- Running the command repeatedly cannot create duplicate external shipments.
- A permanent rejection is not retried forever.
- Job failure records useful context.

## Commit P5.4 — `feat: preserve uncertain shipment submissions`

Scope:

- Implement timeout-to-uncertain transition.
- Reuse the same provider request key for retry or status reconciliation.
- Keep packed and on-hand quantities unchanged.
- Allow a later callback to resolve the state.
- Add timeout, retry, late-success, and duplicate tests.

Done when:

- A timeout never automatically releases or ships inventory.
- A retry cannot create a second provider shipment identity.

## Commit P5.5 — `feat: verify and persist signed provider callbacks`

Scope:

- Implement HMAC signing and verification for the fake provider.
- Validate timestamp replay window and raw request body.
- Validate event structure using a Form Request or dedicated validator.
- Persist the provider event before dispatching processing.
- Acknowledge duplicates using the unique provider/external-event key.
- Add missing, invalid, expired, valid, and duplicate signature tests.

Done when:

- Invalid callbacks cannot reach domain processing.
- A safely persisted duplicate receives a successful idempotent response.

## Commit P5.6 — `feat: process shipment confirmation events atomically`

Scope:

- Implement `ProcessProviderEventJob`.
- Lock shipment, reservation, and balance records in deterministic order.
- Move packed stock to external shipped exactly once.
- Update shipment, reservation, order item, operation, movement, history, and event in one transaction.
- Add duplicate, rollback, partial-shipment, and worker-retry tests.

Done when:

- Repeating shipment confirmation cannot deduct packed stock twice.
- A crash cannot persist a partially confirmed shipment.

## Commit P5.7 — `feat: defer out-of-order provider events`

Scope:

- Detect stale, valid-next, and future events through the shipment state machine.
- Mark stale events ignored without error.
- Keep future events pending.
- Implement `provider-events:process-pending`.
- Add late prerequisite and repeated-sweeper tests.

Done when:

- Delivery arriving before shipment confirmation cannot skip inventory movement.
- Pending events eventually process after prerequisites exist.

## Commit P5.8 — `feat: process delivery confirmations idempotently`

Scope:

- Apply partial and complete delivery progress only to shipped quantities.
- Do not modify warehouse balance projections.
- Handle delayed and duplicate delivery confirmation.
- Calculate order delivery progress.
- Add partial, duplicate, over-delivery, and out-of-order tests.

Done when:

- Delivery can never reintroduce or deduct warehouse inventory.
- An order becomes delivered only when all shipped quantity is delivered.

## Commit P5.9 — `feat: schedule inventory recovery commands`

Scope:

- Schedule pending shipment, reservation expiration, provider-event, and backorder commands.
- Use overlap prevention.
- Document single-server/shared-cache production requirement.
- Add scheduler registration tests where practical.

Done when:

- Every persisted pending state has a scheduled recovery path.
- Scheduler safety supplements rather than replaces domain idempotency.

## Phase Gate

- [ ] All required mock-provider outcomes are reproducible.
- [ ] Pending-shipment command and job pass repeat-execution tests.
- [ ] Timeout and permanent failure have distinct behavior.
- [ ] HMAC validation and replay protection pass.
- [ ] Duplicate and out-of-order events are safe.
- [ ] Shipment confirmation is atomic with inventory deduction.
- [ ] Scheduler exposes every recovery path.
- [ ] Full tests and Pint pass.
