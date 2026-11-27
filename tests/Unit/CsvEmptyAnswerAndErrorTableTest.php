<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use MarketDataApp\Client;
use MarketDataApp\Endpoints\Requests\Parameters;
use MarketDataApp\Enums\Format;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Tests\Traits\MockResponses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A CSV answer that carries no data, or the API's CSV error table, on a single request.
 *
 * The outcome of a request must not depend on the format: the CSV answer gives the
 * empty result where the JSON answer gives "no_data", and raises where the JSON answer
 * raises, with the same exception and message.
 *
 * Regression tests for issue #101.
 */
class CsvEmptyAnswerAndErrorTableTest extends TestCase
{
    use MockResponses;

    private const BOM = "\xEF\xBB\xBF";

    private const CSV_HEADERS = ['Content-Type' => 'text/csv; charset=utf-8'];

    private const JSON_HEADERS = ['Content-Type' => 'application/json'];

    private Client $client;

    protected function setUp(): void
    {
        $this->saveMarketDataTokenState();
        $this->clearMarketDataToken();
        $this->client = new Client("");
    }

    protected function tearDown(): void
    {
        $this->restoreMarketDataTokenState();
        parent::tearDown();
    }

    /**
     * The empty answer in both of its shapes, on the status the API sends it with.
     *
     * Mock response: FROM real API output (captured on 2026-11-27)
     * stocks/candles/D/AAPL/?from=2024-01-06&to=2024-01-07&format=csv (with and without headers=false)
     */
    public static function csvEmptyAnswerProvider(): array
    {
        return [
            'placeholder with headers' => [null, "0\r\n\"\"\r\n"],
            'placeholder without headers' => [false, "\"\"\r\n"],
        ];
    }

    #[DataProvider('csvEmptyAnswerProvider')]
    public function testCandles_csvEmptyAnswer_isTheEmptyResult(?bool $addHeaders, string $body): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $this->setMockResponses([
            new Response(200, self::CSV_HEADERS, $body),
            new Response(404, self::JSON_HEADERS, '{"s":"no_data","prevTime":null,"nextTime":null}'),
        ]);

        $csv = $this->client->stocks->candles(
            'AAPL',
            '2024-01-06',
            '2024-01-07',
            parameters: new Parameters(format: Format::CSV, add_headers: $addHeaders)
        );
        $json = $this->client->stocks->candles('AAPL', '2024-01-06', '2024-01-07');

        $this->assertTrue($csv->isCsv());
        $this->assertSame('', $csv->getCsv());
        $this->assertSame('no_data', $json->status);
    }

    public function testOptionQuote_csvEmptyAnswer_isTheEmptyResult(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        // options/quotes/AAPL250117C00200000/?date=2024-01-06&format=csv
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, "0\r\n\"\"\r\n")]);

        $quotes = $this->client->options->quotes(
            'AAPL250117C00200000',
            date: '2024-01-06',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertSame('', $quotes->getCsv());
    }

    public function testCandles_csvEmptyAnswer_writesAnEmptyFile(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, "0\r\n\"\"\r\n")]);
        $filename = sys_get_temp_dir() . '/md-sdk-test-' . uniqid() . '.csv';

        try {
            $candles = $this->client->stocks->candles(
                'AAPL',
                '2024-01-06',
                '2024-01-07',
                parameters: new Parameters(format: Format::CSV, filename: $filename)
            );

            $this->assertSame($filename, $candles->_saved_filename);
            $this->assertFileExists($filename);
            $this->assertSame('', file_get_contents($filename));
        } finally {
            @unlink($filename);
        }
    }

    /**
     * A 404 whose table says "no_data" is the empty result, as its JSON form is.
     *
     * Mock response: FROM real API output (captured on 2026-11-27)
     * stocks/quotes/ZZZZZZ/?format=csv (with and without headers=false), and ?format=json
     */
    public static function csvNoDataTableProvider(): array
    {
        return [
            'table with headers' => [null, "s,errmsg\r\nno_data,Symbol not found.\r\n"],
            'table without headers' => [false, "no_data,Symbol not found.\r\n"],
        ];
    }

    #[DataProvider('csvNoDataTableProvider')]
    public function testQuote_csvNoDataTableOn404_isTheEmptyResultLikeJson(?bool $addHeaders, string $body): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $this->setMockResponses([
            new Response(404, self::CSV_HEADERS, $body),
            new Response(404, self::JSON_HEADERS, '{"s":"no_data","errmsg":"Symbol not found."}'),
        ]);

        $csv = $this->client->stocks->quote(
            'ZZZZZZ',
            parameters: new Parameters(format: Format::CSV, add_headers: $addHeaders)
        );
        $json = $this->client->stocks->quote('ZZZZZZ');

        $this->assertSame('', $csv->getCsv());
        $this->assertSame('no_data', $json->status);
    }

    /**
     * A 404 whose table says "error" raises, as its JSON form does.
     *
     * Mock response: FROM real API output (captured on 2026-11-27)
     * options/quotes/AAPL250117C09990000/?date=2024-01-05&format=csv (with and without headers=false)
     */
    public static function csvErrorTableProvider(): array
    {
        $message = 'No option found. No option was found for this strike and expiration.';

        return [
            'table with headers' => [null, "s,errmsg\r\nerror,{$message}\r\n"],
            'table without headers' => [false, "error,{$message}\r\n"],
        ];
    }

    #[DataProvider('csvErrorTableProvider')]
    public function testOptionQuote_csvErrorTableOn404_throwsApiExceptionLikeJson(?bool $addHeaders, string $body): void
    {
        $message = 'No option found. No option was found for this strike and expiration.';
        // Mock response: FROM real API output (captured on 2026-11-27)
        $this->setMockResponses([
            new Response(404, self::JSON_HEADERS, '{"s":"error","errmsg":"' . $message . '"}'),
            new Response(404, self::CSV_HEADERS, $body),
        ]);

        $jsonException = $this->captureException(
            fn() => $this->client->options->quotes('AAPL250117C09990000', date: '2024-01-05')
        );
        $csvException = $this->captureException(
            fn() => $this->client->options->quotes(
                'AAPL250117C09990000',
                date: '2024-01-05',
                parameters: new Parameters(format: Format::CSV, add_headers: $addHeaders)
            )
        );

        $this->assertInstanceOf(ApiException::class, $jsonException);
        $this->assertSame(ApiException::class, get_class($csvException));
        $this->assertSame($jsonException->getMessage(), $csvException->getMessage());
        $this->assertSame($message, $csvException->getMessage());
        $this->assertSame(404, $csvException->getResponse()->getStatusCode());
        $this->assertStringContainsString('options/quotes/AAPL250117C09990000/', $csvException->getRequestUrl());
    }

    public function testOptionQuote_csvErrorTableOn404_writesNoFile(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $body = "s,errmsg\r\nerror,No option found. No option was found for this strike and expiration.\r\n";
        $this->setMockResponses([new Response(404, self::CSV_HEADERS, $body)]);
        $filename = sys_get_temp_dir() . '/md-sdk-test-' . uniqid() . '.csv';

        try {
            $exception = $this->captureException(
                fn() => $this->client->options->quotes(
                    'AAPL250117C09990000',
                    date: '2024-01-05',
                    parameters: new Parameters(format: Format::CSV, filename: $filename)
                )
            );

            $this->assertInstanceOf(ApiException::class, $exception);
            $this->assertFileDoesNotExist($filename);
        } finally {
            @unlink($filename);
        }
    }

    public function testOptionQuote_csvErrorTableOn400_hasTheJsonMessage(): void
    {
        $message = 'Invalid option symbol. Use valid OCC symbology for the option symbol.';
        // Mock response: FROM real API output (captured on 2026-11-27)
        // options/quotes/NOTASYMBOL/?format=json and ?format=csv
        $this->setMockResponses([
            new Response(400, self::JSON_HEADERS, '{"s":"error","errmsg":"' . $message . '"}'),
            new Response(400, self::CSV_HEADERS, "s,errmsg\r\nerror,{$message}\r\n"),
        ]);

        $jsonException = $this->captureException(fn() => $this->client->options->quotes('NOTASYMBOL'));
        $csvException = $this->captureException(
            fn() => $this->client->options->quotes('NOTASYMBOL', parameters: new Parameters(format: Format::CSV))
        );

        $this->assertSame(BadStatusCodeError::class, get_class($jsonException));
        $this->assertSame(BadStatusCodeError::class, get_class($csvException));
        $this->assertSame($message, $jsonException->getMessage());
        $this->assertSame($message, $csvException->getMessage());
    }

    public function testErrorMessage_csvErrorTableWithoutHeaders_isTheErrmsg(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponses([new Response(400, self::CSV_HEADERS, "error,\"Bad parameter, symbol\"\r\n")]);

        $exception = $this->captureException(
            fn() => $this->client->options->quotes(
                'NOTASYMBOL',
                parameters: new Parameters(format: Format::CSV, add_headers: false)
            )
        );

        $this->assertSame(BadStatusCodeError::class, get_class($exception));
        $this->assertSame('Bad parameter, symbol', $exception->getMessage());
    }

    public function testErrorMessage_csvBodyThatIsNoErrorTable_isTheBody(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $body = "s,errmsg\r\nok,fine\r\n";
        $this->setMockResponses([new Response(400, self::CSV_HEADERS, $body)]);

        $exception = $this->captureException(
            fn() => $this->client->options->quotes('NOTASYMBOL', parameters: new Parameters(format: Format::CSV))
        );

        $this->assertSame(BadStatusCodeError::class, get_class($exception));
        $this->assertSame($body, $exception->getMessage());
    }

    public function testQuote_csvErrorTableOn200_throwsApiExceptionLikeJson(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponses([
            new Response(200, self::JSON_HEADERS, '{"s":"error","errmsg":"Something failed."}'),
            new Response(200, self::CSV_HEADERS, "s,errmsg\r\nerror,Something failed.\r\n"),
        ]);

        $jsonException = $this->captureException(fn() => $this->client->stocks->quote('AAPL'));
        $csvException = $this->captureException(
            fn() => $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::CSV))
        );

        $this->assertSame(ApiException::class, get_class($jsonException));
        $this->assertSame(ApiException::class, get_class($csvException));
        $this->assertSame('Something failed.', $csvException->getMessage());
    }

    /**
     * Bodies that look a little like the error table or the placeholder, and are data.
     *
     * Mock response: NOT from real API output (uses synthetic/test data)
     */
    public static function csvDataProvider(): array
    {
        return [
            'one headerless row starting with error, on a 200' => ["error,1.5\r\n"],
            'one headerless row starting with no_data, on a 200' => ["no_data,1.5\r\n"],
            'error table with a third column' => ["s,errmsg\r\nerror,a,b\r\n"],
            'error table with an unknown status' => ["s,errmsg\r\nok,fine\r\n"],
            'error table with two rows' => ["s,errmsg\r\nerror,a\r\nerror,b\r\n"],
            'other header over a row starting with error' => ["side,price\r\nerror,1.5\r\n"],
            'placeholder header with a value' => ["0\r\n1\r\n"],
        ];
    }

    #[DataProvider('csvDataProvider')]
    public function testQuote_csvBodyThatIsNoTable_isReturnedUnchanged(string $body): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, $body)]);

        $quote = $this->client->stocks->quote('AAPL', parameters: new Parameters(format: Format::CSV));

        $this->assertSame($body, $quote->getCsv());
    }

    public function testOptionQuote_csvData_isReturnedUnchanged(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        // options/quotes/AAPL250117C00200000/?date=2024-01-05&format=csv
        $body = "optionSymbol,underlying,expiration,side,strike,firstTraded,dte,updated,bid,bidSize,mid,ask,askSize,"
            . "last,openInterest,volume,inTheMoney,intrinsicValue,extrinsicValue,underlyingPrice,iv,delta,gamma,"
            . "theta,vega\r\n"
            . "AAPL250117C00200000,AAPL,1737147600,call,200,1662989400,378,1704488400,12.25,210,12.47,12.7,2,12.47,"
            . "22857,1279,False,0,12.47,181.18,0.2222,0.4564,0.0097,-0.0305,0.7299\r\n";
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, $body)]);

        $quotes = $this->client->options->quotes(
            'AAPL250117C00200000',
            date: '2024-01-05',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertSame($body, $quotes->getCsv());
    }

    public function testCandles_csvEmptyAnswerWithByteOrderMark_isTheEmptyResult(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        // The API sends no byte order mark; one is added here in front of the real placeholder.
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, self::BOM . "0\r\n\"\"\r\n")]);

        $candles = $this->client->stocks->candles(
            'AAPL',
            '2024-01-06',
            '2024-01-07',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertSame('', $candles->getCsv());
    }

    public function testCandles_csvDataWithByteOrderMark_losesTheMark(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        // The API sends no byte order mark; one is added here in front of a real body.
        $body = "t,o,h,l,c,v\r\n1641220200,177.83,179.31,177.71,178.965,3342579\r\n";
        $this->setMockResponses([new Response(200, self::CSV_HEADERS, self::BOM . $body)]);

        $candles = $this->client->stocks->candles(
            'AAPL',
            '2022-01-03',
            '2022-01-03',
            parameters: new Parameters(format: Format::CSV)
        );

        $this->assertSame($body, $candles->getCsv());
    }

    public function testOptionQuote_csvErrorTableWithByteOrderMark_throwsApiException(): void
    {
        // Mock response: NOT from real API output (uses synthetic/test data)
        // The API sends no byte order mark; one is added here in front of a real error table.
        $body = "s,errmsg\r\nerror,No option found. No option was found for this strike and expiration.\r\n";
        $this->setMockResponses([new Response(404, self::CSV_HEADERS, self::BOM . $body)]);

        $exception = $this->captureException(
            fn() => $this->client->options->quotes(
                'AAPL250117C09990000',
                date: '2024-01-05',
                parameters: new Parameters(format: Format::CSV)
            )
        );

        $this->assertSame(ApiException::class, get_class($exception));
        $this->assertSame('No option found. No option was found for this strike and expiration.', $exception->getMessage());
    }

    public function testExecuteInParallel_csvEmptyAnswerAndErrorTable_matchTheSyncPath(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $message = 'No option found. No option was found for this strike and expiration.';
        $this->setMockResponses([
            new Response(200, self::CSV_HEADERS, "0\r\n\"\"\r\n"),
            new Response(404, self::CSV_HEADERS, "s,errmsg\r\nerror,{$message}\r\n"),
            new Response(404, self::CSV_HEADERS, "s,errmsg\r\nno_data,Symbol not found.\r\n"),
        ]);

        $failed = [];
        $results = $this->client->execute_in_parallel([
            ['v1/options/quotes/AAPL250117C00200000/', ['date' => '2024-01-06', 'format' => 'csv']],
            ['v1/options/quotes/AAPL250117C09990000/', ['date' => '2024-01-05', 'format' => 'csv']],
            ['v1/stocks/quotes/ZZZZZZ/', ['format' => 'csv']],
        ], $failed);

        $this->assertSame([0, 2], array_keys($results));
        $this->assertSame('', $results[0]->csv);
        $this->assertSame('', $results[2]->csv);
        $this->assertSame([1], array_keys($failed));
        $this->assertSame(ApiException::class, get_class($failed[1]));
        $this->assertSame($message, $failed[1]->getMessage());
    }

    public function testExecuteInParallel_csvErrorTable_throwsWithoutFailureCollection(): void
    {
        // Mock response: FROM real API output (captured on 2026-11-27)
        $message = 'No option found. No option was found for this strike and expiration.';
        $this->setMockResponses([new Response(404, self::CSV_HEADERS, "error,{$message}\r\n")]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage($message);

        $this->client->execute_in_parallel([
            ['v1/options/quotes/AAPL250117C09990000/', ['date' => '2024-01-05', 'format' => 'csv', 'headers' => 'false']],
        ]);
    }

    private function captureException(callable $call): \Throwable
    {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }
        $this->fail('Expected an exception, none was thrown');
    }
}
