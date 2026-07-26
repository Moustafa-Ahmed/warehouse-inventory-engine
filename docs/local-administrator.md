# Local Administrator

The operational UI uses one configured administrator identity. No default email or password is committed.

Set these values in `.env`:

```dotenv
ADMIN_NAME="Warehouse Administrator"
ADMIN_EMAIL=administrator@example.test
ADMIN_PASSWORD=choose-a-local-password
```

Then create or update the administrator:

```text
php artisan db:seed --class=AdministratorSeeder
```

The normal `php artisan db:seed` flow also calls this seeder. When either `ADMIN_EMAIL` or `ADMIN_PASSWORD` is blank, administrator seeding is skipped.
