<?php

namespace Snappbox;

if (! defined('ABSPATH')) {
    exit;
}

class EnvConfig
{
    /** @var bool */
    private static $loaded = false;

    /** @var array<string, string> */
    private static $vars = [];

    /**
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return [
            'SNAPPBOX_API_BASE_URL_STAGING'     => 'https://b2b-stg.snapp-box.com',
            'SNAPPBOX_API_BASE_URL_PRODUCTION'  => 'https://b2b.snapp-box.com',
            'SNAPPBOX_MAP_STYLE_URL'            => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
            'SNAPPBOX_MAP_RASTER_TILE_URL'      => 'https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png',
            'SNAPPBOX_MAP_REVERSE_URL'          => 'https://api.teh-1.snappmaps.ir/reverse/v1',
            'SNAPPBOX_MAP_NOMINATIM_URL'        => 'https://nominatim.openstreetmap.org/reverse',
            'SNAPPBOX_OSM_TILE_URL'             => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'SNAPPBOX_WOO_CONFIG_URL'           => 'https://assets.snapp-box.com/static/plugin/woo-config.json',
            'SNAPPBOX_NEARBY_API_STAGING'       => 'https://app-stg.snapp-box.com/api/v1/customer/nearby_biker_locations',
            'SNAPPBOX_NEARBY_API_PRODUCTION'    => 'https://app.snapp-box.com/api/v1/customer/nearby_biker_locations',
            'SNAPPBOX_CITIES_API_URL'           => 'https://customer.snapp-box.com/v2/delivery-category/by-city',
            'SNAPPBOX_CONNECT_URL'              => 'https://snapp-box.com/connect',
            'SNAPPBOX_TOP_UP_URL'               => 'https://app.snapp-box.com/top-up',
            'SNAPPBOX_YANDEX_METRIKA_ID'        => '105087875',
            'SNAPPBOX_SMAPP_KEY'                => 'aa22e8eef7d348d32f492d8a0c755f4d',
            'SNAPPBOX_SMAPP_AUTHORIZATION'      => 'pk.eyJ1IjoibWVpaCIsImEiOiJjamY2aTJxenIxank3MzNsbmY0anhwaG9mIn0.egsUz_uibSftB0sjSWb9qw',
        ];
    }

    public static function load(string $plugin_dir): void
    {
        if (self::$loaded) {
            return;
        }

        self::$vars = self::defaults();

        foreach (array_keys(self::$vars) as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                self::$vars[$key] = (string) $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string
    {
        if (! self::$loaded && defined('SNAPPBOX_DIR')) {
            self::load(SNAPPBOX_DIR);
        }

        return self::$vars[$key] ?? $default;
    }

    public static function yandex_script_url(): string
    {
        $id = self::get('SNAPPBOX_YANDEX_METRIKA_ID');

        return 'https://mc.yandex.ru/metrika/tag.js?id=' . rawurlencode($id);
    }

    public static function yandex_watch_url(): string
    {
        $id = self::get('SNAPPBOX_YANDEX_METRIKA_ID');

        return 'https://mc.yandex.ru/watch/' . rawurlencode($id);
    }
}
