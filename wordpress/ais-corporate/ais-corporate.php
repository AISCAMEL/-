<?php
/**
 * Plugin Name:       AIS コーポレート（合同会社アイズ）
 * Plugin URI:        https://aisjaltd.com/
 * Description:        合同会社アイズのコーポレートサイト（車8割・IT2割／上質デザイン）を、固定ページテンプレートとして一括適用します。有効化するとフロントページに自動設定。ショートコード [ais_corporate_home] でも設置できます。
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            合同会社アイズ（AIS CO., LTD.）
 * License:           GPL-2.0-or-later
 * Text Domain:       ais-corporate
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // 直接アクセス禁止

define( 'AIS_CORP_VERSION', '1.0.0' );
define( 'AIS_CORP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIS_CORP_URL', plugin_dir_url( __FILE__ ) );
define( 'AIS_CORP_TEMPLATE', 'ais-corporate-top' ); // 固定ページテンプレートのスラッグ

/* =========================================================
 *  アセット（CSS / フォント / JS）の読み込み
 * ========================================================= */
function ais_corp_enqueue() {
	// Google Fonts（明朝＋英字）
	wp_enqueue_style(
		'ais-corp-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Noto+Sans+JP:wght@400;500;700&family=Noto+Serif+JP:wght@500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'ais-corp-theme', AIS_CORP_URL . 'assets/css/theme.css', array(), AIS_CORP_VERSION );
	wp_enqueue_style( 'ais-corp-style', AIS_CORP_URL . 'assets/css/style.css', array( 'ais-corp-theme' ), AIS_CORP_VERSION );
	wp_enqueue_script( 'ais-corp-main', AIS_CORP_URL . 'assets/js/main.js', array(), AIS_CORP_VERSION, true );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_page() && get_page_template_slug( get_queried_object_id() ) === AIS_CORP_TEMPLATE ) {
		ais_corp_enqueue();
	} elseif ( is_singular() ) {
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'ais_corporate_home' ) ) {
			ais_corp_enqueue();
		}
	}
} );

/* =========================================================
 *  固定ページテンプレートの登録
 * ========================================================= */
add_filter( 'theme_page_templates', function ( $templates ) {
	$templates[ AIS_CORP_TEMPLATE ] = 'AIS コーポレートTOP（全幅・独自ヘッダー）';
	return $templates;
} );

add_filter( 'template_include', function ( $template ) {
	if ( is_page() && get_page_template_slug( get_queried_object_id() ) === AIS_CORP_TEMPLATE ) {
		ais_corp_enqueue();
		$file = AIS_CORP_DIR . 'templates/corporate-top.php';
		if ( file_exists( $file ) ) {
			return $file;
		}
	}
	return $template;
} );

/* =========================================================
 *  ショートコード [ais_corporate_home]
 *  既存テーマのページ内に本文（ヒーロー〜お問い合わせ）を埋め込む用
 * ========================================================= */
add_shortcode( 'ais_corporate_home', function () {
	return ais_corp_sections();
} );

/* =========================================================
 *  マークアップ（ヘッダー / 本文セクション / フッター）
 *  ※ セクションはテンプレートとショートコードで共用
 * ========================================================= */
function ais_corp_site_header() {
	return <<<'HTML'
<header class="site-header">
  <div class="wrap">
    <a class="brand" href="/" aria-label="合同会社アイズ ホーム">
      <span class="mark">AIS</span>
      <span class="name">合同会社アイズ<small>AIS CO., LTD.</small></span>
    </a>
    <nav class="nav" id="nav">
      <a class="nav-link" href="#business">事業紹介</a>
      <a class="nav-link" href="#it">IT事業</a>
      <a class="nav-link" href="#company">会社概要</a>
      <a class="nav-link" href="#contact">お問い合わせ</a>
      <a class="btn btn-gold btn-sm" href="#contact">ご相談はこちら</a>
    </nav>
    <button class="nav-toggle" id="navToggle" aria-label="メニューを開閉">☰</button>
  </div>
</header>
HTML;
}

function ais_corp_sections() {
	return <<<'HTML'
<main class="ais-corp">
<section class="hero">
  <div class="wrap">
    <p class="kicker">福島県いわき市から、全国へ。</p>
    <h1 class="reveal">車のこと、ぜんぶ。<br>そして、次の事業へ。</h1>
    <p class="lead reveal">
      自動車の販売・買取・リース・セキュリティ・レッカー・オークション代行までを一社で担う、
      車の総合カンパニー。あわせて AI・アプリ・Web開発で、未来の事業を育てています。
    </p>
    <div class="cta reveal">
      <a class="btn btn-gold" href="#business">事業を見る</a>
      <a class="btn btn-ghost" href="#contact">お問い合わせ</a>
    </div>
    <div class="meta reveal">
      <div><div class="m-num">07</div><div class="m-label">車のサービス・ブランド</div></div>
      <div><div class="m-num">全国</div><div class="m-label">対応エリア</div></div>
      <div><div class="m-num">8:2</div><div class="m-label">自動車事業 ／ IT事業</div></div>
    </div>
  </div>
</section>

<section class="section" id="business">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow">自動車事業（事業の約8割）</span>
      <h2>クルマのことは、<br>アイズにすべておまかせ。</h2>
      <p>購入から売却、維持、車両の保護、もしものレッカーまで。車のライフサイクル全体を一社でカバーします。</p>
    </div>
    <div class="biz-list">
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">CARMEL</span><span class="biz-ja">カーメル</span></div>
        <div class="biz-body"><h3>自動車販売</h3><p>新車・中古車の販売。ご予算とご希望に合わせ、あなたにぴったりの一台をプロがご提案します。</p></div>
      </a>
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">BUYMO</span><span class="biz-ja">バイモ</span></div>
        <div class="biz-body"><h3>自動車買取</h3><p>無料出張査定・高価買取・最短即日入金。愛車の価値を正しく評価し、気持ちよくお売りいただけます。</p></div>
      </a>
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">CARSHICO</span><span class="biz-ja">カーシコ</span></div>
        <div class="biz-body"><h3>カーリース</h3><p>頭金0円・月々定額で新車に乗れる。車のサブスクという、新しく合理的な持ち方をご提案します。</p></div>
      </a>
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">TENGO</span><span class="biz-ja">天護</span></div>
        <div class="biz-body"><h3>車両セキュリティ・GPS</h3><p>盗難対策とGPS追跡で、大切な愛車を見守ります。車両の所在把握にも対応します。</p></div>
      </a>
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">RESCUE</span><span class="biz-ja">カーレスキュー</span></div>
        <div class="biz-body"><h3>レッカー・緊急対応</h3><p>事故・故障・バッテリー上がりなどの緊急時に迅速対応。GPSによる位置把握にも対応します。</p></div>
      </a>
      <a class="biz-row reveal" href="#">
        <div class="biz-id"><span class="biz-en">AUC-AGENT</span><span class="biz-ja">オークション代行</span></div>
        <div class="biz-body"><h3>入札・落札・出品の代行</h3><p>全国のオートオークションから、プロが入札・落札・出品を代行。中間コストを抑え、相場よりおトクに。</p></div>
      </a>
    </div>
    <div class="fc-band reveal">
      <div>
        <h3>FC事業｜カーメル／BUYMO 加盟店募集</h3>
        <p>アイズのノウハウとブランドで、あなたの地域に車事業を。フランチャイズ加盟を募集しています。</p>
      </div>
      <div class="cta-wrap"><a class="btn btn-gold" href="#contact">加盟を相談する</a></div>
    </div>
  </div>
</section>

<section class="section-sm it-band" id="it">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow">IT・クリエイティブ事業（事業の約2割）</span>
      <h2>AIとアプリで、次の事業をつくる。</h2>
      <p>車事業で培った現場感を、ITの力で加速。アプリ開発を最優先に取り組んでいます。</p>
    </div>
    <div class="it-grid reveal">
      <div class="it-cell">
        <div class="lead-name">APPREX</div>
        <h3>ノーコード アプリ開発</h3>
        <p>AIとノーコードで、アイデアを素早くアプリに。自社アプリの開発・リリースを進める最優先事業です。</p>
        <a class="tlink" href="#">詳しく見る</a>
      </div>
      <div class="it-cell">
        <div class="lead-name">WEB crews</div>
        <h3>Web制作・システム開発</h3>
        <p>ホームページ制作、システム開発、マーケティングまで。事業の成長をデジタルで支えます。</p>
        <a class="tlink" href="#">詳しく見る</a>
      </div>
    </div>
  </div>
</section>

<section class="section" id="company">
  <div class="wrap narrow">
    <div class="section-head reveal"><h2>会社概要</h2></div>
    <div class="company reveal">
      <dl>
        <div class="row"><dt>会社名</dt><dd>合同会社アイズ（AIS CO., LTD.）</dd></div>
        <div class="row"><dt>代表社員</dt><dd>吉田 一平</dd></div>
        <div class="row"><dt>所在地</dt><dd>〒979-0204　福島県いわき市四倉町細谷字大町1番</dd></div>
        <div class="row"><dt>事業内容</dt><dd>自動車の販売・買取・リース・車両セキュリティ・レッカー／カーレスキュー・オートオークション代行・FC（フランチャイズ）事業／AI・ノーコードアプリ開発・Web制作・システム開発・マーケティング</dd></div>
        <div class="row"><dt>許認可</dt><dd>古物商許可　第25121A010859号</dd></div>
        <div class="row"><dt>法人番号</dt><dd>T9380003004349</dd></div>
        <div class="row"><dt>メール</dt><dd><a href="mailto:info@aisjaltd.com">info@aisjaltd.com</a></dd></div>
      </dl>
    </div>
  </div>
</section>

<section class="section" id="contact" style="padding-top:0;">
  <div class="wrap">
    <div class="cta-band reveal">
      <h2>車のこと・IT のこと、<br>まずはお気軽にご相談ください。</h2>
      <p>販売・買取・リース・FC加盟・アプリ／Web開発まで。担当者が丁寧にお答えします。</p>
      <a class="btn btn-gold" href="mailto:info@aisjaltd.com">メールで相談する（info@aisjaltd.com）</a>
    </div>
  </div>
</section>
</main>
HTML;
}

function ais_corp_site_footer() {
	return <<<'HTML'
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <a class="brand" href="/" style="margin-bottom:18px;">
          <span class="mark">AIS</span>
          <span class="name">合同会社アイズ<small>AIS CO., LTD.</small></span>
        </a>
        <p style="font-size:14px; margin:0; color:rgba(255,255,255,.66); line-height:1.9;">
          福島県いわき市から全国へ。<br>車の総合サービス＋ITで、次の事業をつくります。
        </p>
      </div>
      <div>
        <h4>自動車事業</h4>
        <a href="#business">カーメル（販売）</a>
        <a href="#business">BUYMO（買取）</a>
        <a href="#business">CARSHICO（リース）</a>
        <a href="#business">天護 TENGO（セキュリティ）</a>
        <a href="#business">レッカー／カーレスキュー</a>
        <a href="#business">AUC-AGENT（代行）</a>
        <a href="#business">FC事業</a>
      </div>
      <div>
        <h4>IT・その他</h4>
        <a href="#it">APPREX（アプリ開発）</a>
        <a href="#it">WEB crews（Web開発）</a>
        <a href="#company">会社概要</a>
        <a href="#contact">お問い合わせ</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 合同会社アイズ（AIS CO., LTD.）</span>
      <span>古物商許可 第25121A010859号</span>
    </div>
  </div>
</footer>
HTML;
}

/* =========================================================
 *  有効化：フロントページを自動作成・設定（一括リニューアル）
 * ========================================================= */
register_activation_hook( __FILE__, 'ais_corp_activate' );
function ais_corp_activate() {
	$page_id = (int) get_option( 'ais_corp_page_id' );
	if ( ! $page_id || ! get_post( $page_id ) ) {
		$page_id = wp_insert_post( array(
			'post_title'   => 'ホーム',
			'post_name'    => 'ais-home',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '[ais_corporate_home]',
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'ais_corp_page_id', $page_id );
		}
	}
	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_post_meta( $page_id, '_wp_page_template', AIS_CORP_TEMPLATE );
		// 既存のフロント設定をバックアップ（復元用）
		if ( get_option( 'ais_corp_prev_front', false ) === false ) {
			update_option( 'ais_corp_prev_front', array(
				'show_on_front' => get_option( 'show_on_front' ),
				'page_on_front' => (int) get_option( 'page_on_front' ),
			) );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		set_transient( 'ais_corp_activated', 1, 60 );
	}
}

/* =========================================================
 *  無効化：フロント設定を元に戻す（ページ自体は削除しない）
 * ========================================================= */
register_deactivation_hook( __FILE__, 'ais_corp_deactivate' );
function ais_corp_deactivate() {
	$prev = get_option( 'ais_corp_prev_front' );
	if ( is_array( $prev ) ) {
		update_option( 'show_on_front', $prev['show_on_front'] );
		update_option( 'page_on_front', $prev['page_on_front'] );
	}
	delete_option( 'ais_corp_prev_front' );
}

/* =========================================================
 *  有効化直後の管理画面メッセージ
 * ========================================================= */
add_action( 'admin_notices', function () {
	if ( get_transient( 'ais_corp_activated' ) ) {
		delete_transient( 'ais_corp_activated' );
		$url = home_url( '/' );
		echo '<div class="notice notice-success is-dismissible"><p>'
			. '✅ <strong>AIS コーポレート</strong> を適用しました。フロントページを新デザインに設定済みです。'
			. ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">サイトを表示</a>'
			. '</p></div>';
	}
} );
