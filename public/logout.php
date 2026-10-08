<?php
require __DIR__ . '/../includes/bootstrap.php';
require_post_csrf();
logout_user();
session_start();
flash('success', 'Vous êtes déconnecté. À bientôt !');
redirect('login.php');
