<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Controllers;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use SlimTracy\Controllers\SlimTracyConsole;
use SlimTracy\Controllers\Support\ConsoleOutput;
use Tests\BaseTestCase;

final class SlimTracyConsoleTest extends BaseTestCase
{
    public function testEmptyRequestReturnsParseError(): void
    {
        $response = $this->controller()->index(new ServerRequest('POST', '/console'), new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('x-requested-with, content-type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame(-32700, $data['error']['code']);
        self::assertArrayNotHasKey('result', $data);
    }

    public function testConsoleLoginUsesPsrRequestBody(): void
    {
        $input = '{"jsonrpc":"2.0","method":"login","params":["dev","dev"],"id":1}';
        $request = new ServerRequest('POST', '/console', [], $input);
        $response = $this->controller()->index($request, new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $data['id']);
        self::assertArrayHasKey('token', $data['result']);
        self::assertArrayNotHasKey('error', $data);
    }

    public function testExplicitQueryRawRequestIsSupported(): void
    {
        $request = (new ServerRequest('POST', '/console'))->withQueryParams([
            'rawRequest' => '{"jsonrpc":"2.0","method":"login","params":["dev","dev"],"id":2}',
        ]);
        $response = $this->controller()->index($request, new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(2, $data['id']);
        self::assertArrayHasKey('token', $data['result']);
    }

    public function testMalformedQueryRawRequestReturnsParseError(): void
    {
        $request = (new ServerRequest('POST', '/console'))->withQueryParams(['rawRequest' => ['bad']]);
        $response = $this->controller()->index($request, new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(-32700, $data['error']['code']);
    }

    public function testRequestMethodIsTakenFromPsrRequest(): void
    {
        $response = $this->controller()->index(new ServerRequest('GET', '/console'), new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(-32600, $data['error']['code']);
    }

    public function testServiceMapUsesExplicitQueryAndUri(): void
    {
        $request = (new ServerRequest('GET', 'https://example.test/console?smd#ignored'))
            ->withQueryParams(['smd' => '']);
        $response = $this->controller()->index($request, new Response());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('https://example.test/console', $data['target']);
        self::assertArrayHasKey('login', $data['services']);
        self::assertArrayNotHasKey('index', $data['services']);
        self::assertArrayNotHasKey('execute', $data['services']);
    }

    public function testOutputEncodingPreservesErrorsAndConvertsOnlyOutput(): void
    {
        $text = "\u{041F}\u{0440}\u{0438}\u{0432}\u{0435}\u{0442}";
        $output = mb_convert_encoding($text, 'CP866', 'UTF-8');
        $response = ['jsonrpc' => '2.0', 'id' => 3, 'result' => ['output' => $output, 'token' => 'unchanged']];

        self::assertSame($text, ConsoleOutput::convert($response, 'CP866')['result']['output']);
        self::assertSame('unchanged', ConsoleOutput::convert($response, 'CP866')['result']['token']);
        self::assertSame($response, ConsoleOutput::convert($response, ''));
        self::assertSame($response, ConsoleOutput::convert($response, 'UTF-8'));
        $error = ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32700]];
        self::assertSame($error, ConsoleOutput::convert($error, 'CP866'));
    }

    private function controller(): SlimTracyConsole
    {
        $container = $this->createApp()->getContainer();
        $container->set('tracy.settings', $this->cfg['settings']['tracy']);
        return new SlimTracyConsole($container);
    }
}
