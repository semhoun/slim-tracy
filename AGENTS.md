# Repository Guidance

## Setup and Checks

* This is the Composer library `semhoun/slim-tracy`, not a standalone Slim app. Use the repository root for commands; README references to `vendor/semhoun/runtracy` and `Example` are stale.
* `composer install` installs runtime and development dependencies. PHP `>=8.2 <8.6`, `ext-mbstring`, and `ext-json` are required. `composer.lock` is intentionally ignored, so dependency resolution is not pinned.
* Full tests: `composer test`. Focused test: `vendor/bin/phpunit tests/SlimTracy/Middlewares/TracyMiddlewareTest.php`; add `--filter testTracyMiddlewareWithoutCookies` for one method.
* Tests use PHPUnit 11, Slim 4 with Nyholm PSR-7 requests, and PHP-DI. Database integration tests use in-memory SQLite and require `pdo_sqlite` for tests only, not as a library runtime dependency. `phpunit.xml.dist` fails on warnings, notices, and deprecations; preserve these checks.
* PHP checks: `composer rector-check` and `composer insights-check`. Their modifying counterparts are `composer rector-fix` and `composer insights-fix`.
* Support PHP 8.2 through 8.5. The CI matrix in `.github/workflows/php.yml` tests every supported minor version. Rector only scans `src/` and targets the declared PHP 8.2 minimum (`rector.php`). Preserve that compatibility when applying fixes.
* `phpcs.xml` targets `src/` with PSR-12 plus required class/function/property documentation. `phpinsights.php` sets a 120-character code line limit and quality/architecture/style thresholds of 90/85/96.
* Node tooling requires Node.js 22+ and is only for Markdown, not building the bundled browser assets: `npm ci`, then `npm run check`. For one file use `npm exec -- remark AGENTS.md --frail`. `npm run format` rewrites Markdown across the repository.
* `remark-cli` 12 still pulls vulnerable glob/minimatch versions. Scoped overrides select glob 14.0.4+ and minimatch 11.0.5+; remove them when upstream dependencies include the security fixes. When updating, verify CLI glob expansion, ignored paths, plugin loading, and the CommonJS workspace APIs, not just `npm audit`.

## Integration Boundaries

* Composer maps `SlimTracy\` to `src/SlimTracy/` and `Tests\` to `tests/`. `examples/` depends on host-app `App\` classes and absent `config/` files; it is not a runnable demo.
* `Middlewares/TracyMiddleware.php` is the integration entrypoint: construct with `(App $app, array $tracySettings)`, passing the `tracy` subsection itself. It registers collectors in the constructor and panels after `$handler->handle($request)`.
* The middleware needs a writable container with `set()`, not merely an arbitrary PSR-11 implementation: it stores `tracy.settings` there. Panel selection can be overridden by the `tracyPanelsEnabled` JSON cookie, so settings alone may not explain visible panels.
* Doctrine/Twig/Eloquent are optional consumer integrations, despite being development dependencies here. Preserve the middleware's availability guards. `configs.Container.Doctrine` names the shared DBAL/ORM **Configuration** service, not a connection or entity manager; the collector appends a DBAL logging middleware and stores `tracy.doctrineQueries` in the container.
* Debugging helpers are autoloaded static methods on `SlimTracy\Debug`; console levels use `SlimTracy\ConsoleLevel`. The former global helpers and `shortcuts.php` were removed. Public classes may now be final; compose them instead of extending them.
* The console requires a host-app POST route to `SlimTracy\Controllers\SlimTracyConsole:index` plus jQuery and terminal assets. `web/` contains those assets; panel markup also lives in `src/SlimTracy/Templates/*.phtml`. Do not assume a frontend build or automatic asset publishing exists.
