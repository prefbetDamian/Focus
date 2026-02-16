<?php
/**
 * Wylogowanie - czyści sesję
 */
require_once __DIR__.'/core/session.php';

session_destroy();
session_start();
session_regenerate_id(true);

header('Location: index.html');
exit;
