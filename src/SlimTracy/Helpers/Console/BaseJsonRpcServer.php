<?php

declare(strict_types=1);

namespace SlimTracy\Helpers\Console;

/**
 * JSON RPC Server for Eaze.
 *
 * Reads $_GET['rawRequest'] or php://input for Request Data
 *
 * @see       http://www.jsonrpc.org/specification
 * @see       http://dojotoolkit.org/reference-guide/1.8/dojox/rpc/smd.html
 *
 * @author     Sergeyfast
 */
class BaseJsonRpcServer
{
    protected const PARSEERROR = -32700;

    protected const INVALIDREQUEST = -32600;

    protected const METHODNOTFOUND = -32601;

    protected const INVALIDPARAMS = -32602;

    protected const INTERNALERROR = -32603;

    /**
     * Content Type.
     */
    public string $ContentType = 'application/json';

    /**
     * Allow Cross-Domain Requests.
     */
    public bool $IsXDR = true;

    /**
     * Max Batch Calls.
     */
    public int $MaxBatchCalls = 10;

    /**
     * Exposed Instances.
     *
     * @var array<object> namespace => method
     */
    protected array $instances = [];

    /**
     * Decoded Json Request.
     */
    protected array|object $request;

    /**
     * Array of Received Calls.
     */
    protected array $calls = [];

    /**
     * Array of Responses for Calls.
     */
    protected array $response = [];

    /**
     * Has Calls Flag (not notifications).
     */
    protected bool $hasCalls = false;

    /**
     * Hidden Methods.
     */
    protected array $hiddenMethods = [
        'execute', '__construct', 'registerinstance',
    ];

    /**
     * Error Messages.
     */
    protected array $errorMessages = [
        self::PARSEERROR => 'Parse error',
        self::INVALIDREQUEST => 'Invalid Request',
        self::METHODNOTFOUND => 'Method not found',
        self::INVALIDPARAMS => 'Invalid params',
        self::INTERNALERROR => 'Internal error',
    ];

    /**
     * Is Batch Call in using.
     */
    private bool $isBatchCall = false;

    /**
     * Cached Reflection Methods.
     *
     * @var array<\ReflectionMethod>
     */
    private array $reflectionMethods = [];

    /**
     * Create new Instance.
     */
    public function __construct(?object $instance = null)
    {
        if (get_parent_class($this)) {
            $this->registerInstance($this, '');
        } elseif ($instance) {
            $this->registerInstance($instance, '');
        }
    }

    /**
     * Register Instance.
     *
     * @param string $namespace default is empty string
     *
     * @return $this
     */
    public function registerInstance(object $instance, string $namespace = ''): static
    {
        $this->instances[$namespace] = $instance;
        if (is_object($this->instances[$namespace])) {
            $this->instances[$namespace]->errorMessages = $this->errorMessages;
        }

        return $this;
    }

    /**
     * Get Instances.
     *
     * @return array<\object>
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /**
     * Handle Requests.
     * @return mixed[]
     */
    public function execute(): array
    {
        $ret = [];
        do {
            // check for SMD Discovery request
            if (array_key_exists('smd', $_GET)) {
                $this->response[] = $this->getServiceMap();
                $this->hasCalls = true;

                break;
            }

            $error = $this->getRequest();
            if ($error) {
                $this->response[] = $this->getError($error);
                $this->hasCalls = true;

                break;
            }

            foreach ($this->calls as $call) {
                $error = $this->validateCall($call);
                if ($error) {
                    $this->response[] = $this->getError($error[0], $error[1], $error[2]);
                    $this->hasCalls = true;
                } else {
                    $result = $this->processCall($call);
                    if ($result) {
                        $this->response[] = $result;
                        $this->hasCalls = true;
                    }
                }
            }
        } while (false);

        // flush response
        if ($this->hasCalls) {
            if (! $this->isBatchCall) {
                $this->response = reset($this->response);
            }

            // Allow Cross Domain Requests
            if (!headers_sent() && $this->IsXDR) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: x-requested-with, content-type');
            }

            $ret = $this->response;
            $this->resetVars();
        }

        return $ret;
    }

    /**
     * Validate Request.
     *
     * @return int error
     */
    private function getRequest(): ?int
    {
        $error = null;

        do {
            if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $error = self::INVALIDREQUEST;

                break;
            }

            $request = empty($_GET['rawRequest']) ? file_get_contents('php://input') : $_GET['rawRequest'];
            $this->request = json_decode((string) $request, false);
            if ($this->request === null) {
                $error = self::PARSEERROR;

                break;
            }

            if ($this->request === []) {
                $error = self::INVALIDREQUEST;

                break;
            }

            // check for batch call
            if (is_array($this->request)) {
                if (count($this->request) > $this->MaxBatchCalls) {
                    $error = self::INVALIDREQUEST;

                    break;
                }

                $this->calls = $this->request;
                $this->isBatchCall = true;
            } else {
                $this->calls[] = $this->request;
            }
        } while (false);

        return $error;
    }

    /**
     * Get Error Response.
     */
    private function getError(int $code, mixed $id = null, null $data = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $this->errorMessages[$code] ?? $this->errorMessages[self::INTERNALERROR],
                'data' => $data,
            ],
        ];
    }

    /**
     * Check for jsonrpc version and correct method.
     */
    private function validateCall(\stdClass $call): ?array
    {
        $result = null;
        $error = null;
        $data = null;
        $id = is_object($call) && property_exists($call, 'id') ? $call->id : null;
        do {
            if (! is_object($call)) {
                $error = self::INVALIDREQUEST;

                break;
            }

            // hack for inputEx smd tester
            if (property_exists($call, 'version') && $call->version === 'json-rpc-2.0') {
                $call->jsonrpc = '2.0';
            }

            if (! property_exists($call, 'jsonrpc') || $call->jsonrpc !== '2.0') {
                $error = self::INVALIDREQUEST;

                break;
            }

            $fullMethod = property_exists($call, 'method') ? $call->method : '';
            $methodInfo = explode('.', (string) $fullMethod, 2);
            $namespace = array_key_exists(1, $methodInfo) ? $methodInfo[0] : '';
            $method = $namespace !== '' && $namespace !== '0' ? $methodInfo[1] : $fullMethod;
            if (
                ! $method || ! array_key_exists($namespace, $this->instances)
                || ! method_exists($this->instances[$namespace], $method)
                || in_array(strtolower((string) $method), $this->hiddenMethods)
            ) {
                $error = self::METHODNOTFOUND;

                break;
            }

            if (! array_key_exists($fullMethod, $this->reflectionMethods)) {
                $this->reflectionMethods[$fullMethod] = new \ReflectionMethod($this->instances[$namespace], $method);
            }

            /** @var array $params */
            $params = property_exists($call, 'params') ? $call->params : null;
            $paramsType = gettype($params);
            if ($params !== null && $paramsType !== 'array' && $paramsType !== 'object') {
                $error = self::INVALIDPARAMS;
                $data = 'Cast of params error';

                break;
            }

            // check parameters
            switch ($paramsType) {
                case 'array':
                    $totalRequired = 0;
                    // doesn't hold required, null, required sequence of params
                    foreach ($this->reflectionMethods[$fullMethod]->getParameters() as $param) {
                        if (! $param->isDefaultValueAvailable()) {
                            ++$totalRequired;
                        }
                    }

                    if (count($params) < $totalRequired) {
                        $error = self::INVALIDPARAMS;
                        $data = sprintf(
                            'Check numbers of required params (got %d, expected %d)',
                            count($params),
                            $totalRequired
                        );
                    }

                    break;

                case 'object':
                    foreach ($this->reflectionMethods[$fullMethod]->getParameters() as $param) {
                        if (! $param->isDefaultValueAvailable() && ! array_key_exists($param->getName(), $params)) {
                            $error = self::INVALIDPARAMS;
                            $data = $param->getName() . ' not found';

                            break 3;
                        }
                    }

                    break;

                case 'NULL':
                    if ($this->reflectionMethods[$fullMethod]->getNumberOfRequiredParameters() > 0) {
                        $error = self::INVALIDPARAMS;
                        $data = 'Empty required params';

                        break 2;
                    }

                    break;
            }
        } while (false);

        if ($error) {
            return [$error, $id, $data];
        }

        return $result;
    }

    /**
     * Process Call.
     */
    private function processCall(\stdClass $call): ?array
    {
        $id = property_exists($call, 'id') ? $call->id : null;
        $params = property_exists($call, 'params') ? $call->params : [];
        $result = null;
        $namespace = strpos('.', (string) $call->method) ? substr((string) $call->method, 0, strpos((string) $call->method, '.')) : '';

        try {
            // set named parameters
            if (is_object($params)) {
                $newParams = [];
                foreach ($this->reflectionMethods[$call->method]->getParameters() as $reflectionParameter) {
                    $paramName = $reflectionParameter->getName();
                    $defaultValue = $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null;
                    $newParams[] = property_exists($params, $paramName) ? $params->{$paramName} : $defaultValue;
                }

                $params = $newParams;
            }

            // invoke
            $result = $this->reflectionMethods[$call->method]->invokeArgs($this->instances[$namespace], $params);
        } catch (\Exception $exception) {
            return $this->getError($exception->getCode(), $id, $exception->getMessage());
        }

        if (! $id && $id !== 0) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];
    }

    /**
     * Get Doc Comment.
     *
     * @param $comment
     */
    private function getDocDescription($comment): ?string
    {
        if (preg_match('/\*\s+([^@]*)\s+/s', (string) $comment, $matches)) {
            return str_replace('*', "\n", trim(trim($matches[1], '*')));
        }

        return null;
    }

    /**
     * Get Service Map
     * Maybe not so good realization of auto-discover via doc blocks.
     */
    private function getServiceMap(): array
    {
        $result = [
            'transport' => 'POST',
            'envelope' => 'JSON-RPC-2.0',
            'SMDVersion' => '2.0',
            'contentType' => 'application/json',
            'target' => empty($_SERVER['REQUEST_URI']) ?
                '' : substr((string) $_SERVER['REQUEST_URI'], 0, strpos((string) $_SERVER['REQUEST_URI'], '?')),
            'services' => [],
            'description' => '',
        ];

        foreach ($this->instances as $namespace => $instance) {
            $rc = new \ReflectionClass($instance);

            // Get Class Description
            if ($rcDocComment = $this->getDocDescription($rc->getDocComment())) {
                $result['description'] .= $rcDocComment . \PHP_EOL;
            }

            foreach ($rc->getMethods() as $method) {
                /** @var \ReflectionMethod $method */
                if (! $method->isPublic()) {
                    continue;
                }

                if (in_array(strtolower($method->getName()), $this->hiddenMethods)) {
                    continue;
                }

                $methodName = ($namespace ? $namespace . '.' : '') . $method->getName();
                $docComment = $method->getDocComment();

                $result['services'][$methodName] = ['parameters' => []];

                // set description
                if ($rmDocComment = $this->getDocDescription($docComment)) {
                    $result['services'][$methodName]['description'] = $rmDocComment;
                }

                // @param\s+([^\s]*)\s+([^\s]*)\s*([^\s\*]*)
                $parsedParams = [];
                if (preg_match_all('/@param\s+([^\s]*)\s+([^\s]*)\s*([^\n\*]*)/', $docComment, $matches)) {
                    foreach ($matches[2] as $number => $name) {
                        $type = $matches[1][$number];
                        $desc = $matches[3][$number];
                        $name = trim($name, '$');

                        $param = ['type' => $type, 'description' => $desc];
                        $parsedParams[$name] = array_filter($param);
                    }
                }

                // process params
                foreach ($method->getParameters() as $parameter) {
                    $name = $parameter->getName();
                    $param = ['name' => $name, 'optional' => $parameter->isDefaultValueAvailable()];
                    if (array_key_exists($name, $parsedParams)) {
                        $param += $parsedParams[$name];
                    }

                    if ($param['optional']) {
                        $param['default'] = $parameter->getDefaultValue();
                    }

                    $result['services'][$methodName]['parameters'][] = $param;
                }

                // set return type
                if (preg_match('/@return\s+([^\s]+)\s*([^\n\*]+)/', $docComment, $matches)) {
                    $returns = ['type' => $matches[1], 'description' => trim($matches[2])];
                    $result['services'][$methodName]['returns'] = array_filter($returns);
                }
            }
        }

        return $result;
    }

    /**
     * Reset Local Class Vars after Execute.
     */
    private function resetVars(): void
    {
        $this->response = [];
        $this->calls = [];
        $this->hasCalls = false;
        $this->isBatchCall = false;
    }
}
