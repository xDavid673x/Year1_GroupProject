<?php
    $isProduction = getenv("APP_ENV") === "production" || getenv("VERCEL") === "1";
    ini_set('display_errors', $isProduction ? '0' : '1');
    ini_set('display_startup_errors', $isProduction ? '0' : '1');
    error_reporting(E_ALL);

    $isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);

    session_start();

    $isAuthenticated = !empty($_SESSION['user_id']);
    $hasProfileData = false;
    $profile = null;

    $displayname = '';
    $firstname = '';
    $lastname  = '';
    $username  = '';
    $email     = '';
    $phoneno   = '';
    $height    = '';
    $weight    = '';
    $age       = '';
    $gym       = '';
    $bmi       = '';
    $bio       = '';

    if ($isAuthenticated) {
        include __DIR__ . "/../DatabaseInit.php";

        $userid = (int) $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT
                u.username,
                u.email,
                u.PhoneNum AS phoneno,
                p.displayname,
                p.height,
                p.weight,
                p.age,
                p.gym,
                p.BMI,
                p.bio,
                p.profilepicURL
            FROM Users u
            LEFT JOIN Profiles p ON p.userid = u.userid
            WHERE u.userid = ?
        ");

        $stmt->execute([$userid]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profile) {
            $hasProfileData = true;
            $displayname = $profile['displayname'] ?? '';
            $parts = explode(" ", $displayname, 2);

            $firstname = $parts[0] ?? '';
            $lastname  = $parts[1] ?? '';

            $username = (string) ($profile['username'] ?? '');
            $email    = (string) ($profile['email'] ?? '');
            $phoneno  = (string) ($profile['phoneno'] ?? '');
            $height   = (string) ($profile['height'] ?? '');
            $weight   = (string) ($profile['weight'] ?? '');
            $age      = (string) ($profile['age'] ?? '');
            $gym      = (string) ($profile['gym'] ?? '');
            $bmi      = (string) ($profile['BMI'] ?? '');
            $bio      = (string) ($profile['bio'] ?? '');
        }
    }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link rel="stylesheet" href="../global.css" />
    <link rel="stylesheet" href="profile.css?v=6" />

</head>
<body>
    <?php if ($hasProfileData): ?>
    <script src="profile.js" defer></script>
    <?php endif; ?>
    <header class="floating-header">
        <div class="nav-shell">
            <a href="../homepage/homepage.html" class="brand">
                <img src="../img/edited-photo-Photoroom.png" alt="Motiv8 logo" class="brand-logo">
            </a>
            <div class="menu-toggle" id="menuToggle" role="button" tabindex="0" aria-controls="mainNav" aria-expanded="false">&#9776;</div>
            <nav class="floating-nav" id="mainNav" aria-label="Main navigation">
                <a href="../homepage/homepage.html" class="nav-item">Home</a>
                <a href="../Videos/videos.html" class="nav-item">Videos</a>
                <a href="../Gym_Locator/gym_locator.html" class="nav-item">Gym Locator</a>
                <a href="profile.php" class="nav-item active">Profile</a>
                <a href="../Login_FAQs/faq.html" class="nav-item">FAQ</a>
                <a href="../Login_FAQs/gemini.html" class="nav-item">Gemini</a>
                <a href="../Login_FAQs/leaderboard.php" class="nav-item">Leaderboard</a>
            </nav>
            <div class="nav-cta">
                <?php if ($isAuthenticated): ?>
                <a href="../Login_FAQs/api/logout.php" class="login-btn" id="logout-btn">Log Out</a>
                <?php else: ?>
                <a href="../Login_FAQs/login.html" class="login-btn">Log In / Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="page-hero-decor" aria-hidden="true">
        <div class="hero-overlay"></div>
        <div class="hero-orb hero-orb-1"></div>
        <div class="hero-orb hero-orb-2"></div>
        <div class="hero-orb hero-orb-3"></div>
    </div>

    <div class="page-content<?php echo !$isAuthenticated ? ' page-content-gated' : ''; ?>">

    <?php if (!$isAuthenticated): ?>
    <section class="inline-auth-gate" aria-labelledby="profile-auth-title">
        <div class="inline-auth-gate-accent" aria-hidden="true"></div>
        <p class="inline-auth-gate-kicker">Sign In Required</p>
        <h2 id="profile-auth-title">Unlock your <span class="inline-auth-gate-highlight">personal profile</span> dashboard</h2>
        <p class="inline-auth-gate-text">
            Log in to access your <span class="inline-auth-gate-emphasis">personal details</span>,
            review your <span class="inline-auth-gate-emphasis">fitness stats</span>,
            and keep track of your <span class="inline-auth-gate-emphasis">saved progress</span>.
        </p>
        <div class="inline-auth-gate-tags" aria-label="Profile features">
            <span class="inline-auth-gate-tag">Personal details</span>
            <span class="inline-auth-gate-tag">Fitness stats</span>
            <span class="inline-auth-gate-tag">Saved progress</span>
        </div>
        <div class="inline-auth-gate-actions">
            <a href="../Login_FAQs/login.html" class="inline-auth-gate-btn">Go to Login</a>
            <p class="inline-auth-gate-note">New here? Create an account from the same login page.</p>
        </div>
    </section>
    <?php elseif (!$hasProfileData): ?>
    <section class="inline-auth-gate" aria-labelledby="profile-error-title">
        <div class="inline-auth-gate-accent" aria-hidden="true"></div>
        <p class="inline-auth-gate-kicker">Profile unavailable</p>
        <h2 id="profile-error-title">We couldn't load your <span class="inline-auth-gate-highlight">profile</span></h2>
        <p class="inline-auth-gate-text">
            Try refreshing the page. If the problem continues, check that your profile data exists in the database.
        </p>
    </section>
    <?php else: ?>

    <div class="profile-details">
        <div class="pfp">
            <img id="pfp" src="<?php echo htmlspecialchars($profile['profilepicURL'] ?? 'img/profile-placeholder.jpg'); ?>" alt="Profile Picture" class="profile-image">         
            <label for="input-file">upload image</label>
            <input type="file" accept="image/jpeg, image/png, image/jpg" id="input-file">
        </div>
        <div class="main-info">
            <p id="displayname"><?php echo htmlspecialchars($displayname); ?></p>
            <p id="email"><?php echo htmlspecialchars($email); ?></p>
            <p id="phoneno"><?php echo htmlspecialchars($phoneno); ?></p>
        </div>
    </div>

    <p class="bio">Bio:</p>
<textarea name="bio" id="bio-textarea" rows="5" cols="50"><?php echo htmlspecialchars($bio); ?></textarea>
    <div class="button-row">
        <button class="tablinks" onclick="openTab(event, 'user')" id="default-open">User</button>
        <button class="tablinks" onclick="openTab(event, 'info')">Fitness Info</button>
    </div>

    <div id="user" class="tab-content">
        <form action="update-user.php" method="post">

            <div class="field">
                <p>First name</p>
                <input type="text" name = "firstname" value = "<?php echo htmlspecialchars($firstname); ?>">
            </div>

            <div class="field">
                <p>Last name</p>
                <input type="text" name = "lastname"  value = "<?php echo htmlspecialchars($lastname ?? ''); ?>">
            </div>

            <div class="field">
                <p>Username</p>
                <input type="text" value = "<?php echo htmlspecialchars($username); ?>" disabled>
            </div>

            <div class="field">
                <p>Email</p>
                <input type="text" value = "<?php echo htmlspecialchars($email); ?>" disabled>
            </div>

            <div class="field">
                <p>Phone</p>
                <input type="text" value = "<?php echo htmlspecialchars($phoneno); ?>" disabled>
            </div>

            <div class="field">
                <p>Password</p>
                <input type="text" name = "password" value = "*********">
            </div>

            <div class="cancelorsave">
                <button class="save" type="submit">Save</button>
            </div>
            
        </form>
    </div>

    <div id="info" class="tab-content">
        <form action="update-fitness.php" method="post">
            <div class="field unit-field">
                <p>Height</p>
                <input id = "height" type="number" name = "height" value = "<?php echo htmlspecialchars($height); ?>">
                <span class="unit">cm</span>
            </div>

            <div class="field unit-field">
                <p>Weight</p>
                <input id="weight" type="number" name = "weight" value = "<?php echo htmlspecialchars($weight); ?>">
                <span class="unit">kg</span>
            </div>

            <div class="field">
                <p>Age</p>
                <input type="number" name = "age" value = "<?php echo htmlspecialchars($age); ?>">
            </div>

            <div class="field">
                <p>Gym</p>
                <input type="text" name = "gym" value = "<?php echo htmlspecialchars($gym); ?>">
            </div>

            <div class="field">
                <p>BMI</p>
                <input id = "bmi_result"type="text" name = "bmi"  value = "<?php echo htmlspecialchars($bmi); ?>" readonly>
            </div>

            <div class="cancelorsave">
                <button class="save" type="submit">Save</button>
            </div>

        </form>
    </div>

    <button type="button" class="delete" id="delete-account-btn">Delete Account</button>

    </div>

    <div id="delete-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="modal-icon-wrap">
                <svg class="modal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"></path>
                    <path d="M10 11v6M14 11v6"></path>
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"></path>
                </svg>
            </div>
            <h2 id="modal-title" class="modal-title">Delete Account</h2>
            <p class="modal-body">This will permanently erase your profile, fitness data, and all account information. <strong>This cannot be undone.</strong></p>
            <div class="modal-actions">
                <button type="button" class="modal-btn-cancel" id="modal-cancel">Keep My Account</button>
                <form action="delete-account.php" method="post" style="margin:0">
                    <button type="submit" class="modal-btn-delete">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <h2 class="footer-tagline">STAY MOTIVATED</h2>
        <div class="footer-copy">
            <p>© 2026 Motiv8 Fitness Tracking. All rights reserved.</p>
        </div>
    </footer>
    <?php endif; ?>

    <script>
const logoutButton = document.getElementById('logout-btn');
        if (logoutButton) {
            logoutButton.addEventListener('click', async function(e) {
                e.preventDefault();
                try {
                    await fetch('../Login_FAQs/api/logout.php', { method: 'POST', credentials: 'include' });
                } catch {}
                sessionStorage.setItem('nav_auth_state_v1', 'out');
                window.location.href = '../Login_FAQs/login.html';
            });
        }

        const modal = document.getElementById('delete-modal');
        const deleteButton = document.getElementById('delete-account-btn');
        const cancelButton = document.getElementById('modal-cancel');
        if (modal && deleteButton && cancelButton) {
            deleteButton.addEventListener('click', () => {
                modal.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');
            });
            cancelButton.addEventListener('click', () => {
                modal.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        }
    </script>
    <script src="../global-auth.js"></script>
</body>
</html>
