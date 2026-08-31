<?php

namespace Snappbox\Api;

class SnappBoxConfig
{
    const CACHE_OPTION = 'snappbox_remote_config';
    const CRON_HOOK = 'snappbox_refresh_remote_config';

    private $apiUrl;
    public function __construct()
    {
        $this->apiUrl = \Snappbox\EnvConfig::get('SNAPPBOX_WOO_CONFIG_URL');
    }

    public function snappb_get_config()
    {
        $cached = \get_option(self::CACHE_OPTION, null);

        if (is_array($cached) && isset($cached['config']) && is_array($cached['config'])) {
            return (object) $cached['config'];
        }

        if (is_object($cached)) {
            return $cached;
        }

        return null;
    }

    public function snappb_refresh_config()
    {
        if (empty($this->apiUrl)) {
            return null;
        }

        $response = \wp_remote_get($this->apiUrl, [
            'timeout'     => 5,
            'redirection' => 2,
        ]);

        if (\is_wp_error($response)) {
            return null;
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $decoded = \json_decode(\wp_remote_retrieve_body($response), true);
        if (!is_array($decoded) || \json_last_error() !== \JSON_ERROR_NONE) {
            return null;
        }

        \update_option(self::CACHE_OPTION, [
            'config'     => $decoded,
            'updated_at' => \time(),
        ], false);

        return (object) $decoded;
    }
}
