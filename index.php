<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
} elseif ($_SESSION['role'] === 'rh') {
    header("Location: dashboard/index.php");
} else {
    header("Location: aspirante/index.php");
}
exit();
?>
