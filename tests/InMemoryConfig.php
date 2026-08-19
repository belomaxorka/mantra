<?php declare(strict_types=1);

/** Minimal config repository test double shared by authorization tests. */
final class InMemoryConfig implements SettingsStoreInterface
{
    private array $data;
    private int $saveCount = 0;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function schema()
    {
        return null;
    }

    public function get($path, $default = null)
    {
        return Config::getNested($this->data, (string)$path, $default);
    }

    public function load(): self
    {
        return $this;
    }

    public function reload(): self
    {
        return $this;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has($path): bool
    {
        return Config::hasNested($this->data, (string)$path);
    }

    public function set($path, $value): self
    {
        Config::setNested($this->data, (string)$path, $value);
        return $this;
    }

    public function setMultiple($values): self
    {
        if (is_array($values)) {
            foreach ($values as $path => $value) {
                $this->set($path, $value);
            }
        }
        return $this;
    }

    public function replace($data): self
    {
        $this->data = is_array($data) ? $data : [];
        return $this;
    }

    public function delete($path): bool
    {
        $parts = explode('.', (string)$path);
        $last = array_pop($parts);
        $cursor = &$this->data;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return false;
            }
            $cursor = &$cursor[$part];
        }
        if (!is_array($cursor) || !array_key_exists($last, $cursor)) {
            return false;
        }
        unset($cursor[$last]);
        return true;
    }

    public function save(): bool
    {
        $this->saveCount++;
        return true;
    }

    public function getSaveCount(): int
    {
        return $this->saveCount;
    }

    public function path()
    {
        return null;
    }
}
