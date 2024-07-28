<?php

namespace MarketDataApp\Traits;

use MarketDataApp\Endpoints\Requests\Parameters;

trait UniversalParameters
{

    protected function execute(string $method, $arguments, ?Parameters $parameters = null): object
    {
        if(is_null($parameters)) $parameters = new Parameters();

        return $this->client->execute(self::BASE_URL . $method,
            array_merge($arguments, [
                'format' => $parameters->format->value
            ])
        );
    }

    /**
     * @throws \Throwable
     */
    protected function execute_in_parallel(array $calls, ?Parameters $parameters = null): array
    {
        if(is_null($parameters)) $parameters = new Parameters();

        for($i = 0; $i < count($calls); $i++) {
            $calls[$i][0] = self::BASE_URL . $calls[$i][0];
            $calls[$i][1]['format'] = $parameters->format->value;
        }

        return $this->client->execute_in_parallel($calls);
    }
}
