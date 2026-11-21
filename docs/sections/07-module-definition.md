## Module Definition

An optional `module.php` at the module root declares dependencies and the public namespaces of the module. It returns a plain array: no class is loaded during discovery.

```php title="Modules/Shop/module.php"
return [
    'dependencies' => ['Catalog', 'Commerce/Checkout'],
    'exposes' => ['Contracts', 'Events'],
];
```

| Key | Description |
| --- | --- |
| `dependencies` | Module identities this module needs. A missing, disabled or cyclic dependency disables the module, with the reason. |
| `exposes` | Namespaces, relative to the module, that other modules may use. Empty means everything; `module:check` enforces it. |

Load order follows the requirements: parents and dependencies register before the modules that need them.

:::warning
Unknown keys are reported and ignored. A dependency cycle disables every module of the cycle.
:::
