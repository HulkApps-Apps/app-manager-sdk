<?php

namespace HulkApps\AppManager\Client;

use HulkApps\AppManager\Exception\ConnectionException;

class PendingClientRequest
{
    public function __construct() {

        $this->beforeSendingCallbacks = collect(function ($request, $options) {
            $this->cookies = $options['cookies'];
        });
        $this->bodyFormat = 'json';

        $this->baseUri = null;

        $this->options = [
            'http_errors' => false,
        ];
    }

    static function new(...$args)
    {
        return new self(...$args);
    }

    public function withOptions($options) {

        return tap($this, function ($request) use ($options) {
            return $this->options = array_merge_recursive($this->options, $options);
        });
    }

    public function withoutRedirecting() {

        return tap($this, function ($request) {
            return $this->options = array_merge_recursive($this->options, [
                'allow_redirects' => false,
            ]);
        });
    }

    public function withoutVerifying() {

        return tap($this, function ($request) {
            return $this->options = array_merge_recursive($this->options, [
                'verify' => false,
            ]);
        });
    }

    public function asJson() {

        return $this->bodyFormat('json')->contentType('application/json');
    }

    public function asFormParams() {

        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    public function asMultipart() {

        return $this->bodyFormat('multipart');
    }

    public function bodyFormat($format) {

        return tap($this, function ($request) use ($format) {
            $this->bodyFormat = $format;
        });
    }

    public function baseUri($baseUri) {

        return tap($this, function ($request) use ($baseUri) {
            $this->baseUri = $baseUri;
        });
    }

    public function contentType($contentType) {

        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    public function accept($header) {

        return $this->withHeaders(['Accept' => $header]);
    }

    public function withHeaders($headers) {

        return tap($this, function ($request) use ($headers) {
            return $this->options = array_merge_recursive($this->options, [
                'headers' => $headers,
            ]);
        });
    }

    public function withBasicAuth($username, $password) {

        return tap($this, function ($request) use ($username, $password) {
            return $this->options = array_merge_recursive($this->options, [
                'auth' => [$username, $password],
            ]);
        });
    }

    public function withDigestAuth($username, $password) {

        return tap($this, function ($request) use ($username, $password) {
            return $this->options = array_merge_recursive($this->options, [
                'auth' => [$username, $password, 'digest'],
            ]);
        });
    }

    public function withCookies($cookies) {

        return tap($this, function($request) use ($cookies) {
            return $this->options = array_merge_recursive($this->options, [
                'cookies' => $cookies,
            ]);
        });
    }

    public function timeout($seconds) {

        return tap($this, function () use ($seconds) {
            $this->options['timeout'] = $seconds;
        });
    }

    public function beforeSending($callback) {

        return tap($this, function () use ($callback) {
            $this->beforeSendingCallbacks[] = $callback;
        });
    }

    public function get($url, $queryParams = []) {

        return $this->send('GET', $url, [
            'query' => $queryParams,
        ]);
    }

    public function post($url, $params = []) {

        return $this->send('POST', $url, [
            $this->bodyFormat => $params,
        ]);
    }

    public function patch($url, $params = []) {

        return $this->send('PATCH', $url, [
            $this->bodyFormat => $params,
        ]);
    }

    public function put($url, $params = []) {

        return $this->send('PUT', $url, [
            $this->bodyFormat => $params,
        ]);
    }

    public function delete($url, $params = []) {

        return $this->send('DELETE', $url, [
            $this->bodyFormat => $params,
        ]);
    }

    public function send($method, $url, $options) {

        try {
            return tap(new ClientResponse($this->buildClient()->request($method, $url, $this->mergeOptions([
                'query' => $this->parseQueryParams($url),
                'on_stats' => function ($transferStats) {
                    $this->transferStats = $transferStats;
                }
            ], $options))), function($response) {
                $response->cookies = $this->cookies;
                $response->transferStats = $this->transferStats;
            });
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    public function buildClient() {

        return new \GuzzleHttp\Client([
            'base_uri' => $this->baseUri,
            'handler'  => $this->buildHandlerStack(),
            'cookies'  => false,
        ]);
    }

    public function buildHandlerStack() {

        return tap(\GuzzleHttp\HandlerStack::create(), function ($stack) {
            $stack->push($this->buildBeforeSendingHandler());
        });
    }

    public function buildBeforeSendingHandler() {

        return function ($handler) {
            return function ($request, $options) use ($handler) {
                return $handler($this->runBeforeSendingCallbacks($request, $options), $options);
            };
        };
    }

    public function runBeforeSendingCallbacks($request, $options) {

        return tap($request, function ($request) use ($options) {
            $this->beforeSendingCallbacks->each->__invoke(new ClientRequest($request), $options);
        });
    }

    public function mergeOptions(...$options) {

        return array_merge_recursive($this->options, ...$options);
    }

    public function parseQueryParams($url) {

        return tap([], function (&$query) use ($url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
        });
    }
}