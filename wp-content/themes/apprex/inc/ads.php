<?php
/**
 * 自社サイト広告枠（バナー広告）。
 *
 * 管理画面「設定 → APPREX 広告枠」から、バナー画像・リンク先・掲載位置・掲載期間を
 * 登録し、サイト内の各位置に自動表示します。クリック数も計測します（API不要）。
 *
 * 掲載位置（placement）:
 *   - header        : ヘッダー直下（全体ナビの下）
 *   - content_top   : 記事・固定ページ本文の先頭
 *   - content_bottom: 記事・固定ページ本文の末尾
 *   - footer        : フッターの直前
 *   - sidebar       : ショートコード [apprex_ad placement="sidebar"] で任意配置
 *
 * 対象（scope）: all（全ページ） / front（トップのみ） / blog（ブログ・投稿） / page（固定ページ）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * データ
 * ====================================================================== */

/** 掲載位置の選択肢。 */
function apprex_ad_placements() {
	return array(
		'header'         => 'ヘッダー直下（全体ナビの下）',
		'content_top'    => '本文の先頭（投稿・固定ページ）',
		'content_bottom' => '本文の末尾（投稿・固定ページ）',
		'footer'         => 'フッターの直前',
		'sidebar'        => 'サイドバー（ショートコードで配置）',
	);
}

/** 表示対象の選択肢。 */
function apprex_ad_scopes() {
	return array(
		'all'   => '全ページ',
		'front' => 'トップページのみ',
		'blog'  => 'ブログ・投稿ページ',
		'page'  => '固定ページ',
	);
}

/** 登録済み広告の配列を取得（正規化済み）。 */
function apprex_ads_all() {
	$ads = get_option( 'apprex_ads', array() );
	if ( ! is_array( $ads ) ) {
		$ads = array();
	}
	return $ads;
}

/**
 * 指定位置・現在のページ文脈に該当する有効な広告を返す。
 *
 * @param string $placement 掲載位置キー。
 * @return array
 */
function apprex_ads_active( $placement ) {
	$today = current_time( 'Y-m-d' );
	$out   = array();
	foreach ( apprex_ads_all() as $ad ) {
		if ( empty( $ad['enabled'] ) ) {
			continue;
		}
		if ( ( $ad['placement'] ?? '' ) !== $placement ) {
			continue;
		}
		if ( '' === trim( (string) ( $ad['image'] ?? '' ) ) ) {
			continue;
		}
		// 掲載期間。
		if ( ! empty( $ad['start'] ) && $today < $ad['start'] ) {
			continue;
		}
		if ( ! empty( $ad['end'] ) && $today > $ad['end'] ) {
			continue;
		}
		// 表示対象。
		if ( ! apprex_ad_scope_matches( $ad['scope'] ?? 'all' ) ) {
			continue;
		}
		$out[] = $ad;
	}
	return $out;
}

/** 現在のページが対象scopeに合致するか。 */
function apprex_ad_scope_matches( $scope ) {
	switch ( $scope ) {
		case 'front':
			return is_front_page() || is_home();
		case 'blog':
			return is_home() || is_singular( 'post' ) || is_archive() || is_category() || is_tag();
		case 'page':
			return is_page();
		case 'all':
		default:
			return true;
	}
}

/* =========================================================================
 * 表示
 * ====================================================================== */

/**
 * 指定位置の広告HTMLを返す。
 *
 * @param string $placement 掲載位置キー。
 * @return string
 */
function apprex_ad_render( $placement ) {
	$ads = apprex_ads_active( $placement );
	if ( empty( $ads ) ) {
		return '';
	}
	$html = '<div class="apprex-ad-slot apprex-ad-slot--' . esc_attr( $placement ) . '"><div class="container">';
	foreach ( $ads as $ad ) {
		$href   = apprex_ad_click_url( (int) ( $ad['id'] ?? 0 ) );
		$newtab = ! empty( $ad['new_tab'] );
		$target = $newtab ? ' target="_blank" rel="noopener nofollow sponsored"' : ' rel="nofollow sponsored"';
		$alt    = esc_attr( $ad['label'] ?? '広告' );
		$img    = '<img src="' . esc_url( $ad['image'] ) . '" alt="' . $alt . '" loading="lazy" decoding="async">';
		$html  .= '<a class="apprex-ad" href="' . esc_url( $href ) . '"' . $target . '>'
			. '<span class="apprex-ad__pr">PR</span>' . $img . '</a>';
	}
	$html .= '</div></div>';
	return $html;
}

/** クリック計測用のリダイレクトURL。 */
function apprex_ad_click_url( $id ) {
	if ( ! $id ) {
		return '#';
	}
	return add_query_arg( 'apprex_ad', $id, home_url( '/' ) );
}

/* ヘッダー直下・フッター直前（テーマフック）。 */
add_action( 'apprex_header_after', function () {
	echo apprex_ad_render( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部でエスケープ済み。
} );
add_action( 'apprex_footer_before', function () {
	echo apprex_ad_render( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部でエスケープ済み。
} );

/* 本文の先頭・末尾（the_content）。 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || is_feed() ) {
		return $content;
	}
	if ( ! is_singular() ) {
		return $content;
	}
	$top    = apprex_ad_render( 'content_top' );
	$bottom = apprex_ad_render( 'content_bottom' );
	return $top . $content . $bottom;
}, 20 );

/* ショートコード（任意位置：[apprex_ad placement="sidebar"]）。 */
add_shortcode( 'apprex_ad', function ( $atts ) {
	$atts = shortcode_atts( array( 'placement' => 'sidebar' ), $atts, 'apprex_ad' );
	return apprex_ad_render( $atts['placement'] );
} );

/* =========================================================================
 * クリック計測（リダイレクト）
 * ====================================================================== */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['apprex_ad'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$id  = absint( $_GET['apprex_ad'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$ads = apprex_ads_all();
	$dest = '';
	foreach ( $ads as &$ad ) {
		if ( (int) ( $ad['id'] ?? 0 ) === $id ) {
			$ad['clicks'] = (int) ( $ad['clicks'] ?? 0 ) + 1;
			$dest         = (string) ( $ad['link'] ?? '' );
			break;
		}
	}
	unset( $ad );
	if ( '' === $dest ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	update_option( 'apprex_ads', $ads, false );
	wp_redirect( esc_url_raw( $dest ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- 外部広告リンクのため許可。
	exit;
} );

/* =========================================================================
 * 管理画面（設定ページ）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 広告枠', 'APPREX 広告枠', 'manage_options', 'apprex-ads', 'apprex_ads_settings_page' );
} );

/* 保存（admin-post）。 */
add_action( 'admin_post_apprex_ads_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_ads_save' );

	$placements = apprex_ad_placements();
	$scopes     = apprex_ad_scopes();
	$prev       = array();
	foreach ( apprex_ads_all() as $a ) {
		$prev[ (int) ( $a['id'] ?? 0 ) ] = (int) ( $a['clicks'] ?? 0 );
	}

	$rows = isset( $_POST['ad'] ) && is_array( $_POST['ad'] ) ? wp_unslash( $_POST['ad'] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization.MissingUnslash,WordPress.Security.ValidationSanitization.InputNotSanitized
	$ads  = array();
	$next = (int) get_option( 'apprex_ads_seq', 0 );

	foreach ( $rows as $row ) {
		$image = isset( $row['image'] ) ? esc_url_raw( trim( $row['image'] ) ) : '';
		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
		// 画像もラベルも空の行はスキップ。
		if ( '' === $image && '' === $label ) {
			continue;
		}
		$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		if ( ! $id ) {
			$id = ++$next;
		}
		$placement = isset( $row['placement'] ) && isset( $placements[ $row['placement'] ] ) ? $row['placement'] : 'header';
		$scope     = isset( $row['scope'] ) && isset( $scopes[ $row['scope'] ] ) ? $row['scope'] : 'all';

		$ads[] = array(
			'id'        => $id,
			'enabled'   => ! empty( $row['enabled'] ),
			'label'     => $label,
			'image'     => $image,
			'link'      => isset( $row['link'] ) ? esc_url_raw( trim( $row['link'] ) ) : '',
			'new_tab'   => ! empty( $row['new_tab'] ),
			'placement' => $placement,
			'scope'     => $scope,
			'start'     => isset( $row['start'] ) ? preg_replace( '/[^0-9\-]/', '', $row['start'] ) : '',
			'end'       => isset( $row['end'] ) ? preg_replace( '/[^0-9\-]/', '', $row['end'] ) : '',
			'clicks'    => isset( $prev[ $id ] ) ? $prev[ $id ] : 0,
		);
	}

	update_option( 'apprex_ads', $ads, false );
	update_option( 'apprex_ads_seq', max( $next, (int) get_option( 'apprex_ads_seq', 0 ) ), false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'apprex-ads', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
	exit;
} );

/* クリック数リセット。 */
add_action( 'admin_post_apprex_ads_reset_clicks', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_ads_reset' );
	$ads = apprex_ads_all();
	foreach ( $ads as &$a ) {
		$a['clicks'] = 0;
	}
	unset( $a );
	update_option( 'apprex_ads', $ads, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'apprex-ads', 'reset' => '1' ), admin_url( 'options-general.php' ) ) );
	exit;
} );

function apprex_ads_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_enqueue_media();
	$ads        = apprex_ads_all();
	$placements = apprex_ad_placements();
	$scopes     = apprex_ad_scopes();
	$total_clicks = 0;
	foreach ( $ads as $a ) {
		$total_clicks += (int) ( $a['clicks'] ?? 0 );
	}
	?>
	<div class="wrap">
		<h1>APPREX 広告枠</h1>
		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>広告枠を保存しました。</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>クリック数をリセットしました。</p></div>
		<?php endif; ?>
		<p>サイト内に表示するバナー広告を登録します。各広告は掲載位置・対象ページ・掲載期間を個別に設定でき、クリック数を自動で計測します。</p>
		<p><strong>合計クリック数：<?php echo (int) $total_clicks; ?></strong>
			<a class="button" style="margin-left:8px;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_ads_reset_clicks' ), 'apprex_ads_reset' ) ); ?>" onclick="return confirm('全広告のクリック数を0に戻します。よろしいですか？');">クリック数をリセット</a>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_ads_save">
			<?php wp_nonce_field( 'apprex_ads_save' ); ?>

			<div id="apprex-ads-list">
				<?php
				if ( empty( $ads ) ) {
					$ads = array( array() ); // 空の1行を表示。
				}
				foreach ( $ads as $i => $ad ) {
					apprex_ads_row_html( $i, $ad, $placements, $scopes );
				}
				?>
			</div>

			<p>
				<button type="button" class="button" id="apprex-ads-add">＋ 広告を追加</button>
			</p>
			<?php submit_button( '広告枠を保存' ); ?>
		</form>

		<hr>
		<h2>掲載位置について</h2>
		<table class="widefat striped" style="max-width:760px;">
			<tbody>
				<tr><td><code>ヘッダー直下</code></td><td>全体ナビの直下に横長バナーを表示します。</td></tr>
				<tr><td><code>本文の先頭／末尾</code></td><td>投稿・固定ページの本文の前後に表示します。</td></tr>
				<tr><td><code>フッターの直前</code></td><td>ページ最下部のフッターの直前に表示します。</td></tr>
				<tr><td><code>サイドバー</code></td><td>ショートコード <code>[apprex_ad placement="sidebar"]</code> を入れた場所に表示します。</td></tr>
			</tbody>
		</table>

		<template id="apprex-ad-row-tpl">
			<?php apprex_ads_row_html( '__INDEX__', array(), $placements, $scopes ); ?>
		</template>

		<style>
		.apprex-ad-row{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:0 0 14px;max-width:860px}
		.apprex-ad-row .row-grid{display:grid;grid-template-columns:140px 1fr;gap:8px 14px;align-items:center}
		.apprex-ad-row .row-grid label{font-weight:600}
		.apprex-ad-row .ad-preview{max-width:320px;max-height:120px;display:block;margin:6px 0;border:1px solid #eee;border-radius:4px}
		.apprex-ad-row .ad-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
		.apprex-ad-row .ad-clicks{color:#2271b1;font-weight:700}
		.apprex-ad-row input[type=text],.apprex-ad-row input[type=url],.apprex-ad-row select{width:100%;max-width:420px}
		</style>
		<script>
		( function () {
			var list = document.getElementById( 'apprex-ads-list' );
			var tpl  = document.getElementById( 'apprex-ad-row-tpl' );
			var seq  = list.querySelectorAll( '.apprex-ad-row' ).length;

			document.getElementById( 'apprex-ads-add' ).addEventListener( 'click', function () {
				var html = tpl.innerHTML.replace( /__INDEX__/g, 'new' + ( seq++ ) );
				var wrap = document.createElement( 'div' );
				wrap.innerHTML = html.trim();
				list.appendChild( wrap.firstChild );
			} );

			list.addEventListener( 'click', function ( e ) {
				// 画像選択。
				if ( e.target.classList.contains( 'ad-pick' ) ) {
					e.preventDefault();
					var row   = e.target.closest( '.apprex-ad-row' );
					var input = row.querySelector( '.ad-image' );
					var prev  = row.querySelector( '.ad-preview' );
					var frame = wp.media( { title: 'バナー画像を選択', multiple: false, library: { type: 'image' } } );
					frame.on( 'select', function () {
						var att = frame.state().get( 'selection' ).first().toJSON();
						input.value = att.url;
						prev.src = att.url;
						prev.style.display = 'block';
					} );
					frame.open();
				}
				// 行削除。
				if ( e.target.classList.contains( 'ad-remove' ) ) {
					e.preventDefault();
					if ( confirm( 'この広告を削除します。よろしいですか？（保存で確定）' ) ) {
						e.target.closest( '.apprex-ad-row' ).remove();
					}
				}
			} );
		} )();
		</script>
	</div>
	<?php
}

/**
 * 広告1件分の入力行を出力。
 *
 * @param int|string $i          行インデックス。
 * @param array      $ad         広告データ。
 * @param array      $placements 位置選択肢。
 * @param array      $scopes     対象選択肢。
 */
function apprex_ads_row_html( $i, $ad, $placements, $scopes ) {
	$n = 'ad[' . esc_attr( $i ) . ']';
	$v = function ( $k, $d = '' ) use ( $ad ) {
		return isset( $ad[ $k ] ) ? $ad[ $k ] : $d;
	};
	$image = (string) $v( 'image' );
	?>
	<div class="apprex-ad-row">
		<div class="ad-head">
			<label><input type="checkbox" name="<?php echo $n; ?>[enabled]" value="1" <?php checked( ! empty( $ad['enabled'] ) || empty( $ad ) ); ?>> <strong>この広告を表示する</strong></label>
			<span class="ad-clicks">クリック数：<?php echo (int) $v( 'clicks', 0 ); ?></span>
		</div>
		<input type="hidden" name="<?php echo $n; ?>[id]" value="<?php echo esc_attr( $v( 'id' ) ); ?>">
		<div class="row-grid">
			<label>管理名（任意）</label>
			<input type="text" name="<?php echo $n; ?>[label]" value="<?php echo esc_attr( $v( 'label' ) ); ?>" placeholder="例：夏キャンペーン用バナー">

			<label>バナー画像</label>
			<div>
				<button type="button" class="button ad-pick">画像を選択</button>
				<input type="url" class="ad-image" name="<?php echo $n; ?>[image]" value="<?php echo esc_attr( $image ); ?>" placeholder="https://… 画像URL">
				<img class="ad-preview" src="<?php echo esc_url( $image ); ?>" alt="" style="<?php echo $image ? '' : 'display:none;'; ?>">
			</div>

			<label>リンク先URL</label>
			<input type="url" name="<?php echo $n; ?>[link]" value="<?php echo esc_attr( $v( 'link' ) ); ?>" placeholder="https://…">

			<label>別タブで開く</label>
			<label><input type="checkbox" name="<?php echo $n; ?>[new_tab]" value="1" <?php checked( ! empty( $ad['new_tab'] ) || empty( $ad ) ); ?>> 別タブ（target="_blank"）で開く</label>

			<label>掲載位置</label>
			<select name="<?php echo $n; ?>[placement]">
				<?php foreach ( $placements as $key => $lbl ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v( 'placement', 'header' ), $key ); ?>><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>

			<label>表示対象</label>
			<select name="<?php echo $n; ?>[scope]">
				<?php foreach ( $scopes as $key => $lbl ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v( 'scope', 'all' ), $key ); ?>><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>

			<label>掲載開始日（任意）</label>
			<input type="date" name="<?php echo $n; ?>[start]" value="<?php echo esc_attr( $v( 'start' ) ); ?>">

			<label>掲載終了日（任意）</label>
			<input type="date" name="<?php echo $n; ?>[end]" value="<?php echo esc_attr( $v( 'end' ) ); ?>">
		</div>
		<p style="margin:10px 0 0;text-align:right;"><button type="button" class="button-link-delete ad-remove">この広告を削除</button></p>
	</div>
	<?php
}
