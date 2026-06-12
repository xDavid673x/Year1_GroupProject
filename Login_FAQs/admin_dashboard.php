<?php
declare(strict_types=1);

require __DIR__ . '/api/bootstrap.php';
require __DIR__ . '/api/mysql.php';

// bootstrap.php sets Content-Type to application/json by default.
// Since this is a UI page, we must override it back to HTML.
header("Content-Type: text/html; charset=utf-8");

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit;
}

$pdo = mysql_pdo();

// Fetch Gemini Usage
$geminiUsage = 0;
try {
    $stmt = $pdo->query("SELECT SUM(requests_count) FROM GeminiUsage WHERE usage_date = CURDATE()");
    $geminiUsage = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    // Table might not exist yet if setup script wasn't run
}
$geminiLimit = 1500;

// Fetch FAQs
$faqs = [];
try {
    $stmt = $pdo->query("SELECT faq_id, question, answer FROM FAQs ORDER BY created_at ASC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../global.css" />
  <link rel="stylesheet" href="styles.css" />
  <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
  <style>
    .admin-container { max-width: 900px; margin: 40px auto; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    #friendship-network { width: 100%; height: 500px; border: 1px solid #d9e3f7; border-radius: 8px; background: #fafcff; margin-bottom: 20px; }
    .usage-widget { background: #f8fbff; border: 1px solid #d9e3f7; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
    .usage-bar { height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-top: 10px; }
    .usage-fill { height: 100%; background: var(--accent); width: <?php echo min(100, ($geminiUsage / $geminiLimit) * 100); ?>%; transition: width 0.5s ease; }
    .faq-form { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; }
    .faq-form input, .faq-form textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; }
    .faq-list table { width: 100%; border-collapse: collapse; }
    .faq-list th, .faq-list td { padding: 10px; border: 1px solid #eee; text-align: left; vertical-align: top; }
    .btn { padding: 8px 16px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .btn:hover { background: var(--accent-hover); }
    .btn-danger { background: #dc3545; }
    .btn-danger:hover { background: #c82333; }
    @media (max-width: 768px) {
      .admin-container { margin: 90px 16px 40px 16px !important; padding: 16px; }
      #friendship-network { height: 350px; }
      h1 { font-size: 1.8rem; }
      h2 { font-size: 1.4rem; }
      .faq-list { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 6px; border: 1px solid #eee; }
      .faq-list table { min-width: 600px; border: none; }
    }
  </style>
</head>
<body class="dashboard-page has-floating-header">
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
              <a href="../Login_FAQs/leaderboard.php" class="nav-item">Leaderboard</a>
          </nav>
          <div class="nav-cta">
              <a href="../Login_FAQs/login.html" class="login-btn">Log In / Sign Up</a>
          </div>
      </div>
  </header>

  <div class="admin-container" style="margin-top: 100px;">
    <h1 style="margin-top: 0;">Admin Dashboard</h1>
    
    <div class="usage-widget">
      <h2 style="margin-top: 0;">Gemini AI Usage (Today)</h2>
      <p><strong><?php echo $geminiUsage; ?></strong> out of <?php echo $geminiLimit; ?> requests used.</p>
      <div class="usage-bar">
        <div class="usage-fill"></div>
      </div>
    </div>

    <h2>User Friendship Map</h2>
    <div id="friendship-network"></div>
    <p style="text-align: center; color: var(--muted); font-size: 0.9rem; margin-top: -10px; margin-bottom: 30px;">Drag nodes to explore the network.</p>

    <h2>Manage FAQs</h2>
    <form class="faq-form" id="add-faq-form">
      <input type="text" id="question" placeholder="Question" required>
      <textarea id="answer" placeholder="Answer" rows="3" required></textarea>
      <button type="submit" class="btn">Add FAQ</button>
    </form>

    <div class="faq-list">
      <table>
        <thead>
          <tr>
            <th>Question</th>
            <th>Answer</th>
            <th style="width: 100px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($faqs)): ?>
            <tr><td colspan="3" style="text-align: center; color: #888;">No FAQs found.</td></tr>
          <?php else: ?>
            <?php foreach ($faqs as $faq): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($faq['question']); ?></strong></td>
              <td><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></td>
              <td>
                <button class="btn btn-danger delete-faq" data-id="<?php echo $faq['faq_id']; ?>">Delete</button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    document.getElementById('add-faq-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const question = document.getElementById('question').value;
      const answer = document.getElementById('answer').value;
      const btn = e.target.querySelector('button');
      btn.disabled = true;
      
      const res = await fetch('api/admin_faqs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', question, answer })
      });
      const data = await res.json();
      if (data.ok) location.reload();
      else {
          alert(data.error || 'Failed to add FAQ');
          btn.disabled = false;
      }
    });

    document.querySelectorAll('.delete-faq').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        if (!confirm('Delete this FAQ?')) return;
        const id = e.target.getAttribute('data-id');
        
        const res = await fetch('api/admin_faqs.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id })
        });
        const data = await res.json();
        if (data.ok) location.reload();
        else alert(data.error || 'Failed to delete FAQ');
      });
    });

    // Initialize Friendship Map
    async function loadFriendshipMap() {
      try {
        const res = await fetch('api/admin_friendships.php');
        const data = await res.json();
        
        if (data.ok) {
          const container = document.getElementById('friendship-network');
          const nodes = new vis.DataSet(data.nodes);
          const edges = new vis.DataSet(data.edges);
          
          const graphData = { nodes: nodes, edges: edges };
          const options = {
            nodes: {
              shape: 'dot',
              size: 16,
              font: { size: 14, color: '#333' },
              borderWidth: 2,
              color: {
                background: '#eef3ff',
                border: '#2d6cdf',
                highlight: { background: '#fff', border: '#ff6b6b' }
              }
            },
            edges: {
              width: 1,
              color: { color: '#ccc', highlight: '#ff6b6b' },
              smooth: { type: 'continuous' }
            },
            physics: {
              barnesHut: { gravitationalConstant: -2000, centralGravity: 0.3, springLength: 95 },
              stabilization: { iterations: 200 }
            }
          };
          
          new vis.Network(container, graphData, options);
        } else {
          console.error('Failed to load friendship map:', data.error);
        }
      } catch (err) {
        console.error('Error fetching friendship map:', err);
      }
    }
    
    loadFriendshipMap();
  </script>
  <script src="../global-auth.js"></script>
</body>
</html>
