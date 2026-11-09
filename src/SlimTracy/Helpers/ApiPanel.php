<?php

declare(strict_types=1);

namespace SlimTracy\Helpers;

use SlimTracy\Profiling\Configuration;
use Tracy\IBarPanel;

/** Displays the shared development API request history. */
final readonly class ApiPanel implements IBarPanel
{
    /**
     * Configures the shared API history view.
     *
     * @param Configuration $configuration Effective profiler settings.
     */
    public function __construct(private Configuration $configuration)
    {
    }

    /**
     * Returns the toolbar label.
     *
     * @return string Toolbar HTML, or an empty string when profiling is disabled.
     */
    public function getTab(): string
    {
        return $this->configuration->isEnabled()
            ? '<span title="Shared API profiles" data-slim-tracy-client' . $this->attributes() . '>API</span>' : '';
    }

    /**
     * Returns a text-only client view, populated after Tracy initialization.
     *
     * @return string History container HTML, or an empty string when profiling is disabled.
     */
    public function getPanel(): string
    {
        if (! $this->configuration->isEnabled()) {
            return '';
        }

        return '<h1>API Profiles</h1><div class="tracy-inner" data-slim-tracy-api'
            . $this->attributes() . '><p>Shared development history. Loading profiles...</p></div>';
    }

    /**
     * Keeps installation settings on the eagerly rendered tab and lazy panel consistent.
     *
     * @return string Escaped HTML attributes shared by the tab and panel.
     */
    private function attributes(): string
    {
        $path = htmlspecialchars($this->configuration->urlPath(), ENT_QUOTES);
        // Incoming CORS origins are frontend origins, not outbound API capture targets.
        return ' data-base-url="' . $path . '" data-allowed-origins="[]" data-client-enabled="true"';
    }
}
