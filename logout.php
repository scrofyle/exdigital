<?php
define('SYSTEM_INIT', true);
require_once 'config.php';

if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $userType = Session::getUserType();
    
    // Registrar logout
    logAccess($userType, $userId, 'logout', 'Logout realizado');
    
    // Destruir sessão
    Session::destroy();
}

redirect('/login.php');
?>