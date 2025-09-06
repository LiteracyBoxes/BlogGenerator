<?php

/*
Plugin Name: Blog Generator
Plugin URI: https://github.com/LiteracyBoxes/BlogGenerator
GitHub Plugin URI: https://github.com/LiteracyBoxes/BlogGenerator
GitHub Branch: main
Description: ブログ用のカスタム関数をまとめたプラグイン
Version: 1.2.13
Author: ken
--- ChangeLog ---
- テスト更新
*/


if (!defined('ABSPATH')) exit;


// デフォルト設定
function gh_updater_default_settings() {
    return [
        'plugin_file'   => 'bloggenerator/bloggenerator.php',
        'github_user'   => 'LiteracyBoxes',
        'github_repo'   => 'BlogGenerator',
        'zip_name'      => 'bloggenerator.zip',
        'check_interval'=> 60, // 分単位
        'github_token'  => '', // GitHub Personal Access Token
    ];
}

// ログ保存関数
function gh_updater_log($message) {
    $logs = get_option('gh_updater_logs', []);
    $logs[] = '[' . current_time('mysql') . '] ' . $message;
    if (count($logs) > 50) $logs = array_slice($logs, -50);
    update_option('gh_updater_logs', $logs);
}

// プラグイン有効化時に初期設定とCRON登録
register_activation_hook(__FILE__, function() {
    if (!get_option('gh_updater_settings')) {
        update_option('gh_updater_settings', gh_updater_default_settings());
    }
    if (!get_option('gh_updater_logs')) {
        update_option('gh_updater_logs', []);
    }
    gh_updater_reschedule_cron();
});

// 無効化時にCRON解除
register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('gh_updater_cron_hook');
    if ($timestamp) wp_unschedule_event($timestamp, 'gh_updater_cron_hook');
});

// CRONスケジュールのカスタム登録
add_filter('cron_schedules', function($schedules) {
    $settings = get_option('gh_updater_settings', gh_updater_default_settings());
    $interval_minutes = max(1, intval($settings['check_interval']));
    $schedules['gh_updater_custom'] = [
        'interval' => $interval_minutes * MINUTE_IN_SECONDS,
        'display'  => "{$interval_minutes}分ごと"
    ];
    return $schedules;
});

// CRON再スケジュール
function gh_updater_reschedule_cron() {
    $timestamp = wp_next_scheduled('gh_updater_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'gh_updater_cron_hook');
    }
    wp_schedule_event(time(), 'gh_updater_custom', 'gh_updater_cron_hook');
}

// 管理画面メニュー追加
add_action('admin_menu', function() {
    add_options_page(
        'GitHub Updater Settings',
        'GitHub Updater',
        'manage_options',
        'gh-updater',
        'gh_updater_settings_page'
    );
});

// 管理画面表示
function gh_updater_settings_page() {
    if (!current_user_can('manage_options')) return;

    $settings = get_option('gh_updater_settings', gh_updater_default_settings());

    if (isset($_POST['gh_updater_save'])) {
        check_admin_referer('gh_updater_save_settings');

        $settings = [
            'plugin_file'    => sanitize_text_field($_POST['plugin_file']),
            'github_user'    => sanitize_text_field($_POST['github_user']),
            'github_repo'    => sanitize_text_field($_POST['github_repo']),
            'zip_name'       => sanitize_text_field($_POST['zip_name']),
            'check_interval' => max(1, intval($_POST['check_interval'])),
            'github_token'   => sanitize_text_field($_POST['github_token']),
        ];

        update_option('gh_updater_settings', $settings);
        gh_updater_reschedule_cron();

        echo '<div class="updated"><p>設定を保存しました。</p></div>';
    }

    $preview_url = "https://github.com/{$settings['github_user']}/{$settings['github_repo']}/releases/download/latest/{$settings['zip_name']}";
    $logs = get_option('gh_updater_logs', []);
    ?>
    <div class="wrap">
        <h1>GitHub Updater 設定</h1>
        <form method="post">
            <?php wp_nonce_field('gh_updater_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>GitHub Personal Access Token</th>
                    <td>
                        <input type="text" name="github_token" value="<?php echo esc_attr($settings['github_token']); ?>" size="50">
                        <p class="description">※非公開リポジトリの場合は入力。公開リポジトリでも動作しますが、1時間に60回以上のAPIリクエストはアクセス禁止になります。</p>
                    </td>
                </tr>
                <tr>
                    <th>プラグインファイルパス</th>
                    <td><input type="text" name="plugin_file" value="<?php echo esc_attr($settings['plugin_file']); ?>" size="50"></td>
                </tr>
                <tr>
                    <th>GitHub ユーザー名</th>
                    <td><input type="text" name="github_user" value="<?php echo esc_attr($settings['github_user']); ?>"></td>
                </tr>
                <tr>
                    <th>GitHub リポジトリ名</th>
                    <td><input type="text" name="github_repo" value="<?php echo esc_attr($settings['github_repo']); ?>"></td>
                </tr>
                <tr>
                    <th>ZIPファイル名</th>
                    <td><input type="text" name="zip_name" value="<?php echo esc_attr($settings['zip_name']); ?>"></td>
                </tr>
                <tr>
                    <th>チェック間隔（分）</th>
                    <td><input type="number" name="check_interval" value="<?php echo esc_attr($settings['check_interval']); ?>" min="1"></td>
                </tr>
                <tr>
                    <th>ダウンロードURLプレビュー</th>
                    <td>
                        <code><?php echo esc_html($preview_url); ?></code>
                        <p class="description">※最新版のタグ名を <code>latest</code> と仮定して生成したURLです。</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="gh_updater_save" class="button-primary" value="保存"></p>
        </form>

        <h2>更新ログ</h2>
        <div style="background:#f9f9f9; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;">
            <?php
            if(empty($logs)){
                echo '<p>まだログはありません。</p>';
            } else {
                echo '<ul>';
                foreach(array_reverse($logs) as $log){
                    echo '<li>'.esc_html($log).'</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>
    </div>
    <?php
}

// ----------------------------------------------------
// 更新チェックと自動アップデートのための主要なフック
// ----------------------------------------------------

add_action('gh_updater_cron_hook', 'gh_updater_check_and_update');

/**
 * GitHub APIをチェックし、必要であれば強制的にプラグインを更新する
 */
function gh_updater_check_and_update() {
    $settings = get_option('gh_updater_settings', gh_updater_default_settings());
    $api_url = "https://api.github.com/repos/{$settings['github_user']}/{$settings['github_repo']}/releases/latest";
    gh_updater_log("GitHub APIチェック: $api_url");

    $headers = ['User-Agent' => 'WordPress'];
    if (!empty($settings['github_token'])) {
        $headers['Authorization'] = 'token ' . $settings['github_token'];
    }

    $response = wp_remote_get($api_url, ['headers' => $headers]);


    if (is_wp_error($response)) {
        gh_updater_log('GitHub API エラー: ' . $response->get_error_message());
        return;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        gh_updater_log('GitHub API レスポンスコード異常: ' . wp_remote_retrieve_response_code($response));
        return;
    }

    $release_info = json_decode(wp_remote_retrieve_body($response));
    if (!$release_info || empty($release_info->tag_name)) {
        gh_updater_log('GitHub API レスポンスにtag_nameがありません');
        return;
    }

    $latest_version_raw = $release_info->tag_name;
    // バージョン番号から 'v' などのプレフィックスを削除
    $latest_version = ltrim($latest_version_raw, 'vV');

    $plugin_path = WP_PLUGIN_DIR . '/' . $settings['plugin_file'];
    if (!file_exists($plugin_path)) {
        gh_updater_log('プラグインファイルが存在しません: ' . $plugin_path);
        return;
    }

    $plugin_data = get_plugin_data($plugin_path);
    $current_version = $plugin_data['Version'];
    gh_updater_log("現在バージョン: $current_version");

    if (version_compare($latest_version, $current_version, '>')) {
        gh_updater_log("更新開始: 現在バージョン={$current_version}, 最新={$latest_version}");
        $download_url = "https://github.com/{$settings['github_user']}/{$settings['github_repo']}/releases/download/{$latest_version_raw}/{$settings['zip_name']}";
        gh_updater_log("ダウンロードURL: $download_url");
        gh_updater_force_update($settings['plugin_file'], $download_url);
    } else {
        gh_updater_log("更新不要: 現在バージョン={$current_version}, 最新={$latest_version}");
    }
}

/**
 * 強制的にプラグインを更新する関数
 *
 * @param string $plugin_file プラグインファイルパス
 * @param string $download_url ダウンロードURL
 */
function gh_updater_force_update($plugin_file, $download_url) {
    if (!class_exists('WP_Upgrader_Skin')) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }
    if (!function_exists('download_url')) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    // プラグインが有効化されているか確認し、一時的に無効化
    $active_plugins = get_option('active_plugins');
    $is_active = in_array($plugin_file, $active_plugins);
    if ($is_active) {
        deactivate_plugins($plugin_file);
        gh_updater_log('プラグインを一時的に無効化しました。');
    }

    gh_updater_log("ZIPダウンロード開始");
    $tmp_file = download_url($download_url);

    if (is_wp_error($tmp_file)) {
        gh_updater_log('ZIPダウンロード失敗: ' . $tmp_file->get_error_message());
        return;
    }
    gh_updater_log("ZIPダウンロード完了: $tmp_file");

    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    
    gh_updater_log("展開・アップグレード開始");
    $result = $upgrader->run(['package' => $tmp_file, 'destination' => WP_PLUGIN_DIR, 'clear_destination' => true, 'clear_working' => true, 'hook_extra' => ['plugin' => $plugin_file]]);

    if (is_wp_error($result)) {
        gh_updater_log('更新失敗: ' . $result->get_error_message());
    } else {
        gh_updater_log('更新完了');
    }

    // プラグインを再度有効化
    if ($is_active) {
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            gh_updater_log('プラグインの再有効化に失敗しました: ' . $result->get_error_message());
        } else {
            gh_updater_log('プラグインを再有効化しました。');
        }
    }

    @unlink($tmp_file); // 一時ファイル削除
}


function custom_external_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr) {
    $external_url = get_post_meta($post_id, 'external_thumbnail', true);
    if ($external_url) {
      $alt = esc_attr(get_the_title($post_id));
      return '<img src="' . esc_url($external_url) . '" alt="' . $alt . '" />';
    }
    return $html;
}
add_filter('post_thumbnail_html', 'custom_external_featured_image', 10, 5);

function custom_has_post_thumbnail($has_thumbnail, $post) {
    if (is_object($post) && get_post_meta($post->ID, 'external_thumbnail', true)) {
        return true;
    }
    return $has_thumbnail;
}
add_filter('has_post_thumbnail', 'custom_has_post_thumbnail', 10, 2);

// 外部画像がある場合、ダミーのサムネイルIDを返すようにする
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single) {
    if ($meta_key === '_thumbnail_id') {
        $external_url = get_metadata('post', $object_id, 'external_thumbnail', true);
        if ($external_url) {
            // CocoonがサムネイルIDとして使えるよう、仮の数値を返す（例：999999999）
            return $single ? ($object_id + 1000000000) : [$object_id];
        }
    }
    return $value;
}, 10, 4);

add_filter('get_singular_sns_share_image_url', function ($url) {
    if (is_singular()) {
        $external_url = get_post_meta(get_the_ID(), 'external_thumbnail', true);
        if ($external_url) {
            return esc_url($external_url);
        }
    }
    return $url;
});

function my_custom_twitter_share_url() {
  // 管理者のみ適用
  if (!is_user_logged_in() || !current_user_can('administrator')) {
    $title = get_the_title();
    $url = get_permalink();
	return 'https://x.com/intent/tweet?text=' . $title . '&url=' . rawurlencode($url);
  }

  $post_id = get_the_ID();
  $url = get_permalink();
  $suffix = '...  続きはこちら ▶'; // ← 全角13文字
  $text = '';

  // 1. カスタムフィールド優先（そのまま使う）
  $custom_text = get_post_meta($post_id, 'x_share_text', true);
  if (!empty($custom_text)) {
    $text = $custom_text . ' ▶';

  // 2. meta description がある場合は 128文字＋suffix
  } elseif ($meta_desc = get_post_meta($post_id, 'the_page_meta_description', true)) {
    $text = mb_substr($meta_desc, 0, 116) . $suffix;

  // 3. fallback：抜粋＋タイトル（タイトルは含めない指定のため省略）
  } else {
    $excerpt = get_the_excerpt();
    $text = mb_substr($excerpt, 0, 128) . $suffix;
  }

  // URL付きのX（Twitter）シェアリンクを生成
  $tweet_text = rawurlencode($text);
  return 'https://x.com/intent/tweet?text=' . $tweet_text . '&url=' . rawurlencode($url);
}

// 上書き処理（テーマ側のget_twitter_share_urlを置換）
if (!function_exists('get_twitter_share_url')) {
  function get_twitter_share_url() {
    return my_custom_twitter_share_url();
  }
}

// ショートコード：[box_Girl][/box_Girl]
function box_Girl_func( $atts, $content = null ) {
  $domain = $_SERVER['HTTP_HOST'];
  // $icon_url = "https://{$domain}/wp-content/uploads/girl-150x150.jpg";
  $icon_url = "https://{$domain}/wp-content/uploads/girl.jpg";

  $box_Girl = '<!-- wp:cocoon-blocks/balloon-ex-box-1 {"index":"8","id":"9","icon":"' . $icon_url . '","style":"think","position":"r","iconstyle":"sn","iconid":71,"textColor":"key-color","borderColor":"key-color","textColorValue":"#3c3c3c","borderColorValue":"#3c3c3c"} -->
  <div class="wp-block-cocoon-blocks-balloon-ex-box-1 speech-wrap sb-id-9 sbs-think sbp-r sbis-sn cf block-box not-nested-style cocoon-block-balloon" style="--cocoon-custom-text-color:#3c3c3c;--cocoon-custom-border-color:#3c3c3c">
    <div class="speech-person">
      <figure class="speech-icon">
        <img src="' . $icon_url . '" alt="" class="speech-icon-image"/>
      </figure>
      <div class="speech-name"></div>
    </div>
    <div class="speech-balloon has-text-color has-border-color has-key-color-color has-key-color-border-color">
      <p>' . $content . '</p>
    </div>
  </div>
  <!-- /wp:cocoon-blocks/balloon-ex-box-1 -->';

  return $box_Girl;
}
add_shortcode('box_Girl', 'box_Girl_func');

// ショートコード：[box_Boy][/box_Boy]
function box_Boy_func( $atts, $content = null ) {
  $domain = $_SERVER['HTTP_HOST'];
  // $icon_url = "https://{$domain}/wp-content/uploads/boy-150x150.jpg";
  $icon_url = "https://{$domain}/wp-content/uploads/boy.jpg";

  $box_Boy = '<!-- wp:cocoon-blocks/balloon-ex-box-1 {"index":"8","id":"9","icon":"' . $icon_url . '","style":"think","position":"r","iconstyle":"sn","iconid":71,"textColor":"key-color","borderColor":"key-color","textColorValue":"#3c3c3c","borderColorValue":"#3c3c3c"} -->
  <div class="wp-block-cocoon-blocks-balloon-ex-box-1 speech-wrap sb-id-9 sbs-think sbp-r sbis-sn cf block-box not-nested-style cocoon-block-balloon" style="--cocoon-custom-text-color:#3c3c3c;--cocoon-custom-border-color:#3c3c3c">
    <div class="speech-person">
      <figure class="speech-icon">
        <img src="' . $icon_url . '" alt="" class="speech-icon-image"/>
      </figure>
      <div class="speech-name"></div>
    </div>
    <div class="speech-balloon has-text-color has-border-color has-key-color-color has-key-color-border-color">
      <p>' . $content . '</p>
    </div>
  </div>
  <!-- /wp:cocoon-blocks/balloon-ex-box-1 -->';

  return $box_Boy;
}
add_shortcode('box_Boy', 'box_Boy_func');

// ショートコード：[box_Lead][/box_Lead]
function box_Lead_func( $atts, $content = null ) {
  $box_Lead = '<!-- wp:cocoon-blocks/tab-box-1 {"label":"bb-point","backgroundColor":"watery-yellow","borderColor":"orange"} --><div class="box_Lead wp-block-cocoon-blocks-tab-box-1 blank-box bb-tab bb-point block-box has-background has-border-color has-watery-yellow-background-color has-orange-border-color">' . $content . '</div><!-- /wp:cocoon-blocks/tab-box-1 -->';
  return $box_Lead;
}
add_shortcode('box_Lead', 'box_Lead_func');

// ショートコード：[box_Section][/box_Section]
function box_Section_func( $atts, $content = null ) {
  $box_Section = '<!-- wp:cocoon-blocks/tab-box-1 {"label":"bb-point","backgroundColor":"watery-yellow","borderColor":"orange"} --><div class="box_Section wp-block-cocoon-blocks-tab-box-1 blank-box bb-tab bb-point block-box has-background has-border-color has-watery-yellow-background-color has-orange-border-color">' . $content . '</div><!-- /wp:cocoon-blocks/tab-box-1 -->';
  return $box_Section;
}
add_shortcode('box_Section', 'box_Section_func');

// ショートコード：[box_Check][/box_Check]
function box_Check_func( $atts, $content = null ) {
  $box_Check = '<!-- wp:cocoon-blocks/tab-box-1 {"backgroundColor":"watery-yellow","borderColor":"amber"} --><div class="box_Check wp-block-cocoon-blocks-tab-box-1 blank-box bb-tab bb-check block-box has-background has-border-color has-watery-yellow-background-color has-amber-border-color">' . $content . '</div><!-- /wp:cocoon-blocks/tab-box-1 -->';
  return $box_Check;
}
add_shortcode('box_Check', 'box_Check_func');

// ショートコード：[box_Chui][/box_Chui]
function box_Chui_func( $atts, $content = null ) {
  $box_Chui = '<!-- wp:cocoon-blocks/tab-box-1 {"backgroundColor":"watery-yellow","borderColor":"amber"} --><div class="box_Chui wp-block-cocoon-blocks-tab-box-1 blank-box bb-tab bb-check block-box has-background has-border-color has-watery-yellow-background-color has-amber-border-color">' . $content . '</div><!-- /wp:cocoon-blocks/tab-box-1 -->';
  return $box_Chui;
}
add_shortcode('box_Chui', 'box_Chui_func');

// ショートコード：[font_Bold][/font_Bold]
function font_Bold_func( $atts, $content = null ) {
  $font_Bold = '<b>' . $content . '</b>';
  return $font_Bold;
}
add_shortcode('font_Bold', 'font_Bold_func');

add_filter('the_content', function ($text){ //the_contentは投稿の本文

	if(
		is_single() //投稿ページのみ
		&& !is_admin() //管理画面は出さない
		&& $text != null //テキストが空でない
	){
		$text = do_shortcode($text); //テキスト中のショートコードを展開
	}
	return $text;

}, 100); // 11よりも大きければ本文のショートコード展開の後に実行される

// 🔗 日本語と英語の投稿を Polylang で紐づける関数
function link_translations($post_id_ja, $post_id_en) {
    if (function_exists('pll_save_post_translations')) {
        pll_save_post_translations([
            'ja' => (int)$post_id_ja,
            'en' => (int)$post_id_en,
        ]);
    }
}

// 🌐 Polylang用の翻訳紐づけエンドポイントを追加
function register_translation_link_endpoint() {
    register_rest_route('custom/v1', '/link-translations', [
        'methods' => 'POST',
        'callback' => 'custom_link_translations',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('rest_api_init', 'register_translation_link_endpoint');

// 📦 REST API の実処理
function custom_link_translations($request) {
    $post_id_ja = (int) $request->get_param('ja');
    $post_id_en = (int) $request->get_param('en');

    if (!$post_id_ja || !$post_id_en) {
        return new WP_Error('missing', '投稿IDが足りません', ['status' => 400]);
    }

    link_translations($post_id_ja, $post_id_en);

    return [
        'status' => 'success',
        'linked' => [
            'ja' => $post_id_ja,
            'en' => $post_id_en,
        ]
    ];
}

add_action('rest_insert_post', function ($post, $request, $creating) {
    if (function_exists('pll_get_post_language') && pll_get_post_language($post->ID) === 'en') {
        // 翻訳されたカテゴリを取得・置換
        $categories = wp_get_post_categories($post->ID);
        $translated = array_map(fn($id) => pll_get_term($id, 'en'), $categories);
        wp_set_post_categories($post->ID, $translated);

        // タグも同様に置き換える
        $tags = wp_get_post_tags($post->ID, ['fields' => 'ids']);
        $translated_tags = array_map(fn($id) => pll_get_term($id, 'en'), $tags);
        wp_set_post_tags($post->ID, $translated_tags);
    }
}, 10, 3);

add_action('init', function () {
    // --- 共通系 ---
    register_post_meta('post', 'the_page_meta_description', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    register_post_meta('post', 'the_page_meta_keywords', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    register_post_meta('post', 'the_page_memo', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    register_post_meta('post', 'x_share_text', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);

    // --- 日本語SNSシェア用 ---
    for ($i = 0; $i <= 4; $i++) {
        register_post_meta('post', "x_share_ja{$i}", ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    }

    // --- 英語SNSシェア用 ---
    for ($i = 0; $i <= 4; $i++) {
        register_post_meta('post', "x_share_en{$i}", ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    }

    // --- 英語メタディスクリプション ---
    register_post_meta('post', 'the_page_meta_description_en', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);

    // --- 外部アイキャッチ画像URL ---
    register_post_meta('post', 'external_thumbnail', [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
});

// 🌐 Polylang ターム（カテゴリ・タグ）翻訳リンクエンドポイント
function register_term_translation_link_endpoint() {
    register_rest_route('custom/v1', '/link-terms', [
        'methods' => 'POST',
        'callback' => 'custom_link_term_translations',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('rest_api_init', 'register_term_translation_link_endpoint');

// 📦 ターム用 REST API の実処理
function custom_link_term_translations($request) {
    $ja_id = (int) $request->get_param('ja');
    $en_id = (int) $request->get_param('en');
    $taxonomy = sanitize_text_field($request->get_param('taxonomy'));

    if (!$ja_id || !$en_id || !$taxonomy) {
        return new WP_Error('missing', '必要なパラメータ（ja, en, taxonomy）が足りません', ['status' => 400]);
    }

    if (!function_exists('pll_set_term_language') || !function_exists('pll_save_term_translations')) {
        return new WP_Error('missing_function', 'Polylangの関数が見つかりません', ['status' => 500]);
    }

    // 🔥 言語を明示的にセットしてからリンクする
    pll_set_term_language($ja_id, 'ja');
    pll_set_term_language($en_id, 'en');

    // 🔥 そしてリンク保存
    pll_save_term_translations([
        'ja' => $ja_id,
        'en' => $en_id,
    ]);

    return [
        'status' => 'success',
        'linked' => [
            'taxonomy' => $taxonomy,
            'ja' => $ja_id,
            'en' => $en_id,
        ]
    ];
}

// ターム翻訳ID取得API（Pythonからアクセス用）
function register_term_translation_query_endpoint() {
    register_rest_route('custom/v1', '/get-term-translation', [
        'methods' => 'GET',
        'callback' => 'get_translated_term_id_api',
        'args' => [
            'term_id' => ['required' => true],
            'lang' => ['required' => true],
        ],
        'permission_callback' => '__return_true' // 必要に応じて認証制御
    ]);
}
add_action('rest_api_init', 'register_term_translation_query_endpoint');

function get_translated_term_id_api($request) {
    $term_id = (int) $request->get_param('term_id');
    $lang = sanitize_text_field($request->get_param('lang'));

    if (!$term_id || !$lang || !function_exists('pll_get_term')) {
        return new WP_Error('invalid', '不正なリクエストまたはPolylang未定義', ['status' => 400]);
    }

    $translated_id = pll_get_term($term_id, $lang);
    return ['translated_id' => $translated_id];
}

function add_featured_image_to_rss($content) {
    global $post;

    if (!is_object($post) || empty($post->ID)) {
        return $content;
    }

    // すでに画像が含まれていればスキップ（念のため）
    if (strpos($content, '<img') !== false) {
        return $content;
    }

    $thumbnail_url = '';

    // 通常のアイキャッチがある場合
    if (has_post_thumbnail($post->ID)) {
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'medium');
    }

    // 外部アイキャッチがある場合（上書き）
    $external = get_post_meta($post->ID, 'external_thumbnail', true);
    if ($external && filter_var($external, FILTER_VALIDATE_URL)) {
        $thumbnail_url = $external;
    }

    if ($thumbnail_url) {
        $img_tag = '<p><img src="' . esc_url($thumbnail_url) . '" alt="" /></p>';
        $content = $img_tag . $content;
    }

    return $content;
}
add_filter('the_excerpt_rss', 'add_featured_image_to_rss');
add_filter('the_content_feed', 'add_featured_image_to_rss');

function add_media_thumbnail_to_rss() {
    global $post;
    $url = get_post_meta($post->ID, 'external_thumbnail', true);
    if ($url) {
        echo '<media:thumbnail url="' . esc_url($url) . '" />' . "\n";
    }
}
add_action('rss2_item', 'add_media_thumbnail_to_rss');

function usd_price_shortcode($atts) {
    $a = shortcode_atts(array(
        'yen' => 0,
        'prefix' => 'At current rate: ',
        'decimals' => 2
    ), $atts);

    // ✅ レートと更新日をオプションから取得
    $rate = get_option('current_usd_jpy_rate');
    $last_updated = get_option('usd_jpy_rate_date');
    $today = date('Y-m-d');

    // ✅ 今日のレートじゃなければAPIから取得
    if ($last_updated !== $today || !$rate) {
        $response = wp_remote_get('https://open.er-api.com/v6/latest/USD');
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['rates']['JPY'])) {
                $rate = floatval($data['rates']['JPY']);
                update_option('current_usd_jpy_rate', $rate);
                update_option('usd_jpy_rate_date', $today);
            }
        }
    }

    if (!$rate || $rate <= 0) return ''; // エラー時は何も表示しない

    $yen = floatval($a['yen']);
    $usd = $yen / $rate;
    $usd = floor($usd * 100) / 100;

    return esc_html($a['prefix'] . '$' . $usd);
}
add_shortcode('usd_price', 'usd_price_shortcode');

// rssのエラー対策。URLはどのブログでもこれで固定。
add_filter('rss2_ns', function() {
    echo 'xmlns:media="http://search.yahoo.com/mrss/" ';
});



/**
 * 言語に応じてカテゴリ名とポップアップのテキストを切り替える
 */
function recommend_category_popup() {
    // サイトの言語設定を取得
    $site_lang = get_bloginfo('language');
    
    // 言語によってカテゴリ名とポップアップのテキストを決定
    if (strpos($site_lang, 'ja') === 0) {
        // 日本語の場合
        $category_name = 'おすすめ';
        $popup_title = 'この記事を読んだあなたへ：93%の人が次に読んでいる人気記事はこちら';
        $popup_link_text = '次に読むべき人気記事を見てみる';
    } else {
        // 英語など、日本語以外の場合
        $category_name = 'Top Picks';
        $popup_title = 'Based on this article, here are the top picks that 93% of our readers chose to read next.';
        $popup_link_text = 'Read the Top Picks Now';
    }

    // 決定したカテゴリ名でカテゴリ情報を取得
    $recommend_category = get_term_by('name', $category_name, 'category');
    
    // カテゴリが存在しない場合は処理を終了
    if (!$recommend_category) {
        return;
    }

    // カテゴリIDを取得
    $recommend_category_id = $recommend_category->term_id;

    // ポップアップの有効期限（クッキーの有効期間）を設定
    $cookie_expire_hours = 24; // 24時間後に再表示

    ?>
    <style>
        /* ポップアップのスタイル */
        .recommend-popup {
            position: fixed;
            bottom: -150px; /* 初期状態では画面外に配置 */
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            background-color: #fff;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
            padding: 15px 20px;
            text-align: center;
            z-index: 1000;
            transition: bottom 0.5s ease-in-out;
            display: none; /* 初期状態では非表示 */
        }
        .recommend-popup.visible {
            bottom: 0; /* 表示時に画面内に移動 */
        }
        .recommend-popup h3 {
            margin-top: 0;
            padding-bottom: 0.5em;
            font-size: 1rem;
            color: #333;
        }
        .recommend-popup .popup-link {
            display: block;
            background-color: #0073aa;
            color: #fff;
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .recommend-popup .popup-link:hover {
            background-color: #005177;
        }
        .recommend-popup .close-btn {
            position: absolute;
            top: 5px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #ccc;
            cursor: pointer;
        }
    </style>

    <div id="recommend-category-popup" class="recommend-popup">
        <button class="close-btn">&times;</button>
        <h3><?php echo esc_html($popup_title); ?></h3>
        <a href="<?php echo esc_url( get_category_link( $recommend_category_id ) ); ?>" class="popup-link">
            <?php echo esc_html($popup_link_text); ?>
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const popup = document.getElementById('recommend-category-popup');
            const closeBtn = popup.querySelector('.close-btn');
            const cookieName = 'recommendPopupDismissed';
            const cookieExpiryHours = <?php echo intval($cookie_expire_hours); ?>;
            let popupShown = false; // ポップアップが一度表示されたかを追跡するフラグ

            // ポップアップを表示する関数
            function showPopup() {
                if (getCookie(cookieName) === null && !popupShown) {
                    popup.style.display = 'block';
                    setTimeout(() => {
                        popup.classList.add('visible');
                    }, 100);
                    popupShown = true;
                }
            }

            // クッキーの値を読み込む関数
            function getCookie(name) {
                const nameEQ = name + "=";
                const ca = document.cookie.split(';');
                for(let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            }

            // クッキーをセットする関数
            function setCookie(name, value, hours) {
                let expires = "";
                if (hours) {
                    const date = new Date();
                    date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "")  + expires + "; path=/";
            }

            // ポップアップを閉じるイベント
            closeBtn.addEventListener('click', function() {
                popup.classList.remove('visible');
                setCookie(cookieName, 'true', cookieExpiryHours);
            });

            // 記事の最後を検知してポップアップを表示
            const contentElement = document.querySelector('.entry-content'); // テーマに合わせてセレクタを調整してください
            if (contentElement) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            showPopup();
                            observer.disconnect(); // 一度表示したら監視を停止
                        }
                    });
                }, {
                    threshold: 0.5 // 記事コンテンツの半分以上が見えたら
                });
                observer.observe(contentElement.lastElementChild);
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'recommend_category_popup');



// ------------------------------
// クリックログ保存 + 異常検知 + ダッシュボードウィジェット
// ------------------------------

// ▼ DBバージョン
define('BG_CLICK_DB_VERSION', '1.0');

// ▼ テーブル作成
function bg_ensure_click_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'click_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        link_url text NOT NULL,
        post_id bigint(20) unsigned DEFAULT NULL,
        ip varchar(100) NOT NULL,
        ua text NOT NULL,
        time datetime NOT NULL,
        PRIMARY KEY (id),
        KEY ip (ip),
        KEY time (time)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (get_option('bg_click_db_version') === false) {
        add_option('bg_click_db_version', BG_CLICK_DB_VERSION);
    }
}
add_action('after_setup_theme', 'bg_ensure_click_logs_table');

// ▼ クリック記録関数
function bg_log_click($link_url, $post_id = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'click_logs';

    $ip   = $_SERVER['REMOTE_ADDR'];
    $ua   = $_SERVER['HTTP_USER_AGENT'];
    $time = current_time('mysql');

    $wpdb->insert($table, array(
        'link_url' => $link_url,
        'post_id'  => $post_id,
        'ip'       => $ip,
        'ua'       => $ua,
        'time'     => $time,
    ));

    // 異常クリック判定（直近1分同一IP5回以上）
    $time_limit_1m = date('Y-m-d H:i:s', current_time('timestamp') - 60);
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE ip = %s AND time >= %s",
        $ip,
        $time_limit_1m
    ));

    if ($count > 5) {
        $subject = '【異常クリック検知】';
        $message = "記事ID: {$post_id}\nURL: {$link_url}\nIP: {$ip}\nUA: {$ua}\n"
                 . "1分間で {$count} 回クリックされました。";
        wp_mail(get_option('admin_email'), $subject, $message);
    }
}

// ▼ リダイレクト処理（base64対応）
function bg_handle_click_redirect() {
    if (isset($_GET['bg_click']) && $_GET['bg_click'] == 1) {
        $url = isset($_GET['url']) ? base64_decode($_GET['url']) : '';
        $id  = isset($_GET['id']) ? intval($_GET['id']) : null;

        if (!empty($url)) {
            bg_log_click($url, $id);
            wp_redirect(esc_url_raw($url));
            exit;
        }
    }
}
add_action('init', 'bg_handle_click_redirect');


// ------------------------------
// ダッシュボードウィジェット
// ------------------------------

// ▼ ダッシュボードウィジェットを横幅いっぱいに表示するためのCSSを追加
function bg_dashboard_widget_full_width_css() {
    echo '<style type="text/css">
        /* PC画面でのみ全幅を適用 */
        @media screen and (min-width: 783px) {
            #dashboard-widgets .postbox-container {
                width: 100% !important;
                float: none !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
    </style>';
}
add_action('admin_head', 'bg_dashboard_widget_full_width_css');

function bg_add_dashboard_widget() {
    if (current_user_can('manage_options')) {
        // コンテキストを 'normal' に設定
        wp_add_dashboard_widget(
            'bg_click_dashboard_widget',
            '直近クリック状況（異常クリック検知）',
            'bg_render_dashboard_widget',
            null,
            null,
            'normal'
        );
    }
}
add_action('wp_dashboard_setup', 'bg_add_dashboard_widget');

function bg_render_dashboard_widget() {
    global $wpdb;
    $table = $wpdb->prefix . 'click_logs';

    $time_limit_24h = date('Y-m-d H:i:s', current_time('timestamp') - 24*60*60);
    $time_limit_1h = date('Y-m-d H:i:s', current_time('timestamp') - 60*60);

    $clicks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ip, ua, post_id, link_url, MAX(time) as last_time, COUNT(*) as cnt
             FROM {$table}
             WHERE time >= %s
             GROUP BY ip, ua, post_id, link_url
             ORDER BY last_time DESC
             LIMIT 20",
            $time_limit_24h
        )
    );

    if (empty($clicks)) {
        echo "<p>直近24時間のクリックはありません。</p>";
        return;
    }

    echo '<div style="max-height: 400px; overflow-y: auto;">';
    echo '<table style="width:100%; border-collapse: collapse; font-size: 0.8em;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 80px;">時刻</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 100px;">IP</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 150px;">User-Agent</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 150px;">記事URL</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 150px;">クリック先URL</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 70px;">数(24h)</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 70px;">数(1h)</th>';
    echo '<th style="padding: 5px; border: 1px solid #ddd; text-align: left; width: 40px;">異常</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($clicks as $click) {
        $last_time_display = date('Y-m-d H:i:s', strtotime($click->last_time));
        $abnormal = ($click->cnt > 5) ? '⚠️' : '';

        $same_ip_1h = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE ip = %s AND ua = %s AND post_id = %d AND link_url = %s AND time >= %s",
            $click->ip,
            $click->ua,
            $click->post_id,
            $click->link_url,
            $time_limit_1h
        ));

        $article_url = $click->post_id ? get_permalink($click->post_id) : 'N/A';
        $article_url_display = strlen($article_url) > 50
            ? substr($article_url, 0, 30) . '...' . substr($article_url, -17)
            : $article_url;
        $link_url_display = strlen($click->link_url) > 50
            ? substr($click->link_url, 0, 30) . '...' . substr($click->link_url, -17)
            : $click->link_url;
        $ua_display = strlen($click->ua) > 50
            ? substr($click->ua, 0, 30) . '...' . substr($click->ua, -17)
            : $click->ua;

        echo '<tr style="border-bottom:1px solid #eee;">';
        echo "<td style='padding:5px;border:1px solid #ddd;'>{$last_time_display}</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; word-break: break-all;'>{$click->ip}</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; word-break: break-all; max-width: 150px;' title='" . esc_attr($click->ua) . "'>{$ua_display}</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; word-break: break-all; max-width: 150px;'>";
        if ($article_url && $article_url !== 'N/A') {
            echo "<a href='" . esc_url($article_url) . "' target='_blank' title='" . esc_attr($article_url) . "'>{$article_url_display}</a>";
        } else {
            echo $article_url_display;
        }
        echo "</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; word-break: break-all; max-width: 150px;'>";
        echo "<a href='" . esc_url($click->link_url) . "' target='_blank' title='" . esc_attr($click->link_url) . "'>{$link_url_display}</a>";
        echo "</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; text-align: center;'>{$click->cnt}</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; text-align: center;'>{$same_ip_1h}</td>";
        echo "<td style='padding:5px;border:1px solid #ddd; text-align: center;'>{$abnormal}</td>";
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// 外部リンク＆クリック用リダイレクトリンクに rel="nofollow noopener sponsored"
function add_sponsored_to_external_links($content) {
    if (empty($content)) {
        return $content; // 空なら処理せず返す
    }

    $site_url = get_site_url();
    $image_extensions = ['jpg','jpeg','png','gif','webp','svg'];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $anchors = $dom->getElementsByTagName('a');

    foreach ($anchors as $anchor) {
        $href = $anchor->getAttribute('href');
        if (!$href) continue;

        // 画像リンクは除外
        $path_info = pathinfo(parse_url($href, PHP_URL_PATH));
        if (isset($path_info['extension']) && in_array(strtolower($path_info['extension']), $image_extensions)) {
            continue;
        }

        // 外部リンク判定
        $is_external = (strpos($href, $site_url) !== 0 && preg_match('/^https?:\/\//i', $href));

        // クリック用リダイレクトリンクも外部扱いにする
        $is_click_redirect = (strpos($href, '?bg_click=1') !== false);

        if ($is_external || $is_click_redirect) {
            $anchor->setAttribute('rel', 'nofollow noopener sponsored');
        }
    }

    $modified_html = $dom->saveHTML();
    $body_start = strpos($modified_html, '<body>') + 6;
    $body_end = strpos($modified_html, '</body>');
    return substr($modified_html, $body_start, $body_end - $body_start);
}

add_filter('the_content', 'add_sponsored_to_external_links', 20);


// https://a-ippon.com/wp-admin/?run_external_thumbnail_update=1

// アイキャッチ用カスタムフィールドが空なら記事の最初の画像をアイキャッチ用カスタムフィールドexternal_thumbnailに登録
/*
add_action('admin_init', function () {
  if (!current_user_can('administrator')) return;

  if (!isset($_GET['run_external_thumbnail_update'])) return;

  // ========================
  // ① external_thumbnail が未設定の投稿に登録
  // ========================
  $args = [
    'post_type' => 'post',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'meta_query' => [
      [
        'key' => 'external_thumbnail',
        'compare' => 'NOT EXISTS',
      ],
    ],
  ];

  $query = new WP_Query($args);
  $count = 0;

  foreach ($query->posts as $post) {
    $content = $post->post_content;

    if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
      $image_url = esc_url_raw($matches[1]);

      if (strpos($image_url, 'http') === 0) {
        update_post_meta($post->ID, 'external_thumbnail', $image_url);
        $count++;
      }
    }
  }

  echo "<div class='notice notice-success'><p>{$count} 件の投稿に external_thumbnail を追加しました。</p></div>";

  // ========================
  // ② メディアを完全削除（boy.jpg / girl.jpg は除外）
  // ========================
  $excluded_filenames = ['boy.jpg', 'girl.jpg'];
  $deleted = 0;

  $media_query = new WP_Query([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'fields' => 'ids',
  ]);

  foreach ($media_query->posts as $attachment_id) {
    $url = wp_get_attachment_url($attachment_id);
    $basename = basename($url);

    // boy.jpg / girl.jpg はスキップ
    if (in_array($basename, $excluded_filenames)) {
      continue;
    }

    if (wp_delete_attachment($attachment_id, true)) {
      $deleted++;
    }
  }

  echo "<div class='notice notice-warning'><p>{$deleted} 件のメディアを完全に削除しました（boy.jpg / girl.jpg は除外）。</p></div>";
});
*/