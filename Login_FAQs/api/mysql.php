<?php
declare(strict_types=1);

require_once __DIR__ . "/../../DatabaseConnection.php";

function mysql_pdo(): PDO
{
    return app_database_pdo();
}
