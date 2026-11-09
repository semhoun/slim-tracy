<?php

declare(strict_types=1);

// Local documentation fixture: php -S 127.0.0.1:8790 tools/screenshots/app.php
require __DIR__ . '/../../vendor/autoload.php';

use DI\Container;
use Illuminate\Database\Capsule\Manager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Factory\AppFactory;
use SlimTracy\Helpers\Profiler\Profiler;
use SlimTracy\Middlewares\TracyMiddleware;
use Tracy\Debugger;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/jquery.js' => getenv('SCREENSHOT_JQUERY') ?: '/usr/share/javascript/jquery/jquery.min.js',
    '/terminal.js' => __DIR__ . '/../../web/js/jquery.terminal.min.js',
    '/terminal.css' => __DIR__ . '/../../web/css/jquery.terminal.min.css',
];
if (isset($assets[$path])) {
    header('Content-Type: ' . (str_ends_with($path, '.css') ? 'text/css' : 'text/javascript'));
    readfile($assets[$path]);
    return;
}

Profiler::enable();
Profiler::start('Application');
Profiler::start('Container');
$container = new Container();
$profile = new Twig\Profiler\Profile();
$container->set('twig.profile', $profile);
$container->set('settings', ['name' => 'Slim Tracy', 'environment' => 'development']);
$capsule = new Manager();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$db = $capsule->getConnection();
$db->statement('CREATE TABLE projects (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
$db->table('projects')->insert([
    ['name' => 'Website', 'status' => 'published'],
    ['name' => 'Customer portal', 'status' => 'in progress'],
    ['name' => 'API', 'status' => 'published'],
]);
$db->enableQueryLog();
Profiler::finish('Container');

AppFactory::setContainer($container);
AppFactory::setResponseFactory(new Psr17Factory());
$app = AppFactory::create();
$settings = array_fill_keys([
    'showEloquentORMPanel', 'showTwigPanel', 'showPhpInfoPanel', 'showSlimEnvironmentPanel',
    'showSlimContainer', 'showSlimRouterPanel', 'showSlimRequestPanel', 'showSlimResponsePanel',
    'showVendorVersionsPanel', 'showIncludedFiles', 'showConsolePanel', 'showProfilerPanel',
], 1);
$settings['configs'] = [
    'ShowBar' => true,
    'Debugger' => ['mode' => Debugger::Development],
    'Container' => ['Twig' => 'twig.profile'],
    'ProfilerPanel' => [],
    'ConsoleEnable' => true,
    'ConsoleNoLogin' => 0,
    'ConsoleTerminalJs' => '/terminal.js',
    'ConsoleTerminalCss' => '/terminal.css',
];
$app->add(new TracyMiddleware($app, $settings));
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    // This library is the fixture's Composer root, rather than a vendor subdirectory.
    Debugger::getBar()->addPanel(
        new SlimTracy\Helpers\VendorVersionsPanel(__DIR__ . '/../..'),
        SlimTracy\Helpers\VendorVersionsPanel::class
    );
    return $response;
});
$app->get('/', function ($request, $response) use ($db, $profile) {
    Profiler::start('Load projects');
    $projects = $db->table('projects')->orderBy('name')->get();
    $db->table('projects')->where('status', 'published')->count();
    Profiler::finish('Load projects');
    Profiler::start('Render Twig');
    $twig = new Twig\Environment(new Twig\Loader\ArrayLoader([
        'layout.html.twig' => '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Slim Tracy</title><script src="/jquery.js"></script>'
            . '<style>body{margin:60px;font:16px system-ui;color:#243044;background:#f5f7fa}'
            . 'main{max-width:850px;margin:auto}h1{font-size:36px}p{color:#657085}'
            . 'table{width:100%;border-collapse:collapse;background:white}'
            . 'th,td{text-align:left;padding:16px;border-bottom:1px solid #e5e9ef}</style>'
            . '</head><body><main>{% block content %}{% endblock %}</main></body></html>',
        'projects.html.twig' => '{% extends "layout.html.twig" %}{% block content %}'
            . '<h1>Slim Tracy</h1><p>A Slim 4 application with Twig and Eloquent.</p>'
            . '<h2>Projects</h2><table><tr><th>Project</th><th>Status</th></tr>'
            . '{% for project in projects %}{% include "project.html.twig" %}{% endfor %}'
            . '</table>{% endblock %}',
        'project.html.twig' => '<tr><td>{{ project.name }}</td><td>{{ project.status }}</td></tr>',
    ]));
    $twig->addExtension(new Twig\Extension\ProfilerExtension($profile));
    $response->getBody()->write($twig->render('projects.html.twig', ['projects' => $projects]));
    Profiler::finish('Render Twig');
    Profiler::finish('Application');
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
})->setName('projects.index');
$app->get('/projects/{id}', fn ($request, $response) => $response)->setName('projects.show');
$app->post('/projects', fn ($request, $response) => $response)->setName('projects.create');
$app->addRoutingMiddleware();
$request = (new Psr17Factory())->createServerRequest(
    $_SERVER['REQUEST_METHOD'],
    'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    $_SERVER
)->withQueryParams($_GET);
foreach (getallheaders() as $name => $value) {
    $request = $request->withHeader($name, $value);
}
$app->run($request);
