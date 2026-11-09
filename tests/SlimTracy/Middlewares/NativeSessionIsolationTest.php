<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Middlewares;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SlimTracy\Middlewares\Support\DebuggerBootstrap;
use SlimTracy\Middlewares\Support\NativeSessionIsolation;
use Tracy\Debugger;
use Tracy\FileSession;

#[RunTestsInSeparateProcesses]
final class NativeSessionIsolationTest extends TestCase
{
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

    public function testDocumentNavigationRetainsNativeDeferredLoaderStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/page';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }

    public function testNativeHostContentRequestCanStillUseDeferredStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/page?_tracy_bar=content.example';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'script';
        $_GET['_tracy_bar'] = 'content.example';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }

    public function testDebugDocumentAlwaysUsesIsolatedNonPersistentStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/_tracy/api';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => true]]);
        self::assertInstanceOf(NativeSessionIsolation::class, Debugger::getSessionStorage());
    }

    public function testDisabledProfilerDoesNotReplaceHostNativeStorage(): void
    {
        Debugger::$productionMode = Debugger::Development;
        $_SERVER['REQUEST_URI'] = '/api/items';
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
        DebuggerBootstrap::configure(['AjaxProfiler' => ['enabled' => false]]);
        self::assertInstanceOf(FileSession::class, Debugger::getSessionStorage());
    }
}
