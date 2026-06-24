<?php
/**
 * カーメル：見積「初期費用セット」設定ページ（軽 / 普通車）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫STEP3の見積もりに最初から入れる「定型費用」を、WP管理画面の
 *        専用ページ（GUI）で編集できるようにする。ACF Pro 不要。
 *        セットは A=軽自動車 / B=普通車 の2種。値は wp_options に保存。
 *
 * 初期値 : 添付の御見積書PDF（軽＝ダイハツ テリオスキッド）を参考に設定。
 *          後から本ページでいつでも修正可能。
 *
 * 使い方 : 管理画面メニュー「💴 見積初期費用」→ 軽 / 普通車の各費用を入力 → 保存。
 *          STEP3側スニペットが本設定を読み込み、[軽セット適用]/[普通車セット適用]
 *          ボタンや新規在庫の初期反映に使う（別スニペット step3-fee-apply.php）。
 *
 * 公開API : carmel_get_fee_presets() / carmel_fee_preset_items()
 *
 * 導入 : WPCode の PHP Snippet（Run Everywhere）。
 * ---------------------------------------------------------------------------
 */

/* 定型費用の項目定義（key => [ラベル, 区分]） 区分: hikazei=非課税(預り法定) / kazei=課税(手続代行) */
function carmel_fee_preset_items() {
	return array(
		// ［3］預り法定費用（非課税）の定型分
		'kensa_touroku' => array( '検査登録（預り法定）', 'hikazei' ),
		'shako_yokari'  => array( '車庫証明（預り法定）', 'hikazei' ),
		'number_dai'    => array( 'ナンバー代',            'hikazei' ),
		'kibou_number'  => array( '希望ナンバー(OP)',      'hikazei' ),
		// ［4］手続代行費用（課税）
		'kensa_daiko'   => array( '検査登録手続',          'kazei' ),
		'shako_daiko'   => array( '車庫証明手続',          'kazei' ),
		'nousha'        => array( '納車費用',              'kazei' ),
		'mccs'          => array( 'MCCS',                  'kazei' ),
		'kengai'        => array( '県外登録費',            'kazei' ),
		'shikin_kanri'  => array( '資金管理料金',          'kazei' ),
		'sonota'        => array( 'その他費用',            'kazei' ),
	);
}

/* 初期値（PDF参考）。保存済みが無ければこれを使う */
function carmel_fee_preset_defaults() {
	$kei = array(
		'kensa_touroku' => 1800,
		'shako_yokari'  => 0,
		'number_dai'    => 4400,
		'kibou_number'  => 10000,
		'kensa_daiko'   => 16500,
		'shako_daiko'   => 0,
		'nousha'        => 38500,
		'mccs'          => 80000,
		'kengai'        => 50000,
		'shikin_kanri'  => 0,
		'sonota'        => 0,
	);
	// 普通車は初期は軽と同額（裏側で調整してください）
	$futsu = $kei;
	return array( 'kei' => $kei, 'futsu' => $futsu );
}

/* 保存値（無い項目はデフォルトで補完）を返す */
function carmel_get_fee_presets() {
	$saved    = get_option( 'carmel_fee_presets', array() );
	$defaults = carmel_fee_preset_defaults();
	$items    = array_keys( carmel_fee_preset_items() );
	$out      = array();
	foreach ( array( 'kei', 'futsu' ) as $set ) {
		$out[ $set ] = array();
		foreach ( $items as $k ) {
			if ( isset( $saved[ $set ][ $k ] ) && $saved[ $set ][ $k ] !== '' ) {
				$out[ $set ][ $k ] = (int) $saved[ $set ][ $k ];
			} else {
				$out[ $set ][ $k ] = isset( $defaults[ $set ][ $k ] ) ? (int) $defaults[ $set ][ $k ] : 0;
			}
		}
	}
	return $out;
}

/* メニュー登録 */
add_action( 'admin_menu', 'carmel_fee_presets_menu' );
function carmel_fee_presets_menu() {
	add_menu_page(
		'見積初期費用セット',
		'💴 見積初期費用',
		'manage_options',
		'carmel-fee-presets',
		'carmel_fee_presets_render',
		'dashicons-money-alt',
		58
	);
}

/* 設定登録 */
add_action( 'admin_init', 'carmel_fee_presets_register' );
function carmel_fee_presets_register() {
	register_setting( 'carmel_fee_presets_group', 'carmel_fee_presets', 'carmel_fee_presets_sanitize' );
}

function carmel_fee_presets_sanitize( $input ) {
	$items = array_keys( carmel_fee_preset_items() );
	$out   = array();
	foreach ( array( 'kei', 'futsu' ) as $set ) {
		foreach ( $items as $k ) {
			$v = isset( $input[ $set ][ $k ] ) ? preg_replace( '/[^0-9\-]/', '', (string) $input[ $set ][ $k ] ) : '';
			$out[ $set ][ $k ] = ( $v === '' ) ? 0 : (int) $v;
		}
	}
	return $out;
}

/* 画面描画 */
function carmel_fee_presets_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$vals  = carmel_get_fee_presets();
	$items = carmel_fee_preset_items();
	?>
	<div class="wrap">
		<h1>💴 見積初期費用セット（軽 / 普通車）</h1>
		<p>在庫の見積もりに最初から入れる「定型費用」です。STEP3で <b>軽セット / 普通車セット</b> として適用されます。<br>
		   税金・自賠責・リサイクル預託金など<b>車ごとに変わる費用は含みません</b>（車両側で入力）。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'carmel_fee_presets_group' ); ?>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr><th>費用項目</th><th>区分</th><th style="width:150px;">軽自動車（A）</th><th style="width:150px;">普通車（B）</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $key => $def ) :
					$label = $def[0];
					$kbn   = ( $def[1] === 'hikazei' ) ? '非課税' : '課税';
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><?php echo esc_html( $kbn ); ?></td>
						<td><input type="number" name="carmel_fee_presets[kei][<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $vals['kei'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
						<td><input type="number" name="carmel_fee_presets[futsu][<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $vals['futsu'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<?php
}
