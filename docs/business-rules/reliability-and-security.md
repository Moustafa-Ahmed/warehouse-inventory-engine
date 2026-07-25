# Reliability and Security Rules

## 1. Pessimistic Locking

Inventory decisions use MySQL row locks inside short transactions.

Rules:

- Lock the current balance before validating source quantity.
- Re-read values after acquiring the lock.
- Lock multiple balances in ascending balance ID order.
- Keep transactions free of provider calls and other slow external work.
- Locks remain held until commit or rollback.
- Deadlock victims may retry the complete transaction a bounded number of times.
- Lock-wait timeouts are reported rather than treated as business rejection.

Ordinary cache or queue locks may reduce duplicate work, but they do not replace database correctness.

## 2. Idempotent Operations

Every mutation has a central operation record containing:

- Operation type.
- Idempotency key.
- Canonical request hash.
- Processing status.
- Original result or resulting entity reference.
- Failure information where appropriate.
- Completion timestamp.

Rules:

- First use of a key executes the operation.
- Same key, type, and payload returns the original result.
- Same key with a different payload is rejected as a conflict.
- Concurrent attempts are resolved by a database unique constraint.
- A rolled-back transaction must not leave a completed operation.
- Every related movement and transition references the operation.

Queue uniqueness is an efficiency mechanism. Database idempotency is the correctness mechanism.

## 3. Transaction Failure

If any write inside an inventory operation fails:

- Balance projection changes roll back.
- Movement inserts roll back.
- Reservation or shipment changes roll back.
- History changes roll back.
- Database locks release.
- The operation may be retried safely with the same key.

No business operation may catch and suppress an exception while committing partial state.

## 4. Jobs and Recovery

Jobs are at-least-once and must be safe under duplicate execution.

Rules:

- Dispatch domain follow-up work after database commit.
- Set explicit job timeouts.
- Queue `retry_after` must exceed the job timeout.
- Retry transient failures with bounded exponential backoff.
- Do not retry permanent provider failures.
- Persist recoverable pending state before dispatch.
- Scheduled sweepers rediscover work if immediate dispatch is interrupted.
- Job failure handlers record useful context.

Core recovery paths:

- Outstanding order items are discoverable by the backorder allocator.
- Pending shipments are discoverable by the shipment command.
- Provider submissions with unknown outcomes are discoverable by the provider-submission reconciliation command.
- Due and retryable mock-provider webhooks, plus `delivering` attempts whose configured claim lease has expired, are discoverable by the mock-provider dispatcher.
- Pending provider webhook receipts are discoverable by the provider-webhook command.
- Temporary reservations are discoverable by the expiration command.

## 5. Provider Webhook Receipts

Callbacks are persisted as provider webhook receipts before business processing.

The unique identity is:

```text
provider + external_event_id
```

The owner-approved processing states are:

- Pending.
- Processed.
- Ignored as stale.
- Retryable failure.
- Failed permanently.

Rules:

- Only duplicates with the same provider, external event ID, and identical raw body bytes are acknowledged successfully.
- A provider/external-event identity collision with different raw body bytes is rejected as a non-retryable conflict; the original receipt remains unchanged.
- A duplicate does not create another movement or transition.
- Valid next-state webhooks process immediately or through a job.
- A webhook ahead of the shipment state remains pending.
- A stale webhook receipt is stored and ignored.
- Provider delivery order is never assumed.

## 6. HMAC Webhook Verification

The provider signs:

```text
timestamp + "." + raw_request_body
```

The webhook endpoint verifies:

1. Required provider, event ID, timestamp, and signature headers.
2. Timestamp is inside the configured replay window.
3. HMAC using the configured provider secret.
4. Signature comparison uses a timing-safe function.
5. JSON structure and supported event type.
6. Webhook-receipt uniqueness before processing.

Secrets are read through configuration and are never committed.

The local mock provider sends callbacks to the configured webhook URL over actual HTTP. It persists the mock-provider webhook before sending, retries transient transport failures with the same external event ID and raw body, and records delivery attempts. Tests fake outbound HTTP so the suite does not depend on a running server.

A provider status response can reconcile submission state but cannot mark inventory shipped. Shipment deduction remains exclusively webhook-driven.

## 7. Application Security

The challenge implementation uses one administrator role.

Rules:

- All operational UI routes require authentication.
- All operational web mutations require authentication and authorization.
- Form Requests validate and authorize input.
- Controllers remain thin and call application services.
- Models whitelist mass-assignable attributes.
- Blade escapes untrusted output.
- Browser forms use CSRF protection.
- Login and webhook endpoints are rate limited appropriately.
- Every authenticated business-mutation form carries an operation key so browser retries and duplicate submissions reuse domain idempotency.
- Successful business mutations use post/redirect/get and display the stored operation result.
- JavaScript and jQuery may enhance usability but may not be required to enforce a business rule.
- Provider payloads are treated as untrusted input even after signature verification.
- Sensitive values are excluded from logs, browser output, webhook responses, and serialized job payloads.
- Actor identity is recorded for manual inventory changes.

## 8. Observability

Failures should include structured context:

- Operation key and type.
- Product and warehouse identifiers.
- Order, reservation, shipment, provider-submission, or provider-webhook-receipt identifiers.
- Job attempt.
- Provider request key.
- Exception class without leaked secrets.

Operational screens expose pending work, unknown outcomes, and failures without allowing silent data repair.
