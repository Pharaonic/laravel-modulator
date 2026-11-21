## AI Ready

Modulator is AI ready. It ships [Laravel Boost](https://laravel.com/docs/boost) resources, so the coding agent in your application (Claude Code, Cursor, Codex, Copilot, Gemini CLI, Junie…) knows how modules work and which commands to use.

### Install

With Laravel Boost in your application, run:

```bash title="Terminal" no-line-numbers
php artisan boost:install
```

Boost finds the package's guideline and skill in `vendor/` and adds them for the agents you choose. After updating the package, refresh them with:

```bash title="Terminal" no-line-numbers
php artisan boost:update
```

### What the Agent Learns

| Resource | Loaded | Covers |
| --- | --- | --- |
| Guideline (`resources/boost/guidelines/core.blade.php`) | In every session | The module layout and namespaces, identities and slugs, the generators, enabling and disabling, and the integrations |
| Skill `modulator-development` | When the agent works on modules | Every loaded convention, all `module:*` commands and their options, the PHP API, the Livewire, Filament, executor and Vite integrations, pitfalls and testing |

:::tip Keep Them Current
Add `@php artisan boost:update --ansi` to your Composer `post-update-cmd` scripts so the agent always matches the installed release.
:::
