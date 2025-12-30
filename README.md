[![Codacy Badge](https://app.codacy.com/project/badge/Grade/62644bc058af464eb2cfcf564c3500d6)](https://www.codacy.com/gh/semhoun/slim-tracy/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=semhoun/slim-tracy&amp;utm_campaign=Badge_Grade)[![Latest Stable Version](http://poser.pugx.org/semhoun/slim-tracy/v)](https://packagist.org/packages/semhoun/slim-tracy) [![Total Downloads](http://poser.pugx.org/semhoun/slim-tracy/downloads)](https://packagist.org/packages/semhoun/slim-tracy) [![Latest Unstable Version](http://poser.pugx.org/semhoun/slim-tracy/v/unstable)](https://packagist.org/packages/semhoun/slim-tracy) [![License](http://poser.pugx.org/semhoun/slim-tracy/license)](https://packagist.org/packages/semhoun/slim-tracy) [![PHP Version Require](http://poser.pugx.org/semhoun/slim-tracy/require/php)](https://packagist.org/packages/semhoun/slim-tracy)

# Slim Framework 4 Tracy Debugger Bar

configure it by mouse

***

![example](ss/tracy_panel.png "Tracy Panel")

now in package:

***

| Panel | Description |
| --- | --- |
| **Slim Framework** | - |
| Slim Environment | RAW data |
| Slim Container | RAW data |
| Slim Request | RAW data |
| Slim Response | RAW data |
| Slim Router | RAW data |
| **DB** | - |
| Doctrine [ORM](https://github.com/doctrine/doctrine2) or [DBAL](https://github.com/doctrine/dbal) | time, sql, params, types. panel & collector for both. **Note:** Need a Configuration instance available in DI, and must be the same used by Doctrine. |
| [Illuminate Database](https://github.com/illuminate/database) | sql, bindings |
| **Template** | - |
| [Twig](https://github.com/twigphp/Twig) | \Twig\Profiler\Dumper\HtmlDumper() |
| **Common** | - |
| PanelSelector | easy configure (part of fork from [TracyDebugger](https://github.com/adrianbj/TracyDebugger)) |
| PhpInfo | full phpinfo() |
| Console | PTY (pseudo TTY) console (fork from [web-console](https://github.com/nickola/web-console)) |
| Profiler | time, mem usage, timeline (fork from [profiler](https://github.com/netpromotion/profiler)) |
| Included Files | Included Files list |
| XDebug | start and stop a Xdebug session (fork from [Nette-XDebug-Helper](https://github.com/jsmitka/Nette-XDebug-Helper)) |
| VendorVersions | version info from composer.json and composer.lock (fork from [vendor-versions](https://github.com/milo/vendor-versions)) |

***

# Install

**1.**

```bash
$ composer require semhoun/slim-tracy
```

**2.** goto 3 or if need Twig, Doctrine DBAL, Doctrine ORM, Eloquent ORM then:

**2.1** install it

```bash
$ composer require doctrine/dbal
$ composer require doctrine/orm
$ composer require slim/twig-view
$ composer require illuminate/database
```

**2.2** add to your dependencies 

**2.2.1** (Twig, Twig\_Profiler) and/or Eloquent ORM like:

```php
// Twig
return [
    Twig::class => static function (Settings $settings, \Twig\Profiler\Profile $profile): Twig {
        $view = Twig::create($settings->get('view.template_path'), $settings->get('view.twig'));
        if ($settings->get('debug')) {
            // Add extensions
            $view->addExtension(new \Twig\Extension\ProfilerExtension($profile));
            $view->addExtension(new \Twig\Extension\DebugExtension());
        }
        return $view;
    },

    // Doctrine DBAL and ORM
    \Doctrine\DBAL\Connection::class => static function (Settings $settings, Doctrine\ORM\Configuration $conf): Doctrine\DBAL\Connection {
        return \Doctrine\DBAL\DriverManager::getConnection($settings->get('doctrine.connection'), $conf);
    },
    // Doctrine Config used by entity manager and Tracy
    \Doctrine\ORM\Configuration::class => static function (Settings $settings): Doctrine\ORM\Configuration {
        if ($settings->get('debug')) {
            $queryCache = new ArrayAdapter();
            $metadataCache = new ArrayAdapter();
        } else {
            $queryCache = new PhpFilesAdapter('queries', 0, $settings->get('cache_dir'));
            $metadataCache = new PhpFilesAdapter('metadata', 0, $settings->get('cache_dir'));
        }

        $config = new \Doctrine\ORM\Configuration();
        $config->setMetadataCache($metadataCache);
        $driverImpl = new \Doctrine\ORM\Mapping\Driver\AttributeDriver($settings->get('doctrine.entity_path'), true);
        $config->setMetadataDriverImpl($driverImpl);
        $config->setQueryCache($queryCache);
        $config->setProxyDir($settings->get('cache_dir') . '/proxy');
        $config->setProxyNamespace('App\Proxies');

        if ($settings->get('debug')) {
            $config->setAutoGenerateProxyClasses(true);
        } else {
            $config->setAutoGenerateProxyClasses(false);
        }

        return $config;
    },
    // Doctrine EntityManager.
    EntityManager::class => static function (\Doctrine\ORM\Configuration $config, \Doctrine\DBAL\Connection $connection): EntityManager {
        return new EntityManager($connection, $config);
    },
	EntityManagerInterface::class => DI\get(EntityManager::class),
]
```

**2.2.2** Eloquent ORM like:
```php
// Register Eloquent single connection
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($cfg['settings']['db']['connections']['mysql']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule::connection()->enableQueryLog();

```

**3.** register middleware

```php
$app->add(SlimTracy\Middlewares\TracyMiddleware($app, $tracySettings));
```

**4.** register route if you plan use PTY Console

```php
$app->post('/console', 'SlimTracy\Controllers\SlimTracyConsole:index');
```

also copy you want `jquery.terminal.min.js` & `jquery.terminal.min.css`  from vendor/semhoun/runtracy/web and correct path in 'settings' below, or set config with CDN. 
```php
'ConsoleTerminalJs' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery.terminal/2.42.2/js/jquery.terminal.min.js',
'ConsoleTerminalCss' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery.terminal/2.42.2/css/jquery.terminal.min.css',
```

add jquery from local or from CDN (https://code.jquery.com/) or copy/paste
```html
<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
    crossorigin="anonymous"></script>
```

**5.** add to your settings Debugger initialisation and 'tracy' section.

```php
use Tracy\Debugger;

Debugger::enable(Debugger::DEVELOPMENT);

return [
    'settings' => [
                'addContentLengthHeader' => false// debugbar possible not working with true
    ... // ...
    ... // ...

        'tracy' => [
            'showPhpInfoPanel' => 0,
            'showSlimRouterPanel' => 0,
            'showSlimEnvironmentPanel' => 0,
            'showSlimRequestPanel' => 1,
            'showSlimResponsePanel' => 1,
            'showSlimContainer' => 0,
            'showEloquentORMPanel' => 0,
            'showTwigPanel' => 0,
            'showDoctrinePanel' => 0,
            'showProfilerPanel' => 0,
            'showVendorVersionsPanel' => 0,
            'showXDebugHelper' => 0,
            'showIncludedFiles' => 0,
            'showConsolePanel' => 0,
            'configs' => [
                // XDebugger IDE key
                'XDebugHelperIDEKey' => 'PHPSTORM',
                 // Activate the console
                 'ConsoleEnable' => 1,
                // Disable login (don't ask for credentials, be careful) values( 1 || 0 )
                'ConsoleNoLogin' => 0,
                // Multi-user credentials values( ['user1' => 'password1', 'user2' => 'password2'] )
                'ConsoleAccounts' => [
                    'dev' => '34c6fceca75e456f25e7e99531e2425c6c1de443'// = sha1('dev')
                ],
                // Password hash algorithm (password must be hashed) values('md5', 'sha256' ...)
                'ConsoleHashAlgorithm' => 'sha1',
                // Home directory (multi-user mode supported) values ( var || array )
                // '' || '/tmp' || ['user1' => '/home/user1', 'user2' => '/home/user2']
                'ConsoleHomeDirectory' => DIR,
                // terminal.js full URI
                'ConsoleTerminalJs' => '/assets/js/jquery.terminal.min.js',
                // terminal.css full URI
                'ConsoleTerminalCss' => '/assets/css/jquery.terminal.min.css',
                'ConsoleFromEncoding' => 'CP866', // or false
                'ProfilerPanel' => [
                    // Memory usage 'primaryValue' set as Profiler::enable() or Profiler::enable(1)
//                    'primaryValue' =>                   'effective',    // or 'absolute'
                    'show' => [
                        'memoryUsageChart' => 1, // or false
                        'shortProfiles' => true, // or false
                        'timeLines' => true // or false
                    ]
                ],
                'Container' => [
                // Container entry name
                    'Doctrine' => \Doctrine\ORM\Configuration::class, // must be a configuration DBAL or ORM
                    'Twig' => \Twig\Profiler\Profile::class,
                ],
            ]
        ]
```

see config examples in vendor/semhoun/runtracy/Example

![example](ss/panel_selector.png "Panel Selector")

![example](ss/twig.png "Twig panel")

![example](ss/eloquent.png "Eloquent ORM panel")

![example](ss/container.png "Slim Container panel")

![example](ss/request.png "Slim Request panel")

![example](ss/response.png "Slim Response panel ")

![example](ss/router.png "Slim Router panel ")

![example](ss/vendor_versions_panel.png "Vendor Versions Panel")

![example](ss/included_files.png "Included Files Panel")

![example](ss/phpinfo.png "phpinfo Panel")

![example](ss/console_panel.png "PTY Console Panel")

Profiler Example in [slim-skeleton-mvc](https://github.com/semhoun/slim-skeleton-mvc)
`public/index.php`

```php
<?php
use App\Services\Settings;
use DI\ContainerBuilder;

// Set the absolute path to the root directory.
$rootPath = realpath(__DIR__ . '/..');

// Include the composer autoloader.
include_once $rootPath . '/vendor/autoload.php';

SlimTracy\Helpers\Profiler\Profiler::enable();
SlimTracy\Helpers\Profiler\Profiler::start('App');

// At this point the container has not been built. We need to load the settings manually.
SlimTracy\Helpers\Profiler\Profiler::start('loadSettings');
$settings = Settings::load();
SlimTracy\Helpers\Profiler\Profiler::finish('loadSettings');

// DI Builder
$containerBuilder = new ContainerBuilder();

if (! $settings->get('debug')) {
    // Compile and cache container.
    $containerBuilder->enableCompilation($settings->get('cache_dir').'/container');
}

// Set up dependencies
SlimTracy\Helpers\Profiler\Profiler::start('initDeps');
$containerBuilder->addDefinitions($rootPath.'/config/dependencies.php');
SlimTracy\Helpers\Profiler\Profiler::finish('initDeps');

// Build PHP-DI Container instance
 SlimTracy\Helpers\Profiler\Profiler::start('diBuild');
$container = $containerBuilder->build();
SlimTracy\Helpers\Profiler\Profiler::finish('diBuild');

// Instantiate the app
$app = \DI\Bridge\Slim\Bridge::create($container);

// Register middleware
SlimTracy\Helpers\Profiler\Profiler::start('initMiddleware');
$middleware = require $rootPath . '/config/middleware.php';
$middleware($app);
SlimTracy\Helpers\Profiler\Profiler::finish('initMiddleware');

// Register routes
SlimTracy\Helpers\Profiler\Profiler::start('initRoutes');
$routes = require $rootPath . '/config/routes.php';
$routes($app);
SlimTracy\Helpers\Profiler\Profiler::finish('initRoutes');

// Set the cache file for the routes. Note that you have to delete this file
// whenever you change the routes.
if (! $settings->get('debug')) {
    $app->getRouteCollector()->setCacheFile($settings->get('cache_dir').'/route');
}

// Add the routing middleware.
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Run the app
$app->run();
SlimTracy\Helpers\Profiler\Profiler::finish('App');

```

![example](ss/profiler_panel.png "Profiler Panel")

***

## Tests

```bash
$ cd vendor/semhoun/runtracy
$ composer update
$ vendor/bin/phpunit
```



## Credits

*   [https://github.com/runcmf/runtracy](https://github.com/runcmf/runtracy)
*   [https://github.com/semhoun/slim-tracy](https://github.com/semhoun/slim-tracy)

***

## License

```bash
Copyright 2016-2022 1f7.wizard@gmail.com.
Copyright 2024 Nathanaël Semhoun (nathanael@semhoun.net).

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

[ico-version]: https://img.shields.io/packagist/v/semhoun/runtracy.svg

[ico-license]: https://img.shields.io/badge/license-Apache%202-green.svg

[ico-downloads]: https://img.shields.io/packagist/dt/semhoun/runtracy.svg

[link-packagist]: https://packagist.org/packages/semhoun/runtracy

[link-license]: http://www.apache.org/licenses/LICENSE-2.0

[link-downloads]: https://github.com/semhoun/runtracy
