<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Admin panel | Ordo Draconis</title>
  <link rel="stylesheet" href="/css/admin.css">
</head>

<body class="admin-page">

<div id="adminHeader"></div>

<main class="auth-container">
<div class="admin-stats-bar">

  <div class="stat-box">
    <span>Web účty</span>
    <b id="statWebUsers">...</b>
  </div>

  <div class="stat-box">
    <span>Game účty</span>
    <b id="statGameAccounts">...</b>
  </div>

  <div class="stat-box">
    <span>Postavy</span>
    <b id="statCharacters">...</b>
  </div>

  <div class="stat-box">
    <span>VIP 24h</span>
    <b id="statVip24">...</b>
  </div>

  <div class="stat-box">
    <span>Ostatní VIP</span>
    <b id="statVipOther">...</b>
  </div>
  <section class="admin-card">
    <h2>Admin Dashboard</h2>
  </section>

</div>
  <div class="admin-grid">
  
  </div>
    <div class="admin-card">
      <h3>Bug reports</h3>
      <p>Přehled hlášených chyb od hráčů.</p>
      <a href="/admin/bug_reports.html" class="btn btn-primary">Otevřít</a>
    </div>

    <div class="admin-card">
      <h3>VIP & Wallet</h3>
      <p>Správa VIP, DC a uživatelských výhod</p>
      <a href="/admin/vip.html" class="btn btn-primary">Otevřít</a>
    </div>

    <div class="admin-card">
      <h3>Online players</h3>
      <p>Aktuálně přihlášení hráči</p>
      <a href="/admin/online_players.html" class="btn btn-primary">Otevřít</a>
    </div>

    <div class="admin-card">
      <h3>Ekonomika</h3>
      <p>Úprava měny a ledger</p>
      <a href="/admin/economy.html" class="btn btn-primary">Otevřít</a>
    </div>

    <div class="admin-card">
      <h3>Uživatelé</h3>
      <p>Přehled WEB → GAME → CHAR</p>
      <a href="/admin/users.html" class="btn btn-primary">Otevřít</a>
    </div>
 <div class="admin-card">
      <h3>Přehled všech akcí</h3>
      <p>Logy, výpisy</p>
      <a href="/admin/logs.html" class="btn btn-primary">Otevřít</a>
    </div>
  </div>

</main>

<!-- Načtení headeru + aktivní záložka -->
<script>
fetch('/admin/partials/admin_header.html')
  .then(r => r.text())
  .then(html => {
    document.getElementById('adminHeader').innerHTML = html;

    // 🔥 zvýraznění aktivní stránky
    const links = document.querySelectorAll('.admin-nav a');
    const currentPath = window.location.pathname;

    links.forEach(link => {
      if (currentPath.includes(link.getAttribute('href'))) {
        link.classList.add('active');
      }
    });
  });
</script>

<!-- Kontrola admin oprávnění -->
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const res = await fetch('/api/me.php');
  const me = await res.json();

  if (!me.ok || me.role !== 'admin') {
    window.location.replace('/profile/index.html');
  }
});
</script>
<script src="/admin/js/index_admin.js"></script>
</body>
</html>
