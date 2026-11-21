## Command Reference

| Command | Description |
| --- | --- |
| `module:make {module} {--plain}` | Create a module in the generation pool. |
| `module:make:<type> {module} {name?}` | Create a class in a module (see Generators). |
| `module:delete {module} {--with-children} {--dry-run} {--force}` | Delete an application module directory. |
| `module:list` | List the modules with status, pool, package and path. |
| `module:enable {module?} {--with-parents} {--with-dependencies} {--force}` | Remove a module from `disabled`. |
| `module:disable {module?} {--cascade} {--force}` | Add a module to `disabled`. |
| `module:status {module?}` | Configured and effective state, with reasons. |
| `module:use {module?} {--clear}` | Select the module for your console. |
| `module:current` | Show the selected module. |
| `module:pools` | List the pools, highest priority first. |
| `module:source {module}` | The active and shadowed copies of a module. |
| `module:publish {module} {--with-children} {--dry-run} {--force}` | Copy a package module into the local pool. |
| `module:discover` | Rebuild the manifest and list the modules. |
| `module:cache` | Rebuild the manifest; fails on errors. |
| `module:clear` | Delete the manifest. |
| `module:doctor` | Check the whole setup. |
| `module:graph {--format=text}` | Hierarchy and dependencies, as text or JSON. |
| `module:check {module?} {--json}` | Static boundary check. |
| `module:migrate`, `:rollback`, `:status`, `:reset`, `:refresh` | Module migrations (see Database Commands). |
| `module:seed {module?} {--class=}` | Run a module seeder. |
| `module:test {module?}` | Run the module tests. |
| `module:vite {module?}` | The Vite entry points of the enabled modules, as JSON, for `vite.config.js`. |
| `module:routes {module?} {--method=} {--compact} {--columns=*} {--sort=} {--reverse} {--json}` | The routes modules own. |

Every command that takes a module accepts a nested identity such as `Commerce/Checkout`, and rejects invalid ones (`..`, lowercase, separators). Commands exit with 0 on success and 1 when they refuse; `module:test` returns the test runner's exit code.
