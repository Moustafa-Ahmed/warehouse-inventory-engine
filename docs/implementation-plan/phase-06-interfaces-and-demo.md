# Phase 6 — Interfaces and Demonstration

## Objective

Expose proven application actions through one-administrator authentication, small server-rendered Blade workflows, operational query services, and deterministic demonstration data.

The UI does not depend on a general JSON API or AJAX. JavaScript/jQuery is limited to progressive enhancement. Query services are implemented before reports and the dashboard that consume them.

## Commit P6.1 — `feat: authenticate the administrator interface`

**Priority:** Submission-critical

Scope:

- Implement login/logout using Laravel’s existing user model and session guard.
- Restrict operational web routes to authenticated users.
- Add one administrator authorization gate or policy baseline.
- Seed local administrator credentials through documented environment/config values.
- Rate limit login attempts.
- Add a focused authentication test for guest rejection and administrator access.

Done when:

- Anonymous users cannot access operational pages.
- The seeded administrator can perform every core action.
- No credentials or secrets are committed.

## Commit P6.2 — `feat: establish safe operational web conventions`

**Priority:** Submission-critical

Scope:

- Use named web routes, implicit route binding where appropriate, and thin controllers.
- Add Form Requests for validation and authorization.
- Generate and carry an operation key in every authenticated business-mutation form.
- Map application results and domain failures to clear Blade messages.
- Use post/redirect/get after mutations.
- Return the stored result for an identical repeated submission.
- Show a conflict when the same operation key is submitted with changed input.
- Add one representative business-mutation feature test covering authorization, redirect, replay, and conflict.

Done when:

- Web controllers only translate input, call an application action, and select a redirect or view.
- Browser refresh cannot silently become a new business operation.
- Validation, authorization, domain rejection, idempotent replay, and idempotency conflict are distinguishable to the administrator.

## Commit P6.3 — `feat: add the operational Blade layout`

**Priority:** Submission-critical

Scope:

- Add Bootstrap and jQuery assets using the approved dependency strategy.
- Create accessible Blade components for layout, navigation, forms, messages, tables, badges, and pagination.
- Use jQuery only for one nonessential progressive enhancement such as client-side filtering or confirmation affordances.
- Ensure navigation and every required form remain usable without JavaScript.
- Add the layout and authenticated shell to the HTTP smoke suite.

Done when:

- No query or business rule runs from Blade.
- Disabling JavaScript does not block required work.

## Commit P6.4 — `feat: add inventory operation screens`

**Priority:** Submission-critical

Scope:

- Add inventory balance detail with bucket quantities and recent movements.
- Add receive, adjust, and available-stock transfer forms.
- Display stored idempotency results after redirect.
- Keep operation keys stable across duplicate browser submission.
- Add inventory pages to the smoke suite; rely on critical action tests for inventory correctness.

Done when:

- An administrator can prepare and modify demo inventory through the same actions used by commands/jobs.
- The UI cannot bypass movement, locking, or idempotency rules.

## Commit P6.5 — `feat: add order and reservation screens`

**Priority:** Submission-critical

Scope:

- Add focused order list/create/detail/edit screens.
- Show separate allocation, fulfillment, and delivery progress.
- Add reservation detail and transition timeline.
- Add valid reserve, confirm, release, and allocate-now forms.
- Display requested, allocated, and outstanding quantities after every allocation attempt.
- Add order and reservation pages to the smoke suite.
- Extend the representative web workflow to show a partial result.

Done when:

- Outstanding quantity is always visible and partial allocation is never labelled as full.
- Invalid state actions are absent or rejected server-side.

## Commit P6.6 — `feat: add fulfillment shipment and event screens`

**Priority:** Submission-critical

Scope:

- Add valid pick, return, pack, and unpack controls.
- Add shipment list/create/detail/submission screens.
- Show provider attempts, uncertainty, permanent failure, and callback processing.
- Add a provider-event inbox with safe status/error context.
- Add retry controls that reuse the established provider request identity.
- Add these pages to the smoke suite.
- Add one focused assertion that sensitive provider data is not rendered.

Done when:

- The complete shipment lifecycle can be explained from the UI.
- Administrators can inspect uncertain and permanently failed outcomes without exposing secrets.

## Commit P6.7 — `feat: add operational query services and reports`

**Priority:** Submission-critical

Scope:

- Implement dedicated read/query services outside Blade.
- Add filterable aggregate reporting for available, reserved, picked, packed, on-hand, and shipped quantities.
- Derive shipped totals from confirmed packed-to-external movements rather than a warehouse on-hand bucket.
- Add a filterable open-reservation report.
- Add an orders-that-consumed-inventory report, where consumption means confirmed packed-to-external movement.
- Add movement-history filters and pagination.
- Eager load relationships, select required columns, and use explicit default ordering.
- Add report pages to the smoke suite and keep focused query-plan checks for critical lookups.

Done when:

- A reviewer can answer every operational query required by the challenge.
- Aggregate shipped stock and current warehouse stock cannot be confused.
- No report formula is duplicated in Blade.

## Commit P6.8 — `feat: build coherent demonstration scenarios`

**Priority:** Submission-critical

Scope:

- Build demo scenario data through the implemented application actions, never direct non-zero balance or history inserts.
- Add deterministic local controls for every fake-provider outcome.
- Add `demo:concurrent-reservation`.
- Add a local-only `demo:inventory-scenarios` setup/reset command with explicit environment protection.
- Include scenarios for partial allocation, timeout, permanent failure, duplicate callback, pending event, and shipment confirmation.
- Add one focused test proving demo controls are disabled outside local/testing environments.

Done when:

- Demo data reconciles with the movement ledger and histories.
- Required video scenarios are reproducible.
- Demo controls cannot be enabled accidentally in production.

## Commit P6.9 — `feat: add catalog administration screens`

**Priority:** Supporting

Scope:

- Add focused product and warehouse list/create/edit screens.
- Use small application actions for catalog mutations rather than writing directly from controllers.
- Protect forms with authentication, authorization, validation, and CSRF.
- Avoid destructive deletion; use active/inactive state.
- Add catalog pages to the smoke suite.

Done when:

- An administrator can prepare reference products and warehouses without direct database access.
- Catalog controllers remain thin.

## Commit P6.10 — `feat: add the operational dashboard`

**Priority:** Supporting

Scope:

- Build the dashboard only after the query services exist.
- Show concise counts/lists for partial allocations, expiring reservations, pending or uncertain shipments, failed attempts, pending events, and recent movements.
- Link dashboard items to the corresponding operational screens.
- Reuse query services rather than introducing dashboard-specific business queries.
- Add the dashboard to the smoke suite.

Done when:

- The first viewport explains system health without decorative noise.
- Dashboard implementation does not duplicate report logic.

## Optional Future API Sequence

**Priority:** Optional/stretch

Do not begin these commits until all submission-critical implementation, tests, documentation, evidence, and walkthrough preparation pass.

### Optional Commit P6.O1 — `feat: expose versioned inventory queries`

- Register authenticated and rate-limited `/api/v1` routes.
- Add Eloquent API Resources and consistent error envelopes.
- Expose only needed inventory, movement, order, reservation, shipment, and reporting queries.
- Add one read-contract smoke test.

### Optional Commit P6.O2 — `feat: expose idempotent inventory mutations`

- Expose receipt, adjustment, and transfer actions.
- Accept operation keys and preserve request-hash conflict behavior.
- Use Form Requests for authorization and validation.
- Add one focused mutation contract test for authorization, replay, and conflict.

### Optional Commit P6.O3 — `feat: expose order and shipment mutations`

- Expose order, reservation, fulfillment, and shipment actions.
- Return requested, applied, and outstanding quantities where relevant.
- Reuse the same actions as Blade, commands, jobs, and the webhook.
- Add one representative contract workflow.

## Phase Gate

- [ ] One-administrator authentication protects operational routes
- [ ] Web forms are validated, authorized, and CSRF-protected; business-mutation forms are idempotent
- [ ] UI covers the minimum inventory, order, reservation, fulfillment, shipment, and provider-event workflows
- [ ] Query services answer stock, shipped, open-reservation, and consumed-inventory questions
- [ ] Reports exist before the dashboard and Blade contains no query formulas
- [ ] Partial results visibly distinguish requested, allocated, and outstanding quantities
- [ ] Required operations work without JavaScript
- [ ] Demo data is produced through application actions and every provider outcome is deterministic
- [ ] Supporting catalog/dashboard work is complete, simplified, or documented as a limitation
- [ ] No general JSON API is required for this gate
- [ ] README, architecture, AI-usage, and video-outline documents are current
- [ ] The intentionally small smoke and critical suites, Pint, and frontend build pass
