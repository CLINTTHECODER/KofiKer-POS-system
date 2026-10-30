
<?php
$navLinks = require __DIR__ . '/nav-links.php';

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$currentPath = rtrim($currentPath, '/') ?: '/';
?>

<header class="app-header">
    <a class="app-brand" href="/">Kofi Ker POS</a>

    <nav class="app-nav" aria-label="Main navigation">
        <?php foreach ($navLinks as $link): ?>
            <?php
            $path = rtrim($link['path'], '/') ?: '/';
            $active = $currentPath === $path;
            ?>

            <a
                href="<?= htmlspecialchars($link['path'], ENT_QUOTES, 'UTF-8') ?>"
                class="nav-link<?= $active ? ' active' : '' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>
            >
                <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>

<style>
.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 18px 6%;
    background: #442c24;
    color: #fff;
}

.app-brand {
    color: #fff;
    font-size: 22px;
    font-weight: bold;
    text-decoration: none;
    white-space: nowrap;
}

.app-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.nav-link {
    padding: 10px 13px;
    border-radius: 7px;
    color: #fff;
    text-decoration: none;
    transition: background .2s ease;
}

.nav-link:hover {
    background: #624438;
}

.nav-link.active {
    background: #80543c;
    font-weight: bold;
}

@media (max-width: 650px) {
    .app-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .app-nav {
        width: 100%;
    }
}
</style>
