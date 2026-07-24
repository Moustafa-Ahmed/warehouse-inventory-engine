# Mock Shipping Provider Rules

## 1. Purpose and Boundary

The mock shipping provider behaves as a small external system even though its implementation lives in the same repository.

It has its own persisted shipment state, its own outbound-event history, and a configured HTTP callback URL. The warehouse application communicates with it only through the `ShippingProvider` contract and the signed webhook endpoint.

The mock provider must not:

- Change warehouse balances directly.
- Mark a local shipment as shipped or delivered directly.
- Call shipment-confirmation application actions.
- Share the warehouse application's transaction when accepting a shipment.

This separation makes the demonstration representative of a real carrier integration.

## 2. Authority for Shipment State

Submitting a shipment and shipping a shipment are different events.

- The administrator submits a packed shipment to the provider.
- The provider may accept, reject, or leave the submission outcome uncertain.
- An accepted response records external acceptance only.
- Only a valid persisted `shipment.confirmed` webhook may mark the local shipment shipped and move inventory from packed to external/shipped.
- Only a valid persisted `delivery.confirmed` webhook may advance delivery progress.

There is no ordinary administrator action that directly marks a local shipment shipped.

## 3. Persistent Provider State

The mock provider owns two persistence concerns.

### Mock Provider Shipments

Each mock-provider shipment stores:

- A unique stable provider request key.
- A unique mock external shipment ID.
- The local shipment reference supplied in the request.
- The selected scenario.
- Provider status such as accepted, rejected, handoff confirmed, or delivered.
- Acceptance, confirmation, and delivery timestamps where applicable.
- Safe failure or status context without secrets.

Receiving the same provider request key again returns the existing mock-provider shipment. It never creates a second external identity.

### Mock Provider Outbound Events

Each outbound event stores:

- A unique external event ID.
- The mock external shipment ID and provider request key.
- Event type and immutable raw JSON body.
- Occurrence time and next delivery time.
- Delivery state, attempt count, last attempt time, and safe response/error context.
- The original event reference when it is an explicit replay.

The provider event inbox is the warehouse application's received-event history. Mock-provider outbound events are the provider's sending history. They are separate records because they represent opposite sides of the HTTP boundary.

## 4. Submission Lifecycle

The normal flow is:

1. The warehouse application durably creates or reuses a provider attempt and stable request key.
2. A queued submission job calls the mock provider outside inventory-locking transactions.
3. The mock provider creates or finds its external shipment using that key.
4. The mock provider selects the forced per-shipment scenario, or uses weighted random selection when no override exists.
5. It records the provider result and any future outbound event before responding.
6. The warehouse application records accepted, permanently failed, or uncertain state.
7. A mock-provider delivery job sends due callbacks to the configured application webhook URL over HTTP.
8. The webhook verifies HMAC and persists the event before queuing business processing.
9. Event processing performs the eligible shipment or delivery transition atomically.

No provider HTTP call runs while warehouse inventory rows are locked.

## 5. Supported Scenarios

| Scenario | Provider behavior | Local inventory before callback |
| --- | --- | --- |
| Random | Select a configured weighted scenario | Unchanged |
| Immediate success | Accept and make `shipment.confirmed` immediately due for HTTP delivery | Packed |
| Delayed success | Accept and schedule `shipment.confirmed` for later | Packed |
| Permanent failure | Reject and generate no confirmation | Packed |
| Timeout then success | Persist acceptance and a future confirmation, then simulate a lost/timed-out response | Packed and submission uncertain |
| Success with duplicate delivery | Confirm handoff, then send the same delivery event more than once | Deducted once at shipment confirmation |
| Out-of-order delivery | Send delivery before shipment confirmation | Packed; delivery event remains pending |

Random mode is the local default. A per-shipment override makes tests and demonstrations deterministic.

## 6. Actual HTTP Callback Delivery

For local demonstration, the mock provider sends an actual HTTP `POST` to the configured callback URL:

```text
POST /webhooks/shipping-provider
```

The raw JSON body contains at least:

- External event ID.
- Event type.
- External shipment ID.
- Provider request key.
- Event occurrence time.
- Shipment items and confirmed quantities.

The request carries provider, external event ID, timestamp, and HMAC signature headers. The signature covers:

```text
timestamp + "." + raw_request_body
```

Delivery behavior:

- A successful `2xx` response acknowledges the outbound event.
- Network errors, timeouts, `429`, and retryable server errors retain the outbound event for bounded retry.
- Retry sends the same event ID and raw body.
- A fresh request timestamp and matching signature may be generated for a later attempt so legitimate delivery is not rejected by the replay window.
- Non-retryable authentication, validation, or configuration failures are visible to the administrator and are not retried forever.
- Automated tests fake the outbound HTTP client; webhook route tests exercise the real receiving boundary without external network access.

## 7. Waiting, Delay, and Queue Execution

The provider never blocks a web request by sleeping.

- Every outbound event has a persisted `next_delivery_at`.
- Immediate confirmation sets it to now.
- Delayed confirmation sets it to a future time.
- “Send now” changes an eligible event to due now.
- After commit, due work is dispatched to the queue.
- A scheduled bounded sweeper rediscovers due events if dispatch was interrupted.
- The queue worker sends the actual callback HTTP request independently from the administrator's browser request.

Local demonstration therefore requires a non-synchronous queue connection, a running queue worker, and a callback URL reachable from that worker. The callback job must not make a loopback HTTP request while executing on Laravel's `sync` queue inside the original web request.

The UI shows scheduled, due, delivering, acknowledged, retryable-failed, and permanently-failed callback states so “waiting” is observable rather than hidden.

## 8. Timeout and Reconciliation

The required timeout scenario represents acceptance by the provider followed by loss of the response:

1. The mock provider creates the external shipment.
2. It schedules the future `shipment.confirmed` event.
3. The warehouse call times out and records an uncertain submission.
4. Packed and on-hand quantities remain unchanged.
5. Reconciliation queries the provider with the same stable request key.
6. Resubmission with that key also returns the same external shipment.
7. The later signed callback remains the only event allowed to mark the local shipment shipped.

Provider status lookup may resolve whether a submission was accepted or rejected, but it does not bypass the webhook-driven shipment transition. If the provider reports confirmed handoff and the callback is missing, the mock provider makes the existing confirmation event deliverable again.

## 9. Deterministic Demonstration Controls

The local/testing administrator interface provides:

- A per-shipment “next provider outcome” selector.
- “Send shipment confirmation now.”
- “Send delivery confirmation now.”
- “Replay last webhook.”
- “Send out-of-order delivery event.”

The same capabilities are available through guarded demonstration commands so the queue and failure scenarios can be reproduced without browser timing.

Rules:

- Controls exist only in local/testing environments.
- They operate on mock-provider state, not warehouse state.
- “Send shipment confirmation now” creates or releases a provider outbound event; it never calls the shipment-confirmation action directly.
- Ordinary delivery confirmation requires provider handoff confirmation.
- The explicit out-of-order control is allowed to violate that order so pending-event recovery can be demonstrated.
- Replay keeps the original external event ID and raw body, proving inbox and domain idempotency.
- Controls are rejected for a real provider adapter or outside allowed environments.

## 10. Duplicate and Retry Semantics

An exact duplicate is represented by the same external event ID and raw body.

- The mock provider may send it more than once.
- The application inbox unique constraint accepts only one provider/event identity.
- Every safe duplicate receives an idempotent success response.
- The shipment or delivery effect occurs once.

Transport retry and deliberate replay both use the same identity. The outbound delivery log distinguishes why an additional HTTP attempt occurred.

## 11. Recovery and Visibility

Scheduled recovery discovers:

- Due or retryable mock-provider outbound events.
- Uncertain local shipment submissions needing provider status lookup.
- Pending received provider events whose prerequisites may now exist.

The shipment detail and provider-event screens show:

- Local attempt and shipment status.
- Stable provider request key in a safe display form.
- Mock external shipment status.
- Outbound callback delivery state and attempt count.
- Received inbox processing state.
- Whether an outcome was randomly selected or explicitly forced.

Secrets, full signatures, and sensitive raw error responses are never rendered.
