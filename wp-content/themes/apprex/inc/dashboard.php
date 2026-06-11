<?php
/**
 * APPREX 経営ダッシュボード（フェーズC）。
 *
 * 売上（MRR/ARR・今月の初期売上・粗利）、顧客増減（契約中/更新待ち/新規/解約）、
 * リード（問い合わせ・発注・種別内訳）、6ヶ月の推移、最新リストを一覧表示。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * メニュー
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_menu_page(
		'APPREX ダッシュボード',
		'APPREX ダッシュボード',
		'manage_options',
		'apprex-dashboard',
		'apprex_dashboard_page',
		'dashicons-chart-area',
		2
	);
} );

/* -------------------------------------------------------------------------
 * 集計ヘルパー
 * ---------------------------------------------------------------------- */

/** 投稿数カウント（任意で今月・メタ絞り込み）。 */
function apprex_dash_count( $post_type, $status, $meta = array(), $month = false ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => $status,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
	);
	$args = array_merge( $args, $meta );
	if ( $month ) {
		$args['date_query'] = array( array( 'year' => (int) current_time( 'Y' ), 'monthnum' => (int) current_time( 'n' ) ) );
	}
	$q = new WP_Query( $args );
	return (int) $q->found_posts;
}

/** 今月の発注による初期売上合計（初期設定費＋登録費＋一回オプション）。 */
function apprex_dash_order_initial_this_month() {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_order',
			'post_status'    => array( 'publish', 'apprex_new' ),
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'date_query'     => array( array( 'year' => (int) current_time( 'Y' ), 'monthnum' => (int) current_time( 'n' ) ) ),
		)
	);
	$sum = 0;
	foreach ( $ids as $id ) {
		$e    = (array) get_post_meta( $id, 'apprex_estimate', true );
		$sum += isset( $e['initial_total'] ) ? (int) $e['initial_total'] : 0;
	}
	return $sum;
}

/** 直近6ヶ月の月次件数。 */
function apprex_dash_monthly_counts( $post_type, $status ) {
	$out = array();
	for ( $i = 5; $i >= 0; $i-- ) {
		$ts = strtotime( "first day of -$i month", current_time( 'timestamp' ) );
		$q  = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => $status,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'date_query'     => array( array( 'year' => (int) wp_date( 'Y', $ts ), 'monthnum' => (int) wp_date( 'n', $ts ) ) ),
			)
		);
		$out[] = array( 'label' => wp_date( 'n月', $ts ), 'count' => (int) $q->found_posts );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 固定費の保存（粗利計算用）
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_apprex_save_dash', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_dash' );
	update_option( 'apprex_fixed_cost', isset( $_POST['fixed_cost'] ) ? absint( $_POST['fixed_cost'] ) : 0 );
	wp_safe_redirect( admin_url( 'admin.php?page=apprex-dashboard&saved=1' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * 画面
 * ---------------------------------------------------------------------- */
function apprex_dashboard_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mrr        = function_exists( 'apprex_mrr' ) ? apprex_mrr() : 0;
	$arr        = $mrr * 12;
	$fixed      = (int) get_option( 'apprex_fixed_cost', 0 );
	$gross      = $mrr - $fixed;
	$init_month = apprex_dash_order_initial_this_month();

	$active    = function_exists( 'apprex_get_contracts' ) ? count( apprex_get_contracts( 'active' ) ) : 0;
	$pending   = function_exists( 'apprex_get_contracts' ) ? count( apprex_get_contracts( 'pending' ) ) : 0;
	$cancelled = function_exists( 'apprex_get_contracts' ) ? count( apprex_get_contracts( 'cancelled' ) ) : 0;
	$new_contr = apprex_dash_count( 'apprex_contract', 'publish', array(), true );

	$inq_total = apprex_dash_count( 'apprex_inquiry', 'publish' );
	$inq_month = apprex_dash_count( 'apprex_inquiry', 'publish', array(), true );
	$ord_total = apprex_dash_count( 'apprex_order', array( 'publish', 'apprex_new' ) );
	$ord_month = apprex_dash_count( 'apprex_order', array( 'publish', 'apprex_new' ), array(), true );

	$types = array(
		'contact'  => 'お問い合わせ',
		'document' => '資料請求',
		'trial'    => '無料体験',
		'meeting'  => 'ミーティング',
		'partner'  => 'パートナー',
	);

	$yen = function ( $n ) {
		return '¥' . number_format( (int) $n );
	};
	$card = function ( $label, $value, $sub = '', $color = '#2563eb' ) {
		echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
		echo '<div style="font-size:13px;color:#6b7280;">' . esc_html( $label ) . '</div>';
		echo '<div style="font-size:26px;font-weight:700;color:' . esc_attr( $color ) . ';margin-top:4px;">' . esc_html( $value ) . '</div>';
		if ( '' !== $sub ) {
			echo '<div style="font-size:12px;color:#9ca3af;margin-top:2px;">' . esc_html( $sub ) . '</div>';
		}
		echo '</div>';
	};
	?>
	<div class="wrap">
		<h1>APPREX ダッシュボード</h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>
		<?php endif; ?>
		<p style="color:#6b7280;">集計時点：<?php echo esc_html( wp_date( 'Y年n月j日 H:i' ) ); ?></p>

		<h2>売上</h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;">
			<?php
			$card( 'MRR（月次経常収益）', $yen( $mrr ), '契約中の月額合計', '#16a34a' );
			$card( 'ARR（年換算）', $yen( $arr ), 'MRR×12', '#16a34a' );
			$card( '今月の初期売上（発注）', $yen( $init_month ), '初期設定費＋登録費＋一回OP' );
			$card( '粗利（MRR−固定費）', $yen( $gross ), '固定費 ' . $yen( $fixed ) . '/月', $gross >= 0 ? '#16a34a' : '#dc2626' );
			?>
		</div>

		<h2>顧客（契約）</h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
			<?php
			$card( '契約中', (string) $active, '', '#2563eb' );
			$card( '更新待ち', (string) $pending, '', '#d97706' );
			$card( '今月の新規契約', (string) $new_contr );
			$card( '解約（累計）', (string) $cancelled, '', '#6b7280' );
			?>
		</div>

		<h2>リード（問い合わせ・発注）</h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
			<?php
			$card( '問い合わせ（今月）', (string) $inq_month, '累計 ' . $inq_total . '件' );
			$card( '発注（今月）', (string) $ord_month, '累計 ' . $ord_total . '件' );
			$conv = $inq_total > 0 ? round( $ord_total / max( 1, $inq_total ) * 100, 1 ) : 0;
			$card( '発注転換率（累計）', $conv . '%', '発注 ÷ 問い合わせ' );
			?>
		</div>

		<h3 style="margin-top:18px;">種別内訳（累計）</h3>
		<table class="widefat striped" style="max-width:520px;">
			<thead><tr><th>種別</th><th style="text-align:right;">件数</th></tr></thead>
			<tbody>
			<?php
			foreach ( $types as $tk => $tl ) {
				$c = apprex_dash_count( 'apprex_inquiry', 'publish', array( 'meta_key' => 'apprex_type', 'meta_value' => $tk ) );
				echo '<tr><td>' . esc_html( $tl ) . '</td><td style="text-align:right;">' . (int) $c . '</td></tr>';
			}
			?>
			</tbody>
		</table>

		<h2>推移（直近6ヶ月）</h2>
		<?php
		$trend_inq = apprex_dash_monthly_counts( 'apprex_inquiry', 'publish' );
		$trend_ord = apprex_dash_monthly_counts( 'apprex_order', array( 'publish', 'apprex_new' ) );
		$maxv      = 1;
		foreach ( array_merge( $trend_inq, $trend_ord ) as $r ) {
			$maxv = max( $maxv, $r['count'] );
		}
		$bars = function ( $title, $data, $maxv, $color ) {
			echo '<div style="flex:1;min-width:300px;"><strong>' . esc_html( $title ) . '</strong>';
			echo '<div style="display:flex;align-items:flex-end;gap:10px;height:140px;margin-top:8px;border-bottom:1px solid #e5e7eb;padding-bottom:4px;">';
			foreach ( $data as $r ) {
				$h = (int) round( $r['count'] / $maxv * 120 );
				echo '<div style="flex:1;text-align:center;">';
				echo '<div style="font-size:12px;color:#374151;">' . (int) $r['count'] . '</div>';
				echo '<div style="background:' . esc_attr( $color ) . ';height:' . $h . 'px;border-radius:4px 4px 0 0;"></div>';
				echo '<div style="font-size:11px;color:#9ca3af;margin-top:3px;">' . esc_html( $r['label'] ) . '</div>';
				echo '</div>';
			}
			echo '</div></div>';
		};
		echo '<div style="display:flex;gap:24px;flex-wrap:wrap;">';
		$bars( '問い合わせ', $trend_inq, $maxv, '#2563eb' );
		$bars( '発注', $trend_ord, $maxv, '#16a34a' );
		echo '</div>';
		?>

		<h2 style="margin-top:24px;">最新の動き</h2>
		<div style="display:flex;gap:20px;flex-wrap:wrap;">
			<?php
			$recent = function ( $title, $post_type, $status, $cb ) {
				echo '<div style="flex:1;min-width:300px;"><h3>' . esc_html( $title ) . '</h3><table class="widefat striped"><tbody>';
				$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => $status, 'posts_per_page' => 5, 'fields' => 'ids' ) );
				if ( ! $ids ) {
					echo '<tr><td>データがありません。</td></tr>';
				}
				foreach ( $ids as $id ) {
					echo '<tr><td><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">' . esc_html( $cb( $id ) ) . '</a><br><small style="color:#9ca3af;">' . esc_html( get_the_date( 'Y-m-d H:i', $id ) ) . '</small></td></tr>';
				}
				echo '</tbody></table></div>';
			};
			$recent( '最新の契約', 'apprex_contract', 'publish', function ( $id ) {
				return get_the_title( $id ) . '（¥' . number_format( (int) get_post_meta( $id, 'apprex_c_monthly', true ) ) . '/月）';
			} );
			$recent( '最新の発注', 'apprex_order', array( 'publish', 'apprex_new' ), function ( $id ) {
				return get_post_meta( $id, 'apprex_customer_name', true ) . '：' . get_the_title( $id );
			} );
			$recent( '最新の問い合わせ', 'apprex_inquiry', 'publish', function ( $id ) {
				return get_the_title( $id );
			} );
			?>
		</div>

		<h2 style="margin-top:24px;">設定</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_save_dash">
			<?php wp_nonce_field( 'apprex_dash' ); ?>
			<label>月の固定費（人件費・サーバー等の概算）：¥
				<input type="number" name="fixed_cost" value="<?php echo esc_attr( $fixed ); ?>" min="0" step="1000" style="width:140px;">
			</label>
			<button type="submit" class="button">保存して粗利に反映</button>
			<p class="description">固定費を入れると、上の「粗利（MRR−固定費）」に反映されます。</p>
		</form>
	</div>
	<?php
}
