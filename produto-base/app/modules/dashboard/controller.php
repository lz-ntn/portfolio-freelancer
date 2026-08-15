<?php
/**
 * Dashboard — página inicial simples.
 * Substitui pelos teus KPIs quando o produto crescer.
 */

function dashboard_list(): void
{
    requireAuth();

    $user = currentUser();
    require APP_ROOT . '/app/modules/dashboard/views/home.php';
}
