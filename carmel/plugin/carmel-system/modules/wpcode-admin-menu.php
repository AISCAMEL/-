<?php
/**
 * カーメル：管理画面メニュー整理（スタッフ用＋管理者用の両対応）
 * ---------------------------------------------------------------------------
 * wp-admin の左メニューを「必要なものだけ」に絞ってスッキリ表示。
 *   ・スタッフ（管理者以外）と、管理者（自分）で、それぞれ表示/非表示を設定可能。
 *   ・管理者側は安全策として、ダッシュボード・在庫・メディア・プラグイン・設定は
 *     必ず表示（＝設定に戻れる＝ロックアウトしない）。
 *   ・設定 → カーメル メニュー整理 で、今あるメニューを自動一覧化し、1画面で
 *     「スタッフに表示／自分に表示」をチェックで切替。初期値はおすすめ。
 *   ・隠すのは表示だけ。データ・機能は消しません（直URLでは到達可）。無効化で元通り。
 * 管理者専用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'CARMEL_ADMINMENU_OPT' ) ) { define( 'CARMEL_ADMINMENU_OPT', 'carmel_adminmenu_opts2' ); }

/* スタッフに必ず見せる“必須”（タイトな既定） */
if ( ! function_exists( 'carmelx_adminmenu_staff_keep' ) ) {
	function carmelx_adminmenu_staff_keep() {
		return array( 'index.php', 'edit.php?post_type=portfolio', 'upload.php', 'profile.php' );
	}
}
/* 管理者で“絶対に隠さない”安全セット（戻れるように） */
if ( ! function_exists( 'carmelx_adminmenu_admin_keep' ) ) {
	function carmelx_adminmenu_admin_keep() {
		return array( 'index.php', 'edit.php?post_type=portfolio', 'upload.php', 'plugins.php', 'options-general.php' );
	}
}
/* 管理者の初期非表示＝明らかなノイズ（SEO/解析/補助系）だけ。機能メニューは残す */
if ( ! function_exists( 'carmelx_adminmenu_admin_noise' ) ) {
	function carmelx_adminmenu_admin_noise( $slug, $label ) {
		$hay = strtolower( $slug . ' ' . $label );
		return (bool) preg_match( '/(wpseo|yoast|clarity|theme.?panel|wpex|google.?sheet|sheet.?connector|site.?kit|sitekit|mail.?smtp|\bsmtp\b|llar|limit.?login|activity.?log|redirection|wps_|wp-staging|wpstg)/i', $hay );
	}
}

if ( ! function_exists( 'carmelx_adminmenu_label' ) ) {
	function carmelx_adminmenu_label( $title ) {
		$t = preg_replace( '/<span[^>]*>.*?<\/span>/is', '', (string) $title );
		$t = wp_strip_all_tags( $t );
		$t = preg_replace( '/^[^\p{L}\p{N}]+/u', '', trim( $t ) );
		return trim( $t );
	}
}
if ( ! function_exists( 'carmelx_adminmenu_list' ) ) {
	function carmelx_adminmenu_list() {
		global $menu;
		$out = array();
		if ( empty( $menu ) || ! is_array( $menu ) ) { return $out; }
		foreach ( $menu as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug || false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			$label = carmelx_adminmenu_label( isset( $m[0] ) ? $m[0] : $slug );
			$out[ $slug ] = ( '' !== $label ) ? $label : $slug;
		}
		return $out;
	}
}

if ( ! function_exists( 'carmelx_adminmenu_opts' ) ) {
	function carmelx_adminmenu_opts() {
		$o = get_option( CARMEL_ADMINMENU_OPT );
		if ( ! is_array( $o ) ) { $o = array(); }
		return array(
			'staff_on'     => isset( $o['staff_on'] ) ? (int) $o['staff_on'] : 1,
			'admin_on'     => isset( $o['admin_on'] ) ? (int) $o['admin_on'] : 1,
			'hidden_staff' => isset( $o['hidden_staff'] ) && is_array( $o['hidden_staff'] ) ? array_values( $o['hidden_staff'] ) : null,
			'hidden_admin' => isset( $o['hidden_admin'] ) && is_array( $o['hidden_admin'] ) ? array_values( $o['hidden_admin'] ) : null,
		);
	}
}

/* 表示判定（true=隠す） */
if ( ! function_exists( 'carmelx_adminmenu_hide_staff' ) ) {
	function carmelx_adminmenu_hide_staff( $slug, $hidden ) {
		if ( in_array( $slug, carmelx_adminmenu_staff_keep(), true ) ) { return false; }
		if ( null === $hidden ) { return true; }        // 既定：必須以外は隠す
		return in_array( $slug, $hidden, true );
	}
}
if ( ! function_exists( 'carmelx_adminmenu_hide_admin' ) ) {
	function carmelx_adminmenu_hide_admin( $slug, $label, $hidden ) {
		if ( in_array( $slug, carmelx_adminmenu_admin_keep(), true ) ) { return false; } // 安全セットは常に表示
		if ( null === $hidden ) { return carmelx_adminmenu_admin_noise( $slug, $label ); } // 既定：ノイズだけ隠す
		return in_array( $slug, $hidden, true );
	}
}

/* ---- 適用 ---- */
if ( ! function_exists( 'carmelx_adminmenu_apply' ) ) {
	function carmelx_adminmenu_apply() {
		global $menu;
		if ( empty( $menu ) || ! is_array( $menu ) ) { return; }
		$o = carmelx_adminmenu_opts();
		$is_admin_user = current_user_can( 'manage_options' );
		if ( $is_admin_user && empty( $o['admin_on'] ) ) { return; }
		if ( ! $is_admin_user && empty( $o['staff_on'] ) ) { return; }

		foreach ( $menu as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug || false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			$label = carmelx_adminmenu_label( isset( $m[0] ) ? $m[0] : $slug );
			$hide = $is_admin_user
				? carmelx_adminmenu_hide_admin( $slug, $label, $o['hidden_admin'] )
				: carmelx_adminmenu_hide_staff( $slug, $o['hidden_staff'] );
			if ( $hide ) { remove_menu_page( $slug ); }
		}
	}
	add_action( 'admin_menu', 'carmelx_adminmenu_apply', 9999 );
}

/* ---- 設定ページ ---- */
if ( ! function_exists( 'carmelx_adminmenu_add_page' ) ) {
	function carmelx_adminmenu_add_page() {
		add_options_page( 'カーメル メニュー整理', 'カーメル メニュー整理', 'manage_options', 'carmel-adminmenu', 'carmelx_adminmenu_render' );
	}
	add_action( 'admin_menu', 'carmelx_adminmenu_add_page', 100 );
}

if ( ! function_exists( 'carmelx_adminmenu_render' ) ) {
	function carmelx_adminmenu_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$list = carmelx_adminmenu_list();

		if ( isset( $_POST['carmel_adminmenu_nonce'] ) && wp_verify_nonce( $_POST['carmel_adminmenu_nonce'], 'carmel_adminmenu_save' ) ) {
			if ( isset( $_POST['carmel_reset'] ) ) {
				delete_option( CARMEL_ADMINMENU_OPT );
				echo '<div class="notice notice-success is-dismissible"><p>おすすめ初期設定に戻しました。</p></div>';
			} else {
				$all = array_keys( $list );
				$show_staff = isset( $_POST['show_staff'] ) && is_array( $_POST['show_staff'] ) ? array_map( 'strval', (array) $_POST['show_staff'] ) : array();
				$show_admin = isset( $_POST['show_admin'] ) && is_array( $_POST['show_admin'] ) ? array_map( 'strval', (array) $_POST['show_admin'] ) : array();
				$hidden_staff = array(); $hidden_admin = array();
				foreach ( $all as $slug ) {
					if ( ! in_array( $slug, carmelx_adminmenu_staff_keep(), true ) && ! in_array( $slug, $show_staff, true ) ) { $hidden_staff[] = $slug; }
					if ( ! in_array( $slug, carmelx_adminmenu_admin_keep(), true ) && ! in_array( $slug, $show_admin, true ) ) { $hidden_admin[] = $slug; }
				}
				update_option( CARMEL_ADMINMENU_OPT, array(
					'staff_on'     => isset( $_POST['staff_on'] ) ? 1 : 0,
					'admin_on'     => isset( $_POST['admin_on'] ) ? 1 : 0,
					'hidden_staff' => array_values( $hidden_staff ),
					'hidden_admin' => array_values( $hidden_admin ),
				) );
				echo '<div class="notice notice-success is-dismissible"><p>保存しました。表示に反映されます（自分の画面は再読み込みで反映）。</p></div>';
			}
		}

		$o = carmelx_adminmenu_opts();
		?>
		<div class="wrap">
			<h1>カーメル メニュー整理</h1>
			<p style="max-width:820px;">左メニューを「必要なものだけ」にスッキリさせます。<b>スタッフ用</b>と<b>自分（管理者）用</b>を分けて設定できます。
			管理者は安全のため<b>ダッシュボード・在庫・メディア・プラグイン・設定は常に表示</b>（＝ここへ戻れます）。隠すのは表示だけで、データ・機能は消えません。</p>

			<form method="post">
				<?php wp_nonce_field( 'carmel_adminmenu_save', 'carmel_adminmenu_nonce' ); ?>
				<p>
					<label style="margin-right:20px;font-weight:600;"><input type="checkbox" name="staff_on" value="1" <?php checked( ! empty( $o['staff_on'] ) ); ?>> スタッフの画面を整理する</label>
					<label style="font-weight:600;"><input type="checkbox" name="admin_on" value="1" <?php checked( ! empty( $o['admin_on'] ) ); ?>> 自分（管理者）の画面も整理する</label>
				</p>
				<table class="widefat striped" style="max-width:820px;margin:12px 0;">
					<thead><tr><th style="width:90px;text-align:center;">スタッフに<br>表示</th><th style="width:90px;text-align:center;">自分に<br>表示</th><th>メニュー項目</th></tr></thead>
					<tbody>
					<?php foreach ( $list as $slug => $label ) :
						$staff_forced = in_array( $slug, carmelx_adminmenu_staff_keep(), true );
						$admin_forced = in_array( $slug, carmelx_adminmenu_admin_keep(), true );
						$staff_shown  = $staff_forced ? true : ! carmelx_adminmenu_hide_staff( $slug, $o['hidden_staff'] );
						$admin_shown  = $admin_forced ? true : ! carmelx_adminmenu_hide_admin( $slug, $label, $o['hidden_admin'] );
						?>
						<tr>
							<td style="text-align:center;">
								<input type="checkbox" name="show_staff[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $staff_shown ); ?> <?php disabled( $staff_forced ); ?>>
								<?php if ( $staff_forced ) : ?><input type="hidden" name="show_staff[]" value="<?php echo esc_attr( $slug ); ?>"><?php endif; ?>
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="show_admin[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $admin_shown ); ?> <?php disabled( $admin_forced ); ?>>
								<?php if ( $admin_forced ) : ?><input type="hidden" name="show_admin[]" value="<?php echo esc_attr( $slug ); ?>"><?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( $label ); ?></strong> <code style="color:#999;font-size:11px;"><?php echo esc_html( $slug ); ?></code><?php echo ( $staff_forced || $admin_forced ) ? ' <span style="color:#1c7a3a;font-size:11px;">必須</span>' : ''; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="submit" class="button button-primary">保存する</button>
					&nbsp;
					<button type="submit" name="carmel_reset" value="1" class="button" onclick="return confirm('おすすめ初期設定に戻します。よろしいですか？');">おすすめに戻す</button>
				</p>
				<p style="color:#777;font-size:12px;max-width:820px;">
					・チェックＯＮ＝表示／ＯＦＦ＝非表示。<br>
					・スタッフの初期値：在庫・メディア等の必須のみ表示。<br>
					・自分の初期値：SEO/解析/補助系など“ノイズ”だけ非表示（機能メニューは残す）。<br>
					・この設定ページは管理者のみ表示されます。
				</p>
			</form>
		</div>
		<?php
	}
}
