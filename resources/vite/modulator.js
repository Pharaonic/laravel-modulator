// The Vite entry points of the enabled Modulator modules, as inputs for the
// application's vite.config.js:
//
//   import { modules } from './vendor/pharaonic/laravel-modulator/resources/vite/modulator.js';
//
//   laravel({ input: ['resources/css/app.css', 'resources/js/app.js', ...modules()] })
//
// It runs "php artisan module:vite" once, when Vite loads the config.

import { execFileSync } from 'node:child_process';

/**
 * @param {{ php?: string, artisan?: string, cwd?: string }} [options]
 * @returns {string[]}
 */
export function modules(options = {}) {
    const php = options.php ?? process.env.PHP_BINARY ?? 'php';
    const artisan = options.artisan ?? 'artisan';
    const output = execFileSync(php, [artisan, 'module:vite', '--no-ansi'], {
        cwd: options.cwd ?? process.cwd(),
        encoding: 'utf8',
    });

    const entries = JSON.parse(output);

    if (!Array.isArray(entries)) {
        throw new Error('php artisan module:vite did not return a list of entries.');
    }

    return entries;
}
