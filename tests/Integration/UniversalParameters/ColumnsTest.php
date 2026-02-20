<?php

namespace MarketDataApp\Tests\Integration\UniversalParameters;

use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Endpoints\Responses\Stocks\Quote;
use MarketDataApp\Enums\Format;

/**
 * Integration tests for the Columns universal parameter.
 *
 * Tests that the columns parameter works correctly with the actual API
 * in CSV/HTML format to filter and order columns.
 */
class ColumnsTest extends UniversalParametersTestCase
{
    public function testColumns_singleColumn_returnsCsvWithOnlyRequestedColumn(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        $this->assertEquals(['symbol'], $headerRow);

        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(1, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    public function testColumns_multipleColumns_returnsCsvWithRequestedColumns(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['symbol', 'ask', 'bid', 'last']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        $this->assertEquals(['symbol', 'ask', 'bid', 'last'], $headerRow);

        if (count($lines) > 1) {
            $dataRow = str_getcsv($lines[1], ',', '"', '\\');
            $this->assertCount(4, $dataRow);
            $this->assertEquals('AAPL', $dataRow[0]);
        }
    }

    public function testColumns_customOrder_returnsCsvWithColumnsInRequestedOrder(): void
    {
        $response = $this->client->stocks->quote(
            symbol: 'AAPL',
            parameters: new Parameters(
                format: Format::CSV,
                columns: ['bid', 'ask', 'symbol']
            )
        );

        $this->assertInstanceOf(Quote::class, $response);
        $this->assertTrue($response->isCsv());

        $csv = $response->getCsv();
        $lines = explode("\n", trim($csv));
        $headerRow = str_getcsv($lines[0], ',', '"', '\\');

        $this->assertEquals(['bid', 'ask', 'symbol'], $headerRow);
    }
}
