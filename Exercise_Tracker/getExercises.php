<?php
include __DIR__ . "/../DatabaseInit.php";
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

$workoutTypes = [
    'Weight Training' => ['Strength','Core', 'Bodyweight','Rest'],
    'HIIT' => ['HIIT','Cardio','Bodyweight','Rest'],
    'Flexibility' => ['Flexibility','Rest'],
    'Cardio' => ['Cardio','Rest'],
    '' => ['Strength', 'Core', 'Bodyweight', 'HIIT', 'Cardio', 'Flexibility','Rest']
];

$types = $workoutTypes[$type] ?? [];

try {
    if (count($types) > 0) {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $query = "SELECT exerciseid, exercisename, input_type
                  FROM AllExercises
                  WHERE workouttype IN ($placeholders)
                  ORDER BY exercisename";

        $stmt = $pdo->prepare($query);
        $stmt->execute($types);
    } else {
        $stmt = $pdo->query("SELECT exerciseid, exercisename, input_type FROM AllExercises ORDER BY exercisename");
    }

    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($exercises);

} catch (Exception $e) {
    echo json_encode([]);
}
?>