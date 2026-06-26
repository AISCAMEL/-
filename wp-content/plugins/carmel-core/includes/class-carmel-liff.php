<?php
/**
 * LINE LIFF 連携ヘルパー。
 *
 * リッチメニュー →（LIFF URL）→ 審査/問い合わせフォームを置いた WP ページ、という
 * 動線で使う。ショートコード [carmel_liff] をそのページに置くと、LIFF SDK を読み込み、
 * ログイン中の LINE ユーザーの userId を取得して、ページ内フォームの hidden 入力
 * （既定 name="line_user_id"）に自動で差し込む。氏名が空なら LINE 表示名で補完する。
 *
 * フォーム送信時にこの line_user_id が Carmel_Application_Intake::process() へ渡れば、
 * 顧客の user_meta `line_user_id` に保存され、以後の通知がその人の LINE へ届く。
 *
 * 設定：LIFF ID は属性 id="..." または定数 CARMEL_LIFF_ID / オプション carmel_liff_id。
 * 使い方例：[carmel_liff]  /  [carmel_liff id="1657xxxxxx-XXXXXXXX" field="line_user_id"]
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_LIFF {

	/** @var Carmel_LIFF|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_liff';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	private function liff_id( $attr_id = '' ) {
		if ( '' !== (string) $attr_id ) {
			return (string) $attr_id;
		}
		if ( defined( 'CARMEL_LIFF_ID' ) && CARMEL_LIFF_ID ) {
			return (string) CARMEL_LIFF_ID;
		}
		return (string) get_option( 'carmel_liff_id', '' );
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'        => '',
				'field'     => 'line_user_id',
				'fill_name' => 'yes', // 氏名が空なら LINE 表示名で補完
			),
			$atts,
			self::SHORTCODE
		);

		$liff_id = $this->liff_id( $atts['id'] );
		if ( '' === $liff_id ) {
			// 未設定時は管理者にだけ注意を表示（一般来訪者には何も出さない）。
			return current_user_can( 'manage_options' )
				? '<p style="color:#a5281b">[carmel_liff] LIFF ID が未設定です（属性 id か CARMEL_LIFF_ID / carmel_liff_id を設定）。</p>'
				: '';
		}

		$field      = preg_replace( '/[^A-Za-z0-9_\-\[\]]/', '', $atts['field'] );
		$fill_name  = in_array( strtolower( $atts['fill_name'] ), array( 'yes', '1', 'true' ), true ) ? 1 : 0;
		$name_sel   = esc_js( apply_filters( 'carmel_liff_name_selector', 'input[name="your-name"],input[name="name"],input[name="氏名"]' ) );

		ob_start();
		?>
<script>
(function(){
	var LIFF_ID=<?php echo wp_json_encode( $liff_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var FIELD=<?php echo wp_json_encode( $field ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var FILL_NAME=<?php echo (int) $fill_name; ?>;
	function apply(p){
		document.querySelectorAll('form').forEach(function(f){
			var inp=f.querySelector('input[name="'+FIELD+'"]');
			if(!inp){inp=document.createElement('input');inp.type='hidden';inp.name=FIELD;f.appendChild(inp);}
			inp.value=p.userId||'';
			if(FILL_NAME&&p.displayName){
				var nm=f.querySelector('<?php echo $name_sel; // phpcs:ignore WordPress.Security.EscapeOutput ?>');
				if(nm&&!nm.value)nm.value=p.displayName;
			}
		});
		document.dispatchEvent(new CustomEvent('carmel-liff-ready',{detail:p}));
	}
	function boot(){
		if(typeof liff==='undefined')return;
		liff.init({liffId:LIFF_ID}).then(function(){
			if(!liff.isLoggedIn()){liff.login();return;}
			liff.getProfile().then(apply).catch(function(e){window.console&&console.warn('LIFF getProfile',e);});
		}).catch(function(e){window.console&&console.warn('LIFF init',e);});
	}
	var s=document.createElement('script');
	s.src='https://static.line-scdn.net/liff/edge/2/sdk.js';
	s.charset='utf-8';s.onload=boot;
	document.head.appendChild(s);
})();
</script>
		<?php
		return ob_get_clean();
	}
}
