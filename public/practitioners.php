<?php
require __DIR__ . '/../includes/bootstrap.php';

$q = trim((string)($_GET['q'] ?? ''));
$spec = (int)($_GET['specialty'] ?? 0);
$list = search_practitioners(['q' => $q, 'specialty' => $spec]);
$specialties = specialties_all();

page_header('Trouver un praticien', ['body' => 'page-search', 'nav' => 'search']);
?>
<section class="container page-section">
  <div class="dash-head" data-reveal>
    <div>
      <h1>Trouver un praticien</h1>
      <p class="text-body-secondary mb-0">Réservez un créneau libre, sans appel ni attente.</p>
    </div>
  </div>

  <form class="filter-bar" id="filterForm" method="get" data-reveal>
    <div class="hs-field grow">
      <i class="bi bi-search"></i>
      <input type="search" name="q" id="fq" value="<?= e($q) ?>" placeholder="Nom, ville, spécialité…" aria-label="Recherche" autocomplete="off">
    </div>
    <div class="hs-field">
      <i class="bi bi-heart-pulse"></i>
      <select name="specialty" id="fs" aria-label="Spécialité">
        <option value="">Toutes les spécialités</option>
        <?php foreach ($specialties as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $spec === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Rechercher</button>
  </form>

  <p class="result-count" id="resultCount" aria-live="polite"><?= count($list) ?> praticien<?= count($list) > 1 ? 's' : '' ?></p>
  <div class="row g-4" id="results"><?= practitioner_cards_html($list) ?></div>
</section>
<?php page_footer(['directory.js']); ?>
