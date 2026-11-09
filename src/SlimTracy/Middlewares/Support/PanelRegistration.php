<?php

declare(strict_types=1);

namespace SlimTracy\Middlewares\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use SlimTracy\Helpers\PanelSelector;
use Tracy\Debugger;

/** Registers selected panels only after the downstream handler has completed. */
final readonly class PanelRegistration
{
    /** @param array<string, scalar|array|object|null> $settings */
    public function __construct(private App $app, private array $settings)
    {
    }

    /**
     * Adds available, selected panels after the downstream response is complete.
     *
     * Tracy exposes its shared bar statically; selection, availability, and container helpers are stateless policies.
     *
     * @param ServerRequestInterface $request  Current request containing the panel-selection cookie.
     * @param ResponseInterface      $response Completed response to describe in the toolbar.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $config = $this->settings['configs'] ?? [];
        $selected = PanelSettings::resolve($request, $this->settings);
        $consoleEnabled = ! empty($selected['showConsolePanel']) || ! empty($config['ConsoleNoLogin']);
        $selected['showConsolePanel'] = (int) $consoleEnabled;
        $container = WritableContainer::fromApp($this->app);
        $available = static fn (string $name): bool => OptionalIntegrations::available($name, $container, $config);
        $factories = (new PanelRegistry($this->app, $config))->factories($request, $response);
        foreach (array_filter($factories, $available, ARRAY_FILTER_USE_KEY) as $name => $factory) {
            if (! empty($selected[$name])) {
                Debugger::getBar()->addPanel($factory());
            }
        }

        $choices = array_filter(array_diff_key($this->settings, ['configs' => null]), $available, ARRAY_FILTER_USE_KEY);
        Debugger::getBar()->addPanel(new PanelSelector($selected, $choices));
    }
}
