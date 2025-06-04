<?php
/**
 * Google Docs Service
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoogleDocsService {
    private $client;
    private $driveService;
    private $docsService;
    
    public function __construct($client) {
        $this->client = $client;
        $this->driveService = new Google\Service\Drive($client);
        $this->docsService = new Google\Service\Docs($client);
    }
    
    /**
     * Liste les documents Google Docs de l'utilisateur
     */
    public function listDocuments($pageSize = 10) {
        try {
            $optParams = array(
                'pageSize' => $pageSize,
                'fields' => 'nextPageToken, files(id, name, modifiedTime, createdTime, webViewLink)',
                'q' => "mimeType='application/vnd.google-apps.document' and trashed=false",
                'orderBy' => 'modifiedTime desc'
            );
            
            $results = $this->driveService->files->listFiles($optParams);
            return $results->getFiles();
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de la récupération des documents - ' . $e->getMessage());
            throw new Exception('Impossible de récupérer la liste des documents : ' . $e->getMessage());
        }
    }
    
    /**
     * Récupère un document Google Docs par son ID
     */
    public function getDocument($documentId) {
        try {
            return $this->docsService->documents->get($documentId);
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de la récupération du document ' . $documentId . ' - ' . $e->getMessage());
            throw new Exception('Impossible de récupérer le document : ' . $e->getMessage());
        }
    }
    
    /**
     * Extrait le contenu d'un document Google Docs
     */
    public function getDocumentContent($documentId) {
        try {
            $doc = $this->getDocument($documentId);
            
            // Parser le document selon la structure
            $content = $this->parseDocument($doc);
            
            return $content;
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de l\'extraction du contenu - ' . $e->getMessage());
            throw new Exception('Impossible d\'extraire le contenu : ' . $e->getMessage());
        }
    }
    
    /**
     * Importe un document Google Docs comme article WordPress
     */
    public function importDocumentAsPost($documentId) {
        try {
            // Récupérer le contenu du document
            $content = $this->getDocumentContent($documentId);
            
            // Créer l'article WordPress
            $postId = $this->createWordPressPost($content);
            
            if (is_wp_error($postId)) {
                throw new Exception('Erreur lors de la création de l\'article : ' . $postId->get_error_message());
            }
            
            return $postId;
            
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de l\'import - ' . $e->getMessage());
            throw new Exception('Impossible d\'importer le document : ' . $e->getMessage());
        }
    }
    
    /**
     * Parse le contenu d'un document Google Docs
     */
    private function parseDocument($doc) {
        $content = [
            'title' => $doc->getTitle(),
            'body' => '',
            'categories' => [],
            'tags' => [],
            'slug' => '',
            'author' => '',
            'meta_description' => '',
            'target_keyword' => '',
            'excerpt' => '',
            'status' => 'draft'
        ];
        
        try {
            // Récupérer le body du document
            $body = $doc->getBody();
            if ($body && $body->getContent()) {
                $content['body'] = $this->extractTextFromContent($body->getContent());
            }
            
            // Générer un slug automatique depuis le titre
            $content['slug'] = sanitize_title($doc->getTitle());
            
            // Essayer d'extraire des métadonnées depuis le contenu
            $this->extractMetaFromContent($content);
            
            // Générer un extrait automatique si pas défini
            if (empty($content['excerpt']) && !empty($content['body'])) {
                $content['excerpt'] = $this->generateExcerpt($content['body']);
            }
            
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors du parsing - ' . $e->getMessage());
            // Continuer même en cas d'erreur de parsing
        }
        
        return $content;
    }
    
    /**
     * Extrait le texte du contenu du document
     */
    private function extractTextFromContent($content) {
        $text = '';
        
        foreach ($content as $element) {
            if ($element->getParagraph()) {
                $paragraph = $element->getParagraph();
                $paragraphText = $this->extractTextFromParagraph($paragraph);
                
                if (!empty($paragraphText)) {
                    // Détecter les titres (style Heading)
                    $style = $paragraph->getParagraphStyle();
                    if ($style && $style->getNamedStyleType()) {
                        $styleType = $style->getNamedStyleType();
                        if (strpos($styleType, 'HEADING') !== false) {
                            $level = (int) substr($styleType, -1);
                            $text .= str_repeat('#', min($level, 6)) . ' ' . $paragraphText . "\n\n";
                        } else {
                            $text .= $paragraphText . "\n\n";
                        }
                    } else {
                        $text .= $paragraphText . "\n\n";
                    }
                }
            } elseif ($element->getTable()) {
                // Gérer les tableaux
                $text .= $this->extractTextFromTable($element->getTable()) . "\n\n";
            } elseif ($element->getTableOfContents()) {
                // Ignorer la table des matières
                continue;
            }
        }
        
        return trim($text);
    }
    
    /**
     * Extrait le texte d'un paragraphe
     */
    private function extractTextFromParagraph($paragraph) {
        $text = '';
        
        if ($paragraph->getElements()) {
            foreach ($paragraph->getElements() as $element) {
                if ($element->getTextRun()) {
                    $textRun = $element->getTextRun();
                    $textContent = $textRun->getContent();
                    
                    // Appliquer le formatage si nécessaire
                    $textStyle = $textRun->getTextStyle();
                    if ($textStyle) {
                        if ($textStyle->getBold()) {
                            $textContent = '**' . $textContent . '**';
                        }
                        if ($textStyle->getItalic()) {
                            $textContent = '*' . $textContent . '*';
                        }
                        if ($textStyle->getLink()) {
                            $url = $textStyle->getLink()->getUrl();
                            if ($url) {
                                $textContent = '[' . $textContent . '](' . $url . ')';
                            }
                        }
                    }
                    
                    $text .= $textContent;
                } elseif ($element->getInlineObjectElement()) {
                    // Gérer les images/objets intégrés
                    $text .= '[IMAGE]';
                }
            }
        }
        
        return trim($text);
    }
    
    /**
     * Extrait le texte d'un tableau
     */
    private function extractTextFromTable($table) {
        $text = '';
        
        if ($table->getTableRows()) {
            foreach ($table->getTableRows() as $rowIndex => $row) {
                if ($row->getTableCells()) {
                    $rowText = [];
                    foreach ($row->getTableCells() as $cell) {
                        if ($cell->getContent()) {
                            $cellText = $this->extractTextFromContent($cell->getContent());
                            $rowText[] = trim($cellText);
                        }
                    }
                    
                    // Format markdown pour les tableaux
                    if ($rowIndex === 0) {
                        // En-tête
                        $text .= '| ' . implode(' | ', $rowText) . " |\n";
                        $text .= '|' . str_repeat(' --- |', count($rowText)) . "\n";
                    } else {
                        $text .= '| ' . implode(' | ', $rowText) . " |\n";
                    }
                }
            }
        }
        
        return $text;
    }
    
    /**
     * Extrait les métadonnées depuis le contenu
     */
    private function extractMetaFromContent(&$content) {
        $body = $content['body'];
        $originalBody = $body;
        
        // Chercher des patterns pour extraire des métadonnées
        // Exemple: "Catégories: Web, Design"
        if (preg_match('/^Catégories?\s*:\s*(.+)$/im', $body, $matches)) {
            $categories = array_map('trim', explode(',', $matches[1]));
            $content['categories'] = array_filter($categories);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Tags: tag1, tag2, tag3"
        if (preg_match('/^Tags?\s*:\s*(.+)$/im', $body, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
            $content['tags'] = array_filter($tags);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Auteur: Nom de l'auteur"
        if (preg_match('/^Auteur\s*:\s*(.+)$/im', $body, $matches)) {
            $content['author'] = trim($matches[1]);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Description: Meta description"
        if (preg_match('/^Description\s*:\s*(.+)$/im', $body, $matches)) {
            $content['meta_description'] = trim($matches[1]);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Mot-clé: keyword"
        if (preg_match('/^Mot-clé?\s*:\s*(.+)$/im', $body, $matches)) {
            $content['target_keyword'] = trim($matches[1]);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Extrait: Extrait de l'article"
        if (preg_match('/^Extrait\s*:\s*(.+)$/im', $body, $matches)) {
            $content['excerpt'] = trim($matches[1]);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Exemple: "Statut: publish" ou "Statut: draft"
        if (preg_match('/^Statut\s*:\s*(publish|draft|private)$/im', $body, $matches)) {
            $content['status'] = trim($matches[1]);
            $body = str_replace($matches[0], '', $body);
        }
        
        // Nettoyer le body des lignes vides en trop
        $content['body'] = preg_replace('/\n{3,}/', "\n\n", trim($body));
        
        // Si le body est vide après nettoyage, restaurer l'original
        if (empty(trim($content['body']))) {
            $content['body'] = $originalBody;
        }
    }
    
    /**
     * Génère un extrait automatique
     */
    private function generateExcerpt($content, $length = 160) {
        // Supprimer le markdown et les balises
        $text = strip_tags(preg_replace('/[#*\[\]()_`]/', '', $content));
        
        // Limiter la longueur
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
            $text = substr($text, 0, strrpos($text, ' ')) . '...';
        }
        
        return trim($text);
    }
    
    /**
     * Crée un article WordPress depuis le contenu extrait
     */
    private function createWordPressPost($content) {
        // Préparer les données de l'article
        $postData = [
            'post_title' => $content['title'],
            'post_content' => $content['body'],
            'post_status' => $content['status'],
            'post_type' => 'post',
            'post_name' => $content['slug'],
        ];
        
        // Ajouter l'extrait si disponible
        if (!empty($content['excerpt'])) {
            $postData['post_excerpt'] = $content['excerpt'];
        }
        
        // Définir l'auteur si spécifié
        if (!empty($content['author'])) {
            $user = get_user_by('login', $content['author']);
            if (!$user) {
                $user = get_user_by('email', $content['author']);
            }
            if ($user) {
                $postData['post_author'] = $user->ID;
            }
        }
        
        // Créer l'article
        $postId = wp_insert_post($postData);
        
        if (is_wp_error($postId)) {
            return $postId;
        }
        
        // Ajouter les catégories
        if (!empty($content['categories'])) {
            $categoryIds = [];
            foreach ($content['categories'] as $categoryName) {
                $category = get_category_by_slug(sanitize_title($categoryName));
                if (!$category) {
                    // Créer la catégorie si elle n'existe pas
                    $newCategory = wp_insert_category([
                        'cat_name' => $categoryName,
                        'category_nicename' => sanitize_title($categoryName)
                    ]);
                    if (!is_wp_error($newCategory)) {
                        $categoryIds[] = $newCategory;
                    }
                } else {
                    $categoryIds[] = $category->term_id;
                }
            }
            
            if (!empty($categoryIds)) {
                wp_set_post_categories($postId, $categoryIds);
            }
        }
        
        // Ajouter les tags
        if (!empty($content['tags'])) {
            wp_set_post_tags($postId, $content['tags']);
        }
        
        // Ajouter les métadonnées SEO
        if (!empty($content['meta_description'])) {
            update_post_meta($postId, '_yoast_wpseo_metadesc', $content['meta_description']);
        }
        
        if (!empty($content['target_keyword'])) {
            update_post_meta($postId, '_yoast_wpseo_focuskw', $content['target_keyword']);
        }
        
        // Ajouter une métadonnée pour indiquer que l'article vient de Google Docs
        update_post_meta($postId, '_galopins_imported_from_gdocs', true);
        update_post_meta($postId, '_galopins_import_date', current_time('mysql'));
        
        return $postId;
    }
    
    /**
     * Vérifie si un document peut être importé
     */
    public function canImportDocument($documentId) {
        try {
            $doc = $this->getDocument($documentId);
            return !empty($doc->getTitle());
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtient un aperçu du contenu d'un document
     */
    public function getDocumentPreview($documentId, $maxLength = 300) {
        try {
            $content = $this->getDocumentContent($documentId);
            
            $preview = [
                'title' => $content['title'],
                'excerpt' => $this->generateExcerpt($content['body'], $maxLength),
                'categories' => $content['categories'],
                'tags' => $content['tags'],
                'word_count' => str_word_count(strip_tags($content['body']))
            ];
            
            return $preview;
        } catch (Exception $e) {
            error_log('Galopins Tools: Erreur lors de la génération de l\'aperçu - ' . $e->getMessage());
            return null;
        }
    }
}