<?php

namespace HulkApps\AppManager\Client;

class Client
{
    public static function __callStatic($method, $args) {

        return PendingClientRequest::new()->{$method}(...$args);
    }
}