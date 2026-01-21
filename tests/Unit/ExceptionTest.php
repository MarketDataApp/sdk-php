<?php

namespace MarketDataApp\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use MarketDataApp\Exceptions\ApiException;
use MarketDataApp\Exceptions\RequestError;
use PHPUnit\Framework\TestCase;

/**
 * Test case for exception classes.
 *
 * This class tests the getResponse() methods of exception classes.
 */
class ExceptionTest extends TestCase
{
    /**
     * Test ApiException::getResponse() method.
     *
     * @return void
     */
    public function testApiException_getResponse_returnsResponse()
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
    public function testApiException_getResponse_withNullResponse_returnsNull()
    {
        $exception = new ApiException('Test error', 500, null, null);

        $this->assertNull($exception->getResponse());
    }

    /**
     * Test RequestError::getResponse() method.
     *
     * @return void
     */
    public function testRequestError_getResponse_returnsResponse()
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
    public function testRequestError_getResponse_withNullResponse_returnsNull()
    {
        $exception = new RequestError('Test error', 502, null, null);

        $this->assertNull($exception->getResponse());
    }
}
