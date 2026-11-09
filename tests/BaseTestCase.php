<?php

namespace Tests;

/**
 * Class BaseTestCase
 * @package Tests
 */
class BaseTestCase extends \PHPUnit\Framework\TestCase
{
    public $cfg = null;

    protected function setUp(): void
    {
        $this->cfg = require __DIR__ . '/Settings.php';
    }

    protected function callProtectedMethod($name, $classname, $params, array $constructorArgs = [])
    {
        $class = new \ReflectionClass($classname);
        $method = $class->getMethod($name);
        $obj = new $classname(...$constructorArgs);
        return $method->invokeArgs($obj, $params);
    }

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
            strtoupper($requestMethod), $requestUri
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

    protected function createApp(): \Slim\App
    {
        return \Slim\Factory\AppFactory::createFromContainer(new \DI\Container());
    }
}
