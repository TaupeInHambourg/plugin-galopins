<?php
/**
 * Google Docs Authentication Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoogleDocsAuth {
    private $client;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    
    public function __construct() {
        // Utiliser les variables d'environnement
        $this->clientId = defined('CLIENT_GOOGLE_ID') ? CLIENT_GOOGLE_ID : '';
        $this->clientSecret = defined('CLIENT_GOOGLE_SECRET') ? CLIENT_GOOGLE_SECRET : '';
        $this->redirectUri = admin_url('admin.php?page=galopins-tools-callback');
        
        $this->initClient();
    }
    
    private function initClient() {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Les variables d\'environnement CLIENT_GOOGLE_ID et CLIENT_GOOGLE_SECRET sont requises.');
        }
        
        $this->client = new Google\Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri($this->redirectUri);
        
        // Scopes requis
        $this->client->addScope([
            Google\Service\Drive::DRIVE_READONLY,
            Google\Service\Docs::DOCUMENTS_READONLY
        ]);
        
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setIncludeGrantedScopes(true);
        
        // Charger le token existant
        $this->loadExistingToken();
    }
    
    private function loadExistingToken() {
        $token = get_option('galopins_tools_token');
        if ($token && is_array($token)) {
            $this->client->setAccessToken($token);
            
            // Vérifier et rafraîchir le token si nécessaire
            if ($this->client->isAccessTokenExpired()) {
                $this->refreshToken();
            }
        }
    }
    
    private function refreshToken() {
        try {
            $refreshToken = $this->client->getRefreshToken();
            if ($refreshToken) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                
                if (!isset($newToken['error'])) {
                    // Conserver le refresh token s'il n'est pas inclus dans la nouvelle réponse
                    if (!isset($newToken['refresh_token']) && $refreshToken) {
                        $newToken['refresh_token'] = $refreshToken;
                    }
                    
                    update_option('galopins_tools_token', $newToken);
                    $this->client->setAccessToken($newToken);
                    return true;
                } else {
                    error_log('Galopins Tools: Erreur lors du rafraîchissement du token - ' . $newToken['error']);
                    $this->revokeAccess();
                    return false;
                }
            }
        } catch (Exception $e) {
            error_log('Galopins Tools: Exception lors du rafraîchissement du token - ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    public function getAuthUrl() {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Les variables d\'environnement CLIENT_GOOGLE_ID et CLIENT_GOOGLE_SECRET doivent être définies.');
        }
        
        // Générer un state pour la sécurité
        $state = wp_create_nonce('galopins_tools_oauth_state');
        update_option('galopins_tools_oauth_state', $state, false);
        
        $this->client->setState($state);
        return $this->client->createAuthUrl();
    }
    
    public function handleCallback($code) {
        try {
            // Vérifier le state pour la sécurité
            if (isset($_GET['state'])) {
                $storedState = get_option('galopins_tools_oauth_state');
                if ($_GET['state'] !== $storedState) {
                    throw new Exception('État OAuth invalide');
                }
                delete_option('galopins_tools_oauth_state');
            }
            
            // Échanger le code contre un token
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (array_key_exists('error', $accessToken)) {
                throw new Exception('Erreur OAuth : ' . $accessToken['error']);
            }
            
            // Sauvegarder le token
            update_option('galopins_tools_token', $accessToken);
            $this->client->setAccessToken($accessToken);
            
            // Log de succès
            error_log('Galopins Tools: Authentification Google réussie');
            
            return true;
            
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur d\'authentification - ' . $e->getMessage());
            return false;
        }
    }
    
    public function getClient() {
        if (!$this->isAuthenticated()) {
            throw new Exception('Client Google non authentifié');
        }
        return $this->client;
    }
    
    public function isAuthenticated() {
        if (!$this->client) {
            return false;
        }
        
        $token = $this->client->getAccessToken();
        if (!$token) {
            return false;
        }
        
        // Si le token est expiré, tenter de le rafraîchir
        if ($this->client->isAccessTokenExpired()) {
            return $this->refreshToken();
        }
        
        return true;
    }
    
    public function revokeAccess() {
        try {
            if ($this->client && $this->client->getAccessToken()) {
                $this->client->revokeToken();
            }
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de la révocation - ' . $e->getMessage());
        } finally {
            // Supprimer les tokens stockés
            delete_option('galopins_tools_token');
            delete_option('galopins_tools_oauth_state');
        }
    }
    
    public function getTokenInfo() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $token = $this->client->getAccessToken();
        return [
            'expires_at' => isset($token['expires_in']) ? time() + $token['expires_in'] : null,
            'scopes' => $this->client->getScopes()
        ];
    }
}