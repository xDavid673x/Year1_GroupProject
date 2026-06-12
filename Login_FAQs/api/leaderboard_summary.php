<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";

require_method("GET");

function leaderboard_score(array $row): int
{
    $workouts = (int) ($row["workouts_count"] ?? 0);
    $sets = (int) ($row["valid_sets"] ?? 0);
    $duration = (int) ($row["total_duration_minutes"] ?? 0);
    $volume = (int) ($row["total_volume"] ?? 0);

    $score = ($workouts * 10)
        + ($sets * 2)
        + intdiv(max(0, $duration), 5)
        + intdiv(max(0, $volume), 100);

    if ($workouts >= 5) {
        $score += 10;
    } elseif ($workouts >= 3) {
        $score += 5;
    }

    return $score;
}

$today = new DateTimeImmutable("today");
$startDate = $today->modify("monday this week");

try {
    $pdo = mysql_pdo();
    $stmt = $pdo->prepare(
        "SELECT
            u.userid,
            u.username,
            COALESCE(NULLIF(TRIM(p.displayname), ''), u.username) AS display_name,
            COUNT(DISTINCT CASE
                WHEN w.workoutdate BETWEEN :start_date AND :end_date THEN w.workoutID
                ELSE NULL
            END) AS workouts_count,
            SUM(CASE
                WHEN w.workoutdate BETWEEN :start_date AND :end_date
                 AND (
                    (es.reps BETWEEN 1 AND 100 AND es.weight BETWEEN 1 AND 500)
                    OR (es.duration BETWEEN 1 AND 300)
                 )
                THEN 1
                ELSE 0
            END) AS valid_sets,
            SUM(CASE
                WHEN w.workoutdate BETWEEN :start_date AND :end_date
                 AND es.duration BETWEEN 1 AND 300
                THEN es.duration
                ELSE 0
            END) AS total_duration_minutes,
            SUM(CASE
                WHEN w.workoutdate BETWEEN :start_date AND :end_date
                 AND es.reps BETWEEN 1 AND 100
                 AND es.weight BETWEEN 1 AND 500
                THEN es.reps * es.weight
                ELSE 0
            END) AS total_volume
        FROM Users u
        LEFT JOIN Profiles p
            ON p.userid = u.userid
        LEFT JOIN Workouts w
            ON w.userid = u.userid
        LEFT JOIN WorkoutExercises we
            ON we.workoutID = w.workoutID
        LEFT JOIN ExerciseSets es
            ON es.workoutexerciseid = we.workoutexerciseid
        GROUP BY u.userid, u.username, p.displayname
        HAVING workouts_count > 0 OR valid_sets > 0"
    );

    $stmt->execute([
        ":start_date" => $startDate->format("Y-m-d"),
        ":end_date" => $today->format("Y-m-d"),
    ]);

    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log(sprintf(
        "[leaderboard_summary.php] %s in %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    json_response([
        "ok" => false,
        "error" => "Leaderboard data could not be loaded.",
    ], 500);
}

foreach ($rows as &$row) {
    $row["workouts_count"] = (int) ($row["workouts_count"] ?? 0);
    $row["valid_sets"] = (int) ($row["valid_sets"] ?? 0);
    $row["total_duration_minutes"] = (int) ($row["total_duration_minutes"] ?? 0);
    $row["total_volume"] = (int) ($row["total_volume"] ?? 0);
    $row["score"] = leaderboard_score($row);
}
unset($row);

usort($rows, static function (array $left, array $right): int {
    return [$right["score"], $right["workouts_count"], $right["valid_sets"], $right["total_volume"]]
        <=> [$left["score"], $left["workouts_count"], $left["valid_sets"], $left["total_volume"]];
});

$topRows = array_slice($rows, 0, 3);
$leaders = [];
$previousKey = null;
$displayRank = 0;

foreach ($topRows as $index => $row) {
    $rankKey = implode(":", [
        $row["score"],
        $row["workouts_count"],
        $row["valid_sets"],
        $row["total_volume"],
    ]);

    if ($rankKey !== $previousKey) {
        $displayRank = $index + 1;
        $previousKey = $rankKey;
    }

    $leaders[] = [
        "rank" => $displayRank,
        "display_name" => (string) $row["display_name"],
        "username" => (string) $row["username"],
        "score" => (int) $row["score"],
        "workouts_count" => (int) $row["workouts_count"],
        "valid_sets" => (int) $row["valid_sets"],
    ];
}

json_response([
    "ok" => true,
    "range_label" => sprintf("%s to %s", $startDate->format("j M"), $today->format("j M")),
    "leaders" => $leaders,
]);
