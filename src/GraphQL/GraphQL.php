<?php

namespace HulkApps\AppManager\GraphQL;

class GraphQL
{

    public static function __callStatic($method, $args) {

        return GraphQLRequest::new()->{$method}(...$args);
    }
}