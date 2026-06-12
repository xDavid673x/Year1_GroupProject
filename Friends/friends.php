<?php
include __DIR__ . "/../DatabaseInit.php";
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$search = trim((string) ($_GET['search'] ?? ''));
$searchResults = [];

if ($search !== '') {
    $searchStmt = $pdo->prepare(
        "SELECT u.userid, u.username
         FROM Users u
         LEFT JOIN Friends f
           ON (
                (f.userA = u.userid AND f.userB = :currentUserId)
                OR
                (f.userA = :currentUserId AND f.userB = u.userid)
              )
         WHERE u.username LIKE :search
           AND u.userid != :currentUserId
           AND f.userA IS NULL
         ORDER BY u.username ASC"
    );
    $searchStmt->execute([
        ':search' => "%$search%",
        ':currentUserId' => $currentUserId,
    ]);
    $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
}

$friendsStmt = $pdo->prepare(
    "SELECT u.userid, u.username, f.friendstatus
     FROM Users u
     JOIN Friends f
       ON (
            (f.userA = u.userid AND f.userB = :currentUserId)
            OR
            (f.userB = u.userid AND f.userA = :currentUserId)
          )
     WHERE u.userid != :currentUserId
     ORDER BY
       CASE
         WHEN f.friendstatus = 'pending' THEN 0
         WHEN f.friendstatus = 'friends' THEN 1
         ELSE 2
       END,
       u.username ASC"
);
$friendsStmt->execute([':currentUserId' => $currentUserId]);
$userFriends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

$incomingStmt = $pdo->prepare(
    "SELECT u.userid, u.username
     FROM Friends f
     JOIN Users u ON u.userid = f.userA
     WHERE f.userB = :currentUserId
       AND f.friendstatus = 'pending'
     ORDER BY u.username ASC"
);
$incomingStmt->execute([':currentUserId' => $currentUserId]);
$incomingRequests = $incomingStmt->fetchAll(PDO::FETCH_ASSOC);

$friendCount = 0;
$pendingCount = 0;
foreach ($userFriends as $friend) {
    if (($friend['friendstatus'] ?? '') === 'friends') {
        $friendCount++;
    }
    if (($friend['friendstatus'] ?? '') === 'pending') {
        $pendingCount++;
    }
}

$searchCount = count($searchResults);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motiv8 | Friends</title>
    <link rel="stylesheet" href="../global.css">
    <link rel="stylesheet" href="friends.css?v=2">
</head>
<body>
    <div class="friends-hero-decor" aria-hidden="true">
        <div class="hero-overlay"></div>
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
    </div>

    <main class="friends-page">
        <div class="friends-topbar">
            <a class="friends-back-link" href="../homepage/homepage.html">Back to Home</a>
            <div class="friends-top-actions">
                <a class="friends-back-link" href="../Exercise_Tracker/viewWorkouts.php">Workout History</a>
            </div>
        </div>

        <section class="friends-hero-card">
            <div class="friends-hero-copy">
                <p class="friends-kicker">Community</p>
                <h1>Find new training partners and manage your network</h1>
                <p class="friends-subtitle">Search for users, keep track of your current connections, and handle incoming requests without leaving the page.</p>
            </div>
            <div class="friends-stats-grid">
                <article class="friends-stat-card">
                    <span class="friends-stat-label">Current friends</span>
                    <strong><?php echo $friendCount; ?></strong>
                </article>
                <article class="friends-stat-card">
                    <span class="friends-stat-label">Pending</span>
                    <strong><?php echo $pendingCount; ?></strong>
                </article>
                <article class="friends-stat-card">
                    <span class="friends-stat-label">Incoming</span>
                    <strong><?php echo count($incomingRequests); ?></strong>
                </article>
                <article class="friends-stat-card">
                    <span class="friends-stat-label">Search results</span>
                    <strong><?php echo $search !== '' ? $searchCount : 0; ?></strong>
                </article>
            </div>
        </section>

        <section class="friends-search-card">
            <div class="friends-section-head">
                <p class="friends-section-kicker">Search users</p>
                <h2>Find people by username</h2>
                <p>Search for a user and send a friend request directly from the result card.</p>
            </div>

            <form method="GET" class="friends-search-form">
                <div class="friends-field">
                    <label for="friendSearch">Username</label>
                    <input
                        id="friendSearch"
                        type="text"
                        name="search"
                        placeholder="Search username..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        required
                    >
                </div>
                <button type="submit" class="friends-primary-btn">Search</button>
            </form>

            <div class="friends-results">
                <?php if ($search === ''): ?>
                <article class="friend-card no-friends">
                    <span class="friend-name">Start with a username search to find new friends.</span>
                </article>
                <?php elseif (!$searchResults): ?>
                <article class="friend-card no-friends">
                    <span class="friend-name">No matching users found, or you are already connected.</span>
                </article>
                <?php else: ?>
                    <?php foreach ($searchResults as $user): ?>
                    <article class="friend-card">
                        <div class="friend-copy">
                            <span class="friend-name"><?php echo htmlspecialchars((string) $user['username']); ?></span>
                            <span class="friend-status">Available to add</span>
                        </div>
                        <form method="POST" action="processFriends.php">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars((string) $user['username']); ?>">
                            <button type="submit" class="friends-primary-btn compact">Add Friend</button>
                        </form>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <div class="friends-grid">
            <section class="friends-panel-card">
                <div class="friends-section-head">
                    <p class="friends-section-kicker">Connections</p>
                    <h2>Current friends</h2>
                    <p>See active friendships and any outgoing requests that are still pending.</p>
                </div>

                <div class="friends-results">
                    <?php if (!$userFriends): ?>
                    <article class="friend-card no-friends">
                        <span class="friend-name">You have not friended anyone yet.</span>
                    </article>
                    <?php else: ?>
                        <?php foreach ($userFriends as $friend): ?>
                        <article class="friend-card">
                            <div class="friend-copy">
                                <span class="friend-name"><?php echo htmlspecialchars((string) $friend['username']); ?></span>
                                <span class="friend-status"><?php echo $friend['friendstatus'] === 'friends' ? 'Friends' : htmlspecialchars((string) $friend['friendstatus']); ?></span>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="friends-panel-card">
                <div class="friends-section-head">
                    <p class="friends-section-kicker">Requests</p>
                    <h2>Incoming friend requests</h2>
                    <p>Accept requests to add people to your network, or reject them to keep your list clean.</p>
                </div>

                <div class="friends-results">
                    <?php if (!$incomingRequests): ?>
                    <article class="friend-card no-friends">
                        <span class="friend-name">No incoming requests.</span>
                    </article>
                    <?php else: ?>
                        <?php foreach ($incomingRequests as $request): ?>
                        <article class="friend-card incoming-request">
                            <div class="friend-copy">
                                <span class="friend-name"><?php echo htmlspecialchars((string) $request['username']); ?></span>
                                <span class="friend-status">Waiting for your response</span>
                            </div>
                            <div class="request-buttons">
                                <form method="POST" action="processFriends.php">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="userid" value="<?php echo (int) $request['userid']; ?>">
                                    <button type="submit" class="accept">Accept</button>
                                </form>
                                <form method="POST" action="processFriends.php">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="userid" value="<?php echo (int) $request['userid']; ?>">
                                    <button type="submit" class="reject">Reject</button>
                                </form>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
