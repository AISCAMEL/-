<?php
/**
 * 内部リンク自動化（SEO）。
 *
 * - 記事末尾に「関連記事」を自動表示（カテゴリ/タグ/タイトル類似度で関連度を採点）。
 * - 本文中のキーワードを、指定URLへ自動でリンク（管理画面のマップ）。
 * 重複301済みの記事は関連候補から除外。非破壊（保存内容は変更しない）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 関連記事（自動）
 * ---------------------------------------------------------------------- */

/** タイトル類似度（ai-blog の関数があれば利用、無ければ簡易版）。 */
function apprex_il_similarity( $a, $b ) {
	if ( function_exists( 'apprex_ai_similarity' ) ) {
		return apprex_ai_similarity( $a, $b );
	}
	$a = mb_strtolower( wp_strip_all_tags( (string) $a ) );
	$b = mb_strtolower( wp_strip_all_tags( (string) $b ) );
	if ( '' === $a || '' === $b ) {
		return 0;
	}
	$pct = 0;
	similar_text( $a, $b, $pct );
	return (float) $pct;
}

/**
 * 関連記事のIDを関連度順に返す。
 *
 * @param int $id 対象記事ID。
 * @param int $n  取得件数。
 * @return int[]
 */
function apprex_related_posts( $id, $n = 4 ) {
	$cats  = wp_get_post_categories( $id );
	$tags  = wp_get_post_tags( $id, array( 'fields' => 'ids' ) );
	$title = get_the_title( $id );
	$cand  = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'post__not_in'   => array( (int) $id ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
	$scored = array();
	$recent = array();
	foreach ( $cand as $cid ) {
		if ( get_post_meta( $cid, '_apprex_301_to', true ) ) {
			continue; // 301済みは除外。
		}
		$recent[] = $cid;
		$sim      = apprex_il_similarity( $title, get_the_title( $cid ) );
		if ( $sim >= 82 ) {
			continue; // ほぼ重複は関連に出さない。
		}
		$s  = 0;
		$s += count( array_intersect( $cats, wp_get_post_categories( $cid ) ) ) * 8;
		$s += count( array_intersect( $tags, wp_get_post_tags( $cid, array( 'fields' => 'ids' ) ) ) ) * 6;
		$s += $sim * 0.4;
		if ( $s > 0 ) {
			$scored[ $cid ] = $s;
		}
	}
	arsort( $scored );
	$ids = array_slice( array_keys( $scored ), 0, $n );
	// 関連が足りなければ最近の記事で補完（常に内部リンクを張る）。
	if ( count( $ids ) < $n ) {
		foreach ( $recent as $cid ) {
			if ( count( $ids ) >= $n ) {
				break;
			}
			if ( ! in_array( $cid, $ids, true ) ) {
				$ids[] = $cid;
			}
		}
	}
	return $ids;
}

/** 記事末尾に関連記事ブロックを自動表示。 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || is_feed() || ! is_singular( 'post' ) ) {
		return $content;
	}
	if ( ! get_option( 'apprex_related_enabled', 1 ) ) {
		return $content;
	}
	$ids = apprex_related_posts( get_the_ID(), 4 );
	if ( empty( $ids ) ) {
		return $content;
	}
	$html = '<section class="apprex-related"><h2 class="apprex-related__h">関連記事</h2><div class="apprex-related__grid">';
	foreach ( $ids as $rid ) {
		$thumb = get_the_post_thumbnail( $rid, 'apprex-card', array( 'loading' => 'lazy' ) );
		$html .= '<a class="apprex-related__card" href="' . esc_url( get_permalink( $rid ) ) . '">'
			. ( $thumb ? '<span class="apprex-related__thumb">' . $thumb . '</span>' : '' )
			. '<span class="apprex-related__title">' . esc_html( get_the_title( $rid ) ) . '</span>'
			. '<span class="apprex-related__date">' . esc_html( get_the_date( 'Y.m.d', $rid ) ) . '</span></a>';
	}
	$html .= '</div></section>';
	return $content . $html;
}, 25 );

/* -------------------------------------------------------------------------
 * 本文中キーワードの自動リンク
 * ---------------------------------------------------------------------- */

/** キーワード→URL のマップを取得。 */
function apprex_internal_link_map() {
	$raw = (string) get_option( 'apprex_internal_link_map', '' );
	$map = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, '|' ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( '' !== $parts[0] && filter_var( $parts[1], FILTER_VALIDATE_URL ) ) {
			$map[ $parts[0] ] = $parts[1];
		}
	}
	return $map;
}

/**
 * HTML本文中のキーワードを安全にリンク化（タグ内・既存リンク・見出しは除外）。
 *
 * @param string $html 本文HTML。
 * @param array  $map  keyword=>url。
 * @param int    $max  最大リンク数。
 * @param string $self 自ページURL（自己リンク防止）。
 * @return string
 */
function apprex_apply_internal_links( $html, $map, $max, $self = '' ) {
	$tokens = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! $tokens ) {
		return $html;
	}
	$skip = 0;
	$done = 0;
	$used = array();
	foreach ( $tokens as &$t ) {
		if ( '' === $t || null === $t ) {
			continue;
		}
		if ( '<' === $t[0] ) {
			if ( preg_match( '/^<\s*(a|h1|h2|h3)\b/i', $t ) ) {
				$skip++;
			} elseif ( preg_match( '/^<\s*\/\s*(a|h1|h2|h3)\s*>/i', $t ) ) {
				$skip = max( 0, $skip - 1 );
			}
			continue;
		}
		if ( $skip > 0 || $done >= $max ) {
			continue;
		}
		foreach ( $map as $kw => $url ) {
			if ( $done >= $max ) {
				break;
			}
			if ( isset( $used[ $kw ] ) || ( $self && $url === $self ) ) {
				continue;
			}
			$pos = mb_strpos( $t, $kw );
			if ( false !== $pos ) {
				$before = mb_substr( $t, 0, $pos );
				$after  = mb_substr( $t, $pos + mb_strlen( $kw ) );
				$t      = $before . '<a href="' . esc_url( $url ) . '">' . esc_html( $kw ) . '</a>' . $after;
				$used[ $kw ] = 1;
				$done++;
				break; // 1トークンにつき1リンクまで（安全側）。
			}
		}
	}
	unset( $t );
	return implode( '', $tokens );
}

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || is_feed() || ! is_singular( 'post' ) ) {
		return $content;
	}
	if ( ! get_option( 'apprex_internal_link_enabled', 0 ) ) {
		return $content;
	}
	$map = apprex_internal_link_map();
	if ( empty( $map ) ) {
		return $content;
	}
	$max = max( 1, (int) get_option( 'apprex_internal_link_max', 4 ) );
	return apprex_apply_internal_links( $content, $map, $max, get_permalink( get_the_ID() ) );
}, 15 );

/* -------------------------------------------------------------------------
 * 設定ページ
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 内部リンク(SEO)', 'APPREX 内部リンク', 'manage_options', 'apprex-internal-links', 'apprex_internal_links_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_il', 'apprex_related_enabled', array( 'sanitize_callback' => 'absint', 'default' => 1 ) );
	register_setting( 'apprex_il', 'apprex_internal_link_enabled', array( 'sanitize_callback' => 'absint', 'default' => 0 ) );
	register_setting( 'apprex_il', 'apprex_internal_link_max', array( 'sanitize_callback' => 'absint', 'default' => 4 ) );
	register_setting( 'apprex_il', 'apprex_internal_link_map', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
} );

function apprex_internal_links_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX 内部リンク（SEO）</h1>
		<p>記事同士を自動でつなぎ、回遊性と検索評価を高めます。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_il' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th>関連記事の自動表示</th>
					<td><label><input type="checkbox" name="apprex_related_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_related_enabled', 1 ) ); ?>> 記事末尾に「関連記事」を自動表示する</label>
					<p class="description">カテゴリ・タグ・タイトルの関連度で自動選定します（重複301済みは除外）。</p></td>
				</tr>
				<tr>
					<th>本文キーワードの自動リンク</th>
					<td><label><input type="checkbox" name="apprex_internal_link_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_internal_link_enabled', 0 ) ); ?>> 本文中の指定キーワードを自動でリンクする</label>
					<p style="margin-top:8px;">1記事あたりの最大リンク数：
						<input type="number" name="apprex_internal_link_max" value="<?php echo esc_attr( (int) get_option( 'apprex_internal_link_max', 4 ) ); ?>" min="1" max="20" style="width:70px;"></p>
					<p style="margin-top:8px;"><label>キーワード → リンク先URL（1行に1組、<code>キーワード | URL</code>）</label></p>
					<textarea name="apprex_internal_link_map" rows="8" class="large-text" placeholder="アプリ開発 | <?php echo esc_attr( home_url( '/pricing/' ) ); ?>&#10;ノーコード | <?php echo esc_attr( home_url( '/features/' ) ); ?>&#10;マッチングアプリ | <?php echo esc_attr( home_url( '/clp/matching-app/' ) ); ?>&#10;ホームページ制作 | <?php echo esc_attr( home_url( '/hp-creation/' ) ); ?>"><?php echo esc_textarea( (string) get_option( 'apprex_internal_link_map', '' ) ); ?></textarea>
					<p class="description">各キーワードは1記事につき最初の1回だけリンクされます。見出し・既存リンク内・自ページへのリンクは対象外（安全設計）。重要ページ（料金・特徴・LP等）へ誘導すると効果的です。</p></td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
