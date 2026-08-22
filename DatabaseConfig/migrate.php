<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit("Not found.");
}

require_once __DIR__ . "/../DatabaseConnection.php";

$pdo = app_database_pdo();
$schema = file_get_contents(__DIR__ . "/schema.sql");
if ($schema === false) {
    throw new RuntimeException("Could not read DatabaseConfig/schema.sql.");
}

$schemaWithoutComments = preg_replace('/^\s*--.*$/m', '', $schema);
$statements = preg_split('/;\s*(?:\r?\n|$)/', (string) $schemaWithoutComments);

foreach ($statements ?: [] as $statement) {
    $statement = trim($statement);
    if ($statement !== "") {
        $pdo->exec($statement);
    }
}

$requiredTables = [
    "Users",
    "Profiles",
    "Friends",
    "AllExercises",
    "Workouts",
    "WorkoutExercises",
    "ExerciseSets",
    "FAQs",
    "GeminiUsage",
    "PhpSessions",
    "PhpSessionLocks",
];

$tableCheck = $pdo->prepare(
    "SELECT COUNT(*)
     FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = :table_name"
);
foreach ($requiredTables as $tableName) {
    $tableCheck->execute([":table_name" => $tableName]);
    if ((int) $tableCheck->fetchColumn() !== 1) {
        throw new RuntimeException("Required table {$tableName} is missing.");
    }
}

$requiredUniqueIndexes = [
    ["Users", "uq_users_username"],
    ["Users", "uq_users_email"],
    ["AllExercises", "uq_exercises_name"],
];
$indexCheck = $pdo->prepare(
    "SELECT COUNT(*)
     FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = :table_name
       AND index_name = :index_name
       AND non_unique = 0"
);
foreach ($requiredUniqueIndexes as [$tableName, $indexName]) {
    $indexCheck->execute([
        ":table_name" => $tableName,
        ":index_name" => $indexName,
    ]);
    if ((int) $indexCheck->fetchColumn() < 1) {
        throw new RuntimeException(
            "Existing table {$tableName} is incompatible: unique index {$indexName} is missing."
        );
    }
}

$csv = fopen(__DIR__ . "/workouts.csv", "rb");
if ($csv === false) {
    throw new RuntimeException("Could not read DatabaseConfig/workouts.csv.");
}

$header = fgetcsv($csv, null, ",", '"', "\\");
if ($header !== ["exercisename", "workouttype", "description", "input_type"]) {
    fclose($csv);
    throw new RuntimeException("The exercise CSV header is invalid.");
}

$upsert = $pdo->prepare(
    "INSERT INTO AllExercises (exercisename, workouttype, description, input_type)
     VALUES (:name, :type, :description, :input_type)
     ON DUPLICATE KEY UPDATE
       workouttype = VALUES(workouttype),
       description = VALUES(description),
       input_type = VALUES(input_type)"
);

$pdo->beginTransaction();
try {
    while (($row = fgetcsv($csv, null, ",", '"', "\\")) !== false) {
        if (count($row) !== 4) {
            throw new RuntimeException("The exercise CSV contains an invalid row.");
        }
        $upsert->execute([
            ":name" => trim($row[0]),
            ":type" => trim($row[1]),
            ":description" => trim($row[2]),
            ":input_type" => trim($row[3]),
        ]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
} finally {
    fclose($csv);
}

$exerciseCount = (int) $pdo->query("SELECT COUNT(*) FROM AllExercises")->fetchColumn();

printf(
    "Database migration complete: %d required tables, %d exercises.\n",
    count($requiredTables),
    $exerciseCount
);
