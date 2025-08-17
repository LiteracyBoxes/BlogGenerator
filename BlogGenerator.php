<?php
/*
Plugin Name: Blog Generator
Plugin URI: https://github.com/LiteracyBoxes/BlogGenerator
GitHub Plugin URI: https://github.com/LiteracyBoxes/BlogGenerator
GitHub Branch: main
Primary Branch: main
Description: BlogGenerator用のカスタム関数をまとめたプラグイン
Version: 1.0.6
Author: ken
*/

/**
 * 【Git Updater 用：更新時の手順メモ】
 *
 * 1. プラグインのコードを編集
 * 2. このファイルの Version を更新（例: 1.0.0 → 1.0.1）
 *
 * 3. Git コミット & プッシュ
 *    git add .
 *    git commit -m "ポップアップおすすめカテゴリ表示"
 *    git push
 *
 * 4. Git タグを作成してプッシュ（Version と同じに）
 *    git tag 1.0.6
 *    git push origin 1.0.6
 *
 * 5. WordPress 管理画面で更新確認
 *    - 「Git Updater」→「Refresh Cache」
 *    - 更新通知が出たら「更新」をクリック
 *
 * ※ 注意：
 * - Version と タグ名は必ず一致させる（例：1.0.1）
 * - タグを忘れると更新が通知されない
 */


if (!defined('ABSPATH')) exit;


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
        $popup_title = 'この記事に興味を持ったあなたにおすすめの商品・サービス一覧はこちら';
        $popup_link_text = '「おすすめ」カテゴリへ';
    } else {
        // 英語など、日本語以外の場合
        $category_name = 'Top Picks';
        $popup_title = 'Recommended Products & Services Based on This Article';
        $popup_link_text = 'Go to "Top Picks" category';
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

            // スクロールイベントでポップアップを表示
            window.addEventListener('scroll', function() {
                // クッキーが存在しない場合にのみ実行
                if (getCookie(cookieName) === null) {
                    const scrollPosition = window.scrollY;
                    const documentHeight = document.documentElement.scrollHeight;
                    const windowHeight = window.innerHeight;

                    // ページの約半分をスクロールしたらポップアップを表示
                    if (scrollPosition > (documentHeight - windowHeight) / 10) {
                        popup.style.display = 'block';
                        setTimeout(() => {
                            popup.classList.add('visible');
                        }, 100); // 遅延させてアニメーションを適用
                    }
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'recommend_category_popup');

// 全ての外部リンクにrel="sponsored"を自動付加
/*
function add_sponsored_to_external_links($content) {
    $home_url = home_url();
    
    // DOMDocumentを使ってHTMLを解析
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));

    $anchors = $dom->getElementsByTagName('a');

    foreach ($anchors as $anchor) {
        $href = $anchor->getAttribute('href');
        if ($href && strpos($href, $home_url) === false && strpos($href, 'mailto:') === false && strpos($href, 'tel:') === false) {
            $rel = $anchor->getAttribute('rel');
            $rel_array = array_map('trim', explode(' ', $rel));
            if (!in_array('sponsored', $rel_array)) {
                $rel_array[] = 'sponsored';
                $anchor->setAttribute('rel', implode(' ', array_filter($rel_array)));
            }
        }
    }

    return $dom->saveHTML($dom->documentElement);
}
add_filter('the_content', 'add_sponsored_to_external_links', 20);
*/

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