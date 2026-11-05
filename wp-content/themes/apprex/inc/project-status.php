<?php
/**
 * 制作の進み具合とスケジュール。
 *
 * - 担当者が、会員ごとに「工程ごとの予定日・完了日」「いまの作業」「お客様にお願いしていること」「テスト版のURL」を記録する
 *   （ユーザーの編集画面の「AIスタジオ」欄の下）。種類（ノーコード／オリジナル／マッチング）とキックオフ日から、目安の予定を自動で作れる。
 * - お客様が LINE やサイトのチャットで「いまどこまで進んでいますか」「いつ公開ですか」と聞くと、AIがこの記録をもとに答える。
 * - 工程が進んだら、お客様に LINE・メール・マイページでお知らせする（選べる）。
 * - 予定日を過ぎても次に進んでいない工程があれば、社内通知（Slack・LINE WORKS）でお知らせする。
 *
 * 工程の名前と「いまの工程」は inc/studio.php（apprex_studio_prod_steps・apprex_studio_prod）と共通。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 種類ごとの目安（キックオフからの日数。工程の順）。 */
function apprex_prod_presets() {
	return array(
		'nocode'   => array( 'ノーコード（最短2週間）', array( 0, 3, 8, 10, 12, 19 ) ),
		'original' => array( 'オリジナル開発（約3ヶ月）', array( 0, 14, 50, 65, 75, 90 ) ),
		'matching' => array( 'マッチングアプリ（約4ヶ月）', array( 0, 21, 75, 90, 100, 115 ) ),
	);
}

/** 会員の制作の記録。 */
function apprex_prod_get( $uid ) {
	$g = function ( $k, $d = '' ) use ( $uid ) {
		$v = get_user_meta( $uid, 'apprex_prod_' . $k, true );
		return '' === $v || null === $v ? $d : $v;
	};
	return array(
		'type'    => (string) $g( 'type', 'nocode' ),
		'kickoff' => (string) $g( 'kickoff' ),
		'plan'    => (array) $g( 'plan', array() ),
		'done'    => (array) $g( 'done', array() ),
		'note'    => (string) $g( 'note' ),
		'wait'    => (string) $g( 'wait' ),
		'test'    => (string) $g( 'test' ),
		'updated' => (int) $g( 'updated', 0 ),
		'log'     => (array) $g( 'log', array() ),
	);
}

/** 日付（Y-m-d）を「11月5日（水）」に。 */
function apprex_prod_date( $ymd ) {
	$t = strtotime( (string) $ymd . ' 12:00:00' );
	if ( ! $t ) {
		return '';
	}
	$w = array( '日', '月', '火', '水', '木', '金', '土' );
	return wp_date( 'n月j日', $t ) . '（' . $w[ (int) wp_date( 'w', $t ) ] . '）';
}

/** 今日（サイトの時間）。 */
function apprex_prod_today() {
	return wp_date( 'Y-m-d' );
}

/** 2つの日付の差（日）。 */
function apprex_prod_days( $from, $to ) {
	return (int) round( ( strtotime( $to . ' 12:00:00' ) - strtotime( $from . ' 12:00:00' ) ) / DAY_IN_SECONDS );
}

/**
 * いまの状態をまとめる（AI・マイページ・管理画面で共通）。
 *
 * @return array|null 制作を始めていなければ null。
 */
function apprex_prod_state( $uid ) {
	$stage = (int) get_user_meta( $uid, 'apprex_studio_stage', true );
	$cur   = (int) get_user_meta( $uid, 'apprex_studio_prod', true );
	$p     = apprex_prod_get( $uid );
	$steps = function_exists( 'apprex_studio_prod_steps' ) ? apprex_studio_prod_steps() : array();
	if ( $stage < 4 && ! $p['plan'] ) {
		return null;
	}
	$today = apprex_prod_today();
	$rows  = array();
	foreach ( $steps as $i => $label ) {
		$st = $i < $cur ? 'done' : ( $i === $cur && $cur < count( $steps ) ? 'now' : 'next' );
		if ( $cur >= count( $steps ) ) {
			$st = 'done';
		}
		$rows[] = array(
			'label' => $label,
			'state' => $st,
			'plan'  => (string) ( $p['plan'][ $i ] ?? '' ),
			'done'  => (string) ( $p['done'][ $i ] ?? '' ),
		);
	}
	$launch = (string) ( $p['plan'][ count( $steps ) - 1 ] ?? '' );
	$late   = 0;
	if ( $cur < count( $steps ) && '' !== $rows[ $cur ]['plan'] && $rows[ $cur ]['plan'] < $today ) {
		$late = apprex_prod_days( $rows[ $cur ]['plan'], $today );
	}
	return array(
		'stage'    => $stage,
		'cur'      => $cur,
		'finished' => $cur >= count( $steps ),
		'rows'     => $rows,
		'launch'   => $launch,
		'left'     => '' !== $launch ? apprex_prod_days( $today, $launch ) : null,
		'late'     => $late,
		'note'     => $p['note'],
		'wait'     => $p['wait'],
		'test'     => $p['test'],
		'updated'  => $p['updated'],
		'log'      => $p['log'],
	);
}

/** AIに渡す説明（LINE・サイトのチャット）。 */
function apprex_prod_ai_context( $user ) {
	if ( ! $user || ! $user->ID ) {
		return '';
	}
	$stage  = (int) get_user_meta( $user->ID, 'apprex_studio_stage', true );
	$stages = function_exists( 'apprex_studio_stages' ) ? apprex_studio_stages() : array();
	$s      = apprex_prod_state( $user->ID );
	$orig   = json_decode( (string) get_user_meta( $user->ID, 'apprex_orig_spec', true ), true );
	$lines  = array( '# アプリの制作の進み具合とスケジュール（' . apprex_prod_date( apprex_prod_today() ) . '時点・担当者の記録）' );
	if ( ! empty( $orig['name'] ) ) {
		$lines[] = 'アプリ：' . $orig['name'];
	}
	if ( ! $s ) {
		if ( ! $stage && empty( $orig['name'] ) ) {
			return '';
		}
		$lines[] = '段階：' . ( $stages[ $stage ] ?? '作成中' ) . '（まだ制作は始まっていない）';
		$next    = array(
			0 => 'AIスタジオでデザイン・仕様書を作り、「開発を相談」を押す',
			1 => '担当者からの連絡を待つ（ミーティングの予約もできる）',
			2 => '予約したオンラインミーティングで内容を確認する',
			3 => 'お見積りを確認して、ご入金いただく（入金を確認したら制作を始める）',
		);
		if ( isset( $next[ $stage ] ) ) {
			$lines[] = '次にすること：' . $next[ $stage ];
		}
	} else {
		$lines[] = '段階：' . ( $s['finished'] ? '公開済み' : '制作中' );
		$lines[] = '工程：';
		foreach ( $s['rows'] as $i => $r ) {
			$t = ( $i + 1 ) . '. ' . $r['label'] . '：';
			if ( 'done' === $r['state'] ) {
				$t .= '完了' . ( '' !== $r['done'] ? '（' . apprex_prod_date( $r['done'] ) . '）' : '' );
			} elseif ( 'now' === $r['state'] ) {
				$t .= 'いま進めている' . ( '' !== $r['plan'] ? '（終わる予定 ' . apprex_prod_date( $r['plan'] ) . '）' : '（予定日は未定）' );
			} else {
				$t .= 'これから' . ( '' !== $r['plan'] ? '（予定 ' . apprex_prod_date( $r['plan'] ) . '）' : '' );
			}
			$lines[] = $t;
		}
		if ( ! $s['finished'] && '' !== $s['launch'] ) {
			$lines[] = '公開の予定：' . apprex_prod_date( $s['launch'] ) . 'ごろ' . ( null !== $s['left'] && $s['left'] >= 0 ? '（あと' . $s['left'] . '日）' : '' ) . '。ストアの審査の日数で前後することがある';
		}
		if ( $s['late'] > 0 ) {
			$lines[] = 'いまの工程は、予定より' . $s['late'] . '日遅れている（担当者が確認中）';
		}
		if ( '' !== $s['note'] ) {
			$lines[] = 'いまの作業（担当者から）：' . $s['note'];
		}
		if ( '' !== $s['wait'] ) {
			$lines[] = 'お客様にお願いしていること：' . $s['wait'];
		}
		if ( '' !== $s['test'] ) {
			$lines[] = 'テスト版：' . $s['test'];
		}
		if ( $s['updated'] ) {
			$lines[] = '担当者の最終更新：' . wp_date( 'n月j日 G:i', $s['updated'] );
		}
	}
	$lines[] = '（答え方）開発の状況・スケジュール・公開日を聞かれたら、この記録だけをもとに、いまの工程・次の工程・予定日・お客様にお願いしていることを具体的に答える。'
		. '記録にない日付や約束は作らない。予定日が「未定」のものは「担当者から改めてお知らせします」と伝える。'
		. '遅れているときは正直に伝え、担当者から詳しくご連絡すると答える。くわしく知りたい・急ぎたいという希望は、担当者への依頼として受け付ける。';
	return implode( "\n", $lines );
}

/* =========================================================================
 * 管理画面：ユーザーの編集画面（AIスタジオ欄の下）
 * ====================================================================== */
add_action( 'show_user_profile', 'apprex_prod_admin_box', 20 );
add_action( 'edit_user_profile', 'apprex_prod_admin_box', 20 );

function apprex_prod_admin_box( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! get_user_meta( $user->ID, 'apprex_studio', true ) ) {
		return;
	}
	$p     = apprex_prod_get( $user->ID );
	$steps = function_exists( 'apprex_studio_prod_steps' ) ? apprex_studio_prod_steps() : array();
	$cur   = (int) get_user_meta( $user->ID, 'apprex_studio_prod', true );
	wp_nonce_field( 'apprex_prod_admin', 'apprex_prod_nonce' );
	?>
	<h2 id="apprex-prod">制作のスケジュール（お客様がLINE・チャットで聞いたら、AIがこの内容で答えます）</h2>
	<table class="form-table" role="presentation">
		<tr><th>種類とキックオフ日</th><td>
			<select name="apprex_prod_type">
				<?php foreach ( apprex_prod_presets() as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p['type'], $k ); ?>><?php echo esc_html( $v[0] ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="apprex_prod_kickoff" value="<?php echo esc_attr( $p['kickoff'] ); ?>">
			<label style="margin-left:8px"><input type="checkbox" name="apprex_prod_auto" value="1"> 種類とキックオフ日から、下の予定日を目安で作り直す</label>
		</td></tr>
		<tr><th>工程ごとの予定</th><td>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th>工程</th><th>予定日（終わる日）</th><th>完了日</th></tr></thead>
				<tbody>
				<?php foreach ( $steps as $i => $label ) : ?>
					<tr>
						<td><?php echo esc_html( ( $i + 1 ) . '. ' . $label ); ?><?php echo $i === $cur ? ' <b style="color:#d63638">← いま</b>' : ''; ?></td>
						<td><input type="date" name="apprex_prod_plan[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( (string) ( $p['plan'][ $i ] ?? '' ) ); ?>"></td>
						<td><input type="date" name="apprex_prod_done[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( (string) ( $p['done'][ $i ] ?? '' ) ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">「いまの工程」は上の「AIスタジオ」欄の「制作の工程」で選びます。工程を進めると、終わった工程の完了日は今日の日付で自動で入ります。</p>
		</td></tr>
		<tr><th>いまの作業</th><td><textarea name="apprex_prod_note" rows="2" class="large-text" placeholder="例：会員登録とクーポンの画面を作っています。来週はじめにテスト版をお送りします。"><?php echo esc_textarea( $p['note'] ); ?></textarea></td></tr>
		<tr><th>お客様にお願いしていること</th><td><textarea name="apprex_prod_wait" rows="2" class="large-text" placeholder="例：ロゴのデータと、お店の写真3枚をお送りください。"><?php echo esc_textarea( $p['wait'] ); ?></textarea>
			<p class="description">ないときは空にしてください。マイページの「いまお願いしていること」にも出ます。</p></td></tr>
		<tr><th>テスト版のURL</th><td><input type="url" name="apprex_prod_test" class="regular-text" value="<?php echo esc_attr( $p['test'] ); ?>" placeholder="https://（TestFlight の招待リンクなど）"></td></tr>
		<tr><th>お客様へのお知らせ</th><td><label><input type="checkbox" name="apprex_prod_notify" value="1" checked> 工程が進んだとき・「いまの作業」「お願いしていること」を変えたときに、LINE・メール・マイページでお知らせする</label></td></tr>
		<?php if ( $p['log'] ) : ?>
		<tr><th>これまでのお知らせ</th><td><ul style="margin:0">
			<?php foreach ( array_slice( array_reverse( $p['log'] ), 0, 8 ) as $l ) : ?>
				<li><?php echo esc_html( wp_date( 'n/j G:i', (int) $l[0] ) . '　' . $l[1] ); ?></li>
			<?php endforeach; ?>
		</ul></td></tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action( 'personal_options_update', 'apprex_prod_admin_save', 20 );
add_action( 'edit_user_profile_update', 'apprex_prod_admin_save', 20 );

function apprex_prod_admin_save( $uid ) {
	if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['apprex_prod_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_prod_nonce'] ) ), 'apprex_prod_admin' ) ) {
		return;
	}
	$before = apprex_prod_get( $uid );
	$steps  = function_exists( 'apprex_studio_prod_steps' ) ? apprex_studio_prod_steps() : array();
	$date   = function ( $v ) {
		$v = sanitize_text_field( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
	};
	$type    = isset( $_POST['apprex_prod_type'] ) ? sanitize_key( $_POST['apprex_prod_type'] ) : 'nocode';
	$type    = isset( apprex_prod_presets()[ $type ] ) ? $type : 'nocode';
	$kickoff = $date( wp_unslash( $_POST['apprex_prod_kickoff'] ?? '' ) );
	$plan    = array();
	$done    = array();
	foreach ( array_keys( $steps ) as $i ) {
		$plan[ $i ] = $date( wp_unslash( $_POST['apprex_prod_plan'][ $i ] ?? '' ) );
		$done[ $i ] = $date( wp_unslash( $_POST['apprex_prod_done'][ $i ] ?? '' ) );
	}
	if ( ! empty( $_POST['apprex_prod_auto'] ) ) {
		$base = '' !== $kickoff ? $kickoff : apprex_prod_today();
		$kickoff = $base;
		foreach ( apprex_prod_presets()[ $type ][1] as $i => $d ) {
			$plan[ $i ] = wp_date( 'Y-m-d', strtotime( $base . ' 12:00:00 +' . (int) $d . ' days' ) );
		}
	}
	// 終わった工程の完了日。
	$cur = (int) get_user_meta( $uid, 'apprex_studio_prod', true );
	foreach ( array_keys( $steps ) as $i ) {
		if ( $i < $cur && '' === $done[ $i ] ) {
			// 予定日が過ぎていれば予定日、まだなら今日を完了日にする。
			$done[ $i ] = ( '' !== $plan[ $i ] && $plan[ $i ] <= apprex_prod_today() ) ? $plan[ $i ] : apprex_prod_today();
		}
	}
	$note = sanitize_textarea_field( wp_unslash( $_POST['apprex_prod_note'] ?? '' ) );
	$wait = sanitize_textarea_field( wp_unslash( $_POST['apprex_prod_wait'] ?? '' ) );
	$test = esc_url_raw( wp_unslash( $_POST['apprex_prod_test'] ?? '' ) );

	update_user_meta( $uid, 'apprex_prod_type', $type );
	update_user_meta( $uid, 'apprex_prod_kickoff', $kickoff );
	update_user_meta( $uid, 'apprex_prod_plan', array_filter( $plan ) );
	update_user_meta( $uid, 'apprex_prod_done', array_filter( $done ) );
	update_user_meta( $uid, 'apprex_prod_note', $note );
	update_user_meta( $uid, 'apprex_prod_wait', $wait );
	update_user_meta( $uid, 'apprex_prod_test', $test );

	$last    = (int) get_user_meta( $uid, 'apprex_prod_last_step', true );
	$changed = array();
	if ( $cur !== $last ) {
		$changed[] = 'step';
		update_user_meta( $uid, 'apprex_prod_last_step', $cur );
	}
	if ( $note !== $before['note'] && '' !== $note ) {
		$changed[] = 'note';
	}
	if ( $wait !== $before['wait'] && '' !== $wait ) {
		$changed[] = 'wait';
	}
	if ( $test !== $before['test'] && '' !== $test ) {
		$changed[] = 'test';
	}
	if ( $changed || $plan !== array_replace( array_fill_keys( array_keys( $steps ), '' ), $before['plan'] ) ) {
		update_user_meta( $uid, 'apprex_prod_updated', time() );
	}
	if ( $changed && ! empty( $_POST['apprex_prod_notify'] ) ) {
		apprex_prod_notify( $uid, $changed );
	}
}

/** お客様に、制作の進み具合を知らせる文。 */
function apprex_prod_message( $uid, $changed ) {
	$s = apprex_prod_state( $uid );
	if ( ! $s ) {
		return '';
	}
	$t = array();
	if ( in_array( 'step', $changed, true ) ) {
		if ( $s['finished'] ) {
			$t[] = '🎉 アプリが公開されました。ご協力ありがとうございました。';
		} else {
			$prev = $s['cur'] > 0 ? $s['rows'][ $s['cur'] - 1 ]['label'] : '';
			$now  = $s['rows'][ $s['cur'] ];
			$t[]  = '🛠 制作の進み具合のお知らせです。' . ( '' !== $prev ? '「' . $prev . '」が終わり、' : '' ) . '「' . $now['label'] . '」に進みました。'
				. ( '' !== $now['plan'] ? "\nこの工程は " . apprex_prod_date( $now['plan'] ) . ' ごろまでの予定です。' : '' );
		}
	} else {
		$t[] = '🛠 制作の進み具合のお知らせです。';
	}
	if ( ! $s['finished'] && '' !== $s['launch'] ) {
		$t[] = '公開の予定：' . apprex_prod_date( $s['launch'] ) . 'ごろ';
	}
	if ( '' !== $s['note'] ) {
		$t[] = 'いまの作業：' . $s['note'];
	}
	if ( '' !== $s['wait'] ) {
		$t[] = '📌 お願い：' . $s['wait'];
	}
	if ( in_array( 'test', $changed, true ) && '' !== $s['test'] ) {
		$t[] = '📱 テスト版：' . $s['test'];
	}
	$t[] = 'このLINEで「いまの状況は？」と聞いていただくと、いつでもお答えします。';
	return implode( "\n", $t );
}

/** LINE・メール・マイページで知らせる。 */
function apprex_prod_notify( $uid, $changed ) {
	$msg  = apprex_prod_message( $uid, $changed );
	$user = get_userdata( $uid );
	if ( '' === $msg || ! $user ) {
		return;
	}
	$sent = array();
	$luid = (string) get_user_meta( $uid, 'apprex_line_uid', true );
	if ( '' !== $luid && function_exists( 'apprex_line_notice' ) ) {
		apprex_line_notice( $luid, $msg, 'prod' );
		$sent[] = 'LINE';
	}
	$subject = '【APPREX】アプリ制作の進み具合のお知らせ';
	$body    = $user->display_name . " 様\n\n" . $msg . "\n\nマイページ：" . ( function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' ) );
	$html    = function_exists( 'apprex_render_email' ) ? apprex_render_email( $subject, $body, array( 'heading' => '制作の進み具合' ) ) : nl2br( esc_html( $body ) );
	$GLOBALS['apprex_bn_mailing'] = true; // 「メールを送りました」のLINEを重ねない。
	wp_mail( $user->user_email, $subject, $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
	unset( $GLOBALS['apprex_bn_mailing'] );
	$sent[] = 'メール';
	$cids = function_exists( 'apprex_member_contracts' ) ? (array) apprex_member_contracts( $user ) : array();
	if ( $cids && function_exists( 'apprex_bn_mypage_add' ) ) {
		apprex_bn_mypage_add( (int) $cids[0], 'prod', '制作の進み具合', $msg, 'info' );
		$sent[] = 'マイページ';
	}
	$log   = apprex_prod_get( $uid )['log'];
	$log[] = array( time(), strtok( $msg, "\n" ) . '（' . implode( '・', $sent ) . '）' );
	update_user_meta( $uid, 'apprex_prod_log', array_slice( $log, -30 ) );
}

/* =========================================================================
 * 予定を過ぎた工程を、毎朝社内に知らせる
 * ====================================================================== */
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'apprex_prod_cron' ) ) {
			$next = strtotime( wp_date( 'Y-m-d' ) . ' 08:40:00 ' . wp_timezone_string() );
			wp_schedule_event( $next > time() ? $next : $next + DAY_IN_SECONDS, 'daily', 'apprex_prod_cron' );
		}
	}
);
add_filter(
	'apprex_cron_hooks',
	function ( $h ) {
		$h['apprex_prod_cron'] = '制作スケジュールの確認';
		return $h;
	}
);
add_action(
	'apprex_prod_cron',
	function () {
		if ( ! function_exists( 'apprex_notify' ) ) {
			return;
		}
		$users = get_users( array( 'meta_key' => 'apprex_studio_stage', 'meta_value' => '4', 'fields' => 'ID' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$late  = array();
		foreach ( $users as $uid ) {
			$s = apprex_prod_state( (int) $uid );
			if ( $s && ! $s['finished'] && $s['late'] > 0 ) {
				$u      = get_userdata( (int) $uid );
				$late[] = '・' . $u->display_name . ' 様：「' . $s['rows'][ $s['cur'] ]['label'] . '」が予定より' . $s['late'] . '日遅れ ' . admin_url( 'user-edit.php?user_id=' . (int) $uid . '#apprex-prod' );
			}
		}
		if ( $late ) {
			apprex_notify( 'production', "⏰ *予定日を過ぎている制作があります*\n" . implode( "\n", $late ) . "\n（お客様がLINE・チャットで聞くと、AIは「予定より遅れている・担当者から連絡する」と答えます。予定日を見直すか、工程を進めてください）" );
		}
	}
);

/* =========================================================================
 * マイページ：工程に日付を添える
 * ====================================================================== */

/** マイページの工程の横に出す文（予定日・完了日）。 */
function apprex_prod_step_note( $uid, $i ) {
	$s = apprex_prod_state( $uid );
	if ( ! $s || empty( $s['rows'][ $i ] ) ) {
		return '';
	}
	$r = $s['rows'][ $i ];
	if ( 'done' === $r['state'] ) {
		return '' !== $r['done'] ? wp_date( 'n/j', strtotime( $r['done'] ) ) . ' 完了' : '';
	}
	return '' !== $r['plan'] ? wp_date( 'n/j', strtotime( $r['plan'] ) ) . 'ごろ' : '';
}
