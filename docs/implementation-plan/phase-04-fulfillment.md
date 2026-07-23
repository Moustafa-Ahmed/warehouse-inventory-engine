# Phase 4 — Fulfillment

## Objective

Implement the physical warehouse progression from confirmed reservation through packed shipment preparation, including explicit reversals and partial quantities.

## Commit P4.1 — `feat: pick confirmed reserved inventory`

Scope:

- Implement partial and full pick action.
- Allow only confirmed, open reservations.
- Move reserved to picked through the movement applicator.
- Update reservation and order-item projections and history atomically.
- Add invalid-state, over-pick, duplicate, and rollback tests.

Done when:

- Temporary, released, expired, or closed reservations cannot be picked.
- Picked quantity never exceeds confirmed reserved quantity.

## Commit P4.2 — `feat: return picked inventory to available stock`

Scope:

- Implement explicit picked-to-available return action.
- Require administrator actor and reason.
- Restore order-item outstanding demand unless separately cancelled.
- Append compensating movement and history.
- Add partial, over-return, duplicate, and cancellation interaction tests.

Done when:

- Cancelling a picked quantity without physical return is impossible.
- Returned stock becomes available only through this explicit action.

## Commit P4.3 — `feat: pack and unpack picked inventory`

Scope:

- Implement partial and full packing.
- Implement packed-to-picked unpacking.
- Prevent unpacking quantity already consumed by a confirmed shipment.
- Update projections and history through shared actions.
- Add over-pack, over-unpack, duplicate, and rollback tests.

Done when:

- Packed stock remains committed and unavailable.
- Unpacking alone does not make stock available.

## Commit P4.4 — `feat: create warehouse-scoped partial shipments`

Scope:

- Implement shipment creation from eligible packed reservation quantities.
- Require one warehouse per shipment.
- Support multiple items and partial item quantities.
- Reserve packed quantities against shipment items without deducting warehouse stock.
- Add over-allocation, cross-warehouse, duplicate, and partial-shipment tests.

Done when:

- A shipment cannot contain unpacked or differently warehoused stock.
- Multiple shipments can safely consume different parts of one order item.

## Commit P4.5 — `feat: calculate allocation and fulfillment progress`

Scope:

- Centralize order-item and order progress calculation.
- Track allocation, fulfillment, and delivery separately.
- Close reservations only when no active stage quantity remains.
- Mark item fulfilled when `shipped + cancelled = ordered`.
- Add table-driven tests for mixed partial states.

Done when:

- Every interface can query progress without reproducing formulas.
- Partial allocation cannot be mistaken for partial shipment.

## Commit P4.6 — `test: prove fulfillment quantity conservation`

Scope:

- Add lifecycle sequences covering partial pick, pack, ship preparation, release, return, and cancellation.
- Assert the order-item conservation equation after every step.
- Inject exceptions at stage boundaries.
- Verify every state change has one matching movement and history entry.

Done when:

- No tested sequence loses or duplicates quantity.
- Invalid transitions leave every projection unchanged.

## Phase Gate

- [ ] Pick, return, pack, and unpack actions pass.
- [ ] Shipment preparation supports partial quantities.
- [ ] Cross-warehouse shipment composition is rejected.
- [ ] Progress dimensions are independent and correct.
- [ ] Conservation tests cover normal and reversal flows.
- [ ] Full tests and Pint pass.
