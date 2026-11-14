<?php
/**
 * カーメル：管理画面メニュー整理（スタッフ用シンプル化）
 * ---------------------------------------------------------------------------
 * wp-admin の左メニューを、スタッフには「必要なものだけ」に絞ってシンプルに表示。
 *   ・オーナー（管理者＝manage_options）は常に全部表示 → ロックアウトしない安全設計。
 *   ・スタッフ（管理者以外）には、設定で「非表示」にした項目を隠す。
 *   ・設定画面（設定 → カーメル メニュー整理）で、今あるメニューを自動一覧化し、
 *     表示/非表示をチェックで切替。初期値はおすすめ（在庫・メディア等だけ表示）。
 *
 * ※ メニューを隠すだけで、データや機能は消しません（直URLでは到達可能）。
 *    無効化すれば元通り。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'CARMEL_ADMINMENU_OPT' ) ) { define( 'CARMEL_ADMINMENU_OPT', 'carmel_adminmenu_opts' ); }

/* スタッフにも必ず見せる“必須”メニュー（スラッグ） */
if ( ! function_exists( 'carmelx_adminmenu_essentials' ) ) {
	function carmelx_adminmenu_essentials() {
		return array(
			'index.php',                         // ダッシュボード
			'edit.php?post_type=portfolio',      // 在庫
			'upload.php',                        // メディア
			'profile.php',                       // プロフィール
		);
	}
	function carmelx_adminmenu_is_essential( $slug ) {
		return in_array( $slug, carmelx_adminmenu_essentials(), true );
	}
}

/* メニュー項目のラベルを装飾なしで取得（通知バッジ等のHTMLを除去） */
if ( ! function_exists( 'carmelx_adminmenu_label' ) ) {
	function carmelx_adminmenu_label( $title ) {
		$t = preg_replace( '/<span[^>]*>.*?<\/span>/is', '', (string) $title ); // 件数バッジ除去
		$t = wp_strip_all_tags( $t );
		$t = preg_replace( '/^[^\p{L}\p{N}]+/u', '', trim( $t ) ); // 先頭の絵文字/記号
		return trim( $t );
	}
}

/* 現在の最上位メニュー一覧（セパレータ除く）。[slug=>label] */
if ( ! function_exists( 'carmelx_adminmenu_list' ) ) {
	function carmelx_adminmenu_list() {
		global $menu;
		$out = array();
		if ( empty( $menu ) || ! is_array( $menu ) ) { return $out; }
		foreach ( $menu as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug ) { continue; }
			if ( false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			$label = carmelx_adminmenu_label( isset( $m[0] ) ? $m[0] : $slug );
			if ( '' === $label ) { $label = $slug; }
			$out[ $slug ] = $label;
		}
		return $out;
	}
}

/* 現在の設定。未設定なら enabled=1 / hidden=null（＝おすすめ既定を適用） */
if ( ! function_exists( 'carmelx_adminmenu_opts' ) ) {
	function carmelx_adminmenu_opts() {
		$o = get_option( CARMEL_ADMINMENU_OPT );
		if ( ! is_array( $o ) ) { return array( 'enabled' => 1, 'hidden' => null ); }
		return array(
			'enabled' => isset( $o['enabled'] ) ? (int) $o['enabled'] : 1,
			'hidden'  => isset( $o['hidden'] ) && is_array( $o['hidden'] ) ? array_values( $o['hidden'] ) : null,
		);
	}
}

/* ある slug をスタッフに隠すか？ */
if ( ! function_exists( 'carmelx_adminmenu_should_hide' ) ) {
	function carmelx_adminmenu_should_hide( $slug, $hidden ) {
		if ( carmelx_adminmenu_is_essential( $slug ) ) { return false; } // 必須は常に表示
		if ( null === $hidden ) { return true; }                         // 既定：必須以外は隠す
		return in_array( $slug, $hidden, true );                         // 保存済み：明示分だけ隠す
	}
}

/* ---- 適用：スタッフ（管理者以外）に対して非表示メニューを除去 ---- */
if ( ! function_exists( 'carmelx_adminmenu_apply' ) ) {
	function carmelx_adminmenu_apply() {
		$o = carmelx_adminmenu_opts();
		if ( empty( $o['enabled'] ) ) { return; }          // 整理OFF
		if ( current_user_can( 'manage_options' ) ) { return; } // オーナーは全部表示
		global $menu;
		if ( empty( $menu ) || ! is_array( $menu ) ) { return; }
		foreach ( $menu as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug ) { continue; }
			if ( false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			if ( carmelx_adminmenu_should_hide( $slug, $o['hidden'] ) ) {
				remove_menu_page( $slug );
			}
		}
	}
	add_action( 'admin_menu', 'carmelx_adminmenu_apply', 9999 );
}

/* ---- 設定ページ（設定 → カーメル メニュー整理）オーナー専用 ---- */
if ( ! function_exists( 'carmelx_adminmenu_add_page' ) ) {
	function carmelx_adminmenu_add_page() {
		add_options_page( 'カーメル メニュー整理', 'カーメル メニュー整理', 'manage_options', 'carmel-adminmenu', 'carmelx_adminmenu_render' );
	}
	add_action( 'admin_menu', 'carmelx_adminmenu_add_page', 100 );
}

if ( ! function_exists( 'carmelx_adminmenu_render' ) ) {
	function carmelx_adminmenu_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// 保存処理
		if ( isset( $_POST['carmel_adminmenu_nonce'] ) && wp_verify_nonce( $_POST['carmel_adminmenu_nonce'], 'carmel_adminmenu_save' ) ) {
			if ( isset( $_POST['carmel_reset'] ) ) {
				delete_option( CARMEL_ADMINMENU_OPT );
				echo '<div class="notice notice-success is-dismissible"><p>おすすめ初期設定に戻しました。</p></div>';
			} else {
				$all  = array_keys( carmelx_adminmenu_list() );
				$show = isset( $_POST['carmel_show'] ) && is_array( $_POST['carmel_show'] ) ? array_map( 'wp_unslash', $_POST['carmel_show'] ) : array();
				$hidden = array();
				foreach ( $all as $slug ) {
					if ( carmelx_adminmenu_is_essential( $slug ) ) { continue; } // 必須は常に表示＝隠さない
					if ( ! in_array( $slug, $show, true ) ) { $hidden[] = $slug; }
				}
				update_option( CARMEL_ADMINMENU_OPT, array(
					'enabled' => isset( $_POST['carmel_enabled'] ) ? 1 : 0,
					'hidden'  => array_values( $hidden ),
				) );
				echo '<div class="notice notice-success is-dismissible"><p>保存しました。スタッフの画面に反映されます。</p></div>';
			}
		}

		$o    = carmelx_adminmenu_opts();
		$list = carmelx_adminmenu_list();
		?>
		<div class="wrap">
			<h1>カーメル メニュー整理</h1>
			<p style="max-width:760px;">スタッフ（管理者以外）の左メニューを、必要なものだけに絞ってシンプルにします。
			<b>あなた（管理者）は常にすべて表示</b>されるので、ここで自由に調整できます。隠すのは表示だけで、データ・機能は消えません。</p>

			<form method="post">
				<?php wp_nonce_field( 'carmel_adminmenu_save', 'carmel_adminmenu_nonce' ); ?>
				<p>
					<label style="font-weight:600;">
						<input type="checkbox" name="carmel_enabled" value="1" <?php checked( ! empty( $o['enabled'] ) ); ?>>
						スタッフの画面をシンプルにする（整理を有効にする）
					</label>
				</p>

				<table class="widefat striped" style="max-width:760px;margin:12px 0;">
					<thead><tr><th style="width:90px;">スタッフに表示</th><th>メニュー項目</th><th style="width:140px;">状態</th></tr></thead>
					<tbody>
					<?php foreach ( $list as $slug => $label ) :
						$essential = carmelx_adminmenu_is_essential( $slug );
						$shown     = $essential ? true : ! carmelx_adminmenu_should_hide( $slug, $o['hidden'] );
						?>
						<tr>
							<td style="text-align:center;">
								<input type="checkbox" name="carmel_show[]" value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( $shown ); ?> <?php disabled( $essential ); ?>>
								<?php if ( $essential ) : ?><input type="hidden" name="carmel_show[]" value="<?php echo esc_attr( $slug ); ?>"><?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( $label ); ?></strong><br><code style="color:#888;font-size:11px;"><?php echo esc_html( $slug ); ?></code></td>
							<td><?php echo $essential ? '<span style="color:#1c7a3a;font-weight:700;">必須（常に表示）</span>' : ( $shown ? 'スタッフに表示' : '<span style="color:#b32d2e;">スタッフに非表示</span>' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="submit" class="button button-primary">保存する</button>
					&nbsp;
					<button type="submit" name="carmel_reset" value="1" class="button" onclick="return confirm('おすすめ初期設定（在庫・メディア等だけ表示）に戻します。よろしいですか？');">おすすめに戻す</button>
				</p>
				<p style="color:#777;font-size:12px;max-width:760px;">
					・チェックＯＮ＝スタッフに表示／ＯＦＦ＝スタッフに非表示。<br>
					・「在庫」「メディア」「ダッシュボード」「プロフィール」は必須のため常に表示（外せません）。<br>
					・この設定ページ自体は管理者のみ表示されます。
				</p>
			</form>
		</div>
		<?php
	}
}
