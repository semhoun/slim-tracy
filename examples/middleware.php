<?php

declare(strict_types=1);

use App\Middleware\BaseUrlMiddleware;
use App\Middleware\SessionMiddleware;
use App\Services\Settings;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use SlimTracy\Profiling\Configuration;
use SlimTracy\Profiling\Routes;

return static function (App $app): void {
    $container = $app->getContainer();
    $settings = $container->get(Settings::class);

    $app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

    $app->add($container->get(SessionMiddleware::class));
    $app->add($container->get(BaseUrlMiddleware::class));

    $app->add(new \RKA\Middleware\ProxyDetection());

    // These renderers belong to the host application, not this library.
    $errorMiddleware = $app->addErrorMiddleware(false, true, false);
    $errorHandler = $errorMiddleware->getDefaultErrorHandler();
    $errorHandler->registerErrorRenderer('text/html', \App\Renderer\HtmlErrorRenderer::class);
    $errorHandler->registerErrorRenderer('application/json', \App\Renderer\JsonErrorRenderer::class);
    $errorHandler->setDefaultErrorRenderer('application/json', \App\Renderer\JsonErrorRenderer::class);

    // Added last, Tracy wraps handled Slim errors without replacing their JSON responses.
    $tracySettings = $settings->get('tracy');
    $app->add(new SlimTracy\Middlewares\TracyMiddleware($app, $tracySettings));
    $configuration = Configuration::fromSettings($tracySettings);
    Routes::register($app, $configuration);
};
