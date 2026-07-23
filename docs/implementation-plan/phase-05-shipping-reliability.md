# Phase 5 — Shipping Reliability

## Objective

Integrate the Phase 1 provider contract and deterministic fake with shipment attempts, thin queued adapters, explicit timeout handling, signed callbacks, duplicate safety, and scheduled recovery.

Provider calls never run inside an inventory-locking transaction. Jobs and commands locate work and invoke application actions; they do not contain shipment or inventory rules.

## Commit P5.1 — `feat: prepare idempotent shipment submission attempts`

**Priority:** Submission-critical

Scope:

- Implement an action that locks an eligible packed shipment.
- Create or reuse a provider attempt with a stable provider request key.
- Persist the submission-ready state before any provider call.
- Reject ineligible, duplicate, or terminal shipment submissions.
- Commit before returning the provider request object.
- Add one focused replay test.

Done when:

- The provider request identity exists durably before external work.
- Repeating preparation returns the same active attempt and provider key.
- No provider call occurs while shipment or inventory locks are held.

## Commit P5.2 — `feat: submit shipments through the provider boundary`

**Priority:** Submission-critical

Scope:

- Implement `SubmitShipmentAction` as the coordinator around preparation, provider call, and outcome recording.
- Depend on `ShippingProvider`, not the fake implementation.
- Make the provider call outside database transactions.
- Record accepted, permanent failure, timeout/uncertain, and delayed-confirmation outcomes through short follow-up transactions.
- Use explicit connection and request timeouts at real adapter boundaries.
- Add one focused outcome dataset using the deterministic fake.

Done when:

- Every provider outcome produces an explicit local state.
- Timeout cannot be mistaken for permanent failure.
- A provider exception cannot leave an unexplained locked or half-written transaction.

## Commit P5.3 — `feat: process pending shipments through thin jobs and commands`

**Priority:** Submission-critical

Scope:

- Implement `SubmitShipmentJob` as a thin adapter over `SubmitShipmentAction`.
- Implement `shipments:process-pending` to select bounded eligible batches and dispatch jobs.
- Set explicit job timeout, tries, and bounded exponential backoff.
- Ensure queue `retry_after` is greater than the job timeout.
- Add useful `failed()` context without leaking provider secrets.
- Use job uniqueness only as an efficiency measure; retain database idempotency as the correctness mechanism.
- Add one focused command/job test covering success, repeat execution, and permanent failure.

Done when:

- Running the command repeatedly cannot create duplicate provider attempts or external shipment identities.
- A permanent rejection is not retried forever.
- The job and command contain no inventory mutation logic.

## Commit P5.4 — `feat: recover uncertain shipment submissions safely`

**Priority:** Submission-critical

Scope:

- Treat the uncertain state recorded by `SubmitShipmentAction` as an unknown external outcome.
- Reuse the same provider request key for retry or reconciliation.
- Keep packed and on-hand quantities unchanged.
- Allow a later callback to resolve the state.
- Add one critical timeout-followed-by-late-success scenario.

Done when:

- A timeout never automatically releases or ships inventory.
- A retry cannot create a second provider shipment identity.

## Commit P5.5 — `feat: verify and persist signed provider callbacks`

**Priority:** Submission-critical

Scope:

- Implement shared HMAC signing/verification over timestamp and raw body.
- Add `POST /webhooks/shipping-provider`.
- Validate timestamp replay window, event identity, structure, and supported event type.
- Persist the provider event before dispatching processing.
- Acknowledge duplicates using the unique provider/external-event key.
- Rate limit the webhook independently from session-authenticated routes.
- Use one focused security dataset for valid, missing, expired, invalid, and duplicate callback input.

Done when:

- Invalid callbacks cannot reach domain processing.
- A safely persisted duplicate receives a successful idempotent response.
- Valid callbacks survive a worker crash after HTTP acknowledgement.

## Commit P5.6 — `feat: apply shipment confirmation events atomically`

**Priority:** Submission-critical

Scope:

- Implement `ApplyShipmentConfirmationAction`.
- Lock shipment, reservation, shipment-item, and balance records in deterministic order.
- Move packed stock to external/shipped exactly once.
- Update shipment, reservation, order item, operation, movement, history, and event in one transaction.
- Implement `ProcessProviderEventJob` as a thin adapter over event actions.
- Add critical duplicate-confirmation, rollback, and worker-retry scenarios.

Done when:

- Repeating shipment confirmation cannot deduct packed stock twice.
- A crash cannot persist a partially confirmed shipment.
- The job contains no duplicate state-transition implementation.

## Commit P5.7 — `feat: apply delivery confirmations idempotently`

**Priority:** Submission-critical

Scope:

- Implement `ApplyDeliveryConfirmationAction`.
- Apply partial and complete delivery progress only to shipped quantities.
- Do not modify warehouse balance projections.
- Recalculate progress through the shared calculator.
- Handle delayed and duplicate delivery confirmation.
- Add one focused delivery-progress scenario.

Done when:

- Delivery can never reintroduce or deduct warehouse inventory.
- An order becomes delivered only when all shipped quantity is delivered.

## Commit P5.8 — `feat: defer stale and out-of-order provider events`

**Priority:** Submission-critical

Scope:

- Implement an event classifier for stale, valid-next, and future events.
- Mark stale events ignored without error.
- Keep future events pending.
- Implement `provider-events:process-pending` as a bounded recovery command.
- Route valid events to the already implemented shipment-confirmation or delivery action.
- Add one out-of-order scenario proving an event waits and later processes once.

Done when:

- Delivery arriving before shipment confirmation cannot skip inventory movement.
- Pending events eventually process after prerequisites exist.

## Commit P5.9 — `feat: complete random delayed and duplicate mock-provider behavior`

**Priority:** Submission-critical

Scope:

- Add configurable weighted random outcome selection for normal local/demo use.
- Keep deterministic forced outcomes for tests and demonstrations.
- Generate delayed confirmation callbacks through queued work.
- Generate duplicate delivery callbacks with the same external event identity.
- Sign generated callbacks with the configured fake-provider secret.
- Prevent real network calls during automated tests.
- Add a deterministic dataset proving every challenge-required mode can be reproduced without relying on randomness.

Done when:

- Success, permanent failure, timeout, delay, and duplicate confirmation are all reproducible.
- Random mode is available for realistic demonstration without making tests flaky.
- Generated callbacks pass through the same HMAC verification and event-persistence path as the webhook without requiring a real network call in tests.

## Commit P5.10 — `feat: schedule persisted recovery work`

**Priority:** Submission-critical

Scope:

- Schedule pending shipment, provider-event, and backorder commands.
- Schedule reservation expiration when the supporting expiration task is included; otherwise record it for the time review and final limitations.
- Use overlap prevention.
- Use single-server scheduling when deployed with a shared cache.
- Keep every scheduled command bounded by batch size or time.
- Add scheduler and required-command registration to the smoke suite.
- Document worker, timeout, `retry_after`, and scheduler requirements.

Done when:

- Every persisted pending state has a scheduled recovery path.
- Scheduler safety supplements rather than replaces domain idempotency.

## Phase Gate

- [ ] Submission action uses the early provider contract and deterministic fake
- [ ] Provider calls occur outside database transactions and inventory locks
- [ ] Pending-shipment command and job are thin, bounded, and repeat-safe
- [ ] Timeout and permanent failure have distinct behavior
- [ ] HMAC validation and replay protection pass
- [ ] Duplicate and out-of-order events are safe
- [ ] Shipment confirmation is atomic with inventory deduction
- [ ] Every challenge-required mock-provider mode is reproducible
- [ ] Scheduler exposes every persisted recovery path
- [ ] README, architecture, AI-usage, and video-outline documents are current
- [ ] Smoke, provider datasets, and critical shipping tests plus Pint pass
