<?php
declare(strict_types=1);

require __DIR__ . "/api/mysql.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    session_set_cookie_params([
        "httponly" => true,
        "secure" => $isHttps,
        "samesite" => "Lax",
    ]);
    session_start();
}

$currentUserId = (int) ($_SESSION["user_id"] ?? 0);
$isAuthenticated = $currentUserId > 0;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function selected_scope(): string
{
    $scope = strtolower(trim((string) ($_GET["scope"] ?? "global")));
    return in_array($scope, ["global", "friends"], true) ? $scope : "global";
}

function selected_period(): string
{
    $period = strtolower(trim((string) ($_GET["period"] ?? "week")));
    return in_array($period, ["week", "month"], true) ? $period : "week";
}

function score_row(array $row): int
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

$scope = selected_scope();
$scope = $isAuthenticated ? $scope : "global";
$period = selected_period();
$today = new DateTimeImmutable("today");
$startDate = $period === "month"
    ? $today->modify("first day of this month")
    : $today->modify("monday this week");

$dateRangeLabel = sprintf(
    "%s to %s",
    $startDate->format("j M Y"),
    $today->format("j M Y")
);

$rows = [];
$pageError = "";
$pageErrorDetail = "";

try {
    $pdo = mysql_pdo();
    $stmt = $pdo->prepare(
        "SELECT
            u.userid,
            u.username,
            COALESCE(NULLIF(TRIM(p.displayname), ''), u.username) AS display_name,
            COALESCE(p.streak, 0) AS streak,
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
        WHERE (
            :scope = 'global'
            OR u.userid = :current_user_id
            OR EXISTS (
                SELECT 1
                FROM Friends f
                WHERE f.friendstatus = 'friends'
                  AND (
                    (f.userA = :current_user_id AND f.userB = u.userid)
                    OR (f.userB = :current_user_id AND f.userA = u.userid)
                  )
            )
        )
        GROUP BY u.userid, u.username, p.displayname, p.streak
        HAVING workouts_count > 0 OR valid_sets > 0"
    );

    $stmt->execute([
        ":start_date" => $startDate->format("Y-m-d"),
        ":end_date" => $today->format("Y-m-d"),
        ":scope" => $scope,
        ":current_user_id" => $currentUserId,
    ]);

    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log(sprintf(
        "[leaderboard.php] %s in %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    $pageError = "Leaderboard data could not be loaded right now.";
    $pageErrorDetail = sprintf(
        "%s in %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
}

foreach ($rows as &$row) {
    $row["workouts_count"] = (int) ($row["workouts_count"] ?? 0);
    $row["valid_sets"] = (int) ($row["valid_sets"] ?? 0);
    $row["total_duration_minutes"] = (int) ($row["total_duration_minutes"] ?? 0);
    $row["total_volume"] = (int) ($row["total_volume"] ?? 0);
    $row["streak"] = (int) ($row["streak"] ?? 0);
    $row["score"] = score_row($row);
    $row["is_current_user"] = (int) ($row["userid"] ?? 0) === $currentUserId;
}
unset($row);

usort($rows, static function (array $left, array $right): int {
    return [$right["score"], $right["workouts_count"], $right["valid_sets"], $right["total_volume"], $right["streak"]]
        <=> [$left["score"], $left["workouts_count"], $left["valid_sets"], $left["total_volume"], $left["streak"]];
});

$previousKey = null;
$displayRank = 0;
$currentUserRow = null;

foreach ($rows as $index => &$row) {
    $rankKey = implode(":", [
        $row["score"],
        $row["workouts_count"],
        $row["valid_sets"],
        $row["total_volume"],
        $row["streak"],
    ]);

    if ($rankKey !== $previousKey) {
        $displayRank = $index + 1;
        $previousKey = $rankKey;
    }

    $row["rank"] = $displayRank;
}
unset($row);

foreach ($rows as $row) {
    if ($isAuthenticated && $row["userid"] == $currentUserId) {
        $currentUserRow = $row;
        break;
    }
}

$topRows = array_slice($rows, 0, 10);
$topPerformer = $topRows[0] ?? null;
$scopeLabel = $scope === "friends" ? "Friends" : "Global";
$periodLabel = $period === "month" ? "Month to date" : "Week to date";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Training Leaderboard</title>
  <link rel="stylesheet" href="../global.css" />
  <link rel="stylesheet" href="styles.css?v=26" />
</head>
<body class="dashboard-page has-floating-header leaderboard-page">
  <header class="floating-header">
      <div class="nav-shell">
          <a href="../homepage/homepage.html" class="brand" aria-label="Go to Motiv8 homepage">
              <img src="../img/edited-photo-Photoroom.png" alt="Motiv8 logo" class="brand-logo">
          </a>

          <div class="menu-toggle" id="menuToggle" role="button" tabindex="0" aria-controls="mainNav" aria-expanded="false">&#9776;</div>

          <nav class="floating-nav" aria-label="Main navigation" id="mainNav">
              <a href="../homepage/homepage.html" class="nav-item">Home</a>
              <a href="../Videos/videos.html" class="nav-item">Videos</a>
              <a href="../Gym_Locator/gym_locator.html" class="nav-item">Gym Locator</a>
              <a href="../Profile/profile.php" class="nav-item">Profile</a>
              <a href="../Login_FAQs/faq.html" class="nav-item">FAQ</a>
              <a href="../Login_FAQs/gemini.html" class="nav-item">Gemini</a>
              <a href="../Login_FAQs/leaderboard.php" class="nav-item active">Leaderboard</a>
          </nav>

          <div class="nav-cta">
              <a href="../Login_FAQs/login.html" class="login-btn">Log In / Sign Up</a>
          </div>  
      </div>
  </header>

  <div class="faq-hero-decor" aria-hidden="true">
    <div class="hero-overlay"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-orb hero-orb-3"></div>
  </div>

  <main class="leaderboard-main">
    <section class="leaderboard-hero">
      <p class="leaderboard-kicker"><?php echo h($scopeLabel); ?> ranking</p>
      <h1 class="leaderboard-title">
        Training
        <span>Leaderboard</span>
      </h1>
      <p class="leaderboard-subtitle">
        Ranked by recent effort: workouts, valid sets, cardio minutes, and lifting volume.
      </p>

      <div class="leaderboard-toolbar" aria-label="Leaderboard filters">
        <div class="leaderboard-filter-group">
          <a href="?scope=global&amp;period=<?php echo h($period); ?>" class="leaderboard-pill<?php echo $scope === "global" ? " active" : ""; ?>">Global</a>
          <?php if ($isAuthenticated): ?>
            <a href="?scope=friends&amp;period=<?php echo h($period); ?>" class="leaderboard-pill<?php echo $scope === "friends" ? " active" : ""; ?>">Friends</a>
          <?php else: ?>
            <div class="leaderboard-filter-lockup">
              <button
                type="button"
                class="leaderboard-pill leaderboard-pill-muted leaderboard-pill-locked"
                id="friendsFilterButton"
                aria-expanded="false"
                aria-controls="friendsFilterPopover"
              >
                Friends
              </button>
              <div class="leaderboard-login-popover" id="friendsFilterPopover" role="dialog" aria-label="Friends leaderboard login prompt">
                <p class="leaderboard-login-popover-title">Friends ranking is locked</p>
                <p class="leaderboard-login-popover-copy">Log in to compare your training progress with your friends.</p>
                <a href="login.html" class="leaderboard-login-popover-link">Log In To Continue</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="leaderboard-filter-group">
          <a href="?scope=<?php echo h($scope); ?>&amp;period=week" class="leaderboard-pill<?php echo $period === "week" ? " active" : ""; ?>">This Week</a>
          <a href="?scope=<?php echo h($scope); ?>&amp;period=month" class="leaderboard-pill<?php echo $period === "month" ? " active" : ""; ?>">This Month</a>
        </div>
      </div>
    </section>

    <section class="leaderboard-summary" aria-label="Leaderboard summary">
      <article class="leaderboard-stat">
        <span class="leaderboard-stat-label">Range</span>
        <strong class="leaderboard-stat-value"><?php echo h($periodLabel); ?></strong>
        <p class="leaderboard-stat-copy"><?php echo h($dateRangeLabel); ?></p>
      </article>

      <article class="leaderboard-stat">
        <span class="leaderboard-stat-label">Top Athlete</span>
        <strong class="leaderboard-stat-value"><?php echo h($topPerformer["display_name"] ?? "No data"); ?></strong>
        <p class="leaderboard-stat-copy"><?php echo $topPerformer ? (int) $topPerformer["score"] . " activity points" : "No workouts logged yet"; ?></p>
      </article>

      <article class="leaderboard-stat">
        <span class="leaderboard-stat-label">Your Position</span>
        <strong class="leaderboard-stat-value"><?php echo $isAuthenticated ? ($currentUserRow ? "#" . (int) $currentUserRow["rank"] : "Unranked") : "Login"; ?></strong>
        <p class="leaderboard-stat-copy"><?php echo $isAuthenticated ? ($currentUserRow ? (int) $currentUserRow["score"] . " points so far" : "Log a workout to appear here") : "Sign in to track your own rank and use friends view."; ?></p>
      </article>
    </section>

    <section class="leaderboard-card">
      <div class="leaderboard-card-head">
        <div>
          <p class="leaderboard-card-kicker">Scoring</p>
          <h2>Current Standings</h2>
        </div>
        <div class="leaderboard-score-help">
          <button
            type="button"
            class="leaderboard-info-button"
            id="leaderboardInfoButton"
            aria-expanded="false"
            aria-controls="leaderboardInfoTooltip"
            aria-label="How scoring works"
          >
            <span aria-hidden="true">i</span>
          </button>
          <div class="leaderboard-tooltip" id="leaderboardInfoTooltip" role="tooltip">
            <p class="leaderboard-tooltip-title">How scoring works</p>
            <ul class="leaderboard-tooltip-list">
              <li>10 points per workout</li>
              <li>2 points per valid set</li>
              <li>1 point per 5 cardio minutes</li>
              <li>1 point per 100 kg lifted</li>
              <li>Bonus points for consistent training</li>
            </ul>
          </div>
        </div>
      </div>

      <?php if ($pageError !== ""): ?>
        <div class="leaderboard-empty-state">
          <h3>Leaderboard unavailable</h3>
          <p><?php echo h($pageError); ?></p>
          <?php if ($pageErrorDetail !== ""): ?>
            <p><code><?php echo h($pageErrorDetail); ?></code></p>
          <?php endif; ?>
        </div>
      <?php elseif (!$topRows): ?>
        <div class="leaderboard-empty-state">
          <h3>No ranked workouts yet</h3>
          <p>Once members log workouts in the selected period, the leaderboard will appear here.</p>
        </div>
      <?php else: ?>
        <div class="leaderboard-table-shell">
          <table class="leaderboard-table">
            <thead>
              <tr>
                <th scope="col">Rank</th>
                <th scope="col">Athlete</th>
                <th scope="col">Score</th>
                <th scope="col">Workouts</th>
                <th scope="col">Sets</th>
                <th scope="col">Minutes</th>
                <th scope="col">
                  <div class="leaderboard-th-help">
                    <span class="leaderboard-th-label">Total Volume</span>
                    <button
                      type="button"
                      class="leaderboard-th-info"
                      id="volumeInfoButton"
                      aria-expanded="false"
                      aria-controls="volumeInfoTooltip"
                      aria-label="How total volume is calculated"
                    >i</button>
                    <div class="leaderboard-th-tooltip" id="volumeInfoTooltip" role="tooltip">
                      Sum of reps multiplied by weight across valid sets.
                    </div>
                  </div>
                </th>
                <th scope="col">Streak</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topRows as $row): ?>
                <tr class="<?php echo !empty($row["is_current_user"]) ? "is-current-user" : ""; ?>">
                  <td><span class="leaderboard-rank-badge"><?php echo (int) $row["rank"]; ?></span></td>
                  <td>
                    <div class="leaderboard-athlete">
                      <strong><?php echo h((string) $row["display_name"]); ?></strong>
                      <span>@<?php echo h((string) $row["username"]); ?></span>
                    </div>
                  </td>
                  <td class="leaderboard-score-cell"><?php echo (int) $row["score"]; ?></td>
                  <td><?php echo (int) $row["workouts_count"]; ?></td>
                  <td><?php echo (int) $row["valid_sets"]; ?></td>
                  <td><?php echo (int) $row["total_duration_minutes"]; ?></td>
                  <td><?php echo number_format((int) $row["total_volume"]); ?> kg</td>
                  <td><?php echo (int) $row["streak"]; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="../global-auth.js"></script>
  <script>
(function attachLeaderboardTooltip() {
      const button = document.getElementById("leaderboardInfoButton");
      const tooltip = document.getElementById("leaderboardInfoTooltip");
      if (!button || !tooltip) return;

      function setOpenState(isOpen) {
        button.setAttribute("aria-expanded", isOpen ? "true" : "false");
        tooltip.classList.toggle("is-open", isOpen);
      }

      button.addEventListener("click", (event) => {
        event.stopPropagation();
        setOpenState(button.getAttribute("aria-expanded") !== "true");
      });

      button.addEventListener("blur", () => {
        window.setTimeout(() => {
          const active = document.activeElement;
          if (active !== button && !tooltip.contains(active)) {
            setOpenState(false);
          }
        }, 0);
      });

      document.addEventListener("click", (event) => {
        if (!tooltip.contains(event.target) && event.target !== button) {
          setOpenState(false);
        }
      });

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          setOpenState(false);
        }
      });
    })();

    (function attachFriendsFilterPopover() {
      const button = document.getElementById("friendsFilterButton");
      const popover = document.getElementById("friendsFilterPopover");
      if (!button || !popover) return;

      function setOpenState(isOpen) {
        button.setAttribute("aria-expanded", isOpen ? "true" : "false");
        popover.classList.toggle("is-open", isOpen);
      }

      button.addEventListener("click", (event) => {
        event.stopPropagation();
        setOpenState(button.getAttribute("aria-expanded") !== "true");
      });

      document.addEventListener("click", (event) => {
        if (!popover.contains(event.target) && event.target !== button) {
          setOpenState(false);
        }
      });

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          setOpenState(false);
        }
      });
    })();

    (function attachVolumeTooltip() {
      const button = document.getElementById("volumeInfoButton");
      const tooltip = document.getElementById("volumeInfoTooltip");
      if (!button || !tooltip) return;

      function setOpenState(isOpen) {
        button.setAttribute("aria-expanded", isOpen ? "true" : "false");
        tooltip.classList.toggle("is-open", isOpen);
      }

      button.addEventListener("click", (event) => {
        event.stopPropagation();
        setOpenState(button.getAttribute("aria-expanded") !== "true");
      });

      document.addEventListener("click", (event) => {
        if (!tooltip.contains(event.target) && event.target !== button) {
          setOpenState(false);
        }
      });

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          setOpenState(false);
        }
      });
    })();
  </script>
</body>
</html>
