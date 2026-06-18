<?php
/**
 * Template helpers shared across the theme.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 公式連絡先メール（全サイト統一）。
 * 設定 > APPREX 連携 の通知先メールがあればそれ、無ければ既定。
 *
 * @return string
 */
function apprex_contact_email() {
	$opt = get_option( 'apprex_notify_email', '' );
	$email = $opt ? $opt : 'info@aisjaltd.com';
	return apply_filters( 'apprex_contact_email', $email );
}

/**
 * Read a custom field, transparently using ACF when present and falling back
 * to core post meta otherwise.
 *
 * @param string   $key     Meta key.
 * @param int|null $post_id Post ID (defaults to current).
 * @return string
 */
function apprex_field( $key, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $key, $post_id );
		if ( null !== $value && '' !== $value ) {
			return $value;
		}
	}
	return (string) get_post_meta( $post_id, $key, true );
}

/**
 * Resolve the URL of a published page by slug, with a graceful fallback to a
 * pretty permalink even before the page exists in wp-admin.
 *
 * @param string $slug Page slug.
 * @return string
 */
function apprex_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		return get_permalink( $page );
	}
	return home_url( '/' . ltrim( $slug, '/' ) );
}

/**
 * Echo a section heading block.
 *
 * @param string $eyebrow Small label above the title.
 * @param string $title   Section title.
 * @param string $lead    Optional supporting text.
 */
function apprex_section_head( $eyebrow, $title, $lead = '' ) {
	echo '<div class="section-head is-reveal">';
	if ( $eyebrow ) {
		printf( '<span class="eyebrow">%s</span>', esc_html( $eyebrow ) );
	}
	printf( '<h2>%s</h2>', esc_html( $title ) );
	if ( $lead ) {
		printf( '<p>%s</p>', esc_html( $lead ) );
	}
	echo '</div>';
}

/**
 * Render the two primary hero/footer CTA buttons.
 *
 * @param string $variant 'accent' for hero, 'light' for dark backgrounds.
 */
function apprex_cta_buttons( $variant = 'accent' ) {
	$primary_class = 'btn btn--' . ( 'light' === $variant ? 'light' : 'cta' );
	printf(
		'<a class="%1$s" href="%2$s">%3$s</a>',
		esc_attr( $primary_class ),
		esc_url( apprex_page_url( 'free-trial' ) ),
		esc_html__( '30日間 無料体験', 'apprex' )
	);
	printf(
		'<a class="btn btn--ghost" href="%1$s">%2$s</a>',
		esc_url( apprex_page_url( 'meeting' ) ),
		esc_html__( 'オンライン相談予約', 'apprex' )
	);
}

/**
 * Default FAQ items (spec §7). Used when no editable FAQ source exists yet.
 *
 * @return array<int, array{q:string, a:string}>
 */
function apprex_default_faqs() {
	return array(
		array(
			'q' => __( 'プログラミングの知識は必要ですか？', 'apprex' ),
			'a' => __( '不要です。APPREX はノーコードでアプリを構築できるため、専門知識がなくても運用いただけます。', 'apprex' ),
		),
		array(
			'q' => __( '対応している OS は？', 'apprex' ),
			'a' => __( 'iOS / Android の両プラットフォームに対応しています。1つの管理画面から両対応アプリを公開できます。', 'apprex' ),
		),
		array(
			'q' => __( '料金プランの種類を教えてください。', 'apprex' ),
			'a' => __( 'トライアル / スタート / ビジネスの3プランをご用意しています。詳細は料金プランページをご覧ください。', 'apprex' ),
		),
		array(
			'q' => __( '最低契約期間はありますか？', 'apprex' ),
			'a' => __( 'はい。各プラン1年契約（12ヶ月）です。30日間の無料体験で十分にご検討いただけます。', 'apprex' ),
		),
		array(
			'q' => __( '初期費用はかかりますか？', 'apprex' ),
			'a' => __( '現在、初期費用0円キャンペーン（通常30万円）を実施中です。月額19,800円〜からご利用いただけます。', 'apprex' ),
		),
	);
}
