<?php

declare(strict_types=1);

if (!defined('LANDING_V2_EDITOR')) {
    http_response_code(403);
    exit('Forbidden');
}

require public_path('landing-v2/includes/content.php');

$icons = icon_options();
$message = '';
$error = '';
$assetBase = '/landing-v2/assets';
$previewUrl = '/landing-v2/index.php?v=' . (@filemtime(public_path('landing-v2/data/content.json')) ?: time());
$cssVersion = @filemtime(public_path('landing-v2/assets/app.css')) ?: time();

function compact_list(?array $items): array
{
    if (!is_array($items)) {
        return [];
    }

    return array_values(array_filter($items, static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }

        foreach ($item as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }));
}

function posted_content(array $post): array
{
    $defaults = default_content();
    $incoming = $post['content'] ?? [];
    $content = merge_content($defaults, is_array($incoming) ? $incoming : []);

    $content['nav'] = compact_list($content['nav'] ?? []);
    $content['about']['bullets'] = compact_list($content['about']['bullets'] ?? []);
    $content['steps']['items'] = compact_list($content['steps']['items'] ?? []);
    $content['features']['items'] = compact_list($content['features']['items'] ?? []);
    $content['screenshots']['items'] = compact_list($content['screenshots']['items'] ?? []);
    $content['advantages']['items'] = compact_list($content['advantages']['items'] ?? []);
    $content['faq']['items'] = compact_list($content['faq']['items'] ?? []);
    $content['footer']['links'] = compact_list($content['footer']['links'] ?? []);
    $content['footer']['socials'] = compact_list($content['footer']['socials'] ?? []);

    return $content;
}

if (request()->isMethod('post') && (request('action') === 'save')) {
    try {
        save_content(posted_content(request()->all()));
        $message = 'İçerik kaydedildi.';
    } catch (Throwable $e) {
        $error = 'Kayıt sırasında bir hata oluştu.';
    }
}

$content = get_content();

function field_name(string $path): string
{
    $parts = explode('.', $path);
    $name = 'content';
    foreach ($parts as $part) {
        $name .= '[' . $part . ']';
    }

    return $name;
}

function input_field(string $path, string $label, ?string $value, string $type = 'text', bool $wide = false): void
{
    ?>
    <div class="field <?= $wide ? 'wide' : '' ?>">
        <label><?= e($label) ?></label>
        <input type="<?= e($type) ?>" name="<?= e(field_name($path)) ?>" value="<?= e($value) ?>">
    </div>
    <?php
}

function textarea_field(string $path, string $label, ?string $value, bool $wide = true): void
{
    ?>
    <div class="field <?= $wide ? 'wide' : '' ?>">
        <label><?= e($label) ?></label>
        <textarea name="<?= e(field_name($path)) ?>"><?= e($value) ?></textarea>
    </div>
    <?php
}

function icon_select(string $name, string $label, ?string $value, array $icons): void
{
    ?>
    <div class="field">
        <label><?= e($label) ?></label>
        <div class="icon-select">
            <span class="icon-preview"><span class="material-symbols-outlined"><?= e($value ?: 'star') ?></span></span>
            <select name="<?= e($name) ?>" data-icon-select>
                <?php foreach ($icons as $icon): ?>
                    <option value="<?= e($icon) ?>" <?= $icon === $value ? 'selected' : '' ?>><?= e($icon) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
}

function direct_input(string $name, string $label, ?string $value, string $type = 'text', bool $wide = false): void
{
    ?>
    <div class="field <?= $wide ? 'wide' : '' ?>">
        <label><?= e($label) ?></label>
        <input type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">
    </div>
    <?php
}

function direct_textarea(string $name, string $label, ?string $value, bool $wide = true): void
{
    ?>
    <div class="field <?= $wide ? 'wide' : '' ?>">
        <label><?= e($label) ?></label>
        <textarea name="<?= e($name) ?>"><?= e($value) ?></textarea>
    </div>
    <?php
}

?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bil Bakalım Web Landing Yönetimi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/app.css?v=<?= e((string) $cssVersion) ?>">
    <style>
        :root {
            --primary: <?= e($content['settings']['primary']) ?>;
            --primary-soft: <?= e($content['settings']['primary_soft']) ?>;
            --secondary: <?= e($content['settings']['secondary']) ?>;
            --surface: <?= e($content['settings']['surface']) ?>;
        }
    </style>
</head>
<body class="admin-shell">
    <header class="admin-header">
        <div class="container">
            <div class="admin-title">
                <div class="admin-mark"><span class="material-symbols-outlined">dashboard_customize</span></div>
                <div>
                    <h1>Bil Bakalım Web Landing Yönetimi</h1>
                    <p>Web landing içeriklerini buradan yönet.</p>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <a class="small-btn" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener">Siteyi Gör</a>
            </div>
        </div>
    </header>

    <form method="post" action="">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <div class="container admin-layout">
            <aside class="admin-sidebar">
                <a href="#genel"><span class="material-symbols-outlined">tune</span>Genel</a>
                <a href="#menu"><span class="material-symbols-outlined">menu</span>Menü</a>
                <a href="#hero"><span class="material-symbols-outlined">rocket_launch</span>Hero</a>
                <a href="#hakkinda"><span class="material-symbols-outlined">info</span>Hakkında</a>
                <a href="#adimlar"><span class="material-symbols-outlined">route</span>Adımlar</a>
                <a href="#ozellikler"><span class="material-symbols-outlined">star</span>Özellikler</a>
                <a href="#ekranlar"><span class="material-symbols-outlined">smartphone</span>Ekranlar</a>
                <a href="#avantajlar"><span class="material-symbols-outlined">diamond</span>Avantajlar</a>
                <a href="#sss"><span class="material-symbols-outlined">quiz</span>SSS</a>
                <a href="#iletisim"><span class="material-symbols-outlined">mail</span>İletişim</a>
                <a href="#footer"><span class="material-symbols-outlined">web_asset</span>Footer</a>
            </aside>

            <main class="admin-main">
                <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="notice" style="color:#8a1111;background:#ffeded"><?= e($error) ?></div><?php endif; ?>

                <section class="panel-card" id="genel">
                    <h2>Genel Ayarlar</h2>
                    <div class="panel-grid">
                        <?php input_field('settings.site_title', 'Site Başlığı', $content['settings']['site_title'], 'text', true); ?>
                        <?php textarea_field('settings.meta_description', 'Meta Açıklama', $content['settings']['meta_description']); ?>
                        <?php input_field('settings.primary', 'Ana Mor', $content['settings']['primary'], 'color'); ?>
                        <?php input_field('settings.primary_soft', 'Yumuşak Mor', $content['settings']['primary_soft'], 'color'); ?>
                        <?php input_field('settings.secondary', 'Amber', $content['settings']['secondary'], 'color'); ?>
                        <?php input_field('settings.surface', 'Arka Plan', $content['settings']['surface'], 'color'); ?>
                        <?php input_field('settings.logo_url', 'Header Logo URL', $content['settings']['logo_url'], 'url', true); ?>
                        <?php input_field('settings.footer_logo_url', 'Footer Logo URL', $content['settings']['footer_logo_url'], 'url', true); ?>
                    </div>
                </section>

                <section class="panel-card" id="menu">
                    <h2>Menü Linkleri</h2>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['nav'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Menü Öğesi</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php direct_input("content[nav][$index][label]", 'Başlık', $item['label']); ?>
                                    <?php direct_input("content[nav][$index][target]", 'Hedef ID', $item['target']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="nav">+ Menü Öğesi Ekle</button>
                </section>

                <section class="panel-card" id="hero">
                    <h2>Hero Alanı</h2>
                    <div class="panel-grid">
                        <?php input_field('hero.eyebrow', 'Üst Etiket', $content['hero']['eyebrow']); ?>
                        <?php input_field('hero.highlight', 'Vurgulanacak Kelime', $content['hero']['highlight']); ?>
                        <?php textarea_field('hero.title', 'Başlık', $content['hero']['title']); ?>
                        <?php textarea_field('hero.description', 'Açıklama', $content['hero']['description']); ?>
                        <?php input_field('hero.primary_cta', 'Ana Buton Metni', $content['hero']['primary_cta']); ?>
                        <?php input_field('hero.primary_url', 'Ana Buton Linki', $content['hero']['primary_url']); ?>
                        <?php input_field('hero.secondary_cta', 'İkincil Buton Metni', $content['hero']['secondary_cta']); ?>
                        <?php input_field('hero.secondary_url', 'İkincil Buton Linki', $content['hero']['secondary_url']); ?>
                        <?php icon_select(field_name('hero.secondary_icon'), 'İkincil Buton İkonu', $content['hero']['secondary_icon'], $icons); ?>
                        <?php input_field('hero.phone_image', 'Ön Telefon Görseli URL', $content['hero']['phone_image'], 'url', true); ?>
                        <?php input_field('hero.phone_image_secondary', 'Arka Telefon Görseli URL', $content['hero']['phone_image_secondary'], 'url', true); ?>
                        <?php input_field('hero.trust_text', 'Güven Satırı', $content['hero']['trust_text'], 'text', true); ?>
                    </div>
                </section>

                <section class="panel-card" id="hakkinda">
                    <h2>Hakkında Alanı</h2>
                    <div class="panel-grid">
                        <?php input_field('about.eyebrow', 'Üst Etiket', $content['about']['eyebrow']); ?>
                        <?php input_field('about.title', 'Başlık', $content['about']['title']); ?>
                        <?php textarea_field('about.description', 'Açıklama', $content['about']['description']); ?>
                        <?php input_field('about.image', 'Kare/Yatay Görsel URL', $content['about']['image'], 'url', true); ?>
                    </div>
                    <h3 class="font-headline" style="color:var(--primary)">Kısa Maddeler</h3>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['about']['bullets'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Madde</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php icon_select("content[about][bullets][$index][icon]", 'İkon', $item['icon'], $icons); ?>
                                    <?php direct_input("content[about][bullets][$index][title]", 'Metin', $item['title']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="about-bullets">+ Madde Ekle</button>
                </section>

                <section class="panel-card" id="adimlar">
                    <h2>Nasıl Oynanır?</h2>
                    <div class="panel-grid">
                        <?php input_field('steps.title', 'Başlık', $content['steps']['title']); ?>
                        <?php input_field('steps.description', 'Açıklama', $content['steps']['description']); ?>
                    </div>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['steps']['items'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Adım</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php icon_select("content[steps][items][$index][icon]", 'İkon', $item['icon'], $icons); ?>
                                    <?php direct_input("content[steps][items][$index][title]", 'Başlık', $item['title']); ?>
                                    <?php direct_textarea("content[steps][items][$index][description]", 'Açıklama', $item['description']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="steps">+ Adım Ekle</button>
                </section>

                <section class="panel-card" id="ozellikler">
                    <h2>Özellikler</h2>
                    <div class="panel-grid">
                        <?php input_field('features.title', 'Başlık', $content['features']['title']); ?>
                        <?php input_field('features.description', 'Açıklama', $content['features']['description']); ?>
                    </div>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['features']['items'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Özellik Kartı</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php icon_select("content[features][items][$index][icon]", 'İkon', $item['icon'], $icons); ?>
                                    <?php direct_input("content[features][items][$index][title]", 'Başlık', $item['title']); ?>
                                    <?php direct_textarea("content[features][items][$index][description]", 'Açıklama', $item['description']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="features">+ Özellik Ekle</button>
                </section>

                <section class="panel-card" id="ekranlar">
                    <h2>Uygulama Ekranları</h2>
                    <div class="panel-grid">
                        <?php input_field('screenshots.title', 'Başlık', $content['screenshots']['title']); ?>
                        <?php input_field('screenshots.description', 'Açıklama', $content['screenshots']['description']); ?>
                    </div>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['screenshots']['items'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Ekran Görseli</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php direct_input("content[screenshots][items][$index][image]", 'Görsel URL', $item['image'], 'url', true); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="screenshots">+ Ekran Ekle</button>
                </section>

                <section class="panel-card" id="avantajlar">
                    <h2>Avantajlar</h2>
                    <div class="panel-grid">
                        <?php input_field('advantages.title', 'Başlık', $content['advantages']['title']); ?>
                        <?php input_field('advantages.description', 'Açıklama', $content['advantages']['description']); ?>
                    </div>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['advantages']['items'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Avantaj Kartı</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php icon_select("content[advantages][items][$index][icon]", 'İkon', $item['icon'], $icons); ?>
                                    <?php direct_input("content[advantages][items][$index][title]", 'Başlık', $item['title']); ?>
                                    <?php direct_textarea("content[advantages][items][$index][description]", 'Açıklama', $item['description']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="advantages">+ Avantaj Ekle</button>
                </section>

                <section class="panel-card" id="sss">
                    <h2>Sıkça Sorulan Sorular</h2>
                    <div class="panel-grid">
                        <?php input_field('faq.title', 'Başlık', $content['faq']['title'], 'text', true); ?>
                    </div>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['faq']['items'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Soru</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php direct_input("content[faq][items][$index][question]", 'Soru', $item['question'], 'text', true); ?>
                                    <?php direct_textarea("content[faq][items][$index][answer]", 'Cevap', $item['answer']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="faq">+ Soru Ekle</button>
                </section>

                <section class="panel-card" id="iletisim">
                    <h2>İletişim</h2>
                    <div class="panel-grid">
                        <?php input_field('contact.title', 'Başlık', $content['contact']['title']); ?>
                        <?php input_field('contact.email', 'E-posta', $content['contact']['email'], 'email'); ?>
                        <?php textarea_field('contact.description', 'Açıklama', $content['contact']['description']); ?>
                        <?php input_field('contact.email_label', 'E-posta Etiketi', $content['contact']['email_label']); ?>
                        <?php input_field('contact.form_name_label', 'Ad Alanı Etiketi', $content['contact']['form_name_label']); ?>
                        <?php input_field('contact.form_email_label', 'E-posta Alanı Etiketi', $content['contact']['form_email_label']); ?>
                        <?php input_field('contact.form_message_label', 'Mesaj Alanı Etiketi', $content['contact']['form_message_label']); ?>
                        <?php input_field('contact.form_button', 'Form Butonu', $content['contact']['form_button']); ?>
                    </div>
                </section>

                <section class="panel-card" id="footer">
                    <h2>Footer</h2>
                    <div class="panel-grid">
                        <?php input_field('footer.copyright', 'Copyright', $content['footer']['copyright'], 'text', true); ?>
                    </div>
                    <h3 class="font-headline" style="color:var(--primary)">Footer Linkleri</h3>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['footer']['links'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Link</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php direct_input("content[footer][links][$index][label]", 'Başlık', $item['label']); ?>
                                    <?php direct_input("content[footer][links][$index][url]", 'URL', $item['url']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="footer-links">+ Link Ekle</button>

                    <h3 class="font-headline" style="color:var(--primary)">Sosyal İkonlar</h3>
                    <div class="repeater" data-repeater>
                        <?php foreach ($content['footer']['socials'] as $index => $item): ?>
                            <div class="repeat-item">
                                <div class="repeat-head"><strong>Sosyal</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
                                <div class="panel-grid">
                                    <?php icon_select("content[footer][socials][$index][icon]", 'İkon', $item['icon'], $icons); ?>
                                    <?php direct_input("content[footer][socials][$index][url]", 'URL', $item['url']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="small-btn" type="button" data-add="footer-socials">+ Sosyal Ekle</button>
                </section>

                <div class="save-bar">
                    <a class="small-btn" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener">Siteyi Gör</a>
                    <button class="pill pill-primary" type="submit">Tüm Değişiklikleri Kaydet</button>
                </div>
            </main>
        </div>
    </form>

    <template id="template-nav">
        <div class="repeat-item">
            <div class="repeat-head"><strong>Menü Öğesi</strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
            <div class="panel-grid">
                <div class="field"><label>Başlık</label><input name="content[nav][__i__][label]"></div>
                <div class="field"><label>Hedef ID</label><input name="content[nav][__i__][target]"></div>
            </div>
        </div>
    </template>
    <template id="template-about-bullets"><?= repeater_template('content[about][bullets][__i__]', 'Madde', ['icon', 'title'], $icons) ?></template>
    <template id="template-steps"><?= repeater_template('content[steps][items][__i__]', 'Adım', ['icon', 'title', 'description'], $icons) ?></template>
    <template id="template-features"><?= repeater_template('content[features][items][__i__]', 'Özellik Kartı', ['icon', 'title', 'description'], $icons) ?></template>
    <template id="template-screenshots"><?= repeater_template('content[screenshots][items][__i__]', 'Ekran Görseli', ['image'], $icons) ?></template>
    <template id="template-advantages"><?= repeater_template('content[advantages][items][__i__]', 'Avantaj Kartı', ['icon', 'title', 'description'], $icons) ?></template>
    <template id="template-faq"><?= repeater_template('content[faq][items][__i__]', 'Soru', ['question', 'answer'], $icons) ?></template>
    <template id="template-footer-links"><?= repeater_template('content[footer][links][__i__]', 'Link', ['label', 'url'], $icons) ?></template>
    <template id="template-footer-socials"><?= repeater_template('content[footer][socials][__i__]', 'Sosyal', ['icon', 'url'], $icons) ?></template>

    <script src="<?= e($assetBase) ?>/admin.js?v=<?= e((string) (@filemtime(public_path('landing-v2/assets/admin.js')) ?: time())) ?>"></script>
</body>
</html>
<?php

function icon_options_markup(array $icons): string
{
    $html = '';
    foreach ($icons as $icon) {
        $html .= '<option value="' . e($icon) . '">' . e($icon) . '</option>';
    }

    return $html;
}

function repeater_template(string $prefix, string $title, array $fields, array $icons): string
{
    ob_start();
    ?>
    <div class="repeat-item">
        <div class="repeat-head"><strong><?= e($title) ?></strong><button class="small-btn danger" type="button" data-remove>Sil</button></div>
        <div class="panel-grid">
            <?php foreach ($fields as $field): ?>
                <?php if ($field === 'icon'): ?>
                    <div class="field">
                        <label>İkon</label>
                        <div class="icon-select">
                            <span class="icon-preview"><span class="material-symbols-outlined">star</span></span>
                            <select name="<?= e($prefix) ?>[icon]" data-icon-select><?= icon_options_markup($icons) ?></select>
                        </div>
                    </div>
                <?php elseif (in_array($field, ['description', 'answer'], true)): ?>
                    <div class="field wide"><label><?= $field === 'answer' ? 'Cevap' : 'Açıklama' ?></label><textarea name="<?= e($prefix) ?>[<?= e($field) ?>]"></textarea></div>
                <?php else: ?>
                    <?php
                    $labels = [
                        'title' => 'Başlık',
                        'image' => 'Görsel URL',
                        'question' => 'Soru',
                        'label' => 'Başlık',
                        'url' => 'URL',
                    ];
                    $type = in_array($field, ['image', 'url'], true) ? 'url' : 'text';
                    ?>
                    <div class="field <?= in_array($field, ['image', 'question'], true) ? 'wide' : '' ?>">
                        <label><?= e($labels[$field] ?? $field) ?></label>
                        <input type="<?= e($type) ?>" name="<?= e($prefix) ?>[<?= e($field) ?>]">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
