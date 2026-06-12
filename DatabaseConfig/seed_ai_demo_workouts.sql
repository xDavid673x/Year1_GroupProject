-- AI assistant demo workout seed
-- Run this in phpMyAdmin against your project database.
-- This script attaches demo workouts to the existing account:
-- username: my_name_is_david
-- email: leidavid673@gmail.com

START TRANSACTION;

SET @demo_user_id = (
  SELECT userid
  FROM Users
  WHERE email = 'leidavid673@gmail.com'
     OR username = 'my_name_is_david'
  ORDER BY userid DESC
  LIMIT 1
);

INSERT INTO Profiles (userid, displayname, privacy, height, weight, age, gym, BMI, streak, bio)
VALUES (
  @demo_user_id,
  'David Demo Athlete',
  'public',
  176,
  74,
  21,
  'Motiv8 Gym',
  23.89,
  4,
  'Demo workout data for AI assistant analysis.'
)
ON DUPLICATE KEY UPDATE
  displayname = VALUES(displayname),
  privacy = VALUES(privacy),
  height = VALUES(height),
  weight = VALUES(weight),
  age = VALUES(age),
  gym = VALUES(gym),
  BMI = VALUES(BMI),
  streak = VALUES(streak),
  bio = VALUES(bio);

-- Remove previous demo workouts so the script can be re-run safely.
DELETE FROM Workouts
WHERE userid = @demo_user_id
  AND workoutbio LIKE 'AI assistant demo seed%';

SET @bench_press = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Bench Press' LIMIT 1);
SET @squat = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Squat' LIMIT 1);
SET @deadlift = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Deadlift' LIMIT 1);
SET @running = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Running' LIMIT 1);
SET @cycling = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Cycling' LIMIT 1);
SET @burpees = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Burpees' LIMIT 1);
SET @mountain_climbers = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Mountain Climbers' LIMIT 1);
SET @plank = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Plank' LIMIT 1);
SET @yoga_stretch = (SELECT exerciseid FROM AllExercises WHERE exercisename = 'Yoga Stretch' LIMIT 1);

-- Workout 1: strength baseline
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'Upper Strength Baseline', 'AI assistant demo seed: chest and lower body strength baseline.', '2026-04-18', '18:10:00', 'Weight Training');
SET @w1 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w1, @bench_press, 3);
SET @we1 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we1, 1, 10, 40, 'kg'),
(@we1, 2, 8, 45, 'kg'),
(@we1, 3, 6, 50, 'kg');

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w1, @squat, 3);
SET @we2 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we2, 1, 10, 55, 'kg'),
(@we2, 2, 8, 60, 'kg'),
(@we2, 3, 8, 60, 'kg');

-- Workout 2: cardio session
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'Steady Cardio Run', 'AI assistant demo seed: moderate cardio endurance session.', '2026-04-20', '17:30:00', 'Cardio');
SET @w2 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w2, @running, 1);
SET @we3 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit) VALUES
(@we3, 1, 28, 'min');

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w2, @plank, 2);
SET @we4 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit) VALUES
(@we4, 1, 2, 'min'),
(@we4, 2, 2, 'min');

-- Workout 3: heavier strength day
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'Lower Body Progression', 'AI assistant demo seed: heavier lower-body training with improved load.', '2026-04-22', '19:00:00', 'Weight Training');
SET @w3 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w3, @squat, 3);
SET @we5 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we5, 1, 8, 62, 'kg'),
(@we5, 2, 8, 65, 'kg'),
(@we5, 3, 6, 70, 'kg');

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w3, @deadlift, 3);
SET @we6 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we6, 1, 6, 75, 'kg'),
(@we6, 2, 5, 80, 'kg'),
(@we6, 3, 5, 85, 'kg');

-- Workout 4: HIIT and conditioning
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'HIIT Conditioning', 'AI assistant demo seed: high-intensity conditioning and core work.', '2026-04-24', '18:45:00', 'HIIT');
SET @w4 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w4, @burpees, 3);
SET @we7 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we7, 1, 12, 1, 'kg'),
(@we7, 2, 10, 1, 'kg'),
(@we7, 3, 10, 1, 'kg');

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w4, @mountain_climbers, 2);
SET @we8 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit) VALUES
(@we8, 1, 4, 'min'),
(@we8, 2, 4, 'min');

-- Workout 5: current-week mixed session for leaderboard and AI demo
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'Current Week Mixed Session', 'AI assistant demo seed: current-week mixed training for leaderboard and AI analysis.', '2026-04-27', '09:20:00', 'Weight Training');
SET @w5 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w5, @bench_press, 3);
SET @we9 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, reps, weight, unit) VALUES
(@we9, 1, 8, 47, 'kg'),
(@we9, 2, 8, 50, 'kg'),
(@we9, 3, 6, 52, 'kg');

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w5, @cycling, 1);
SET @we10 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit) VALUES
(@we10, 1, 18, 'min');

-- Workout 6: recovery/mobility session
INSERT INTO Workouts (userid, workoutname, workoutbio, workoutdate, workouttime, workouttype)
VALUES (@demo_user_id, 'Recovery Mobility', 'AI assistant demo seed: light flexibility and recovery session after harder training.', '2026-04-27', '19:10:00', 'Flexibility');
SET @w6 = LAST_INSERT_ID();

INSERT INTO WorkoutExercises (workoutID, exerciseid, sets) VALUES (@w6, @yoga_stretch, 1);
SET @we11 = LAST_INSERT_ID();
INSERT INTO ExerciseSets (workoutexerciseid, setnum, duration, unit) VALUES
(@we11, 1, 22, 'min');

COMMIT;
