<?php
define('SYSTEM_INIT', true);
require_once 'config.php';

if (Session::isLoggedIn()) {
    $userType = Session::getUserType();
    if ($userType === 'admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/cliente/dashboard.php');
    }
} else {
    redirect('/login.php');
}
?>