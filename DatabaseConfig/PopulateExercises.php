<?php
include __DIR__."/../DatabaseInit.php";

// CSV file
$workoutscsv = "workouts.csv";

// Check if file exists
if (!file_exists($workoutscsv)) {
    die("CSV file not found.");
}

// Check if table has already been populated
$stmt = $pdo->query("SELECT COUNT(*) AS count FROM AllExercises");
$count = $stmt->fetch(PDO::FETCH_ASSOC);

if ($count['count'] == 0) {

    $pdo->beginTransaction();

    if (($file = fopen($workoutscsv, 'r')) !== false) {
        $row = 0;
        $sql = "INSERT INTO AllExercises (exercisename, workouttype, description, input_type) 
                VALUES (:name, :type, :desc, :inp)";
        $stmt = $pdo -> prepare($sql);

        while ($data = fgetcsv($file)) {
            // Skip first row (header)
            if ($row == 0) { 
                $row++; 
                continue;
            }

            // Load and trim data
            $exercisename = trim($data[0]);
            $workouttype = trim($data[1]);
            $description = trim($data[2]);
            $inputtype = trim($data[3]);

            // Execute insert
            $stmt -> execute([":name" => $exercisename,":type" => $workouttype,":desc" => $description,":inp" => $inputtype]);
            $row++;
        }
        fclose($file);
    }

    // Commit transaction
    $pdo->commit();
    echo "CSV imported successfully!";
    
} else {
    echo "Table already populated.";
}
?>
