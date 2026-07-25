# Phase 2 — Inventory Ledger

## Objective

Implement the canonical movement ledger and synchronously locked balance projections before adding orders or fulfillment behavior.

## Commit P2.1 — `feat: enforce operation idempotency`

**Priority:** Submission-critical

Scope:

- Implement `OperationService` to claim an idempotency key inside the caller service’s transaction.
- Introduce the operation-type enum as part of the coordinator's typed contract, starting only with the receipt operation used by the next implementation task. Extend it alongside each later service rather than predicting every future mutation.
- Introduce the idempotency-conflict exception here because `OperationService` is its first real consumer.
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

- Implement `InventoryMovementService` as the one movement applicator for all bucket/location changes.
- Introduce the insufficient-source-quantity exception here because the movement applicator has the locked source context required to populate it correctly.
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

- Add native `final readonly` receive-stock input/result DTOs under `app/DTOs/Inventory` and implement `InventoryService::receive()`.
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

- Implement positive and negative adjustments through `InventoryService::adjust()`.
- Add only the readonly adjustment DTOs needed to keep the service signature explicit, and extend the operation-type enum with the adjustment case.
- Require an idempotency key and execute the adjustment through `OperationService`.
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

- Implement available-stock transfers through `InventoryService::transfer()`.
- Add only the readonly transfer DTOs needed to keep the service signature explicit, and extend the operation-type enum with the transfer case.
- Require an idempotency key and execute the transfer through `OperationService`.
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
- [ ] Receipt and transfer service methods are idempotent.
- [ ] Supporting adjustment work is complete or explicitly recorded for the post-Phase-5 time review.
- [ ] Multi-row locks use deterministic order.
- [ ] Concurrency tests run against MySQL.
- [ ] Rollback tests prove atomic ledger/projection behavior.
- [ ] Smoke and critical tests plus Pint pass.
