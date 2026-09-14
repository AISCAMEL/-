<?php
/**
 * Plugin Name: カーメル タイトル一括修正
 * Description: 在庫タイトルを「メーカー 車種 年式 走行距離」の形で一括整形します（例：三菱 デリカD:5 2012年 50,000km）。空・おかしいタイトルだけ／全件作り直しを選択可。任意でメタ説明（検索結果の説明文）に「低与信ローン対応」を空欄補完（Yoast/Rank Math/SEOPress 自動判定）。プレビュー付き・変化する値のみ更新。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「タイトル一括修正」を開く → プレビュー → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Title_Bulk' ) ) {

class Carmel_Title_Bulk {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'タイトル一括修正',
			'タイトル一括修正',
			'manage_options',
			'carmel-title-bulk',
			array( $this, 'render' )
		);
	}

	private function blank( $v ) { return ( null === $v || '' === $v || false === $v ); }

	/* 本体と同じ解決（複数候補キー × ACF優先 × post_metaフォールバック） */
	private function get_any( $pid, $keys ) {
		if ( function_exists( 'carmel_detail_get_any' ) ) {
			return (string) carmel_detail_get_any( $pid, (array) $keys );
		}
		foreach ( (array) $keys as $k ) {
			$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
			if ( $this->blank( $v ) ) { $v = get_post_meta( $pid, $k, true ); }
			if ( is_array( $v ) ) { $v = implode( '', array_filter( $v ) ); }
			$v = is_string( $v ) ? trim( $v ) : $v;
			if ( ! $this->blank( $v ) ) { return (string) $v; }
		}
		return '';
	}

	/* 年式：4桁数字なら「年」を付与。和暦・既に年付きはそのまま */
	private function fmt_year( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return ''; }
		$h = function_exists( 'mb_convert_kana' ) ? mb_convert_kana( $raw, 'n', 'UTF-8' ) : $raw;
		if ( preg_match( '/^\d{4}$/', $h ) ) { return $h . '年'; }
		return $raw;
	}

	/* 走行距離：数値なら 3桁カンマ + km */
	private function fmt_mileage( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) { return ''; }
		$digits = preg_replace( '/[^0-9]/', '', $raw );
		if ( '' !== $digits && (int) $digits > 0 ) { return number_format( (int) $digits ) . 'km'; }
		return $raw;
	}

	/* 1台の理想タイトルを組み立て（情報不足なら ''） */
	private function build_title( $pid ) {
		$maker   = $this->get_any( $pid, array( 'marker', 'maker', 'メーカー' ) );
		$type    = $this->get_any( $pid, array( 'type', 'name', 'car_model', 'shashu' ) );
		$year    = $this->fmt_year( $this->get_any( $pid, array( 'year', 'nenshiki' ) ) );
		$mileage = $this->fmt_mileage( $this->get_any( $pid, array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ) ) );

		// メーカー・車種が両方ない場合は安全のため作らない
		if ( '' === $maker && '' === $type ) { return ''; }

		$parts = array_filter( array( $maker, $type, $year, $mileage ), function ( $v ) { return '' !== trim( (string) $v ); } );
		return trim( preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );
	}

	/* 既存タイトルが「空・おかしい」か */
	private function looks_bad( $title ) {
		$t = trim( (string) $title );
		if ( '' === $t ) { return true; }
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $t, 'UTF-8' ) < 4 ) { return true; }
		if ( preg_match( '/[\x{301C}\x{FF5E}]/u', $t ) ) { return true; } // 〜混入
		if ( preg_match( '/^[0-9\s,]+$/', $t ) ) { return true; }          // 数字だけ
		if ( in_array( $t, array( '無題', '(無題)', 'Auto Draft', '中古車', 'Portfolio Item' ), true ) ) { return true; }
		return false;
	}

	/* ---- メタ説明（SEOプラグイン自動判定） ---- */

	private function seo_meta_key() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta' ) ) { return '_yoast_wpseo_metadesc'; } // Yoast
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) { return 'rank_math_description'; } // Rank Math
		if ( function_exists( 'seopress_init' ) || defined( 'SEOPRESS_VERSION' ) ) { return '_seopress_titles_desc'; } // SEOPress
		return '';
	}

	private function seo_label() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta' ) ) { return 'Yoast SEO'; }
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) { return 'Rank Math'; }
		if ( function_exists( 'seopress_init' ) || defined( 'SEOPRESS_VERSION' ) ) { return 'SEOPress'; }
		return '';
	}

	/* メタ説明の文面（最大120文字目安） */
	private function build_metadesc( $pid ) {
		$maker   = $this->get_any( $pid, array( 'marker', 'maker', 'メーカー' ) );
		$type    = $this->get_any( $pid, array( 'type', 'name', 'car_model', 'shashu' ) );
		$year    = $this->fmt_year( $this->get_any( $pid, array( 'year', 'nenshiki' ) ) );
		$mileage = $this->fmt_mileage( $this->get_any( $pid, array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ) ) );
		$car = trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( array( $maker, $type, $year, $mileage ) ) ) ) );
		if ( '' === $car ) { return ''; }
		$d = $car . 'の中古車情報。低与信ローン対応・全国納車OK。カーメルが安心の現車確認・保証付きで販売します。';
		if ( function_exists( 'mb_substr' ) ) { $d = mb_substr( $d, 0, 120, 'UTF-8' ); }
		return $d;
	}

	private function save_field( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		else { update_post_meta( $pid, $key, $val ); }
	}

	private function all_ids() {
		return get_posts( array(
			'post_type'      => 'portfolio',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'suppress_filters' => true,
		) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$posted   = isset( $_POST['ctb_submit'] ) || isset( $_POST['ctb_apply'] );
		$scope    = isset( $_POST['ctb_scope'] ) ? sanitize_key( $_POST['ctb_scope'] ) : 'bad';
		$do_meta  = $posted ? ! empty( $_POST['ctb_meta'] ) : false;
		$seo_key  = $this->seo_meta_key();
		$seo_lbl  = $this->seo_label();
		if ( '' === $seo_key ) { $do_meta = false; }

		$did = false; $applied_t = 0; $applied_m = 0;
		if ( isset( $_POST['ctb_apply'] ) && check_admin_referer( 'ctb_apply' ) ) {
			list( $applied_t, $applied_m ) = $this->run( $scope, $do_meta, $seo_key );
			$did = true;
		}

		// プレビュー集計
		$ids = $this->all_ids();
		$rows = array(); $count_t = 0; $count_m = 0; $skipped = 0;
		foreach ( $ids as $pid ) {
			$cur = (string) get_the_title( $pid );

			// タイトル対象か
			$target = ( 'all' === $scope ) ? true : $this->looks_bad( $cur );
			$new = $target ? $this->build_title( $pid ) : '';
			$t_change = ( $target && '' !== $new && $new !== $cur );
			if ( $target && '' === $new ) { $skipped++; }
			if ( $t_change ) { $count_t++; }

			// メタ説明（空欄補完のみ）
			$m_change = false; $m_new = '';
			if ( $do_meta && '' !== $seo_key ) {
				$m_cur = get_post_meta( $pid, $seo_key, true );
				if ( $this->blank( $m_cur ) ) {
					$m_new = $this->build_metadesc( $pid );
					if ( '' !== $m_new ) { $m_change = true; $count_m++; }
				}
			}

			if ( ( $t_change || $m_change ) && count( $rows ) < 60 ) {
				$rows[] = array( 'pid' => $pid, 'from' => $cur, 'to' => $new, 't' => $t_change, 'm' => $m_change, 'mtext' => $m_new );
			}
		}
		?>
		<div class="wrap">
			<h1>タイトル一括修正</h1>
			<p>形式：<code>メーカー 車種 年式 走行距離</code>（例：三菱 デリカD:5 2012年 50,000km）。
			<strong>変化する値だけ更新します。</strong>実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did ) : ?>
				<div class="notice notice-success"><p>タイトル <?php echo (int) $applied_t; ?> 件<?php if ( $applied_m ) : ?>、メタ説明 <?php echo (int) $applied_m; ?> 件<?php endif; ?> を更新しました。</p></div>
			<?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:760px;">
				<table class="form-table">
					<tr><th>対象</th><td>
						<label><input type="radio" name="ctb_scope" value="bad" <?php checked( $scope, 'bad' ); ?>> 空・おかしいタイトルだけ直す（おすすめ）</label><br>
						<label><input type="radio" name="ctb_scope" value="all" <?php checked( $scope, 'all' ); ?>> 全件をこの形式に作り直す</label>
						<p style="color:#666;font-size:12px;margin:6px 0 0;">※「空・おかしい」＝空欄／4文字未満／数字だけ／「〜」混入／無題 など。</p>
					</td></tr>
					<tr><th>メタ説明（SEO）</th><td>
						<?php if ( '' !== $seo_key ) : ?>
							<label><input type="checkbox" name="ctb_meta" value="1" <?php checked( $do_meta ); ?>> 検索結果の説明文に「低与信ローン対応」を空欄だけ補完する（<?php echo esc_html( $seo_lbl ); ?> 検出）</label>
							<p style="color:#666;font-size:12px;margin:6px 0 0;">既に説明文があるページは触りません。タイトルは汚さず、説明文にだけ入ります。</p>
						<?php else : ?>
							<em style="color:#888;">対応SEOプラグイン（Yoast / Rank Math / SEOPress）が見つからないため、メタ説明の補完は無効です。タイトル整形のみ行います。</em>
						<?php endif; ?>
					</td></tr>
				</table>
				<p><button class="button" name="ctb_submit" value="1">プレビューを更新</button></p>

				<h2>変更対象：タイトル <strong style="color:#1f6feb;"><?php echo (int) $count_t; ?></strong> 件<?php if ( $do_meta ) : ?> ／ メタ説明 <strong style="color:#1f6feb;"><?php echo (int) $count_m; ?></strong> 件<?php endif; ?></h2>
				<?php if ( $skipped > 0 ) : ?><p style="color:#8a6d00;">※ メーカー・車種が未入力で作れない車が <?php echo (int) $skipped; ?> 台あります（タイトルは変更しません）。先に「在庫ページ診断」で補完を。</p><?php endif; ?>

				<?php if ( $count_t > 0 || $count_m > 0 ) : ?>
					<?php wp_nonce_field( 'ctb_apply' ); ?>
					<p><button class="button button-primary button-hero" name="ctb_apply" value="1"
						onclick="return confirm('タイトル <?php echo (int) $count_t; ?> 件<?php if ( $do_meta ) : ?>・メタ説明 <?php echo (int) $count_m; ?> 件<?php endif; ?> を更新します。よろしいですか？');">
						今すぐ更新する</button></p>
				<?php else : ?>
					<p><em>更新が必要なページはありません。</em></p>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $rows ) ) : ?>
				<h2>プレビュー（先頭 <?php echo count( $rows ); ?> 件）</h2>
				<table class="widefat striped" style="max-width:1100px;">
					<thead><tr><th>車両</th><th>変更内容</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $r['pid'] ) ); ?>"><?php echo esc_html( $r['from'] ?: '(無題 #' . $r['pid'] . ')' ); ?></a></td>
							<td>
								<?php if ( $r['t'] ) : ?>
									<div><span style="color:#888;">タイトル：</span><?php echo esc_html( $r['from'] ?: '（空）' ); ?> <strong style="color:#1f6feb;">→</strong> <strong><?php echo esc_html( $r['to'] ); ?></strong></div>
								<?php endif; ?>
								<?php if ( $r['m'] ) : ?>
									<div style="margin-top:4px;"><span style="color:#888;">メタ説明：</span><span style="color:#46802b;"><?php echo esc_html( $r['mtext'] ); ?></span></div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function run( $scope, $do_meta, $seo_key ) {
		$ids = $this->all_ids();
		$applied_t = 0; $applied_m = 0;
		foreach ( $ids as $pid ) {
			$cur = (string) get_the_title( $pid );

			// タイトル
			$target = ( 'all' === $scope ) ? true : $this->looks_bad( $cur );
			if ( $target ) {
				$new = $this->build_title( $pid );
				if ( '' !== $new && $new !== $cur ) {
					wp_update_post( array( 'ID' => $pid, 'post_title' => $new ) );
					$applied_t++;
				}
			}

			// メタ説明（空欄補完のみ）
			if ( $do_meta && '' !== $seo_key ) {
				$m_cur = get_post_meta( $pid, $seo_key, true );
				if ( $this->blank( $m_cur ) ) {
					$m_new = $this->build_metadesc( $pid );
					if ( '' !== $m_new ) { update_post_meta( $pid, $seo_key, $m_new ); $applied_m++; }
				}
			}
		}
		return array( $applied_t, $applied_m );
	}
}

new Carmel_Title_Bulk();

}
