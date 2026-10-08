<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Support;

class OnenoteLightRagTarget
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $instances = config('intranet-app-msgraph.lightrag.instances', []);
        if (! is_array($instances)) {
            return [];
        }

        $options = [];
        foreach ($instances as $key => $instance) {
            if (! is_string($key) || ! is_array($instance)) {
                continue;
            }

            $options[$key] = (string) ($instance['label'] ?? $key);
        }

        return $options;
    }

    public static function known(string $instance): bool
    {
        return array_key_exists($instance, self::options());
    }

    public static function forNotebook(string $notebookId, string $notebookName): ?string
    {
        $configured = config('intranet-app-msgraph.lightrag.notebooks.'.$notebookId);
        if (is_string($configured) && self::known($configured)) {
            return $configured;
        }

        $name = mb_strtolower($notebookName);
        if (str_contains($name, 'wiki')) {
            return self::known('wiki') ? 'wiki' : null;
        }

        if (str_contains($name, 'teammeeting') || str_contains($name, 'team-meeting')) {
            return self::known('team-meetings') ? 'team-meetings' : null;
        }

        return null;
    }

    public static function url(string $instance): string
    {
        $url = config('intranet-app-msgraph.lightrag.instances.'.$instance.'.url');
        if (! is_string($url) || trim($url) === '') {
            throw new \InvalidArgumentException('Die LightRAG-Instanz ist nicht konfiguriert.');
        }

        return rtrim($url, '/');
    }
}
