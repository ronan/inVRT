<?php

namespace InVRT\Core\Service;

use Symfony\Component\Yaml\Yaml;

class PlanService
{
    /**
     * Merge known project/page metadata into plan.yaml while preserving user keys.
     *
     * Recognised $data keys:
     *   url            string  → project.url
     *   id             string  → project.id
     *   name           string  → project.name
     *   title          string  → project.title
     *   home_title     string  → pages./.title
     *   checked_at     string  → project.checked_at
     *   profiles       string[] (replaces top-level profiles list)
     *   exclude        string[] (only seeded when current value is empty)
     *
     * @param array<string, mixed> $data
     */
    public static function update(string $planFile, array $data): bool
    {
        $plan = self::read($planFile);

        if (!isset($plan['project']) || !is_array($plan['project'])) {
            $plan['project'] = [];
        }
        if (!isset($plan['pages']) || !is_array($plan['pages'])) {
            $plan['pages'] = [];
        }

        foreach (['url', 'id', 'name', 'title', 'checked_at'] as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
            if ($value !== '') {
                $plan['project'][$key] = $value;
            }
        }

        if (!empty($data['profiles']) && is_array($data['profiles'])) {
            $existing = is_array($plan['profiles'] ?? null) ? $plan['profiles'] : [];
            if (self::isList($data['profiles'])) {
                if ($existing === [] || self::isList($existing)) {
                    $plan['profiles'] = array_values(array_unique(array_merge(
                        array_map('strval', $existing),
                        array_map('strval', $data['profiles']),
                    )));
                } else {
                    foreach ($data['profiles'] as $name) {
                        $existing[(string) $name] ??= [];
                    }
                    $plan['profiles'] = $existing;
                }
            } else {
                $plan['profiles'] = (is_array($existing) && !self::isList($existing))
                    ? $existing + $data['profiles']
                    : $data['profiles'];
            }
        }

        foreach (['environments', 'devices'] as $section) {
            if (!empty($data[$section]) && is_array($data[$section])) {
                $existing = is_array($plan[$section] ?? null) ? $plan[$section] : [];
                $plan[$section] = $existing + $data[$section];
            }
        }

        if (!empty($data['exclude']) && is_array($data['exclude'])) {
            $existing = $plan['exclude'] ?? null;
            if (!is_array($existing) || $existing === []) {
                $plan['exclude'] = array_values(array_map('strval', $data['exclude']));
            }
        }

        if (!array_key_exists('/', $plan['pages'])) {
            $plan['pages']['/'] = [];
        }

        $homeTitle = isset($data['home_title']) ? trim((string) $data['home_title']) : '';
        if ($homeTitle !== '') {
            if (is_array($plan['pages']['/'])) {
                $plan['pages']['/']['title'] = $homeTitle;
            } else {
                $plan['pages']['/'] = $homeTitle;
            }
        }

        $plan = self::orderTopLevel($plan);

        $dir = dirname($planFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return file_put_contents($planFile, Yaml::dump($plan, 6, 2)) !== false;
    }

    /** Does plan.yaml contain at least one testable page path? */
    public static function hasPages(string $planFile): bool
    {
        if ($planFile === '' || !is_readable($planFile)) {
            return false;
        }
        $pages = self::read($planFile)['pages'] ?? [];
        if (!is_array($pages)) {
            return false;
        }
        foreach (array_keys($pages) as $key) {
            if (is_string($key) && str_starts_with($key, '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Read a plan file as a raw associative array. Returns an empty array when
     * the file is missing, unreadable, or empty. Yaml parse errors propagate.
     *
     * @return array<string, mixed>
     */
    public static function read(string $planFile): array
    {
        if (!is_file($planFile) || !is_readable($planFile) || filesize($planFile) === 0) {
            return [];
        }

        $parsed = Yaml::parseFile($planFile);
        return is_array($parsed) ? $parsed : [];
    }

    /** Detect whether an array is a list (sequential 0-indexed) vs a keyed map. */
    private static function isList(array $value): bool
    {
        return $value === [] || array_is_list($value);
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private static function orderTopLevel(array $plan): array
    {
        $order  = ['project', 'environments', 'profiles', 'devices', 'exclude', 'pages'];
        $sorted = [];
        foreach ($order as $key) {
            if (array_key_exists($key, $plan)) {
                $sorted[$key] = $plan[$key];
                unset($plan[$key]);
            }
        }
        return $sorted + $plan;
    }
}
