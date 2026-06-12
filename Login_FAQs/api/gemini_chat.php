<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
$config = require __DIR__ . "/config.php";
require_once __DIR__ . "/mysql.php";

require_method("POST");
$authUser = require_auth_user();

function normalize_gemini_model_name(string $model): string
{
    $model = trim($model);
    if (stripos($model, "models/") === 0) {
        $model = substr($model, 7);
    }
    return trim($model);
}

function resolve_exercise_name_column(PDO $pdo): ?string
{
    try {
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'AllExercises'"
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $error) {
        return null;
    }

    $columns = [];
    foreach ($rows as $row) {
        $name = (string) ($row["COLUMN_NAME"] ?? "");
        if ($name !== "") {
            $columns[strtolower($name)] = $name;
        }
    }

    foreach (["exercisename", "exercise_name", "name", "title"] as $candidate) {
        if (isset($columns[$candidate])) {
            return $columns[$candidate];
        }
    }

    return null;
}

function resolve_table_columns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name"
        );
        $stmt->execute(["table_name" => $table]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        return [];
    }

    $columns = [];
    foreach ($rows as $row) {
        $name = strtolower(trim((string) ($row["COLUMN_NAME"] ?? "")));
        if ($name !== "") {
            $columns[$name] = true;
        }
    }

    return $columns;
}

function fetch_exercise_name_map(PDO $pdo, array $exerciseIds): array
{
    if ($exerciseIds === []) {
        return [];
    }

    $nameColumn = resolve_exercise_name_column($pdo);
    if ($nameColumn === null || !preg_match('/^[A-Za-z0-9_]+$/', $nameColumn)) {
        return [];
    }

    $placeholders = implode(",", array_fill(0, count($exerciseIds), "?"));
    $sql = "SELECT exerciseid, `{$nameColumn}` AS exercise_name
            FROM AllExercises
            WHERE exerciseid IN ({$placeholders})";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($exerciseIds);
        $rows = $stmt->fetchAll();
    } catch (Throwable $error) {
        return [];
    }

    $nameMap = [];
    foreach ($rows as $row) {
        $id = (int) ($row["exerciseid"] ?? 0);
        $name = trim((string) ($row["exercise_name"] ?? ""));
        if ($id > 0 && $name !== "") {
            $nameMap[$id] = $name;
        }
    }

    return $nameMap;
}

function fetch_user_training_snapshot(int $userId): array
{
    try {
        $pdo = mysql_pdo();
    } catch (Throwable $error) {
        return [
            "user_id" => $userId,
            "error" => "Unable to read workout data.",
        ];
    }

    $workoutsStmt = $pdo->prepare(
        "SELECT workoutID, workoutname, workoutbio, workoutdate, workouttime, workouttype
         FROM Workouts
         WHERE userid = :user_id
         ORDER BY workoutdate DESC, workouttime DESC, workoutID DESC
         LIMIT 12"
    );
    $workoutsStmt->execute(["user_id" => $userId]);
    $workouts = $workoutsStmt->fetchAll();

    $workoutTypes = [];
    foreach ($workouts as $workout) {
        $type = trim((string) ($workout["workouttype"] ?? ""));
        if ($type === "") {
            $type = "Unspecified";
        }
        $workoutTypes[$type] = ($workoutTypes[$type] ?? 0) + 1;
    }

    $workoutIds = [];
    foreach ($workouts as $workout) {
        $workoutId = (int) ($workout["workoutID"] ?? 0);
        if ($workoutId > 0) {
            $workoutIds[] = $workoutId;
        }
    }

    $exerciseRows = [];
    $exerciseIds = [];
    $workoutExerciseIds = [];
    $exerciseIdByWorkoutExerciseId = [];
    $workoutExerciseColumns = resolve_table_columns($pdo, "WorkoutExercises");
    $hasWorkoutExerciseId = isset($workoutExerciseColumns["workoutexerciseid"]);
    if ($workoutIds !== []) {
        $workoutPlaceholders = implode(",", array_fill(0, count($workoutIds), "?"));
        $workoutExerciseIdSelect = $hasWorkoutExerciseId ? ", workoutexerciseid" : "";
        $exerciseStmt = $pdo->prepare(
            "SELECT workoutID, exerciseid, sets{$workoutExerciseIdSelect}
             FROM WorkoutExercises
             WHERE workoutID IN ($workoutPlaceholders)
             ORDER BY workoutID DESC, exerciseid ASC"
        );
        $exerciseStmt->execute($workoutIds);
        $exerciseRows = $exerciseStmt->fetchAll();

        foreach ($exerciseRows as $row) {
            $exerciseId = (int) ($row["exerciseid"] ?? 0);
            if ($exerciseId > 0) {
                $exerciseIds[$exerciseId] = true;
            }

            if ($hasWorkoutExerciseId) {
                $workoutExerciseId = (int) ($row["workoutexerciseid"] ?? 0);
                if ($workoutExerciseId > 0) {
                    $workoutExerciseIds[$workoutExerciseId] = true;
                    $exerciseIdByWorkoutExerciseId[$workoutExerciseId] = $exerciseId;
                }
            }
        }
    }

    $exerciseSetRows = [];
    $exerciseIdList = array_keys($exerciseIds);
    $exerciseNameMap = fetch_exercise_name_map($pdo, $exerciseIdList);

    foreach ($exerciseRows as &$exerciseRow) {
        $exerciseId = (int) ($exerciseRow["exerciseid"] ?? 0);
        $exerciseRow["exercise_name"] = $exerciseNameMap[$exerciseId] ?? null;
    }
    unset($exerciseRow);

    $exerciseSetColumns = resolve_table_columns($pdo, "ExerciseSets");
    $hasSetWorkoutExerciseId = isset($exerciseSetColumns["workoutexerciseid"]);
    $hasSetExerciseId = isset($exerciseSetColumns["exerciseid"]);

    if ($hasSetWorkoutExerciseId && $workoutExerciseIds !== []) {
        $setPlaceholders = implode(",", array_fill(0, count($workoutExerciseIds), "?"));
        $setStmt = $pdo->prepare(
            "SELECT setid, workoutexerciseid, setnum, reps, weight, duration, unit
             FROM ExerciseSets
             WHERE workoutexerciseid IN ($setPlaceholders)
             ORDER BY setid DESC
             LIMIT 120"
        );
        $setStmt->execute(array_keys($workoutExerciseIds));
        $exerciseSetRows = $setStmt->fetchAll();

        foreach ($exerciseSetRows as &$setRow) {
            $workoutExerciseId = (int) ($setRow["workoutexerciseid"] ?? 0);
            $exerciseId = (int) ($exerciseIdByWorkoutExerciseId[$workoutExerciseId] ?? 0);
            $setRow["exerciseid"] = $exerciseId;
            $setRow["exercise_name"] = $exerciseNameMap[$exerciseId] ?? null;
        }
        unset($setRow);
    } elseif ($hasSetExerciseId && $exerciseIdList !== []) {
        $exercisePlaceholders = implode(",", array_fill(0, count($exerciseIdList), "?"));
        $setStmt = $pdo->prepare(
            "SELECT setid, exerciseid, setnum, reps, weight, duration, unit
             FROM ExerciseSets
             WHERE exerciseid IN ($exercisePlaceholders)
             ORDER BY setid DESC
             LIMIT 120"
        );
        $setStmt->execute($exerciseIdList);
        $exerciseSetRows = $setStmt->fetchAll();

        foreach ($exerciseSetRows as &$setRow) {
            $exerciseId = (int) ($setRow["exerciseid"] ?? 0);
            $setRow["exercise_name"] = $exerciseNameMap[$exerciseId] ?? null;
        }
        unset($setRow);
    }

    $exerciseOverviewById = [];
    foreach ($exerciseRows as $row) {
        $exerciseId = (int) ($row["exerciseid"] ?? 0);
        if ($exerciseId <= 0) {
            continue;
        }

        if (!isset($exerciseOverviewById[$exerciseId])) {
            $exerciseOverviewById[$exerciseId] = [
                "exercise_id" => $exerciseId,
                "exercise_name" => $exerciseNameMap[$exerciseId] ?? null,
                "workout_occurrences" => 0,
                "programmed_sets" => 0,
                "logged_sets" => 0,
                "total_reps" => 0,
                "max_weight" => null,
                "weight_unit" => null,
                "total_duration" => 0,
                "duration_unit" => null,
            ];
        }

        $exerciseOverviewById[$exerciseId]["workout_occurrences"] += 1;
        $exerciseOverviewById[$exerciseId]["programmed_sets"] += (int) ($row["sets"] ?? 0);
    }

    foreach ($exerciseSetRows as $setRow) {
        $exerciseId = (int) ($setRow["exerciseid"] ?? 0);
        if ($exerciseId <= 0) {
            continue;
        }

        if (!isset($exerciseOverviewById[$exerciseId])) {
            $exerciseOverviewById[$exerciseId] = [
                "exercise_id" => $exerciseId,
                "exercise_name" => $exerciseNameMap[$exerciseId] ?? null,
                "workout_occurrences" => 0,
                "programmed_sets" => 0,
                "logged_sets" => 0,
                "total_reps" => 0,
                "max_weight" => null,
                "weight_unit" => null,
                "total_duration" => 0,
                "duration_unit" => null,
            ];
        }

        $overview = &$exerciseOverviewById[$exerciseId];
        $overview["logged_sets"] += 1;
        $overview["total_reps"] += (int) ($setRow["reps"] ?? 0);
        $overview["total_duration"] += (int) ($setRow["duration"] ?? 0);

        $weight = $setRow["weight"];
        if ($weight !== null) {
            $weightValue = (float) $weight;
            if ($overview["max_weight"] === null || $weightValue > (float) $overview["max_weight"]) {
                $overview["max_weight"] = $weightValue;
                $overview["weight_unit"] = trim((string) ($setRow["unit"] ?? "")) ?: null;
            }
        }

        if ($overview["duration_unit"] === null && $setRow["duration"] !== null) {
            $overview["duration_unit"] = trim((string) ($setRow["unit"] ?? "")) ?: null;
        }
        unset($overview);
    }

    $exerciseOverview = array_values($exerciseOverviewById);
    usort(
        $exerciseOverview,
        static fn (array $a, array $b): int => ((int) $a["exercise_id"]) <=> ((int) $b["exercise_id"])
    );

    return [
        "generated_at_utc" => gmdate("c"),
        "user_id" => $userId,
        "workout_count" => count($workouts),
        "workout_types" => $workoutTypes,
        "recent_workouts" => $workouts,
        "workout_exercises" => $exerciseRows,
        "recent_exercise_sets" => $exerciseSetRows,
        "exercise_overview" => $exerciseOverview,
    ];
}

$gemini = is_array($config["gemini"] ?? null) ? $config["gemini"] : [];
$apiKeys = [];
if (!empty($gemini["api_keys"]) && is_array($gemini["api_keys"])) {
    foreach ($gemini["api_keys"] as $k) {
        $k = trim((string)$k);
        if ($k !== "") {
            $apiKeys[] = $k;
        }
    }
} elseif (!empty($gemini["api_key"])) {
    $apiKeys[] = trim((string)$gemini["api_key"]);
}
$models = [];
if (!empty($gemini["models"]) && is_array($gemini["models"])) {
    foreach ($gemini["models"] as $m) {
        $m = normalize_gemini_model_name((string)$m);
        if ($m !== "") {
            $models[] = $m;
        }
    }
} elseif (!empty($gemini["model"])) {
    $models[] = normalize_gemini_model_name((string)$gemini["model"]);
}
if ($models === []) {
    $models[] = "gemini-3.1-flash-lite";
}

$apiBase = rtrim((string) ($gemini["api_base"] ?? "https://generativelanguage.googleapis.com/v1beta"), "/");
$timeoutSeconds = max(5, (int) ($gemini["timeout_seconds"] ?? 30));
$systemPrompt = trim((string) ($gemini["system_prompt"] ?? ""));

if ($apiKeys === []) {
    json_response(["error" => "Gemini API keys are missing. Set them in config.php."], 500);
}

if (!function_exists("curl_init")) {
    json_response(["error" => "Server is missing cURL support."], 500);
}

$data = get_request_data();
$prompt = trim((string) ($data["prompt"] ?? ""));
$requestedModel = normalize_gemini_model_name((string) ($data["model"] ?? ""));
if ($requestedModel !== "") {
    array_unshift($models, $requestedModel);
    $models = array_values(array_unique($models));
}
$history = is_array($data["history"] ?? null) ? $data["history"] : [];
$trainingSnapshot = fetch_user_training_snapshot((int) ($authUser["id"] ?? 0));

$promptLength = function_exists("mb_strlen") ? mb_strlen($prompt) : strlen($prompt);
if ($prompt === "") {
    json_response(["error" => "Prompt is required."], 422);
}
if ($promptLength > 4000) {
    json_response(["error" => "Prompt is too long. Keep it under 4000 characters."], 422);
}

$contents = [];
$lastRole = null;
foreach ($history as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $roleRaw = (string) ($entry["role"] ?? "");
    $text = trim((string) ($entry["content"] ?? ""));
    if ($text === "") {
        continue;
    }

    $role = null;
    if ($roleRaw === "user") {
        $role = "user";
    } elseif ($roleRaw === "assistant") {
        $role = "model";
    }

    if ($role === null) {
        continue;
    }

    if ($role === $lastRole) {
        $lastIndex = count($contents) - 1;
        $contents[$lastIndex]["parts"][0]["text"] .= "\n\n" . $text;
    } else {
        $contents[] = [
            "role" => $role,
            "parts" => [["text" => $text]],
        ];
        $lastRole = $role;
    }
}

if ("user" === $lastRole) {
    $lastIndex = count($contents) - 1;
    $contents[$lastIndex]["parts"][0]["text"] .= "\n\n" . $prompt;
} else {
    $contents[] = [
        "role" => "user",
        "parts" => [["text" => $prompt]],
    ];
}

$payload = [
    "contents" => $contents,
];
$snapshotJson = json_encode($trainingSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($snapshotJson) || $snapshotJson === "") {
    $snapshotJson = "{}";
}

$coachPrompt = $systemPrompt;
if ($coachPrompt === "") {
    $coachPrompt = "You are an exercise data analyst for a gym app. Provide practical, safe, personalized advice.";
}

$coachPrompt .= "\n\nUse the JSON snapshot below from the app database to personalize your advice."
    . "\nIf data is missing or sparse, explicitly state what is missing and what the user should track next."
    . "\nKeep answers concise and structured with headings and bullet points."
    . "\nAvoid shorthand like 'Exercise 2: 1 set, 12 reps @ 1 kg'."
    . "\nAlways use exercise_name when available; only use Exercise ID if the name is missing."
    . "\nUse plain language format instead, e.g. 'Goblet Squat - 1 set of 12 reps at 1 kg'."
    . "\n\nUSER_TRAINING_SNAPSHOT_JSON:\n"
    . $snapshotJson;

if ($coachPrompt !== "") {
    $payload["systemInstruction"] = [
        "parts" => [
            ["text" => $coachPrompt],
        ],
    ];
}

$rawResponse = false;
$curlError = "";
$status = 0;
$decoded = null;
$lastApiMessage = "";
$success = false;
$usedModel = "";

foreach ($models as $currentModel) {
    foreach ($apiKeys as $currentApiKey) {
        $endpoint = $apiBase . "/models/" . rawurlencode($currentModel) . ":generateContent?key=" . rawurlencode($currentApiKey);
        
        $ch = curl_init($endpoint);
        if ($ch === false) {
            json_response(["error" => "Unable to initialize Gemini request."], 500);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        
        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        
        if ($rawResponse === false) {
            $lastApiMessage = "cURL Error: " . $curlError;
            continue;
        }
        
        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            $lastApiMessage = "Gemini returned an invalid response.";
            continue;
        }
        
        // 429 = Too Many Requests, 403 = Forbidden / Invalid API Key, 404 = Model Not Found, 5xx = Server Error
        if ($status === 429 || $status === 403 || $status === 404 || $status >= 500) {
            $lastApiMessage = (string) ($decoded["error"]["message"] ?? "Rate limit, invalid key, model not found, or server error.");
            continue;
        }
        
        // If not a rate limit or bad key error, break and process the response
        $success = true;
        $usedModel = $currentModel;
        break; // break api keys loop
    }
    
    if ($success) {
        break; // break models loop
    }
}

if ($rawResponse === false || !is_array($decoded)) {
    json_response(["error" => "Gemini request failed after trying available keys and models: " . $lastApiMessage], 502);
}

if ($status >= 400) {
    $apiMessage = (string) ($decoded["error"]["message"] ?? "Gemini request failed.");
    json_response(["error" => $apiMessage], 502);
}

$parts = $decoded["candidates"][0]["content"]["parts"] ?? [];
$reply = "";
if (is_array($parts)) {
    foreach ($parts as $part) {
        if (is_array($part) && isset($part["text"])) {
            $reply .= (string) $part["text"];
        }
    }
}
$reply = trim($reply);

if ($reply === "") {
    $blockReason = (string) ($decoded["promptFeedback"]["blockReason"] ?? "");
    if ($blockReason !== "") {
        json_response(["error" => "Gemini blocked this prompt (" . $blockReason . ")."], 422);
    }
    json_response(["error" => "Gemini returned an empty response."], 502);
}

try {
    $pdo = mysql_pdo();
    $pdo->exec("INSERT INTO GeminiUsage (usage_date, requests_count) VALUES (CURDATE(), 1) ON DUPLICATE KEY UPDATE requests_count = requests_count + 1");
} catch (Exception $e) {
    // Ignore error if table hasn't been created yet
}

json_response([
    "ok" => true,
    "reply" => $reply,
    "model" => $usedModel,
]);
