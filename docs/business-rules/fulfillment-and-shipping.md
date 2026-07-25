# Fulfillment and Shipping Rules

## 1. Fulfillment Sequence

The valid physical sequence is:

```text
Reserved -> Picked -> Packed -> Shipped -> Delivered
```

Reversal before shipment is explicit:

```text
Picked -> Available
Packed -> Picked -> Available
```

Shipped stock cannot be reversed through reservation cancellation.

## 2. Picking

Picking moves confirmed reserved quantity into the picked bucket.

Rules:

- Reservation must be confirmed and open.
- Pick quantity must be positive.
- Pick quantity cannot exceed the reservation’s currently reserved quantity.
- Inventory balance, reservation projection, movement, and history update atomically.
- Partial picking is allowed.
- Repeating the same pick operation has no additional effect.

## 3. Returning Picked Stock

A return-to-stock action moves picked quantity directly to available after the warehouse confirms that the item has returned to an available condition.

Rules:

- Quantity cannot exceed currently picked quantity.
- A reason and actor are required.
- It creates a compensating movement and reservation transition.
- It does not cancel the order item automatically; the quantity becomes outstanding allocation again unless separately cancelled.

## 4. Packing and Unpacking

Packing moves picked quantity into packed.

Rules:

- Quantity must be positive and cannot exceed picked quantity.
- Partial packing is allowed.
- Packed stock is committed and unavailable to other orders.

Unpacking moves packed quantity back to picked:

- Quantity cannot exceed packed quantity not already assigned to a confirmed shipment.
- A later return-to-stock action is required before it becomes available.

## 5. Shipment Composition

Shipments are created only from packed quantities.

Rules:

- Every shipment belongs to exactly one order and warehouse.
- Every shipment item references its source reservation and a positive quantity.
- The reservation provides the shipment item's order item, product, and warehouse relationships; `ShipmentItem` does not duplicate `order_item_id`.
- Every shipment item reservation must belong to the shipment's order and warehouse.
- One order can have multiple shipments.
- One order item can be represented through multiple reservations and split across shipments.
- One shipment can contain multiple order items from the same warehouse.
- A shipment item's quantity cannot exceed its reservation's packed quantity not already assigned to another pending shipment.
- A new shipment starts in `pending_handoff`.
- Creating a shipment does not reduce warehouse on-hand stock.

## 6. Shipment Submission

A pending shipment is submitted asynchronously.

Rules:

- The processing command discovers eligible pending shipments.
- A queued job calls the provider outside database transactions that lock inventory.
- Every provider request uses a stable request key.
- Provider submissions are recorded independently from the shipment’s business state.
- A duplicate job must not create another external shipment.
- An accepted provider response records acceptance only; it does not mark the shipment shipped.
- Provider submission acceptance, failure, and unknown outcomes do not replace the shipment's `pending_handoff` state.
- The shipment is marked shipped only after a valid `shipment.confirmed` webhook is persisted as a `ProviderWebhookReceipt` and processed.

## 7. Provider Outcomes

### Immediate or Delayed Acceptance

The provider may accept immediately and make its confirmation callback due immediately, or schedule it for later. In both cases, inventory remains packed until the signed callback is received and processed.

### Timeout

A timeout means the external outcome is unknown:

- The provider submission outcome becomes unknown.
- Packed and on-hand balances do not change.
- Retry, provider status lookup, or reconciliation reuses the same provider request key.
- A later callback may resolve the state.
- The required demonstration scenario allows the provider to have accepted and scheduled the callback before the caller experiences the timeout.

### Permanent Failure

- The provider submission is permanently failed.
- Warehouse inventory remains packed.
- No inventory deduction occurs.
- A new provider submission may be created.
- Releasing the stock requires explicit unpack and return operations.

## 8. Shipment Confirmation

A valid `shipment.confirmed` webhook moves stock:

```text
Warehouse / Packed -> External / Shipped
```

The confirmation transaction:

1. Claims the provider webhook receipt.
2. Locks the shipment, affected reservations, and inventory balance rows.
3. Validates the shipment is `pending_handoff` and the callback confirms the complete composed shipment.
4. Appends the canonical movement.
5. Reduces packed projection quantity.
6. Increases shipped progress on reservation and order-item projections using each shipment item's quantity.
7. Marks the shipment `shipped`; `ShipmentItem` does not duplicate shipped quantity.
8. Appends transition history.
9. Marks the webhook receipt processed.
10. Commits atomically.

Repeating confirmation cannot deduct stock twice.

Provider status lookup cannot run this transaction directly. If reconciliation discovers confirmed handoff but the callback was lost, the provider must redeliver its existing signed confirmation webhook.

## 9. Delivery

Delivery is a fulfillment event, not a warehouse inventory movement.

Rules:

- Delivery can advance only after the shipment is `shipped`.
- Each shipment item's delivered quantity must remain between zero and its quantity.
- Delivery may be partial.
- Duplicate delivery webhooks do not repeat effects.
- Shipment delivery progress is derived from shipment-item quantities and delivered quantities, not another shipment status.
- An order becomes delivered only when every shipped quantity is delivered.
- A delivery failure does not place goods back into warehouse inventory.

## 10. Completion Rules

An order item is fulfilled when:

```text
shipped_quantity + cancelled_quantity = ordered_quantity
```

A reservation closes when:

```text
reserved_quantity + picked_quantity + packed_quantity = 0
```

and all quantity originally handled by it is either shipped or released.

An order is shipped when every non-cancelled item is fully shipped. It is delivered when all shipped quantities are delivered.

## 11. Returns Boundary

A production returns workflow would require:

- Return merchandise authorization.
- Carrier return tracking.
- Warehouse receiving.
- Inspection and condition grading.
- Quarantine or damage handling.
- Restocking through a new receipt movement.

This workflow is a documented future improvement and is not part of the core challenge implementation.
