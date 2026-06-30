## bootstrap/app.php
Add:
```php
use App\Http\Middleware\AdminMiddleware;
```

Inside `withMiddleware(...)`:
```php
$middleware->alias([
    'admin' => AdminMiddleware::class,
]);
```

## app/Console/Kernel.php
Register:
- `App\Console\Commands\CreateAdminUserCommand::class`
- `App\Console\Commands\ImportXlsFormFixtureCommand::class`
