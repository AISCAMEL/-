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

/** 契約の平均契約年数（未設定は除外、データなしは3年とみなす）。 */
function apprex_dash_avg_term_years() {
	$ids = get_posts( array( 'post_type' => 'apprex_contract', 'post_status' => 'publish', 'posts_per_page' => 1000, 'fields' => 'ids' ) );
	$sum = 0;
	$n   = 0;
	foreach ( $ids as $id ) {
		$t = (int) get_post_meta( $id, 'apprex_c_term', true );
		if ( $t > 0 ) {
			$sum += $t;
			$n++;
		}
	}
	return $n ? round( $sum / $n, 1 ) : 3;
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
 * 延滞リスト（入金未確認）
 * ---------------------------------------------------------------------- */

/**
 * 延滞している契約の一覧（契約中で、最終入金確認 または 契約開始から38日超）。
 *
 * @return array[] 各要素 { id, name, company, email, monthly, last_paid, days, method, payment_day }。
 */
function apprex_overdue_contracts() {
	if ( ! function_exists( 'apprex_get_contracts' ) ) {
		return array();
	}
	$now  = current_time( 'timestamp' );
	$list = array();
	foreach ( apprex_get_contracts( 'active' ) as $id ) {
		$last  = (string) get_post_meta( $id, 'apprex_c_last_paid', true );
		$lts   = $last ? strtotime( $last ) : 0;
		$start = (string) get_post_meta( $id, 'apprex_c_start', true );
		$ref   = $lts ? $lts : ( $start ? strtotime( $start ) : 0 );
		if ( ! $ref ) {
			continue; // 判定の基準日が無い。
		}
		$days = (int) floor( ( $now - $ref ) / DAY_IN_SECONDS );
		if ( $days <= 38 ) {
			continue;
		}
		$list[] = array(
			'id'          => $id,
			'name'        => (string) get_post_meta( $id, 'apprex_c_name', true ),
			'company'     => (string) get_post_meta( $id, 'apprex_c_company', true ),
			'email'       => (string) get_post_meta( $id, 'apprex_c_email', true ),
			'monthly'     => (int) get_post_meta( $id, 'apprex_c_monthly', true ),
			'last_paid'   => $last,
			'days'        => $days,
			'method'      => 'invoice' === get_post_meta( $id, 'apprex_c_payment_method', true ) ? '請求書' : 'Square',
			'payment_day' => (int) get_post_meta( $id, 'apprex_c_payment_day', true ),
		);
	}
	usort(
		$list,
		function ( $a, $b ) {
			return $b['days'] - $a['days'];
		}
	);
	return $list;
}

/** 延滞リストを Slack 用テキストに整形。 */
function apprex_overdue_slack_text( $list ) {
	$lines = array( ':rotating_light: *延滞リスト（入金未確認）* ' . count( $list ) . '件' );
	foreach ( array_slice( $list, 0, 20 ) as $r ) {
		$lines[] = sprintf(
			'• %s%s ／ ¥%s/月 ／ %d日経過 ／ 最終入金 %s ／ %s',
			$r['name'],
			$r['company'] ? '（' . $r['company'] . '）' : '',
			number_format( $r['monthly'] ),
			$r['days'],
			$r['last_paid'] ? $r['last_paid'] : '記録なし',
			$r['method']
		);
	}
	if ( count( $list ) > 20 ) {
		$lines[] = '…ほか ' . ( count( $list ) - 20 ) . '件';
	}
	$lines[] = admin_url( 'admin.php?page=apprex-dashboard' );
	return implode( "\n", $lines );
}

/** 1日1回、延滞リストを Slack へ要約通知（既存の契約cronに相乗り）。 */
add_action( 'apprex_contract_cron', 'apprex_overdue_daily_summary' );
function apprex_overdue_daily_summary() {
	if ( ! get_option( 'apprex_overdue_slack_daily', 1 ) ) {
		return;
	}
	$today = wp_date( 'Y-m-d' );
	if ( get_option( 'apprex_overdue_summary_date' ) === $today ) {
		return; // 本日分は送信済み。
	}
	update_option( 'apprex_overdue_summary_date', $today );
	$list = apprex_overdue_contracts();
	if ( empty( $list ) || ! function_exists( 'apprex_slack_notify' ) ) {
		return;
	}
	apprex_slack_notify( apprex_overdue_slack_text( $list ) );
}

/** ダッシュボードから手動で Slack に延滞リストを送信。 */
add_action( 'admin_post_apprex_send_overdue_slack', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_overdue_slack' );
	$list = apprex_overdue_contracts();
	$sent = 0;
	if ( function_exists( 'apprex_slack_notify' ) ) {
		$text = empty( $list ) ? ':white_check_mark: 現在、延滞はありません。' : apprex_overdue_slack_text( $list );
		$sent = apprex_slack_notify( $text ) ? 1 : 0;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=apprex-dashboard&overdue_slack=' . $sent ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * CSV エクスポート
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_apprex_dash_export', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_dash_export' );
	$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

	$specs = array(
		'contract' => array(
			'post_type' => 'apprex_contract',
			'status'    => 'publish',
			'header'    => array( 'ID', '名前', '会社', 'メール', 'サービス', 'プラン', '状態', '月額', '開始日', '更新日', '契約年数', '支払方法', '支払日', '最終入金' ),
			'row'       => function ( $id ) {
				$g = function ( $k ) use ( $id ) {
					return (string) get_post_meta( $id, $k, true );
				};
				$st = array( 'active' => '契約中', 'pending' => '更新待ち', 'cancelled' => '解約' );
				$s  = $g( 'apprex_c_status' );
				return array(
					$id, $g( 'apprex_c_name' ), $g( 'apprex_c_company' ), $g( 'apprex_c_email' ),
					$g( 'apprex_c_service' ), $g( 'apprex_c_plan' ),
					isset( $st[ $s ] ) ? $st[ $s ] : $s,
					$g( 'apprex_c_monthly' ), $g( 'apprex_c_start' ), $g( 'apprex_c_renewal' ),
					$g( 'apprex_c_term' ),
					'invoice' === $g( 'apprex_c_payment_method' ) ? '請求書' : 'Square',
					$g( 'apprex_c_payment_day' ), $g( 'apprex_c_last_paid' ),
				);
			},
		),
		'inquiry'  => array(
			'post_type' => 'apprex_inquiry',
			'status'    => 'publish',
			'header'    => array( 'ID', '日時', '種別', '名前', '会社', 'メール', '電話', '内容' ),
			'row'       => function ( $id ) {
				$g = function ( $k ) use ( $id ) {
					return (string) get_post_meta( $id, $k, true );
				};
				return array(
					$id, get_the_date( 'Y-m-d H:i', $id ), $g( 'apprex_type' ),
					$g( 'apprex_name' ), $g( 'apprex_company' ), $g( 'apprex_email' ),
					$g( 'apprex_phone' ), $g( 'apprex_message' ),
				);
			},
		),
		'order'    => array(
			'post_type' => 'apprex_order',
			'status'    => array( 'publish', 'apprex_new' ),
			'header'    => array( 'ID', '日時', '顧客名', '会社', 'メール', '内容', '月額', '初期費用', 'ステータス' ),
			'row'       => function ( $id ) {
				$e = (array) get_post_meta( $id, 'apprex_estimate', true );
				return array(
					$id, get_the_date( 'Y-m-d H:i', $id ),
					(string) get_post_meta( $id, 'apprex_customer_name', true ),
					(string) get_post_meta( $id, 'apprex_customer_company', true ),
					(string) get_post_meta( $id, 'apprex_customer_email', true ),
					get_the_title( $id ),
					isset( $e['monthly'] ) ? $e['monthly'] : '',
					isset( $e['initial_total'] ) ? $e['initial_total'] : '',
					get_post_status( $id ),
				);
			},
		),
	);

	if ( ! isset( $specs[ $type ] ) ) {
		wp_die( '不正な書き出し種別です。' );
	}
	$spec = $specs[ $type ];

	$ids = get_posts(
		array(
			'post_type'      => $spec['post_type'],
			'post_status'    => $spec['status'],
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="apprex-' . $type . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	echo "\xEF\xBB\xBF"; // UTF-8 BOM（Excel対策）。
	fputcsv( $out, $spec['header'] );
	foreach ( $ids as $id ) {
		$row = call_user_func( $spec['row'], $id );
		fputcsv( $out, array_map( 'wp_strip_all_tags', array_map( 'strval', $row ) ) );
	}
	fclose( $out );
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

	// 収益指標：解約率・ARPA・LTV。
	$total_c = $active + $pending + $cancelled;
	$churn   = $total_c > 0 ? round( $cancelled / $total_c * 100, 1 ) : 0;
	$arpa    = $active > 0 ? (int) round( $mrr / $active ) : 0;
	$avg_term = apprex_dash_avg_term_years();
	$ltv      = (int) round( $arpa * $avg_term * 12 );

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

		<h2>収益指標</h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;">
			<?php
			$card( '解約率（累計）', $churn . '%', '解約 ÷ 全契約', $churn <= 10 ? '#16a34a' : '#dc2626' );
			$card( 'ARPA（顧客あたり月額）', $yen( $arpa ), '契約中の平均月額' );
			$card( 'LTV（顧客生涯価値）', $yen( $ltv ), 'ARPA × 平均契約年数 ' . $avg_term . '年' );
			$card( '継続率（累計）', round( 100 - $churn, 1 ) . '%', '1 − 解約率', '#16a34a' );
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

		<?php $overdue = apprex_overdue_contracts(); ?>
		<h2 style="margin-top:24px;">延滞リスト（入金未確認）
			<span style="font-size:14px;font-weight:normal;color:<?php echo $overdue ? '#dc2626' : '#16a34a'; ?>;">
				<?php echo $overdue ? esc_html( count( $overdue ) . '件' ) : '0件（問題なし）'; ?>
			</span>
		</h2>
		<?php if ( isset( $_GET['overdue_slack'] ) ) : ?>
			<div class="notice notice-<?php echo '1' === $_GET['overdue_slack'] ? 'success' : 'warning'; ?> is-dismissible" style="margin:8px 0;"><p><?php echo '1' === $_GET['overdue_slack'] ? 'Slackに延滞リストを送信しました。' : 'Slack Webhookが未設定です（連携 > Slack）。'; ?></p></div>
		<?php endif; ?>
		<p style="color:#6b7280;">最終入金確認（消し込み）または契約開始から <strong>38日</strong> を超えても入金確認が無い契約です。入金を確認したら契約編集の「最終入金確認日」を更新してください。</p>
		<?php if ( $overdue ) : ?>
			<table class="widefat striped" style="max-width:920px;">
				<thead><tr><th>顧客</th><th>会社</th><th style="text-align:right;">月額</th><th style="text-align:right;">経過日数</th><th>最終入金</th><th>支払方法</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $overdue as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['name'] ); ?></td>
						<td><?php echo esc_html( $r['company'] ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( '¥' . number_format( $r['monthly'] ) ); ?></td>
						<td style="text-align:right;color:<?php echo $r['days'] > 60 ? '#dc2626' : '#d97706'; ?>;font-weight:600;"><?php echo (int) $r['days']; ?>日</td>
						<td><?php echo esc_html( $r['last_paid'] ? $r['last_paid'] : '記録なし' ); ?></td>
						<td><?php echo esc_html( $r['method'] ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $r['id'] . '&action=edit' ) ); ?>">消し込み</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p style="color:#16a34a;">現在、延滞はありません。</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
			<input type="hidden" name="action" value="apprex_send_overdue_slack">
			<?php wp_nonce_field( 'apprex_overdue_slack' ); ?>
			<button type="submit" class="button">Slackに延滞リストを送信</button>
			<span style="margin-left:8px;color:#9ca3af;font-size:13px;">毎日自動でもSlackに要約通知します（延滞がある日のみ）。</span>
		</form>

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

		<h2 style="margin-top:24px;">データ書き出し（CSV）</h2>
		<p style="color:#6b7280;">Excel／スプレッドシートで開けるCSV（UTF-8 BOM付き）をダウンロードします。</p>
		<?php
		$exp = function ( $type, $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_dash_export&type=' . $type ), 'apprex_dash_export' );
			echo '<a class="button" style="margin-right:8px;" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		};
		$exp( 'contract', '契約をCSV出力' );
		$exp( 'inquiry', '問い合わせをCSV出力' );
		$exp( 'order', '発注をCSV出力' );
		?>

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
