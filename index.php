<?php 

    namespace WIM;

    // files ##########################################################################################
    require_once dirname(__FILE__) . '/db-auth.php';
    
    // redirect to admin
    Auth::redirectToAdmin();