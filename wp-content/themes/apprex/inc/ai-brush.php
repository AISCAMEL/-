<?php
/**
 * 公開済みの記事を、SEO・MEO・LLMO向けに書き直す（投稿 > 記事の見直し）。
 *
 * - URL（スラッグ）とアイキャッチはそのまま。タイトル・本文・説明文（抜粋）を書き直す。
 * - 冒頭に「この記事でわかること」と結論、疑問の形の見出し、用語のひとこと定義、
 *   「よくある質問」、まとめ、APPREXの関連ページへのリンクを入れる。
 * - Claude のキーがあれば、Web で最新の情報を確かめてから書き直す（古い数字・制度を直す）。
 * - 元の内容は控え（_apprex_brush_backup）と WordPress のリビジョンに残す。「元に戻す」ボタンあり。
 * - まとめて見直すときは、1本ずつ数分おきに順番に処理する（サーバーの時間切れを避ける）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 内部リンクに使うAPPREXのページ（名前 => URL）。 */
function apprex_brush_links() {
	$p = function ( $slug, $fb ) {
		return function_exists( 'apprex_page_url' ) ? apprex_page_url( $slug ) : home_url( $fb );
	};
	return array(
		'料金プラン'               => $p( 'pricing', '/pricing/' ),
		'AIスタジオ（無料でアプリ設計）' => function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : home_url( '/studio/' ),
		'無料オンライン相談'         => $p( 'meeting', '/meeting/' ),
		'機能一覧'                 => $p( 'features', '/features/' ),
		'導入事例'                 => home_url( '/cases/' ),
		'ホームページ制作'           => $p( 'hp-creation', '/hp-creation/' ),
		'補助金の相談'             => home_url( '/subsidy/' ),
	);
}

/**
 * 1本を書き直す。
 *
 * @param int   $id   投稿ID。
 * @param array $opts research（Webで確かめる）, images（写真がなければ入れる）。
 * @return array|WP_Error { title }
 */
function apprex_brush_post( $id, $opts = array() ) {
	$post = get_post( $id );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'not_found', '記事が見つかりません。' );
	}
	$old_text = trim( wp_strip_all_tags( $post->post_content ) );
	if ( mb_strlen( $old_text ) < 200 ) {
		return new WP_Error( 'too_short', '本文が短すぎるため見直しませんでした（200字未満）。' );
	}

	// ほかの記事（内部リンク用）。
	$others = array();
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 15, 'exclude' => array( $id ) ) ) as $o ) {
		$others[] = get_the_title( $o ) . '：' . get_permalink( $o );
	}
	$links = array();
	foreach ( apprex_brush_links() as $name => $url ) {
		$links[] = $name . '：' . $url;
	}

	$body_in = wp_kses( $post->post_content, array( 'h2' => array(), 'h3' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'strong' => array(), 'a' => array( 'href' => array() ) ) );
	$body_in = mb_substr( preg_replace( '/\n{3,}/', "\n\n", $body_in ), 0, 14000 );
	$research = ! empty( $opts['research'] ) && function_exists( 'apprex_claude_research' ) && function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude();

	$user = "次の既存のブログ記事を、検索（Google）とAIの回答（ChatGPT・Gemini・AI Overviewなど）で見つけてもらえるように書き直してください。今日は" . wp_date( 'Y年n月j日' ) . "です。\n\n"
		. "【書き直しのルール】\n"
		. "- 記事のテーマとURLは変えない（同じテーマのまま、内容を良くする）。元の記事の良い部分は活かす。\n"
		. ( $research ? "- 記事の中の数字・制度・サービスの情報が今も正しいか、Web検索で確かめる。古い情報は新しい情報に直し、確かめられないものは書かない。\n" : "- 確かでない数字や情報は書かない。\n" )
		. "- タイトル：32字前後。検索されやすい言葉（主なキーワード）を前半に入れる。\n"
		. "- 説明文（META）：検索結果に出る説明。100〜120字。読むと何が分かるかを書く。\n"
		. "- 本文の最初：<h2>この記事でわかること</h2> の下に <ul> で3つ、続けて結論を2〜3文で先に書く（AIが引用しやすいように）。\n"
		. "- 見出し（h2）は、読者が検索しそうな疑問の形にする（〜とは？／費用はいくら？／どう選ぶ？など）。h2は4〜7個。必要ならh3も使う。\n"
		. "- 専門用語は、出てきたところで1文で説明する。\n"
		. "- お店や地域の会社が読むテーマなら、Googleマップ・Googleビジネスプロフィール（MEO）や「地域名＋業種」で探されることとの関係も、自然に入る範囲で触れる（無理に入れない）。\n"
		. "- 最後の方に <h2>よくある質問</h2> を入れ、<h3>質問</h3><p>答え（80〜150字）</p> を4〜5個。実際に検索されそうな質問にする。\n"
		. "- 最後に <h2>まとめ</h2>。\n"
		. "- 本文の中で、関係のあるAPPREXのページへ自然にリンクする（<a href=\"URL\">）。2〜4個。下の一覧のURLだけを使う。関係が深いほかの記事へのリンクも1〜2個まで入れてよい。\n"
		. "- 宣伝ばかりにしない。APPREXの話は最後の方に1章ほど。\n"
		. "- 本文は約3,000〜4,000字。\n\n"
		. "【出力形式（厳守）】\n1行目「TITLE: タイトル」、2行目「META: 説明文」、3行目以降に本文のHTML（<h2>,<h3>,<p>,<ul>,<ol>,<li>,<strong>,<a> のみ。<h1>不可）。前置きや作業の説明は書かない。\n\n"
		. "【APPREXのページ】\n" . implode( "\n", $links ) . "\n\n"
		. ( $others ? "【ほかの記事】\n" . implode( "\n", $others ) . "\n\n" : '' )
		. "【今のタイトル】\n" . get_the_title( $post ) . "\n\n【今の本文】\n" . $body_in;

	$system = function_exists( 'apprex_ai_article_system' ) ? apprex_ai_article_system() : '';
	if ( $research ) {
		$r = apprex_claude_research( $system, $user, array( 'timeout' => 300, 'searches' => 4, 'optional' => true ) );
		$result = is_wp_error( $r ) ? $r : $r['text'];
	} else {
		$result = apprex_openrouter_complete(
			array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $user ),
			),
			array( 'max_tokens' => 4000, 'timeout' => 180, 'article' => true )
		);
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// TITLE・META・本文に分ける。
	if ( ! preg_match( '/^\s*TITLE\s*[:：]\s*(.+)$/m', $result, $mt, PREG_OFFSET_CAPTURE ) ) {
		return new WP_Error( 'bad_output', '書き直した記事の形が正しくなかったため、変更しませんでした。' );
	}
	$title = trim( wp_strip_all_tags( $mt[1][0] ) );
	$rest  = substr( $result, $mt[0][1] + strlen( $mt[0][0] ) );
	$meta  = '';
	if ( preg_match( '/^\s*META\s*[:：]\s*(.+)$/m', $rest, $mm, PREG_OFFSET_CAPTURE ) ) {
		$meta = mb_substr( trim( wp_strip_all_tags( $mm[1][0] ) ), 0, 140 );
		$rest = substr( $rest, $mm[0][1] + strlen( $mm[0][0] ) );
	}
	$body = function_exists( 'apprex_ai_clean_body' ) ? apprex_ai_clean_body( $rest ) : trim( $rest );
	$new_text = trim( wp_strip_all_tags( $body ) );
	// 短くなりすぎた・見出しがない → 失敗とみなして変えない。
	if ( '' === $title || substr_count( $body, '<h2' ) < 3 || mb_strlen( $new_text ) < max( 800, (int) ( mb_strlen( $old_text ) * 0.7 ) ) ) {
		return new WP_Error( 'bad_output', '書き直した記事が短すぎるか、見出しが足りなかったため、変更しませんでした。' );
	}

	// 元の内容を控えに残す（最初の1回分を残し、元に戻せるように）。
	if ( ! get_post_meta( $id, '_apprex_brush_backup', true ) ) {
		add_post_meta(
			$id,
			'_apprex_brush_backup',
			array(
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'time'    => time(),
			),
			true
		);
	}
	$res = wp_update_post(
		array(
			'ID'           => $id,
			'post_title'   => $title,
			'post_content' => wp_kses_post( $body ),
			'post_excerpt' => $meta,
			'post_name'    => $post->post_name, // URLは変えない。
		),
		true
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	update_post_meta( $id, '_apprex_brushed', time() );
	delete_post_meta( $id, '_apprex_brush_queued' );
	delete_post_meta( $id, '_apprex_brush_error' );

	// 本文に写真がなければ入れる（安い画像モデル・1枚約1円）。
	if ( ! empty( $opts['images'] ) && false === stripos( $body, '<img' ) && function_exists( 'apprex_ai_body_images' ) ) {
		apprex_ai_body_images( $id, $title, 2 );
	}
	return array( 'title' => $title );
}

/** 元に戻す。 */
function apprex_brush_restore( $id ) {
	$b = get_post_meta( $id, '_apprex_brush_backup', true );
	if ( ! is_array( $b ) ) {
		return false;
	}
	$post = get_post( $id );
	wp_update_post(
		array(
			'ID'           => $id,
			'post_title'   => $b['title'],
			'post_content' => $b['content'],
			'post_excerpt' => $b['excerpt'],
			'post_name'    => $post ? $post->post_name : '',
		)
	);
	delete_post_meta( $id, '_apprex_brushed' );
	delete_post_meta( $id, '_apprex_brush_backup' );
	return true;
}

/* まとめて見直す：1本ずつ順番に（cron）。 */
add_action(
	'apprex_brush_one',
	function ( $id ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$opts = get_option( 'apprex_brush_opts', array( 'research' => 1, 'images' => 1 ) );
		$r    = apprex_brush_post( (int) $id, (array) $opts );
		delete_post_meta( (int) $id, '_apprex_brush_queued' );
		if ( is_wp_error( $r ) ) {
			update_post_meta( (int) $id, '_apprex_brush_error', $r->get_error_message() );
		}
	}
);

/* -------------------------------------------------------------------------
 * 管理画面：投稿 > 記事の見直し
 * ---------------------------------------------------------------------- */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php', '記事の見直し（SEO・MEO・LLMO）', '記事の見直し', 'edit_posts', 'apprex-brush', 'apprex_brush_page' );
	}
);

add_action(
	'admin_post_apprex_brush',
	function () {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_brush' ) ) {
			wp_die( '権限がありません。' );
		}
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$one  = isset( $_POST['one'] ) ? absint( $_POST['one'] ) : 0;
		$opts = array(
			'research' => empty( $_POST['research'] ) ? 0 : 1,
			'images'   => empty( $_POST['images'] ) ? 0 : 1,
		);
		update_option( 'apprex_brush_opts', $opts, false );
		$msg = '';
		$typ = 'success';
		if ( 'now' === $do && $one ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$r = apprex_brush_post( $one, $opts );
			if ( is_wp_error( $r ) ) {
				$typ = 'error';
				$msg = '「' . get_the_title( $one ) . '」：' . $r->get_error_message();
				update_post_meta( $one, '_apprex_brush_error', $r->get_error_message() );
			} else {
				$msg = '「' . $r['title'] . '」に書き直しました。';
			}
		} elseif ( 'restore' === $do && $one ) {
			$msg = apprex_brush_restore( $one ) ? '「' . get_the_title( $one ) . '」を元に戻しました。' : '元に戻せる控えがありません。';
		} elseif ( 'queue' === $do && $ids ) {
			$t = time() + 30;
			foreach ( $ids as $i => $id ) {
				if ( current_user_can( 'edit_post', $id ) ) {
					wp_schedule_single_event( $t + $i * 4 * MINUTE_IN_SECONDS, 'apprex_brush_one', array( $id ) );
					update_post_meta( $id, '_apprex_brush_queued', time() );
					delete_post_meta( $id, '_apprex_brush_error' );
				}
			}
			$msg = count( $ids ) . '本を、4分おきに1本ずつ見直します（全部で約' . ( count( $ids ) * 4 ) . '分）。この画面を閉じても進みます。';
		} elseif ( 'cancel' === $do ) {
			$n = 0;
			foreach ( _get_cron_array() as $ts => $hooks ) {
				if ( isset( $hooks['apprex_brush_one'] ) ) {
					foreach ( $hooks['apprex_brush_one'] as $ev ) {
						wp_unschedule_event( $ts, 'apprex_brush_one', $ev['args'] );
						delete_post_meta( (int) $ev['args'][0], '_apprex_brush_queued' );
						$n++;
					}
				}
			}
			$msg = $n . '本の予定を取り消しました。';
		}
		set_transient( 'apprex_brush_notice_' . get_current_user_id(), array( $typ, $msg ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?page=apprex-brush' ) );
		exit;
	}
);

function apprex_brush_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$notice = get_transient( 'apprex_brush_notice_' . get_current_user_id() );
	delete_transient( 'apprex_brush_notice_' . get_current_user_id() );
	$opts   = get_option( 'apprex_brush_opts', array( 'research' => 1, 'images' => 1 ) );
	$claude = function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude();
	$posts  = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC' ) );
	?>
	<div class="wrap">
		<h1>記事の見直し（SEO・MEO・LLMO）</h1>
		<?php if ( $notice && $notice[1] ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>
		<div class="notice notice-info"><p>
			公開済みの記事を、検索とAIの回答で見つけてもらえる形に書き直します。<b>URLとアイキャッチはそのまま</b>です。<br>
			入るもの：検索されやすいタイトルと説明文／冒頭の「この記事でわかること」と結論／疑問の形の見出し／用語の説明／Googleマップ（MEO）との関係（テーマに合うとき）／よくある質問（検索結果用の構造化データも自動で付きます）／まとめ／APPREXのページへのリンク。<br>
			元の内容は控えに残るので、各記事の「元に戻す」でいつでも戻せます。
			記事に使うAI：<b><?php echo esc_html( function_exists( 'apprex_claude_for' ) && apprex_claude_for( 'article' ) ? 'Claude（' . apprex_claude_model() . '）' : 'OpenRouter（' . apprex_openrouter_model() . '）' ); ?></b>。
			費用の目安：1本 約30〜60円（Webで確かめる場合）。
		</p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'apprex_brush' ); ?>
			<input type="hidden" name="action" value="apprex_brush">
			<p>
				<label><input type="checkbox" name="research" value="1" <?php checked( ! empty( $opts['research'] ) ); ?> <?php disabled( ! $claude ); ?>> Webで最新の情報を確かめて直す（Claude APIキーが必要）</label>
				<label style="margin-left:16px"><input type="checkbox" name="images" value="1" <?php checked( ! empty( $opts['images'] ) ); ?>> 本文に写真がなければ2枚入れる（1枚約1円）</label>
			</p>
			<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.apb-c').forEach(function(c){c.checked=this.checked}.bind(this))"></td><th>記事</th><th>文字数</th><th>状態</th><th></th></tr></thead><tbody>
			<?php foreach ( $posts as $p ) : ?>
				<?php
				$len  = mb_strlen( trim( wp_strip_all_tags( $p->post_content ) ) );
				$done = (int) get_post_meta( $p->ID, '_apprex_brushed', true );
				$q    = get_post_meta( $p->ID, '_apprex_brush_queued', true );
				$err  = (string) get_post_meta( $p->ID, '_apprex_brush_error', true );
				?>
				<tr>
					<th class="check-column"><?php if ( $len >= 200 ) : ?><input type="checkbox" class="apb-c" name="ids[]" value="<?php echo (int) $p->ID; ?>" <?php checked( ! $done && ! $q ); ?>><?php endif; ?></th>
					<td><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank"><b><?php echo esc_html( get_the_title( $p ) ); ?></b></a><br><small><?php echo esc_html( get_the_date( 'Y/n/j', $p ) ); ?></small></td>
					<td><?php echo (int) $len; ?>字</td>
					<td>
						<?php if ( $len < 200 ) : ?><span style="color:#6b7280">短すぎるため対象外（不要ならゴミ箱へ）</span>
						<?php elseif ( $q ) : ?><span style="color:#b45309">順番待ち・作業中</span>
						<?php elseif ( $done ) : ?><span style="color:#15803d">見直し済み（<?php echo esc_html( wp_date( 'n/j H:i', $done ) ); ?>）</span>
						<?php else : ?>未<?php endif; ?>
						<?php if ( $err ) : ?><br><span style="color:#b91c1c"><?php echo esc_html( $err ); ?></span><?php endif; ?>
					</td>
					<td style="white-space:nowrap">
						<?php if ( $len >= 200 ) : ?><button class="button" name="one" value="<?php echo (int) $p->ID; ?>" onclick="this.form.do.value='now';this.textContent='書き直しています…（1〜3分）'">今すぐ見直す</button><?php endif; ?>
						<?php if ( get_post_meta( $p->ID, '_apprex_brush_backup', true ) ) : ?><button class="button-link" name="one" value="<?php echo (int) $p->ID; ?>" onclick="if(!confirm('見直す前の内容に戻します。よろしいですか？'))return false;this.form.do.value='restore'">元に戻す</button><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<input type="hidden" name="do" value="queue">
			<p>
				<button class="button button-primary" onclick="this.form.do.value='queue'">チェックした記事をまとめて見直す（4分おきに1本ずつ）</button>
				<button class="button" onclick="this.form.do.value='cancel'">順番待ちを取り消す</button>
			</p>
			<p class="description">まとめて見直す場合、WordPressの自動実行はサイトにアクセスがあったときに動きます。進みが遅いときは、しばらくしてからこの画面を開き直してください。</p>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 記事の「よくある質問」→ 検索結果用の構造化データ（FAQPage）
 * ---------------------------------------------------------------------- */

/**
 * 本文の「よくある質問」から、質問と答えを取り出す。
 *
 * @param string $content 本文。
 * @return array [ [q, a], ... ]
 */
function apprex_post_faqs( $content ) {
	if ( ! preg_match( '/<h2[^>]*>[^<]*(よくある質問|FAQ|Q&amp;A|Q&A)[^<]*<\/h2>(.*?)(?=<h2|$)/su', $content, $m ) ) {
		return array();
	}
	$out = array();
	if ( preg_match_all( '/<h3[^>]*>(.*?)<\/h3>\s*((?:<p[^>]*>.*?<\/p>\s*)+)/su', $m[2], $qs, PREG_SET_ORDER ) ) {
		foreach ( $qs as $q ) {
			$qq = trim( wp_strip_all_tags( $q[1] ) );
			$aa = trim( wp_strip_all_tags( $q[2] ) );
			if ( '' !== $qq && '' !== $aa ) {
				$out[] = array( preg_replace( '/^Q[\.．:：\s]*/u', '', $qq ), preg_replace( '/^A[\.．:：\s]*/u', '', $aa ) );
			}
		}
	}
	return array_slice( $out, 0, 10 );
}

add_action(
	'wp_head',
	function () {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$faqs = apprex_post_faqs( (string) get_post_field( 'post_content', get_queried_object_id() ) );
		if ( ! $faqs ) {
			return;
		}
		$items = array();
		foreach ( $faqs as $f ) {
			$items[] = array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
		}
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	},
	6
);

/* -------------------------------------------------------------------------
 * 記事の最後に「この記事について」（運営会社・所在地・更新日）。
 * 誰が書いた記事か・どこの会社かが分かると、検索・AIの回答・地図検索（MEO）で信頼されやすい。
 * ---------------------------------------------------------------------- */
add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id  = get_the_ID();
		$box = '<aside class="ap-about"><b>この記事について</b>'
			. '<p>APPREX（アプリックス）編集部が書いています。運営：合同会社アイズ（福島県いわき市）。ノーコードのアプリ開発・ホームページ制作・AIで運用するMEO（Googleマップ対策）を、オンラインで全国にご提供しています。</p>'
			. '<p class="ap-about-d">公開：' . esc_html( get_the_date( 'Y年n月j日', $id ) ) . ( get_the_modified_date( 'Ymd', $id ) !== get_the_date( 'Ymd', $id ) ? '／最終更新：' . esc_html( get_the_modified_date( 'Y年n月j日', $id ) ) : '' )
			. '　<a href="' . esc_url( function_exists( 'apprex_page_url' ) ? apprex_page_url( 'company' ) : home_url( '/company/' ) ) . '">会社概要</a></p></aside>';
		return $content . $box;
	},
	14
);

add_action(
	'wp_head',
	function () {
		if ( is_singular( 'post' ) ) {
			echo '<style id="ap-about-css">.ap-about{margin:2.2em 0 1em;padding:16px 18px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;font-size:14px;line-height:1.8}.ap-about b{display:block;margin-bottom:4px;color:#0f172a}.ap-about p{margin:0}.ap-about-d{margin-top:6px!important;font-size:12.5px;color:#64748b}</style>';
		}
	}
);
