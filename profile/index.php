<?php
session_start();

$lang = $_SESSION['lang'] ?? 'cs';

if (!isset($_SESSION['user_id'])) {
    if ($lang === 'en') {
        header('Location: /auth/login-en.html');
    } else {
        header('Location: /auth/login.html');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Profil | Ordo Draconis</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div style="padding: 40px; max-width: 800px; margin: auto;">
    <h1>Profil hr·Ëe</h1>

    <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['email']) ?></p>

    <form method="post" action="/api/logout.php">
        <button type="submit">Odhl·sit se</button>
    </form>
</div>

</body>
</html>
