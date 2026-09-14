<?php
/**
 * カーメル：店舗(shop)編集画面に「担当者・店舗情報」入力欄を追加
 * ---------------------------------------------------------------------------
 * [carmel_staff_shop] が読む項目を、ACF不要で入力できるようにする。
 *   担当者名 tantou_name / 役職 tantou_role / 顔写真 tantou_photo(添付ID)
 *   住所 address / 営業時間 hours / 定休日 closed
 * 顔写真はメディアアップロード対応。保存はそのまま post_meta へ。
 *
 * 導入 : WPCode → PHP Snippet → Run Everywhere（または管理画面のみ）→ 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_shop_fields_box' ) ) {

	/* メディアアップローダ読み込み（shop編集画面のみ） */
	add_action( 'admin_enqueue_scripts', function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $s && 'shop' === $s->post_type ) { wp_enqueue_media(); }
	} );

	/* メタボックス登録 */
	add_action( 'add_meta_boxes', function () {
		add_meta_box( 'carmelx_shop_fields', '担当者・店舗情報（詳細ページ表示用）', 'carmelx_shop_fields_box', 'shop', 'normal', 'high' );
	} );

	/* 入力欄の描画 */
	function carmelx_shop_fields_box( $post ) {
		wp_nonce_field( 'carmelx_shop_fields_save', 'carmelx_shop_fields_nonce' );
		$texts = array(
			'tantou_name' => '担当者名',
			'tantou_role' => '役職（例：店長 / 営業担当）',
			'address'     => '住所',
			'hours'       => '営業時間（例：10:00〜18:00）',
			'closed'      => '定休日（例：なし / 水曜）',
		);
		$photo_id  = (int) get_post_meta( $post->ID, 'tantou_photo', true );
		$photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';

		echo '<style>.cxsf p{margin:0 0 14px;}.cxsf label{display:block;font-weight:600;margin-bottom:4px;}.cxsf input[type=text]{width:100%;}'
			. '.cxsf-photo{display:flex;align-items:center;gap:12px;}.cxsf-photo img{width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #ccc;display:none;}'
			. '.cxsf-photo img[src]{display:block;}</style>';
		echo '<div class="cxsf">';

		// 顔写真
		echo '<p><label>担当者の顔写真</label><span class="cxsf-photo">';
		echo '<img class="cxsf-prev" ' . ( $photo_url ? 'src="' . esc_url( $photo_url ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="tantou_photo" class="cxsf-id" value="' . esc_attr( $photo_id ) . '">';
		echo '<button type="button" class="button cxsf-pick">画像を選択</button>';
		echo '<button type="button" class="button cxsf-clear">削除</button>';
		echo '</span></p>';

		// テキスト項目
		foreach ( $texts as $key => $label ) {
			$v = get_post_meta( $post->ID, $key, true );
			echo '<p><label for="cxsf_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
			echo '<input type="text" id="cxsf_' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $v ) . '"></p>';
		}
		echo '</div>';
		?>
		<script>
		jQuery(function($){
			var frame;
			$('.cxsf-pick').on('click', function(e){
				e.preventDefault();
				if(frame){ frame.open(); return; }
				frame = wp.media({ title:'担当者の顔写真を選択', library:{type:'image'}, button:{text:'この画像を使用'}, multiple:false });
				frame.on('select', function(){
					var a = frame.state().get('selection').first().toJSON();
					var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					$('.cxsf-id').val(a.id);
					$('.cxsf-prev').attr('src', url);
				});
				frame.open();
			});
			$('.cxsf-clear').on('click', function(e){
				e.preventDefault();
				$('.cxsf-id').val('');
				$('.cxsf-prev').removeAttr('src');
			});
		});
		</script>
		<?php
	}

	/* 保存 */
	add_action( 'save_post_shop', function ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST['carmelx_shop_fields_nonce'] ) || ! wp_verify_nonce( $_POST['carmelx_shop_fields_nonce'], 'carmelx_shop_fields_save' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		foreach ( array( 'tantou_name', 'tantou_role', 'address', 'hours', 'closed' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) { update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ); }
		}
		if ( isset( $_POST['tantou_photo'] ) ) {
			$id = (int) $_POST['tantou_photo'];
			if ( $id > 0 ) { update_post_meta( $post_id, 'tantou_photo', $id ); }
			else { delete_post_meta( $post_id, 'tantou_photo' ); }
		}
	} );
}
