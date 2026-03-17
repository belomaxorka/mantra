<?php
/**
 * Debug helpers
 */

/**
 * Debug dump
 */
function dd($var)
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    exit;
}
