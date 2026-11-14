<?php
/**
 * カーメル：車両ページ 動画埋め込み
 * ---------------------------------------------------------------------------
 * 在庫（portfolio）編集画面に「動画URL」入力欄を追加し、入力があれば
 * 車両詳細ページの画像の下に動画を表示する。
 *   ・YouTube の視聴URL（watch?v= / youtu.be / shorts / embed）→ iframe 埋め込み
 *   ・.mp4 等の直リンク → <video> 埋め込み
 *   ・空なら何も表示しない
 * 保存は post_meta 'carmel_video'。固有名＋function_exists ガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---- 編集画面：動画URL 入力欄 ---- */
if ( ! function_exists( 'carmelx_video_box' ) ) {
	function carmelx_video_box( $post ) {
		wp_nonce_field( 'carmelx_video_save', 'carmelx_video_nonce' );
		$v = get_post_meta( $post->ID, 'carmel_video', true );
		echo '<p style="margin:0 0 6px;"><label for="carmelx_video" style="font-weight:600;">動画URL</label></p>';
		echo '<input type="url" id="carmelx_video" name="carmelx_video" value="' . esc_attr( (string) $v ) . '" style="width:100%;" placeholder="https://www.youtube.com/watch?v=XXXXX または https://example.com/movie.mp4">';
		echo '<p style="color:#888;font-size:12px;margin:8px 0 0;">※ YouTubeの視聴URL、または .mp4 などの動画URLを貼って「更新」すると、車両ページの画像の下に動画が表示されます。空欄なら表示されません。</p>';
	}
}

if ( ! function_exists( 'carmelx_video_add_box' ) ) {
	function carmelx_video_add_box() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_meta_box( 'carmelx_video', '動画（車両ページに表示）', 'carmelx_video_box', 'portfolio', 'normal', 'high' );
	}
	add_action( 'add_meta_boxes', 'carmelx_video_add_box' );
}

if ( ! function_exists( 'carmelx_video_save' ) ) {
	function carmelx_video_save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST['carmelx_video_nonce'] ) || ! wp_verify_nonce( $_POST['carmelx_video_nonce'], 'carmelx_video_save' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( isset( $_POST['carmelx_video'] ) ) {
			$url = esc_url_raw( trim( (string) wp_unslash( $_POST['carmelx_video'] ) ) );
			update_post_meta( $post_id, 'carmel_video', $url );
		}
	}
	add_action( 'save_post_portfolio', 'carmelx_video_save' );
}

/* ---- 埋め込みHTMLを作る ---- */
if ( ! function_exists( 'carmelx_video_embed_html' ) ) {
	function carmelx_video_embed_html( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) { return ''; }

		// YouTube ID 抽出
		$yt = '';
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
			$yt = $m[1];
		} elseif ( preg_match( '~[?&]v=([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
			$yt = $m[1];
		}
		if ( $yt !== '' ) {
			$src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $yt );
			return '<div class="cmx-video__frame"><iframe src="' . esc_url( $src ) . '" title="車両動画" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
		}
		// 直リンク動画
		if ( preg_match( '~\.(mp4|webm|ogg|mov)(\?.*)?$~i', $url ) ) {
			return '<div class="cmx-video__frame"><video src="' . esc_url( $url ) . '" controls playsinline preload="metadata"></video></div>';
		}
		// それ以外はリンク表示
		return '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">動画を見る</a></p>';
	}
}

/* ---- front：画像の下に動画セクションを差し込む ---- */
if ( ! function_exists( 'carmelx_video_render' ) ) {
	function carmelx_video_render() {
		if ( is_admin() || ! is_singular( 'portfolio' ) ) { return; }
		$url = trim( (string) get_post_meta( get_the_ID(), 'carmel_video', true ) );
		if ( $url === '' ) { return; }
		$embed = carmelx_video_embed_html( $url );
		if ( $embed === '' ) { return; }
		?>
<style id="carmel-video-css">
.cmx-video{max-width:1040px;margin:18px auto 0;background:#fff;border:1px solid #e2e8e4;border-radius:16px;padding:18px 20px;box-sizing:border-box;}
.cmx-video__t{display:flex;align-items:center;gap:9px;font-size:18px;font-weight:900;color:#20292b;margin:0 0 12px;}
.cmx-video__t .mk{width:26px;height:26px;border-radius:50%;background:#2cac44;color:#fff;display:grid;place-items:center;flex-shrink:0;}
.cmx-video__t .mk svg{width:16px;height:16px;}
.cmx-video__frame{position:relative;width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000;}
.cmx-video__frame iframe,.cmx-video__frame video{position:absolute;inset:0;width:100%;height:100%;border:0;display:block;}
</style>
<div id="cmx-video-src" style="display:none">
	<div class="cmx-video">
		<div class="cmx-video__t"><span class="mk"><svg viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg></span>動画で見る</div>
		<?php echo $embed; // phpcs:ignore ?>
	</div>
</div>
<script id="carmel-video-js">
(function(){
	function run(){
		var src = document.getElementById('cmx-video-src');
		if(!src) return;
		var card = src.firstElementChild;
		if(!card || card.getAttribute('data-cmx-placed')==='1') return;
		// 先頭セクション（画像＋ローン）の直後に差し込む
		var firstSec = document.querySelector('.custom-singular-template > .vc_section, .single-portfolio .vc_section');
		if(firstSec && firstSec.parentNode){
			card.setAttribute('data-cmx-placed','1');
			firstSec.parentNode.insertBefore(card, firstSec.nextSibling);
		}
	}
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', function(){ setTimeout(run,80); });
	else setTimeout(run,80);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_video_render', 20 );
}
