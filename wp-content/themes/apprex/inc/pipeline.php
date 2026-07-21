<?php
/**
 * 案件獲得パイプライン（¥1億ゴール）。
 *
 * 目標（年間・既定1億円）に対する獲得案件額の進捗、見込み→発注→契約のファネル、
 * 目標達成に必要な残り件数、優先対応すべきホットリードを可視化する。
 * 既存ダッシュボードの apprex_dashboard_after_overdue フックに描画。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * リード見込み度スコア（0〜100）。種別・電話・法人・記述量・新しさで採点。
 *
 * @param string $type     問い合わせ種別。
 * @param string $phone    電話番号。
 * @param string $company  会社名。
 * @param string $message  内容。
 * @param float  $age_days 受付からの経過日数。
 * @return int
 */
function apprex_lead_score( $type, $phone, $company, $message, $age_days = 0 ) {
	$weight = array(
		'estimate' => 50,
		'meeting'  => 45,
		'trial'    => 35,
		'partner'  => 25,
		'contact'  => 20,
		'document' => 12,
	);
	$score  = isset( $weight[ $type ] ) ? $weight[ $type ] : 15;
	$score += $phone ? 20 : 0;                                       // 電話番号あり＝本気度。
	$score += $company ? 10 : 0;                                     // 法人。
	$score += min( 20, (int) floor( mb_strlen( (string) $message ) / 20 ) * 5 ); // 記述量。
	$score += $age_days <= 7 ? 15 : ( $age_days <= 30 ? 8 : 0 );     // 新しさ。
	return (int) min( 100, $score );
}

/* ホットリード即通知：スコアの高い問い合わせが来たら即Slack通知（反応速度UP）。 */
add_action( 'apprex_inquiry_submitted', 'apprex_hotlead_notify', 20, 3 );
function apprex_hotlead_notify( $post_id, $type, $fields ) {
	if ( ! get_option( 'apprex_hotlead_notify', 1 ) ) {
		return;
	}
	$phone   = isset( $fields['phone'] ) ? $fields['phone'] : '';
	$company = isset( $fields['company'] ) ? $fields['company'] : '';
	$message = isset( $fields['message'] ) ? $fields['message'] : '';
	$score   = apprex_lead_score( $type, $phone, $company, $message, 0 );

	$threshold = (int) get_option( 'apprex_hotlead_threshold', 60 );
	if ( $score < $threshold ) {
		return;
	}
	if ( ! function_exists( 'apprex_slack_notify' ) ) {
		return;
	}
	$name  = isset( $fields['name'] ) ? $fields['name'] : '';
	$email = isset( $fields['email'] ) ? $fields['email'] : '';
	$label = function_exists( 'apprex_type_label' ) ? apprex_type_label( $type ) : $type;
	$text  = sprintf(
		":fire: *ホットリード（スコア %d/100）* ｜ %s\n%s%s ／ %s ／ %s\n%s\n%s",
		$score,
		$label,
		$name,
		$company ? '（' . $company . '）' : '',
		$email ? $email : 'メール未記入',
		$phone ? '☎ ' . $phone : '電話なし',
		$message ? '「' . mb_substr( wp_strip_all_tags( $message ), 0, 80 ) . '」' : '',
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);
	apprex_slack_notify( $text );
}

/** 年間目標額（既定：1億円）。 */
function apprex_pipeline_goal() {
	$g = (int) get_option( 'apprex_goal_annual', 100000000 );
	return $g > 0 ? $g : 100000000;
}

/** 1契約の総額（月額 × 12 × 契約年数）。 */
function apprex_contract_total_value( $id ) {
	$monthly = (int) get_post_meta( $id, 'apprex_c_monthly', true );
	$term    = (int) get_post_meta( $id, 'apprex_c_term', true );
	if ( $term <= 0 ) {
		$term = function_exists( 'apprex_dash_avg_term_years' ) ? (int) round( apprex_dash_avg_term_years() ) : 3;
		$term = $term > 0 ? $term : 3;
	}
	return $monthly * 12 * $term;
}

/**
 * 契約の集計（総額・件数）。$year を渡すとその年に作成された契約のみ。
 *
 * @param int|null $year 西暦（null で全期間）。
 * @return array{count:int,value:int}
 */
function apprex_pipeline_contracts_sum( $year = null ) {
	$args = array(
		'post_type'      => 'apprex_contract',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'fields'         => 'ids',
	);
	if ( $year ) {
		$args['date_query'] = array( array( 'year' => (int) $year ) );
	}
	$ids   = get_posts( $args );
	$value = 0;
	foreach ( $ids as $id ) {
		// 解約済みも「獲得した案件」としては計上（進捗の実績）。
		$value += apprex_contract_total_value( $id );
	}
	return array( 'count' => count( $ids ), 'value' => $value );
}

/** 見込み（発注済み・未契約化を含む）の合計額。 */
function apprex_pipeline_orders_sum() {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_order',
			'post_status'    => array( 'publish', 'apprex_new' ),
			'posts_per_page' => 2000,
			'fields'         => 'ids',
		)
	);
	$value = 0;
	foreach ( $ids as $id ) {
		$e        = (array) get_post_meta( $id, 'apprex_estimate', true );
		$initial  = isset( $e['initial_total'] ) ? (int) $e['initial_total'] : 0;
		$monthly  = isset( $e['monthly'] ) ? (int) $e['monthly'] : 0;
		$value   += $initial + $monthly * 12; // 初年度換算の見込み額。
	}
	return array( 'count' => count( $ids ), 'value' => $value );
}

/**
 * ホットリード（要対応）。直近60日の問い合わせを見込み度で採点し上位を返す。
 *
 * @param int $limit 返す件数。
 * @return array[] { id, name, company, type, score, date, phone, email }
 */
function apprex_pipeline_hot_leads( $limit = 8 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => '60 days ago' ) ),
		)
	);
	$rows = array();
	foreach ( $ids as $id ) {
		$type  = (string) get_post_meta( $id, 'apprex_type', true );
		$phone = (string) get_post_meta( $id, 'apprex_phone', true );
		$msg   = (string) get_post_meta( $id, 'apprex_message', true );
		$comp  = (string) get_post_meta( $id, 'apprex_company', true );

		$age   = ( current_time( 'timestamp' ) - get_post_time( 'U', true, $id ) ) / DAY_IN_SECONDS;
		$score = apprex_lead_score( $type, $phone, $comp, $msg, $age );

		$rows[] = array(
			'id'      => $id,
			'name'    => (string) get_post_meta( $id, 'apprex_name', true ),
			'company' => $comp,
			'type'    => $type,
			'phone'   => $phone,
			'email'   => (string) get_post_meta( $id, 'apprex_email', true ),
			'score'   => (int) min( 100, $score ),
			'date'    => get_the_date( 'Y-m-d', $id ),
		);
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return $b['score'] - $a['score'];
		}
	);
	return array_slice( $rows, 0, $limit );
}

/* 目標額の保存。 */
add_action( 'admin_post_apprex_pipeline_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_pipeline' );
	update_option( 'apprex_goal_annual', isset( $_POST['goal_annual'] ) ? absint( $_POST['goal_annual'] ) : 100000000 );
	update_option( 'apprex_hotlead_notify', empty( $_POST['hotlead_notify'] ) ? 0 : 1 );
	$th = isset( $_POST['hotlead_threshold'] ) ? absint( $_POST['hotlead_threshold'] ) : 60;
	update_option( 'apprex_hotlead_threshold', max( 0, min( 100, $th ) ) );
	wp_safe_redirect( admin_url( 'admin.php?page=apprex-dashboard&goal_saved=1#apprex-pipeline' ) );
	exit;
} );

/** 直近N日でスコアがしきい値以上のホットリード件数。 */
function apprex_kpi_hotlead_count( $days = 30 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => $days . ' days ago' ) ),
			'no_found_rows'  => true,
		)
	);
	$threshold = (int) get_option( 'apprex_hotlead_threshold', 60 );
	$n = 0;
	foreach ( $ids as $id ) {
		$age   = ( current_time( 'timestamp' ) - get_post_time( 'U', true, $id ) ) / DAY_IN_SECONDS;
		$score = apprex_lead_score(
			(string) get_post_meta( $id, 'apprex_type', true ),
			(string) get_post_meta( $id, 'apprex_phone', true ),
			(string) get_post_meta( $id, 'apprex_company', true ),
			(string) get_post_meta( $id, 'apprex_message', true ),
			$age
		);
		if ( $score >= $threshold ) {
			$n++;
		}
	}
	return $n;
}

/* KPI（案件獲得エンジンの効果測定）をダッシュボードに描画。 */
add_action( 'apprex_dashboard_after_overdue', 'apprex_kpi_render', 20 );
function apprex_kpi_render() {
	if ( ! function_exists( 'apprex_dash_count' ) ) {
		return;
	}
	$c = function ( $pt, $st, $meta = array(), $month = false ) {
		return apprex_dash_count( $pt, $st, $meta, $month );
	};

	// アポ（ミーティング予約）。
	$appt_month = $c( 'apprex_inquiry', 'publish', array( 'meta_key' => 'apprex_type', 'meta_value' => 'meeting' ), true );
	$appt_total = $c( 'apprex_inquiry', 'publish', array( 'meta_key' => 'apprex_type', 'meta_value' => 'meeting' ) );

	// チャット。
	$chat_month  = $c( 'apprex_chatlog', 'publish', array(), true );
	$chat_total  = $c( 'apprex_chatlog', 'publish' );
	$clead_month = $c( 'apprex_inquiry', 'publish', array( 'meta_key' => 'apprex_source', 'meta_value' => 'chat' ), true );
	$clead_total = $c( 'apprex_inquiry', 'publish', array( 'meta_key' => 'apprex_source', 'meta_value' => 'chat' ) );

	// SEO記事。
	$post_month = $c( 'post', 'publish', array(), true );
	$ai_total   = $c( 'post', 'publish', array( 'meta_key' => '_apprex_ai_generated', 'meta_value' => '1' ) );

	// 全体件数（転換率用）。
	$inq_total = $c( 'apprex_inquiry', 'publish' );
	$con_total = function_exists( 'apprex_get_contracts' ) ? count( apprex_get_contracts() ) : $c( 'apprex_contract', 'publish' );

	$hot30 = apprex_kpi_hotlead_count( 30 );

	$rate = function ( $num, $den ) {
		return $den > 0 ? round( $num / $den * 100, 1 ) . '%' : '—';
	};
	$card = function ( $label, $value, $sub, $color ) {
		echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04);">'
			. '<div style="font-size:12px;color:#6b7280;">' . esc_html( $label ) . '</div>'
			. '<div style="font-size:24px;font-weight:800;color:' . esc_attr( $color ) . ';margin-top:2px;">' . esc_html( $value ) . '</div>'
			. '<div style="font-size:11px;color:#9ca3af;">' . esc_html( $sub ) . '</div></div>';
	};
	?>
	<hr style="margin:28px 0;">
	<h2>📊 KPI（案件獲得エンジン）</h2>
	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:960px;">
		<?php
		$card( 'アポ獲得（今月）', (string) $appt_month, '累計 ' . $appt_total . '件', '#16a34a' );
		$card( 'ホットリード（30日）', (string) $hot30, 'スコア' . (int) get_option( 'apprex_hotlead_threshold', 60 ) . '点以上', '#dc2626' );
		$card( 'チャット会話（今月）', (string) $chat_month, '累計 ' . $chat_total . '件', '#2563eb' );
		$card( 'チャット経由リード（今月）', (string) $clead_month, '累計 ' . $clead_total . '件', '#2563eb' );
		$card( 'チャットCV率', $rate( $clead_total, $chat_total ), 'チャットリード ÷ 会話数', '#7c3aed' );
		$card( 'リード→アポ率', $rate( $appt_total, $inq_total ), 'アポ ÷ 問い合わせ', '#0891b2' );
		$card( 'アポ→受注率', $rate( $con_total, $appt_total ), '契約 ÷ アポ', '#0891b2' );
		$card( '記事公開（今月）', (string) $post_month, 'AI生成 累計 ' . $ai_total . '本', '#d97706' );
		?>
	</div>
	<p style="color:#9ca3af;font-size:12px;max-width:960px;">アポ＝ミーティング予約の問い合わせ。チャットCV率＝会話からリード化した割合。数字が伸びない箇所（集客／チャット対応／アポ化／受注化）が、次に手を打つべきボトルネックです。</p>
	<?php
}

/* ダッシュボードに描画。 */
add_action( 'apprex_dashboard_after_overdue', 'apprex_pipeline_render' );
function apprex_pipeline_render() {
	$goal   = apprex_pipeline_goal();
	$year   = (int) current_time( 'Y' );
	$c_year = apprex_pipeline_contracts_sum( $year );
	$c_all  = apprex_pipeline_contracts_sum();
	$orders = apprex_pipeline_orders_sum();

	$won      = $c_year['value'];                 // 今年の獲得案件額（確定）。
	$progress = $goal > 0 ? min( 100, round( $won / $goal * 100, 1 ) ) : 0;
	$remain   = max( 0, $goal - $won );

	// 平均案件単価：今年の実績優先、無ければ全期間、無ければ既定。
	if ( $c_year['count'] > 0 ) {
		$avg_deal = (int) round( $c_year['value'] / $c_year['count'] );
	} elseif ( $c_all['count'] > 0 ) {
		$avg_deal = (int) round( $c_all['value'] / $c_all['count'] );
	} else {
		$avg_deal = 1432800; // 39,800×12×3 の目安。
	}
	$need = $avg_deal > 0 ? (int) ceil( $remain / $avg_deal ) : 0;

	// ファネル（累計）。
	$inq = function_exists( 'apprex_dash_count' ) ? apprex_dash_count( 'apprex_inquiry', 'publish' ) : 0;
	$ord = $orders['count'];
	$con = $c_all['count'];

	$yen = function ( $n ) {
		return '¥' . number_format( (int) $n );
	};
	$oku = function ( $n ) {
		return number_format( $n / 100000000, 2 ) . '億円';
	};
	?>
	<hr style="margin:28px 0;">
	<h2 id="apprex-pipeline">🎯 案件獲得パイプライン（目標 <?php echo esc_html( $oku( $goal ) ); ?>）</h2>
	<?php if ( isset( $_GET['goal_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p>目標額を保存しました。</p></div>
	<?php endif; ?>

	<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 22px;max-width:920px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
		<div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px;">
			<div style="font-size:14px;color:#6b7280;"><?php echo esc_html( $year ); ?>年の獲得案件額（確定・契約総額ベース）</div>
			<div style="font-size:13px;color:#9ca3af;">目標 <?php echo esc_html( $yen( $goal ) ); ?></div>
		</div>
		<div style="font-size:30px;font-weight:800;color:#16a34a;margin:2px 0 10px;"><?php echo esc_html( $yen( $won ) ); ?>
			<span style="font-size:16px;color:#6b7280;font-weight:600;">／ <?php echo esc_html( $progress ); ?>%</span>
		</div>
		<div style="height:16px;background:#eef2f7;border-radius:999px;overflow:hidden;">
			<div style="height:100%;width:<?php echo esc_attr( max( 1, $progress ) ); ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);"></div>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;">
			<?php
			$box = function ( $label, $value, $sub, $color ) {
				echo '<div style="background:#f9fafb;border:1px solid #eef0f3;border-radius:8px;padding:12px 14px;">'
					. '<div style="font-size:12px;color:#6b7280;">' . esc_html( $label ) . '</div>'
					. '<div style="font-size:20px;font-weight:700;color:' . esc_attr( $color ) . ';">' . esc_html( $value ) . '</div>'
					. '<div style="font-size:11px;color:#9ca3af;">' . esc_html( $sub ) . '</div></div>';
			};
			$box( '目標まで残り', $yen( $remain ), '達成まであと' . $progress . '%を積み上げ', '#dc2626' );
			$box( '達成に必要な案件数', $need . '件', '平均単価 ' . $yen( $avg_deal ) . ' で試算', '#2563eb' );
			$box( '見込み（発注済み）', $yen( $orders['value'] ) . '（' . $ord . '件）', '初年度換算の見込み額', '#d97706' );
			$box( '累計の獲得案件額', $yen( $c_all['value'] ), '全期間の契約総額', '#16a34a' );
			?>
		</div>
	</div>

	<h3 style="margin-top:22px;">獲得ファネル（累計）</h3>
	<?php
	$stage = function ( $label, $count, $prev, $color ) {
		$rate = $prev > 0 ? round( $count / $prev * 100, 1 ) . '%' : '—';
		echo '<div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;text-align:center;">'
			. '<div style="font-size:13px;color:#6b7280;">' . esc_html( $label ) . '</div>'
			. '<div style="font-size:26px;font-weight:800;color:' . esc_attr( $color ) . ';">' . (int) $count . '</div>'
			. '<div style="font-size:11px;color:#9ca3af;">前段からの転換 ' . esc_html( $rate ) . '</div></div>';
	};
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:stretch;max-width:920px;">';
	$stage( '問い合わせ', $inq, 0, '#2563eb' );
	$stage( '発注（見込み）', $ord, $inq, '#d97706' );
	$stage( '契約（受注）', $con, $ord, '#16a34a' );
	echo '</div>';
	?>

	<h3 style="margin-top:22px;">🔥 今すぐ対応すべきホットリード（見込み度スコア順・直近60日）</h3>
	<?php $hot = apprex_pipeline_hot_leads( 8 ); ?>
	<?php if ( $hot ) : ?>
		<table class="widefat striped" style="max-width:920px;">
			<thead><tr><th style="width:70px;">スコア</th><th>お名前 / 会社</th><th>種別</th><th>電話</th><th>受付日</th><th></th></tr></thead>
			<tbody>
			<?php
			$type_label = function ( $t ) {
				$m = array( 'estimate' => '見積もり', 'meeting' => 'ミーティング', 'trial' => '無料体験', 'partner' => 'パートナー', 'contact' => 'お問い合わせ', 'document' => '資料請求' );
				return isset( $m[ $t ] ) ? $m[ $t ] : $t;
			};
			foreach ( $hot as $r ) :
				$col = $r['score'] >= 70 ? '#dc2626' : ( $r['score'] >= 45 ? '#d97706' : '#6b7280' );
				?>
				<tr>
					<td><span style="display:inline-block;min-width:38px;text-align:center;background:<?php echo esc_attr( $col ); ?>;color:#fff;font-weight:700;border-radius:999px;padding:2px 8px;"><?php echo (int) $r['score']; ?></span></td>
					<td><strong><?php echo esc_html( $r['name'] ); ?></strong><?php echo $r['company'] ? '<br><small style="color:#6b7280;">' . esc_html( $r['company'] ) . '</small>' : ''; ?></td>
					<td><?php echo esc_html( $type_label( $r['type'] ) ); ?></td>
					<td><?php echo $r['phone'] ? esc_html( $r['phone'] ) : '<span style="color:#9ca3af;">—</span>'; ?></td>
					<td><?php echo esc_html( $r['date'] ); ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $r['id'] . '&action=edit' ) ); ?>">対応する</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="color:#9ca3af;font-size:12px;">スコアは 種別・電話番号・法人・記述量・新しさ から自動採点しています（100点満点）。上から順に対応すると効率的です。</p>
	<?php else : ?>
		<p style="color:#6b7280;">直近60日の新しいリードはありません。SEO・広告で流入を増やしましょう。</p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
		<input type="hidden" name="action" value="apprex_pipeline_save">
		<?php wp_nonce_field( 'apprex_pipeline' ); ?>
		<p><label>年間目標額：¥
			<input type="number" name="goal_annual" value="<?php echo esc_attr( apprex_pipeline_goal() ); ?>" min="0" step="1000000" style="width:180px;">
		</label>
		<span style="margin-left:8px;color:#9ca3af;font-size:13px;">既定は 100,000,000（1億円）。</span></p>
		<p><label><input type="checkbox" name="hotlead_notify" value="1" <?php checked( 1, (int) get_option( 'apprex_hotlead_notify', 1 ) ); ?>> <strong>ホットリードを即Slack通知する</strong></label>
			<span style="margin-left:12px;">通知スコアしきい値：
				<input type="number" name="hotlead_threshold" value="<?php echo esc_attr( (int) get_option( 'apprex_hotlead_threshold', 60 ) ); ?>" min="0" max="100" style="width:70px;">点以上
			</span>
		</p>
		<p class="description">スコアが基準以上の問い合わせが来た瞬間に、Slackへ即通知します（反応速度＝成約率）。Slack Webhookは「APPREX 連携設定」で設定。見積もり＋電話番号ありなら約70点になります。</p>
		<button type="submit" class="button button-primary">目標・通知設定を保存</button>
	</form>
	<?php
}
