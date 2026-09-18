<?php
$activePage = $_GET['page'] ?? 'monthly';
header('Location: ' . $activePage . '.php');
exit;
