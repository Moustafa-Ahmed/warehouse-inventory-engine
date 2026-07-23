# Phase 2 — Inventory Ledger

## Objective

Implement the canonical movement ledger and synchronously locked balance projections before adding orders or fulfillment behavior.

## Commit P2.1 — `feat: enforce operation idempotency`

Scope:

- Implement an operation coordinator that claims an idempotency key inside the caller’s transaction.
- Canonically hash the operation type and validated request.
- Return the original completed result for a repeated identical request.
- Reject the same key with a different payload.
- Handle concurrent claims through the database unique constraint.
- Add unit and MySQL integration tests.

Done when:

- Duplicate requests cannot execute the protected callback twice.
- Conflicting reuse produces a domain-specific conflict.
- Rolled-back work does not leave a false completed result.

## Commit P2.2 — `feat: apply double-entry inventory movements`

Scope:

- Implement one movement applicator for all bucket/location changes.
- Resolve and lock every affected balance in ascending ID order.
- Validate source quantity after locking.
- Append movement and update projections in one transaction.
- Support external source/destination boundaries.
- Add conservation, non-negative, rollback, and lock-order tests.

Done when:

- No caller can directly mutate balance quantities.
- Every projection change has exactly one canonical movement.
- Replaying movements yields the same expected bucket totals.

## Commit P2.3 — `feat: receive stock into available inventory`

Scope:

- Implement the receive-stock command object, result, and action.
- Move external quantity into warehouse available stock.
- Require product, warehouse, positive quantity, source reference, and idempotency key.
- Persist administrator actor when present.
- Dispatch outstanding-allocation follow-up after commit.
- Add success, duplicate, invalid, and rollback tests.

Done when:

- Stock receipt is atomic and idempotent.
- Duplicate receipt cannot inflate inventory.
- Follow-up work never runs before commit.

## Commit P2.4 — `feat: adjust available inventory with audit reasons`

Scope:

- Implement positive and negative adjustment actions.
- Require an explicit reason and actor.
- Reject negative adjustments exceeding available quantity.
- Record compensating movements rather than editing history.
- Add authorization-independent domain tests and integration tests.

Done when:

- Adjustments cannot consume committed stock.
- Every correction has an operation, actor, and reason.

## Commit P2.5 — `feat: transfer available inventory between warehouses`

Scope:

- Implement available-stock transfers.
- Reject identical source/destination warehouses.
- Lock source and destination balances in deterministic order.
- Move only source available quantity.
- Create one movement with both warehouse endpoints.
- Add duplicate, insufficient, rollback, and opposite-direction concurrency tests.

Done when:

- Transfers cannot move reserved, picked, or packed stock.
- Concurrent opposite-direction transfers do not corrupt or deadlock indefinitely.

## Commit P2.6 — `test: prove inventory transaction concurrency and rollback`

Scope:

- Add dedicated MySQL integration tests using separate database connections or processes.
- Prove one source bucket cannot be consumed twice.
- Prove lock waits observe committed state.
- Prove bounded deadlock retry behavior where reproducible.
- Inject failures between ledger and projection writes.

Done when:

- Tests fail if `lockForUpdate()` or transaction boundaries are removed.
- Projection and ledger remain consistent after injected exceptions.
- The test documentation explains why SQLite is insufficient.

## Phase Gate

- [ ] Balances are mutated only by the movement applicator.
- [ ] Receipt, adjustment, and transfer actions are idempotent.
- [ ] Multi-row locks use deterministic order.
- [ ] Concurrency tests run against MySQL.
- [ ] Rollback tests prove atomic ledger/projection behavior.
- [ ] Full tests and Pint pass.
