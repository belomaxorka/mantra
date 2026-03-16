<?php
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General Settings',
            'fields' => array(
                array(
                    'path' => 'show_in_menu',
                    'type' => 'toggle',
                    'title' => 'Show in Navigation Menu',
                    'default' => true,
                ),
                array(
                    'path' => 'welcome_message',
                    'type' => 'text',
                    'title' => 'Welcome Message',
                    'default' => 'Welcome to Example Module!',
                ),
            ),
        ),
    ),
);
