<?php

namespace NgoTools\LaravelStarter\Support;

class ManifestGenerator
{
    public function generate(array $options): array
    {
        $manifest = [
            'schema_version' => 1,
            'slug' => $options['slug'],
            'name' => [
                'de' => $options['name'],
                'en' => $options['name'],
            ],
            'description' => [
                'de' => $options['description'] ?? '',
                'en' => $options['description'] ?? '',
            ],
            'version' => '1.0.0',
            'author' => [
                'name' => $options['author'] ?? '',
            ],
            'oauth_redirect_uri' => '/auth/callback',
            'scopes' => $options['scopes'] ?? [],
            'ui_slots' => [],
            'webhook_url' => null,
            'webhook_events' => [],
        ];

        foreach ($options['ui_slots'] ?? [] as $slot) {
            $manifest['ui_slots'][] = match ($slot) {
                'navigation_entry' => [
                    'slot' => 'navigation_entry',
                    'type' => 'iframe',
                    'endpoint_url' => '/ui',
                    'label' => $options['name'],
                    'icon' => 'heroicon-o-puzzle-piece',
                    'navigation_group' => 'extra',
                ],
                'dashboard_card' => [
                    'slot' => 'dashboard_card',
                    'type' => 'iframe',
                    'endpoint_url' => '/ui/widget',
                    'label' => $options['name'],
                ],
                'contact_tab' => [
                    'slot' => 'contact_tab',
                    'type' => 'iframe',
                    'endpoint_url' => '/ui/contact',
                    'label' => $options['name'],
                ],
            };
        }

        if (! empty($options['webhook_events'])) {
            $manifest['webhook_url'] = '/webhooks';
            $manifest['webhook_events'] = $options['webhook_events'];
        }

        return $manifest;
    }

    public function writeToFile(array $manifest, string $basePath): void
    {
        $dir = $basePath . '/.well-known';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir . '/ngotools.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
}
