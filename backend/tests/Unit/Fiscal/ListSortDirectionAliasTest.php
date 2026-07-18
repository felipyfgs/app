<?php

namespace Tests\Unit\Fiscal;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LFU-07 — `sort_direction` e `direction` são aliases equivalentes.
 * Espelha a resolução usada em TaxGuideController / ClientController.
 */
class ListSortDirectionAliasTest extends TestCase
{
    #[DataProvider('directionProvider')]
    public function test_resolve_sort_direction_alias(array $query, string $expected): void
    {
        $request = Request::create('/api/v1/fiscal/guides', 'GET', $query);
        $raw = $request->query('sort_direction', $request->query('direction', ''));
        $direction = is_string($raw) ? strtolower($raw) : '';

        $this->assertSame($expected, $direction);
    }

    public static function directionProvider(): array
    {
        return [
            'sort_direction asc' => [['sort_direction' => 'asc'], 'asc'],
            'direction desc' => [['direction' => 'desc'], 'desc'],
            'sort_direction vence direction' => [
                ['sort_direction' => 'asc', 'direction' => 'desc'],
                'asc',
            ],
            'ausente' => [[], ''],
        ];
    }
}
