<?php
/**
 * 顧客メモ／議事録／Googleドライブ・動画リンク 格納ボックス。
 *
 * お問い合わせ・見積発注・契約の各レコードに、以下を保存できる共通メタボックスを追加する。
 *  - Googleドライブ フォルダURL（その顧客の格納先フォルダ）
 *  - 関連リンク（議事録ファイル・ミーティング動画・資料など。1行 = ラベル | URL）
 *  - 議事録／メモ（追記式ログ。日時・担当者つきで蓄積）
 *
 * Google Drive 本体へのアップロードはおこなわず、Drive 側に置いたファイル/フォルダの
 * 共有リンクを貼って一元管理する方式（OAuth不要・すぐ運用可能）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** このボックスを表示する投稿タイプ。 */
function apprex_crm_post_types() {
	return array( 'apprex_inquiry', 'apprex_order', 'apprex_contract' );
}

/** メタボックス登録。 */
add_action( 'add_meta_boxes', function () {
	foreach ( apprex_crm_post_types() as $pt ) {
		add_meta_box(
			'apprex_crm_notes',
			'📁 メモ・議事録・Googleドライブ',
			'apprex_crm_notes_box',
			$pt,
			'normal',
			'default'
		);
	}
} );

/** 関連リンクの文字列（1行=「ラベル | URL」）を配列に。 */
function apprex_crm_parse_links( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( false !== strpos( $line, '|' ) ) {
			list( $label, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
		} else {
			$label = '';
			$url   = $line;
		}
		if ( '' === $url ) {
			continue;
		}
		$out[] = array(
			'label' => $label,
			'url'   => $url,
		);
	}
	return $out;
}

/** メタボックス本体。 */
function apprex_crm_notes_box( $post ) {
	wp_nonce_field( 'apprex_crm_save', 'apprex_crm_nonce' );

	$drive = (string) get_post_meta( $post->ID, 'apprex_crm_drive', true );
	$links = (string) get_post_meta( $post->ID, 'apprex_crm_links', true );
	$log   = get_post_meta( $post->ID, 'apprex_crm_log', true );
	$log   = is_array( $log ) ? $log : array();
	?>
	<style>
		.apprex-crm label.h { display:block; font-weight:600; margin:14px 0 4px; }
		.apprex-crm .desc { color:#6b7280; font-size:12px; margin:2px 0 0; }
		.apprex-crm input[type=url], .apprex-crm textarea { width:100%; }
		.apprex-crm .links a { display:inline-flex; align-items:center; gap:4px; margin:0 10px 6px 0; }
		.apprex-crm .log { margin:8px 0 0; border:1px solid #e5e7eb; border-radius:8px; max-height:280px; overflow:auto; }
		.apprex-crm .log__item { padding:10px 12px; border-bottom:1px solid #f0f0f0; }
		.apprex-crm .log__item:last-child { border-bottom:0; }
		.apprex-crm .log__meta { font-size:11px; color:#6b7280; margin-bottom:4px; }
		.apprex-crm .log__text { white-space:pre-wrap; }
		.apprex-crm .drive-open { margin-left:8px; }
	</style>
	<div class="apprex-crm">

		<label class="h" for="apprex_crm_drive">Googleドライブ フォルダ URL</label>
		<input type="url" id="apprex_crm_drive" name="apprex_crm_drive" value="<?php echo esc_attr( $drive ); ?>" placeholder="https://drive.google.com/drive/folders/…">
		<?php if ( $drive ) : ?>
			<p class="desc">→ <a href="<?php echo esc_url( $drive ); ?>" target="_blank" rel="noopener">このお客様のドライブフォルダを開く ↗</a></p>
		<?php else : ?>
			<p class="desc">Googleドライブでこのお客様用フォルダを作成し、「共有」→ リンクをここに貼り付けてください。</p>
		<?php endif; ?>

		<?php
		// Drive連携（サービスアカウント）が有効なら、自動作成ボタン等を差し込む。
		do_action( 'apprex_crm_after_drive', $post, $drive );
		?>

		<label class="h" for="apprex_crm_links">関連リンク（議事録ファイル・ミーティング動画・資料など）</label>
		<textarea id="apprex_crm_links" name="apprex_crm_links" rows="4" placeholder="キックオフ議事録 | https://docs.google.com/...&#10;初回MTG動画 | https://drive.google.com/file/d/..."><?php echo esc_textarea( $links ); ?></textarea>
		<p class="desc">1行に1つ。「ラベル | URL」の形式（縦棒で区切り）。URLだけでも可。</p>
		<?php $parsed = apprex_crm_parse_links( $links ); ?>
		<?php if ( $parsed ) : ?>
			<p class="links" style="margin-top:8px;">
				<?php foreach ( $parsed as $l ) : ?>
					<a href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener">🔗 <?php echo esc_html( '' !== $l['label'] ? $l['label'] : $l['url'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<label class="h" for="apprex_crm_note_new">議事録・メモを追記</label>
		<textarea id="apprex_crm_note_new" name="apprex_crm_note_new" rows="4" placeholder="例）6/18 オンラインMTG。要件ヒアリング。予算◯◯、希望納期◯◯。次回までに見積提示。"></textarea>
		<p class="desc">「更新」を押すと、日時・担当者つきで下の履歴に追記されます（過去の記録は消えません）。</p>

		<?php if ( $log ) : ?>
			<label class="h">これまでの議事録・メモ（新しい順）</label>
			<div class="log">
				<?php foreach ( array_reverse( $log ) as $entry ) : ?>
					<div class="log__item">
						<div class="log__meta"><?php echo esc_html( $entry['t'] . '　' . $entry['u'] ); ?></div>
						<div class="log__text"><?php echo esc_html( $entry['m'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/** 保存。 */
function apprex_crm_save( $post_id ) {
	if ( ! isset( $_POST['apprex_crm_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apprex_crm_nonce'] ) ), 'apprex_crm_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, 'apprex_crm_drive', isset( $_POST['apprex_crm_drive'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_crm_drive'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_crm_links', isset( $_POST['apprex_crm_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apprex_crm_links'] ) ) : '' );

	// 追記があればログへ（日時・担当者つき）。
	$new = isset( $_POST['apprex_crm_note_new'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apprex_crm_note_new'] ) ) : '';
	if ( '' !== trim( $new ) ) {
		$log = get_post_meta( $post_id, 'apprex_crm_log', true );
		$log = is_array( $log ) ? $log : array();
		$user = wp_get_current_user();
		$log[] = array(
			't' => wp_date( 'Y-m-d H:i' ),
			'u' => $user && $user->display_name ? $user->display_name : '担当者',
			'm' => $new,
		);
		update_post_meta( $post_id, 'apprex_crm_log', $log );
	}
}
foreach ( apprex_crm_post_types() as $apprex_crm_pt ) {
	add_action( 'save_post_' . $apprex_crm_pt, 'apprex_crm_save' );
}

/** 一覧画面に「📁」列を追加（ドライブ/メモの有無がひと目で分かる）。 */
foreach ( apprex_crm_post_types() as $apprex_crm_pt ) {
	add_filter( 'manage_' . $apprex_crm_pt . '_posts_columns', function ( $cols ) {
		$cols['apprex_crm'] = '📁';
		return $cols;
	} );
	add_action( 'manage_' . $apprex_crm_pt . '_posts_custom_column', function ( $col, $post_id ) {
		if ( 'apprex_crm' !== $col ) {
			return;
		}
		$marks = array();
		if ( get_post_meta( $post_id, 'apprex_crm_drive', true ) ) {
			$marks[] = '<span title="ドライブあり">📁</span>';
		}
		$log = get_post_meta( $post_id, 'apprex_crm_log', true );
		if ( is_array( $log ) && $log ) {
			$marks[] = '<span title="議事録 ' . count( $log ) . '件">📝' . count( $log ) . '</span>';
		}
		echo $marks ? wp_kses_post( implode( ' ', $marks ) ) : '<span style="color:#cbd5e1;">—</span>';
	}, 10, 2 );
}
