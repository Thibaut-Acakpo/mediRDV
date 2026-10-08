<?php
declare(strict_types=1);

/** Logo : le tracé de pouls de la carte projet. */
function logo_svg(int $size = 28): string
{
    return '<svg class="logo-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" aria-hidden="true">' .
        '<path d="M3 25h9l6-17 10 32 6-15h11" stroke="currentColor" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function page_header(string $title, array $o = []): void
{
    $user = current_user();
    $name = config('app.name');
    $body = $o['body'] ?? '';
    $active = $o['nav'] ?? '';
    ?>
<!doctype html>
<html lang="fr" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e($name) ?></title>
  <meta name="description" content="Réservez un rendez-vous médical en ligne : choisissez votre praticien, un créneau libre, et recevez un rappel par email.">
  <meta name="theme-color" content="#0d0f1f">
  <script>document.documentElement.classList.add('js')</script>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='12' fill='%23667eea'/%3E%3Cpath d='M5 25h8l5-14 9 26 5-12h11' stroke='white' stroke-width='4' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="<?= e(asset('vendor/sora/sora.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="<?= e($body) ?>" data-csrf="<?= e(csrf_token()) ?>" data-base="<?= e(base_path()) ?>">
<canvas id="ecg-bg" aria-hidden="true"></canvas>
<div class="cursor-glow" aria-hidden="true"></div>
<a class="skip-link" href="#main">Aller au contenu</a>

<nav class="navbar navbar-expand-lg app-nav sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= e(url('index.php')) ?>"><?= logo_svg() ?><span><?= e($name) ?></span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Menu">
      <i class="bi bi-list fs-2"></i>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto ms-lg-3">
        <li class="nav-item"><a class="nav-link <?= $active === 'search' ? 'active' : '' ?>" href="<?= e(url('practitioners.php')) ?>">Trouver un praticien</a></li>
        <?php if ($user && $user['role'] === 'patient'): ?>
          <li class="nav-item"><a class="nav-link <?= $active === 'dash' ? 'active' : '' ?>" href="<?= e(url('patient/dashboard.php')) ?>">Mon espace</a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'appts' ? 'active' : '' ?>" href="<?= e(url('patient/appointments.php')) ?>">Mes rendez-vous</a></li>
        <?php elseif ($user): ?>
          <li class="nav-item"><a class="nav-link <?= $active === 'dash' ? 'active' : '' ?>" href="<?= e(url('praticien/dashboard.php')) ?>">Tableau de bord</a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'appts' ? 'active' : '' ?>" href="<?= e(url('praticien/appointments.php')) ?>">Agenda</a></li>
          <li class="nav-item"><a class="nav-link <?= $active === 'avail' ? 'active' : '' ?>" href="<?= e(url('praticien/availability.php')) ?>">Disponibilités</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 pt-3 pt-lg-0">
        <?php if ($user): ?>
          <div class="dropdown">
            <button class="user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="avatar avatar-sm"><?= e(initials($user['first_name'], $user['last_name'])) ?></span>
              <span class="d-none d-sm-inline"><?= e($user['first_name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e(url(dashboard_url($user))) ?>"><i class="bi bi-grid me-2"></i>Mon espace</a></li>
              <li><a class="dropdown-item" href="<?= e(url($user['role'] === 'practitioner' ? 'praticien/profile.php' : 'patient/profile.php')) ?>"><i class="bi bi-person me-2"></i>Mon profil</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?>
                  <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button>
                </form>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Se connecter</a>
          <a class="btn btn-brand" href="<?= e(url('register.php')) ?>">Créer un compte</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main id="main">
<?php $flashes = pull_flashes(); if ($flashes): ?>
  <div class="container pt-3 flash-stack">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif;
}

function page_footer(array $scripts = []): void
{
    ?>
</main>
<footer class="app-footer">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
    <span><?= logo_svg(18) ?> <?= e(config('app.name')) ?> · Prenez rendez-vous sans attendre au téléphone.</span>
    <span class="text-body-secondary small">© <?= date('Y') ?> <?= e(config('app.name')) ?></span>
  </div>
</footer>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/fx.js')) ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e(asset('js/' . $s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** Onglets des espaces connectés. */
function dash_title(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    echo '<div class="dash-head" data-reveal><div><h1>' . e($title) . '</h1>' .
        ($subtitle ? '<p class="text-body-secondary mb-0">' . e($subtitle) . '</p>' : '') .
        '</div><div class="d-flex gap-2 flex-wrap">' . $actionsHtml . '</div></div>';
}

function specialties_all(): array
{
    return fetch_all('SELECT id, name, icon FROM specialties ORDER BY name');
}

/** @return array<int,array> */
function search_practitioners(array $f, int $limit = 24): array
{
    $where = ['p.is_active = 1'];
    $params = [];
    if (!empty($f['specialty'])) {
        $where[] = 'p.specialty_id = ?';
        $params[] = (int)$f['specialty'];
    }
    $term = trim((string)($f['q'] ?? ''));
    if ($term !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR p.city LIKE ? OR s.name LIKE ? OR p.address LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    return fetch_all(
        'SELECT p.id, p.city, p.address, p.price_fcfa, p.slot_minutes, p.bio, u.first_name, u.last_name, s.name AS specialty, s.icon
           FROM practitioners p
           JOIN users u ON u.id = p.user_id
           JOIN specialties s ON s.id = p.specialty_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY u.last_name, u.first_name LIMIT ' . (int)$limit,
        $params
    );
}

function practitioner_cards_html(array $list): string
{
    if (!$list) {
        return '<div class="empty-state"><i class="bi bi-search"></i><h3>Aucun praticien trouvé</h3>' .
            '<p>Essayez une autre spécialité ou une autre ville.</p></div>';
    }
    $html = '';
    foreach ($list as $i => $p) {
        $next = next_free_slot((int)$p['id']);
        $nextHtml = $next
            ? '<span class="next-slot"><i class="bi bi-calendar-check"></i> Prochain créneau : <strong>' . e(fmt_date($next, true)) . ' à ' . e(fmt_time($next)) . '</strong></span>'
            : '<span class="next-slot none"><i class="bi bi-calendar-x"></i> Aucun créneau dans les 3 semaines</span>';
        $html .= '<div class="col-md-6 col-xl-4"><article class="pro-card" data-tilt style="--i:' . $i . '">' .
            '<div class="d-flex gap-3 align-items-center">' .
            '<span class="avatar avatar-lg">' . e(initials($p['first_name'], $p['last_name'])) . '</span>' .
            '<div class="min-w-0"><h3>Dr ' . e($p['first_name'] . ' ' . $p['last_name']) . '</h3>' .
            '<span class="chip"><i class="bi ' . e($p['icon']) . '"></i> ' . e($p['specialty']) . '</span></div></div>' .
            '<ul class="pro-meta">' .
            '<li><i class="bi bi-geo-alt"></i> ' . e($p['address'] . ', ' . $p['city']) . '</li>' .
            '<li><i class="bi bi-clock"></i> Consultation de ' . (int)$p['slot_minutes'] . ' min · ' . e(fmt_price($p['price_fcfa'] ? (int)$p['price_fcfa'] : null)) . '</li></ul>' .
            $nextHtml .
            '<a class="btn btn-brand w-100 mt-3" href="' . e(url('practitioner.php?id=' . (int)$p['id'])) . '">Prendre rendez-vous</a>' .
            '</article></div>';
    }
    return $html;
}

/** Ligne de rendez-vous réutilisée côté patient. */
function appt_card_patient(array $a, bool $actions = true): string
{
    $d = new DateTimeImmutable($a['start_at']);
    $active = in_array($a['status'], ['pending', 'confirmed'], true) && $d > now();
    $h = '<article class="appt-card" data-tilt-soft>' .
        '<div class="appt-date"><span class="m">' . e(mb_substr(FR_MONTHS[(int)$d->format('n') - 1], 0, 4)) . '</span><span class="d">' . $d->format('j') . '</span><span class="t">' . e($d->format('H:i')) . '</span></div>' .
        '<div class="appt-body"><div class="d-flex flex-wrap gap-2 align-items-center mb-1"><h3>Dr ' . e($a['doc_first'] . ' ' . $a['doc_last']) . '</h3>' . status_badge($a['status']) . '</div>' .
        '<p class="mb-1 text-body-secondary">' . e($a['specialty']) . ' · ' . e(fmt_date($a['start_at'], true)) . '</p>' .
        '<p class="mb-0 small text-body-secondary"><i class="bi bi-geo-alt"></i> ' . e($a['address'] . ', ' . $a['city']) . '</p>' .
        (!empty($a['reason']) ? '<p class="mb-0 small mt-1"><i class="bi bi-chat-left-text"></i> ' . e($a['reason']) . '</p>' : '') .
        '</div>';
    if ($actions) {
        $h .= '<div class="appt-actions">';
        if ($active) {
            $h .= '<button class="btn btn-outline-danger btn-sm" data-api="cancel.php" data-payload=\'{"id":' . (int)$a['id'] . '}\' data-confirm="Votre rendez-vous du ' . e(fmt_datetime($a['start_at'])) . ' sera annulé et le créneau libéré.">' .
                '<i class="bi bi-x-lg"></i> Annuler</button>';
        } else {
            $h .= '<a class="btn btn-ghost btn-sm" href="' . e(url('practitioner.php?id=' . (int)$a['pid'])) . '"><i class="bi bi-arrow-repeat"></i> Reprendre RDV</a>';
        }
        $h .= '</div>';
    }
    return $h . '</article>';
}

/** Fenêtre de confirmation partagée (annulation). */
function confirm_modal(): void
{
    ?>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-4 text-center">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h2 class="h5 mb-2" id="confirmTitle">Annuler ce rendez-vous ?</h2>
        <p class="text-body-secondary mb-4" id="confirmText"></p>
        <div class="d-flex gap-2 justify-content-center">
          <button class="btn btn-ghost" data-bs-dismiss="modal">Garder</button>
          <button class="btn btn-danger" id="confirmOk">Oui, annuler</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
}

function err_class(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' is-invalid' : '';
}

function err_msg(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="invalid-feedback">' . e($errors[$k]) . '</div>' : '';
}
