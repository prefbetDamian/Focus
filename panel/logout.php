<?php
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'None');

session_start();

/* WYCZYŚĆ DANE */
$_SESSION = [];

/* USUŃ COOKIE SESJI */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/* ZNISZCZ SESJĘ */
session_destroy();

/* PRZEKIEROWANIE */
header("Location: ../index.html");
exit;
