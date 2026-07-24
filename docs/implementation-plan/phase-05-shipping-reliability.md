# Phase 5 — Shipping Reliability

## Objective

Turn the Phase 1 provider boundary and schema into a persistent mock external system, then integrate it with shipment attempts, actual signed HTTP callbacks, explicit timeout reconciliation, duplicate safety, and scheduled recovery.

Provider calls never run inside an inventory-locking transaction. Jobs and commands locate work and invoke application actions; they do not contain shipment or inventory rules. A provider response may change submission state, but only a valid persisted callback may move packed inventory to shipped.

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

## Commit P5.2 — `feat: persist mock-provider shipments and outcomes`

**Priority:** Submission-critical

Scope:

- Implement the persistent local `ShippingProvider` adapter using the Phase 1 contract.
- Replace the local runtime binding to the Phase 1 in-memory fake while retaining that small fake for isolated contract tests.
- Create or find one mock external shipment by stable provider request key.
- Return the existing external identity for repeated submission.
- Implement provider status lookup by request key.
- Apply a per-shipment forced scenario when present; otherwise use configurable weighted random selection.
- Persist accepted, permanently rejected, and handoff/delivery provider states independently from local shipment state.
- Persist immediate, delayed, timeout-followed-by-success, delivery, and out-of-order event intents as mock-provider outbound events.
- Model exact duplicate delivery as another delivery attempt of the same event ID and immutable raw body, not a second business event.
- For timeout-after-acceptance, commit the external shipment and future callback before simulating the lost response.
- Add one focused deterministic dataset for stable identity, outcome mapping, status lookup, and timeout-after-acceptance.

Done when:

- Every required outcome is reproducible without randomness.
- Repeated submission cannot create a second mock external shipment.
- Provider state and future callback intent survive worker restarts and simulated timeouts.
- No callback is delivered and no warehouse inventory changes in this commit.

## Commit P5.3 — `feat: verify and persist signed provider callbacks`

**Priority:** Submission-critical

Scope:

- Implement shared HMAC signing/verification over timestamp and raw body.
- Add `POST /webhooks/shipping-provider`.
- Validate provider, event ID, timestamp replay window, signature, JSON structure, quantities, and supported event type.
- Persist the received provider event before dispatching processing.
- Acknowledge duplicates using the unique provider/external-event key.
- Rate limit the webhook independently from session-authenticated routes.
- Use one focused security dataset for valid, missing, expired, invalid, malformed, and duplicate callback input.

Done when:

- Invalid callbacks cannot reach domain processing.
- A safely persisted duplicate receives a successful idempotent response.
- Valid callbacks survive a worker crash after HTTP acknowledgement.

## Commit P5.4 — `feat: deliver mock-provider callbacks over signed HTTP`

**Priority:** Submission-critical

Scope:

- Implement `DeliverMockProviderWebhookJob` for one persisted due outbound event.
- Send an actual HTTP `POST` to the configured webhook URL in local demonstration.
- Require the callback job to run on a non-synchronous queue worker so a loopback request cannot block the original administrator request.
- Send the persisted external event ID and raw body with a current timestamp and matching HMAC signature.
- Treat `2xx` as acknowledged.
- Retain network failures, timeouts, `429`, and retryable server responses for bounded exponential retry.
- Mark non-retryable authentication, validation, and configuration responses visibly failed rather than retrying forever.
- Record attempt count, timestamps, and safe response/error context without signatures or secrets.
- Implement bounded `mock-provider:dispatch-pending` discovery.
- Fake outbound HTTP in tests and assert URL, safe headers, stable event identity/body, acknowledgement, retryable failure, and permanent transport failure.

Done when:

- Local/demo callbacks cross the real HTTP webhook boundary.
- Transport retry reuses the same external event ID and raw body.
- Automated tests do not need a running web server or real network access.
- A failed delivery remains visible and recoverable according to its failure class.

## Commit P5.5 — `feat: submit shipments through the provider boundary`

**Priority:** Submission-critical

Scope:

- Implement `SubmitShipmentAction` as the coordinator around preparation, provider call, and outcome recording.
- Depend on `ShippingProvider`, not the persistent mock implementation.
- Make the provider call outside database transactions.
- Record accepted, permanent failure, and timeout/uncertain results through short follow-up transactions.
- Never interpret provider acceptance as shipment confirmation.
- Ensure immediate success means an immediately due outbound callback, not direct inventory deduction.
- Use explicit connection and request timeouts at provider adapter boundaries.
- Add one focused outcome dataset using the deterministic provider implementation.

Done when:

- Every provider response produces an explicit local submission state.
- Timeout cannot be mistaken for permanent failure.
- Acceptance cannot mark a shipment shipped.
- A provider exception cannot leave an unexplained locked or half-written warehouse transaction.

## Commit P5.6 — `feat: process pending shipments through thin jobs and commands`

**Priority:** Submission-critical

Scope:

- Implement `SubmitShipmentJob` as a thin adapter over `SubmitShipmentAction`.
- Implement `shipments:process-pending` to select bounded eligible batches and dispatch jobs.
- Set explicit job timeout, tries, and bounded exponential backoff.
- Ensure queue `retry_after` is greater than the job timeout.
- Add useful `failed()` context without leaking provider secrets.
- Use job uniqueness only as an efficiency measure; retain database identity constraints as the correctness mechanism.
- Add one focused command/job test covering acceptance, repeat execution, and permanent failure.

Done when:

- Running the command repeatedly cannot create duplicate provider attempts or external shipment identities.
- A permanent rejection is not retried forever.
- The job and command contain no inventory mutation logic.

## Commit P5.7 — `feat: reconcile uncertain shipment submissions`

**Priority:** Submission-critical

Scope:

- Implement `ReconcileShipmentSubmissionAction` using provider status lookup and the stable request key.
- Implement bounded `shipments:reconcile-uncertain` discovery and thin reconciliation jobs.
- Keep packed and on-hand quantities unchanged while the local outcome is uncertain.
- Record provider acceptance or rejection without treating status lookup as shipment confirmation.
- If provider handoff is confirmed but its callback is unacknowledged, make the existing outbound confirmation event due for redelivery.
- Allow idempotent resubmission with the same key as a fallback without creating another external shipment.
- Add one critical timeout-after-acceptance, status-lookup, callback-redelivery, and late-success scenario.

Done when:

- A timeout never automatically releases or ships inventory.
- Status lookup and resubmission cannot create a second provider shipment identity.
- Reconciliation cannot deduct inventory or bypass the signed callback.

## Commit P5.8 — `feat: apply shipment confirmation events atomically`

**Priority:** Submission-critical

Scope:

- Implement `ApplyShipmentConfirmationAction`.
- Lock shipment, reservation, shipment-item, and balance records in deterministic order.
- Move packed stock to external/shipped exactly once.
- Update shipment, reservation, order item, operation, movement, history, and received event in one transaction.
- Implement `ProcessProviderEventJob` as a thin adapter over event actions.
- Add critical duplicate-confirmation, rollback, and worker-retry scenarios.

Done when:

- Repeating shipment confirmation cannot deduct packed stock twice.
- A crash cannot persist a partially confirmed shipment.
- The job contains no duplicate state-transition implementation.

## Commit P5.9 — `feat: apply delivery confirmations idempotently`

**Priority:** Submission-critical

Scope:

- Implement `ApplyDeliveryConfirmationAction`.
- Apply partial and complete delivery progress only to shipped quantities.
- Do not modify warehouse balance projections.
- Recalculate progress through the shared calculator.
- Handle delayed and exact duplicate delivery confirmation.
- Add one focused delivery-progress and duplicate scenario.

Done when:

- Delivery can never reintroduce or deduct warehouse inventory.
- An order becomes delivered only when all shipped quantity is delivered.
- Replaying the same delivery event has one business effect.

## Commit P5.10 — `feat: defer stale and out-of-order provider events`

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

## Commit P5.11 — `feat: add guarded mock-provider scenario controls`

**Priority:** Submission-critical

Scope:

- Implement local/testing-only application actions to set a shipment's next forced provider scenario.
- Implement controls to create or release shipment-confirmed and delivery-confirmed outbound events.
- Implement exact replay using the original external event ID and raw body.
- Implement the deliberate out-of-order delivery control.
- Add guarded `mock-provider:send-event` and `mock-provider:replay-event` commands.
- Reject controls outside local/testing environments and for non-mock provider adapters.
- Ensure controls mutate only mock-provider state or dispatch callback delivery; they never invoke warehouse shipment or inventory actions directly.
- Add a focused authorization/environment test and a small control-mapping dataset.

Done when:

- Every required provider scenario can be triggered deterministically.
- “Send shipment confirmation now” still reaches warehouse state only through signed HTTP and the persisted inbox.
- Demo-only behavior cannot be exposed accidentally in production.

## Commit P5.12 — `feat: schedule persisted shipping recovery`

**Priority:** Submission-critical

Scope:

- Schedule pending-shipment discovery.
- Schedule uncertain-submission reconciliation.
- Schedule due/retryable mock-provider outbound callback delivery.
- Schedule pending received provider-event processing.
- Schedule backorder allocation.
- Schedule reservation expiration when the supporting expiration task is included; otherwise record it for the time review and final limitations.
- Use overlap prevention.
- Use single-server scheduling when deployed with a shared cache.
- Keep every scheduled command bounded by batch size or time.
- Add scheduler and required-command registration to the smoke suite.
- Document the non-synchronous queue connection, web server callback URL, worker, timeout, `retry_after`, and scheduler requirements.

Done when:

- Every persisted shipping pending state has a scheduled recovery path.
- Scheduler safety supplements rather than replaces database idempotency.
- The local callback URL and queue requirements are clear enough to run from a clean clone.

## Phase Gate

- [ ] Submission action uses the early provider contract and persistent deterministic mock provider
- [ ] Mock external shipments and outbound events persist independently from local attempts and the inbox
- [ ] Provider calls occur outside database transactions and inventory locks
- [ ] Pending-shipment command and job are thin, bounded, and repeat-safe
- [ ] Provider acceptance alone cannot mark a shipment shipped
- [ ] Timeout-after-acceptance and permanent failure have distinct behavior
- [ ] Stable-key status lookup and resubmission cannot create another external identity
- [ ] Reconciliation cannot bypass the shipment-confirmed webhook
- [ ] HMAC validation and replay protection pass
- [ ] Local/demo callback delivery uses actual signed HTTP
- [ ] Outbound transport retries retain one event identity and are observable
- [ ] Duplicate and out-of-order received events are safe
- [ ] Shipment confirmation is atomic with inventory deduction
- [ ] Every challenge-required mock-provider mode is deterministic and random mode remains available
- [ ] Demo controls are unavailable outside local/testing environments
- [ ] Scheduler exposes every persisted recovery path
- [ ] README, architecture, AI-usage, and video-outline documents are current
- [ ] Smoke, focused provider datasets, critical shipping tests, and Pint pass
