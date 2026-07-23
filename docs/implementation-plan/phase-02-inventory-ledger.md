# Phase 2 — Inventory Ledger

## Objective

Implement the canonical movement ledger and synchronously locked balance projections before adding orders or fulfillment behavior.

## Commit P2.1 — `feat: enforce operation idempotency`

**Priority:** Submission-critical

Scope:

- Implement an operation coordinator that claims an idempotency key inside the caller’s transaction.
- Canonically hash the operation type and validated request.
- Return the original completed result for a repeated identical request.
- Reject the same key with a different payload.
- Handle concurrent claims through the database unique constraint.
- Add focused MySQL tests for identical replay, conflicting reuse, concurrent claim, and rollback; no separate unit test is required.

Done when:

- Duplicate requests cannot execute the protected callback twice.
- Conflicting reuse produces a domain-specific conflict.
- Rolled-back work does not leave a false completed result.

## Commit P2.2 — `feat: apply double-entry inventory movements`

**Priority:** Submission-critical

Scope:

- Implement one movement applicator for all bucket/location changes.
- Resolve and lock every affected balance in ascending ID order.
- Validate source quantity after locking.
- Append movement and update projections in one transaction.
- Support external source/destination boundaries.
- Add focused MySQL critical tests for conservation, non-negative source protection, rollback, and deterministic multi-row locking.

Done when:

- No caller can directly mutate balance quantities.
- Every projection change has exactly one canonical movement.
- Replaying movements yields the same expected bucket totals.

## Commit P2.3 — `feat: receive stock into available inventory`

**Priority:** Submission-critical

Scope:

- Implement the receive-stock command object, result, and action.
- Move external quantity into warehouse available stock.
- Require product, warehouse, positive quantity, source reference, and idempotency key.
- Persist administrator actor when present.
- Do not dispatch backorder work yet; the allocator and job are introduced in Phase 3.
- Add focused tests for duplicate receipt and atomic rollback; ordinary success is exercised by those scenarios and the smoke workflow.

Done when:

- Stock receipt is atomic and idempotent.
- Duplicate receipt cannot inflate inventory.
- No forward dependency on an unimplemented backorder job exists.

## Commit P2.4 — `feat: adjust available inventory with audit reasons`

**Priority:** Supporting

Scope:

- Implement positive and negative adjustment actions.
- Require an explicit reason and actor.
- Reject negative adjustments exceeding available quantity.
- Record compensating movements rather than editing history.
- Add one focused MySQL test proving a negative adjustment cannot consume committed stock; ordinary adjustment behavior can use the smoke workflow.

Done when:

- Adjustments cannot consume committed stock.
- Every correction has an operation, actor, and reason.

## Commit P2.5 — `feat: transfer available inventory between warehouses`

**Priority:** Submission-critical

Scope:

- Implement available-stock transfers.
- Reject identical source/destination warehouses.
- Lock source and destination balances in deterministic order.
- Move only source available quantity.
- Create one movement with both warehouse endpoints.
- Add focused MySQL tests for committed-stock protection and opposite-direction lock ordering; use the representative smoke flow for ordinary transfer success.

Done when:

- Transfers cannot move reserved, picked, or packed stock.
- Concurrent opposite-direction transfers do not corrupt or deadlock indefinitely.

## Commit P2.6 — `test: prove inventory transaction concurrency and rollback`

**Priority:** Submission-critical

Scope:

- Add dedicated MySQL integration tests using separate database connections or processes.
- Prove one source bucket cannot be consumed twice.
- Inject failures between ledger and projection writes.
- Keep this commit limited to the final-unit concurrency and atomic rollback risks.

Done when:

- The focused tests fail if the essential row lock or transaction boundary is removed.
- Projection and ledger remain consistent after injected exceptions.
- The test documentation briefly explains why these cases require MySQL.

## Phase Gate

- [ ] Balances are mutated only by the movement applicator.
- [ ] Receipt and transfer actions are idempotent.
- [ ] Supporting adjustment work is complete or explicitly recorded for the post-Phase-5 time review.
- [ ] Multi-row locks use deterministic order.
- [ ] Concurrency tests run against MySQL.
- [ ] Rollback tests prove atomic ledger/projection behavior.
- [ ] README and architecture documents describe the implemented ledger and locking behavior.
- [ ] Smoke and critical tests plus Pint pass.
