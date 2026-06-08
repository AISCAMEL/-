<?php
/**
 * ACF field group for the "case" post type (spec §8).
 *
 * Registered programmatically so the data layer travels with the theme.
 * If ACF (free or Pro) is active the fields appear in the editor; the theme
 * also reads the same meta keys directly, so it works even without ACF.
 *
 * Field meta keys:
 *   - case_industry  業種            (text)
 *   - case_metric_1  成果指標1       (text, e.g. 売上+150%)
 *   - case_metric_2  成果指標2       (text)
 *   - case_duration  開発期間        (text, e.g. 2週間)
 *   - case_features  利用機能        (textarea, one per line)
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the local ACF field group when ACF is available.
 */
function apprex_register_case_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_apprex_case',
			'title'    => __( '導入事例 詳細', 'apprex' ),
			'fields'   => array(
				array(
					'key'          => 'field_case_industry',
					'label'        => __( '業種', 'apprex' ),
					'name'         => 'case_industry',
					'type'         => 'text',
					'instructions' => __( '例：アパレルブランド', 'apprex' ),
				),
				array(
					'key'          => 'field_case_metric_1',
					'label'        => __( '成果指標1', 'apprex' ),
					'name'         => 'case_metric_1',
					'type'         => 'text',
					'instructions' => __( '例：売上+150%', 'apprex' ),
				),
				array(
					'key'          => 'field_case_metric_2',
					'label'        => __( '成果指標2', 'apprex' ),
					'name'         => 'case_metric_2',
					'type'         => 'text',
					'instructions' => __( '例：会員数10,000人（任意）', 'apprex' ),
				),
				array(
					'key'          => 'field_case_duration',
					'label'        => __( '開発期間', 'apprex' ),
					'name'         => 'case_duration',
					'type'         => 'text',
					'instructions' => __( '例：2週間', 'apprex' ),
				),
				array(
					'key'          => 'field_case_features',
					'label'        => __( '利用機能', 'apprex' ),
					'name'         => 'case_features',
					'type'         => 'textarea',
					'instructions' => __( '1行に1機能を記載', 'apprex' ),
					'rows'         => 4,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'case',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'apprex_register_case_fields' );

/**
 * Add a lightweight meta box fallback so editors can fill the fields even when
 * ACF is not installed. Only runs when ACF is absent.
 */
function apprex_case_metabox() {
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	add_meta_box( 'apprex_case_meta', __( '導入事例 詳細', 'apprex' ), 'apprex_case_metabox_render', 'case', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'apprex_case_metabox' );

/**
 * Render the fallback meta box.
 *
 * @param WP_Post $post Current post.
 */
function apprex_case_metabox_render( $post ) {
	wp_nonce_field( 'apprex_case_meta', 'apprex_case_nonce' );
	$fields = array(
		'case_industry' => __( '業種', 'apprex' ),
		'case_metric_1' => __( '成果指標1', 'apprex' ),
		'case_metric_2' => __( '成果指標2', 'apprex' ),
		'case_duration' => __( '開発期間', 'apprex' ),
	);
	echo '<div style="display:grid;gap:12px;max-width:640px">';
	foreach ( $fields as $key => $label ) {
		printf(
			'<label style="font-weight:600">%s<br><input type="text" name="%s" value="%s" style="width:100%%"></label>',
			esc_html( $label ),
			esc_attr( $key ),
			esc_attr( get_post_meta( $post->ID, $key, true ) )
		);
	}
	printf(
		'<label style="font-weight:600">%s<br><textarea name="case_features" rows="4" style="width:100%%">%s</textarea></label>',
		esc_html__( '利用機能（1行1機能）', 'apprex' ),
		esc_textarea( get_post_meta( $post->ID, 'case_features', true ) )
	);
	echo '</div>';
}

/**
 * Save the fallback meta box.
 *
 * @param int $post_id Post ID.
 */
function apprex_case_metabox_save( $post_id ) {
	if ( ! isset( $_POST['apprex_case_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_case_nonce'] ), 'apprex_case_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'case_industry', 'case_metric_1', 'case_metric_2', 'case_duration' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['case_features'] ) ) {
		update_post_meta( $post_id, 'case_features', sanitize_textarea_field( wp_unslash( $_POST['case_features'] ) ) );
	}
}
add_action( 'save_post_case', 'apprex_case_metabox_save' );
