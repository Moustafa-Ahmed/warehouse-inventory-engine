# Phase 3 — Orders and Reservations

## Objective

Implement order demand, immediate hard reservations, explicit partial results, expiration, releases, and automatic backorder recovery.

## Commit P3.1 — `feat: create and edit order demand`

Scope:

- Implement actions to create orders and order items.
- Implement delta-based quantity edits.
- Prevent reductions below shipped and cancelled quantity.
- Reject reductions that require picked or packed reversal.
- Recalculate allocation and fulfillment projections consistently.
- Add tests for increases, eligible decreases, and invalid reductions.

Done when:

- Existing commitments are not released and recreated during edits.
- Quantity conservation holds after every valid edit.

## Commit P3.2 — `feat: reserve available inventory with partial results`

Scope:

- Implement warehouse-scoped reservation action.
- Lock the selected balance before calculating availability.
- Allocate `min(available, outstanding)` quantity.
- Return requested, allocated, remaining, and fully-allocated indicators.
- Create no movement when zero can be allocated.
- Update reservation, order item, ledger, projection, operation, and history atomically.
- Add full, partial, zero, duplicate, and rollback tests.

Done when:

- Partial allocation is explicit and queryable.
- One quantity cannot be reserved twice.
- Zero allocation does not create misleading stock movement.

## Commit P3.3 — `feat: release eligible reserved inventory`

Scope:

- Implement partial and full reservation release.
- Move only reserved quantity back to available.
- Require a reason and operation key.
- Update outstanding order-item demand unless the quantity is also cancelled.
- Make cancellation and release intent explicit.
- Add tests for partial release, cancellation, over-release, and duplicate execution.

Done when:

- Picked, packed, and shipped quantities cannot be released through this action.
- Released-but-not-cancelled demand becomes allocatable again.

## Commit P3.4 — `feat: confirm and expire temporary reservations`

Scope:

- Implement temporary reservation confirmation.
- Implement expiration action for eligible expired holds.
- Add `reservations:expire` command that processes bounded batches.
- Release stock using the normal movement and release rules.
- Make command execution idempotent and safe under overlap.
- Add time-controlled tests.

Done when:

- Confirmed reservations do not expire.
- Repeated expiration runs do not release twice.
- Expiration history and movements are complete.

## Commit P3.5 — `feat: allocate outstanding order items`

Scope:

- Implement FIFO selection of eligible outstanding order items.
- Implement `AllocateBackorderJob`.
- Implement `inventory:allocate-backorders`.
- Call the same reservation action used by other entry points.
- Dispatch after stock receipt commit.
- Process in bounded batches and remain safe under duplicate jobs.
- Add priority, eligibility, partial, and recovery tests.

Done when:

- New stock can complete a prior partial allocation.
- A lost immediate dispatch is recoverable by the command.
- Duplicate allocator execution cannot over-allocate.

## Commit P3.6 — `test: prove reservation races and retries`

Scope:

- Add separate-connection test for two users reserving the final unit.
- Add concurrent duplicate-idempotency test.
- Add order-edit versus reservation concurrency test.
- Add expiration versus confirmation race test.
- Assert movement and history counts, not only final balances.

Done when:

- Exactly one competing operation consumes the final quantity.
- Every race produces one valid, explainable final state.

## Stretch Commit P3.S1 — `feat: allocate order items across warehouses`

Begin only after all required gates pass and remaining time is approved.

Scope:

- Add warehouse ranking policy.
- Lock all candidate balances in deterministic order.
- Create multiple warehouse reservations for one item.
- Preserve explicit partial results.
- Add multi-warehouse concurrency and partial-shipment tests.

Done when:

- No schema rewrite is required.
- The system remains correct when candidate warehouse stock changes concurrently.

## Phase Gate

- [ ] Full, partial, and zero reservation behavior passes.
- [ ] Outstanding demand remains explicit.
- [ ] Backorders recover through job and command paths.
- [ ] Temporary expiration is safe and idempotent.
- [ ] Final-unit concurrency test passes on MySQL.
- [ ] Full tests and Pint pass.
