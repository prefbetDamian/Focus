<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
session_destroy();
header("Location: ../index.html");
