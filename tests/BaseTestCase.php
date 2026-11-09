<?php

namespace Tests;

/**
 * Class BaseTestCase
 * @package Tests
 */
class BaseTestCase extends \PHPUnit\Framework\TestCase
{
    /** @var array|null Repository test settings loaded before each test. */
    public $cfg = null;

    /** Load a fresh copy of the test settings. */
    protected function setUp(): void
    {
        $this->cfg = require __DIR__ . '/Settings.php';
    }

    /** Invoke a protected method on an instance with explicit constructor arguments. */
    protected function callProtectedMethod($name, $classname, $params, array $constructorArgs = [])
    {
        $class = new \ReflectionClass($classname);
        $method = $class->getMethod($name);
        $obj = new $classname(...$constructorArgs);
        return $method->invokeArgs($obj, $params);
    }

    /** Return both a reflected method result and the instance used to invoke it. */
    protected function callProtectedMethodReturnObj($name, $classname, $params)
    {
        $class = new \ReflectionClass($classname);
        $method = $class->getMethod($name);
        $obj = new $classname();
        return [
            $obj,
            $method->invokeArgs($obj, $params)
        ];
    }

    /**
     * Process the application given a request method and URI
     *
     * @param string            $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string            $requestUri    the request URI
     * @param array|object|null $requestData   the request data
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function runApp($requestMethod, $requestUri, $requestData = null)
    {
        $request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createServerRequest(
            strtoupper($requestMethod),
            $requestUri
        );
        $_SESSION = [];
        // Add request data, if it exists
        if (isset($requestData)) {
            $request = $request->withParsedBody($requestData);
        }
        $app = $this->createApp();
        $app->getContainer()->set('tracy.settings', $this->cfg['settings']['tracy']);
        $app->post('/console', 'SlimTracy\Controllers\SlimTracyConsole:index');
        // Process the application
        $response = $app->handle($request);

        // Return the response
        return $response;
    }

    /**
     * Build a writable-container app through Slim's public static factory.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function createApp(): \Slim\App
    {
        return \Slim\Factory\AppFactory::createFromContainer(new \DI\Container());
    }
}
