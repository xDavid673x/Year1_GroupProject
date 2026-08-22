<?php
include __DIR__ . "/../DatabaseInit.php";
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

$workoutName = trim($_POST['Title'] ?? '');
$description = trim($_POST['Description'] ?? '');
$date = $_POST['workout-date'] ?? '';
$time = $_POST['workout-time'] ?? '';
$type = $_POST['workout-type'] ?? '';
$exercises = $_POST['exercises'] ?? [];

if ($workoutName === '' || $date === '' || $time === '' || empty($exercises)) {
    die("Missing required workout data.");
}

try {
    // The hosted database session handler can already hold a transaction for
    // the session lock. PDO does not support starting a nested transaction.
    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    $sql = "INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
            VALUES (:userid, :name, :bio, :date, :time, :type)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        "userid" => $_SESSION['user_id'],
        "name" => $workoutName,
        "bio" => $description,
        "date" => $date,
        "time" => $time,
        "type" => $type
    ]);

    $workoutID = $pdo->lastInsertId();

    foreach ($exercises as $exercise) {
        $setNum = 0;
        $exerciseID = $exercise['id'] ?? null;
        $sets = $exercise['sets'] ?? [];
        $amountOfSets = count($sets);

        if (!$exerciseID || $amountOfSets === 0) {
            continue;
        }

        $sql = "INSERT INTO WorkoutExercises (workoutID, exerciseid, sets)
                VALUES (:wid, :eid, :sets)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'wid' => $workoutID,
            'eid' => $exerciseID,
            'sets' => $amountOfSets
        ]);

        $workoutExerciseID = $pdo->lastInsertId();

        foreach ($sets as $set) {
            $setNum++;

            if (isset($set['reps']) && $set['reps'] !== '') {
                $reps = $set['reps'];
                $weight = $set['weight'] ?? null;
                $unit = $set['unit'] ?? '';

                $sql = "INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit)
                        VALUES (:weid, :sn, :reps, :weight, :unit)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'weid' => $workoutExerciseID,
                    'sn' => $setNum,
                    'reps' => $reps,
                    'weight' => $weight,
                    'unit' => $unit
                ]);
            } elseif (isset($set['duration']) && $set['duration'] !== '') {
                $duration = $set['duration'];
                $unit = $set['unit'] ?? '';

                $sql = "INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit)
                        VALUES (:weid, :sn, :dur, :unit)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'weid' => $workoutExerciseID,
                    'sn' => $setNum,
                    'dur' => $duration,
                    'unit' => $unit
                ]);
            }
        }
    }

    if ($startedTransaction) {
        $pdo->commit();
    }
    header("Location: viewWorkouts.php");
    exit;
} catch (Exception $e) {
    if (($startedTransaction ?? false) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error saving workout: " . $e->getMessage());
}
?>
