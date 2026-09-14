<?php
/**
 * カーメル：コード場所さがし（一時利用）
 * ---------------------------------------------------------------------------
 * 目的 : 「型式から車両情報を自動入力」など carmel 系の処理が、
 *        テーマ / プラグイン / WPCode のどのファイルで定義されているかを表示。
 *        これで“フォーム本体のソースがどこにあるか”を特定できる。
 *
 * 導入 : WPCode →「PHP Snippet」/「自動挿入・どこでも実行」で貼り付けて有効化。
 *        管理画面のどれかのページを開くと、上部に黒いボックスが出る。
 *        中身を全選択コピーして送ってください。確認できたら無効化してOK。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_notices', 'carmel_code_locator' );
function carmel_code_locator() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$lines = array();

	/* 1) 関数名に carmel / lookup / katashiki / step_ui / vehicle を含むものの定義ファイル */
	$lines[] = '===== 関数の定義場所（carmel系・型式系）=====';
	$funcs   = get_defined_functions();
	$user    = isset( $funcs['user'] ) ? $funcs['user'] : array();
	$needle  = array( 'carmel', 'lookup', 'katashiki', 'step_ui', 'vehicle', 'shaken', 'shindan' );
	$found   = false;
	foreach ( $user as $fn ) {
		$low = strtolower( $fn );
		$hit = false;
		foreach ( $needle as $n ) { if ( strpos( $low, $n ) !== false ) { $hit = true; break; } }
		if ( ! $hit ) { continue; }
		try {
			$r    = new ReflectionFunction( $fn );
			$file = $r->getFileName();
			$line = $r->getStartLine();
			$lines[] = $fn . '()   →   ' . ( $file ? $file : '(不明)' ) . ':' . $line;
			$found   = true;
		} catch ( Exception $e ) {}
	}
	if ( ! $found ) { $lines[] = '(該当する関数が見つかりません)'; }

	/* 2) 型式検索のAJAXアクションが、どのファイルのどの関数に紐づくか */
	$lines[] = '';
	$lines[] = '===== AJAXアクションの登録先（lookup/vehicle/型式 候補）=====';
	global $wp_filter;
	$actions = array(
		'wp_ajax_carmel_lookup_vehicle',
		'wp_ajax_carmel_lookup',
		'wp_ajax_carmel_katashiki',
		'wp_ajax_carmel_vehicle_lookup',
		'wp_ajax_carmel_get_vehicle',
	);
	/* 念のため、登録済みフックの中から carmel を含むものも全部拾う */
	if ( isset( $wp_filter ) && is_array( $wp_filter ) ) {
		foreach ( $wp_filter as $tag => $obj ) {
			$lt = strtolower( $tag );
			if ( strpos( $lt, 'carmel' ) === false && strpos( $lt, 'lookup' ) === false && strpos( $lt, 'katashiki' ) === false ) { continue; }
			if ( strpos( $lt, 'wp_ajax' ) === false ) { continue; }
			if ( ! in_array( $tag, $actions, true ) ) { $actions[] = $tag; }
		}
	}
	$any = false;
	foreach ( $actions as $tag ) {
		if ( empty( $wp_filter[ $tag ] ) ) { continue; }
		foreach ( $wp_filter[ $tag ]->callbacks as $prio => $cbs ) {
			foreach ( $cbs as $cb ) {
				$f    = $cb['function'];
				$name = '';
				$file = '';
				$ln   = '';
				try {
					if ( is_string( $f ) ) {
						$name = $f; $rr = new ReflectionFunction( $f );
						$file = $rr->getFileName(); $ln = $rr->getStartLine();
					} elseif ( is_array( $f ) ) {
						$cls  = is_object( $f[0] ) ? get_class( $f[0] ) : $f[0];
						$name = $cls . '::' . $f[1];
						$rr   = new ReflectionMethod( $cls, $f[1] );
						$file = $rr->getFileName(); $ln = $rr->getStartLine();
					} elseif ( $f instanceof Closure ) {
						$name = '(無名関数)'; $rr = new ReflectionFunction( $f );
						$file = $rr->getFileName(); $ln = $rr->getStartLine();
					}
				} catch ( Exception $e ) {}
				$lines[] = $tag . '  →  ' . $name . '  @  ' . $file . ':' . $ln;
				$any = true;
			}
		}
	}
	if ( ! $any ) { $lines[] = '(型式検索系のAJAXは見つかりませんでした)'; }

	/* 3) 参考情報：使用中テーマ */
	$lines[] = '';
	$lines[] = '===== 参考：使用中テーマ =====';
	$theme   = wp_get_theme();
	$lines[] = 'テーマ名 : ' . $theme->get( 'Name' );
	$lines[] = 'テーマDir: ' . get_stylesheet_directory();

	$out = implode( "\n", $lines );
	?>
	<div style="position:relative;margin:16px 0;border:2px solid #1f2d3d;border-radius:8px;background:#0f1722;color:#cfe3ff;font:12px/1.5 monospace;">
		<h3 style="margin:0;padding:8px 12px;background:#1f2d3d;color:#fff;font-size:13px;">🧭 カーメル コード場所さがし（この内容を全部コピーして送ってください）</h3>
		<div style="padding:10px 12px;">
			<textarea readonly style="width:100%;height:320px;background:#0f1722;color:#cfe3ff;border:0;font:12px/1.5 monospace;"><?php echo esc_textarea( $out ); ?></textarea>
		</div>
	</div>
	<?php
}
