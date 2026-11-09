<?php

declare(strict_types=1);

use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use SlimTracy\Middlewares\TracyMiddleware;
use SlimTracy\Profiling\Configuration;
use SlimTracy\Profiling\Routes;
use Tracy\Debugger;

require __DIR__ . '/../../vendor/autoload.php';

$mode = getenv('FIXTURE_MODE') ?: 'enabled';
$settings = [
    'showSlimRequestPanel' => true,
    'showSlimResponsePanel' => true,
    'showPhpInfoPanel' => true,
    'showConsolePanel' => true,
    'configs' => [
        'ShowBar' => true,
        'ConsoleEnable' => true,
        'Debugger' => ['mode' => $mode === 'production' ? Debugger::Production : Debugger::Development],
        'AjaxProfiler' => [
            'enabled' => $mode !== 'disabled',
            'origins' => ['http://127.0.0.1:8781', 'http://127.0.0.1:8782'],
            'storageDirectory' => getenv('FIXTURE_STORAGE'),
        ],
    ],
];

$container = new Container();
AppFactory::setContainer($container);
AppFactory::setResponseFactory(new Psr17Factory());
$app = AppFactory::create();
Debugger::getBar()->addPanel(new class implements Tracy\IBarPanel {
    /** Return the sentinel tab used to detect host-panel leakage. */
    public function getTab(): string
    {
        return '<span>SECRET_HOST_PANEL</span>';
    }

    /** Return the sentinel body used to detect host-panel leakage. */
    public function getPanel(): string
    {
        return '<h1>SECRET_HOST_PANEL</h1>';
    }
}, 'fixture-host-secret');

$app->get('/health', function ($request, $response) {
    $response->getBody()->write('ready');
    return $response->withHeader('Content-Type', 'text/plain');
});
$app->map(['GET', 'POST'], '/{endpoint:echo|untrusted}', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'method' => $request->getMethod(),
        'body' => (string) $request->getBody(),
        'nativeHeader' => $request->getHeaderLine('X-Tracy-Ajax'),
        'authorization' => $request->getHeaderLine('Authorization'),
    ], JSON_THROW_ON_ERROR));
    return $response->withHeader('Content-Type', 'application/json')->withHeader('X-Host-Cors', 'preserved');
});
$app->get('/slow', function ($request, $response) {
    $started = hrtime(true);
    usleep(350000);
    $response->getBody()->write(json_encode([
        'slow' => true, 'started' => $started, 'finished' => hrtime(true),
        'hasTracyCookie' => isset($_COOKIE['tracy-session']),
        'processId' => getmypid(),
    ], JSON_THROW_ON_ERROR));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/error', function (): never {
    throw new RuntimeException('Fixture Slim error');
});
$app->get('/{page:twig|loader}', function ($request, $response) {
    $twig = new Twig\Environment(new Twig\Loader\ArrayLoader([
        'page' => '<!doctype html><html><head><title>Twig fixture</title></head>'
            . '<body><h1>{{ title }}</h1></body></html>',
    ]));
    $html = $twig->render('page', ['title' => 'Real Twig page']);
    if ($request->getUri()->getPath() === '/loader') {
        ob_start();
        Debugger::renderLoader();
        $html = str_replace('</head>', ob_get_clean() . '</head>', $html);
    }
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$tracy = new TracyMiddleware($app, $settings);
$configuration = Configuration::fromSettings($settings);
Routes::register($app, $configuration);
$app->addRoutingMiddleware();
$errors = $app->addErrorMiddleware(false, false, false);
$errors->setDefaultErrorHandler(function ($request, $exception) use ($app): ResponseInterface {
    $response = $app->getResponseFactory()->createResponse(500);
    $response->getBody()->write('{"error":"Slim handled"}');
    return $response->withHeader('Content-Type', 'application/json');
});
// Tracy wraps Slim's error middleware so handled errors retain a correlated JSON response.
$app->add($tracy);
// Application CORS is independent of profiler route CORS and preserves the host's policy.
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($app) {
    $response = $request->getMethod() === 'OPTIONS' && !str_starts_with($request->getUri()->getPath(), '/_tracy/')
        ? $app->getResponseFactory()->createResponse(204)
        : $handler->handle($request);
    if (
        $request->getHeaderLine('Origin') === 'http://127.0.0.1:8782'
        && !str_starts_with($request->getUri()->getPath(), '/_tracy/')
    ) {
        return $response->withHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:8782')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Expose-Headers', 'X-SlimTracy-Profile, X-Host-Cors')
            ->withHeader('Vary', 'Origin');
    }
    return $response;
});
$factory = new Psr17Factory();
parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
$request = $factory->createServerRequest(
    $_SERVER['REQUEST_METHOD'],
    'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    $_SERVER
)
    ->withQueryParams($query)
    ->withBody($factory->createStream(file_get_contents('php://input')));
foreach (getallheaders() as $name => $value) {
    $request = $request->withHeader($name, $value);
}
$app->run($request);
