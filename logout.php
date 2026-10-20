<?php
require 'config.php';
// Hanya hapus status login; data pendaftaran tetap ada di session.
unset($_SESSION['login']);
header('Location: login.php');
exit;
