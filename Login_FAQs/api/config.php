<?php
declare(strict_types=1);

$config = [
    "mysql" => [
        "host" => getenv("MYSQL_HOST") ?: "localhost",
        "port" => (int) (getenv("MYSQL_PORT") ?: 3306),
        "database" => getenv("MYSQL_DATABASE") ?: "motiv8",
        "username" => getenv("MYSQL_USERNAME") ?: "root",
        "password" => getenv("MYSQL_PASSWORD") ?: "",
        "charset" => "utf8mb4",
        "timeout_seconds" => (int) (getenv("MYSQL_TIMEOUT_SECONDS") ?: 5),
    ],
    "mongodb" => [
        "uri" => getenv("MONGODB_URI") ?: "",
        "database" => getenv("MONGODB_DATABASE") ?: "gymapp",
        "users_collection" => getenv("MONGODB_USERS_COLLECTION") ?: "users_profiles",
    ],
    "gemini" => [
        "api_keys" => array_values(array_filter([
            getenv("GEMINI_API_KEY") ?: "",
            getenv("GEMINI_API_KEY_2") ?: "",
        ])),
        "models" => [
            getenv("GEMINI_MODEL") ?: "gemini-3.1-flash-lite",
            getenv("GEMINI_MODEL_FALLBACK_1") ?: "gemini-2.5-flash",
            getenv("GEMINI_MODEL_FALLBACK_2") ?: "gemini-2.5-flash-lite",
            getenv("GEMINI_MODEL_FALLBACK_3") ?: "gemini-1.5-flash",
            getenv("GEMINI_MODEL_FALLBACK_4") ?: "gemini-1.5-flash-8b",
        ],
        "api_base" => getenv("GEMINI_API_BASE") ?: "https://generativelanguage.googleapis.com/v1beta",
        "timeout_seconds" => (int) (getenv("GEMINI_TIMEOUT_SECONDS") ?: 30),
        "system_prompt" => getenv("GEMINI_SYSTEM_PROMPT") ?: "You are an elite, highly encouraging personal trainer and data-driven fitness coach for the Motiv8 app.\n"
            . "Rules:\n"
            . "- If the JSON snapshot shows 0 workouts, enthusiastically welcome the user to Motiv8! Briefly explain the benefits of logging workouts and suggest a simple beginner workout they can track today. Do not attempt to analyze missing data.\n"
            . "- If the user asks a specific question (e.g., 'How is my squat?'), answer it directly and concisely using their data.\n"
            . "- ONLY provide the full 5-point report if the user asks for a general review or 'analyze my data'. The 5-point report includes: 1) Weekly summary, 2) Progress trends, 3) Risk flags, 4) 3 actionable recommendations, 5) Motivational note.\n"
            . "- Format your responses beautifully using Markdown. Use `###` for headings, `**bold**` for key metrics or exercise names, and bulleted lists `*` to make information easy to skim.\n"
            . "- Be specific and data-driven.\n"
            . "- CRITICAL: Keep your responses extremely brief, simple, and precise. Cut out all fluff, long paragraphs, and unnecessary pleasantries. Aim for a maximum of 2-3 short sentences per reply.",
    ],
];

$localConfigPath = __DIR__ . "/config.local.php";
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
