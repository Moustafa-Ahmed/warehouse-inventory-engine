# Phase 5 — Shipping Reliability

## Objective

Turn the Phase 1 provider boundary and schema into a persistent mock external system, then integrate it with provider submissions, actual signed HTTP callbacks, explicit timeout reconciliation, duplicate safety, and scheduled recovery.

Provider calls never run inside an inventory-locking transaction. Jobs and commands locate work and invoke application services; they do not contain shipment or inventory rules. A provider response may change submission state, but only a valid persisted callback may move packed inventory to shipped.

## Commit P5.1 — `feat: prepare idempotent provider submissions`

**Priority:** Submission-critical

Scope:

- Implement `ShipmentSubmissionService::prepare()` to lock an eligible packed shipment.
- Reuse the Phase 1 shipping DTOs at the provider boundary; add a new readonly preparation result only if the service needs to carry `ProviderSubmission` identity alongside the provider request.
- Create or reuse a `ProviderSubmission` with a stable provider request key.
- Persist the submission-ready state before any provider call.
- Reject ineligible, duplicate, or terminal shipment submissions.
- Commit before returning the provider request object.
- Add one focused replay test.

Done when:

- The provider request identity exists durably before external work.
- Repeating preparation returns the same active provider submission and request key.
- No provider call occurs while shipment or inventory locks are held.

## Commit P5.2 — `feat: persist mock-provider shipments and outcomes`

**Priority:** Submission-critical

Scope:

- Implement the persistent local `ShippingProvider` adapter using the Phase 1 contract.
- Replace the local runtime binding to the Phase 1 in-memory fake while retaining that small fake for isolated contract tests.
- Create or find one `MockProviderShipment` by stable provider request key.
- Return the existing external identity for repeated submission.
- Implement provider status lookup by request key.
- Apply a per-shipment forced scenario when present; otherwise use configurable weighted random selection.
- Persist accepted, permanently rejected, and handoff/delivery provider states independently from the warehouse application's shipment state.
- Persist immediate, delayed, timeout-followed-by-success, delivery, and out-of-order webhook intents as `MockProviderWebhook` records.
- Model exact duplicate delivery as another delivery attempt of the same webhook ID and immutable raw body, not a second webhook.
- For timeout-after-acceptance, commit the external shipment and future callback before simulating the lost response.
- Add one focused deterministic dataset under the concrete Shipping test area for stable identity, outcome mapping, status lookup, and timeout-after-acceptance.

Done when:

- Every required outcome is reproducible without randomness.
- Repeated submission cannot create a second mock-provider shipment.
- Provider state and future callback intent survive worker restarts and simulated timeouts.
- No callback is delivered and no warehouse inventory changes in this commit.

## Commit P5.3 — `feat: verify and persist provider webhooks`

**Priority:** Submission-critical

Scope:

- Implement shared HMAC signing/verification over timestamp and raw body.
- Add `POST /webhooks/shipping-provider`.
- Convert validated callback input into a readonly shipping DTO before passing it to the provider-webhook service; do not pass the raw request or an untyped array into business orchestration.
- Validate provider, event ID, timestamp replay window, signature, JSON structure, quantities, and supported event type.
- Persist a `ProviderWebhookReceipt` before dispatching processing.
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

- Implement `DeliverMockProviderWebhookJob` for one persisted due `MockProviderWebhook`.
- Send an actual HTTP `POST` to the configured webhook URL in local demonstration.
- Require the callback job to run on a non-synchronous queue worker so a loopback request cannot block the original administrator request.
- Send the persisted external event ID and raw body with a current timestamp and matching HMAC signature.
- Treat `2xx` as acknowledged.
- Retain network failures, timeouts, `429`, and retryable server responses for bounded exponential retry.
- Mark non-retryable authentication, validation, and configuration responses visibly failed rather than retrying forever.
- Record attempt count, timestamps, and safe response/error context without signatures or secrets.
- Implement bounded `mock-provider:dispatch-pending` discovery.
- Fake outbound HTTP in tests and assert URL, safe headers, stable webhook identity/body, acknowledgement, retryable failure, and permanent transport failure.

Done when:

- Local/demo callbacks cross the real HTTP webhook boundary.
- Transport retry reuses the same external event ID and raw body.
- Automated tests do not need a running web server or real network access.
- A failed delivery remains visible and recoverable according to its failure class.

## Commit P5.5 — `feat: submit shipments through the provider boundary`

**Priority:** Submission-critical

Scope:

- Implement `ShipmentSubmissionService::submit()` as the coordinator around preparation, provider call, and outcome recording.
- Depend on `ShippingProvider`, not the persistent mock implementation.
- Consume the existing provider request/result DTOs and keep submission outcome separate from callback-delivery intent.
- Make the provider call outside database transactions.
- Record accepted, permanently failed, and unknown-after-timeout results on the `ProviderSubmission` through short follow-up transactions owned by the service.
- Never interpret provider acceptance as shipment confirmation.
- Ensure immediate success means an immediately due mock-provider webhook, not direct inventory deduction.
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

- Implement `SubmitShipmentJob` as a thin adapter over `ShipmentSubmissionService`.
- Implement `shipments:process-pending` to select bounded eligible batches and dispatch jobs.
- Set explicit job timeout, tries, and bounded exponential backoff.
- Ensure queue `retry_after` is greater than the job timeout.
- Add useful `failed()` context without leaking provider secrets.
- Use job uniqueness only as an efficiency measure; retain database identity constraints as the correctness mechanism.
- Add one focused command/job test covering acceptance, repeat execution, and permanent failure.

Done when:

- Running the command repeatedly cannot create duplicate provider submissions or external shipment identities.
- A permanent rejection is not retried forever.
- The job and command contain no inventory mutation logic.

## Commit P5.7 — `feat: reconcile provider submissions with unknown outcomes`

**Priority:** Submission-critical

Scope:

- Implement `ShipmentSubmissionService::reconcile()` using provider status lookup and the stable request key.
- Implement bounded `provider-submissions:reconcile-unknown` discovery and thin reconciliation jobs.
- Keep packed and on-hand quantities unchanged while the local outcome is unknown.
- Record provider acceptance or rejection without treating status lookup as shipment confirmation.
- If provider handoff is confirmed but its callback is unacknowledged, make the existing mock-provider confirmation webhook due for redelivery.
- Allow idempotent resubmission with the same key as a fallback without creating another external shipment.
- Add one critical timeout-after-acceptance, status-lookup, callback-redelivery, and late-success scenario.

Done when:

- A timeout never automatically releases or ships inventory.
- Status lookup and resubmission cannot create a second provider shipment identity.
- Reconciliation cannot deduct inventory or bypass the signed callback.

## Commit P5.8 — `feat: apply shipment confirmation webhooks atomically`

**Priority:** Submission-critical

Scope:

- Implement shipment confirmation through `ShipmentService::confirmHandoff()`.
- Extend the operation-type enum with shipment confirmation here, where the persisted provider webhook receipt and shipment service consume it.
- Lock shipment, reservation, shipment-item, and balance records in deterministic order.
- Require the callback to confirm the complete composed shipment.
- Move every shipment item's full quantity from packed to external/shipped exactly once.
- Mark the shipment `shipped` without adding a shipment-item shipped-quantity projection.
- Update shipment, reservation, order item, operation, movement, history, and provider webhook receipt in one transaction.
- Implement `ProviderWebhookService` to classify and route persisted receipts, with `ProcessProviderWebhookJob` as its thin queued adapter.
- Add critical duplicate-confirmation, rollback, and worker-retry scenarios.

Done when:

- Repeating shipment confirmation cannot deduct packed stock twice.
- A crash cannot persist only part of the shipment-confirmation transaction.
- The job contains no duplicate state-transition implementation and delegates to the service layer.

## Commit P5.9 — `feat: apply delivery confirmations idempotently`

**Priority:** Submission-critical

Scope:

- Implement delivery confirmation through `ShipmentService::confirmDelivery()`.
- Extend the operation-type enum with delivery confirmation here; the persisted provider webhook receipt supplies the stable identity for the central operation record.
- Require a `shipped` shipment and apply partial or complete delivery progress to each shipment item's `delivered_quantity`.
- Prevent delivered quantity from exceeding the shipment item's quantity.
- Do not modify warehouse balance projections.
- Recalculate progress through the shared calculator.
- Keep delivery progress separate from shipment and provider-submission statuses; do not duplicate delivery labels inside those enums.
- Handle delayed and exact duplicate delivery confirmation.
- Add one focused delivery-progress and duplicate scenario.

Done when:

- Delivery can never reintroduce or deduct warehouse inventory.
- An order becomes delivered only when all shipped quantity is delivered.
- Replaying the same delivery webhook has one business effect.

## Commit P5.10 — `feat: defer stale and out-of-order provider webhooks`

**Priority:** Submission-critical

Scope:

- Implement a webhook classifier for stale, valid-next, and future receipts.
- Mark stale webhook receipts ignored without error.
- Keep future webhook receipts pending.
- Implement `provider-webhooks:process-pending` as a bounded recovery command.
- Route valid receipts through `ProviderWebhookService` to the already implemented `ShipmentService` methods.
- Add one out-of-order scenario proving a webhook receipt waits and later processes once.

Done when:

- Delivery arriving before shipment confirmation cannot skip inventory movement.
- Pending provider webhook receipts eventually process after prerequisites exist.

## Commit P5.11 — `feat: add guarded mock-provider scenario controls`

**Priority:** Submission-critical

Scope:

- Implement `MockProviderControlService` with local/testing-only methods to set a shipment's next forced provider scenario.
- Implement controls to create or release shipment-confirmed and delivery-confirmed mock-provider webhooks.
- Implement exact replay using the original external event ID and raw body.
- Implement the deliberate out-of-order delivery control.
- Add guarded `mock-provider:send-webhook` and `mock-provider:replay-webhook` commands.
- Reject controls outside local/testing environments and for non-mock provider adapters.
- Ensure controls mutate only mock-provider state or dispatch callback delivery; they never invoke warehouse shipment or inventory services directly.
- Add a focused authorization/environment test and a small control-mapping dataset.

Done when:

- Every required provider scenario can be triggered deterministically.
- “Send shipment confirmation now” still reaches warehouse state only through signed HTTP and the persisted provider webhook receipt.
- Demo-only behavior cannot be exposed accidentally in production.

## Commit P5.12 — `feat: schedule persisted shipping recovery`

**Priority:** Submission-critical

Scope:

- Schedule pending-shipment discovery.
- Schedule reconciliation for provider submissions with unknown outcomes.
- Schedule due/retryable mock-provider webhook delivery.
- Schedule pending provider webhook receipt processing.
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

- [ ] Shipment submission service uses the early provider contract and persistent deterministic mock provider
- [ ] Mock-provider shipments and webhooks persist independently from provider submissions and provider webhook receipts
- [ ] Provider calls occur outside database transactions and inventory locks
- [ ] Pending-shipment command and job are thin, bounded, and repeat-safe
- [ ] Provider acceptance alone cannot mark a shipment shipped
- [ ] Timeout-after-acceptance and permanent failure have distinct behavior
- [ ] Stable-key status lookup and resubmission cannot create another external identity
- [ ] Reconciliation cannot bypass the shipment-confirmed webhook
- [ ] HMAC validation and replay protection pass
- [ ] Local/demo callback delivery uses actual signed HTTP
- [ ] Outbound transport retries retain one webhook identity and are observable
- [ ] Duplicate and out-of-order provider webhook receipts are safe
- [ ] Shipment confirmation is atomic with inventory deduction
- [ ] Every challenge-required mock-provider mode is deterministic and random mode remains available
- [ ] Demo controls are unavailable outside local/testing environments
- [ ] Scheduler exposes every persisted recovery path
- [ ] Smoke, focused provider datasets, critical shipping tests, and Pint pass
