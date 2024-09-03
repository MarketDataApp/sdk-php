<?php

namespace MarketDataApp\Traits;

use MarketDataApp\Endpoints\Requests\Parameters;

/**
 * Trait UniversalParameters
 *
 * This trait provides methods for executing API requests with universal parameters.
 * It can be used to add common functionality across different endpoint classes.
 */
trait UniversalParameters
{

    /**
     * Execute a single API request with universal parameters.
     *
     * @param string          $method     The API method to call.
     * @param array           $arguments  The arguments for the API call.
     * @param Parameters|null $parameters Optional Parameters object for additional settings.
     *
     * @return object The API response as an object.
     */
    protected function execute(string $method, $arguments, ?Parameters $parameters): object
    {
        if (is_null($parameters)) {
            $parameters = new Parameters();
        }

        return $this->client->execute(self::BASE_URL . $method,
            array_merge($arguments, [
                'format' => $parameters->format->value
            ])
        );
    }

    /**
     * Execute multiple API requests in parallel with universal parameters.
     *
     * @param array           $calls      An array of method calls, each containing the method name and arguments.
     * @param Parameters|null $parameters Optional Parameters object for additional settings.
     *
     * @return array An array of API responses.
     * @throws \Throwable
     */
    protected function execute_in_parallel(array $calls, ?Parameters $parameters = null): array
    {
        if (is_null($parameters)) {
            $parameters = new Parameters();
        }

        for ($i = 0; $i < count($calls); $i++) {
            $calls[$i][0] = self::BASE_URL . $calls[$i][0];
            $calls[$i][1]['format'] = $parameters->format->value;
        }

        return $this->client->execute_in_parallel($calls);
    }
}
