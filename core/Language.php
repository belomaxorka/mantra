<?php
/**
 * Language - Internationalization system
 */

class Language {
    private $currentLang = 'en';
    private $translations = array();
    private $fallbackLang = 'en';
    
    public function __construct() {
        $app = Application::getInstance();
        $this->fallbackLang = $app->config('default_language', 'en');
        $this->currentLang = $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Detect current language
     */
    private function detectLanguage() {
        // From session
        if (isset($_SESSION['language'])) {
            return $_SESSION['language'];
        }
        
        // From cookie
        if (isset($_COOKIE['language'])) {
            return $_COOKIE['language'];
        }
        
        return $this->fallbackLang;
    }
    
    /**
     * Load translations for current language
     */
    private function loadTranslations() {
        $langFile = MANTRA_CONTENT . '/languages/' . $this->currentLang . '.json';
        
        if (file_exists($langFile)) {
            $content = file_get_contents($langFile);
            $this->translations = json_decode($content, true);
        }
    }
    
    /**
     * Translate a key
     */
    public function translate($key, $params = array()) {
        $value = $this->get($key);
        
        // Replace parameters
        foreach ($params as $k => $v) {
            $value = str_replace('{' . $k . '}', $v, $value);
        }
        
        return $value;
    }
    
    /**
     * Get translation
     */
    public function get($key, $default = null) {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        return $default !== null ? $default : $key;
    }
    
    /**
     * Set current language
     */
    public function setLanguage($lang) {
        $this->currentLang = $lang;
        $_SESSION['language'] = $lang;
        $this->loadTranslations();
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLang;
    }
}

/**
 * Helper function for translation
 */
function __($key, $params = array()) {
    static $lang = null;
    if ($lang === null) {
        $lang = new Language();
    }
    return $lang->translate($key, $params);
}
