<?php
/**
 * EditorModule - WYSIWYG editor integration
 * Default: TinyMCE (can be replaced with other editors)
 */

class EditorModule extends Module {
    
    public function init() {
        // Register hook to inject editor scripts
        $this->hook('view.render', array($this, 'injectEditorScripts'));
    }
    
    /**
     * Inject editor scripts into admin pages
     */
    public function injectEditorScripts($content) {
        // Only inject in admin pages
        $uri = request()->uri();
        
        if (strpos($uri, '/admin') === false) {
            return $content;
        }
        
        $editorScript = $this->getEditorScript();
        
        // Inject before </body>
        $content = str_replace('</body>', $editorScript . '</body>', $content);
        
        return $content;
    }
    
    /**
     * Get editor initialization script
     */
    private function getEditorScript() {
        return '
        <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
        <script>
            tinymce.init({
                selector: ".wysiwyg-editor",
                height: 500,
                menubar: false,
                plugins: [
                    "advlist autolink lists link image charmap print preview anchor",
                    "searchreplace visualblocks code fullscreen",
                    "insertdatetime media table paste code help wordcount"
                ],
                toolbar: "undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code"
            });
        </script>
        ';
    }
}
