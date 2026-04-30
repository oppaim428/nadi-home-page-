<?php
/**
 * Site config — a single document keyed by id='site_config'.
 * Stored as JSON in the `site_config.data` column.
 */
declare(strict_types=1);

function config_default(): array
{
    return [
        'theme'             => 'cosmic',

        'gplay_url'         => '#',
        'appstore_url'      => '#',
        'appgallery_url'    => '#',
        'apk_url'           => '#',

        'contact_email'     => 'nadiplayer@nadi.kr',

        'hero_eyebrow_en'   => 'Premium IPTV Experience',
        'hero_title_en'     => 'Master Your TV',
        'hero_subtitle_en'  => 'Watch +5000 live channels, +1200 movies and +500 series across phone, tablet and TV — in stunning quality.',

        'hero_eyebrow_ar'   => 'تجربة IPTV فاخرة',
        'hero_title_ar'     => 'تحكم في تلفازك',
        'hero_subtitle_ar'  => 'شاهد أكثر من 5000 قناة و1200 فيلم و500 مسلسل على هاتفك ولوحيك وتلفازك بجودة مذهلة.',

        'sec1_title_en'     => 'Master your TV.',
        'sec1_desc_en'      => 'Live, on-demand, and your own subscriptions — all in one beautiful player.',
        'sec1_title_ar'     => 'تحكم في تلفازك.',
        'sec1_desc_ar'      => 'البث المباشر، حسب الطلب، واشتراكاتك الخاصة — كل ذلك في مشغل واحد فاخر.',

        'sec2_title_en'     => 'Channels in the cloud.',
        'sec2_desc_en'      => '5000+ channels organized smartly with EPG, favourites and instant zapping.',
        'sec2_title_ar'     => 'قنوات في السحابة.',
        'sec2_desc_ar'      => 'أكثر من 5000 قناة منظمة بذكاء مع دليل البرامج والمفضلة والتنقل الفوري.',

        'sec3_title_en'     => 'On all your screens.',
        'sec3_desc_en'      => 'Phone, tablet, Android TV, Fire TV — one subscription, every device.',
        'sec3_title_ar'     => 'على جميع شاشاتك.',
        'sec3_desc_ar'      => 'هاتف، لوحي، Android TV، Fire TV — اشتراك واحد لكل الأجهزة.',

        'sec4_title_en'     => 'Add your options.',
        'sec4_desc_en'      => 'Bring your own Xtream Codes or M3U URL. We don\'t sell content — just the player.',
        'sec4_title_ar'     => 'أضف خياراتك.',
        'sec4_desc_ar'      => 'استخدم Xtream Codes أو رابط M3U الخاص بك. نحن لا نبيع المحتوى — فقط المشغل.',

        'price_1m'          => '$12.99',
        'price_6m'          => '$39.99',
        'price_12m'         => '$69.99',
        'subscribe_url'     => '#',
        'free_trial_url'    => '#',

        'logo_url'          => '',
        'hero_phone_url'    => '',
        'nebula_bg_url'     => '',
        'favicon_url'       => '',

        // Cosmic + Yellow theme screenshot mockups (editable in admin)
        'screen_home_url'     => '',
        'screen_channels_url' => '',
        'screen_movies_url'   => '',
        'screen_login_url'    => '',
    ];
}

function config_get(): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT data FROM site_config WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => 'site_config']);
    $row = $stmt->fetch();
    if (!$row) {
        $defaults = config_default();
        $insert = $pdo->prepare('INSERT INTO site_config (id, data, updated_at) VALUES (:id, :data, :u)');
        $insert->execute([
            ':id'   => 'site_config',
            ':data' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            ':u'    => now_iso(),
        ]);
        return $defaults;
    }
    $data = json_decode((string) $row['data'], true);
    if (!is_array($data)) {
        return config_default();
    }
    // Merge with defaults so missing keys get sensible values.
    return array_merge(config_default(), $data);
}

function config_save(array $payload): array
{
    $merged = array_merge(config_default(), config_get(), $payload);
    // Only keep known keys.
    $allowed = array_keys(config_default());
    $clean = [];
    foreach ($allowed as $k) {
        $clean[$k] = isset($merged[$k]) ? (string) $merged[$k] : '';
    }
    // Normalize theme: only allow known values.
    if (!in_array($clean['theme'], ['cosmic', 'yellow', 'premium'], true)) {
        $clean['theme'] = 'cosmic';
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM site_config WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => 'site_config']);
    if ($stmt->fetch()) {
        $u = $pdo->prepare('UPDATE site_config SET data = :d, updated_at = :u WHERE id = :id');
        $u->execute([
            ':id' => 'site_config',
            ':d'  => json_encode($clean, JSON_UNESCAPED_UNICODE),
            ':u'  => now_iso(),
        ]);
    } else {
        $i = $pdo->prepare('INSERT INTO site_config (id, data, updated_at) VALUES (:id, :d, :u)');
        $i->execute([
            ':id' => 'site_config',
            ':d'  => json_encode($clean, JSON_UNESCAPED_UNICODE),
            ':u'  => now_iso(),
        ]);
    }
    return $clean;
}
