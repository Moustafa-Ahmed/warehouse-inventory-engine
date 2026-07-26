# Local Shipping Runtime

The persistent mock provider sends signed callbacks over HTTP. Run it with a real queue worker; `QUEUE_CONNECTION=sync` is not valid for this loopback flow because the callback must execute independently from the request or scheduler process that dispatched it.

## Required Environment

```dotenv
APP_URL=http://127.0.0.1:8000
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=90
MOCK_PROVIDER_WEBHOOK_URL="${APP_URL}/webhooks/shipping-provider"
MOCK_PROVIDER_WEBHOOK_SECRET=replace-with-a-local-secret
```

`MOCK_PROVIDER_WEBHOOK_URL` must be reachable from the queue worker. The same `MOCK_PROVIDER_WEBHOOK_SECRET` signs outbound mock callbacks and verifies the receiving webhook.

The shipping jobs have application timeouts of at most 30 seconds. Run the worker with a 60-second timeout and keep `DB_QUEUE_RETRY_AFTER` above that worker timeout so an interrupted job is not made available to a second worker before the first one exits.

## Processes

After installing dependencies, configuring `.env`, and running `php artisan migrate`, use separate terminals:

```text
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --timeout=60 --tries=3
php artisan schedule:work
npm run dev
```

The scheduler runs bounded recovery for:

- Pending shipment submissions.
- Provider submissions with unknown outcomes.
- Due, retryable, and abandoned mock-provider webhook deliveries.
- Pending provider webhook receipts, including out-of-order receipts waiting on prerequisites.
- Outstanding backorder allocation.
- Expired temporary reservations.

Every recovery command runs once per minute with overlap prevention and single-server locking. Multi-server deployments must use a shared cache store supported by Laravel scheduler locks; the default database cache is suitable when all application nodes share the same database.

Database uniqueness and idempotent services remain the correctness mechanisms. Scheduler and queue uniqueness only rediscover work and reduce duplicate execution.
