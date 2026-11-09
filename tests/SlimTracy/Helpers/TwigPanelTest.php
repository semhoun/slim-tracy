<?php

namespace Tests\SlimTracy\Helpers;

use Slim\Views\Twig;
use SlimTracy\Helpers\TwigPanel;
use Tests\BaseTestCase;
use Twig\Extension\DebugExtension;
use Twig\Extension\ProfilerExtension;
use Twig\Loader\ArrayLoader;
use Twig\Profiler\Profile;

/** Verifies Twig profiler data from a real rendered template. */
class TwigPanelTest extends BaseTestCase
{
    /** Verify a real rendered Twig template is represented in the profiler panel. */
    public function testTwigPanel(): void
    {
        $profile = new Profile();
        $view = new Twig(new ArrayLoader(['hello' => 'Hello {{ name }}']), ['debug' => true]);
        $view->addExtension(new ProfilerExtension($profile));
        $view->addExtension(new DebugExtension());
        $this->assertSame('Hello World', $view->fetch('hello', ['name' => 'World']));
        $this->assertCount(1, $profile->getProfiles());

        $panel = new TwigPanel($profile, ['slim' => \Slim\App::VERSION]);
        $this->assertInstanceOf(\Tracy\IBarPanel::class, $panel);
        $this->assertMatchesRegularExpression('#Twig Info#', $panel->getTab());
        $this->assertMatchesRegularExpression('#Twig profiler result#', $panel->getPanel());
        $this->assertStringContainsString('hello', $panel->getPanel());
    }
}
