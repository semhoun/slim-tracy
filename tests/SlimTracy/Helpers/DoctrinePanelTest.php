<?php

namespace Tests\SlimTracy\Helpers;

use Doctrine\DBAL\ParameterType;
use SlimTracy\Helpers\DoctrinePanel;
use SlimTracy\Helpers\SqlParameters;
use Tests\BaseTestCase;

class DoctrinePanelTest extends BaseTestCase
{
    public function testDoctrinePanel(): void
    {
        $panel = new DoctrinePanel([], ['slim' => \Slim\App::VERSION]);
        $this->assertInstanceOf(\Tracy\IBarPanel::class, $panel);
        $this->assertStringContainsString('Doctrine DBAL Panel', $panel->getTab());
        $this->assertStringContainsString('Slim ' . \Slim\App::VERSION . ' / Doctrine DBAL', $panel->getPanel());
    }

    public function testDoctrinePanelParser(): void
    {
        $data = [
            ['sql' => 'SELECT id, name FROM authors WHERE id = :id',
                'params' => [':id' => 22], 'types' => [':id' => ParameterType::INTEGER],
                'executionMS' => 0.010435104370117],
            ['sql' => 'SELECT author_id, title FROM authors, books WHERE author_id = :id AND title = :title',
                'params' => [':id' => 3, ':title' => 'Dragondrums'],
                'types' => [':id' => ParameterType::INTEGER, ':title' => ParameterType::STRING],
                'executionMS' => 0.0032620429992676],
        ];
        $panel = new DoctrinePanel($data, ['slim' => \Slim\App::VERSION]);
        $html = $panel->getPanel();
        foreach (['FROM authors WHERE', '0.01043510', 'FROM authors, books WHERE', 'Dragondrums',
            '0.00326204', '22', 'INTEGER', 'STRING'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $params = SqlParameters::values($data[1]['params']);
        $this->assertStringContainsString('3', $params);
        $this->assertStringContainsString('Dragondrums', $params);
        $types = SqlParameters::types($data[1]['types']);
        $this->assertStringContainsString('INTEGER', $types);
        $this->assertStringContainsString('STRING', $types);
    }

    public function testPendingQueriesAreNotRendered(): void
    {
        $panel = new DoctrinePanel([
            ['sql' => 'PENDING', 'params' => null, 'types' => null],
            ['sql' => 'COMPLETE', 'params' => null, 'types' => null, 'executionMS' => 0.001],
        ], ['slim' => \Slim\App::VERSION]);

        $this->assertStringNotContainsString('PENDING', $panel->getPanel());
        $this->assertStringContainsString('COMPLETE', $panel->getPanel());
        $this->assertStringContainsString('(1 / 1 ms)', $panel->getTab());
        $this->assertSame('', SqlParameters::values(null));
        $this->assertSame('', SqlParameters::types(null));
    }
}
