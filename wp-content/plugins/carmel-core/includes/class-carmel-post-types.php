<?php
/**
 * Registers the 9 custom post types of the Carmel system.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Post_Types {

	/** @var Carmel_Post_Types|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Definition of every CPT keyed by post type slug.
	 *
	 * `cap_type` becomes the capability_type used by Carmel_Roles so the
	 * permission matrix and the CPTs stay in sync from a single source.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		return array(
			'carmel_deal'         => array(
				'label'    => '案件',
				'plural'   => '案件',
				'icon'     => 'dashicons-portfolio',
				'cap_type' => 'carmel_deal',
			),
			'carmel_store'        => array(
				'label'    => '加盟店',
				'plural'   => '加盟店',
				'icon'     => 'dashicons-store',
				'cap_type' => 'carmel_store',
			),
			'carmel_vehicle'      => array(
				'label'    => '在庫車両',
				'plural'   => '在庫車両',
				'icon'     => 'dashicons-car',
				'cap_type' => 'carmel_vehicle',
				'supports' => array( 'title', 'editor', 'thumbnail' ), // 車両画像
			),
			'carmel_document'     => array(
				'label'    => '書類',
				'plural'   => '書類',
				'icon'     => 'dashicons-media-document',
				'cap_type' => 'carmel_document',
			),
			'carmel_repayment'    => array(
				'label'    => '返済',
				'plural'   => '返済',
				'icon'     => 'dashicons-money-alt',
				'cap_type' => 'carmel_repayment',
			),
			'carmel_support'      => array(
				'label'      => 'サポート',
				'plural'     => 'サポートチケット',
				'icon'       => 'dashicons-sos',
				'cap_type'   => 'carmel_support',
				'rest_base'  => 'carmel-support',
			),
			'carmel_inspection'   => array(
				'label'    => '車検',
				'plural'   => '車検',
				'icon'     => 'dashicons-clipboard',
				'cap_type' => 'carmel_inspection',
			),
			'carmel_insurance'    => array(
				'label'    => '保険',
				'plural'   => '保険',
				'icon'     => 'dashicons-shield',
				'cap_type' => 'carmel_insurance',
			),
			'carmel_notify_log'   => array(
				'label'         => '通知ログ',
				'plural'        => '通知ログ',
				'icon'          => 'dashicons-megaphone',
				'cap_type'      => 'carmel_notify_log',
				'public'        => false,
				'supports'      => array( 'title' ),
				'hq_only_admin' => true, // 管理画面では本部のみが見る性質
			),
		);
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register every CPT from the definition table.
	 */
	public function register_post_types() {
		foreach ( self::definitions() as $slug => $def ) {
			register_post_type( $slug, $this->build_args( $slug, $def ) );
		}
	}

	/**
	 * Build register_post_type() args from a compact definition.
	 *
	 * @param string $slug Post type slug.
	 * @param array  $def  Definition row.
	 * @return array
	 */
	private function build_args( $slug, array $def ) {
		$singular = $def['label'];
		$plural   = isset( $def['plural'] ) ? $def['plural'] : $def['label'];
		$cap_type = $def['cap_type'];
		$public   = isset( $def['public'] ) ? (bool) $def['public'] : false;

		$labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => '新規追加',
			'add_new_item'       => $singular . 'を追加',
			'edit_item'          => $singular . 'を編集',
			'new_item'           => '新しい' . $singular,
			'view_item'          => $singular . 'を表示',
			'search_items'       => $singular . 'を検索',
			'not_found'          => $singular . 'が見つかりません',
			'not_found_in_trash' => 'ゴミ箱に' . $singular . 'はありません',
			'all_items'          => $plural . '一覧',
		);

		return array(
			'labels'              => $labels,
			'public'              => $public,
			'publicly_queryable'  => $public,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'rest_base'           => isset( $def['rest_base'] ) ? $def['rest_base'] : $slug,
			'menu_icon'           => isset( $def['icon'] ) ? $def['icon'] : 'dashicons-admin-post',
			'hierarchical'        => false,
			'has_archive'         => $public,
			'exclude_from_search' => ! $public,
			'supports'            => isset( $def['supports'] ) ? $def['supports'] : array( 'title', 'editor' ),
			'capability_type'     => array( $cap_type, $cap_type . 's' ),
			'map_meta_cap'        => true,
		);
	}
}
