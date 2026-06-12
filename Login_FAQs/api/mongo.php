<?php
declare(strict_types=1);

function mongo_users_collection(): ?object
{
    if (!class_exists("\\MongoDB\\Client")) {
        return null;
    }

    $config = require __DIR__ . "/config.php";
    $mongo = $config["mongodb"];

    if (empty($mongo["uri"])) {
        return null;
    }

    $client = new MongoDB\Client($mongo["uri"]);
    return $client
        ->selectDatabase($mongo["database"])
        ->selectCollection($mongo["users_collection"]);
}
