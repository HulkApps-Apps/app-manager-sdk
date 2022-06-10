<?php

//Example feature

/*
 * Feature list
 *
 * Values type : integer, boolean, string, array
 * Format: percentage, count, string
 * */
return [
    [
        "uuid" => "b48a3a6c-c1fb-11ec-9d64-0242ac120002",
        "name" => "Features 1",
        "slug" => "feature-1",
        "description" => "Feature Description",
        "value_type" => "integer",
        "format" => "count",
        "display_order" => 1,
        "hidden_feature" => false,
        "group_order" => "1",
        "group" => "Group 1",
    ],
    [
        "uuid" => "b48a3a6c-c1fb-11ec-9d64-0242ac120003",
        "name" => "Features 2",
        "slug" => "feature-2",
        "description" => "Feature Description",
        "value_type" => "string",
        "format" => null,
        "display_order" => 2,
        "hidden_feature" => false,
        "group_order" => "1",
        "group" => "Group 1",
    ],
    [
        "uuid" => "b48a3a6c-c1fb-11ec-9d64-0242ac120004",
        "name" => "Features 3",
        "slug" => "feature-3",
        "description" => "Feature Description",
        "value_type" => "boolean",
        "format" => null,
        "display_order" => 3,
        "hidden_feature" => false,
        "group_order" => "2",
        "group" => "Group 2",
    ],
    [
        "uuid" => "b48a3a6c-c1fb-11ec-9d64-0242ac120005",
        "name" => "Features 4",
        "slug" => "feature-4",
        "description" => "Feature Description",
        "value_type" => "array",
        "values" => [
            "value-1" => "Value 1",
            "value-2" => "Value 2",
            "value-3" => "Value 4",
        ],
        "format" => null,
        "display_order" => 4,
        "hidden_feature" => false,
        "group_order" => "2",
        "group" => "Group 2",
    ],
];