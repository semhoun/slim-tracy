<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SlimTracy\Helpers;

use Tracy\IBarPanel;

/** Displays installed Composer packages and requested version constraints. */
final class VendorVersionsPanel implements IBarPanel
{
    /**
     * @var string|null Most recent Composer directory or document error.
     */
    private ?string $error;

    /**
     * @var string|null Resolved directory containing Composer project files.
     */
    private readonly ?string $dir;

    /**
     * Resolves the Composer project directory for package inspection.
     *
     * ComposerDirectory is a stateless resolver with a public static API.
     *
     * @param string|null $composerLockDir Explicit project directory, or null for discovery.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(?string $composerLockDir = null)
    {
        $resolved = ComposerDirectory::resolve($composerLockDir);
        $this->dir = $resolved['directory'];
        $this->error = $resolved['error'];
    }

    /**
     * Renders the package-version toolbar tab.
     *
     * @return string Toolbar HTML identifying the Composer package panel.
     */
    public function getTab(): string
    {
        return (require __DIR__ . '/../Templates/VendorTab.phtml')([]);
    }

    /**
     * Loads package versions and renders their requested constraints.
     *
     * ComposerProject exposes stateless project loading through its public static API.
     *
     * @return string Package table HTML including any project-loading error.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getPanel(): string
    {
        $jsonFile = $this->dir . \DIRECTORY_SEPARATOR . 'composer.json';
        $lockFile = $this->dir . \DIRECTORY_SEPARATOR . 'composer.lock';
        $project = ComposerProject::load($this->dir, $this->error);
        $this->error = $project['error'];

        return (require __DIR__ . '/../Templates/VendorVersionsPanel.phtml')(
            $project['packages'],
            $this->error,
            $jsonFile,
            $lockFile
        );
    }

    /**
     * Exposes the most recent project-loading error.
     *
     * @return string|null Error message, or null when project loading succeeded.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
