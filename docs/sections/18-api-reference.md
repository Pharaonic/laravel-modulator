## API Reference

### Modules Facade

`Pharaonic\Laravel\Modulator\Facades\Modules` resolves the `ModuleRegistry`.

| Method | Description | Returns |
| --- | --- | --- |
| `Modules::all()` | Every resolved module, enabled or not, by identity | `array<string, Module>` |
| `Modules::enabled()` | The enabled modules, parents and dependencies first | `array<string, Module>` |
| `Modules::find($id)` | The module with this identity | `?Module` |

### Module

`Pharaonic\Laravel\Modulator\Module`

| Method | Description | Returns |
| --- | --- | --- |
| `id()` | The identity, e.g. `Commerce/Checkout` | `string` |
| `name()` | The last segment, e.g. `Checkout` | `string` |
| `namespace()` | The namespace root, e.g. `Modules\Commerce\Checkout` | `string` |
| `slug()` | The view, translation and config namespace, e.g. `commerce-checkout` | `string` |
| `path($path = null)` | The module root, or a path inside it | `string` |
| `pool()` | The pool it was resolved from | `string` |
| `package()` | The Composer package that provides it, or null | `?string` |
| `parent()` | The identity of the nearest ancestor module, or null | `?string` |
| `children()` | Identities of its child modules | `list<string>` |
| `dependencies()` | Identities declared in `module.php` | `list<string>` |
| `isEnabled()` | Whether it is effectively enabled | `bool` |
| `disabledReason()` | Why it is disabled, or null | `?string` |

### Helpers

| Helper | Description |
| --- | --- |
| `module_path($module, $path = null)` | The root of a module, or a path inside it. Throws `InvalidArgumentException` for an invalid or unknown identity. |
| `module_config_path($module, $path = null)` | `<module>/config/...` |
| `module_database_path($module, $path = null)` | `<module>/database/...` |
| `module_resource_path($module, $path = null)` | `<module>/resources/...` |

:::warning
`module_path()` without a module returns `app/Modules` (the 1.x behaviour) and is deprecated.
:::

### Contracts\Pool

| Method | Returns |
| --- | --- |
| `name()` | `string` |
| `priority()` | `int` |
| `path()` | `string` |
| `namespace()` | `string` |
| `package()` | `?string` |

`DirectoryPool` is the default implementation, built from each `pools` entry.

### Publish Tags

`pharaonic`, `laravel-modulator`, `pharaonic-config`, `modulator-config`. The 1.x tags `config` and `modulator` still work and are deprecated.
