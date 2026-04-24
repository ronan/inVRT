<?php

namespace InVRT\Core\Service;

use Symfony\Component\Yaml\Yaml;

class PlanService
{
    /**
     * Merge known project/page metadata into plan.yaml while preserving user keys.
     */
    public static function update(
        string $planFile,
        string $projectUrl,
        string $projectId = '',
        string $projectTitle = '',
        string $homePageTitle = '',
    ): bool {
        $data = self::read($planFile);

        if (!isset($data['project']) || !is_array($data['project'])) {
            $data['project'] = [];
        }
        if (!isset($data['pages']) || !is_array($data['pages'])) {
            $data['pages'] = [];
        }

        $projectUrl = trim($projectUrl);
        if ($projectUrl !== '') {
            $data['project']['url'] = $projectUrl;
        }

        $projectId = trim($projectId);
        if ($projectId !== '') {
            $data['project']['id'] = $projectId;
        }

        $projectTitle = trim($projectTitle);
        if ($projectTitle !== '') {
            $data['project']['title'] = $projectTitle;
        }

        if (!array_key_exists('/', $data['pages'])) {
            $data['pages']['/'] = [];
        }

        $homePageTitle = trim($homePageTitle);
        if ($homePageTitle !== '') {
            if (is_array($data['pages']['/'])) {
                $data['pages']['/']['title'] = $homePageTitle;
            } else {
                $data['pages']['/'] = $homePageTitle;
            }
        }

        $dir = dirname($planFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return file_put_contents($planFile, Yaml::dump($data, 6, 2)) !== false;
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
     * @return array<string, mixed>
     */
    private static function read(string $planFile): array
    {
        if (!is_file($planFile) || !is_readable($planFile) || filesize($planFile) === 0) {
            return [];
        }

        $parsed = Yaml::parseFile($planFile);
        return is_array($parsed) ? $parsed : [];
    }
}
