<?php

namespace MarketDataApp\Traits;

use MarketDataApp\Enums\Format;
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
     * Merge method-level parameters with client default parameters.
     *
     * Priority order (highest to lowest):
     * 1. Method-level parameters (if provided)
     * 2. Client default parameters ($this->client->default_params)
     * 3. Default Parameters() values
     *
     * @param Parameters|null $methodParams Method-level parameters, or null to use only client defaults.
     *
     * @return Parameters Merged parameters instance.
     */
    protected function mergeParameters(?Parameters $methodParams): Parameters
    {
        // Start with client defaults (which already include env vars from construction)
        $merged = clone $this->client->default_params;

        // Override with method-level parameters
        if ($methodParams !== null) {
            // Format always overrides (it's required)
            $merged->format = $methodParams->format;

            // Optional parameters: override if method param is not null
            // Note: In PHP, we can't distinguish "not set" from "explicitly null" for optional parameters.
            // So we only override when the value is not null. This means:
            // - new Parameters(mode: Mode::LIVE) -> overrides client default
            // - new Parameters() -> uses client default (mode not overridden)
            // - new Parameters(mode: null) -> doesn't override (PHP limitation, can't distinguish from "not set")
            if ($methodParams->use_human_readable !== null) {
                $merged->use_human_readable = $methodParams->use_human_readable;
            }

            if ($methodParams->mode !== null) {
                $merged->mode = $methodParams->mode;
            }

            // CSV/HTML-only parameters: override if method param is not null
            if ($methodParams->date_format !== null) {
                $merged->date_format = $methodParams->date_format;
            }

            if ($methodParams->columns !== null) {
                $merged->columns = $methodParams->columns;
            }

            if ($methodParams->add_headers !== null) {
                $merged->add_headers = $methodParams->add_headers;
            }

            if ($methodParams->filename !== null) {
                $merged->filename = $methodParams->filename;
            }
        }

        // Validate merged parameters: CSV/HTML-only params cannot be used with JSON format
        // This catches cases where client defaults have CSV-only params and method params change format to JSON
        if ($merged->format !== Format::CSV && $merged->format !== Format::HTML) {
            if ($merged->date_format !== null) {
                throw new \InvalidArgumentException(
                    'date_format parameter can only be used with CSV or HTML format. ' .
                    'Current format: ' . $merged->format->value
                );
            }

            if ($merged->columns !== null) {
                throw new \InvalidArgumentException(
                    'columns parameter can only be used with CSV or HTML format. ' .
                    'Current format: ' . $merged->format->value
                );
            }

            if ($merged->add_headers !== null) {
                throw new \InvalidArgumentException(
                    'add_headers parameter can only be used with CSV or HTML format. ' .
                    'Current format: ' . $merged->format->value
                );
            }

            if ($merged->filename !== null) {
                throw new \InvalidArgumentException(
                    'filename parameter can only be used with CSV or HTML format. ' .
                    'Current format: ' . $merged->format->value
                );
            }
        }

        return $merged;
    }

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
        // Merge method parameters with client defaults
        $parameters = $this->mergeParameters($parameters);

        $universalParams = [
            'format' => $parameters->format->value
        ];

        if ($parameters->use_human_readable !== null) {
            $universalParams['human'] = $parameters->use_human_readable ? 'true' : 'false';
        }

        if ($parameters->mode !== null) {
            $universalParams['mode'] = $parameters->mode->value;
        }

        // dateformat can only be used with CSV or HTML format
        if ($parameters->date_format !== null && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
            $universalParams['dateformat'] = $parameters->date_format->value;
        }

        // columns can only be used with CSV or HTML format
        if ($parameters->columns !== null && !empty($parameters->columns) && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
            $universalParams['columns'] = implode(',', $parameters->columns);
        }

        // headers can only be used with CSV or HTML format
        if ($parameters->add_headers !== null && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
            $universalParams['headers'] = $parameters->add_headers ? 'true' : 'false';
        }

        // Pass filename through via _filename key (won't be sent to API)
        if ($parameters->filename !== null) {
            $arguments['_filename'] = $parameters->filename;
        }

        return $this->client->execute(self::BASE_URL . $method,
            array_merge($arguments, $universalParams)
        );
    }

    /**
     * Execute multiple API requests in parallel with universal parameters.
     *
     * @param array           $calls           An array of method calls, each containing the method name and arguments.
     * @param Parameters|null $parameters      Optional Parameters object for additional settings.
     * @param array|null      &$failedRequests Optional by-reference array to collect failed requests instead of throwing.
     *                                         When provided, exceptions are stored here keyed by their call index.
     *
     * @return array An array of API responses. When $failedRequests is provided, results are keyed by original call index.
     * @throws \Throwable When $failedRequests is not provided and any request fails.
     */
    protected function execute_in_parallel(array $calls, ?Parameters $parameters = null, ?array &$failedRequests = null): array
    {
        $tolerateFailed = func_num_args() >= 3;
        // Merge method parameters with client defaults
        $parameters = $this->mergeParameters($parameters);

        // Validate that filename is not provided with parallel requests
        // Defensive code: callers validate filename before calling this method
        // @codeCoverageIgnoreStart
        if ($parameters->filename !== null) {
            throw new \InvalidArgumentException(
                'filename parameter cannot be used with parallel requests. ' .
                'Each parallel response would conflict writing to the same file. ' .
                'Use filename only with single requests, or use saveToFile() method on individual response objects.'
            );
        }
        // @codeCoverageIgnoreEnd

        for ($i = 0; $i < count($calls); $i++) {
            $calls[$i][0] = self::BASE_URL . $calls[$i][0];
            $calls[$i][1]['format'] = $parameters->format->value;
            
            if ($parameters->use_human_readable !== null) {
                $calls[$i][1]['human'] = $parameters->use_human_readable ? 'true' : 'false';
            }

            if ($parameters->mode !== null) {
                $calls[$i][1]['mode'] = $parameters->mode->value;
            }

            // dateformat can only be used with CSV or HTML format
            if ($parameters->date_format !== null && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
                $calls[$i][1]['dateformat'] = $parameters->date_format->value;
            }

            // columns can only be used with CSV or HTML format
            if ($parameters->columns !== null && !empty($parameters->columns) && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
                $calls[$i][1]['columns'] = implode(',', $parameters->columns);
            }

            // headers can only be used with CSV or HTML format
            if ($parameters->add_headers !== null && ($parameters->format === Format::CSV || $parameters->format === Format::HTML)) {
                $calls[$i][1]['headers'] = $parameters->add_headers ? 'true' : 'false';
            }
        }

        if ($tolerateFailed) {
            return $this->client->execute_in_parallel($calls, $failedRequests);
        }
        return $this->client->execute_in_parallel($calls);
    }
}
