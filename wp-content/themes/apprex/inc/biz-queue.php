<?php
/**
 * AI営業リスト → まとめて調べ直す（一括処理）。
 *
 * たくさんのお店を、裏で少しずつ（1回に数件）調べ直す。
 * 画面を開いている間は画面から進め、閉じても WP-Cron で1分ごとに続ける。
 * 終わったら社内通知（営業）で知らせる。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 調べ直しの状態。 */
function apprex_ldq_get() {
	$q = get_option( 'apprex_ld_queue', array() );
	$q = is_array( $q ) ? $q : array();
	return wp_parse_args( $q, array( 'ids' => array(), 'total' => 0, 'done' => 0, 'mail' => 0, 'form' => 0, 'drafts' => 0, 'draft' => 0, 'label' => '', 'started' => 0, 'finished' => 0 ) );
}

/** 調べ直しを始める（前のが残っていれば足す）。 */
function apprex_ldq_start( $ids, $label, $draft = false ) {
	$q   = apprex_ldq_get();
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	if ( ! $ids ) {
		return 0;
	}
	if ( ! $q['ids'] ) {
		$q = array( 'ids' => array(), 'total' => 0, 'done' => 0, 'mail' => 0, 'form' => 0, 'drafts' => 0, 'draft' => 0, 'label' => '', 'started' => time(), 'finished' => 0 );
	}
	$new          = array_values( array_diff( $ids, $q['ids'] ) );
	$q['ids']     = array_merge( $q['ids'], $new );
	$q['total']  += count( $new );
	$q['draft']   = $draft ? 1 : (int) $q['draft'];
	$q['label']   = $label;
	$q['finished'] = 0;
	update_option( 'apprex_ld_queue', $q, false );
	if ( ! wp_next_scheduled( 'apprex_ldq_cron' ) ) {
		wp_schedule_single_event( time() + 30, 'apprex_ldq_cron' );
	}
	return count( $new );
}

/**
 * 少しだけ進める。
 *
 * @param int $seconds 使ってよい時間。
 * @return array 状態。
 */
function apprex_ldq_step( $seconds = 20 ) {
	$lock = get_transient( 'apprex_ldq_lock' );
	if ( $lock ) {
		return apprex_ldq_get(); // ほかで進めている最中。
	}
	set_transient( 'apprex_ldq_lock', 1, $seconds + 60 );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( $seconds + 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$end = time() + $seconds;
	do {
		$q = apprex_ldq_get();
		if ( ! $q['ids'] ) {
			break;
		}
		$id = (int) array_shift( $q['ids'] );
		update_option( 'apprex_ld_queue', $q, false ); // 先に外す（失敗しても同じお店で止まらない）。
		if ( 'apprex_lead' === get_post_type( $id ) && ! is_wp_error( apprex_ld_research( $id ) ) ) {
			$q = apprex_ldq_get();
			if ( apprex_ld_meta_list( $id, 'apprex_ld_emails' ) ) {
				$q['mail']++;
			} elseif ( get_post_meta( $id, 'apprex_ld_form', true ) ) {
				$q['form']++;
			}
			if ( $q['draft'] && function_exists( 'apprex_ldm_make_draft' ) && ! get_post_meta( $id, 'apprex_ld_draft', true ) && true === apprex_ldm_make_draft( $id ) ) {
				$q['drafts']++;
			}
		} else {
			$q = apprex_ldq_get();
		}
		$q['done']++;
		update_option( 'apprex_ld_queue', $q, false );
	} while ( time() < $end );
	delete_transient( 'apprex_ldq_lock' );
	$q = apprex_ldq_get();
	if ( ! $q['ids'] && $q['total'] && ! $q['finished'] ) {
		$q['finished'] = time();
		update_option( 'apprex_ld_queue', $q, false );
		if ( function_exists( 'apprex_notify' ) ) {
			apprex_notify( 'sales', '🔁 AI営業リストの調べ直しが終わりました（' . $q['label'] . '）：' . $q['done'] . '件を調べて、メールあり ' . $q['mail'] . '件・フォームだけ ' . $q['form'] . '件' . ( $q['draft'] ? '・下書き ' . $q['drafts'] . '件' : '' ) . "\n" . admin_url( 'admin.php?page=apprex-leads' . ( $q['drafts'] ? '#ldm' : '' ) ) );
		}
	}
	return $q;
}

// 画面を閉じても進める（1分ごと、残りがある間だけ）。
add_action(
	'apprex_ldq_cron',
	function () {
		$q = apprex_ldq_step( 45 );
		if ( $q['ids'] && ! wp_next_scheduled( 'apprex_ldq_cron' ) ) {
			wp_schedule_single_event( time() + 60, 'apprex_ldq_cron' );
		}
	}
);

// 画面から進める。
add_action(
	'wp_ajax_apprex_ldq_step',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'apprex_ldq' );
		$q = apprex_ldq_step( 15 );
		wp_send_json_success( array( 'left' => count( $q['ids'] ), 'done' => (int) $q['done'], 'total' => (int) $q['total'], 'mail' => (int) $q['mail'], 'form' => (int) $q['form'], 'drafts' => (int) $q['drafts'] ) );
	}
);

/** 対象のお店を選ぶ。 */
function apprex_ldq_targets( $what, $f = array() ) {
	$all = get_posts(
		array(
			'post_type'      => 'apprex_lead',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$out = array();
	foreach ( $all as $id ) {
		$st  = (string) get_post_meta( $id, 'apprex_ld_status', true );
		$res = (int) get_post_meta( $id, 'apprex_ld_researched', true );
		$em  = apprex_ld_meta_list( $id, 'apprex_ld_emails' );
		if ( in_array( $st, array( 'skip', 'won', 'lost' ), true ) && 'filter' !== $what ) {
			continue;
		}
		$ok = false;
		if ( 'nomail' === $what ) {
			$ok = $res && ! $em && ! get_post_meta( $id, 'apprex_ld_form', true );
		} elseif ( 'unresearched' === $what ) {
			$ok = ! $res;
		} elseif ( 'old' === $what ) {
			$ok = $res && $res < time() - 30 * DAY_IN_SECONDS;
		} elseif ( 'filter' === $what ) {
			$ok = ( empty( $f['genre'] ) || get_post_meta( $id, 'apprex_ld_genre', true ) === $f['genre'] ) && ( empty( $f['status'] ) || $st === $f['status'] );
		}
		if ( $ok ) {
			$out[] = (int) $id;
		}
	}
	// 点数の高いお店から。
	usort(
		$out,
		function ( $a, $b ) {
			return (int) get_post_meta( $b, 'apprex_ld_score', true ) <=> (int) get_post_meta( $a, 'apprex_ld_score', true );
		}
	);
	return array_slice( $out, 0, 500 );
}

add_action(
	'admin_post_apprex_ldq',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_ldq' );
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=apprex-leads' );
		$msg  = '';
		if ( 'stop' === $do ) {
			$q        = apprex_ldq_get();
			$left     = count( $q['ids'] );
			$q['ids'] = array();
			$q['finished'] = time();
			update_option( 'apprex_ld_queue', $q, false );
			wp_clear_scheduled_hook( 'apprex_ldq_cron' );
			$msg = '調べ直しを止めました（残り ' . $left . '件はやめました）。';
		} elseif ( 'start' === $do ) {
			$what   = sanitize_key( $_POST['what'] ?? 'nomail' );
			$labels = array( 'nomail' => 'メールもフォームも見つからなかったお店', 'unresearched' => 'まだ調べていないお店', 'old' => '30日より前に調べたお店', 'filter' => 'いまの絞り込みのお店' );
			$f      = array( 'genre' => sanitize_key( $_POST['genre'] ?? '' ), 'status' => sanitize_key( $_POST['status'] ?? '' ) );
			$ids    = apprex_ldq_targets( $what, $f );
			$n      = apprex_ldq_start( $ids, $labels[ $what ] ?? '', ! empty( $_POST['draft'] ) );
			$msg    = $n ? $n . '件の調べ直しを始めました。この画面を開いたままにすると早く進みます（閉じても1分ごとに続きます）。' : '対象のお店がありませんでした。';
		}
		wp_safe_redirect( add_query_arg( 'ldmsg', rawurlencode( $msg ), $back ) );
		exit;
	}
);

/** リストの上に出す「まとめて調べ直す」。 */
function apprex_ldq_box( $f ) {
	$q   = apprex_ldq_get();
	$run = (bool) $q['ids'];
	$act = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div id="ldq" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 14px;margin:0 0 10px;max-width:1000px">
		<?php if ( $run ) : ?>
			<?php $pct = $q['total'] ? (int) round( 100 * $q['done'] / $q['total'] ) : 0; ?>
			<b>🔁 調べ直し中：<?php echo esc_html( $q['label'] ); ?></b>
			<div style="background:#f0f0f1;border-radius:99px;height:10px;margin:8px 0;overflow:hidden"><div id="ldq_bar" style="background:#2271b1;height:10px;width:<?php echo (int) $pct; ?>%"></div></div>
			<span id="ldq_txt"><?php echo (int) $q['done']; ?> / <?php echo (int) $q['total']; ?>件（メールあり <?php echo (int) $q['mail']; ?>件・フォームだけ <?php echo (int) $q['form']; ?>件<?php echo $q['draft'] ? '・下書き ' . (int) $q['drafts'] . '件' : ''; ?>）</span>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="display:inline;margin-left:10px"><input type="hidden" name="action" value="apprex_ldq"><input type="hidden" name="do" value="stop"><?php wp_nonce_field( 'apprex_ldq' ); ?><button class="button button-small">止める</button></form>
			<p class="description" style="margin:6px 0 0">この画面を開いたままにすると早く進みます。閉じても1分ごとに続き、終わったら社内通知でお知らせします。</p>
			<script>
			(function(){
				var busy=false;
				function step(){
					if(busy)return;busy=true;
					var fd=new FormData();fd.append('action','apprex_ldq_step');fd.append('_ajax_nonce','<?php echo esc_js( wp_create_nonce( 'apprex_ldq' ) ); ?>');
					fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
						busy=false;if(!j||!j.success)return setTimeout(step,10000);
						var d=j.data;document.getElementById('ldq_bar').style.width=(d.total?Math.round(100*d.done/d.total):0)+'%';
						document.getElementById('ldq_txt').textContent=d.done+' / '+d.total+'件（メールあり '+d.mail+'件・フォームだけ '+d.form+'件'+(<?php echo $q['draft'] ? 'true' : 'false'; ?>?'・下書き '+d.drafts+'件':'')+'）';
						if(d.left>0){setTimeout(step,500);}else{location.href=location.pathname+'?page=apprex-leads';}
					}).catch(function(){busy=false;setTimeout(step,10000);});
				}
				setTimeout(step,300);
			})();
			</script>
		<?php else : ?>
			<?php
			$cnt = array(
				'nomail'       => count( apprex_ldq_targets( 'nomail' ) ),
				'unresearched' => count( apprex_ldq_targets( 'unresearched' ) ),
				'old'          => count( apprex_ldq_targets( 'old' ) ),
				'filter'       => count( apprex_ldq_targets( 'filter', $f ) ),
			);
			?>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
				<input type="hidden" name="action" value="apprex_ldq"><input type="hidden" name="do" value="start"><?php wp_nonce_field( 'apprex_ldq' ); ?>
				<input type="hidden" name="genre" value="<?php echo esc_attr( $f['genre'] ?? '' ); ?>"><input type="hidden" name="status" value="<?php echo esc_attr( $f['status'] ?? '' ); ?>">
				<b>まとめて調べ直す</b>
				<select name="what">
					<option value="nomail">メールもフォームも見つからなかったお店（<?php echo (int) $cnt['nomail']; ?>件）</option>
					<option value="unresearched">まだ調べていないお店（<?php echo (int) $cnt['unresearched']; ?>件）</option>
					<option value="old">30日より前に調べたお店（<?php echo (int) $cnt['old']; ?>件）</option>
					<option value="filter">いまの絞り込みのお店すべて（<?php echo (int) $cnt['filter']; ?>件）</option>
				</select>
				<?php if ( function_exists( 'apprex_ldm_make_draft' ) ) : ?><label><input type="checkbox" name="draft" value="1"> 見つかったらメールの下書きも作る</label><?php endif; ?>
				<button class="button button-primary" onclick="return confirm('選んだお店をまとめて調べ直します。AIの利用料が件数ぶんかかります。始めますか？')">始める</button>
			</form>
			<?php if ( $q['finished'] && $q['total'] && $q['finished'] > time() - DAY_IN_SECONDS ) : ?>
				<p class="description" style="margin:6px 0 0">前回（<?php echo esc_html( wp_date( 'n/j G:i', (int) $q['finished'] ) ); ?>）：<?php echo esc_html( $q['label'] ); ?> <?php echo (int) $q['done']; ?>件を調べて、メールあり <?php echo (int) $q['mail']; ?>件・フォームだけ <?php echo (int) $q['form']; ?>件<?php echo $q['draft'] ? '・下書き ' . (int) $q['drafts'] . '件' : ''; ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
