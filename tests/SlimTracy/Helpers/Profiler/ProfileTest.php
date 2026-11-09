<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com
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

namespace Tests\SlimTracy\Helpers\Profiler;

use SlimTracy\Helpers\Profiler\Profile;

/**
 * Class ProfileTest
 * @package Tests\SlimTracy\Helpers\Profiler
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Profile::class)]
class ProfileTest extends \PHPUnit\Framework\TestCase
{
    public function testProfile()
    {
        $p = new Profile(['label' => 'request', 'duration' => 999], 0.05, 0.03, 640, 1024);
        $this->assertSame(0.05, $p->absoluteDuration());
        $this->assertSame(0.03, $p->duration());
        $this->assertSame(640, $p->absoluteMemoryUsageChange());
        $this->assertSame(1024, $p->memoryUsageChange());
        $this->assertSame(['label' => 'request', 'duration' => 999], $p->metadata());
        $this->assertSame([
            'label' => 'request',
            'duration' => 0.03,
            'absolute_duration' => 0.05,
            'absolute_memory_usage_change' => 640,
            'memory_usage_change' => 1024,
        ], $p->jsonSerialize());
        $this->assertSame($p->jsonSerialize(), json_decode(json_encode($p, JSON_THROW_ON_ERROR), true));
    }

    public function testMetadataReadDoesNotExposeMutableState(): void
    {
        $profile = new Profile(['label' => 'original']);
        $metadata = $profile->metadata();
        $metadata['label'] = 'changed';
        $this->assertSame(['label' => 'original'], $profile->metadata());
    }
}
