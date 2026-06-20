<?php
/**
 * cron 稼働状況パネル（ダッシュボード）。
 *
 * APPREXの自動処理（月次請求・ステップメール・Square入金同期・LINEステップ）が
 * 動いているかを可視化し、外部cron設定を案内する。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 監視対象cronフック（hook => ラベル）。 */
function apprex_cron_hooks() {
	return array(
		'apprex_contract_cron'    => '契約・月次自動請求',
		'apprex_dripmail_cron'    => 'ステップメール / リマインダー',
		'apprex_square_sync_cron' => 'Square入金ステータス同期',
		'apprex_line_steps_cron'  => 'LINEステップ配信',
	);
}

/** 各cron実行時に「最終実行時刻」を記録。 */
add_action( 'init', function () {
	foreach ( array_keys( apprex_cron_hooks() ) as $hook ) {
		add_action( $hook, 'apprex_cron_record', 1 );
	}
} );
function apprex_cron_record() {
	$hook = current_action();
	$last = get_option( 'apprex_cron_last', array() );
	if ( ! is_array( $last ) ) {
		$last = array();
	}
	$last[ $hook ] = time();
	update_option( 'apprex_cron_last', $last, false );
}

/** ダッシュボードに稼働状況を表示。 */
add_action( 'apprex_dashboard_after_overdue', function () {
	$hooks = apprex_cron_hooks();
	$last  = get_option( 'apprex_cron_last', array() );
	$last  = is_array( $last ) ? $last : array();
	$now   = time();
	$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$cron_url = home_url( '/wp-cron.php?doing_wp_cron' );

	// 直近1時間以内にどれか動いていれば「健全」とみなす目安。
	$recent = 0;
	foreach ( $last as $t ) {
		if ( ( $now - (int) $t ) < HOUR_IN_SECONDS ) {
			$recent++;
		}
	}
	?>
	<h2 style="margin-top:24px;">⚙️ 自動処理（cron）の稼働状況</h2>
	<p style="color:#6b7280;">
		WP-Cron：<strong><?php echo $disabled ? '外部cronモード（DISABLE_WP_CRON=true）' : '標準モード（アクセス依存）'; ?></strong>
		<?php if ( ! $disabled ) : ?>
			<span style="color:#b91c1c;">← アクセスが少ないと自動処理が遅延・不発します。下記の外部cron設定を推奨。</span>
		<?php endif; ?>
	</p>
	<table class="widefat striped" style="max-width:820px;">
		<thead><tr><th>自動処理</th><th>次回予定</th><th>最終実行</th></tr></thead>
		<tbody>
		<?php foreach ( $hooks as $hook => $label ) :
			$next = wp_next_scheduled( $hook );
			$lt   = isset( $last[ $hook ] ) ? (int) $last[ $hook ] : 0;
			?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td><?php echo $next ? esc_html( wp_date( 'n/j H:i', $next ) ) : '<span style="color:#b91c1c;">未スケジュール</span>'; ?></td>
				<td><?php
					if ( $lt ) {
						$ago = $now - $lt;
						$txt = $ago < 3600 ? ( floor( $ago / 60 ) . '分前' ) : ( $ago < 86400 ? ( floor( $ago / 3600 ) . '時間前' ) : ( floor( $ago / 86400 ) . '日前' ) );
						echo '<span style="color:#15803d;">' . esc_html( wp_date( 'n/j H:i', $lt ) . '（' . $txt . '）' ) . '</span>';
					} else {
						echo '<span style="color:#9ca3af;">まだ実行記録なし</span>';
					}
				?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;max-width:820px;margin-top:12px;">
		<strong>📌 確実に動かす設定（推奨）</strong>
		<ol style="margin:8px 0 0;padding-left:20px;line-height:1.9;">
			<li><code>wp-config.php</code> に <code>define( 'DISABLE_WP_CRON', true );</code> を追記</li>
			<li>サーバーのcronで、<strong>5〜10分おき</strong>に次のURLを実行：<br>
				<code style="display:inline-block;background:#fff;border:1px solid #e5e7eb;padding:4px 8px;border-radius:6px;margin-top:4px;">curl -s "<?php echo esc_html( $cron_url ); ?>" &gt;/dev/null 2&gt;&amp;1</code>
			</li>
		</ol>
		<p class="description" style="margin:8px 0 0;">レンタルサーバーの管理画面に「cron設定」欄があればそこに登録できます。設定方法はご利用サーバーに合わせてご案内します。</p>
	</div>
	<?php
} );
