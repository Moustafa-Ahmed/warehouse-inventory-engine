# Phase 3 — Orders and Reservations

## Objective

Implement shared quantity calculation, order demand, immediate hard reservations, explicit partial results, release/edit behavior, expiration, and automatic backorder recovery in dependency order.

## Commit P3.1 — `feat: calculate order item quantity progress`

**Priority:** Submission-critical

Scope:

- Implement one pure order-item quantity/progress calculator.
- Calculate ordered, cancelled, outstanding, reserved, picked, packed, shipped, and delivered relationships.
- Keep allocation, fulfillment, and delivery as separate dimensions.
- Enforce the quantity-conservation equation.
- Add a small table-driven unit test for the important empty, partial, and terminal combinations.

Done when:

- Later order, reservation, fulfillment, shipment, and UI code can call one calculator.
- No service method needs temporary or duplicated progress formulas.
- Partial allocation cannot be mistaken for partial shipment or delivery.

## Commit P3.2 — `feat: create order demand`

**Priority:** Submission-critical

Scope:

- Implement order and order-item creation through `OrderService`.
- Validate positive ordered quantities and product eligibility.
- Initialize projections through the shared progress calculator.
- Add factories for open, partial, and terminal order demand.
- Add order creation to the representative smoke flow.

Done when:

- One order can contain multiple product lines.
- New demand begins entirely outstanding and conserves quantity.

## Commit P3.3 — `feat: reserve available inventory with partial results`

**Priority:** Submission-critical

Scope:

- Implement warehouse-scoped reservation through `ReservationService::reserve()`.
- Lock the selected balance before calculating availability.
- Allocate `min(available, outstanding)` quantity.
- Return requested, allocated, outstanding, and fully-allocated indicators.
- Create no movement when zero can be allocated.
- Recalculate progress using the shared calculator.
- Update reservation, order item, ledger, projection, operation, and history atomically.
- Add focused critical tests for partial allocation, zero availability, idempotent replay, and rollback.

Done when:

- Partial allocation is explicit and queryable.
- One quantity cannot be reserved twice.
- Zero allocation does not create a misleading stock movement.

## Commit P3.4 — `feat: release and cancel eligible reserved inventory`

**Priority:** Submission-critical

Scope:

- Implement partial and full reservation release.
- Move only reserved quantity back to available.
- Distinguish release-with-outstanding-demand from release-with-cancellation.
- Require a reason and operation key.
- Recalculate order-item progress through the shared calculator.
- Add focused critical tests for eligible release, committed-stage protection, and duplicate execution.

Done when:

- Picked, packed, and shipped quantities cannot be released through this service method.
- Released-but-not-cancelled demand becomes allocatable again.
- Cancelled quantity no longer appears as outstanding demand.

## Commit P3.5 — `feat: edit order demand by quantity delta`

**Priority:** Submission-critical

Scope:

- Implement delta-based quantity edits.
- Treat an increase as new outstanding demand.
- Use the existing `ReservationService` release/cancellation method for an eligible decrease.
- Prevent reductions below shipped and cancelled quantity.
- Reject reductions that require picked or packed physical reversal.
- Recalculate progress through the shared calculator.
- Add one focused conservation scenario covering a valid delta and a reduction requiring physical reversal.

Done when:

- Existing commitments are not released and recreated during edits.
- `OrderService` does not contain a second release implementation.
- Quantity conservation holds after every valid edit.

## Commit P3.6 — `feat: allocate outstanding order items after stock receipt`

**Priority:** Submission-critical

Scope:

- Implement FIFO selection of eligible outstanding order items.
- Implement `AllocateBackorderJob` as a thin adapter over `ReservationService`.
- Implement `inventory:allocate-backorders`.
- Wire stock receipt to dispatch the new job only after commit.
- Keep the scheduled command as the recovery path if dispatch is lost.
- Process bounded batches and remain safe under duplicate jobs.
- Add one focused recovery test proving stock receipt completes the oldest eligible partial allocation without over-allocation.

Done when:

- New stock can complete a prior partial allocation.
- The job exists before receipt dispatch is wired.
- A lost immediate dispatch is recoverable by the command.
- Duplicate allocator execution cannot over-allocate.

## Commit P3.7 — `test: prove reservation concurrency and repeated execution`

**Priority:** Submission-critical

Scope:

- Add a separate-connection MySQL test for two users reserving the final unit.
- Add a concurrent duplicate-idempotency test.
- Assert movement and history counts, not only final balances.

Done when:

- Exactly one competing operation consumes the final quantity.
- Repeating the same intent does not create another effect.
- Every tested race produces one valid, explainable final state.

## Commit P3.8 — `feat: confirm and expire temporary reservations`

**Priority:** Supporting

Scope:

- Implement temporary reservation confirmation.
- Implement expiration for eligible expired holds through `ReservationService::expire()`.
- Add `reservations:expire` command that processes bounded batches.
- Release stock through the existing `ReservationService` method.
- Make command execution idempotent and safe under overlap.
- Add one time-controlled test proving an expired temporary hold releases once while a confirmed reservation remains.

Done when:

- Confirmed reservations do not expire.
- Repeated expiration runs do not release twice.
- Expiration history and movements are complete.

## Stretch Commit P3.S1 — `feat: allocate order items across warehouses`

**Priority:** Optional/stretch

Begin only after all submission-critical gates pass and remaining time is approved.

Scope:

- Add warehouse ranking policy.
- Lock all candidate balances in deterministic order.
- Create multiple warehouse reservations for one item.
- Preserve explicit partial results.
- Add one focused cross-warehouse concurrency scenario rather than exhaustive combinations.

Done when:

- No schema rewrite is required.
- The system remains correct when candidate warehouse stock changes concurrently.

## Phase Gate

- [ ] One shared quantity/progress calculator is used by order and reservation services
- [ ] Full, partial, and zero reservation behavior passes
- [ ] Release and cancellation do not touch physically progressed stock
- [ ] Order edits reuse release behavior and conserve quantity
- [ ] Outstanding demand remains explicit
- [ ] Backorders recover through job and command paths wired after implementation
- [ ] Supporting temporary confirmation/expiration is complete or explicitly recorded for the post-Phase-5 time review
- [ ] Final-unit concurrency test passes on MySQL
- [ ] Smoke and critical tests plus Pint pass
