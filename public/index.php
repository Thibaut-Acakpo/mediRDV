<?php
require __DIR__ . '/../includes/bootstrap.php';

$specialties = specialties_all();
$user = current_user();
page_header('Réservation de rendez-vous médicaux', ['body' => 'page-home']);
?>
<div class="intro" id="intro" aria-hidden="true">
  <svg viewBox="0 0 300 80" class="intro-ecg"><path d="M0 44h86l14-30 22 62 20-48 14 16h144" /></svg>
  <span class="intro-name"><?= e(config('app.name')) ?></span>
</div>

<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 hero-copy">
        <span class="hero-tag"><span class="pulse-dot"></span> Réservation en ligne, 24 h/24</span>
        <h1>Votre rendez-vous médical, réservé en deux minutes.</h1>
        <p class="lead">Choisissez votre praticien, prenez un créneau réellement libre et recevez un rappel par email avant la consultation.</p>

        <form class="hero-search" action="<?= e(url('practitioners.php')) ?>" method="get">
          <div class="hs-field">
            <i class="bi bi-heart-pulse"></i>
            <select name="specialty" aria-label="Spécialité">
              <option value="">Toutes les spécialités</option>
              <?php foreach ($specialties as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="hs-field">
            <i class="bi bi-geo-alt"></i>
            <input type="search" name="q" placeholder="Ville ou nom du praticien" aria-label="Ville ou nom">
          </div>
          <button class="btn btn-brand" type="submit"><i class="bi bi-search"></i> Rechercher</button>
        </form>
        <p class="hero-note"><i class="bi bi-shield-check"></i> Gratuit pour les patients · Annulation en un clic</p>
      </div>

      <div class="col-lg-6">
        <div class="scene" data-scene aria-hidden="true"><div class="scene-stage">
          <div class="scene-glow"></div>
          <div class="scene-ring r1"></div>
          <div class="scene-ring r2"></div>
          <div class="scene-card sc-main">
            <div class="sc-head"><span class="avatar">KA</span><div><strong>Dr Koffi Adjovi</strong><small>Médecine générale · Cotonou</small></div></div>
            <div class="sc-days"><span>Lun<b>6</b></span><span class="on">Mar<b>7</b></span><span>Mer<b>8</b></span><span>Jeu<b>9</b></span></div>
            <div class="sc-slots"><i>08:00</i><i>08:30</i><i class="on">09:00</i><i>09:30</i><i class="off">10:00</i><i>10:30</i></div>
          </div>
          <div class="scene-card sc-confirm"><i class="bi bi-check-circle-fill"></i><div><strong>Rendez-vous confirmé</strong><small>Mardi à 09:00</small></div></div>
          <div class="scene-card sc-remind"><i class="bi bi-bell-fill"></i><div><strong>Rappel envoyé</strong><small>La veille, par email</small></div></div>
        </div></div>
      </div>
    </div>
  </div>
  <svg class="hero-ecg" viewBox="0 0 1200 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70h380l30-52 40 100 36-80 24 32h690"/></svg>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <h2>Deux espaces, un seul agenda</h2>
      <p>Les patients réservent, les praticiens gardent la main sur leurs horaires. Chacun voit la même chose en temps réel.</p>
    </div>
    <div class="row g-4">
      <div class="col-lg-6" data-reveal>
        <article class="feature-card" data-tilt>
          <div class="fc-icon"><i class="bi bi-person-heart"></i></div>
          <h3>Espace patient</h3>
          <ul class="tick-list">
            <li>Recherche par spécialité, ville ou nom</li>
            <li>Uniquement des créneaux libres, mis à jour en direct</li>
            <li>Confirmation et rappel automatiques par email</li>
            <li>Historique et annulation depuis votre espace</li>
          </ul>
          <a class="btn btn-ghost" href="<?= e(url($user ? dashboard_url($user) : 'register.php')) ?>">Je suis patient</a>
        </article>
      </div>
      <div class="col-lg-6" data-reveal style="--d:.12s">
        <article class="feature-card alt" data-tilt>
          <div class="fc-icon"><i class="bi bi-clipboard2-pulse"></i></div>
          <h3>Espace praticien</h3>
          <ul class="tick-list">
            <li>Horaires hebdomadaires par plages, congés et fermetures</li>
            <li>Confirmation automatique ou manuelle des demandes</li>
            <li>Agenda du jour, suivi des présences et des absences</li>
            <li>Moins d'oublis grâce aux rappels envoyés aux patients</li>
          </ul>
          <a class="btn btn-ghost" href="<?= e(url($user ? dashboard_url($user) : 'register.php?role=practitioner')) ?>">Je suis praticien</a>
        </article>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-head" data-reveal>
      <h2>Comment ça marche</h2>
    </div>
    <ol class="steps">
      <li data-reveal><span class="step-n">1</span><h3>Choisissez un praticien</h3><p>Filtrez par spécialité ou par ville et consultez le prochain créneau disponible.</p></li>
      <li data-reveal style="--d:.1s"><span class="step-n">2</span><h3>Sélectionnez un créneau</h3><p>Seules les heures encore libres sont proposées, impossible de réserver deux fois le même créneau.</p></li>
      <li data-reveal style="--d:.2s"><span class="step-n">3</span><h3>Recevez la confirmation et le rappel</h3><p>Un email confirme votre rendez-vous, puis un rappel automatique arrive la veille.</p></li>
    </ol>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <h2>Parcourir par spécialité</h2>
    </div>
    <div class="spec-grid">
      <?php foreach ($specialties as $i => $s): ?>
        <a class="spec-tile" data-tilt-soft data-reveal style="--d:<?= $i * 0.04 ?>s" href="<?= e(url('practitioners.php?specialty=' . (int)$s['id'])) ?>">
          <i class="bi <?= e($s['icon']) ?>"></i><span><?= e($s['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section pt-0">
  <div class="container">
    <div class="cta-band" data-reveal>
      <div>
        <h2>Vous êtes praticien ?</h2>
        <p>Ouvrez votre agenda en ligne, définissez vos horaires et laissez vos patients réserver seuls.</p>
      </div>
      <a class="btn btn-light btn-lg" href="<?= e(url('register.php?role=practitioner')) ?>">Ouvrir mon agenda</a>
    </div>
  </div>
</section>
<?php page_footer(); ?>
