<?php
/**
 * Shared front-end design layer for all Carmel portals.
 *
 * Prints one stylesheet (in the footer so it reliably overrides the
 * per-shortcode inline styles) that establishes:
 *   - a clean, readable Japanese-first font stack (書体)
 *   - comfortable base typography (size / line-height / color)
 *   - mobile-friendly rules (16px inputs to stop iOS zoom, 44px tap targets,
 *     scrollable tables, stacking, full-width primary buttons on phones)
 *   - brand-color unification (purple) across portals
 *
 * Optional web font: define CARMEL_WEBFONT (or option carmel_webfont) to a
 * stylesheet URL (e.g. Noto Sans JP) to load it; otherwise the system stack
 * is used (fast, no external request).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Assets {

	/** @var Carmel_Assets|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_webfont' ) );
		add_action( 'wp_footer', array( $this, 'print_css' ), 99 );
	}

	/**
	 * Optionally load a web font (e.g. Noto Sans JP) if configured.
	 */
	public function maybe_webfont() {
		$url = defined( 'CARMEL_WEBFONT' ) ? CARMEL_WEBFONT : get_option( 'carmel_webfont', '' );
		if ( $url ) {
			wp_enqueue_style( 'carmel-webfont', esc_url( $url ), array(), null ); // phpcs:ignore
		}
	}

	/**
	 * Print the shared design layer in the footer.
	 */
	public function print_css() {
		echo "\n<style id=\"carmel-theme\">\n" . $this->css() . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function css() {
		return <<<CSS
:root{
  --carmel-brand:#5b2a86; --carmel-accent:#7c3aed;
  --carmel-ink:#2b2433; --carmel-muted:#7a7488;
  --carmel-line:#e7e2ef; --carmel-radius:12px;
  --carmel-font:-apple-system,BlinkMacSystemFont,"Hiragino Sans","Hiragino Kaku Gothic ProN","Noto Sans JP","Yu Gothic",Meiryo,sans-serif;
}
/* ---- 書体・基本タイポ ---- */
[class^="carmel-"],[class*=" carmel-"]{
  font-family:var(--carmel-font);
  color:var(--carmel-ink);
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
  letter-spacing:.01em;
}
[class^="carmel-"] h2,[class^="carmel-"] h3,[class^="carmel-"] h4{line-height:1.4;letter-spacing:.02em}
[class^="carmel-"] p,[class^="carmel-"] td,[class^="carmel-"] li{line-height:1.75}
/* ---- 入力・ボタン（タップしやすさ） ---- */
[class^="carmel-"] input,[class^="carmel-"] select,[class^="carmel-"] textarea{
  font-size:16px;            /* iOSの自動ズーム防止 */
  border-radius:10px;
}
[class^="carmel-"] button,[class^="carmel-"] input[type=submit],[class^="carmel-"] .carmel-btn{
  min-height:44px;border-radius:10px;font-weight:700;letter-spacing:.03em;
  transition:filter .15s,transform .02s;
}
[class^="carmel-"] button:active,[class^="carmel-"] .carmel-btn:active{transform:translateY(1px)}
[class^="carmel-"] a{color:var(--carmel-accent)}
/* ---- ブランド統一（紫） ---- */
.carmel-type,.carmel-badge,.carmel-tab.active,.carmel-count{background:var(--carmel-accent)}
.carmel-btn-blue{background:var(--carmel-accent)}
.carmel-kpi-val,.carmel-stat-num,.carmel-mini-store,.carmel-cat{color:var(--carmel-brand)}
.carmel-kpi,.carmel-stat,.carmel-card,.carmel-up-card,.carmel-appform,.carmel-frform{border-radius:var(--carmel-radius)}
/* ---- テーブル：読みやすさ ---- */
[class^="carmel-"] .carmel-table{border-radius:10px;overflow:hidden}
[class^="carmel-"] .carmel-table th{font-weight:700;color:var(--carmel-muted);font-size:.86em}
/* ---- スマホ最適化 ---- */
@media (max-width:640px){
  .carmel-mypage,.carmel-store,.carmel-reports,.carmel-hqstores,.carmel-board,
  .carmel-contracts,.carmel-hq-screening,.carmel-upload,.carmel-learning{font-size:15px}
  /* 横に長い表は横スクロール */
  .carmel-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
  /* 主要ボタンは横幅いっぱい・押しやすく */
  .carmel-actions{flex-direction:column;align-items:stretch}
  .carmel-actions form{width:100%}
  .carmel-actions .carmel-btn,.carmel-appform-btn,.carmel-frform-btn{width:100%}
  /* カード/カラムは縦積み */
  .carmel-kpis,.carmel-cards,.carmel-learn-grid,.carmel-board-cols{flex-direction:column}
  .carmel-kpi,.carmel-stat,.carmel-col{min-width:0;width:100%}
  .carmel-card,.carmel-up-card{padding:1em}
  /* ステッパーは折り返し可 */
  .carmel-stepper{flex-wrap:wrap;gap:.4em}
  .carmel-step{min-width:56px}
}
CSS;
	}
}
