<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Middlewares;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SlimTracy\Middlewares\Support\DebuggerBootstrap;
use SlimTracy\Middlewares\Support\NativeSessionIsolation;
use Tracy\Debugger;
use Tracy\FileSession;

/** Verifies native storage selection using real incoming request globals. */
#[RunTestsInSeparateProcesses]
final class NativeSessionIsolationTest extends TestCase
{
    /**
     * Bootstrap's static API must inspect the exact server and cookie boundary.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testApiRequestNeverOpensNativeCookieStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/api/items';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_COOKIE['tracy-session'] = 'valid12345';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(NativeSessionIsolation::class, Debugger::getSessionStorage());
        self::assertFalse(Debugger::getSessionStorage()->isAvailable());
        self::assertNull(Debugger::dispatch());
    }

    /**
     * Bootstrap's static API selects native storage from incoming navigation headers.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testDocumentNavigationRetainsNativeDeferredLoaderStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/page';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }

    /**
     * Bootstrap's static API must preserve native dispatch from actual query globals.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testNativeHostContentRequestCanStillUseDeferredStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/page?_tracy_bar=content.example';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'script';
        $_GET['_tracy_bar'] = 'content.example';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }

    /**
     * Bootstrap's static API isolates the reserved URL from actual server globals.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testDebugDocumentAlwaysUsesIsolatedNonPersistentStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/_tracy/api';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(NativeSessionIsolation::class, Debugger::getSessionStorage());
    }

    /**
     * Bootstrap's static API leaves native storage unchanged for an incoming API request.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function testDisabledProfilerDoesNotReplaceHostNativeStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/api/items';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }
}
