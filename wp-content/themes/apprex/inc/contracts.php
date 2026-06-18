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

/**
 * 会員種別の選択肢（値 => 表示名）。`apprex_member_types` フィルタで追加可。
 *
 * @return array
 */
function apprex_member_types() {
	return apply_filters(
		'apprex_member_types',
		array(
			'トライアル' => 'トライアル',
			'スタート'   => 'スタート',
			'ビジネス'   => 'ビジネス',
			'Light'      => 'Light',
			'Standard'   => 'Standard',
			'Premium'    => 'Premium',
		)
	);
}

/**
 * 会員種別コードを表示名に変換。
 *
 * @param string $key 会員種別コード。
 * @return string
 */
function apprex_member_type_label( $key ) {
	$types = apprex_member_types();
	return isset( $types[ $key ] ) ? $types[ $key ] : (string) $key;
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
 * 反映用：選択した問い合わせ／発注から契約フィールドの初期値を作る。
 *
 * @return array meta_key => 値。
 */
function apprex_contract_prefill_data() {
	if ( empty( $_GET['apprex_prefill'] ) ) {
		return array();
	}
	$src = absint( $_GET['apprex_prefill'] );
	$pt  = get_post_type( $src );

	if ( 'apprex_inquiry' === $pt ) {
		return array(
			'apprex_c_name'    => get_post_meta( $src, 'apprex_name', true ),
			'apprex_c_company' => get_post_meta( $src, 'apprex_company', true ),
			'apprex_c_email'   => get_post_meta( $src, 'apprex_email', true ),
		);
	}
	if ( 'apprex_order' === $pt ) {
		$est = (array) get_post_meta( $src, 'apprex_estimate', true );
		return array(
			'apprex_c_name'    => get_post_meta( $src, 'apprex_customer_name', true ),
			'apprex_c_company' => get_post_meta( $src, 'apprex_customer_company', true ),
			'apprex_c_email'   => get_post_meta( $src, 'apprex_customer_email', true ),
			'apprex_c_service' => isset( $est['service_label'] ) ? $est['service_label'] : '',
			'apprex_c_plan'    => isset( $est['plan_label'] ) ? $est['plan_label'] : '',
			'apprex_c_monthly' => isset( $est['monthly'] ) ? (int) $est['monthly'] : '',
		);
	}
	return array();
}

/**
 * 契約編集メタボックス。
 *
 * @param WP_Post $post 契約。
 */
function apprex_contract_box( $post ) {
	wp_nonce_field( 'apprex_contract_save', 'apprex_contract_nonce' );
	$prefill = apprex_contract_prefill_data();
	$g = function ( $k, $d = '' ) use ( $post, $prefill ) {
		$v = get_post_meta( $post->ID, $k, true );
		if ( '' !== $v && null !== $v ) {
			return $v;
		}
		if ( isset( $prefill[ $k ] ) && '' !== $prefill[ $k ] ) {
			return $prefill[ $k ]; // 空欄のみ、選んだ顧客情報で補完。
		}
		return $d;
	};
	$statuses = apprex_contract_statuses();

	// 顧客情報の反映（問い合わせ・発注＝スプレッドと同じデータから）。
	$leads = get_posts(
		array(
			'post_type'      => array( 'apprex_inquiry', 'apprex_order' ),
			'post_status'    => array( 'publish', 'apprex_new' ),
			'posts_per_page' => 50,
		)
	);
	// 0件でもボックスは必ず表示する（「何も出ない＝壊れている？」を防ぐ）。
	$base = remove_query_arg( 'apprex_prefill' );
	echo '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 12px;margin-bottom:14px;">';
	echo '<strong>顧客情報を反映</strong>（問い合わせ・発注から引用）<br>';
	if ( $leads ) {
		echo '<select id="apprex_prefill_select" style="max-width:60%;margin:6px 6px 6px 0;">';
		echo '<option value="">— 顧客を選択 —</option>';
		foreach ( $leads as $lead ) {
			if ( 'apprex_order' === $lead->post_type ) {
				$nm = get_post_meta( $lead->ID, 'apprex_customer_name', true );
				$em = get_post_meta( $lead->ID, 'apprex_customer_email', true );
				$tag = '発注';
			} else {
				$nm = get_post_meta( $lead->ID, 'apprex_name', true );
				$em = get_post_meta( $lead->ID, 'apprex_email', true );
				$tag = get_post_meta( $lead->ID, 'apprex_type', true ) ? get_post_meta( $lead->ID, 'apprex_type', true ) : '問い合わせ';
			}
			$label = sprintf( '[%s] %s %s', $tag, $nm ? $nm : '(無名)', $em ? '／' . $em : '' );
			echo '<option value="' . esc_attr( $lead->ID ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="button" class="button" onclick="(function(){var v=document.getElementById(\'apprex_prefill_select\').value;if(!v)return;var u=new URL(' . wp_json_encode( $base ) . ',window.location.origin);u.searchParams.set(\'apprex_prefill\',v);window.location.href=u.toString();})()">反映する</button>';
		echo '<p class="description" style="margin:6px 0 0;">選んだ顧客の氏名・会社・メール（発注ならプラン・月額も）を、<strong>空欄の項目だけ</strong>に差し込みます。確認後に「公開／更新」で保存してください。</p>';
	} else {
		echo '<p class="description" style="margin:6px 0 0;">引用できる<strong>問い合わせ・発注がまだありません</strong>。サイトのお問い合わせフォーム（このテーマ標準フォーム）から届いた問い合わせ、または発注がここに一覧表示されます。<br>※ Contact Form 7 など別プラグインのフォームから届いた問い合わせはここには出ません。</p>';
	}
	echo '</div>';
	?>
	<style>.apprex-c-tbl th{width:140px;text-align:left;vertical-align:top;padding:8px 6px}.apprex-c-tbl td{padding:6px}</style>
	<table class="apprex-c-tbl">
		<tr><th>お名前</th><td><input type="text" name="apprex_c_name" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_name' ) ); ?>"></td></tr>
		<tr><th>会社名</th><td><input type="text" name="apprex_c_company" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_company' ) ); ?>"></td></tr>
		<tr><th>メール</th><td><input type="email" name="apprex_c_email" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_email' ) ); ?>"></td></tr>
		<tr><th>サービス</th><td><input type="text" name="apprex_c_service" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_service' ) ); ?>" placeholder="アプリ開発 / ホームページ制作 など"></td></tr>
		<tr><th>プラン</th><td>
			<select name="apprex_c_member_type">
				<?php $mt = $g( 'apprex_c_member_type' ); ?>
				<option value="">（未設定）</option>
				<?php foreach ( apprex_member_types() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $mt, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description">選んだプランがマイページ・契約書・集計に反映されます。</span>
		</td></tr>
		<tr><th>月額(円)</th><td><input type="number" name="apprex_c_monthly" value="<?php echo esc_attr( $g( 'apprex_c_monthly', 0 ) ); ?>" min="0" step="100"> 円（税抜）<br><span class="description">契約書 第6条の月額利用料に反映されます。</span></td></tr>
		<tr><th>初期費用(円)</th><td><input type="number" name="apprex_c_initial" value="<?php echo esc_attr( $g( 'apprex_c_initial', 0 ) ); ?>" min="0" step="100"> 円（税抜）<br><span class="description">契約書 第6条の初期費用に反映（キャンペーン無料なら 0）。</span></td></tr>
		<tr><th>制作費(円)</th><td><input type="number" name="apprex_c_production" value="<?php echo esc_attr( $g( 'apprex_c_production', 0 ) ); ?>" min="0" step="1000"> 円（税抜）<br><span class="description">契約書 第6条の制作費に反映（不要なら 0）。</span></td></tr>
		<tr><th>初期費用の支払期日</th><td><input type="date" name="apprex_c_initial_due" value="<?php echo esc_attr( $g( 'apprex_c_initial_due' ) ); ?>"><br><span class="description">契約書 第6条「初期費用は◯年◯月◯日までに支払う」に反映（未入力時は「別途甲が定める日」）。</span></td></tr>
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
		<tr><th>支払い方法</th><td>
			<select name="apprex_c_payment_method">
				<?php $pm = $g( 'apprex_c_payment_method', 'square' ); ?>
				<option value="square" <?php selected( $pm, 'square' ); ?>>Square（自動課金）</option>
				<option value="invoice" <?php selected( $pm, 'invoice' ); ?>>請求書（振込）</option>
			</select>
		</td></tr>
		<tr><th>支払い期日</th><td>毎月 <input type="number" name="apprex_c_payment_day" value="<?php echo esc_attr( $g( 'apprex_c_payment_day', 27 ) ); ?>" min="1" max="31" style="width:70px"> 日<br><span class="description"><strong>Square（自動課金）</strong>は保存時に<strong>契約開始日と同じ日（毎月同日）</strong>へ自動設定されます（29〜31日は月末ズレ防止で28日に調整）。<strong>請求書（振込）</strong>はこの欄で指定した日が支払期日になります。</span></td></tr>
		<tr><th>最終入金確認日<br><span class="description">（消し込み）</span></th><td><input type="date" name="apprex_c_last_paid" value="<?php echo esc_attr( $g( 'apprex_c_last_paid' ) ); ?>"><br><span class="description">入金を確認したらこの日付を更新。一定期間更新が無いと延滞としてSlack通知します。</span></td></tr>
		<tr><th>アプリ製作ページURL</th><td><input type="url" name="apprex_c_app_url" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_app_url' ) ); ?>" placeholder="https://…（コントロールパネル/アプリビルダー）"><br><span class="description">会員マイページの「アプリ製作ページを開く」ボタンのリンク先。</span></td></tr>
		<tr><th>アプリ ログインID</th><td><input type="text" name="apprex_c_app_login" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_app_login' ) ); ?>" autocomplete="off" placeholder="お客様ごとに発行したログインID"><br><span class="description">手動入力。会員マイページの「アプリログイン情報」に表示されます。</span></td></tr>
		<tr><th>アプリ ログインPW</th><td><input type="text" name="apprex_c_app_pass" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_app_pass' ) ); ?>" autocomplete="off" placeholder="お客様ごとに発行したパスワード"><br><span class="description">手動入力。会員本人のみマイページで閲覧できます。</span></td></tr>
		<tr><th>備考</th><td><textarea name="apprex_c_note" rows="3" class="large-text"><?php echo esc_textarea( $g( 'apprex_c_note' ) ); ?></textarea></td></tr>

		<tr><th colspan="2" style="border-top:1px solid #e5e7eb;padding-top:14px;"><strong>契約書・電子契約（マネーフォワード クラウド契約）</strong></th></tr>
		<tr><th>契約書プレビュー</th><td>
			<?php if ( function_exists( 'apprex_contract_doc_url' ) && $post->ID && 'auto-draft' !== $post->post_status ) : ?>
				<a href="<?php echo esc_url( apprex_contract_doc_url( $post->ID ) ); ?>" target="_blank" rel="noopener" class="button">契約書を表示（PDF保存可）</a>
				<span class="description">テンプレートに契約情報を差し込んだ契約書を別タブで表示。<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=apprex_contract&page=apprex-contract-template' ) ); ?>" target="_blank">テンプレートを編集</a></span>
			<?php else : ?>
				<span class="description">先に「公開」して保存すると表示できます。</span>
			<?php endif; ?>
		</td></tr>
		<tr><th>締結ステータス</th><td>
			<select name="apprex_c_mf_status">
				<?php $mfs = $g( 'apprex_c_mf_status', 'none' ); ?>
				<?php foreach ( apprex_mf_statuses() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $mfs, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description">「締結済」に変更して保存すると、会員へ締結完了メールを自動送信します。</span>
		</td></tr>
		<tr><th>MF締結ページURL</th><td><input type="url" name="apprex_c_mf_url" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_mf_url' ) ); ?>" placeholder="https://… マネーフォワード クラウド契約の締結URL"><br><span class="description">お客様が署名するページのURL。マイページに「マネーフォワードで締結する」ボタンとして表示されます。</span></td></tr>
		<tr><th>署名済みPDF URL</th><td><input type="url" name="apprex_c_mf_signed_pdf" class="regular-text" value="<?php echo esc_attr( $g( 'apprex_c_mf_signed_pdf' ) ); ?>" placeholder="https://… 締結後にMFからDLしてメディアにアップしたPDF"><br><span class="description">締結後の署名済みPDFのURL。マイページからダウンロードできるようになります。</span></td></tr>
		<tr><th>締結日</th><td><input type="date" name="apprex_c_mf_signed_at" value="<?php echo esc_attr( $g( 'apprex_c_mf_signed_at' ) ); ?>"></td></tr>
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

	$text = array( 'apprex_c_name', 'apprex_c_company', 'apprex_c_service', 'apprex_c_start', 'apprex_c_status', 'apprex_c_last_paid', 'apprex_c_member_type', 'apprex_c_app_login', 'apprex_c_app_pass' );
	foreach ( $text as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}

	// プランは「会員種別→プラン」選択に統一。選択があれば apprex_c_plan に同期（下位互換：タイトル/マイページ/集計用）。
	$selected_plan = get_post_meta( $post_id, 'apprex_c_member_type', true );
	if ( '' !== $selected_plan ) {
		update_post_meta( $post_id, 'apprex_c_plan', $selected_plan );
	}
	update_post_meta( $post_id, 'apprex_c_email', isset( $_POST['apprex_c_email'] ) ? sanitize_email( wp_unslash( $_POST['apprex_c_email'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_monthly', isset( $_POST['apprex_c_monthly'] ) ? absint( $_POST['apprex_c_monthly'] ) : 0 );
	update_post_meta( $post_id, 'apprex_c_initial', isset( $_POST['apprex_c_initial'] ) ? absint( $_POST['apprex_c_initial'] ) : 0 );
	update_post_meta( $post_id, 'apprex_c_production', isset( $_POST['apprex_c_production'] ) ? absint( $_POST['apprex_c_production'] ) : 0 );
	update_post_meta( $post_id, 'apprex_c_initial_due', isset( $_POST['apprex_c_initial_due'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_c_initial_due'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_term', max( 1, isset( $_POST['apprex_c_term'] ) ? absint( $_POST['apprex_c_term'] ) : 1 ) );
	update_post_meta( $post_id, 'apprex_c_autorenew', isset( $_POST['apprex_c_autorenew'] ) ? 1 : 0 );
	update_post_meta( $post_id, 'apprex_c_payment_method', ( isset( $_POST['apprex_c_payment_method'] ) && 'invoice' === $_POST['apprex_c_payment_method'] ) ? 'invoice' : 'square' );
	// 支払日：Square（自動課金）は契約開始日と同じ「日」に毎月課金（月末ズレ防止で28日を上限にクランプ）。
	// 請求書（振込）は管理画面で指定した日を使用。
	$pay_method = get_post_meta( $post_id, 'apprex_c_payment_method', true );
	$pay_day    = min( 31, max( 1, isset( $_POST['apprex_c_payment_day'] ) ? absint( $_POST['apprex_c_payment_day'] ) : 27 ) );
	if ( 'square' === $pay_method ) {
		$cstart = get_post_meta( $post_id, 'apprex_c_start', true );
		if ( $cstart ) {
			$d = (int) wp_date( 'j', strtotime( $cstart ) );
			if ( $d >= 1 ) {
				$pay_day = min( 28, $d );
			}
		}
	}
	update_post_meta( $post_id, 'apprex_c_payment_day', $pay_day );
	update_post_meta( $post_id, 'apprex_c_app_url', isset( $_POST['apprex_c_app_url'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_c_app_url'] ) ) : '' );
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
			'payment_method' => ( 'invoice' === get_post_meta( $post_id, 'apprex_c_payment_method', true ) ) ? '請求書' : 'Square',
			'payment_day'    => (int) get_post_meta( $post_id, 'apprex_c_payment_day', true ),
			'last_paid'      => get_post_meta( $post_id, 'apprex_c_last_paid', true ),
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
		update_post_meta( $contract_id, 'apprex_c_initial', (int) ( $est['initial'] ?? 0 ) ); // 見積りの初期費用（キャンペーン適用後）を初期反映。
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
