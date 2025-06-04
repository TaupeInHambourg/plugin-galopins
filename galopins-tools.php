<?php
/**
 * Plugin Name: Galopins Tools
 * Description: Plugin développé par l'agence Galopins pour améliorer le fonctionnement de nos sites.
 * Version: 1.0
 * Author: Agence Galopins
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GLP_PATH', plugin_dir_path(__FILE__));
define('GLP_URL', plugin_dir_url(__FILE__));

// Include composer autoload
if (file_exists(GLP_PATH . 'vendor/autoload.php')) {
    require_once GLP_PATH . 'vendor/autoload.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Galopins Tools: Veuillez exécuter "composer install" dans le répertoire du plugin.</p></div>';
    });
    return;
}

// Include files
require_once GLP_PATH . 'includes/gdoc-auth.php';
require_once GLP_PATH . 'includes/class/class-gdoc-auth-handler.php';
require_once GLP_PATH . 'includes/services/class-gdoc-service.php';
require_once GLP_PATH . 'includes/functions.php';

// Plugin activation hook
register_activation_hook(__FILE__, 'galopins_tools_activate');
function galopins_tools_activate() {
    // Vérifications lors de l'activation
    if (!extension_loaded('curl')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Le plugin Galopins Tools nécessite l\'extension PHP cURL.');
    }
}

// Initialize plugin
add_action('plugins_loaded', 'galopins_tools_init_plugin');
function galopins_tools_init_plugin() {
    // Initialize admin menu
    add_action('admin_menu', 'galopins_tools_menu');
}

// Admin menu
function galopins_tools_menu() {
    add_menu_page(
        'Galopins Tools',
        'Galopins Tools',
        'manage_options',
        'galopins-tools',
        'galopins_tools_main_page',
        esc_url(GLP_URL . 'src/assets/galopins-logo.svg'),
        30
    );
    
    add_submenu_page(
        'galopins-tools',
        'Paramètres Google',
        'Paramètres',
        'manage_options',
        'galopins-tools-settings',
        'galopins_tools_settings_page'
    );
    
    // Hidden callback page
    add_submenu_page(
        null,
        'OAuth Callback',
        'OAuth Callback',
        'manage_options',
        'galopins-tools-callback',
        'galopins_tools_callback_handler'
    );
}

// Main page
function galopins_tools_main_page() {
    ?>
    <div class="wrap">
        <h1>Galopins Tools</h1>
        
        <?php
        $auth = new GoogleDocsAuth();
        if ($auth->isAuthenticated()) {
            ?>
            <div class="notice notice-success">
                <p><strong>✓ Connexion Google établie</strong> - Vous pouvez maintenant importer vos documents.</p>
            </div>
            
            <h2>Importer un document Google Docs</h2>
            <p>Fonctionnalité en développement...</p>
            <?php
        } else {
            ?>
            <div class="notice notice-warning">
                <p><strong>Connexion Google requise</strong> - Veuillez configurer votre connexion dans les paramètres.</p>
            </div>
            <p><a href="<?php echo admin_url('admin.php?page=galopins-tools-settings'); ?>" class="button button-primary">Configurer la connexion Google</a></p>
            <?php
        }
        ?>
    </div>
    <?php
}

// Settings page
function galopins_tools_settings_page() {
    // Handle success/error messages
    $message = '';
    if (isset($_GET['auth'])) {
        if ($_GET['auth'] === 'success') {
            $message = '<div class="notice notice-success is-dismissible"><p>Connexion Google établie avec succès !</p></div>';
        } elseif ($_GET['auth'] === 'error') {
            $message = '<div class="notice notice-error is-dismissible"><p>Erreur lors de la connexion Google. Veuillez réessayer.</p></div>';
        }
    }
    
    if (isset($_GET['revoked'])) {
        $message = '<div class="notice notice-info is-dismissible"><p>Accès Google révoqué avec succès.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php echo $message; ?>

        <h2>Connexion Google</h2>
        <?php
        try {
            $auth = new GoogleDocsAuth();
            if ($auth->isAuthenticated()) {
                echo '<div class="notice notice-success inline"><p><strong>✓ Connecté à Google</strong></p></div>';
                echo '<p>Votre plugin est connecté à votre compte Google et peut accéder à vos documents.</p>';
                echo '<p><a href="' . admin_url('admin.php?page=galopins-tools-settings&revoke=1') . '" class="button" onclick="return confirm(\'Êtes-vous sûr de vouloir révoquer l\\\'accès ?\')">Révoquer l\'accès</a></p>';
            } else {
                echo '<div class="notice notice-warning inline"><p><strong>⚠ Non connecté</strong></p></div>';
                echo '<p>Cliquez sur le bouton ci-dessous pour autoriser le plugin à accéder à vos documents Google.</p>';
                echo '<p><a href="' . esc_url($auth->getAuthUrl()) . '" class="button button-primary">Se connecter à Google</a></p>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error inline"><p><strong>Erreur :</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        }
        ?>
        
        <h3>Instructions de configuration</h3>
        <ol>
            <li>Créez un projet dans la <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Activez les APIs "Google Drive API" et "Google Docs API"</li>
            <li>Créez des identifiants OAuth 2.0</li>
            <li>Ajoutez cette URL de redirection : <code><?php echo admin_url('admin.php?page=galopins-tools-callback'); ?></code></li>
            <li>Saisissez votre ID Client et Secret Client ci-dessus</li>
        </ol>
    </div>
    <?php
}

// Callback handler
function galopins_tools_callback_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Accès non autorisé');
    }
    
    if (isset($_GET['code'])) {
        try {
            $auth = new GoogleDocsAuth();
            $result = $auth->handleCallback($_GET['code']);
            
            if ($result) {
                wp_redirect(admin_url('admin.php?page=galopins-tools-settings&auth=success'));
            } else {
                wp_redirect(admin_url('admin.php?page=galopins-tools-settings&auth=error'));
            }
        } catch (Exception $e) {
            error_log('Galopins Tools Auth Error: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=galopins-tools-settings&auth=error'));
        }
    } else {
        wp_redirect(admin_url('admin.php?page=galopins-tools-settings'));
    }
    exit;
}