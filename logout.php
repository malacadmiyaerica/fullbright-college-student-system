<?php
/**
 * Fullbright College - Student Management System
 * Log the student out.
 */

session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;