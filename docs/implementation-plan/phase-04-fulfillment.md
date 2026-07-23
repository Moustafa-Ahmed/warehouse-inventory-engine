# Phase 4 — Fulfillment and Shipment Preparation

## Objective

Implement physical progression from confirmed reservation through packed shipment preparation, including explicit reversals and partial quantities. Every action uses the progress calculator introduced in Phase 3.

## Commit P4.1 — `feat: pick confirmed reserved inventory`

**Priority:** Submission-critical

Scope:

- Implement partial and full pick action.
- Allow only confirmed, open reservations.
- Move reserved to picked through the movement applicator.
- Recalculate reservation and order-item progress through the shared calculator.
- Update history atomically.
- Add one focused invalid-state test; the normal path is covered by the fulfillment lifecycle test.

Done when:

- Temporary, released, expired, or closed reservations cannot be picked.
- Picked quantity never exceeds confirmed reserved quantity.

## Commit P4.2 — `feat: return picked inventory to available stock`

**Priority:** Supporting

Scope:

- Implement explicit picked-to-available return action.
- Require administrator actor and reason.
- Restore order-item outstanding demand unless separately cancelled.
- Append compensating movement and history.
- Recalculate progress through the shared calculator.
- Cover return and cancellation interaction in the shared fulfillment lifecycle test.

Done when:

- Cancelling a picked quantity without physical return is impossible.
- Returned stock becomes available only through this explicit action.

## Commit P4.3 — `feat: pack picked inventory`

**Priority:** Submission-critical

Scope:

- Implement partial and full packing.
- Move picked quantity to packed through the movement applicator.
- Recalculate progress through the shared calculator.
- Record the transition and operation atomically.
- Add one focused committed-quantity protection assertion to the lifecycle test.

Done when:

- Packed stock remains committed and unavailable.
- Packed quantity never exceeds currently picked quantity.

## Commit P4.4 — `feat: unpack eligible packed inventory`

**Priority:** Supporting

Scope:

- Implement packed-to-picked unpacking.
- Reject quantity assigned to an active or confirmed shipment.
- Require an explicit reason and operation key.
- Recalculate progress and append history through shared actions.
- Cover normal unpack and assigned-quantity rejection in the lifecycle test.

Done when:

- Unpacking alone does not make stock available.
- Shipment-assigned quantity cannot silently escape shipment preparation.

## Commit P4.5 — `feat: create warehouse-scoped partial shipments`

**Priority:** Submission-critical

Scope:

- Implement shipment creation from eligible packed reservation quantities.
- Require one warehouse per shipment.
- Support multiple items and partial item quantities.
- Assign packed quantities to shipment items without deducting warehouse stock.
- Prevent the same packed quantity from being assigned twice.
- Add one focused shipment-composition test covering packed-quantity and single-warehouse enforcement.

Done when:

- A shipment cannot contain unpacked or differently warehoused stock.
- Multiple shipments can safely consume different parts of one order item.
- Shipment creation does not deduct on-hand inventory.

## Commit P4.6 — `test: prove fulfillment quantity conservation`

**Priority:** Submission-critical

Scope:

- Add one submission-critical lifecycle covering reservation, partial release/cancellation, pick, pack, and shipment preparation.
- If the supporting reversal actions are implemented, extend the lifecycle with picked return and eligible unpacking.
- Assert the order-item conservation equation after each step.
- Inject one failure at a movement/transition boundary.
- Verify each state change has one matching movement and history entry.

Done when:

- No tested sequence loses or duplicates quantity.
- Invalid transitions leave every projection unchanged.
- Partial allocation cannot be mistaken for partial shipment.

## Phase Gate

- [ ] Pick and pack actions use the movement applicator and shared progress calculator
- [ ] Supporting return and unpack work is complete or explicitly recorded for the post-Phase-5 time review
- [ ] Shipment preparation supports partial quantities without inventory deduction
- [ ] Cross-warehouse and duplicate packed-quantity assignment are rejected
- [ ] Conservation tests cover the representative normal and reversal flow
- [ ] README, architecture, and AI-usage documents are current
- [ ] Smoke, focused lifecycle, and calculator tests plus Pint pass
