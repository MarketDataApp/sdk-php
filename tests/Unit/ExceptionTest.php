<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\BadStatusCodeError;
use MarketDataApp\Exceptions\MarketDataException;
use MarketDataApp\Exceptions\RequestError;
use MarketDataApp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Test case for exception classes.
 *
 * This class tests the exception classes including getResponse(), getRequestId(),
 * getRequestUrl(), and __toString() methods.
 */
class ExceptionTest extends TestCase
{
    /**
     * Test MarketDataException::getResponse() method.
     *
     * @return void
     */
    public function testMarketDataException_getResponse_returnsResponse(): void
    {
        $response = new Response(500, [], json_encode(['errmsg' => 'Server Error']));
        $exception = new MarketDataException('Test error', 500, null, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * Test MarketDataException::getResponse() with null response.
     *
     * @return void
     */
    public function testMarketDataException_getResponse_withNullResponse_returnsNull(): void
    {
        $exception = new MarketDataException('Test error', 500, null, null);

        $this->assertNull($exception->getResponse());
    }

    /**
     * Test MarketDataException::getRequestId() extracts cf-ray header from response.
     *
     * @return void
     */
    public function testMarketDataException_getRequestId_extractsFromCfRayHeader(): void
    {
        $response = new Response(500, ['cf-ray' => 'abc123-LAX'], json_encode(['errmsg' => 'Server Error']));
        $exception = new MarketDataException('Test error', 500, null, $response);

        $this->assertEquals('abc123-LAX', $exception->getRequestId());
    }

    /**
     * Test MarketDataException::getRequestId() returns null when no cf-ray header.
     *
     * @return void
     */
    public function testMarketDataException_getRequestId_withNoCfRayHeader_returnsNull(): void
    {
        $response = new Response(500, [], json_encode(['errmsg' => 'Server Error']));
        $exception = new MarketDataException('Test error', 500, null, $response);

        $this->assertNull($exception->getRequestId());
    }

    /**
     * Test MarketDataException::getRequestId() returns null when response is null.
     *
     * @return void
     */
    public function testMarketDataException_getRequestId_withNullResponse_returnsNull(): void
    {
        $exception = new MarketDataException('Test error', 500, null, null);

        $this->assertNull($exception->getRequestId());
    }

    /**
     * Test MarketDataException::getRequestId() returns null when cf-ray header is empty.
     *
     * @return void
     */
    public function testMarketDataException_getRequestId_withEmptyCfRayHeader_returnsNull(): void
    {
        $response = new Response(500, ['cf-ray' => ''], json_encode(['errmsg' => 'Server Error']));
        $exception = new MarketDataException('Test error', 500, null, $response);

        $this->assertNull($exception->getRequestId());
    }

    /**
     * Test MarketDataException::getRequestUrl() returns the URL.
     *
     * @return void
     */
    public function testMarketDataException_getRequestUrl_returnsUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new MarketDataException('Test error', 500, null, null, $url);

        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test MarketDataException::getRequestUrl() returns null when not provided.
     *
     * @return void
     */
    public function testMarketDataException_getRequestUrl_withNoUrl_returnsNull(): void
    {
        $exception = new MarketDataException('Test error', 500);

        $this->assertNull($exception->getRequestUrl());
    }

    /**
     * Test MarketDataException::getTimestamp() returns a DateTimeImmutable.
     *
     * @return void
     */
    public function testMarketDataException_getTimestamp_returnsDateTimeImmutable(): void
    {
        $before = new \DateTimeImmutable();
        $exception = new MarketDataException('Test error', 500);
        $after = new \DateTimeImmutable();

        $timestamp = $exception->getTimestamp();

        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    /**
     * Test MarketDataException::getTimestamp() is set at construction time.
     *
     * @return void
     */
    public function testMarketDataException_getTimestamp_isSetAtConstruction(): void
    {
        $exception1 = new MarketDataException('Test error 1');
        usleep(1000); // Sleep 1ms to ensure different timestamps
        $exception2 = new MarketDataException('Test error 2');

        // Each exception should have its own timestamp
        $this->assertNotEquals(
            $exception1->getTimestamp()->format('U.u'),
            $exception2->getTimestamp()->format('U.u')
        );
    }

    /**
     * Test MarketDataException::getSupportContext() returns array with all fields.
     *
     * @return void
     */
    public function testMarketDataException_getSupportContext_returnsCompleteArray(): void
    {
        $response = new Response(500, ['cf-ray' => 'abc123-LAX'], json_encode(['errmsg' => 'Server Error']));
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new MarketDataException('Test error', 500, null, $response, $url);

        $context = $exception->getSupportContext();

        $this->assertIsArray($context);
        $this->assertArrayHasKey('timestamp', $context);
        $this->assertArrayHasKey('request_id', $context);
        $this->assertArrayHasKey('url', $context);
        $this->assertArrayHasKey('http_code', $context);
        $this->assertArrayHasKey('message', $context);
        $this->assertArrayHasKey('exception_type', $context);

        $this->assertEquals('abc123-LAX', $context['request_id']);
        $this->assertEquals($url, $context['url']);
        $this->assertEquals(500, $context['http_code']);
        $this->assertEquals('Test error', $context['message']);
        $this->assertEquals(MarketDataException::class, $context['exception_type']);
    }

    /**
     * Test MarketDataException::getSupportContext() handles null values.
     *
     * @return void
     */
    public function testMarketDataException_getSupportContext_handlesNullValues(): void
    {
        $exception = new MarketDataException('Test error', 500);

        $context = $exception->getSupportContext();

        $this->assertNull($context['request_id']);
        $this->assertNull($context['url']);
    }

    /**
     * Test MarketDataException::getSupportInfo() returns formatted string.
     *
     * @return void
     */
    public function testMarketDataException_getSupportInfo_returnsFormattedString(): void
    {
        $response = new Response(500, ['cf-ray' => 'abc123-LAX'], json_encode(['errmsg' => 'Server Error']));
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new MarketDataException('Test error', 500, null, $response, $url);

        $info = $exception->getSupportInfo();

        $this->assertStringContainsString('MARKET DATA SUPPORT INFO', $info);
        $this->assertStringContainsString('Timestamp:', $info);
        $this->assertStringContainsString('Request ID:   abc123-LAX', $info);
        $this->assertStringContainsString('URL:          https://api.marketdata.app/v1/stocks/quotes/AAPL', $info);
        $this->assertStringContainsString('HTTP Code:    500', $info);
        $this->assertStringContainsString('Error:        Test error', $info);
    }

    /**
     * Test MarketDataException::getSupportInfo() shows N/A for missing values.
     *
     * @return void
     */
    public function testMarketDataException_getSupportInfo_showsNAForMissingValues(): void
    {
        $exception = new MarketDataException('Test error', 500);

        $info = $exception->getSupportInfo();

        $this->assertStringContainsString('Request ID:   N/A', $info);
        $this->assertStringContainsString('URL:          N/A', $info);
    }

    /**
     * Test child exceptions inherit getSupportContext() and getSupportInfo().
     *
     * @return void
     */
    public function testChildExceptions_inheritSupportMethods(): void
    {
        $response = new Response(401, ['cf-ray' => 'xyz789-NYC'], json_encode(['errmsg' => 'Unauthorized']));
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new UnauthorizedException('Unauthorized', 401, null, $response, $url);

        // Test getSupportContext()
        $context = $exception->getSupportContext();
        $this->assertEquals('xyz789-NYC', $context['request_id']);
        $this->assertEquals(UnauthorizedException::class, $context['exception_type']);

        // Test getSupportInfo()
        $info = $exception->getSupportInfo();
        $this->assertStringContainsString('Request ID:   xyz789-NYC', $info);
        $this->assertStringContainsString('HTTP Code:    401', $info);
    }

    /**
     * Test MarketDataException::__toString() includes timestamp, request ID and URL.
     *
     * @return void
     */
    public function testMarketDataException_toString_includesRequestContext(): void
    {
        $response = new Response(500, ['cf-ray' => 'abc123-LAX'], json_encode(['errmsg' => 'Server Error']));
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new MarketDataException('Test error', 500, null, $response, $url);

        $string = (string) $exception;

        $this->assertStringContainsString('Test error', $string);
        $this->assertStringContainsString('Timestamp:', $string);
        $this->assertStringContainsString('Request ID: abc123-LAX', $string);
        $this->assertStringContainsString('URL: https://api.marketdata.app/v1/stocks/quotes/AAPL', $string);
    }

    /**
     * Test MarketDataException::__toString() includes timestamp even without request context.
     *
     * @return void
     */
    public function testMarketDataException_toString_withoutRequestContext(): void
    {
        $exception = new MarketDataException('Test error', 500);

        $string = (string) $exception;

        $this->assertStringContainsString('Test error', $string);
        $this->assertStringContainsString('Timestamp:', $string);
        $this->assertStringNotContainsString('Request ID:', $string);
        $this->assertStringNotContainsString('URL:', $string);
    }

    /**
     * Test MarketDataException::__toString() with only request ID.
     *
     * @return void
     */
    public function testMarketDataException_toString_withOnlyRequestId(): void
    {
        $response = new Response(500, ['cf-ray' => 'abc123-LAX'], json_encode(['errmsg' => 'Server Error']));
        $exception = new MarketDataException('Test error', 500, null, $response);

        $string = (string) $exception;

        $this->assertStringContainsString('Timestamp:', $string);
        $this->assertStringContainsString('Request ID: abc123-LAX', $string);
        $this->assertStringNotContainsString('URL:', $string);
    }

    /**
     * Test MarketDataException::__toString() with only URL.
     *
     * @return void
     */
    public function testMarketDataException_toString_withOnlyUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new MarketDataException('Test error', 500, null, null, $url);

        $string = (string) $exception;

        $this->assertStringContainsString('Timestamp:', $string);
        $this->assertStringNotContainsString('Request ID:', $string);
        $this->assertStringContainsString('URL: https://api.marketdata.app/v1/stocks/quotes/AAPL', $string);
    }

    /**
     * Test ApiException::getResponse() method.
     *
     * @return void
     */
    public function testApiException_getResponse_returnsResponse(): void
    {
        $response = new Response(200, [], json_encode(['s' => 'ok']));
        $exception = new ApiException('Test error', 500, null, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * Test ApiException::getResponse() with null response.
     *
     * @return void
     */
    public function testApiException_getResponse_withNullResponse_returnsNull(): void
    {
        $exception = new ApiException('Test error', 500, null, null);

        $this->assertNull($exception->getResponse());
    }

    /**
     * Test ApiException::getRequestId() extracts cf-ray header.
     *
     * @return void
     */
    public function testApiException_getRequestId_extractsFromResponse(): void
    {
        $response = new Response(200, ['cf-ray' => 'def456-SFO'], json_encode(['s' => 'ok']));
        $exception = new ApiException('Test error', 0, null, $response);

        $this->assertEquals('def456-SFO', $exception->getRequestId());
    }

    /**
     * Test ApiException::getRequestUrl() returns the URL.
     *
     * @return void
     */
    public function testApiException_getRequestUrl_returnsUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/stocks/candles/D/AAPL';
        $exception = new ApiException('No data', 0, null, null, $url);

        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test RequestError::getResponse() method.
     *
     * @return void
     */
    public function testRequestError_getResponse_returnsResponse(): void
    {
        $response = new Response(502, [], json_encode(['errmsg' => 'Server Error']));
        $exception = new RequestError('Test error', 502, null, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * Test RequestError::getResponse() with null response.
     *
     * @return void
     */
    public function testRequestError_getResponse_withNullResponse_returnsNull(): void
    {
        $exception = new RequestError('Test error', 502, null, null);

        $this->assertNull($exception->getResponse());
    }

    /**
     * Test RequestError::getRequestId() extracts cf-ray header.
     *
     * @return void
     */
    public function testRequestError_getRequestId_extractsFromResponse(): void
    {
        $response = new Response(502, ['cf-ray' => 'ghi789-ORD'], json_encode(['errmsg' => 'Server Error']));
        $exception = new RequestError('Test error', 502, null, $response);

        $this->assertEquals('ghi789-ORD', $exception->getRequestId());
    }

    /**
     * Test RequestError::getRequestUrl() returns the URL.
     *
     * @return void
     */
    public function testRequestError_getRequestUrl_returnsUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/options/chain/AAPL';
        $exception = new RequestError('Server error', 502, null, null, $url);

        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test RequestError with all parameters.
     *
     * @return void
     */
    public function testRequestError_withAllParameters_hasFullContext(): void
    {
        $response = new Response(503, ['cf-ray' => 'jkl012-DFW'], json_encode(['errmsg' => 'Service Unavailable']));
        $url = 'https://api.marketdata.app/v1/stocks/quotes/MSFT';
        $previous = new \RuntimeException('Network timeout');

        $exception = new RequestError('Service Unavailable', 503, $previous, $response, $url);

        $this->assertEquals('Service Unavailable', $exception->getMessage());
        $this->assertEquals(503, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($response, $exception->getResponse());
        $this->assertEquals('jkl012-DFW', $exception->getRequestId());
        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test BadStatusCodeError::getResponse() method.
     *
     * @return void
     */
    public function testBadStatusCodeError_getResponse_returnsResponse(): void
    {
        $response = new Response(400, [], json_encode(['errmsg' => 'Bad Request']));
        $exception = new BadStatusCodeError('Test error', 400, null, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * Test BadStatusCodeError::getRequestId() extracts cf-ray header.
     *
     * @return void
     */
    public function testBadStatusCodeError_getRequestId_extractsFromResponse(): void
    {
        $response = new Response(400, ['cf-ray' => 'mno345-SEA'], json_encode(['errmsg' => 'Bad Request']));
        $exception = new BadStatusCodeError('Bad Request', 400, null, $response);

        $this->assertEquals('mno345-SEA', $exception->getRequestId());
    }

    /**
     * Test BadStatusCodeError::getRequestUrl() returns the URL.
     *
     * @return void
     */
    public function testBadStatusCodeError_getRequestUrl_returnsUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/stocks/quotes/INVALID';
        $exception = new BadStatusCodeError('Invalid symbol', 400, null, null, $url);

        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test UnauthorizedException::getResponse() method.
     *
     * @return void
     */
    public function testUnauthorizedException_getResponse_returnsResponse(): void
    {
        $response = new Response(401, [], json_encode(['errmsg' => 'Unauthorized']));
        $exception = new UnauthorizedException('Unauthorized', 401, null, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * Test UnauthorizedException::getRequestId() extracts cf-ray header.
     *
     * @return void
     */
    public function testUnauthorizedException_getRequestId_extractsFromResponse(): void
    {
        $response = new Response(401, ['cf-ray' => 'pqr678-NYC'], json_encode(['errmsg' => 'Unauthorized']));
        $exception = new UnauthorizedException('Unauthorized', 401, null, $response);

        $this->assertEquals('pqr678-NYC', $exception->getRequestId());
    }

    /**
     * Test UnauthorizedException::getRequestUrl() returns the URL.
     *
     * @return void
     */
    public function testUnauthorizedException_getRequestUrl_returnsUrl(): void
    {
        $url = 'https://api.marketdata.app/v1/stocks/quotes/AAPL';
        $exception = new UnauthorizedException('Invalid token', 401, null, null, $url);

        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test UnauthorizedException defaults to 401 code.
     *
     * @return void
     */
    public function testUnauthorizedException_defaultsTo401Code(): void
    {
        $exception = new UnauthorizedException('Unauthorized');

        $this->assertEquals(401, $exception->getCode());
    }

    /**
     * Test UnauthorizedException with all parameters.
     *
     * @return void
     */
    public function testUnauthorizedException_withAllParameters_hasFullContext(): void
    {
        $response = new Response(401, ['cf-ray' => 'stu901-BOS'], json_encode(['errmsg' => 'Invalid token']));
        $url = 'https://api.marketdata.app/user/';
        $previous = new \RuntimeException('Auth failed');

        $exception = new UnauthorizedException('Invalid token', 401, $previous, $response, $url);

        $this->assertEquals('Invalid token', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($response, $exception->getResponse());
        $this->assertEquals('stu901-BOS', $exception->getRequestId());
        $this->assertEquals($url, $exception->getRequestUrl());
    }

    /**
     * Test exception inheritance - RequestError extends MarketDataException.
     *
     * @return void
     */
    public function testRequestError_extendsMarketDataException(): void
    {
        $exception = new RequestError('Test error');

        $this->assertInstanceOf(MarketDataException::class, $exception);
    }

    /**
     * Test exception inheritance - BadStatusCodeError extends MarketDataException.
     *
     * @return void
     */
    public function testBadStatusCodeError_extendsMarketDataException(): void
    {
        $exception = new BadStatusCodeError('Test error');

        $this->assertInstanceOf(MarketDataException::class, $exception);
    }

    /**
     * Test exception inheritance - ApiException extends MarketDataException.
     *
     * @return void
     */
    public function testApiException_extendsMarketDataException(): void
    {
        $exception = new ApiException('Test error');

        $this->assertInstanceOf(MarketDataException::class, $exception);
    }

    /**
     * Test exception inheritance - UnauthorizedException extends BadStatusCodeError.
     *
     * @return void
     */
    public function testUnauthorizedException_extendsBadStatusCodeError(): void
    {
        $exception = new UnauthorizedException('Test error');

        $this->assertInstanceOf(BadStatusCodeError::class, $exception);
        $this->assertInstanceOf(MarketDataException::class, $exception);
    }

    /**
     * Test that all exceptions can be caught as MarketDataException.
     *
     * @return void
     */
    public function testAllExceptions_canBeCaughtAsMarketDataException(): void
    {
        $exceptions = [
            new ApiException('Test'),
            new BadStatusCodeError('Test'),
            new RequestError('Test'),
            new UnauthorizedException('Test'),
        ];

        foreach ($exceptions as $exception) {
            try {
                throw $exception;
            } catch (MarketDataException $e) {
                $this->assertInstanceOf(MarketDataException::class, $e);
            }
        }
    }
}
