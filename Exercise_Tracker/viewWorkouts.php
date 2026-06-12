<?php
include __DIR__ . "/../DatabaseInit.php";
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

function formatWorkoutDateTime(string $date = '', string $time = ''): string
{
    if ($date === '') {
        return 'Date unavailable';
    }

    $timestamp = strtotime(trim($date . ' ' . $time));
    if ($timestamp === false) {
        return trim($date . ' ' . $time);
    }

    return date('d M Y', $timestamp) . ($time !== '' ? ' at ' . date('H:i', $timestamp) : '');
}

function formatSetSummary(array $set): string
{
    $setNum = isset($set['setnum']) ? (int) $set['setnum'] : 0;
    $prefix = $setNum > 0 ? 'Set ' . $setNum . ' · ' : '';

    if (isset($set['reps']) && $set['reps'] !== null) {
        $weight = isset($set['weight']) && $set['weight'] !== null ? (int) $set['weight'] : 0;
        $unit = trim((string) ($set['unit'] ?? ''));
        return $prefix . (int) $set['reps'] . ' reps @ ' . $weight . ($unit !== '' ? ' ' . $unit : '');
    }

    if (isset($set['duration']) && $set['duration'] !== null) {
        $unit = trim((string) ($set['unit'] ?? 'sec'));
        return $prefix . (int) $set['duration'] . ' ' . $unit;
    }

    return $prefix . 'Logged';
}

function hydrateWorkouts(PDO $pdo, array $workouts): array
{
    $exerciseStmt = $pdo->prepare("SELECT * FROM WorkoutExercises WHERE workoutID = :wid ORDER BY workoutexerciseid ASC");
    $setStmt = $pdo->prepare(
        "SELECT es.*, ae.exercisename
         FROM ExerciseSets es
         INNER JOIN WorkoutExercises we ON we.workoutexerciseid = es.workoutexerciseid
         INNER JOIN AllExercises ae ON ae.exerciseid = we.exerciseid
         WHERE es.workoutexerciseid = :weid
         ORDER BY es.setnum ASC"
    );

    foreach ($workouts as &$workout) {
        $exerciseStmt->execute(['wid' => $workout['workoutID']]);
        $exerciseRows = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);
        $exerciseCards = [];
        $setsCount = 0;

        foreach ($exerciseRows as $exercise) {
            $setStmt->execute(['weid' => $exercise['workoutexerciseid']]);
            $sets = $setStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$sets) {
                continue;
            }

            $setsCount += count($sets);
            $exerciseCards[] = [
                'name' => $sets[0]['exercisename'] ?? 'Exercise',
                'set_lines' => array_map('formatSetSummary', $sets),
            ];
        }

        $workout['exercise_cards'] = $exerciseCards;
        $workout['exercise_count'] = count($exerciseCards);
        $workout['sets_count'] = $setsCount;
        $workout['display_name'] = trim((string) ($workout['displayname'] ?? '')) ?: (string) ($workout['username'] ?? 'Member');
        $workout['profile_pic'] = trim((string) ($workout['profilepicURL'] ?? '')) ?: '../homepage/img/profile-placeholder.jpg';
        $workout['formatted_datetime'] = formatWorkoutDateTime((string) ($workout['workoutdate'] ?? ''), (string) ($workout['workouttime'] ?? ''));
        $workout['type_label'] = trim((string) ($workout['workouttype'] ?? '')) ?: 'Workout';
    }
    unset($workout);

    return $workouts;
}

$myStmt = $pdo->prepare(
    "SELECT w.*, u.username, p.displayname, p.profilepicURL
     FROM Workouts w
     INNER JOIN Users u ON u.userid = w.userid
     LEFT JOIN Profiles p ON p.userid = w.userid
     WHERE w.userid = :uid
     ORDER BY w.workoutdate DESC, w.workouttime DESC"
);
$myStmt->execute(['uid' => $_SESSION['user_id']]);
$myWorkouts = hydrateWorkouts($pdo, $myStmt->fetchAll(PDO::FETCH_ASSOC));

$friendsStmt = $pdo->prepare(
    "SELECT w.*, u.username, p.displayname, p.profilepicURL
     FROM Workouts w
     INNER JOIN Users u ON u.userid = w.userid
     INNER JOIN Friends f ON
        ((f.userA = :uid AND f.userB = w.userid) OR
         (f.userB = :uid AND f.userA = w.userid))
     LEFT JOIN Profiles p ON p.userid = w.userid
     WHERE f.friendstatus = 'friends'
     ORDER BY w.workoutdate DESC, w.workouttime DESC"
);
$friendsStmt->execute(['uid' => $_SESSION['user_id']]);
$friendsWorkouts = hydrateWorkouts($pdo, $friendsStmt->fetchAll(PDO::FETCH_ASSOC));

$thisWeekStart = new DateTimeImmutable('monday this week');
$thisWeekCount = 0;
$totalSetsLogged = 0;
foreach ($myWorkouts as $workout) {
    $totalSetsLogged += (int) ($workout['sets_count'] ?? 0);
    $workoutDate = trim((string) ($workout['workoutdate'] ?? ''));
    if ($workoutDate !== '' && $workoutDate >= $thisWeekStart->format('Y-m-d')) {
        $thisWeekCount += 1;
    }
}
$latestSession = $myWorkouts[0]['formatted_datetime'] ?? 'No workouts saved yet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motiv8 | Workout History</title>
    <link rel="stylesheet" href="../global.css">
    <link rel="stylesheet" href="viewWorkouts.css?v=5">
    <script defer src="viewWorkouts.js?v=3"></script>
</head>
<body>
    <div class="history-hero-decor" aria-hidden="true">
        <div class="hero-overlay"></div>
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
    </div>

    <main class="history-page">
        <div class="history-topbar">
            <a class="history-back-link" href="../homepage/homepage.html#exercise-tracker">Back to Home</a>
            <a class="history-back-link" href="exerciseTracker.php">Start New Workout</a>
        </div>

        <section class="history-hero-card">
            <div class="history-hero-copy">
                <p class="history-kicker">Workout history</p>
                <h1>Review your sessions and track your consistency</h1>
                <p class="history-subtitle">See your saved workouts, compare activity from friends, and open the details of each session without digging through raw entries.</p>
            </div>
            <div class="history-stats-grid">
                <article class="history-stat-card">
                    <span class="history-stat-label">My workouts</span>
                    <strong><?php echo count($myWorkouts); ?></strong>
                </article>
                <article class="history-stat-card">
                    <span class="history-stat-label">This week</span>
                    <strong><?php echo $thisWeekCount; ?></strong>
                </article>
                <article class="history-stat-card">
                    <span class="history-stat-label">Logged sets</span>
                    <strong><?php echo $totalSetsLogged; ?></strong>
                </article>
                <article class="history-stat-card">
                    <span class="history-stat-label">Latest session</span>
                    <strong class="history-stat-text"><?php echo htmlspecialchars((string) $latestSession); ?></strong>
                </article>
            </div>
        </section>

        <div class="toggle-buttons">
            <button id="show-my" class="nav-link-btn active" type="button">My Workouts</button>
            <button id="show-friends" class="nav-link-btn" type="button">Friends' Workouts</button>
        </div>

        <section id="my-workouts" class="history-section">
            <?php if (!$myWorkouts): ?>
            <article class="history-empty-card">
                <h2>No workouts saved yet</h2>
                <p>Your saved workout history will appear here once you log your first session.</p>
                <a href="exerciseTracker.php" class="history-action-link">Start a workout</a>
            </article>
            <?php else: ?>
            <div class="workout-feed">
                <?php foreach ($myWorkouts as $workout): ?>
                <article class="workout-view">
                    <div class="workout-header">
                        <div class="workout-header-copy">
                            <p class="workout-type-badge"><?php echo htmlspecialchars((string) $workout['type_label']); ?></p>
                            <h2><?php echo htmlspecialchars((string) $workout['workoutname']); ?></h2>
                            <p class="workout-meta"><?php echo htmlspecialchars((string) $workout['formatted_datetime']); ?></p>
                        </div>
                        <div class="workout-header-actions">
                            <button class="workout-toggle-btn" type="button" aria-expanded="false" aria-controls="workout-<?php echo (int) $workout['workoutID']; ?>" onclick="showWorkout(<?php echo (int) $workout['workoutID']; ?>)">View details</button>
                            <button class="delete-btn" type="button" onclick="deleteWorkout(<?php echo (int) $workout['workoutID']; ?>, event)">Delete</button>
                        </div>
                    </div>

                    <?php if (!empty($workout['workoutbio'])): ?>
                    <div class="workout-bio">
                        <p><?php echo htmlspecialchars((string) $workout['workoutbio']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="workout-summary-row">
                        <span class="workout-summary-pill"><?php echo (int) $workout['exercise_count']; ?> exercises</span>
                        <span class="workout-summary-pill"><?php echo (int) $workout['sets_count']; ?> sets</span>
                    </div>

                    <div class="workout-details" id="workout-<?php echo (int) $workout['workoutID']; ?>">
                        <?php foreach ($workout['exercise_cards'] as $index => $exercise): ?>
                        <div class="exercise-view">
                            <h3><?php echo (int) ($index + 1); ?>. <?php echo htmlspecialchars((string) $exercise['name']); ?></h3>
                            <ul class="set-list">
                                <?php foreach ($exercise['set_lines'] as $setLine): ?>
                                <li><?php echo htmlspecialchars((string) $setLine); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section id="friends-workouts" class="history-section" hidden>
            <div class="friends-header-row">
                <p class="friends-helper">Compare your recent activity with your friends' latest workouts.</p>
                <button type="button" class="add-friends-button" onclick="window.location.href='../Friends/friends.php'">Open Friends</button>
            </div>

            <?php if (!$friendsWorkouts): ?>
            <article class="history-empty-card">
                <h2>No workouts from friends yet</h2>
                <p>Once you add friends and they log sessions, their workouts will appear here.</p>
                <a href="../Friends/friends.php" class="history-action-link">Add friends</a>
            </article>
            <?php else: ?>
            <div class="workout-feed">
                <?php foreach ($friendsWorkouts as $workout): ?>
                <article class="workout-view friend-workout">
                    <div class="workout-header">
                        <div class="workout-header-copy with-avatar">
                            <img src="<?php echo htmlspecialchars((string) $workout['profile_pic']); ?>" alt="<?php echo htmlspecialchars((string) $workout['display_name']); ?> profile picture" class="profile-pic">
                            <div>
                                <p class="workout-type-badge"><?php echo htmlspecialchars((string) $workout['type_label']); ?></p>
                                <h2><?php echo htmlspecialchars((string) $workout['workoutname']); ?></h2>
                                <p class="workout-meta"><?php echo htmlspecialchars((string) $workout['display_name']); ?> · <?php echo htmlspecialchars((string) $workout['formatted_datetime']); ?></p>
                            </div>
                        </div>
                        <div class="workout-header-actions">
                            <button class="workout-toggle-btn" type="button" aria-expanded="false" aria-controls="workout-<?php echo (int) $workout['workoutID']; ?>" onclick="showWorkout(<?php echo (int) $workout['workoutID']; ?>)">View details</button>
                        </div>
                    </div>

                    <?php if (!empty($workout['workoutbio'])): ?>
                    <div class="workout-bio">
                        <p><?php echo htmlspecialchars((string) $workout['workoutbio']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="workout-summary-row">
                        <span class="workout-summary-pill"><?php echo (int) $workout['exercise_count']; ?> exercises</span>
                        <span class="workout-summary-pill"><?php echo (int) $workout['sets_count']; ?> sets</span>
                    </div>

                    <div class="workout-details" id="workout-<?php echo (int) $workout['workoutID']; ?>">
                        <?php foreach ($workout['exercise_cards'] as $index => $exercise): ?>
                        <div class="exercise-view">
                            <h3><?php echo (int) ($index + 1); ?>. <?php echo htmlspecialchars((string) $exercise['name']); ?></h3>
                            <ul class="set-list">
                                <?php foreach ($exercise['set_lines'] as $setLine): ?>
                                <li><?php echo htmlspecialchars((string) $setLine); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
