<?php

if (!defined('ABSPATH')) {
    exit;
}

function toolnest_register_tool_content()
{
    register_post_type('tool', [
        'labels' => [
            'name' => __('Tools', 'toolnest'),
            'menu_name' => __('Tool Library', 'toolnest'),
            'singular_name' => __('Tool', 'toolnest'),
            'add_new_item' => __('Add New Tool', 'toolnest'),
            'edit_item' => __('Edit Tool', 'toolnest'),
            'all_items' => __('All Tools', 'toolnest'),
        ],
        'public' => true,
        'publicly_queryable' => true,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'excerpt'],
        'taxonomies' => ['tool_category', 'tool_tag'],
        'has_archive' => false,
    ]);

    register_taxonomy('tool_category', ['tool'], [
        'labels' => [
            'name' => __('Tool Categories', 'toolnest'),
            'singular_name' => __('Tool Category', 'toolnest'),
            'all_items' => __('All Tool Categories', 'toolnest'),
            'edit_item' => __('Edit Tool Category', 'toolnest'),
            'add_new_item' => __('Add New Tool Category', 'toolnest'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_nav_menus' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'query_var' => false,
    ]);

    register_taxonomy('tool_tag', ['tool'], [
        'labels' => [
            'name' => __('Tool Tags', 'toolnest'),
            'singular_name' => __('Tool Tag', 'toolnest'),
            'all_items' => __('All Tool Tags', 'toolnest'),
            'edit_item' => __('Edit Tool Tag', 'toolnest'),
            'add_new_item' => __('Add New Tool Tag', 'toolnest'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_nav_menus' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'query_var' => false,
    ]);
}
add_action('init', 'toolnest_register_tool_content');

function toolnest_track_tool_view()
{
    if (!is_singular('tool') || is_preview()) {
        return;
    }

    $tool_id = get_queried_object_id();
    if (!$tool_id || 'publish' !== get_post_status($tool_id)) {
        return;
    }

    $view_count = (int) get_post_meta($tool_id, '_toolnest_view_count', true);
    update_post_meta($tool_id, '_toolnest_view_count', $view_count + 1);
}
add_action('template_redirect', 'toolnest_track_tool_view');

function toolnest_tool_breadcrumb()
{
    $all_tools_page = get_page_by_path('all-tools');
    $all_tools_url = $all_tools_page ? get_permalink($all_tools_page) : home_url('/all-tools/');
    ?>
    <nav aria-label="<?php esc_attr_e('Breadcrumb', 'toolnest'); ?>" class="tn-breadcrumb">
      <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item">
          <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'toolnest'); ?></a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?php echo esc_url($all_tools_url); ?>"><?php esc_html_e('All Tools', 'toolnest'); ?></a>
        </li>
        <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html(get_the_title()); ?></li>
      </ol>
    </nav>
    <?php
}

/**
 * Resolve stored tool icon meta value into full CSS icon classes.
 * Supports Font Awesome 6 classes (fa-solid, fa-regular, fa-brands, fas, far, fab)
 * as well as legacy Bootstrap Icons (bi-*) and a default fallback icon.
 */
function toolnest_get_tool_icon_class($icon = '')
{
    $icon = trim((string) $icon);

    if ('' === $icon) {
        return 'fa-solid fa-screwdriver-wrench';
    }

    if (preg_match('/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b/', $icon)) {
        return $icon;
    }

    if (0 === strpos($icon, 'fa-')) {
        return 'fa-solid ' . $icon;
    }

    if (0 === strpos($icon, 'bi-')) {
        return 'bi ' . $icon;
    }

    return $icon;
}

/**
 * Return curated list of Font Awesome 6 Free icons for the Tool Icon visual picker.
 */
function toolnest_get_fontawesome_icons()
{
    return [
        // Solid — Tools, Files, PDF, Image, Text, Calculators, Dev & Business
        ['class' => 'fa-solid fa-screwdriver-wrench', 'label' => 'Wrench & Screwdriver', 'style' => 'solid'],
        ['class' => 'fa-solid fa-wand-magic-sparkles', 'label' => 'Magic Wand / AI', 'style' => 'solid'],
        ['class' => 'fa-solid fa-calculator', 'label' => 'Calculator', 'style' => 'solid'],
        ['class' => 'fa-solid fa-language', 'label' => 'Language / Translate', 'style' => 'solid'],
        ['class' => 'fa-solid fa-coins', 'label' => 'Coins / Currency', 'style' => 'solid'],
        ['class' => 'fa-solid fa-money-bill-wave', 'label' => 'Money Bill', 'style' => 'solid'],
        ['class' => 'fa-solid fa-money-check-dollar', 'label' => 'Cheque / Amount', 'style' => 'solid'],
        ['class' => 'fa-solid fa-sack-dollar', 'label' => 'Money Sack', 'style' => 'solid'],
        ['class' => 'fa-solid fa-scale-balanced', 'label' => 'Balance / Converter', 'style' => 'solid'],
        ['class' => 'fa-solid fa-right-left', 'label' => 'Swap / Convert', 'style' => 'solid'],
        ['class' => 'fa-solid fa-arrows-rotate', 'label' => 'Sync / Format Convert', 'style' => 'solid'],
        ['class' => 'fa-solid fa-calendar-check', 'label' => 'Calendar Check / Age', 'style' => 'solid'],
        ['class' => 'fa-solid fa-calendar-days', 'label' => 'Calendar Days', 'style' => 'solid'],
        ['class' => 'fa-solid fa-cake-candles', 'label' => 'Birthday / Age', 'style' => 'solid'],
        ['class' => 'fa-solid fa-clock', 'label' => 'Clock / Time', 'style' => 'solid'],
        ['class' => 'fa-solid fa-stopwatch', 'label' => 'Stopwatch / Timer', 'style' => 'solid'],
        ['class' => 'fa-solid fa-image', 'label' => 'Image / Photo', 'style' => 'solid'],
        ['class' => 'fa-solid fa-images', 'label' => 'Multiple Images / Batch', 'style' => 'solid'],
        ['class' => 'fa-solid fa-camera', 'label' => 'Camera', 'style' => 'solid'],
        ['class' => 'fa-solid fa-crop-simple', 'label' => 'Crop / Resize', 'style' => 'solid'],
        ['class' => 'fa-solid fa-expand', 'label' => 'Expand / Upscale', 'style' => 'solid'],
        ['class' => 'fa-solid fa-compress', 'label' => 'Compress / Shrink', 'style' => 'solid'],
        ['class' => 'fa-solid fa-magnifying-glass-plus', 'label' => 'Zoom In / Upscaler', 'style' => 'solid'],
        ['class' => 'fa-solid fa-eye-dropper', 'label' => 'Eyedropper / Color Picker', 'style' => 'solid'],
        ['class' => 'fa-solid fa-palette', 'label' => 'Color Palette', 'style' => 'solid'],
        ['class' => 'fa-solid fa-eraser', 'label' => 'Eraser / BG Remover', 'style' => 'solid'],
        ['class' => 'fa-solid fa-user-tie', 'label' => 'Passport / Official Photo', 'style' => 'solid'],
        ['class' => 'fa-solid fa-id-card', 'label' => 'ID Card / Passport', 'style' => 'solid'],
        ['class' => 'fa-solid fa-id-badge', 'label' => 'ID Badge', 'style' => 'solid'],
        ['class' => 'fa-solid fa-eye', 'label' => 'Eye / Vision AI', 'style' => 'solid'],
        ['class' => 'fa-solid fa-closed-captioning', 'label' => 'Alt Text / Caption', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-pdf', 'label' => 'PDF File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-word', 'label' => 'Word File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-excel', 'label' => 'Excel File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-csv', 'label' => 'CSV File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-image', 'label' => 'Image File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-audio', 'label' => 'Audio / MP3 File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-video', 'label' => 'Video File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-music', 'label' => 'Music / Audio', 'style' => 'solid'],
        ['class' => 'fa-solid fa-headphones', 'label' => 'Headphones / Studio Audio', 'style' => 'solid'],
        ['class' => 'fa-solid fa-microphone-lines', 'label' => 'Microphone / Voice', 'style' => 'solid'],
        ['class' => 'fa-solid fa-volume-high', 'label' => 'Volume / Audio Boost', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-zipper', 'label' => 'ZIP / Archive File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-lines', 'label' => 'Document / Text File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-code', 'label' => 'Code / Meta File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-shield', 'label' => 'Protected / Encrypted File', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-signature', 'label' => 'File Signature', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-invoice', 'label' => 'Invoice', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-invoice-dollar', 'label' => 'Invoice Dollar', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-export', 'label' => 'File Export', 'style' => 'solid'],
        ['class' => 'fa-solid fa-file-import', 'label' => 'File Import', 'style' => 'solid'],
        ['class' => 'fa-solid fa-copy', 'label' => 'Copy / Merge Files', 'style' => 'solid'],
        ['class' => 'fa-solid fa-scissors', 'label' => 'Scissors / Split PDF', 'style' => 'solid'],
        ['class' => 'fa-solid fa-object-group', 'label' => 'Organize / Layout', 'style' => 'solid'],
        ['class' => 'fa-solid fa-layer-group', 'label' => 'Layers / Batch', 'style' => 'solid'],
        ['class' => 'fa-solid fa-lock', 'label' => 'Lock / Protect', 'style' => 'solid'],
        ['class' => 'fa-solid fa-unlock-keyhole', 'label' => 'Unlock', 'style' => 'solid'],
        ['class' => 'fa-solid fa-key', 'label' => 'Key / Password', 'style' => 'solid'],
        ['class' => 'fa-solid fa-shield-halved', 'label' => 'Shield / Security', 'style' => 'solid'],
        ['class' => 'fa-solid fa-qrcode', 'label' => 'QR Code', 'style' => 'solid'],
        ['class' => 'fa-solid fa-barcode', 'label' => 'Barcode / Scanner', 'style' => 'solid'],
        ['class' => 'fa-solid fa-receipt', 'label' => 'Receipt / Slip', 'style' => 'solid'],
        ['class' => 'fa-solid fa-briefcase', 'label' => 'Briefcase / Business', 'style' => 'solid'],
        ['class' => 'fa-solid fa-building-columns', 'label' => 'Bank / Finance', 'style' => 'solid'],
        ['class' => 'fa-solid fa-percent', 'label' => 'Percent / Discount / VAT', 'style' => 'solid'],
        ['class' => 'fa-solid fa-chart-line', 'label' => 'Chart Line / Profit', 'style' => 'solid'],
        ['class' => 'fa-solid fa-chart-pie', 'label' => 'Chart Pie / Analytics', 'style' => 'solid'],
        ['class' => 'fa-solid fa-piggy-bank', 'label' => 'Savings / DPS / FDR', 'style' => 'solid'],
        ['class' => 'fa-solid fa-ruler-combined', 'label' => 'Ruler / Land Area', 'style' => 'solid'],
        ['class' => 'fa-solid fa-map-location-dot', 'label' => 'Map / Land Measurement', 'style' => 'solid'],
        ['class' => 'fa-solid fa-font', 'label' => 'Font / Typography', 'style' => 'solid'],
        ['class' => 'fa-solid fa-bold', 'label' => 'Bold Text', 'style' => 'solid'],
        ['class' => 'fa-solid fa-italic', 'label' => 'Italic Text', 'style' => 'solid'],
        ['class' => 'fa-solid fa-text-height', 'label' => 'Text Size / Case', 'style' => 'solid'],
        ['class' => 'fa-solid fa-spell-check', 'label' => 'Spell Check / Word Count', 'style' => 'solid'],
        ['class' => 'fa-solid fa-paragraph', 'label' => 'Paragraph / Lorem Ipsum', 'style' => 'solid'],
        ['class' => 'fa-solid fa-align-left', 'label' => 'Text Align / Formatter', 'style' => 'solid'],
        ['class' => 'fa-solid fa-list-ol', 'label' => 'Numbered List / Counter', 'style' => 'solid'],
        ['class' => 'fa-solid fa-keyboard', 'label' => 'Keyboard / Typing Test', 'style' => 'solid'],
        ['class' => 'fa-solid fa-pen-nib', 'label' => 'Pen Nib / Signature', 'style' => 'solid'],
        ['class' => 'fa-solid fa-signature', 'label' => 'Signature Maker', 'style' => 'solid'],
        ['class' => 'fa-solid fa-pen-fancy', 'label' => 'Pen Fancy / Writer', 'style' => 'solid'],
        ['class' => 'fa-solid fa-code', 'label' => 'Code / Developer', 'style' => 'solid'],
        ['class' => 'fa-solid fa-terminal', 'label' => 'Terminal / Console', 'style' => 'solid'],
        ['class' => 'fa-solid fa-brackets-curly', 'label' => 'JSON / Braces', 'style' => 'solid'],
        ['class' => 'fa-solid fa-robot', 'label' => 'Robot / Robots.txt / AI', 'style' => 'solid'],
        ['class' => 'fa-solid fa-tags', 'label' => 'Meta Tags / SEO', 'style' => 'solid'],
        ['class' => 'fa-solid fa-globe', 'label' => 'Globe / Webmaster', 'style' => 'solid'],
        ['class' => 'fa-solid fa-link', 'label' => 'Link / UTM Builder', 'style' => 'solid'],
        ['class' => 'fa-solid fa-share-nodes', 'label' => 'Share / Social Link', 'style' => 'solid'],
        ['class' => 'fa-solid fa-bullhorn', 'label' => 'Campaign / Marketing', 'style' => 'solid'],
        ['class' => 'fa-solid fa-magnifying-glass', 'label' => 'Search / SEO Inspect', 'style' => 'solid'],
        ['class' => 'fa-solid fa-user-graduate', 'label' => 'CV / Resume / Education', 'style' => 'solid'],
        ['class' => 'fa-solid fa-print', 'label' => 'Print / Sheet', 'style' => 'solid'],
        ['class' => 'fa-solid fa-download', 'label' => 'Download', 'style' => 'solid'],
        ['class' => 'fa-solid fa-cloud-arrow-up', 'label' => 'Upload', 'style' => 'solid'],
        ['class' => 'fa-solid fa-bolt', 'label' => 'Fast / Instant Tool', 'style' => 'solid'],
        ['class' => 'fa-solid fa-gears', 'label' => 'Gears / Utility', 'style' => 'solid'],
        ['class' => 'fa-solid fa-sliders', 'label' => 'Sliders / Settings', 'style' => 'solid'],
        ['class' => 'fa-solid fa-table', 'label' => 'Table / Spreadsheet', 'style' => 'solid'],
        ['class' => 'fa-solid fa-filter', 'label' => 'Filter', 'style' => 'solid'],
        ['class' => 'fa-solid fa-fingerprint', 'label' => 'Fingerprint / Identity', 'style' => 'solid'],
        ['class' => 'fa-solid fa-truck-fast', 'label' => 'Courier / Delivery Slip', 'style' => 'solid'],
        ['class' => 'fa-solid fa-box-open', 'label' => 'Package / Order', 'style' => 'solid'],
        ['class' => 'fa-solid fa-wifi', 'label' => 'WiFi / Network', 'style' => 'solid'],
        ['class' => 'fa-solid fa-microchip', 'label' => 'AI / Processor', 'style' => 'solid'],

        // Regular
        ['class' => 'fa-regular fa-file-pdf', 'label' => 'PDF File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-word', 'label' => 'Word File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-excel', 'label' => 'Excel File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-image', 'label' => 'Image File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-lines', 'label' => 'Text File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-code', 'label' => 'Code File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-file-zipper', 'label' => 'Archive File (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-image', 'label' => 'Image (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-images', 'label' => 'Gallery (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-calendar-check', 'label' => 'Calendar Check (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-calendar-days', 'label' => 'Calendar (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-clock', 'label' => 'Clock (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-copy', 'label' => 'Copy (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-clipboard', 'label' => 'Clipboard (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-id-card', 'label' => 'ID Card (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-keyboard', 'label' => 'Keyboard (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-pen-to-square', 'label' => 'Edit / Write (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-eye', 'label' => 'Eye (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-lightbulb', 'label' => 'Lightbulb / Idea (Outline)', 'style' => 'regular'],
        ['class' => 'fa-regular fa-Credit-card', 'label' => 'Card (Outline)', 'style' => 'regular'],

        // Brands
        ['class' => 'fa-brands fa-whatsapp', 'label' => 'WhatsApp', 'style' => 'brands'],
        ['class' => 'fa-brands fa-facebook', 'label' => 'Facebook', 'style' => 'brands'],
        ['class' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'style' => 'brands'],
        ['class' => 'fa-brands fa-youtube', 'label' => 'YouTube', 'style' => 'brands'],
        ['class' => 'fa-brands fa-linkedin', 'label' => 'LinkedIn', 'style' => 'brands'],
        ['class' => 'fa-brands fa-x-twitter', 'label' => 'X / Twitter', 'style' => 'brands'],
        ['class' => 'fa-brands fa-tiktok', 'label' => 'TikTok', 'style' => 'brands'],
        ['class' => 'fa-brands fa-telegram', 'label' => 'Telegram', 'style' => 'brands'],
        ['class' => 'fa-brands fa-discord', 'label' => 'Discord', 'style' => 'brands'],
        ['class' => 'fa-brands fa-google', 'label' => 'Google', 'style' => 'brands'],
        ['class' => 'fa-brands fa-github', 'label' => 'GitHub', 'style' => 'brands'],
        ['class' => 'fa-brands fa-wordpress', 'label' => 'WordPress', 'style' => 'brands'],
        ['class' => 'fa-brands fa-html5', 'label' => 'HTML5', 'style' => 'brands'],
        ['class' => 'fa-brands fa-css3-alt', 'label' => 'CSS3', 'style' => 'brands'],
        ['class' => 'fa-brands fa-js', 'label' => 'JavaScript', 'style' => 'brands'],
        ['class' => 'fa-brands fa-markdown', 'label' => 'Markdown', 'style' => 'brands'],
    ];
}

function toolnest_admin_enqueue_tool_icon_assets($hook)
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'tool' !== $screen->post_type) {
        return;
    }

    wp_enqueue_style(
        'toolnest-admin-fontawesome',
        'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css',
        [],
        '6.5.2'
    );
    wp_enqueue_style(
        'toolnest-admin-bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        [],
        '1.11.3'
    );
}
add_action('admin_enqueue_scripts', 'toolnest_admin_enqueue_tool_icon_assets');

function toolnest_add_tool_icon_meta_box()
{
    add_meta_box(
        'toolnest_tool_icon_metabox',
        __('Tool Icon (Font Awesome)', 'toolnest'),
        'toolnest_render_tool_icon_meta_box',
        'tool',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'toolnest_add_tool_icon_meta_box');

function toolnest_render_tool_icon_meta_box($post)
{
    wp_nonce_field('toolnest_save_tool_icon', 'toolnest_tool_icon_nonce');

    $saved_icon = get_post_meta($post->ID, '_toolnest_tool_icon', true);
    $resolved_icon = $saved_icon ? toolnest_get_tool_icon_class($saved_icon) : '';
    $icons = toolnest_get_fontawesome_icons();
    ?>
    <style>
      .tn-admin-icon-box {
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      .tn-admin-icon-preview-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
      }
      .tn-admin-icon-preview {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #eff6ff;
        color: #2563eb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
        border: 1px solid #bfdbfe;
      }
      .tn-admin-icon-meta {
        min-width: 0;
        flex: 1;
      }
      .tn-admin-icon-meta strong {
        display: block;
        font-size: 12px;
        color: #0f172a;
        margin-bottom: 2px;
      }
      .tn-admin-icon-code {
        display: block;
        font-family: monospace;
        font-size: 11px;
        color: #475569;
        word-break: break-all;
      }
      .tn-admin-icon-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
      }
      .tn-admin-icon-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      /* Visual Icon Picker Modal */
      .tn-icon-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(2px);
        z-index: 100000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
      }
      .tn-icon-modal-backdrop.is-open {
        display: flex;
      }
      .tn-icon-modal {
        background: #ffffff;
        width: min(760px, 100%);
        max-height: 84vh;
        border-radius: 12px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.25);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        animation: tnIconModalIn 0.18s ease-out;
      }
      @keyframes tnIconModalIn {
        from {
          opacity: 0;
          transform: translateY(10px) scale(0.98);
        }
        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }
      .tn-icon-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
      }
      .tn-icon-modal__title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .tn-icon-modal__title i {
        color: #2563eb;
      }
      .tn-icon-modal__close {
        background: transparent;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        width: 32px;
        height: 32px;
        cursor: pointer;
        color: #475569;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
      }
      .tn-icon-modal__close:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fca5a5;
      }
      .tn-icon-modal__toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
        align-items: center;
      }
      .tn-icon-modal__search {
        flex: 1 1 240px;
        min-height: 36px;
        padding: 6px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 13px;
      }
      .tn-icon-modal__tabs {
        display: flex;
        gap: 6px;
      }
      .tn-icon-modal__tab {
        padding: 6px 12px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
      }
      .tn-icon-modal__tab.is-active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
      }
      .tn-icon-modal__body {
        padding: 18px 20px;
        overflow-y: auto;
        flex: 1;
      }
      .tn-icon-modal__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
        gap: 10px;
      }
      .tn-icon-modal__item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #ffffff;
        cursor: pointer;
        text-align: center;
        transition: all 0.15s ease;
      }
      .tn-icon-modal__item i {
        font-size: 22px;
        color: #1e293b;
        transition: transform 0.15s ease, color 0.15s ease;
      }
      .tn-icon-modal__item span {
        font-size: 11px;
        color: #475569;
        line-height: 1.25;
        word-break: break-word;
      }
      .tn-icon-modal__item:hover {
        border-color: #2563eb;
        background: #eff6ff;
        transform: translateY(-2px);
      }
      .tn-icon-modal__item:hover i {
        color: #2563eb;
        transform: scale(1.12);
      }
      .tn-icon-modal__item.is-selected {
        border-color: #2563eb;
        background: #dbeafe;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
      }
      .tn-icon-modal__item.is-selected i {
        color: #1d4ed8;
      }
      .tn-icon-modal__empty {
        text-align: center;
        padding: 32px 16px;
        color: #64748b;
        display: none;
      }
    </style>

    <div class="tn-admin-icon-box">
      <div class="tn-admin-icon-preview-wrap">
        <div class="tn-admin-icon-preview" id="tn-admin-icon-preview">
          <?php if ($resolved_icon) : ?>
            <i class="<?php echo esc_attr($resolved_icon); ?>" aria-hidden="true"></i>
          <?php else : ?>
            <i class="fa-solid fa-screwdriver-wrench" style="opacity:0.35;" aria-hidden="true"></i>
          <?php endif; ?>
        </div>
        <div class="tn-admin-icon-meta">
          <strong><?php esc_html_e('Selected Icon', 'toolnest'); ?></strong>
          <span class="tn-admin-icon-code" id="tn-admin-icon-code">
            <?php echo $saved_icon ? esc_html($saved_icon) : esc_html__('None selected (using default)', 'toolnest'); ?>
          </span>
        </div>
      </div>

      <input
        type="text"
        name="toolnest_tool_icon"
        id="tn-tool-icon-input"
        class="widefat"
        value="<?php echo esc_attr($saved_icon); ?>"
        placeholder="e.g. fa-solid fa-calculator">

      <div class="tn-admin-icon-actions">
        <button type="button" class="button button-primary" id="tn-open-icon-picker-btn">
          <i class="fa-solid fa-icons" aria-hidden="true"></i>
          <?php esc_html_e('Select Icon', 'toolnest'); ?>
        </button>
        <button
          type="button"
          class="button"
          id="tn-clear-icon-picker-btn"
          <?php echo $saved_icon ? '' : 'style="display:none;"'; ?>>
          <?php esc_html_e('Clear', 'toolnest'); ?>
        </button>
      </div>
    </div>

    <!-- Visual Font Awesome Icon Picker Panel -->
    <div class="tn-icon-modal-backdrop" id="tn-icon-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="tn-icon-modal-title">
      <div class="tn-icon-modal">
        <div class="tn-icon-modal__header">
          <h2 class="tn-icon-modal__title" id="tn-icon-modal-title">
            <i class="fa-solid fa-icons" aria-hidden="true"></i>
            <?php esc_html_e('Select Tool Icon (Font Awesome)', 'toolnest'); ?>
          </h2>
          <button type="button" class="tn-icon-modal__close" id="tn-close-icon-picker-btn" aria-label="<?php esc_attr_e('Close icon panel', 'toolnest'); ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>

        <div class="tn-icon-modal__toolbar">
          <input
            type="search"
            id="tn-icon-picker-search"
            class="tn-icon-modal__search"
            placeholder="<?php esc_attr_e('Search icons (e.g. pdf, image, calculator, qr, lock, whatsapp)...', 'toolnest'); ?>">
          <div class="tn-icon-modal__tabs" role="tablist">
            <button type="button" class="tn-icon-modal__tab is-active" data-tn-icon-style="all"><?php esc_html_e('All', 'toolnest'); ?></button>
            <button type="button" class="tn-icon-modal__tab" data-tn-icon-style="solid"><?php esc_html_e('Solid', 'toolnest'); ?></button>
            <button type="button" class="tn-icon-modal__tab" data-tn-icon-style="regular"><?php esc_html_e('Regular', 'toolnest'); ?></button>
            <button type="button" class="tn-icon-modal__tab" data-tn-icon-style="brands"><?php esc_html_e('Brands', 'toolnest'); ?></button>
          </div>
        </div>

        <div class="tn-icon-modal__body">
          <div class="tn-icon-modal__grid" id="tn-icon-picker-grid">
            <?php foreach ($icons as $icon_item) :
                $icon_class = strtolower($icon_item['class']);
                $is_selected = ($saved_icon === $icon_class);
                ?>
              <button
                type="button"
                class="tn-icon-modal__item<?php echo $is_selected ? ' is-selected' : ''; ?>"
                data-icon-class="<?php echo esc_attr($icon_class); ?>"
                data-icon-label="<?php echo esc_attr(strtolower($icon_item['label'] . ' ' . $icon_class)); ?>"
                data-icon-style="<?php echo esc_attr($icon_item['style']); ?>"
                title="<?php echo esc_attr($icon_item['label'] . ' (' . $icon_class . ')'); ?>">
                <i class="<?php echo esc_attr($icon_class); ?>" aria-hidden="true"></i>
                <span><?php echo esc_html($icon_item['label']); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="tn-icon-modal__empty" id="tn-icon-picker-empty">
            <?php esc_html_e('No matching Font Awesome icons found. Try another keyword.', 'toolnest'); ?>
          </div>
        </div>
      </div>
    </div>

    <script>
      (function () {
        const input = document.getElementById('tn-tool-icon-input');
        const preview = document.getElementById('tn-admin-icon-preview');
        const codeLabel = document.getElementById('tn-admin-icon-code');
        const openBtn = document.getElementById('tn-open-icon-picker-btn');
        const closeBtn = document.getElementById('tn-close-icon-picker-btn');
        const clearBtn = document.getElementById('tn-clear-icon-picker-btn');
        const backdrop = document.getElementById('tn-icon-modal-backdrop');
        const searchInput = document.getElementById('tn-icon-picker-search');
        const styleTabs = document.querySelectorAll('[data-tn-icon-style]');
        const items = document.querySelectorAll('.tn-icon-modal__item');
        const emptyMsg = document.getElementById('tn-icon-picker-empty');

        if (!input || !openBtn || !backdrop) return;

        // Move modal backdrop to document.body so it isn't clipped by sidebar containers
        document.body.appendChild(backdrop);

        let activeStyle = 'all';

        function resolvePreviewClass(val) {
          const v = (val || '').trim();
          if (!v) return '';
          if (/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b/.test(v)) return v;
          if (v.indexOf('fa-') === 0) return 'fa-solid ' + v;
          if (v.indexOf('bi-') === 0) return 'bi ' + v;
          return v;
        }

        function updateUI(val) {
          const clean = (val || '').trim();
          input.value = clean;
          const resolved = resolvePreviewClass(clean);
          if (resolved) {
            preview.innerHTML = '<i class="' + resolved.replace(/"/g, '') + '" aria-hidden="true"></i>';
            codeLabel.textContent = clean;
            clearBtn.style.display = '';
          } else {
            preview.innerHTML = '<i class="fa-solid fa-screwdriver-wrench" style="opacity:0.35;" aria-hidden="true"></i>';
            codeLabel.textContent = 'None selected (using default)';
            clearBtn.style.display = 'none';
          }

          items.forEach(function (item) {
            item.classList.toggle('is-selected', item.getAttribute('data-icon-class') === clean);
          });
        }

        function filterIcons() {
          const q = (searchInput.value || '').trim().toLowerCase();
          let visible = 0;
          items.forEach(function (item) {
            const label = item.getAttribute('data-icon-label') || '';
            const style = item.getAttribute('data-icon-style') || '';
            const matchStyle = activeStyle === 'all' || style === activeStyle;
            const matchQuery = !q || label.indexOf(q) !== -1;
            if (matchStyle && matchQuery) {
              item.style.display = '';
              visible++;
            } else {
              item.style.display = 'none';
            }
          });
          if (emptyMsg) {
            emptyMsg.style.display = visible === 0 ? 'block' : 'none';
          }
        }

        function openModal() {
          backdrop.classList.add('is-open');
          setTimeout(function () {
            if (searchInput) searchInput.focus();
          }, 50);
        }

        function closeModal() {
          backdrop.classList.remove('is-open');
          openBtn.focus();
        }

        openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        backdrop.addEventListener('click', function (e) {
          if (e.target === backdrop) {
            closeModal();
          }
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
            closeModal();
          }
        });

        if (clearBtn) {
          clearBtn.addEventListener('click', function () {
            updateUI('');
          });
        }

        input.addEventListener('input', function () {
          updateUI(input.value);
        });

        if (searchInput) {
          searchInput.addEventListener('input', filterIcons);
        }

        styleTabs.forEach(function (tab) {
          tab.addEventListener('click', function () {
            styleTabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            activeStyle = tab.getAttribute('data-tn-icon-style') || 'all';
            filterIcons();
          });
        });

        items.forEach(function (item) {
          item.addEventListener('click', function () {
            const selectedClass = item.getAttribute('data-icon-class') || '';
            updateUI(selectedClass);
            closeModal();
          });
        });
      })();
    </script>
    <?php
}

function toolnest_save_tool_icon_meta($post_id)
{
    if (!isset($_POST['toolnest_tool_icon_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['toolnest_tool_icon_nonce'])), 'toolnest_save_tool_icon')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $icon = isset($_POST['toolnest_tool_icon'])
        ? sanitize_text_field(wp_unslash($_POST['toolnest_tool_icon']))
        : '';

    if ('' !== $icon) {
        update_post_meta($post_id, '_toolnest_tool_icon', $icon);
    } else {
        delete_post_meta($post_id, '_toolnest_tool_icon');
    }
}
add_action('save_post_tool', 'toolnest_save_tool_icon_meta');

