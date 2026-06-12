<?php
include "DatabaseInit.php";

//Function to create table and/or display error message
function create_table($sqlIn){
    global $pdo;
    try {
        $pdo->exec($sqlIn);
    } catch (PDOException $e) {
        die("Table creation failed: " . $e->getMessage());
    }

}

//Create Users table
$sql = "CREATE TABLE IF NOT EXISTS Users(userid INT AUTO_INCREMENT PRIMARY KEY,
                                        username VARCHAR(255) NOT NULL UNIQUE,
                                        passwordhash VARCHAR(255) NOT NULL,
                                        email VARCHAR(255),
                                        PhoneNum VARCHAR(20),
                                        role ENUM('admin','member') DEFAULT 'member',
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";

create_table($sql);

//Create Profiles table
$sql = "CREATE TABLE IF NOT EXISTS Profiles(userid INT PRIMARY KEY,
                                            displayname VARCHAR(255),
                                            privacy ENUM('private','public') NOT NULL DEFAULT 'public',
                                            height INT,
                                            weight INT,
                                            age INT,
                                            gym VARCHAR(255),
                                            BMI DECIMAL(5,2),
                                            streak INT DEFAULT 0,
                                            bio VARCHAR(500),
                                            profilepicURL VARCHAR(2083),
                                            FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE)";
create_table($sql);

//Create Friends table
$sql = "CREATE TABLE IF NOT EXISTS Friends(userA INT NOT NULL,
                                            userB INT NOT NULL,
                                            friendstatus ENUM('pending','blocked','friends') NOT NULL DEFAULT 'pending',
                                            PRIMARY KEY (userA, userB),
                                            FOREIGN KEY(userA) REFERENCES Users(userid),
                                            FOREIGN KEY(userB) REFERENCES Users(userid))";
create_table($sql);

//Create All Exercises table
$sql = "CREATE TABLE IF NOT EXISTS AllExercises(exerciseid INT AUTO_INCREMENT PRIMARY KEY,
                                                exercisename VARCHAR(255),
                                                workouttype VARCHAR(255),
                                                description VARCHAR(255)),
                                                input_type ENUM('reps','duration')";
create_table($sql);

//Create Workouts table
$sql = "CREATE TABLE IF NOT EXISTS Workouts(workoutID INT AUTO_INCREMENT PRIMARY KEY,
                                            userid INT,
                                            workoutname VARCHAR(255),
                                            workoutbio VARCHAR(255),
                                            workoutdate DATE,
                                            workouttime TIME,
                                            workouttype VARCHAR(255),
                                            FOREIGN KEY(userid) REFERENCES Users(userid))";

create_table($sql);

//Create Workout Exercises table
$sql = "CREATE TABLE WorkoutExercises(
                        workoutexerciseid INT AUTO_INCREMENT PRIMARY KEY,
                        workoutID INT,
                        exerciseid INT,
                        sets INT,
                        FOREIGN KEY(workoutID) REFERENCES Workouts(workoutID) ON DELETE CASCADE,
                        FOREIGN KEY(exerciseid) REFERENCES AllExercises(exerciseid) ON DELETE CASCADE)";

create_table($sql);

//Create Exercise Sets table
$sql = "CREATE TABLE ExerciseSets(
                        setid INT AUTO_INCREMENT PRIMARY KEY,
                        workoutexerciseid INT,
                        setnum INT,
                        reps INT,
                        weight INT,
                        duration INT,
                        unit VARCHAR(10),
                        FOREIGN KEY(workoutexerciseid) REFERENCES WorkoutExercises(workoutexerciseid) ON DELETE CASCADE)";
create_table($sql);
?>