<?php
/**
 * Google Docs Authentication Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check requirements and show notices
add_action('admin_notices', 'galopins_tools_admin_notices');
function galopins_tools_admin_notices() {
    // Only show on our plugin pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'galopins-tools') === false) {
        return;
    }
    
    $errors = galopins_tools_check_requirements();
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo '<div class="notice notice-error"><p><strong>Galopins Tools :</strong> ' . esc_html($error) . '</p></div>';
        }
    }
}

// Check plugin requirements
function galopins_tools_check_requirements() {
    $errors = [];
    
    // Check cURL
    if (!function_exists('curl_version')) {
        $errors[] = "L'extension PHP cURL est requise.";
    }
    
    // Check Google credentials
    $client_id = get_option('galopins_tools_client_id');
    $client_secret = get_option('galopins_tools_client_secret');
    
    if (empty($client_id) || empty($client_secret)) {
        $errors[] = "Veuillez configurer votre ID Client et Secret Client Google dans les paramètres.";
    }
    
    // Check if composer dependencies are loaded
    if (!class_exists('Google\Client')) {
        $errors[] = "Les dépendances Google ne sont pas installées. Exécutez 'composer install' dans le répertoire du plugin.";
    }
    
    return $errors;
}

// Handle revoke action
add_action('admin_init', 'galopins_tools_handle_actions');
function galopins_tools_handle_actions() {
    // Handle revoke access
    if (isset($_GET['page']) && $_GET['page'] === 'galopins-tools-settings' && isset($_GET['revoke'])) {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé');
        }
        
        try {
            $auth = new GoogleDocsAuth();
            $auth->revokeAccess();
            wp_redirect(admin_url('admin.php?page=galopins-tools-settings&revoked=1'));
            exit;
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de la révocation - ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=galopins-tools-settings&auth=error'));
            exit;
        }
    }
}