<?php
/**
 * 契約管理（フェーズA：データの土台）。
 *
 * - CPT「契約」(apprex_contract) に契約情報を保存
 *   顧客 / プラン / 月額 / 契約開始日 / 契約年数 / 次回更新日 / ステータス / 自動継続
 * - 保存時に「次回更新日」を自動計算（開始日 + 契約年数）
 * - 保存時に GAS（event=contract）へ同期 → スプレッド「契約」タブに記録
 * - 発注（apprex_order）から1クリックで契約化
 * - MRR（月次経常収益）等の集計ヘルパー（ダッシュボードで使用）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * CPT
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type(
		'apprex_contract',
		array(
			'labels'          => array(
				'name'          => __( '契約', 'apprex' ),
				'singular_name' => __( '契約', 'apprex' ),
				'menu_name'     => __( '契約', 'apprex' ),
				'all_items'     => __( '契約一覧', 'apprex' ),
				'add_new_item'  => __( '契約を追加', 'apprex' ),
				'edit_item'     => __( '契約を編集', 'apprex' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-id-alt',
			'menu_position'   => 7,
			'capability_type' => 'post',
			'supports'        => array( 'title' ),
		)
	);
} );

/** 契約のステータス定義。 */
function apprex_contract_statuses() {
	return array(
		'active'    => '契約中',
		'pending'   => '更新待ち',
		'cancelled' => '解約',
	);
}

/** 契約フィールド定義（meta_key => ラベル）。 */
function apprex_contract_fields() {
	return array(
		'apprex_c_name'      => 'お名前',
		'apprex_c_company'   => '会社名',
		'apprex_c_email'     => 'メール',
		'apprex_c_service'   => 'サービス',
		'apprex_c_plan'      => 'プラン',
		'apprex_c_monthly'   => '月額(円)',
		'apprex_c_start'     => '契約開始日',
		'apprex_c_term'      => '契約年数',
		'apprex_c_renewal'   => '次回更新日',
		'apprex_c_status'    => 'ステータス',
		'apprex_c_autorenew' => '自動継続',
		'apprex_c_note'      => '備考',
	);
}

/* -------------------------------------------------------------------------
 * 編集画面（メタボックス）
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_contract_box', '契約情報', 'apprex_contract_box', 'apprex_contract', 'normal', 'high' );
} );

/**
 * 契約編集メタボックス。
 *
 * @param WP_Post $post 契約。
 */
function apprex_contract_box( $post ) {
	wp_nonce_field( 'apprex_contract_save', 'apprex_contract_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, $k, true );
		return ( '' === $v || null === $v ) ? $d : $v;
	};
	$statuses = apprex_contract_statuses();
	?>
	<style>.apprex-c-tbl th{width:140px;text-align:left;vertical-align:top;padding:8px 6px}.apprex-c-tbl td{padding:6px}</style>
	<table class="apprex-c-tbl">
		<tr><th>お名前</th><td><input type="text" name="apprex_c_name" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_name' ) ); ?>"></td></tr>
		<tr><th>会社名</th><td><input type="text" name="apprex_c_company" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_company' ) ); ?>"></td></tr>
		<tr><th>メール</th><td><input type="email" name="apprex_c_email" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_email' ) ); ?>"></td></tr>
		<tr><th>サービス</th><td><input type="text" name="apprex_c_service" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_service' ) ); ?>" placeholder="アプリ開発 / ホームページ制作 など"></td></tr>
		<tr><th>プラン</th><td><input type="text" name="apprex_c_plan" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_plan' ) ); ?>" placeholder="スタート / ビジネス など"></td></tr>
		<tr><th>月額(円)</th><td><input type="number" name="apprex_c_monthly" value="<?php echo esc_attr( $g( 'apprex_c_monthly', 0 ) ); ?>" min="0" step="100"> 円（税抜）</td></tr>
		<tr><th>契約開始日</th><td><input type="date" name="apprex_c_start" value="<?php echo esc_attr( $g( 'apprex_c_start' ) ); ?>"></td></tr>
		<tr><th>契約年数</th><td><input type="number" name="apprex_c_term" value="<?php echo esc_attr( $g( 'apprex_c_term', 1 ) ); ?>" min="1" step="1"> 年</td></tr>
		<tr><th>次回更新日</th><td><strong><?php echo esc_html( $g( 'apprex_c_renewal', '（保存時に自動計算）' ) ); ?></strong><br><span class="description">契約開始日 + 契約年数で自動計算されます。</span></td></tr>
		<tr><th>ステータス</th><td>
			<select name="apprex_c_status">
				<?php $cs = $g( 'apprex_c_status', 'active' ); ?>
				<?php foreach ( $statuses as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cs, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td></tr>
		<tr><th>自動継続</th><td><label><input type="checkbox" name="apprex_c_autorenew" value="1" <?php checked( 1, (int) $g( 'apprex_c_autorenew', 1 ) ); ?>> 期限到来時に自動で1年延長する</label></td></tr>
		<tr><th>備考</th><td><textarea name="apprex_c_note" rows="3" class="large-text"><?php echo esc_textarea( $g( 'apprex_c_note' ) ); ?></textarea></td></tr>
	</table>
	<?php
}

/**
 * 契約の保存。次回更新日を自動計算し、GAS に同期。
 *
 * @param int $post_id 契約ID。
 */
add_action( 'save_post_apprex_contract', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['apprex_contract_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_contract_nonce'] ) ), 'apprex_contract_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text = array( 'apprex_c_name', 'apprex_c_company', 'apprex_c_service', 'apprex_c_plan', 'apprex_c_start', 'apprex_c_status' );
	foreach ( $text as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	update_post_meta( $post_id, 'apprex_c_email', isset( $_POST['apprex_c_email'] ) ? sanitize_email( wp_unslash( $_POST['apprex_c_email'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_monthly', isset( $_POST['apprex_c_monthly'] ) ? absint( $_POST['apprex_c_monthly'] ) : 0 );
	update_post_meta( $post_id, 'apprex_c_term', max( 1, isset( $_POST['apprex_c_term'] ) ? absint( $_POST['apprex_c_term'] ) : 1 ) );
	update_post_meta( $post_id, 'apprex_c_autorenew', isset( $_POST['apprex_c_autorenew'] ) ? 1 : 0 );
	update_post_meta( $post_id, 'apprex_c_note', isset( $_POST['apprex_c_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apprex_c_note'] ) ) : '' );

	// 次回更新日 = 開始日 + 契約年数。
	$start = get_post_meta( $post_id, 'apprex_c_start', true );
	$term  = max( 1, (int) get_post_meta( $post_id, 'apprex_c_term', true ) );
	if ( $start ) {
		$ts = strtotime( $start . ' +' . $term . ' year' );
		if ( $ts ) {
			update_post_meta( $post_id, 'apprex_c_renewal', wp_date( 'Y-m-d', $ts ) );
		}
	}

	// タイトルを「氏名 / プラン」に整える（無限ループ防止のためフック解除）。
	$name  = get_post_meta( $post_id, 'apprex_c_name', true );
	$plan  = get_post_meta( $post_id, 'apprex_c_plan', true );
	$title = trim( $name . ( $plan ? ' / ' . $plan : '' ) );
	if ( $title && get_the_title( $post_id ) !== $title ) {
		remove_action( 'save_post_apprex_contract', __FUNCTION__ );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		add_action( 'save_post_apprex_contract', __FUNCTION__ );
	}

	apprex_sync_contract_to_gas( $post_id );
} );

/**
 * 契約を GAS（event=contract）へ同期。
 *
 * @param int $post_id 契約ID。
 */
function apprex_sync_contract_to_gas( $post_id ) {
	if ( ! function_exists( 'apprex_dispatch_event' ) ) {
		return;
	}
	apprex_dispatch_event(
		'contract',
		array(
			'id'         => $post_id,
			'name'       => get_post_meta( $post_id, 'apprex_c_name', true ),
			'company'    => get_post_meta( $post_id, 'apprex_c_company', true ),
			'email'      => get_post_meta( $post_id, 'apprex_c_email', true ),
			'service'    => get_post_meta( $post_id, 'apprex_c_service', true ),
			'plan'       => get_post_meta( $post_id, 'apprex_c_plan', true ),
			'monthly'    => (int) get_post_meta( $post_id, 'apprex_c_monthly', true ),
			'start_date' => get_post_meta( $post_id, 'apprex_c_start', true ),
			'term_years' => (int) get_post_meta( $post_id, 'apprex_c_term', true ),
			'renewal'    => get_post_meta( $post_id, 'apprex_c_renewal', true ),
			'auto_renew' => (int) get_post_meta( $post_id, 'apprex_c_autorenew', true ) ? 'ON' : 'OFF',
			'status'     => apprex_contract_status_label( get_post_meta( $post_id, 'apprex_c_status', true ) ),
			'admin_url'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		)
	);
}

/** ステータスのラベル。 */
function apprex_contract_status_label( $key ) {
	$s = apprex_contract_statuses();
	return isset( $s[ $key ] ) ? $s[ $key ] : ( $key ? $key : '契約中' );
}

/* -------------------------------------------------------------------------
 * 一覧カラム
 * ---------------------------------------------------------------------- */
add_filter( 'manage_apprex_contract_posts_columns', function ( $cols ) {
	return array(
		'cb'       => $cols['cb'],
		'title'    => __( '契約者', 'apprex' ),
		'plan'     => __( 'プラン', 'apprex' ),
		'monthly'  => __( '月額', 'apprex' ),
		'start'    => __( '開始日', 'apprex' ),
		'renewal'  => __( '次回更新日', 'apprex' ),
		'cstatus'  => __( 'ステータス', 'apprex' ),
	);
} );

add_action( 'manage_apprex_contract_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'plan':
			echo esc_html( get_post_meta( $post_id, 'apprex_c_service', true ) . ' ' . get_post_meta( $post_id, 'apprex_c_plan', true ) );
			break;
		case 'monthly':
			echo '¥' . esc_html( number_format( (int) get_post_meta( $post_id, 'apprex_c_monthly', true ) ) );
			break;
		case 'start':
			echo esc_html( get_post_meta( $post_id, 'apprex_c_start', true ) );
			break;
		case 'renewal':
			$r = get_post_meta( $post_id, 'apprex_c_renewal', true );
			$auto = (int) get_post_meta( $post_id, 'apprex_c_autorenew', true ) ? '（自動継続）' : '';
			echo esc_html( $r . $auto );
			break;
		case 'cstatus':
			echo esc_html( apprex_contract_status_label( get_post_meta( $post_id, 'apprex_c_status', true ) ) );
			break;
	}
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 発注 → 契約化（ワンクリック）
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_order_to_contract', '契約化', 'apprex_order_to_contract_box', 'apprex_order', 'side', 'high' );
} );

/**
 * 発注編集画面のサイドに「契約として登録」ボタン。
 *
 * @param WP_Post $post 発注。
 */
function apprex_order_to_contract_box( $post ) {
	$linked = (int) get_post_meta( $post->ID, 'apprex_contract_id', true );
	if ( $linked && get_post_status( $linked ) ) {
		echo '<p>この発注は契約 <a href="' . esc_url( admin_url( 'post.php?post=' . $linked . '&action=edit' ) ) . '">#' . (int) $linked . '</a> として登録済みです。</p>';
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_order_to_contract&order=' . $post->ID ), 'apprex_order_to_contract' );
	echo '<p>この発注内容から契約レコードを作成します。</p>';
	echo '<a href="' . esc_url( $url ) . '" class="button button-primary">この発注を契約化する</a>';
}

/** 発注→契約化の実行。 */
add_action( 'admin_post_apprex_order_to_contract', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_order_to_contract' );
	$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	if ( ! $order_id || 'apprex_order' !== get_post_type( $order_id ) ) {
		wp_die( '対象の発注が見つかりません。' );
	}

	$est   = (array) get_post_meta( $order_id, 'apprex_estimate', true );
	$name  = get_post_meta( $order_id, 'apprex_customer_name', true );
	$today = wp_date( 'Y-m-d' );

	$contract_id = wp_insert_post(
		array(
			'post_type'   => 'apprex_contract',
			'post_status' => 'publish',
			'post_title'  => trim( $name . ' / ' . ( $est['plan_label'] ?? '' ) ),
		)
	);
	if ( $contract_id ) {
		update_post_meta( $contract_id, 'apprex_c_name', $name );
		update_post_meta( $contract_id, 'apprex_c_company', get_post_meta( $order_id, 'apprex_customer_company', true ) );
		update_post_meta( $contract_id, 'apprex_c_email', get_post_meta( $order_id, 'apprex_customer_email', true ) );
		update_post_meta( $contract_id, 'apprex_c_service', $est['service_label'] ?? '' );
		update_post_meta( $contract_id, 'apprex_c_plan', $est['plan_label'] ?? '' );
		update_post_meta( $contract_id, 'apprex_c_monthly', (int) ( $est['monthly'] ?? 0 ) );
		update_post_meta( $contract_id, 'apprex_c_start', $today );
		update_post_meta( $contract_id, 'apprex_c_term', 1 );
		update_post_meta( $contract_id, 'apprex_c_renewal', wp_date( 'Y-m-d', strtotime( $today . ' +1 year' ) ) );
		update_post_meta( $contract_id, 'apprex_c_status', 'active' );
		update_post_meta( $contract_id, 'apprex_c_autorenew', 1 );
		update_post_meta( $order_id, 'apprex_contract_id', $contract_id );
		apprex_sync_contract_to_gas( $contract_id );
	}

	wp_safe_redirect( admin_url( 'post.php?post=' . $contract_id . '&action=edit' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * 集計ヘルパー（ダッシュボードで使用）
 * ---------------------------------------------------------------------- */

/**
 * 契約を取得。
 *
 * @param string $status active|pending|cancelled|'' (全件).
 * @return int[] 契約ID配列。
 */
function apprex_get_contracts( $status = '' ) {
	$args = array(
		'post_type'      => 'apprex_contract',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'fields'         => 'ids',
	);
	if ( $status ) {
		$args['meta_key']   = 'apprex_c_status';
		$args['meta_value'] = $status;
	}
	return get_posts( $args );
}

/**
 * MRR（契約中の月額合計）。
 *
 * @return int
 */
function apprex_mrr() {
	$total = 0;
	foreach ( apprex_get_contracts( 'active' ) as $id ) {
		$total += (int) get_post_meta( $id, 'apprex_c_monthly', true );
	}
	return $total;
}
