<?php declare(strict_types=1);

/**
 * Common contract for mutable schema-backed settings stores.
 *
 * Mutations are in-memory until save() is called. This makes a group of
 * related changes one explicit persistence operation.
 */
interface SettingsStoreInterface
{
    public function schema();

    public function load();

    public function reload();

    public function all();

    public function get($path, $default = null);

    public function has($path);

    public function set($path, $value);

    public function setMultiple($values);

    public function replace($data);

    public function delete($path);

    public function save();

    public function path();
}
