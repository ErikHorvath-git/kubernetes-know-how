<?php
// VARIANT D — Markdown CMS: .md súbory v posts/ → HTML
// FS interakcia: čítanie (scandir, file_get_contents), zápis (upload .md)
//
// Bez závislostí — má vstavaný mini-markdown parser
// (pokrýva nadpisy, tučné, kurzíva, kód, zoznamy, odkazy).

$siteTitle = getenv('SITE_TITLE') ?: 'Markdown CMS';
$postsDir  = '/var/www/html/data/posts';

if (!is_dir($postsDir)) {
    mkdir($postsDir, 0775, true);
    // Vlož demo článok pri prvom štarte
    file_put_contents($postsDir . '/uvod.md',
        "# Vitajte v CMS\n\n" .
        "Toto je **prvý** článok. Pridaj nový cez formulár hore.\n\n" .
        "- bod jeden\n- bod dva\n\n" .
        "Viac na [GitHube](https://github.com).");
}

// Spracovanie nového .md súboru
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title'])) {
    $slug = preg_replace('/[^a-z0-9-]+/', '-',
                         strtolower(trim($_POST['title'])));
    $slug = trim($slug, '-') ?: 'bez-nazvu';
    $body = "# " . $_POST['title'] . "\n\n" . ($_POST['body'] ?? '');
    file_put_contents("$postsDir/$slug.md", $body);
    $msg = "Uložené: $slug.md";
}

// Mini Markdown → HTML (jednoduchý, bez závislostí)
function md(string $s): string {
    $s = htmlspecialchars($s);
    $s = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $s);
    $s = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $s);
    $s = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $s);
    $s = preg_replace('/\*(.+?)\*/',     '<i>$1</i>', $s);
    $s = preg_replace('/`(.+?)`/',       '<code>$1</code>', $s);
    $s = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $s);
    $s = preg_replace('/^- (.+)$/m', '<li>$1</li>', $s);
    $s = preg_replace('/(<li>.+<\/li>\n?)+/s', '<ul>$0</ul>', $s);
    $s = preg_replace('/\n\n+/', "</p><p>", $s);
    return "<p>$s</p>";
}

$selected = $_GET['post'] ?? null;
$posts = array_filter(scandir($postsDir) ?: [],
    fn($f) => str_ends_with($f, '.md'));
?>
<!doctype html>
<html lang="sk"><head><meta charset="utf-8">
<title><?= htmlspecialchars($siteTitle) ?></title>
<style>body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;
display:grid;grid-template-columns:200px 1fr;gap:2rem}
nav{border-right:1px solid #ddd;padding-right:1rem}
nav a{display:block;padding:.3rem 0}
.box{background:#f4f4f4;padding:1rem;border-radius:6px;margin:1rem 0}
input,textarea{width:100%;padding:.4rem;margin:.2rem 0}</style></head>
<body>

<nav>
  <h2><?= htmlspecialchars($siteTitle) ?></h2>
  <p><small>Pod: <?= gethostname() ?></small></p>
  <h3>Články</h3>
  <?php foreach ($posts as $p): ?>
    <a href="?post=<?= urlencode($p) ?>">
      <?= htmlspecialchars(str_replace('.md', '', $p)) ?>
    </a>
  <?php endforeach; ?>
</nav>

<main>
  <?php if ($msg): ?><p><b><?= $msg ?></b></p><?php endif; ?>

  <?php if ($selected && in_array($selected, $posts, true)): ?>
    <article><?= md(file_get_contents("$postsDir/$selected")) ?></article>
  <?php else: ?>
    <p>Vyber článok vľavo alebo pridaj nový.</p>
  <?php endif; ?>

  <div class="box">
    <h3>Nový článok</h3>
    <form method="post">
      <input name="title" placeholder="Názov" required>
      <textarea name="body" rows="6" placeholder="Markdown obsah..."></textarea>
      <button type="submit">Uložiť</button>
    </form>
  </div>
</main>
</body></html>
