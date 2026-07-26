# Video Walkthrough

## Submission Status

Target duration: **18 minutes 30 seconds**.

Video URL: **Pending recording and upload by the repository owner.**

Do not submit the repository with “Pending” as the final video link. After uploading, replace the line above with the public or reviewer-accessible URL and verify it while signed out or through the intended reviewer account.

## Before Recording

### Personal details to prepare

The introduction must be in the owner's own words. Prepare one sentence for each relevant area:

- Name and current role.
- ERP or large CRUD system experience.
- Inventory, warehousing, logistics, or POS experience.
- The specific part of this challenge that best demonstrates personal engineering judgment.

Do not claim experience that is not true. A short honest introduction is stronger than reading generic background text.

### Runtime preparation

Use two active terminals:

1. Application and Vite:

   ```bash
   composer run dev
   ```

2. Demonstration commands and focused tests.

Keep a third terminal available for `php artisan schedule:list`, but do **not** run `schedule:work` during the deterministic manual callback demonstrations. The scheduler correctly delivers every due mock-provider webhook; starting it too early would advance the prepared timeout, duplicate, and out-of-order scenarios before they appear in the recording. Start it only after those demonstrations if you want to show automatic recovery live.

Confirm `.env` uses:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=90
MOCK_PROVIDER_WEBHOOK_URL="${APP_URL}/webhooks/shipping-provider"
MOCK_PROVIDER_WEBHOOK_SECRET=the-same-local-secret-used-by-the-receiver
```

Prepare a clean deterministic demonstration immediately before recording:

```bash
php artisan migrate --seed
php artisan demo:inventory-scenarios --reset --force
```

Open the application, sign in, and keep these tabs ready:

- Dashboard.
- `DEMO-PARTIAL-001` order.
- Timeout shipment.
- Duplicate-callback shipment.
- Pending out-of-order provider webhook receipt.
- Inventory movement report.
- `docs/ARCHITECTURE.md`.
- `docs/testing-evidence.md`.
- `docs/AI_USAGE.md`.

Rehearse once, then reset the demo again. Provider callbacks change durable state, so a second recording attempt needs another reset.

Never show `.env`, passwords, HMAC secrets, browser password storage, or raw authorization material on screen.

## Timed Recording Script

### 0:00–2:00 — Introduction and design choice

Show the dashboard while introducing yourself.

Cover:

- Your real background using the prepared personal sentences.
- The problem: shared warehouse inventory must remain correct under concurrent allocation and unreliable provider callbacks.
- The chosen balance: MySQL pessimistic row locking, an append-only movement ledger, synchronous projections, and Laravel application services.
- Why this fits an interview task: the correctness boundary is strong and explainable without event-sourcing or distributed-system machinery.

Suggested transition:

> “The rest of the walkthrough follows one rule: an inventory effect is committed atomically and can be applied at most once, regardless of which interface or retry caused it.”

### 2:00–7:00 — Architecture and business model

Show [System Architecture](ARCHITECTURE.md), then briefly relate it to the running UI.

#### 2:00–3:00 — Inventory ownership

Explain:

- `inventory_movements` is append-only history.
- `inventory_balances` is the current product/warehouse projection.
- Available, reserved, picked, and packed are mutually exclusive on-hand buckets.
- Shipped is an external movement destination; delivered is cumulative progress inside shipped quantity.

Use the movement-flow diagram:

```text
External -> Available -> Reserved -> Picked -> Packed -> External/Shipped
```

#### 3:00–4:00 — Transactions and concurrency

Explain:

- Application services own transaction boundaries.
- `InventoryMovementService` locks affected balances by ascending ID.
- Source quantity is reread after the lock.
- Movement, projection, reservation/order state, history, and operation result commit or roll back together.
- Provider HTTP never occurs while inventory rows are locked.

#### 4:00–5:00 — Idempotency

Distinguish three identities:

- Business operation key: same request replays the stored result; changed payload conflicts.
- Provider request key: stable across submission retry and status lookup.
- Provider event ID: unique with provider identity and exact raw-body duplicate handling.

Emphasize that queue uniqueness is an optimization; database state is the correctness mechanism.

#### 5:00–6:00 — Reservation and shipment semantics

Explain:

- Partial reservation reports requested, allocated, and outstanding.
- Temporary reservations may expire; confirmed reservations do not.
- Pick, pack, and their explicit reversals are physical movements.
- Shipment composition assigns packed stock but does not deduct it.
- Provider acceptance is not handoff.
- Only a valid persisted `shipment.confirmed` callback moves packed stock to shipped.

#### 6:00–7:00 — Boundaries and patterns

Show the service/DTO/provider sections and name only patterns actually present:

- Application Service.
- Ledger plus synchronous projection.
- Idempotent command.
- Port and adapter at the provider boundary.
- Durable pending intent plus recovery sweepers.
- Native `final readonly` DTOs as typed boundaries.

State what was deliberately omitted: generic repositories, base services, a general JSON API, event sourcing, and a generic outbox.

### 7:00–13:00 — Six failure demonstrations

Keep the inventory movement report open in another tab. After each scenario, state why inventory is still correct.

#### 7:00–7:50 — Concurrent final unit

Run:

```bash
php artisan demo:concurrent-reservation
```

Point out:

- Attempt allocations are `0, 1`.
- Available ends at `0`.
- Reserved ends at `1`.
- Two independent requests did not promise the same unit.

Correctness explanation: both writers target the same balance row; only the winner sees the last available unit after acquiring the lock.

#### 7:50–8:40 — Duplicate reservation operation

Run:

```bash
php artisan test --compact --filter="replays a completed full reservation without allocating twice"
```

Explain the important assertions: one reservation effect, one movement, one transition, and the same original result on replay.

Correctness explanation: the unique operation key and canonical request hash identify an identical intent before another stock movement can occur.

#### 8:40–9:40 — Partial allocation and recovery

Open order `DEMO-PARTIAL-001`.

Show:

- Ordered/requested: 10.
- Allocated: 6.
- Outstanding: 4.

Receive four units of the same product into the demo warehouse through the inventory receipt screen. Let the queue worker process the after-commit allocation job, or run:

```bash
php artisan inventory:allocate-backorders --batch=50
```

Refresh the order and show that the remaining demand is allocated without exceeding ten.

Correctness explanation: partial work stays explicit; recovery allocates only the current outstanding quantity through the same locked reservation service.

#### 9:40–11:00 — Timeout after provider acceptance

Open the prepared timeout shipment and its provider submission.

Show:

- Submission outcome is `unknown`.
- A stable provider request key exists.
- The simulated external shipment and callback intent already exist.
- The warehouse shipment remains `pending_handoff`.
- Inventory remains packed.

Use the reconcile control. Then use the handoff-confirmation control on the same shipment and let the queue worker deliver the signed callback over actual HTTP.

Refresh and show:

- Provider webhook receipt is processed.
- Shipment is `shipped`.
- Packed decreased only now.
- A packed-to-external/shipped movement exists.

Correctness explanation: reconciliation can discover provider state and request redelivery, but only the persisted callback-processing transaction deducts inventory.

#### 11:00–11:50 — Exact duplicate callback

Open the prepared duplicate-callback shipment. Send handoff confirmation once, wait for processing, then use **Replay last webhook**.

Show that:

- The replay keeps the same external event ID and raw body.
- The endpoint returns a duplicate/complete response.
- Only one provider receipt identity exists.
- No second packed-to-shipped movement appears.

Correctness explanation: unique provider/event identity handles receipt duplication, and the completed operation protects the inventory effect.

#### 11:50–12:30 — Transaction rollback

Run:

```bash
php artisan test --compact --filter="rolls back the movement and projection when the caller transaction fails"
```

Explain that the test injects failure after a movement operation and verifies the movement and balance projection return to their original state.

Correctness explanation: the service never suppresses the exception; MySQL rolls back all writes and releases the locks.

#### 12:30–13:00 — Out-of-order callback

Open the prepared pending delivery receipt and show that delivery arrived before carrier handoff but remains pending.

Send handoff confirmation for its shipment, then run:

```bash
php artisan provider-webhooks:process-pending --limit=50
```

Show that the prerequisite confirmation is applied first and the stored delivery receipt can then advance delivery.

Correctness explanation: receipt persistence does not imply immediate application; processing classifies ready, waiting, and stale events.

### 13:00–15:00 — Testing strategy

Show [Testing Evidence](testing-evidence.md) and [the passing-suite evidence](evidence/passing-tests.png).

Explain:

- Smoke tests protect application wiring, routes, screens, commands, seeding, and representative forms.
- Critical MySQL tests protect concurrency, lock order, idempotency, rollback, quantity conservation, shipment confirmation, HMAC, callback order, retries, and query plans.
- Unit tests are limited to the pure order progress calculator and provider outcome mapping.
- The highest-risk concurrency and duplicate-processing tests repeat three times.
- Simple DTOs, enums, and model accessors do not get standalone tests because that would add volume without protecting a distinct risk.

Call out the final result shown in the evidence image:

```text
129 passed
1,413 assertions
```

### 15:00–17:00 — AI usage and engineering ownership

Show [AI Usage and Engineering Ownership](AI_USAGE.md).

Be direct:

- AI produced most first drafts of challenge-specific code and documentation.
- You personally selected the business decisions and scope.
- You reviewed work task by task, required tests and commits, and challenged naming and overengineering.

Name concrete rejected or changed suggestions:

- No event sourcing.
- No generic repositories or base services.
- Removed the scenario-selector interface.
- Used Laravel-oriented DTO folders instead of vague Domain/ValueObjects structure.
- Merged duplicate provider result DTOs.
- Used `unknown`, not `uncertain`.
- Kept a small risk-based test suite.
- Required the persistent mock provider to use durable state and real signed HTTP callbacks.

State that ownership means being able to explain and modify the solution, not claiming that every character was typed without assistance.

### 17:00–18:30 — Trade-offs, scale, and close

Explain the main trade-off:

- Pessimistic locking gives a simple correctness proof.
- A hot product/warehouse balance serializes writers.
- Short transactions, deterministic lock ordering, and bounded retries control the cost.

For millions of transactions, propose measured evolution:

1. Observe hot keys, lock waits, deadlocks, queue lag, callback age, and query latency.
2. Partition or archive movement history.
3. Move reporting to replicas or dedicated projections.
4. Use Redis for queueing and distributed scheduler locks while retaining MySQL row locks for inventory correctness.
5. Serialize only exceptionally hot SKU commands if measurements justify it.
6. Add provider-specific adapters, rate limits, circuit breakers, and secret rotation.
7. Add a transactional outbox only when real external consumers require it.

Close with the explicit limitations: no returns/RMA, costing, lots, serials, bins, multi-tenancy, real carrier, general API, or ledger repair command.

Suggested final sentence:

> “The implementation is intentionally narrow, but every included inventory transition has a durable identity, an atomic transaction, and focused evidence for its highest-risk failure mode.”

## Recording Checklist

- [ ] Duration is between 15 and 20 minutes.
- [ ] Personal introduction is accurate and not read from generic filler.
- [ ] Architecture, schema, lifecycle, locking, idempotency, security, patterns, and SOLID are covered.
- [ ] At least five failure scenarios are visibly demonstrated.
- [ ] The timeout scenario includes actual signed HTTP callback delivery.
- [ ] Inventory correctness is explained after every failure.
- [ ] Testing scope and deliberate non-coverage are explained.
- [ ] AI-generated, modified, rejected, and owner-decided work are distinguished.
- [ ] Scaling and remaining risks are discussed.
- [ ] No secret or local password appears on screen.
- [ ] The uploaded URL works for the intended reviewer.
- [ ] The final URL replaces “Pending” at the top of this document and in the root README.
