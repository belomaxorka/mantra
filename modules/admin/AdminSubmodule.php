<?php

interface AdminSubmodule {
    /**
     * Unique admin submodule id (used for routing/sidebar).
     */
    public function getId();

    /**
     * Initialize submodule (register hooks/routes/sidebar/assets).
     */
    public function init($admin);
}
