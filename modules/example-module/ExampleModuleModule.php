<?php
/**
 * ExampleModuleModule - Demonstrates module best practices
 */

class ExampleModuleModule extends Module {
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
        $this->hook('theme.navigation', array($this, 'addNavigation'));
    }
    
    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/example', array($this, 'showExample'));
        return $data;
    }
    
    public function addNavigation($items) {
        if ($this->settings()->get('show_in_menu', true)) {
            $items[] = array(
                'id' => 'example',
                'title' => 'Example',
                'url' => base_url('/example'),
                'order' => 50,
            );
        }
        return $items;
    }
    
    public function showExample() {
        $this->view('example-module:example', array(
            'title' => 'Example Page',
            'message' => $this->settings()->get('welcome_message', 'Welcome!'),
        ));
    }
    
    public function onEnable() {
        $this->log('info', 'Example module enabled');
        return true;
    }
    
    public function onDisable() {
        $this->log('info', 'Example module disabled');
        return true;
    }
}
