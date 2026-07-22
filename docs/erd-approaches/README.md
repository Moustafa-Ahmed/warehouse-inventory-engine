# Warehouse Inventory Reservation Engine — Five ERD Approaches

This document explores **five distinct database design strategies** for the Inventory Reservation Engine. Each approach makes different trade-offs between concurrency safety, complexity, traceability, and performance. Below is a summary of all five; click through to each for the full analysis.

Each approach includes an **interactive ERD diagram** in `.excalidraw` format — open these files in VS Code with the **Excalidraw** extension to explore the schema visually.

| #   | Approach                                                            | Excalidraw File                                                                |
| --- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| 1   | [Optimistic Locking](./01-optimistic-locking.md)                    | [`01-optimistic-locking.excalidraw`](./01-optimistic-locking.excalidraw)       |
| 2   | [Pessimistic Locking & State Machine](./02-pessimistic-locking.md)  | [`02-pessimistic-locking.excalidraw`](./02-pessimistic-locking.excalidraw)     |
| 3   | [Event Sourcing / Ledger](./03-event-sourcing.md)                   | [`03-event-sourcing.excalidraw`](./03-event-sourcing.excalidraw)               |
| 4   | [Two-Phase Reservation with Timeout](./04-two-phase-reservation.md) | [`04-two-phase-reservation.excalidraw`](./04-two-phase-reservation.excalidraw) |
| 5   | [Discrete Unit / Serial Tracking](./05-discrete-units.md)           | [`05-discrete-units.excalidraw`](./05-discrete-units.excalidraw)               |

---

## Approach 1 — [Optimistic Locking](./01-optimistic-locking.md)

**Core Idea:** Every inventory record carries a `version` column. Before updating, the application checks that the version has not changed. If it has, the operation is retried.

| Strengths               | Weaknesses                             |
| ----------------------- | -------------------------------------- |
| Simple to implement     | Requires application-level retry logic |
| No database locks held  | Can degrade under high contention      |
| Works with any database | Retries may cause unexpected failures  |
| Reads are never blocked | Not ideal for hotly contested items    |

**Best for:** Low-to-moderate concurrency environments. Systems where conflicts are rare.

---

## Approach 2 — [Pessimistic Locking & State Machine](./02-pessimistic-locking.md)

**Core Idea:** Inventory records are locked at the database level (`SELECT ... FOR UPDATE`) within transactions. A strict `status` state machine governs valid transitions.

| Strengths                        | Weaknesses                              |
| -------------------------------- | --------------------------------------- |
| Strongest consistency guarantees | Locks can cause deadlocks under load    |
| Predictable behaviour            | Read performance degrades during writes |
| Easy to reason about             | Requires careful transaction scoping    |
| No retry logic needed            | Not suitable for distributed databases  |

**Best for:** High-value inventory where correctness trumps throughput. Systems with moderate write contention.

---

## Approach 3 — [Event Sourcing / Ledger](./03-event-sourcing.md)

**Core Idea:** All inventory changes are **append-only events** in an immutable ledger. Current stock is derived by summing ledger entries. No record is ever updated in place.

| Strengths                             | Weaknesses                                    |
| ------------------------------------- | --------------------------------------------- |
| Complete audit trail                  | Current-state queries require aggregation     |
| No locking required                   | Storage grows indefinitely (needs compaction) |
| Naturally handles retries/idempotency | Higher read latency for stock levels          |
| Perfect traceability                  | Snapshot tables often needed for performance  |

**Best for:** Regulated environments requiring full audit trails. Systems where traceability is more important than read performance.

---

## Approach 4 — [Two-Phase Reservation with Timeout](./04-two-phase-reservation.md)

**Core Idea:** Reservations have a **two-phase lifecycle**: `pending` → `confirmed` (or `expired`). Pending reservations are "soft holds" that expire after a configurable timeout, preventing deadlocks from abandoned reservations.

| Strengths                            | Weaknesses                                 |
| ------------------------------------ | ------------------------------------------ |
| Graceful handling of abandoned carts | Increased schema complexity                |
| Configurable timeout policies        | Expired reservations must be reconciled    |
| Prevents indefinite locks            | Background jobs required for cleanup       |
| Better UX for shopping flows         | Users may lose a reservation if it expires |

**Best for:** E-commerce, sales-order workflows, or any system where users "hold" inventory before finalizing.

---

## Approach 5 — [Discrete Unit / Serial Tracking](./05-discrete-units.md)

**Core Idea:** Inventory is tracked in **individual, identifiable units** (by serial number, lot, bin location, etc.). Each unit moves independently through its lifecycle.

| Strengths                    | Weaknesses                             |
| ---------------------------- | -------------------------------------- |
| Maximum granularity          | Complex schema and queries             |
| Perfect for serialized goods | Higher storage overhead                |
| No overselling by design     | More complex reservation logic         |
| End-to-end unit traceability | Not practical for bulk/commodity items |

**Best for:** High-value or serialized goods (electronics, pharmaceuticals, automotive parts). Multi-warehouse, multi-bin environments.

---

## Comparison Matrix

| Criterion                 | Optimistic Locking | Pessimistic Locking | Event Sourcing  | Two-Phase | Discrete Unit |
| ------------------------- | ------------------ | ------------------- | --------------- | --------- | ------------- |
| Concurrency safety        | ★★★☆               | ★★★★★               | ★★★★★           | ★★★★☆     | ★★★★★         |
| Read performance          | ★★★★★              | ★★★★☆               | ★★☆☆☆           | ★★★★☆     | ★★★☆☆         |
| Write throughput          | ★★★☆☆              | ★★☆☆☆               | ★★★★☆           | ★★★☆☆     | ★★☆☆☆         |
| Implementation complexity | ★★★★★ (simple)     | ★★★★☆               | ★★☆☆☆ (complex) | ★★★☆☆     | ★★☆☆☆         |
| Audit / traceability      | ★★☆☆☆              | ★★★☆☆               | ★★★★★           | ★★★☆☆     | ★★★★★         |
| Idempotency handling      | ★★★☆☆              | ★★★★☆               | ★★★★★           | ★★★★☆     | ★★★★☆         |
| Deadlock prevention       | ★★★★☆              | ★★☆☆☆               | ★★★★★           | ★★★★☆     | ★★★★☆         |

---

## Recommendation Guide

1. **Start with Optimistic Locking** if this is a greenfield project with modest traffic.
2. **Use Pessimistic Locking** if inventory value is high and you cannot tolerate any overselling.
3. **Adopt Event Sourcing** if regulatory compliance requires a complete, tamper-evident audit log.
4. **Add Two-Phase Reservation** on top of any approach when your business needs "soft holds" (e.g., shopping carts).
5. **Switch to Discrete Unit Tracking** only if you track serial numbers, lot numbers, or bin locations today.

Each approach can be mixed: for example, use **Event Sourcing for audit** with **Discrete Units for serialized goods** and **Optimistic Locking for bulk commodities** in the same system.
