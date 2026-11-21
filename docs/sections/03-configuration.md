## Configuration

The configuration lives under `pharaonic.modulator` (published to `config/pharaonic/modulator.php`).

```php title="config/pharaonic/modulator.php"
return [
    'disabled' => [
        // 'Shop/Orders',
    ],

    'pools' => [
        'local' => [
            'path' => base_path('Modules'),
            'namespace' => 'Modules',
            'priority' => 100,
        ],
        'app' => [
            'path' => app_path('Modules'),
            'namespace' => 'App\\Modules',
            'priority' => 90,
            'legacy' => true,
        ],
    ],

    'discovery' => [
        'cache' => true,
    ],

    'generation' => [
        'pool' => 'local',
        'stubs' => null,
    ],
];
```

| Key | Default | Description |
| --- | --- | --- |
| `disabled` | `[]` | Module identities to disable. Every other discovered module is enabled. Keep it a plain list of strings so `module:enable` / `module:disable` can edit it. |
| `pools` | `local`, `app` | Directories that hold modules. Each pool has a `path`, a `namespace` root, a `priority`, and optionally `legacy => true` (1.x layout). A pool may also be the class name of a `Pharaonic\Laravel\Modulator\Contracts\Pool`. |
| `discovery.cache` | `true` | Keep the discovery result in `bootstrap/cache/modulator.php`. |
| `generation.pool` | `local` | The pool `module:make` and `module:publish` write to. It must be an application pool with the `app/` layout. |
| `generation.stubs` | `null` | A directory of stubs that replace the package stubs with the same name. When `null`, `stubs/modulator` is used if it exists. |

:::info
A pool namespace is the namespace root of its modules: `Modules/Shop/app/Models/Product.php` in the `local` pool is `Modules\Shop\Models\Product`. `app/` never appears in the namespace.
:::

### Custom Pool Classes

A class implementing `Contracts\Pool` provides a pool whose path is computed at runtime, for example per tenant:

```php title="app/Modules/TenantPool.php"
use Pharaonic\Laravel\Modulator\Contracts\Pool;

final class TenantPool implements Pool
{
    public function name(): string { return 'tenant'; }
    public function priority(): int { return 80; }
    public function path(): string { return storage_path('tenant/modules'); }
    public function namespace(): string { return 'Tenant\\Modules'; }
    public function package(): ?string { return null; }
}
```

```php title="config/pharaonic/modulator.php"
'pools' => [
    // ...
    'tenant' => App\Modules\TenantPool::class,
],
```
