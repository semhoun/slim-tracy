<?php

declare(strict_types=1);

/**
 * Copyright 2017 1f7.wizard@gmail.com.
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

namespace SlimTracy\Collectors;

use Exception;
use Psr\Container\ContainerInterface as Container;

/**
 * Class DoctrineCollector.
 */
class DoctrineCollector
{
    /*
     * DoctrineCollector constructor

     * @throws Exception
     */
    public function __construct(?Container $container = null, string $containerName = '')
    {
        if (!$container instanceof \Psr\Container\ContainerInterface || ! $container->has($containerName)) {
            return;
        }

        $conf = $container->get($containerName);
        if (! ($conf instanceof \Doctrine\DBAL\Configuration)) {
            throw new Exception('Neither Doctrine DBAL neither ORM Configuration not found');
        }

        $queries = new DoctrineLogger\Queries();

        $middlewares = $conf->getMiddlewares();
        $middlewares[] = new DoctrineLogger\Middleware($queries);
        $conf->setMiddlewares($middlewares);

        if (method_exists($container, 'set')) {
            $container->set('tracy.doctrineQueries', $queries);
        } else {
            $container['tracy.doctrineQueries'] = $queries;
        }
    }
}
