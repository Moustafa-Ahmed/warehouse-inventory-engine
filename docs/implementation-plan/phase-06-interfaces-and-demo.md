# Phase 6 — Interfaces and Demonstration

## Objective

Expose the proven application actions through one-administrator authentication, server-rendered Blade pages and forms, the signed provider webhook built in Phase 5, and deterministic demonstration controls.

The UI does not depend on a general JSON API or AJAX. JavaScript/jQuery is limited to progressive enhancement.

## Commit P6.1 — `feat: authenticate the administrator interface`

Scope:

- Implement login/logout using Laravel's existing user model and session guard.
- Restrict operational web routes to authenticated users.
- Add one administrator authorization gate or policy baseline.
- Seed local administrator credentials through documented environment/config values.
- Rate limit login attempts.
- Add login, logout, guest, throttling, and authorized-access tests.

Done when:

- Anonymous users cannot access operational pages.
- The seeded administrator can perform every core action.
- No credentials or secrets are committed.

## Commit P6.2 — `feat: establish safe operational web conventions`

Scope:

- Use named web routes, implicit route binding where appropriate, and thin controllers.
- Add Form Requests for validation and authorization.
- Generate and carry an operation key in every authenticated business-mutation form.
- Map application results and domain failures to clear Blade messages.
- Use post/redirect/get after mutations.
- Return the stored result for an identical repeated submission.
- Show a conflict when the same operation key is submitted with changed input.
- Add CSRF, authorization, validation, duplicate-submission, conflict, and redirect-flow tests.

Done when:

- Web controllers only translate input, call an application action, and select a redirect or view.
- Browser refresh cannot silently become a new business operation.
- Validation, authorization, domain rejection, idempotent replay, and idempotency conflict are distinguishable to the administrator.

## Commit P6.3 — `feat: add operational layout and dashboard`

Scope:

- Add Bootstrap and jQuery assets using the approved dependency strategy.
- Create accessible Blade components for layout, navigation, forms, messages, tables, badges, and pagination.
- Add dashboard counts/lists for partial allocations, expiring reservations, pending or uncertain shipments, failed attempts, pending events, and recent movements.
- Use jQuery only for nonessential progressive enhancement such as client-side table filtering or confirmation affordances.
- Ensure navigation and every required form remain usable without JavaScript.
- Eager load relationships and select only required columns.
- Add page-access, no-JavaScript behavior, and query-behavior tests where valuable.

Done when:

- The first viewport explains system health without decorative dashboard noise.
- No query or business rule runs from Blade.
- At least one small jQuery enhancement is demonstrable, but disabling JavaScript does not block required work.

## Commit P6.4 — `feat: add catalog inventory and reporting screens`

Scope:

- Add product and warehouse list/create/edit screens.
- Add inventory balance detail with bucket quantities and movements.
- Add receive, adjust, and available-stock transfer forms.
- Add filterable aggregate inventory reporting for available, reserved, picked, packed, on-hand, and shipped quantities.
- Derive shipped totals from confirmed packed-to-external movements rather than a warehouse on-hand bucket.
- Add movement-history filters and pagination.
- Protect forms with session authentication, authorization, validation, CSRF, and operation keys.
- Add feature tests for pages, mutations, reports, filters, idempotent replay, and domain errors.

Done when:

- An administrator can prepare demo inventory without direct database access.
- Aggregate shipped stock and current warehouse stock are both visible and cannot be confused.

## Commit P6.5 — `feat: add order reservation and backorder screens`

Scope:

- Add order list/create/detail/edit screens.
- Show separate allocation, fulfillment, and delivery progress.
- Add reservation detail and transition timeline.
- Add valid reserve, confirm, release, pick, return, pack, and unpack forms.
- Add backorder queue and allocate-now action.
- Add a filterable open-reservation report.
- Add an orders-that-consumed-inventory report, using confirmed packed-to-external movement as the consumption definition.
- Display requested, allocated, and outstanding quantities after every allocation attempt.
- Add feature tests for page behavior, partial results, reports, invalid actions, and duplicate form submissions.

Done when:

- Outstanding quantity is always visible and partial allocation is never labelled as full.
- Invalid state actions are absent or rejected server-side.
- A reviewer can list open reservations and orders that actually consumed inventory.

## Commit P6.6 — `feat: add shipment and provider event screens`

Scope:

- Add shipment list/create/detail/submission screens.
- Show provider attempts, uncertainty, permanent failure, and callback processing.
- Add provider-event inbox with status and safe error context.
- Add retry controls that reuse the established provider request identity.
- Ensure the signed webhook remains outside session authentication and is not treated as a general API.
- Add feature tests for page behavior, valid actions, authorization, duplicate submissions, and redacted provider data.

Done when:

- The complete shipment lifecycle can be explained from the UI.
- Administrators can inspect uncertain and permanently failed outcomes without exposing secrets.

## Commit P6.7 — `feat: add deterministic demonstration controls`

Scope:

- Add local/demo-only provider-outcome controls.
- Add `demo:concurrent-reservation`.
- Add optional `demo:inventory-scenarios` dataset/reset helper without destructive production behavior.
- Add environment restrictions and tests.

Done when:

- Required video scenarios are reproducible.
- Demo controls cannot be enabled accidentally in production.

## Optional Future API Sequence

Do not begin these commits until all required implementation, tests, documentation, evidence, and walkthrough preparation pass. They are not part of the Phase 6 gate.

### Optional Commit P6.O1 — `feat: expose versioned inventory queries`

- Register authenticated and rate-limited `/api/v1` routes.
- Add Eloquent API Resources and consistent error envelopes.
- Expose only required inventory, movement, order, reservation, shipment, and reporting queries.
- Add independent contract tests.

### Optional Commit P6.O2 — `feat: expose idempotent inventory mutations`

- Expose receipt, adjustment, and transfer actions.
- Accept operation keys and preserve request-hash conflict behavior.
- Use Form Requests for authorization and validation.
- Add success, validation, authorization, replay, conflict, and domain-error tests.

### Optional Commit P6.O3 — `feat: expose order and shipment mutations`

- Expose order, reservation, fulfillment, and shipment actions.
- Return requested, applied, and outstanding quantities where relevant.
- Reuse the same actions as Blade, commands, jobs, and the webhook.
- Add transition, failure, idempotency, and contract tests.

## Phase Gate

- [ ] One-administrator authentication protects operational routes.
- [ ] Web forms are validated, authorized, and CSRF-protected; business-mutation forms are also idempotent.
- [ ] UI covers inventory, reports, orders, reservations, backorders, shipments, and events.
- [ ] Partial results visibly distinguish requested, allocated, and outstanding quantities.
- [ ] Required operations work without JavaScript; jQuery supplies only a small progressive enhancement.
- [ ] Web controllers, commands, jobs, and webhook call shared application actions.
- [ ] Demo provider outcomes are deterministic locally and in tests.
- [ ] No general JSON API is required for this gate.
- [ ] Full tests, Pint, and frontend build pass.
