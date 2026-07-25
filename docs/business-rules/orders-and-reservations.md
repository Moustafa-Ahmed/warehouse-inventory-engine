# Orders and Reservation Rules

## 1. Order Model

An order contains one or more order items. Each item identifies a product and ordered quantity. Multiple reservations may allocate one order item, including reservations from different warehouses if the stretch allocation behavior is implemented later.

Order-item progress is represented in separate dimensions:

- Allocation progress.
- Fulfillment progress.
- Delivery progress.

A single overloaded status must not hide these dimensions.

## 2. Quantity Accounting

For an order item:

```text
remaining_to_allocate =
    ordered_quantity
    - cancelled_quantity
    - reserved_quantity
    - picked_quantity
    - packed_quantity
    - shipped_quantity
```

The stage quantities are aggregated across every reservation for that item.

Required invariants:

```text
remaining_to_allocate >= 0

ordered_quantity =
    cancelled_quantity
    + remaining_to_allocate
    + reserved_quantity
    + picked_quantity
    + packed_quantity
    + shipped_quantity
```

Delivered is a subset of shipped, so it is not added to this conservation equation.

## 3. Reservation Behavior

Reservations are immediate hard commitments.

When an order requests 10 and only 6 are available:

```text
requested_quantity = 10
allocated_quantity = 6
remaining_quantity = 4
is_fully_reserved = false
allocation_status = partially_allocated
```

The command succeeds with an explicit partial result. It must never report full reservation.

If zero is available:

- No inventory movement is created.
- The order item remains unallocated.
- The full quantity remains outstanding.
- The operation result still records that zero was allocated.

## 4. Warehouse Selection

Core implementation:

- The caller selects the warehouse for an allocation attempt.
- The allocator uses only that warehouse.
- Partial allocation is allowed.

Schema requirement:

- An order item may have multiple reservations.
- Every reservation belongs to one warehouse.
- The schema must not prevent a later cross-warehouse allocator.

Stretch behavior:

- Search multiple warehouses.
- Lock candidate balances in deterministic order.
- Allocate across warehouses until demand is met or stock is exhausted.

## 5. Backorder Fulfillment

Outstanding demand is never hidden or discarded.

Automatic recovery uses:

1. An after-commit job when stock is received.
2. A scheduled `inventory:allocate-backorders` command as a safety net.
3. The same reservation service used by authenticated web requests and any future API.

Allocation priority is FIFO by eligible order-item creation time.

The allocator skips:

- Cancelled items.
- Fully allocated items.
- Fulfilled items.
- Orders that are no longer eligible for fulfillment.
- Temporary holds that have expired.

Every retry uses its own deterministic operation context so repeated jobs remain safe.

## 6. Temporary and Confirmed Reservations

Temporary reservations:

- Have an expiration time.
- May transition to confirmed before expiration.
- Release eligible reserved quantity when they expire.
- Record release movements and reservation history.

Confirmed reservations:

- Do not expire automatically.
- Remain committed until fulfilled, explicitly released, or reversed through valid warehouse operations.

Only confirmed commitments may progress into warehouse fulfillment. This prevents an expiring temporary hold from being picked or packed.

## 7. Order Edits

Edits apply only the delta.

### Quantity Increase

Changing 10 to 14:

- Adds four units of outstanding demand.
- Attempts allocation through the normal reservation service when requested.
- Does not release or recreate existing commitments.

### Quantity Decrease

Changing 10 to 7:

- Attempts to remove three units.
- Releases reserved quantities first.
- Cannot directly remove picked or packed quantities.
- Cannot reduce below `shipped + cancelled`.
- Fails clearly when the requested reduction requires a physical reversal first.

Product replacement is not an in-place item edit. It requires cancelling eligible quantity on the old item and adding a new order item.

## 8. Release and Cancellation

Immediate release is allowed only from the reserved bucket:

```text
Reserved -> Available
```

Picked and packed quantities require physical reversal:

```text
Picked -> Available

Packed -> Picked -> Available
```

Shipped quantities cannot be cancelled.

Partial cancellation is supported. The order item remains active while any non-cancelled quantity is outstanding or progressing through fulfillment.

## 9. Reservation History

Every reservation transition records:

- Reservation.
- Previous and new state.
- Stage quantities before and after.
- Operation.
- Actor or system source.
- Reason.
- Timestamp.

History is append-only. Repeated idempotent calls do not create duplicate transitions.
