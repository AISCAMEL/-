<?php
/**
 * ホームページ制作プラン（ヒアリング → 提案 → 月額見積り → 3年契約で運用）。
 *
 * - 制作費0円・月額（Light / Standard / Premium）
 * - 契約期間は3年（36か月）。途中で解約するときの解約金は「残りの月数 × 月額」
 * - 入口は AIプランナーではなく、ヒアリングの申し込み（担当者が提案書・企画・デザインを作る）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 契約期間（か月）。 */
function apprex_hp_term_months() {
	return 36;
}

/** 3年たったあとの説明（ページ・提案・チャットで同じ言葉を使う）。 */
function apprex_hp_renewal_text() {
	return '1年ごとの自動更新です。更新しないときは、期間が終わる1か月前までにお知らせください。更新したあとの年の途中で解約するときも、解約金は「残りの月数 × 月額」です。';
}

/** 月額プラン（料金表の設定と同じ金額）。key => [名前, 月額, 中身]。 */
function apprex_hp_plans() {
	return array(
		'light'    => array( 'Light', 9800, '〜5ページ・月1回の更新サポート' ),
		'standard' => array( 'Standard', 19800, '〜10ページ＋LP1本・月2回の更新サポート' ),
		'premium'  => array( 'Premium', 39800, '〜20ページ・EC・会員機能・月3回の更新サポート' ),
	);
}

/**
 * 途中で解約するときの解約金（残りの月数 × 月額）。
 *
 * @param int $monthly 月額。
 * @param int $used    使った月数。
 * @return int
 */
function apprex_hp_penalty( $monthly, $used ) {
	return max( 0, apprex_hp_term_months() - max( 0, (int) $used ) ) * max( 0, (int) $monthly );
}

/** ヒアリングの質問。key => [見出し, 種類, 選択肢]。 */
function apprex_hp_hearing_questions() {
	return array(
		'kind'     => array( '作りたいサイト', 'select', array( '会社・お店のホームページ', 'サービス紹介・LP', '採用サイト', 'ネットショップ（EC）', '今のサイトのリニューアル', 'まだ決めていない' ) ),
		'industry' => array( '業種・事業の内容', 'text', '例：いわき市の美容室（スタッフ5名）' ),
		'goal'     => array( 'いちばんの目的', 'select', array( '問い合わせ・予約を増やしたい', '会社・お店の信頼を高めたい', '採用につなげたい', 'ネットで売りたい', '情報をきちんと載せたい', 'そのほか' ) ),
		'target'   => array( '見てほしいお客様', 'text', '例：30〜40代の近所の女性' ),
		'pages'    => array( 'ほしいページ', 'checks', array( 'トップ', '会社・お店の紹介', 'サービス・メニュー', '料金', '実績・事例', 'お知らせ・ブログ', 'よくある質問', '採用', 'お問い合わせ', 'ネット予約', 'ネットショップ' ) ),
		'now'      => array( '今のホームページ（あれば）', 'text', 'https://' ),
		'ref'      => array( '参考にしたいサイト（あれば）', 'textarea', 'URLや「〇〇の雰囲気が好き」など' ),
		'assets'   => array( '文章・写真', 'select', array( 'だいたい用意できる', '一部ある', 'ない（作ってほしい）' ) ),
		'domain'   => array( 'ドメイン（〇〇.jp など）', 'select', array( '持っている', '持っていない', 'わからない' ) ),
		'when'     => array( '公開したい時期', 'select', array( '1か月以内', '2〜3か月以内', '半年以内', 'まだ決めていない' ) ),
		'plan'     => array( '気になるプラン', 'select', array( 'おまかせ（提案してほしい）', 'Light（月額9,800円）', 'Standard（月額19,800円）', 'Premium（月額39,800円）' ) ),
	);
}

/** フォームの中身（ヒアリングの質問）。 */
function apprex_hp_hearing_fields() {
	foreach ( apprex_hp_hearing_questions() as $k => $q ) {
		$name = 'hp[' . $k . ']';
		echo '<label><span class="apprex-form__lbl">' . esc_html( $q[0] ) . ( in_array( $k, array( 'kind', 'industry', 'goal' ), true ) ? ' <span class="req">*</span>' : '' ) . '</span>';
		if ( 'select' === $q[1] ) {
			echo '<select name="' . esc_attr( $name ) . '"' . ( in_array( $k, array( 'kind', 'goal' ), true ) ? ' required' : '' ) . '><option value="">選んでください</option>';
			foreach ( $q[2] as $o ) {
				echo '<option>' . esc_html( $o ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checks' === $q[1] ) {
			echo '<span style="display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:4px">';
			foreach ( $q[2] as $o ) {
				echo '<span style="white-space:nowrap"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $o ) . '"> ' . esc_html( $o ) . '</span>';
			}
			echo '</span>';
		} elseif ( 'textarea' === $q[1] ) {
			echo '<textarea name="' . esc_attr( $name ) . '" rows="3" placeholder="' . esc_attr( $q[2] ) . '"></textarea>';
		} else {
			echo '<input type="text" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $q[2] ) . '"' . ( 'industry' === $k ? ' required' : '' ) . '>';
		}
		echo '</label>';
	}
}

/**
 * 送られてきた答えを、見出し => 答え に。
 *
 * @param array $raw hp[...]。
 * @return array
 */
function apprex_hp_hearing_collect( $raw ) {
	$out = array();
	foreach ( apprex_hp_hearing_questions() as $k => $q ) {
		$v = isset( $raw[ $k ] ) ? $raw[ $k ] : '';
		$v = is_array( $v ) ? implode( '、', array_map( 'sanitize_text_field', $v ) ) : ( 'textarea' === $q[1] ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v ) );
		if ( '' !== trim( $v ) ) {
			$out[ $q[0] ] = mb_substr( $v, 0, 600 );
		}
	}
	return $out;
}
