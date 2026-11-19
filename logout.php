<?php
/* ============================================================================
   logout.php - End the session
   ============================================================================ */
require_once __DIR__ . '/db.php';

logout_user();

/* a fresh session, only so the goodbye message can be shown on the login page */
session_start();
flash('You have been logged out.', 'info');
redirect('login.php');
