# Inventory and Ledger Rules

## 1. Balance Boundary

There is one current inventory balance per product and warehouse. The unique business key is:

```text
product_id + warehouse_id
```

The balance projection contains mutually exclusive current buckets:

```text
available_quantity
reserved_quantity
picked_quantity
packed_quantity
```

Derived values:

```text
on_hand_quantity =
    available_quantity
    + reserved_quantity
    + picked_quantity
    + packed_quantity
```

Shipped and delivered quantities are not warehouse balance buckets because those goods are no longer physically held by the warehouse. They remain queryable through movements, reservations, order items, shipment items, and delivery events.

The movement ledger may classify an external destination as `shipped` while its warehouse reference is null. That movement-endpoint classification is distinct from the four warehouse balance buckets and must not be used as a mutable on-hand projection.

## 2. Balance Invariants

For every product and warehouse:

```text
available_quantity >= 0
reserved_quantity >= 0
picked_quantity >= 0
packed_quantity >= 0
```

Additional invariants:

- Quantities are whole, positive integers for this challenge.
- No operation may consume more than exists in its source bucket.
- A product/warehouse balance row is created before its first movement.
- Application validation and database constraints both protect non-negative values.
- Current balance updates and their movement records commit atomically.

## 3. Canonical Movement Ledger

The inventory movement ledger is append-only and canonical. A movement records:

- Unique movement identifier.
- Originating operation.
- Product.
- Positive quantity.
- Nullable source warehouse and source bucket.
- Nullable destination warehouse and destination bucket.
- Business reference type and identifier.
- Actor when initiated by an administrator.
- Timestamp and optional metadata.

Existing movement records are never edited or deleted. Corrections are new compensating movements.

### Movement Examples

| Operation | Source | Destination |
| --- | --- | --- |
| Receive stock | External | Cairo / Available |
| Reserve stock | Cairo / Available | Cairo / Reserved |
| Release stock | Cairo / Reserved | Cairo / Available |
| Pick stock | Cairo / Reserved | Cairo / Picked |
| Return picked stock | Cairo / Picked | Cairo / Available |
| Pack stock | Cairo / Picked | Cairo / Packed |
| Unpack stock | Cairo / Packed | Cairo / Picked |
| Transfer stock | Cairo / Available | Alexandria / Available |
| Confirm shipment | Cairo / Packed | External / Shipped |

## 4. Synchronous Projection

The balance projection is a fast operational view, not an independently editable source.

Every inventory mutation follows this sequence:

1. Begin a database transaction.
2. Claim or validate the idempotency operation; return its completed result before taking inventory locks when possible.
3. Resolve every affected balance row.
4. Lock rows with `FOR UPDATE` in ascending balance ID order.
5. Re-read the locked quantities.
6. Validate the operation against source-bucket quantities.
7. Append the canonical movement.
8. Apply the same movement to the locked projections.
9. Write related domain history.
10. Mark the operation completed with its original result.
11. Commit and release the locks.

External calls never occur while balance rows are locked.

## 5. Stock Receipt

Receiving stock moves quantity from an external source into the available bucket.

Rules:

- Quantity must be positive.
- Product and warehouse must be active.
- The operation must be idempotent.
- Receipt creates a movement and updates the balance atomically.
- After commit, an outstanding-allocation job is dispatched.
- The scheduled backorder allocator provides recovery if immediate dispatch is interrupted.

## 6. Adjustments

An adjustment is an explicit correction, not a replacement for normal lifecycle operations.

Rules:

- Every adjustment requires a reason and administrator identity.
- Positive adjustments move external stock into available.
- Negative adjustments move available stock to an external adjustment destination.
- A negative adjustment cannot consume reserved, picked, or packed stock.
- An adjustment cannot produce a negative available balance.
- Corrections to historical mistakes use compensating movements rather than ledger edits.

## 7. Warehouse Transfers

A transfer moves available inventory from one company warehouse to another.

Rules:

- Source and destination must be different, active warehouses.
- Product must be active.
- Quantity must be positive.
- Only source `available_quantity` can transfer.
- Reserved, picked, and packed quantities cannot transfer.
- Both balance rows are locked in deterministic ID order.
- One canonical movement identifies the source and destination.
- Both projections update in the same transaction.
- A duplicate transfer operation returns the original result.

Reservation reallocation between warehouses is not part of the core transfer operation.

## 8. Projection Reconciliation

Reconciliation is optional after core delivery:

1. Replay canonical movements in order.
2. Calculate expected bucket balances.
3. Compare expected balances with projections.
4. Report every mismatch and exit unsuccessfully.
5. Never silently overwrite a balance.

Any repair requires investigation and explicit compensating operations.
