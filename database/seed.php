<?php
/**
 * Données de démonstration (idempotent). Lancé par install.php --seed
 * ou directement : php database/seed.php
 */
if (!isset($pdo)) {
    if (PHP_SAPI !== 'cli') exit('CLI uniquement.');
    $config = require dirname(__DIR__) . '/config/config.php';
    $c = $config['db'];
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
date_default_timezone_set('Africa/Porto-Novo');

$PATIENT_PWD  = 'Patient123';
$PRACTICE_PWD = 'Praticien123';

$val = function (string $sql, array $p = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchColumn();
};
$user = function (string $role, string $first, string $last, string $email, string $phone, string $pwd) use ($pdo, $val): int {
    if ($id = $val('SELECT id FROM users WHERE email = ?', [$email])) return (int)$id;
    $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, phone, password_hash) VALUES (?,?,?,?,?,?)')
        ->execute([$role, $first, $last, $email, $phone, password_hash($pwd, PASSWORD_DEFAULT)]);
    return (int)$pdo->lastInsertId();
};

$patient = $user('patient', 'Thibaut', 'Démo', 'patient@medirdv.test', '+229 01 97 00 00 01', $PATIENT_PWD);
$user('patient', 'Awa', 'Gbèdo', 'awa@medirdv.test', '+229 01 96 00 00 02', $PATIENT_PWD);

$WEEK = [1, 2, 3, 4, 5];
$doctors = [
    ['Koffi', 'Adjovi', 'dr.adjovi@medirdv.test', 'Médecine générale', 'Cotonou', 'Rue des Cheminots, Haie Vive', 30, 10000,
        'Médecin généraliste : suivi des adultes et des familles, certificats, bilans de santé.',
        [[$WEEK, [['08:00', '12:00'], ['15:00', '18:00']]], [[6], [['09:00', '12:00']]]], 1],
    ['Aïcha', 'Zinsou', 'dr.zinsou@medirdv.test', 'Pédiatrie', 'Cotonou', 'Avenue Jean-Paul II, Fidjrossè', 30, 15000,
        'Pédiatre : suivi de croissance, vaccinations, consultations du nourrisson et de l\'enfant.',
        [[[1, 3, 5], [['09:00', '13:00']]], [[2, 4], [['14:00', '18:00']]]], 1],
    ['Romuald', 'Hounkpatin', 'dr.hounkpatin@medirdv.test', 'Cardiologie', 'Porto-Novo', 'Boulevard de la Marina', 45, 25000,
        'Cardiologue : électrocardiogramme, échographie cardiaque, suivi de l\'hypertension. Rendez-vous confirmés manuellement.',
        [[[2, 3, 4], [['08:30', '12:30'], ['14:30', '17:30']]]], 0],
    ['Sènami', 'Dossou', 'dr.dossou@medirdv.test', 'Gynécologie', 'Cotonou', 'Akpakpa, près du marché', 30, 20000,
        'Gynécologue-obstétricienne : suivi de grossesse, contraception, consultations de prévention.',
        [[[1, 2, 4, 5], [['09:00', '12:00'], ['14:00', '17:00']]]], 1],
    ['Cédric', 'Agbodjan', 'dr.agbodjan@medirdv.test', 'Dentisterie', 'Abomey-Calavi', 'Route de l\'Université', 30, 12000,
        'Chirurgien-dentiste : soins, détartrage, extractions et urgences dentaires.',
        [[$WEEK, [['08:00', '12:00'], ['14:00', '17:00']]], [[6], [['08:00', '12:00']]]], 1],
    ['Mariam', 'Bio', 'dr.bio@medirdv.test', 'Dermatologie', 'Parakou', 'Rue du Grand Marché', 20, 15000,
        'Dermatologue : acné, eczéma, allergies cutanées, suivi des grains de beauté.',
        [[$WEEK, [['09:00', '13:00']]]], 1],
];

$pids = [];
foreach ($doctors as $i => [$fn, $ln, $email, $sp, $city, $addr, $dur, $price, $bio, $hours, $auto]) {
    $uid = $user('practitioner', $fn, $ln, $email, '+229 01 90 00 00 ' . str_pad((string)($i + 11), 2, '0', STR_PAD_LEFT), $PRACTICE_PWD);
    if ($pid = $val('SELECT id FROM practitioners WHERE user_id = ?', [$uid])) {
        $pids[] = (int)$pid;
        continue;
    }
    $pdo->prepare('INSERT INTO practitioners (user_id, specialty_id, city, address, bio, slot_minutes, price_fcfa, auto_confirm) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$uid, $val('SELECT id FROM specialties WHERE name = ?', [$sp]), $city, $addr, $bio, $dur, $price, $auto]);
    $pid = (int)$pdo->lastInsertId();
    $pids[] = $pid;
    $ins = $pdo->prepare('INSERT INTO availabilities (practitioner_id, weekday, start_time, end_time) VALUES (?,?,?,?)');
    foreach ($hours as [$days, $ranges]) {
        foreach ($days as $d) foreach ($ranges as [$s, $e]) $ins->execute([$pid, $d, $s, $e]);
    }
}

// Quelques rendez-vous pour que les tableaux de bord ne soient pas vides
if (!$val('SELECT COUNT(*) FROM appointments WHERE patient_id = ?', [$patient])) {
    $nextWeekday = function (int $minDays, string $time): string {
        $d = new DateTimeImmutable("+$minDays days");
        while ((int)$d->format('N') > 5) $d = $d->modify('+1 day');
        return $d->format('Y-m-d') . " $time:00";
    };
    $add = function (int $pid, string $start, int $min, string $status, string $reason) use ($pdo, $patient) {
        $end = (new DateTimeImmutable($start))->modify("+$min minutes")->format('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO appointments (patient_id, practitioner_id, start_at, end_at, status, reason, reminder_sent) VALUES (?,?,?,?,?,?,0)')
            ->execute([$patient, $pid, $start, $end, $status, $reason]);
    };
    $add($pids[0], $nextWeekday(2, '09:00'), 30, 'confirmed', 'Bilan de santé annuel');
    $add($pids[4], $nextWeekday(5, '10:00'), 30, 'confirmed', 'Détartrage');
    $add($pids[2], $nextWeekday(4, '09:30'), 45, 'pending', 'Contrôle tension');
    $past = (new DateTimeImmutable('-12 days'))->format('Y-m-d') . ' 09:00:00';
    $add($pids[0], $past, 30, 'completed', 'Fièvre persistante');
}
echo "Données de démonstration prêtes.\n";
echo "  Patient    : patient@medirdv.test / $PATIENT_PWD\n";
echo "  Praticien  : dr.adjovi@medirdv.test / $PRACTICE_PWD\n";
