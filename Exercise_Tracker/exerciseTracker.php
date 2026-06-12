<?php
session_start();

$workoutDate = $_POST['date'] ?? date('Y-m-d');
$workoutTime = $_POST['time'] ?? date('H:i');
$workoutType = trim((string) ($_POST['workout-type'] ?? ''));
$username = (string) ($_SESSION['username'] ?? 'Your');
$isAuthenticated = !empty($_SESSION['user_id']);
$displayWorkoutType = $workoutType !== '' ? $workoutType : 'Workout Builder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motiv8 | Exercise Tracker</title>
    <link rel="stylesheet" href="../global.css">
    <link rel="stylesheet" href="exerciseTracker.css?v=6">
    <script>
        const initialWorkoutType = <?php echo json_encode($workoutType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const trackerUsername = <?php echo json_encode($username, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script defer src="exerciseTracker.js?v=6"></script>
</head>
<body>
    <div class="tracker-hero-decor" aria-hidden="true">
        <div class="hero-overlay"></div>
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
    </div>

    <main class="tracker-page">
        <div class="tracker-topbar">
            <a href="../homepage/homepage.html#exercise-tracker" class="tracker-back-link">Back to Home</a>
            <?php if ($isAuthenticated): ?>
            <a href="viewWorkouts.php" class="tracker-top-link">View Saved Workouts</a>
            <?php endif; ?>
        </div>

        <section class="tracker-hero-card">
            <div class="tracker-hero-copy">
                <p class="tracker-kicker">Exercise tracker</p>
                <h1 id="trackerHeroTitle"><?php echo htmlspecialchars($username); ?>'s <?php echo htmlspecialchars($displayWorkoutType); ?> workout</h1>
                <p class="tracker-subtitle">Build your session clearly, log each exercise with proper sets, and keep your workout history organised for later review.</p>
                <div class="tracker-pill-row">
                    <span class="tracker-pill"><?php echo htmlspecialchars($workoutDate); ?></span>
                    <span class="tracker-pill"><?php echo htmlspecialchars($workoutTime); ?></span>
                    <span class="tracker-pill" id="trackerTypePill"><?php echo htmlspecialchars($displayWorkoutType); ?></span>
                </div>
            </div>

            <div class="tracker-hero-actions">
                <a href="../Videos/videos.html" target="_blank" rel="noopener noreferrer" class="tracker-primary-link">Video tutorials</a>
                <?php if ($isAuthenticated): ?>
                <a href="viewWorkouts.php" class="tracker-secondary-link">Open workout history</a>
                <?php else: ?>
                <a href="../Login_FAQs/login.html" class="tracker-secondary-link">Go to login</a>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$isAuthenticated): ?>
        <section class="tracker-auth-gate" aria-labelledby="trackerAuthTitle">
            <div class="tracker-auth-glow" aria-hidden="true"></div>
            <p class="tracker-auth-kicker">Login required</p>
            <h2 id="trackerAuthTitle">Log in to build and save your <span>next workout</span></h2>
            <p class="tracker-auth-text">You can preview the tracker here, but saving sessions, viewing workout history, and tracking progress all require an account session.</p>
            <div class="tracker-auth-tags">
                <span>Workout builder</span>
                <span>Saved history</span>
                <span>Progress tracking</span>
            </div>
            <div class="tracker-auth-actions">
                <a href="../Login_FAQs/login.html" class="tracker-primary-link">Go to Login</a>
                <a href="../homepage/homepage.html#exercise-tracker" class="tracker-secondary-link">Back to Homepage</a>
            </div>
        </section>
        <?php else: ?>
        <section class="tracker-shell">
            <div class="tracker-main-card">
                <div class="tracker-section-head">
                    <p class="tracker-section-kicker">Workout builder</p>
                    <h2>Start a new session</h2>
                    <p>Choose exercises, add at least one set for each movement, and save the session once everything is complete.</p>
                </div>

                <form id="workout-form" action="processExercises.php" method="post" class="tracker-form">
                    <div class="tracker-form-grid">
                        <div class="tracker-field field-span-2">
                            <label for="trackerWorkoutType">Workout type</label>
                            <select id="trackerWorkoutType" name="workout-type" required>
                                <option value="">Choose workout type</option>
                                <option value="Weight Training" <?php echo $workoutType === 'Weight Training' ? 'selected' : ''; ?>>Weight Training</option>
                                <option value="HIIT" <?php echo $workoutType === 'HIIT' ? 'selected' : ''; ?>>HIIT</option>
                                <option value="Flexibility" <?php echo $workoutType === 'Flexibility' ? 'selected' : ''; ?>>Flexibility</option>
                                <option value="Cardio" <?php echo $workoutType === 'Cardio' ? 'selected' : ''; ?>>Cardio</option>
                            </select>
                        </div>

                        <div class="tracker-field field-span-2">
                            <label for="workout-title">Workout name</label>
                            <input type="text" id="workout-title" placeholder="Name your workout" maxlength="50" required class="workout-title" name="Title">
                        </div>

                        <div class="tracker-field field-span-2">
                            <label for="workout-description">Workout description</label>
                            <input type="text" id="workout-description" placeholder="Add a short description" class="workout-description" name="Description">
                        </div>
                    </div>

                    <input type="hidden" value="<?php echo htmlspecialchars($workoutDate); ?>" name="workout-date">
                    <input type="hidden" value="<?php echo htmlspecialchars($workoutTime); ?>" name="workout-time">

                    <div class="tracker-section-subhead">
                        <h3>Exercises</h3>
                        <p>Search for an exercise, select it, then fill in the sets with reps and weight or duration depending on the movement type.</p>
                    </div>

                    <div class="tracker-empty-state" id="trackerEmptyState">
                        <strong>No exercises added yet</strong>
                        <p>Use the button below to add your first exercise.</p>
                    </div>

                    <div class="Exercises" id="exercises-container"></div>

                    <button type="button" class="tracker-add-btn" onclick="addExercise()">Add Exercise</button>

                    <div id="save-button" class="tracker-save-slot"></div>
                </form>
            </div>

            <aside class="tracker-side-card">
                <div class="tracker-side-block">
                    <p class="tracker-section-kicker">Quick actions</p>
                    <a href="viewWorkouts.php" class="tracker-side-link">View saved workouts</a>
                    <a href="../Videos/videos.html" target="_blank" rel="noopener noreferrer" class="tracker-side-link">Open workout videos</a>
                </div>

                <div class="tracker-side-block">
                    <p class="tracker-section-kicker">Tips</p>
                    <ul class="tracker-side-list">
                        <li>Keep workout names short and clear.</li>
                        <li>Add every set you actually completed.</li>
                        <li>Use accurate units so your progress is easier to analyse later.</li>
                    </ul>
                </div>

                <div class="tracker-side-block">
                    <p class="tracker-section-kicker">Session details</p>
                    <div class="tracker-pill-row compact">
                        <span class="tracker-pill"><?php echo htmlspecialchars($workoutDate); ?></span>
                        <span class="tracker-pill"><?php echo htmlspecialchars($workoutTime); ?></span>
                        <span class="tracker-pill" id="trackerSideTypePill"><?php echo htmlspecialchars($displayWorkoutType); ?></span>
                    </div>
                </div>
            </aside>
        </section>
        <?php endif; ?>
    </main>
</body>
</html>
