<?php
session_start();
header('Location: ' . (empty($_SESSION['role']) ? 'login.php' : $_SESSION['role'] . '/dashboard.php'));
