<?php

return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => array('key' => 'editor.settings.tabs.general', 'fallback' => 'General'),
            'fields' => array(
                array(
                    'path' => 'enabled',
                    'type' => 'toggle',
                    'title' => array('key' => 'editor.settings.enabled', 'fallback' => 'Enable editor integration'),
                    'default' => true,
                ),
                array(
                    'path' => 'editor',
                    'type' => 'select',
                    'title' => array('key' => 'editor.settings.editor', 'fallback' => 'Editor'),
                    'default' => 'tinymce',
                    'options' => array(
                        'tinymce' => array('key' => 'editor.settings.editor.tinymce', 'fallback' => 'TinyMCE'),
                    ),
                ),
            ),
        ),
    ),
);
