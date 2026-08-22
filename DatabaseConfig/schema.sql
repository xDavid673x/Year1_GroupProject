-- Canonical Motiv8 schema for MySQL 8 and TiDB Cloud Starter.
-- The database is created by the hosting provider, so this file only creates
-- application objects inside the selected database.

CREATE TABLE IF NOT EXISTS Users (
  userid INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  passwordhash VARCHAR(255) NOT NULL,
  PhoneNum VARCHAR(30) NOT NULL,
  role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (userid),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS Profiles (
  userid INT NOT NULL,
  displayname VARCHAR(255) NULL,
  privacy ENUM('private', 'public') NOT NULL DEFAULT 'public',
  height INT NULL,
  weight INT NULL,
  age INT NULL,
  gym VARCHAR(255) NULL,
  BMI DECIMAL(5,2) NULL,
  streak INT NOT NULL DEFAULT 0,
  bio VARCHAR(500) NULL,
  profilepicURL VARCHAR(2083) NULL,
  PRIMARY KEY (userid),
  CONSTRAINT fk_profiles_user
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS Friends (
  userA INT NOT NULL,
  userB INT NOT NULL,
  friendstatus ENUM('pending', 'blocked', 'friends') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (userA, userB),
  KEY idx_friends_user_b (userB),
  KEY idx_friends_status (friendstatus),
  CONSTRAINT fk_friends_user_a
    FOREIGN KEY (userA) REFERENCES Users(userid) ON DELETE CASCADE,
  CONSTRAINT fk_friends_user_b
    FOREIGN KEY (userB) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS AllExercises (
  exerciseid INT NOT NULL AUTO_INCREMENT,
  exercisename VARCHAR(255) NOT NULL,
  workouttype VARCHAR(255) NOT NULL,
  description VARCHAR(255) NOT NULL,
  input_type ENUM('reps', 'duration') NOT NULL,
  PRIMARY KEY (exerciseid),
  UNIQUE KEY uq_exercises_name (exercisename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS Workouts (
  workoutID INT NOT NULL AUTO_INCREMENT,
  userid INT NOT NULL,
  workoutname VARCHAR(255) NOT NULL,
  workoutbio VARCHAR(255) NULL,
  workoutdate DATE NOT NULL,
  workouttime TIME NOT NULL,
  workouttype VARCHAR(255) NOT NULL,
  PRIMARY KEY (workoutID),
  KEY idx_workouts_user_date (userid, workoutdate),
  CONSTRAINT fk_workouts_user
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS WorkoutExercises (
  workoutexerciseid INT NOT NULL AUTO_INCREMENT,
  workoutID INT NOT NULL,
  exerciseid INT NOT NULL,
  sets INT NOT NULL,
  PRIMARY KEY (workoutexerciseid),
  KEY idx_workout_exercises_workout (workoutID),
  KEY idx_workout_exercises_exercise (exerciseid),
  CONSTRAINT fk_workout_exercises_workout
    FOREIGN KEY (workoutID) REFERENCES Workouts(workoutID) ON DELETE CASCADE,
  CONSTRAINT fk_workout_exercises_exercise
    FOREIGN KEY (exerciseid) REFERENCES AllExercises(exerciseid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS ExerciseSets (
  setid INT NOT NULL AUTO_INCREMENT,
  workoutexerciseid INT NOT NULL,
  setnum INT NOT NULL,
  reps INT NULL,
  weight DECIMAL(8,2) NULL,
  duration INT NULL,
  unit VARCHAR(10) NULL,
  PRIMARY KEY (setid),
  KEY idx_exercise_sets_workout_exercise (workoutexerciseid),
  CONSTRAINT fk_exercise_sets_workout_exercise
    FOREIGN KEY (workoutexerciseid) REFERENCES WorkoutExercises(workoutexerciseid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS FAQs (
  faq_id INT NOT NULL AUTO_INCREMENT,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (faq_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS GeminiUsage (
  usage_date DATE NOT NULL,
  requests_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS PhpSessions (
  session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_data MEDIUMBLOB NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  PRIMARY KEY (session_id),
  KEY idx_php_sessions_last_activity (last_activity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS PhpSessionLocks (
  session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  PRIMARY KEY (session_id),
  KEY idx_php_session_locks_last_activity (last_activity)
) ENGINE=InnoDB;
