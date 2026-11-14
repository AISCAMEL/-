<?php
/**
 * カーメル：諸経費設定（旧「見積初期費用」）
 * ---------------------------------------------------------------------------
 * AS-NET「かんたん見積作成」設定画面と同じ構成。普通自動車／軽自動車の2列で
 * 各費用の初期値を管理。STEP3見積もりや点検整備A/B選択の元データになる。
 * 値は wp_options 'carmel_fee_settings' に保存。ACF Pro 不要。
 *
 * 公開API: carmel_get_fee_settings()
 * 導入   : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

/* 車種別の費用項目（key => [ラベル, グループ]） */
function carmel_fee_items() {
	return array(
		'yotei_rieki'     => array( '予定利益（上乗せ・税抜）', 'rieki' ),
		'shaken_seibi'    => array( '車検整備費用（税抜）',     'seibi' ),
		'nousha_seibi'    => array( '納車整備費用（税抜）',     'seibi' ),
		'kensa_touroku'   => array( '検査登録（印紙代）',       'houtei' ),
		'shako_inshi'     => array( '車庫証明（印紙代）',       'houtei' ),
		'shitadori_inshi' => array( '下取車手続・処分（印紙代）', 'houtei' ),
		'number_dai'      => array( 'ナンバー代',               'houtei' ),
		'kibou_number'    => array( '希望ナンバー（OP）',       'houtei' ),
		'kensa_daiko'     => array( '検査登録手続代行（税抜）', 'daiko' ),
		'shako_daiko'     => array( '車庫証明手続代行（税抜）', 'daiko' ),
		'shitadori_daiko' => array( '下取車手続・処分代行（税抜）', 'daiko' ),
		'shitadori_satei' => array( '下取車査定料（税抜）',     'daiko' ),
		'shikin_kanri'    => array( '資金管理料金（税抜）',     'daiko' ),
		'nousha'          => array( '納車費用（税抜）',         'daiko' ),
		'mccs'            => array( 'MCCS（税抜）',             'daiko' ),
		'kengai'          => array( '県外登録費（税抜）',       'daiko' ),
		'hoshou_hiyou'    => array( '中古車保証費用（税抜）',   'hoshou' ),
	);
}

function carmel_fee_group_labels() {
	return array(
		'rieki'  => '予定利益',
		'seibi'  => '整備費用（税抜）',
		'houtei' => '預り法定費用（非課税）',
		'daiko'  => '手続代行費用（税抜）',
		'hoshou' => '保証',
	);
}

/* 初期値 */
function carmel_fee_defaults() {
	$futsu = array(
		'yotei_rieki' => 150000, 'shaken_seibi' => 100000, 'nousha_seibi' => 50000,
		'kensa_touroku' => 1800, 'shako_inshi' => 2750, 'shitadori_inshi' => 0,
		'number_dai' => 4400, 'kibou_number' => 10000,
		'kensa_daiko' => 16500, 'shako_daiko' => 0, 'shitadori_daiko' => 0,
		'shitadori_satei' => 0, 'shikin_kanri' => 0, 'nousha' => 38500,
		'mccs' => 80000, 'kengai' => 50000, 'hoshou_hiyou' => 0,
	);
	$kei = $futsu;
	$kei['yotei_rieki'] = 100000;
	$kei['shako_inshi'] = 0; // 軽は車庫証明印紙なし
	return array(
		'tax_mode'      => 'excl',  // excl=税抜 / incl=税込
		'teiki_tenken'  => 'yes',
		'hoshou_umu'    => 'no',
		'hoshou_naiyou' => '',
		'jibai_months'  => 25,
		'loan_rate'     => 12.5,
		'tax_rate'      => 10,
		'shop'          => array(
			'name' => 'カーメル', 'address' => '福島県いわき市四倉町細谷字大町1番',
			'tel' => '050-1807-2533', 'tantou' => '吉田一平', 'sekinin' => '吉田一平',
			'url' => 'carmelonline.jp',
		),
		'futsu'         => $futsu,
		'kei'           => $kei,
	);
}

/* 設定値（デフォルト補完）取得 */
function carmel_get_fee_settings() {
	$saved = get_option( 'carmel_fee_settings', array() );
	$def   = carmel_fee_defaults();
	$out   = array(
		'tax_mode'      => isset( $saved['tax_mode'] ) ? $saved['tax_mode'] : $def['tax_mode'],
		'teiki_tenken'  => isset( $saved['teiki_tenken'] ) ? $saved['teiki_tenken'] : $def['teiki_tenken'],
		'hoshou_umu'    => isset( $saved['hoshou_umu'] ) ? $saved['hoshou_umu'] : $def['hoshou_umu'],
		'hoshou_naiyou' => isset( $saved['hoshou_naiyou'] ) ? $saved['hoshou_naiyou'] : $def['hoshou_naiyou'],
		'jibai_months'  => isset( $saved['jibai_months'] ) ? $saved['jibai_months'] : $def['jibai_months'],
		'loan_rate'     => isset( $saved['loan_rate'] ) ? $saved['loan_rate'] : $def['loan_rate'],
		'tax_rate'      => isset( $saved['tax_rate'] ) ? $saved['tax_rate'] : $def['tax_rate'],
		'shop'          => array(),
		'futsu'         => array(),
		'kei'           => array(),
	);
	foreach ( $def['shop'] as $k => $val ) {
		$out['shop'][ $k ] = isset( $saved['shop'][ $k ] ) ? $saved['shop'][ $k ] : $val;
	}
	foreach ( array( 'futsu', 'kei' ) as $type ) {
		foreach ( array_keys( carmel_fee_items() ) as $k ) {
			$out[ $type ][ $k ] = ( isset( $saved[ $type ][ $k ] ) && $saved[ $type ][ $k ] !== '' )
				? (int) $saved[ $type ][ $k ]
				: (int) $def[ $type ][ $k ];
		}
	}
	return $out;
}

add_action( 'admin_menu', 'carmel_fee_settings_menu' );
function carmel_fee_settings_menu() {
	add_menu_page( '諸経費設定', '💴 諸経費設定', 'manage_options', 'carmel-fee-settings', 'carmel_fee_settings_render', 'dashicons-money-alt', 58 );
}

add_action( 'admin_init', 'carmel_fee_settings_register' );
function carmel_fee_settings_register() {
	register_setting( 'carmel_fee_settings_group', 'carmel_fee_settings', 'carmel_fee_settings_sanitize' );
}

function carmel_fee_settings_sanitize( $in ) {
	$out = array(
		'tax_mode'      => ( isset( $in['tax_mode'] ) && $in['tax_mode'] === 'incl' ) ? 'incl' : 'excl',
		'teiki_tenken'  => ( isset( $in['teiki_tenken'] ) && $in['teiki_tenken'] === 'no' ) ? 'no' : 'yes',
		'hoshou_umu'    => ( isset( $in['hoshou_umu'] ) && $in['hoshou_umu'] === 'yes' ) ? 'yes' : 'no',
		'hoshou_naiyou' => isset( $in['hoshou_naiyou'] ) ? sanitize_text_field( $in['hoshou_naiyou'] ) : '',
		'jibai_months'  => isset( $in['jibai_months'] ) ? (int) $in['jibai_months'] : 25,
		'loan_rate'     => isset( $in['loan_rate'] ) ? (float) $in['loan_rate'] : 12.5,
		'tax_rate'      => isset( $in['tax_rate'] ) ? (float) $in['tax_rate'] : 10,
		'shop'          => array(),
		'futsu'         => array(),
		'kei'           => array(),
	);
	foreach ( array( 'name', 'address', 'tel', 'tantou', 'sekinin', 'url' ) as $k ) {
		$out['shop'][ $k ] = isset( $in['shop'][ $k ] ) ? sanitize_text_field( $in['shop'][ $k ] ) : '';
	}
	foreach ( array( 'futsu', 'kei' ) as $type ) {
		foreach ( array_keys( carmel_fee_items() ) as $k ) {
			$val = isset( $in[ $type ][ $k ] ) ? preg_replace( '/[^0-9]/', '', (string) $in[ $type ][ $k ] ) : '';
			$out[ $type ][ $k ] = ( $val === '' ) ? 0 : (int) $val;
		}
	}
	return $out;
}

function carmel_fee_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$v      = carmel_get_fee_settings();
	$items  = carmel_fee_items();
	$glabel = carmel_fee_group_labels();
	?>
	<div class="wrap">
		<h1>💴 諸経費設定（普通自動車 / 軽自動車）</h1>
		<p>この設定が STEP3 見積もりや点検整備A/B（普通車=A・軽=B）の初期値になります。AS-NET見積画面と同じ構成です。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'carmel_fee_settings_group' ); ?>

			<h2>課税対象金額の入力方法</h2>
			<label><input type="radio" name="carmel_fee_settings[tax_mode]" value="incl" <?php checked( $v['tax_mode'], 'incl' ); ?>> 税込みで入力</label>
			<label><input type="radio" name="carmel_fee_settings[tax_mode]" value="excl" <?php checked( $v['tax_mode'], 'excl' ); ?>> 税抜きで入力</label>

			<table class="widefat striped" style="max-width:760px;margin-top:14px;">
				<thead><tr><th>費用項目</th><th style="width:150px;">普通自動車</th><th style="width:150px;">軽自動車</th></tr></thead>
				<tbody>
				<?php
				$curgrp = '';
				foreach ( $items as $key => $def ) :
					if ( $def[1] !== $curgrp ) {
						$curgrp = $def[1];
						echo '<tr><td colspan="3" style="background:#eef1f4;font-weight:700;">' . esc_html( $glabel[ $curgrp ] ) . '</td></tr>';
						if ( $curgrp === 'seibi' ) {
							echo '<tr><td>定期点検整備の有無</td><td colspan="2">'
								. '<label><input type="radio" name="carmel_fee_settings[teiki_tenken]" value="yes" ' . checked( $v['teiki_tenken'], 'yes', false ) . '> 有</label>　'
								. '<label><input type="radio" name="carmel_fee_settings[teiki_tenken]" value="no" ' . checked( $v['teiki_tenken'], 'no', false ) . '> 無</label></td></tr>';
						}
						if ( $curgrp === 'hoshou' ) {
							echo '<tr><td>保証の有無</td><td colspan="2">'
								. '<label><input type="radio" name="carmel_fee_settings[hoshou_umu]" value="yes" ' . checked( $v['hoshou_umu'], 'yes', false ) . '> 有</label>　'
								. '<label><input type="radio" name="carmel_fee_settings[hoshou_umu]" value="no" ' . checked( $v['hoshou_umu'], 'no', false ) . '> 無</label></td></tr>';
							echo '<tr><td>保証の内容</td><td colspan="2"><input type="text" name="carmel_fee_settings[hoshou_naiyou]" value="' . esc_attr( $v['hoshou_naiyou'] ) . '" style="width:100%;"></td></tr>';
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $def[0] ); ?></td>
						<td><input type="number" name="carmel_fee_settings[futsu][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $v['futsu'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
						<td><input type="number" name="carmel_fee_settings[kei][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $v['kei'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>その他の設定</h2>
			<table class="form-table" style="max-width:760px;">
				<tr><th>自賠責保険料の算出月数</th><td><input type="number" name="carmel_fee_settings[jibai_months]" value="<?php echo esc_attr( $v['jibai_months'] ); ?>" style="width:80px;"> ヶ月（車検無しの車両の場合）</td></tr>
				<tr><th>ローン計算標準金利</th><td><input type="number" step="0.1" name="carmel_fee_settings[loan_rate]" value="<?php echo esc_attr( $v['loan_rate'] ); ?>" style="width:80px;"> ％</td></tr>
				<tr><th>消費税率</th><td><input type="number" step="0.1" name="carmel_fee_settings[tax_rate]" value="<?php echo esc_attr( $v['tax_rate'] ); ?>" style="width:80px;"> ％</td></tr>
			</table>

			<h2>販売店情報（見積書に表示する内容）</h2>
			<table class="form-table" style="max-width:760px;">
				<tr><th>販売店名</th><td><input type="text" name="carmel_fee_settings[shop][name]" value="<?php echo esc_attr( $v['shop']['name'] ); ?>" style="width:100%;"></td></tr>
				<tr><th>住所</th><td><input type="text" name="carmel_fee_settings[shop][address]" value="<?php echo esc_attr( $v['shop']['address'] ); ?>" style="width:100%;"></td></tr>
				<tr><th>電話番号</th><td><input type="text" name="carmel_fee_settings[shop][tel]" value="<?php echo esc_attr( $v['shop']['tel'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>見積担当者</th><td><input type="text" name="carmel_fee_settings[shop][tantou]" value="<?php echo esc_attr( $v['shop']['tantou'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>責任者</th><td><input type="text" name="carmel_fee_settings[shop][sekinin]" value="<?php echo esc_attr( $v['shop']['sekinin'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>販売店URL</th><td><input type="text" name="carmel_fee_settings[shop][url]" value="<?php echo esc_attr( $v['shop']['url'] ); ?>" style="width:100%;"></td></tr>
			</table>

			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<?php
}
