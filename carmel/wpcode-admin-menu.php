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
 *   ・隠した項目は「すべてのメニュー」ページ（左メニュー上部）からいつでも開けます。
 *   ・旧「CARMEL 自動生成」プラグインが隠していた項目（carmel_auto_settings の hidden_menus）を
 *     引き継ぎます。旧プラグインを止めても、左メニューが元の長さに戻りません。
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

/* 「引き継ぎだけ」モード：旧プラグインが隠していた項目だけを隠し、それ以外は何も変えない。
   軽量版「カーメル 在庫連携」から読み込まれた時に使う（本番の左メニューを今と同じに保つため）。 */
if ( ! function_exists( 'carmelx_adminmenu_inherit_only' ) ) {
	function carmelx_adminmenu_inherit_only() {
		return defined( 'CARMEL_ADMINMENU_INHERIT_ONLY' ) && CARMEL_ADMINMENU_INHERIT_ONLY;
	}
}
/* この機能自身のページと、記事・在庫の入れ物は、引き継ぎでは隠さない（旧プラグインの中に移設されていた分は左に戻す） */
if ( ! function_exists( 'carmelx_adminmenu_never_inherit' ) ) {
	function carmelx_adminmenu_never_inherit() {
		return array(
			'carmel-allmenus', 'index.php', 'upload.php', 'plugins.php', 'options-general.php',
			'edit.php?post_type=portfolio', 'edit.php?post_type=news', 'edit.php?post_type=shop_blog',
			'edit.php?post_type=media_article', 'edit.php?post_type=page',
		);
	}
}
/* 旧「CARMEL 自動生成」が隠していたトップメニュー（設定は旧プラグインを止めても DB に残っている） */
if ( ! function_exists( 'carmelx_adminmenu_inherited' ) ) {
	function carmelx_adminmenu_inherited() {
		$old = get_option( 'carmel_auto_settings' );
		if ( ! is_array( $old ) || empty( $old['hidden_menus'] ) || ! is_array( $old['hidden_menus'] ) ) { return array(); }
		$out = array();
		foreach ( $old['hidden_menus'] as $slug ) {
			$slug = (string) $slug;
			if ( '' === $slug || in_array( $slug, carmelx_adminmenu_never_inherit(), true ) ) { continue; }
			$out[] = $slug;
		}
		return array_values( array_unique( $out ) );
	}
}
/* 隠す前の左メニュー（「すべてのメニュー」ページと設定ページで使う） */
if ( ! function_exists( 'carmelx_adminmenu_snapshot' ) ) {
	function carmelx_adminmenu_snapshot() {
		global $menu, $submenu;
		if ( isset( $GLOBALS['carmelx_adminmenu_all'] ) ) { return; }
		$GLOBALS['carmelx_adminmenu_all']     = is_array( $menu ) ? $menu : array();
		$GLOBALS['carmelx_adminmenu_all_sub'] = is_array( $submenu ) ? $submenu : array();
	}
}
/* メニューのスラッグ → 開く先の URL */
if ( ! function_exists( 'carmelx_adminmenu_url' ) ) {
	function carmelx_adminmenu_url( $slug ) {
		$slug = html_entity_decode( (string) $slug, ENT_QUOTES, 'UTF-8' );
		if ( preg_match( '#^https?://#i', $slug ) ) { return $slug; }
		if ( false !== strpos( $slug, '.php' ) ) { return admin_url( $slug ); }
		$u = function_exists( 'menu_page_url' ) ? menu_page_url( $slug, false ) : '';
		return ( '' !== (string) $u ) ? $u : admin_url( 'admin.php?page=' . $slug );
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
		// 隠す前の一覧を使う（隠した項目も出さないと、保存のたびに元へ戻ってしまう）
		$src = ( isset( $GLOBALS['carmelx_adminmenu_all'] ) && is_array( $GLOBALS['carmelx_adminmenu_all'] ) && $GLOBALS['carmelx_adminmenu_all'] ) ? $GLOBALS['carmelx_adminmenu_all'] : $menu;
		if ( empty( $src ) || ! is_array( $src ) ) { return $out; }
		foreach ( $src as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug || false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			if ( 'carmel-allmenus' === $slug ) { continue; }
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
		if ( null === $hidden ) {
			if ( carmelx_adminmenu_inherit_only() ) { return in_array( $slug, carmelx_adminmenu_inherited(), true ); } // 引き継ぎだけ
			return true;                                // 既定：必須以外は隠す
		}
		return in_array( $slug, $hidden, true );
	}
}
if ( ! function_exists( 'carmelx_adminmenu_hide_admin' ) ) {
	function carmelx_adminmenu_hide_admin( $slug, $label, $hidden ) {
		if ( in_array( $slug, carmelx_adminmenu_admin_keep(), true ) ) { return false; } // 安全セットは常に表示
		if ( 'carmel-allmenus' === $slug ) { return false; }                             // 「すべてのメニュー」は常に表示
		if ( null === $hidden ) {                                                         // 既定：ノイズ＋旧プラグインが隠していた分
			if ( carmelx_adminmenu_inherit_only() ) { return in_array( $slug, carmelx_adminmenu_inherited(), true ); } // 引き継ぎだけ
			return carmelx_adminmenu_admin_noise( $slug, $label ) || in_array( $slug, carmelx_adminmenu_inherited(), true );
		}
		return in_array( $slug, $hidden, true );
	}
}

/* ---- 適用 ---- */
if ( ! function_exists( 'carmelx_adminmenu_apply' ) ) {
	function carmelx_adminmenu_apply() {
		global $menu;
		if ( empty( $menu ) || ! is_array( $menu ) ) { return; }
		carmelx_adminmenu_snapshot();
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
	// 旧プラグインなど他の仕組みが隠す（9999）より前に、全項目を控える
	add_action( 'admin_menu', 'carmelx_adminmenu_snapshot', 9990 );
}

/* ---- 旧プラグインの「隠す」設定を一度だけ取り込む（こちらで既に保存済みの場合） ---- */
if ( ! function_exists( 'carmelx_adminmenu_inherit_once' ) ) {
	function carmelx_adminmenu_inherit_once() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( get_option( 'carmelx_adminmenu_inherit_done' ) ) { return; }
		if ( function_exists( 'carmel3_hide_menus' ) ) { return; } // 旧プラグインが動いている間は何もしない
		$inh = carmelx_adminmenu_inherited();
		$o   = get_option( CARMEL_ADMINMENU_OPT );
		if ( $inh && is_array( $o ) && isset( $o['hidden_admin'] ) && is_array( $o['hidden_admin'] ) ) {
			$o['hidden_admin'] = array_values( array_unique( array_merge( $o['hidden_admin'], $inh ) ) );
			update_option( CARMEL_ADMINMENU_OPT, $o );
		}
		update_option( 'carmelx_adminmenu_inherit_done', 1, false );
	}
	add_action( 'admin_init', 'carmelx_adminmenu_inherit_once', 40 );
}

/* ---- 「すべてのメニュー」：隠した項目もここから全部開ける ---- */
if ( ! function_exists( 'carmelx_adminmenu_all_page' ) ) {
	function carmelx_adminmenu_all_page() {
		add_menu_page( 'すべてのメニュー', 'すべてのメニュー', 'manage_options', 'carmel-allmenus', 'carmelx_adminmenu_all_render', 'dashicons-screenoptions', '2.71' );
	}
	add_action( 'admin_menu', 'carmelx_adminmenu_all_page', 100 );
}
if ( ! function_exists( 'carmelx_adminmenu_all_render' ) ) {
	function carmelx_adminmenu_all_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $menu;
		$all = isset( $GLOBALS['carmelx_adminmenu_all'] ) && is_array( $GLOBALS['carmelx_adminmenu_all'] ) ? $GLOBALS['carmelx_adminmenu_all'] : ( is_array( $menu ) ? $menu : array() );
		$sub = isset( $GLOBALS['carmelx_adminmenu_all_sub'] ) && is_array( $GLOBALS['carmelx_adminmenu_all_sub'] ) ? $GLOBALS['carmelx_adminmenu_all_sub'] : array();
		$left = array(); // いま実際に左に出ている項目
		if ( is_array( $menu ) ) { foreach ( $menu as $lm ) { if ( ! empty( $lm[2] ) ) { $left[ (string) $lm[2] ] = 1; } } }
		$groups = array( 'hidden' => array(), 'shown' => array() );
		foreach ( $all as $m ) {
			$slug  = isset( $m[2] ) ? (string) $m[2] : '';
			$class = isset( $m[4] ) ? (string) $m[4] : '';
			if ( '' === $slug || 'carmel-allmenus' === $slug || false !== strpos( $class, 'wp-menu-separator' ) ) { continue; }
			if ( ! empty( $m[1] ) && ! current_user_can( $m[1] ) ) { continue; }
			$label = carmelx_adminmenu_label( isset( $m[0] ) ? $m[0] : $slug );
			if ( '' === $label ) { $label = $slug; }
			$links = array();
			if ( ! empty( $sub[ $slug ] ) && is_array( $sub[ $slug ] ) ) {
				foreach ( $sub[ $slug ] as $sm ) {
					if ( empty( $sm[2] ) ) { continue; }
					if ( ! empty( $sm[1] ) && ! current_user_can( $sm[1] ) ) { continue; }
					$sl = carmelx_adminmenu_label( isset( $sm[0] ) ? $sm[0] : '' );
					if ( '' === $sl ) { continue; }
					$links[ carmelx_adminmenu_url( $sm[2] ) ] = $sl;
				}
			}
			$top = $links ? key( $links ) : carmelx_adminmenu_url( $slug );
			$hidden = ! isset( $left[ $slug ] );
			$groups[ $hidden ? 'hidden' : 'shown' ][] = array( 'label' => $label, 'url' => $top, 'links' => $links );
		}
		$titles = array(
			'hidden' => '左メニューから隠している項目',
			'shown'  => '左メニューに出ている項目',
		);
		?>
		<div class="wrap">
			<h1>すべてのメニュー</h1>
			<p style="max-width:860px;">左メニューは、よく使うものだけにしています。隠している項目も、ここから全部開けます。隠すのは表示だけで、機能やデータは消していません。
			左に出す項目は <a href="<?php echo esc_url( admin_url( 'options-general.php?page=carmel-adminmenu' ) ); ?>">設定 → カーメル メニュー整理</a> で変えられます。</p>
			<?php foreach ( $groups as $key => $rows ) : if ( ! $rows ) { continue; } ?>
				<h2 style="margin-top:22px;"><?php echo esc_html( $titles[ $key ] ); ?>（<?php echo (int) count( $rows ); ?>）</h2>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;max-width:1200px;">
				<?php foreach ( $rows as $r ) : ?>
					<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;">
						<a href="<?php echo esc_url( $r['url'] ); ?>" style="font-size:14px;font-weight:600;text-decoration:none;"><?php echo esc_html( $r['label'] ); ?></a>
						<?php if ( count( $r['links'] ) > 1 ) : ?>
							<ul style="margin:8px 0 0;">
							<?php foreach ( $r['links'] as $u => $l ) : ?>
								<li style="margin:0 0 4px;"><a href="<?php echo esc_url( $u ); ?>" style="text-decoration:none;"><?php echo esc_html( $l ); ?></a></li>
							<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
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
					<?php if ( carmelx_adminmenu_inherit_only() ) : ?>
					・初期値：旧「CARMEL 自動生成」で隠していた項目（<?php echo (int) count( carmelx_adminmenu_inherited() ); ?>件）だけを非表示。それ以外は元のまま。<br>
					<?php else : ?>
					・スタッフの初期値：在庫・メディア等の必須のみ表示。<br>
					・自分の初期値：SEO/解析/補助系など“ノイズ”と、旧「CARMEL 自動生成」で隠していた項目（<?php echo (int) count( carmelx_adminmenu_inherited() ); ?>件）を非表示。<br>
					<?php endif; ?>
					・隠した項目は、左メニューの「すべてのメニュー」から開けます。<br>
					・この設定ページは管理者のみ表示されます。
				</p>
			</form>
		</div>
		<?php
	}
}
